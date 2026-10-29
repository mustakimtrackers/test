<?php
$pageTitle = 'Add Supplier';
require_once __DIR__ . '/../../includes/auth_check.php';

// Process Form Submission (BEFORE including header to avoid "headers already sent" error)
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $errors[] = 'The uploaded file is too large. Please select a smaller image.';
    } elseif (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Your session may have expired, please refresh the page.';
    } else {
        $name = cleanInput($_POST['name'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $phone = cleanInput($_POST['phone'] ?? '');
        $company = cleanInput($_POST['company_name'] ?? '');
        $address = cleanInput($_POST['address'] ?? '');
        $emailOptIn = isset($_POST['email_opt_in']) ? 1 : 0;
        $openingBalance = (float)($_POST['opening_balance'] ?? 0);
        
        if (empty($name)) {
            $errors[] = 'Supplier name is required';
        }
        
        if (empty($errors)) {
            $imagePath = null;
            if (!empty($_FILES['image']['name'])) {
                $upload = handleFileUpload($_FILES['image'], 'contacts');
                if ($upload) {
                    $imagePath = $upload;
                }
            }

            $stmt = $conn->prepare("INSERT INTO contacts (type, name, email, phone, company_name, address, opening_balance, balance, image, email_opt_in) VALUES ('supplier', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssddsi", $name, $email, $phone, $company, $address, $openingBalance, $openingBalance, $imagePath, $emailOptIn);
            
            if ($stmt->execute()) {
                $id = $conn->insert_id;
                logActivity('create', 'contacts', $id, null, null, "Created new supplier: $name");
                setFlashMessage('success', 'Supplier created successfully!');
                redirect(url('/modules/suppliers/list.php'));
            } else {
                $errors[] = 'Failed to create supplier';
            }
            $stmt->close();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-truck"></i> Add Supplier</h1>
        <a href="<?= url('/modules/suppliers/list.php') ?>" class="btn btn-secondary">
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
                                    <label for="name" class="form-label">Supplier Name *</label>
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
                                        Receive purchase orders & alerts via email
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
                            <i class="bi bi-save"></i> Create Supplier
                        </button>
                        <a href="<?= url('/modules/suppliers/list.php') ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
