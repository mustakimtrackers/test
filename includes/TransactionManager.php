<?php
/**
 * Transaction Manager
 * Handles all financial ledger operations
 */

class TransactionManager {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    /**
     * Record a financial transaction
     * 
     * @param int $accountId
     * @param string $type 'credit' (Money In) or 'debit' (Money Out)
     * @param float $amount
     * @param string $refType 'sale', 'purchase', 'expense', 'capital_injection', 'capital_withdrawal', 'transfer'
     * @param int $refId
     * @param string $description
     * @param int $userId
     * @param string|null $transactionDate
     * @return bool
     * @throws Exception
     */
    public function recordTransaction($accountId, $type, $amount, $refType, $refId, $description, $userId, $transactionDate = null) {
        if ($amount <= 0) {
            return false; // Or throw exception
        }

        $dateToUse = $transactionDate ? $transactionDate : date('Y-m-d');
        $txnStartedHere = false;

        try {
            // Check if a transaction is already active
            $inTxnRes = $this->conn->query("SELECT @@in_transaction");
            $inTxn = $inTxnRes ? $inTxnRes->fetch_row()[0] : 0;
            
            if (!$inTxn) {
                $this->conn->begin_transaction();
                $txnStartedHere = true;
            }

            // 1. Balance Validation for Debit (Money Out)
            if ($type === 'debit') {
                $checkStmt = $this->conn->prepare("SELECT balance FROM financial_accounts WHERE id = ? FOR UPDATE");
                if (!$checkStmt) throw new Exception("Check Prepare failed: " . $this->conn->error);
                
                $checkStmt->bind_param("i", $accountId);
                $checkStmt->execute();
                $accRes = $checkStmt->get_result();
                $acc = $accRes->fetch_assoc();
                $checkStmt->close();

                if (!$acc) {
                    throw new Exception("Financial account not found.");
                }
                if ($acc['balance'] < $amount) {
                    throw new Exception("Insufficient balance in account. Available: " . number_format($acc['balance'], 2) . ", Required: " . number_format($amount, 2));
                }
            }

            // 2. Insert into financial_transactions table
            $stmt = $this->conn->prepare("
                INSERT INTO financial_transactions 
                (financial_account_id, transaction_type, amount, reference_type, reference_id, description, transaction_date, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("isdsissi", $accountId, $type, $amount, $refType, $refId, $description, $dateToUse, $userId);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();

            // 3. Update Account Balance
            $balanceSql = "";
            if ($type === 'credit') {
                $balanceSql = "UPDATE financial_accounts SET balance = balance + ? WHERE id = ?";
            } else {
                $balanceSql = "UPDATE financial_accounts SET balance = balance - ? WHERE id = ?";
            }

            $balStmt = $this->conn->prepare($balanceSql);
            if (!$balStmt) {
                throw new Exception("Balance Prepare failed: " . $this->conn->error);
            }

            $balStmt->bind_param("di", $amount, $accountId);
            
            if (!$balStmt->execute()) {
                throw new Exception("Balance Update failed: " . $balStmt->error);
            }
            $balStmt->close();

            // Commit if we started the transaction here
            if ($txnStartedHere) {
                $this->conn->commit();
            }

            // --- ADVANCED ACCOUNTING LAYER (Non-Destructive) ---
            // Push to double-entry journal silently
            try {
                $this->generateAutomatedJournal($type, $amount, $refType, $refId, $description, $dateToUse, $userId);
            } catch (Exception $e) {
                // We log but don't break the main transaction
                error_log("Failed to generate automated journal: " . $e->getMessage());
            }
            // ----------------------------------------------------

            return true;

        } catch (Exception $e) {
            // Rollback if we started the transaction here
            if ($txnStartedHere) {
                $this->conn->rollback();
            }
            throw $e; // Re-throw to caller
        }
    }

    private function getAccountIdByCode($code) {
        static $cache = [];
        if (isset($cache[$code])) return $cache[$code];
        $stmt = $this->conn->prepare("SELECT id FROM chart_of_accounts WHERE code = ?");
        if (!$stmt) return null;
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($res) {
            $cache[$code] = $res['id'];
            return $res['id'];
        }
        return null;
    }

    /**
     * ADVANCED ACCOUNTING: Generate Automated Journal from Legacy Transaction
     */
    private function generateAutomatedJournal($type, $amount, $refType, $refId, $description, $date, $userId) {
        $debit_account = null;
        $credit_account = null;
        
        // Simple mapping based on cash movement
        if ($refType === 'sale' && $type === 'credit') {
            // Received money from customer
            $debit_account = $this->getAccountIdByCode('1000'); // Cash
            $credit_account = $this->getAccountIdByCode('1200'); // Accounts Receivable
        } elseif ($refType === 'purchase' && $type === 'debit') {
            // Paid money to supplier
            $debit_account = $this->getAccountIdByCode('2000') ?: $this->getAccountIdByCode('2100'); // Accounts Payable
            $credit_account = $this->getAccountIdByCode('1000'); // Cash
        } elseif ($refType === 'expense' && $type === 'debit') {
            $debit_account = $this->getAccountIdByCode('5100'); // General Expenses
            $credit_account = $this->getAccountIdByCode('1000'); // Cash
        } elseif ($refType === 'capital_injection' && $type === 'credit') {
            $debit_account = $this->getAccountIdByCode('1000'); // Cash
            $credit_account = $this->getAccountIdByCode('3100'); // Owners Capital
        } elseif ($refType === 'capital_withdrawal' && $type === 'debit') {
            $debit_account = $this->getAccountIdByCode('3100'); // Owners Capital
            $credit_account = $this->getAccountIdByCode('1000'); // Cash
        }
        
        $branch_id = $_SESSION['branch_id'] ?? 1;

        if ($debit_account && $credit_account) {
            $lines = [
                ['account_id' => $debit_account, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $credit_account, 'debit' => 0, 'credit' => $amount]
            ];
            $this->createJournalEntry($date, $refType, $refId, $description, $lines, $branch_id);
        }
    }

    /**
     * ADVANCED ACCOUNTING: Create a direct Journal Entry
     */
    public function createJournalEntry($date, $refType, $refId, $description, $lines, $branch_id = 1) {
        // Validate Debits = Credits
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($lines as $line) {
            $totalDebit += (float)($line['debit'] ?? 0);
            $totalCredit += (float)($line['credit'] ?? 0);
        }
        
        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new Exception("Journal Entry Unbalanced. Debit: $totalDebit, Credit: $totalCredit");
        }

        // Check Period Lock
        $periodStmt = $this->conn->prepare("SELECT is_closed FROM financial_periods WHERE ? BETWEEN start_date AND end_date LIMIT 1");
        $periodStmt->bind_param("s", $date);
        $periodStmt->execute();
        $res = $periodStmt->get_result()->fetch_assoc();
        $periodStmt->close();
        
        if ($res && $res['is_closed']) {
            throw new Exception("Cannot record transaction. The financial period for $date is locked/closed.");
        }

        $stmt = $this->conn->prepare("INSERT INTO journal_entries (date, reference_type, reference_id, description, branch_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisi", $date, $refType, $refId, $description, $branch_id);
        $stmt->execute();
        $journal_id = $stmt->insert_id;
        $stmt->close();

        $lineStmt = $this->conn->prepare("INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?, ?, ?, ?)");
        foreach ($lines as $line) {
            $debit = $line['debit'] ?? 0;
            $credit = $line['credit'] ?? 0;
            if ($debit > 0 || $credit > 0) {
                $lineStmt->bind_param("iidd", $journal_id, $line['account_id'], $debit, $credit);
                $lineStmt->execute();
            }
        }
        $lineStmt->close();
        return $journal_id;
    }
}
?>
