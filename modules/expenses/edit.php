<?php
$pageTitle = 'Edit Expense';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

if ($id <= 0) {
    setFlashMessage('error', 'Invalid expense ID');
    redirect(url('/modules/expenses/list.php'));
}

// Fetch expense logic
$stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$expense = $result->fetch_assoc();
$stmt->close();

if (!$expense) {
    setFlashMessage('error', 'Expense not found');
    redirect(url('/modules/expenses/list.php'));
}

// Process Form Submission (BEFORE including header)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $category = cleanInput($_POST['category'] ?? '');
        $amount = !empty($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $description = cleanInput($_POST['description'] ?? '');
        $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
        
        if (empty($category)) {
            $errors[] = 'Category is required';
        }
        if ($amount <= 0) {
            $errors[] = 'Amount must be greater than 0';
        }
        
        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                // 1. Fetch OLD Transaction
                $oldTransStmt = $conn->prepare("SELECT id, financial_account_id, amount, transaction_type FROM financial_transactions WHERE reference_type = 'expense' AND reference_id = ? ORDER BY id DESC LIMIT 1");
                $oldTransStmt->bind_param("i", $id);
                $oldTransStmt->execute();
                $oldTransResult = $oldTransStmt->get_result();
                $oldTrans = $oldTransResult->fetch_assoc();
                $oldTransStmt->close();

                require_once __DIR__ . '/../../includes/TransactionManager.php';
                $tm = new TransactionManager($conn);

                if ($oldTrans) {
                    // Revert OLD balance
                    if ($oldTrans['transaction_type'] === 'debit') {
                        $revertStmt = $conn->prepare("UPDATE financial_accounts SET balance = balance + ? WHERE id = ?");
                        $revertStmt->bind_param("di", $oldTrans['amount'], $oldTrans['financial_account_id']);
                        $revertStmt->execute();
                        $revertStmt->close();
                    } elseif ($oldTrans['transaction_type'] === 'credit') {
                        $revertStmt = $conn->prepare("UPDATE financial_accounts SET balance = balance - ? WHERE id = ?");
                        $revertStmt->bind_param("di", $oldTrans['amount'], $oldTrans['financial_account_id']);
                        $revertStmt->execute();
                        $revertStmt->close();
                    }
                    
                    // Delete OLD transaction record
                    $delStmt = $conn->prepare("DELETE FROM financial_transactions WHERE id = ?");
                    $delStmt->bind_param("i", $oldTrans['id']);
                    $delStmt->execute();
                    $delStmt->close();
                }

                // 2. Update Expense Record
                $stmt = $conn->prepare("UPDATE expenses SET category = ?, amount = ?, expense_date = ?, description = ? WHERE id = ?");
                $stmt->bind_param("sdssi", $category, $amount, $expenseDate, $description, $id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update expense record");
                }
                $stmt->close();

                // 3. Create NEW Transaction (Debit new amount from selected account)
                $oldAccountId = $oldTrans ? $oldTrans['financial_account_id'] : 0;
                $newAccountId = !empty($_POST['financial_account_id']) ? (int)$_POST['financial_account_id'] : (int)$oldAccountId;
                if ($newAccountId) {
                    $tm->recordTransaction(
                        $newAccountId,
                        'debit',
                        $amount,
                        'expense',
                        $id,
                        "Expense: $category - $description (Updated)",
                        $_SESSION['user_id']
                    );
                }

                $conn->commit();
                setFlashMessage('success', 'Expense updated and account adjusted successfully!');
                redirect(url('/modules/expenses/list.php'));

            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "Update failed: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-pencil-square"></i> Edit Expense</h1>
        <a href="<?= url('/modules/expenses/list.php') ?>" class="btn btn-secondary">
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
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Category *</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Rent" <?= $expense['category'] === 'Rent' ? 'selected' : '' ?>>Rent</option>
                                    <option value="Utilities" <?= $expense['category'] === 'Utilities' ? 'selected' : '' ?>>Utilities</option>
                                    <option value="Salaries" <?= $expense['category'] === 'Salaries' ? 'selected' : '' ?>>Salaries</option>
                                    <option value="Office Supplies" <?= $expense['category'] === 'Office Supplies' ? 'selected' : '' ?>>Office Supplies</option>
                                    <option value="Marketing" <?= $expense['category'] === 'Marketing' ? 'selected' : '' ?>>Marketing</option>
                                    <option value="Transportation" <?= $expense['category'] === 'Transportation' ? 'selected' : '' ?>>Transportation</option>
                                    <option value="Other" <?= $expense['category'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="expense_date" class="form-label">Date *</label>
                                <input type="date" class="form-control" id="expense_date" name="expense_date" 
                                       value="<?= $expense['expense_date'] ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" class="form-control" id="amount" name="amount" 
                                       step="0.01" min="0.01" value="<?= $expense['amount'] ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Payment Source (Account)</label>
                            <select class="form-select" name="financial_account_id" required>
                                <?php 
                                // Get currently linked account
                                $currAccStmt = $conn->prepare("SELECT financial_account_id FROM financial_transactions WHERE reference_type = 'expense' AND reference_id = ? ORDER BY id DESC LIMIT 1");
                                $currAccStmt->bind_param("i", $id);
                                $currAccStmt->execute();
                                $currAccResult = $currAccStmt->get_result();
                                $currAccId = 0;
                                if ($currAccResult->num_rows > 0) {
                                    $currAccId = $currAccResult->fetch_assoc()['financial_account_id'];
                                }
                                $currAccStmt->close();

                                $accs = $conn->query("SELECT id, account_name, balance FROM financial_accounts WHERE is_active=1");
                                while ($ac = $accs->fetch_assoc()): 
                                ?>
                                    <option value="<?= $ac['id'] ?>" <?= $ac['id'] == $currAccId ? 'selected' : '' ?>>
                                        <?= escape($ac['account_name']) ?> (<?= formatCurrency($ac['balance']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= escape($expense['description']) ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Expense
                        </button>
                        <a href="<?= url('/modules/expenses/list.php') ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
