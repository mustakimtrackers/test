<?php
$pageTitle = 'Add Customer';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

require_once __DIR__ . '/../../includes/Validator.php';

// Process Form Submission (BEFORE including header to avoid "headers already sent" error)
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $errors[] = 'The uploaded file is too large. Please select a smaller image.';
    } elseif (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Your session may have expired, please refresh the page.';
    } else {
        // Data
        $data = [
            'type' => $_GET['type'] ?? 'customer', // Default to customer if not specified
            'name' => $_POST['name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'email_opt_in' => isset($_POST['email_opt_in']) ? 1 : 0,
            'phone' => $_POST['phone'] ?? '',
            'company_name' => $_POST['company_name'] ?? '',
            'address' => $_POST['address'] ?? '',
            'opening_balance' => $_POST['opening_balance'] ?? 0
        ];
        
        // Ensure type is valid
        if (!in_array($data['type'], ['customer', 'supplier', 'investor'])) {
            $data['type'] = 'customer';
        }

        // Validation Rules
        $rules = [
            'name' => 'required|min:3|max:50',
            'phone' => 'required|phone',
            'email' => 'email'
        ];

        $validator = new Validator($conn);

        if ($validator->validate($data, $rules)) {
            $type = $data['type'];
            $name = cleanInput($data['name']);
            $email = cleanInput($data['email']);
            $phone = cleanInput($data['phone']);
            $company = cleanInput($data['company_name']);
            $address = cleanInput($data['address']);
            $openingBalance = (float)$data['opening_balance'];
            
            $imagePath = null;
            if (!empty($_FILES['image']['name'])) {
                $upload = handleFileUpload($_FILES['image'], 'contacts');
                if ($upload) {
                    $imagePath = $upload;
                }
            }

            $emailOptIn = $data['email_opt_in'];

            $stmt = $conn->prepare("INSERT INTO contacts (type, name, email, phone, company_name, address, opening_balance, balance, image, email_opt_in) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssddsi", $type, $name, $email, $phone, $company, $address, $openingBalance, $openingBalance, $imagePath, $emailOptIn);

            if ($stmt->execute()) {
                $id = $stmt->insert_id;
                
                // Log Activity
                logActivity('create', 'contacts', $id, null, null, "Created new $type: $name");
                
                // Redirect based on type
                if ($type == 'investor') {
                    redirect(url('/modules/capital/form.php?type=injection'));
                } else {
                    redirect(url('/modules/customers/list.php'));
                }
            } else {
                $errors[] = 'Failed to create contact: ' . $conn->error;
            }
            $stmt->close();
        } else {
            $errors = array_values($validator->getErrors());
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-person-plus"></i> Add Customer</h1>
        <a href="<?= url('/modules/customers/list.php') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= escape($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="row">
                            <div class="col-md-9">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="image" class="form-label">Image</label>
                                    <input type="file" class="form-control form-control-sm" name="image" id="image" accept="image/*">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="email_opt_in" id="email_opt_in" value="1" checked>
                                    <label class="form-check-label text-muted small" for="email_opt_in">
                                        Receive invoices & alerts via email
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="company_name" name="company_name">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="opening_balance" class="form-label">Opening Balance (Due)</label>
                                <div class="input-group">
                                    <span class="input-group-text">৳</span>
                                    <input type="number" class="form-control" id="opening_balance" name="opening_balance" step="0.01" value="0.00">
                                </div>
                                <div class="form-text text-danger">Start with 0 if new. This balance does NOT affect Cash/Bank. (এটা কেবল পূর্বের বকেয়া, ক্যাশ/ব্যাংক থেকে কাটবে না)</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Create Customer
                        </button>
                        <a href="<?= url('/modules/customers/list.php') ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
