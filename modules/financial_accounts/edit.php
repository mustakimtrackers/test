<?php
/**
 * Financial Accounts - Edit
 * Update account details
 */

$pageTitle = 'Edit Financial Account';
require_once __DIR__ . '/../../includes/auth_check.php';

$accountId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($accountId <= 0) {
    setFlashMessage('error', 'Invalid account ID');
    redirect(url('/modules/financial_accounts/list.php'));
}

// Get account
$stmt = $conn->prepare("SELECT * FROM financial_accounts WHERE id = ?");
$stmt->bind_param("i", $accountId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$account) {
    setFlashMessage('error', 'Account not found');
    redirect(url('/modules/financial_accounts/list.php'));
}

// Process Form
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $accountName = cleanInput($_POST['account_name'] ?? '');
        $accountType = cleanInput($_POST['account_type'] ?? '');
        
        if (empty($accountName)) {
            $errors[] = 'Account name is required';
        }
        if (empty($accountType)) {
            $errors[] = 'Account type is required';
        }
        
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE financial_accounts SET account_name = ?, account_type = ? WHERE id = ?");
            $stmt->bind_param("ssi", $accountName, $accountType, $accountId);
            
            if ($stmt->execute()) {
                logActivity('update', 'financial_accounts', $accountId, "Updated account: $accountName");
                setFlashMessage('success', 'Account updated successfully!');
                redirect(url('/modules/financial_accounts/list.php'));
            } else {
                $errors[] = 'Failed to update account';
            }
            $stmt->close();
        }
    }
} else {
    // Populate form with existing data
    $_POST['account_name'] = $account['account_name'];
    $_POST['account_type'] = $account['account_type'];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title">
                    <i class="bi bi-pencil"></i> Edit Financial Account
                </h1>
                <a href="<?= url('/modules/financial_accounts/list.php') ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 col-lg-6 mx-auto">
            <div class="card">
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= escape($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Account Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="account_name" 
                                   value="<?= escape($_POST['account_name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Account Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="account_type" required>
                                <option value="">-- Select Type --</option>
                                <option value="cash" <?= $_POST['account_type'] === 'cash' ? 'selected' : '' ?>>
                                    💵 Cash
                                </option>
                                <option value="bank" <?= $_POST['account_type'] === 'bank' ? 'selected' : '' ?>>
                                    🏦 Bank Account
                                </option>
                                <option value="mobile_banking" <?= $_POST['account_type'] === 'mobile_banking' ? 'selected' : '' ?>>
                                    📱 Mobile Banking
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Current Balance</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="text" class="form-control" value="<?= formatCurrency($account['balance']) ?>" disabled>
                            </div>
                            <small class="text-muted">Balance is calculated from transactions and cannot be edited directly</small>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="<?= url('/modules/financial_accounts/list.php') ?>" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Update Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
