<?php
$pageTitle = 'Edit Supplier';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

requireAdmin(); // Strict Rule: Only Admin can Edit

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

if ($id <= 0) {
    setFlashMessage('error', 'Invalid supplier ID');
    redirect(url('/modules/suppliers/list.php'));
}

// Fetch supplier details
$stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ? AND type = 'supplier'");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$supplier = $result->fetch_assoc();
$stmt->close();

if (!$supplier) {
    setFlashMessage('error', 'Supplier not found');
    redirect(url('/modules/suppliers/list.php'));
}

// Process Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
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
            $newImageUploaded = false;
            $imagePath = null;
            if (!empty($_FILES['image']['name'])) {
                $upload = handleFileUpload($_FILES['image'], 'contacts');
                if ($upload) {
                    $imagePath = $upload;
                    $newImageUploaded = true;
                }
            }

            // First, get the old opening balance
            $oldOpStmt = $conn->prepare("SELECT opening_balance, balance FROM contacts WHERE id = ?");
            $oldOpStmt->bind_param("i", $id);
            $oldOpStmt->execute();
            $oldData = $oldOpStmt->get_result()->fetch_assoc();
            $oldOpStmt->close();
            
            $balanceDiff = $openingBalance - (float)$oldData['opening_balance'];

            if ($imagePath) {
                $stmt = $conn->prepare("UPDATE contacts SET name = ?, email = ?, phone = ?, company_name = ?, address = ?, opening_balance = ?, balance = balance + ?, image = ?, email_opt_in = ? WHERE id = ?");
                $stmt->bind_param("sssssddsii", $name, $email, $phone, $company, $address, $openingBalance, $balanceDiff, $imagePath, $emailOptIn, $id);
            } else {
                $stmt = $conn->prepare("UPDATE contacts SET name = ?, email = ?, phone = ?, company_name = ?, address = ?, opening_balance = ?, balance = balance + ?, email_opt_in = ? WHERE id = ?");
                $stmt->bind_param("sssssddii", $name, $email, $phone, $company, $address, $openingBalance, $balanceDiff, $emailOptIn, $id);
            }
            
            if ($stmt->execute()) {
                if ($newImageUploaded && !empty($supplier['image'])) {
                    deleteUploadedFile($supplier['image']);
                }
                setFlashMessage('success', 'Supplier updated successfully!');
                redirect(url('/modules/suppliers/list.php'));
            } else {
                $errors[] = 'Failed to update supplier';
            }
            $stmt->close();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-pencil-square"></i> Edit Supplier</h1>
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
                        
                        <div class="row mb-4">
                            <div class="col-md-12 text-center">
                                <label for="image" class="form-label d-block fw-bold position-relative cursor-pointer">
                                    <?php if (!empty($supplier['image'])): ?>
                                        <img src="<?= url($supplier['image']) ?>" alt="Profile" class="rounded-circle mb-3 border shadow-sm" style="width: 100px; height: 100px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle mb-3 border shadow-sm d-inline-flex align-items-center justify-content-center bg-light text-secondary" style="width: 100px; height: 100px; font-size: 2.5rem;">
                                            <i class="bi bi-person"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="small text-muted mb-2">Change Image</div>
                                </label>
                                <input type="file" class="form-control form-control-sm w-50 mx-auto" name="image" id="image" accept="image/*">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Supplier Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= escape($supplier['name']) ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= escape($supplier['email']) ?>">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="email_opt_in" id="email_opt_in" value="1" <?= (isset($supplier['email_opt_in']) && $supplier['email_opt_in']) ? 'checked' : '' ?>>
                                    <label class="form-check-label text-muted small" for="email_opt_in">
                                        Receive purchase orders & alerts via email
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?= escape($supplier['phone']) ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                       value="<?= escape($supplier['company_name']) ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="opening_balance" class="form-label">Opening Balance (Due)</label>
                                <div class="input-group">
                                    <span class="input-group-text">৳</span>
                                    <input type="number" class="form-control" id="opening_balance" name="opening_balance" 
                                           step="0.01" value="<?= escape($supplier['opening_balance'] ?? 0) ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?= escape($supplier['address']) ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Supplier
                        </button>
                        <a href="<?= url('/modules/suppliers/list.php') ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
