<?php
/**
 * Create Financial Account
 */
$pageTitle = 'Add Financial Account';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/Validator.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $name = cleanInput($_POST['account_name']);
        $type = cleanInput($_POST['account_type']);
        $number = cleanInput($_POST['account_number']);
        $initialBalance = (float)$_POST['initial_balance'];

        if (empty($name)) $errors[] = "Account Name is required";
        
        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO financial_accounts (account_name, account_type, account_number, balance) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssd", $name, $type, $number, $initialBalance);
            if ($stmt->execute()) {
                setFlashMessage('success', 'Account created successfully');
                redirect('index.php');
            } else {
                $errors[] = "Database Error: " . $conn->error;
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title">Add Account</h1>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $e): ?><div><?= $e ?></div><?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <div class="mb-3">
                            <label class="form-label">Account Name *</label>
                            <input type="text" class="form-control" name="account_name" placeholder="e.g. City Bank Gulshan" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Type *</label>
                            <select class="form-select" name="account_type">
                                <option value="bank">Bank Account</option>
                                <option value="mobile_banking">Mobile Banking (Bkash/Nagad)</option>
                                <option value="cash">Cash Drawer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Number (Optional)</label>
                            <input type="text" class="form-control" name="account_number" placeholder="e.g. 123-456-789">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Initial Balance (Opening)</label>
                            <input type="number" class="form-control" name="initial_balance" value="0.00" step="0.01">
                            <div class="form-text">If this is an existing account, enter current balance.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Create Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
