<?php
/**
 * Financial Accounts - Create
 * Add new financial account
 */

$pageTitle = 'Add Financial Account';
require_once __DIR__ . '/../../includes/auth_check.php';

// Process Form
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $accountName = cleanInput($_POST['account_name'] ?? '');
        $accountType = cleanInput($_POST['account_type'] ?? '');
        $initialBalance = !empty($_POST['initial_balance']) ? (float)$_POST['initial_balance'] : 0;
        
        if (empty($accountName)) {
            $errors[] = 'Account name is required';
        }
        if (empty($accountType)) {
            $errors[] = 'Account type is required';
        }
        
        if (empty($errors)) {
            // Create account with balance = 0 (TransactionManager will update it)
            $stmt = $conn->prepare("INSERT INTO financial_accounts (account_name, account_type, balance, is_active) VALUES (?, ?, 0, 1)");
            $stmt->bind_param("ss", $accountName, $accountType);
            
            if ($stmt->execute()) {
                $accountId = $stmt->insert_id;
                
                // If initial balance != 0, create opening balance transaction
                // TransactionManager will automatically update the account balance
                if ($initialBalance != 0) {
                    require_once __DIR__ . '/../../includes/TransactionManager.php';
                    $tm = new TransactionManager($conn);
                    $userId = $_SESSION['user_id'];
                    
                    try {
                        $tm->recordTransaction(
                            $accountId,
                            $initialBalance >= 0 ? 'credit' : 'debit',
                            abs($initialBalance),
                            'opening_balance',
                            $accountId,
                            "Opening Balance for $accountName",
                            $userId
                        );
                    } catch (Exception $e) {
                        // Log error but don't fail account creation
                    }
                }
                
                logActivity('create', 'financial_accounts', $accountId, "Created account: $accountName");
                setFlashMessage('success', 'Financial account created successfully!');
                redirect(url('/modules/financial_accounts/list.php'));
            } else {
                $errors[] = 'Failed to create account';
            }
            $stmt->close();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title">
                    <i class="bi bi-plus-circle"></i> Add Financial Account
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
                                   placeholder="e.g., Cash Drawer, DBBL Bank, bKash Business"
                                   value="<?= escape($_POST['account_name'] ?? '') ?>" required>
                            <small class="text-muted">Enter a descriptive name for this account</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Account Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="account_type" required>
                                <option value="">-- Select Type --</option>
                                <option value="cash" <?= (($_POST['account_type'] ?? '') === 'cash') ? 'selected' : '' ?>>
                                    💵 Cash (Physical money in drawer/vault)
                                </option>
                                <option value="bank" <?= (($_POST['account_type'] ?? '') === 'bank') ? 'selected' : '' ?>>
                                    🏦 Bank Account (Traditional banking)
                                </option>
                                <option value="mobile_banking" <?= (($_POST['account_type'] ?? '') === 'mobile_banking') ? 'selected' : '' ?>>
                                    📱 Mobile Banking (bKash, Nagad, Rocket)
                                </option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Initial Balance (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" class="form-control" name="initial_balance" 
                                       step="0.01" value="<?= escape($_POST['initial_balance'] ?? '0') ?>"
                                       placeholder="0.00">
                            </div>
                            <small class="text-muted">
                                Enter the current balance if migrating from existing system. Leave 0 for new account.
                            </small>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Note:</strong> The initial balance will be recorded as an opening balance transaction.
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="<?= url('/modules/financial_accounts/list.php') ?>" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Create Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
