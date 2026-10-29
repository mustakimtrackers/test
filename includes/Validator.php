<?php
/**
 * Validator Class
 * Handles server-side validation for forms
 */

class Validator {
    private $db;
    private $errors = [];

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    /**
     * Validate data against rules
     * @param array $data Input data ($_POST)
     * @param array $rules Validation rules ['field' => 'required|email|unique:table,column']
     * @return bool
     */
    public function validate($data, $rules) {
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $rulesArray = explode('|', $ruleString);
            $value = trim($data[$field] ?? '');
            $label = ucwords(str_replace('_', ' ', $field));

            foreach ($rulesArray as $rule) {
                // Parse rule with parameters (e.g., unique:users,email)
                $params = [];
                if (strpos($rule, ':') !== false) {
                    [$ruleName, $paramString] = explode(':', $rule);
                    $params = explode(',', $paramString);
                    $rule = $ruleName;
                }

                // Check rules
                if ($rule === 'required' && empty($value)) {
                    $this->addError($field, "$label is required.");
                }
                elseif (!empty($value)) {
                    switch ($rule) {
                        case 'email':
                            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $this->addError($field, "$label must be a valid email address.");
                            }
                            break;
                        
                        case 'numeric':
                            if (!is_numeric($value)) {
                                $this->addError($field, "$label must be a number.");
                            }
                            break;

                        case 'min':
                            if (strlen($value) < $params[0]) {
                                $this->addError($field, "$label must be at least {$params[0]} characters.");
                            }
                            break;

                        case 'max':
                            if (strlen($value) > $params[0]) {
                                $this->addError($field, "$label must not exceed {$params[0]} characters.");
                            }
                            break;
                        
                        case 'phone':
                            // Bangladesh phone validation (11 digits, starts with 01)
                            if (!preg_match('/^01[3-9]\d{8}$/', $value)) {
                                $this->addError($field, "$label must be a valid 11-digit BD phone number.");
                            }
                            break;

                        case 'unique':
                            $table = $params[0];
                            $column = $params[1];
                            $excludeId = $params[2] ?? null;

                            if ($this->checkUnique($table, $column, $value, $excludeId)) {
                                $this->addError($field, "$label already exists.");
                            }
                            break;
                    }
                }
            }
        }

        return empty($this->errors);
    }

    private function checkUnique($table, $column, $value, $excludeId = null) {
        // SQL Injection Protection (Mass Assignment)
        $allowedTables = ['users', 'products', 'categories', 'suppliers', 'contacts', 'units', 'financial_accounts'];
        $allowedColumns = ['email', 'sku', 'slug', 'name', 'phone', 'account_number'];
        
        if (!in_array($table, $allowedTables) || !in_array($column, $allowedColumns)) {
            throw new Exception("Invalid table/column for unique check");
        }

        $sql = "SELECT id FROM `$table` WHERE `$column` = ?";
        $types = "s";
        $params = [$value];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $types .= "i";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }

    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getFirstError() {
        return reset($this->errors);
    }
}
?>
