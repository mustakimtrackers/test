<?php
$pageTitle = 'Add Expense';
require_once __DIR__ . '/../../includes/auth_check.php';

// Process Form Submission (BEFORE including header)
$errors = [];
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
            $userId = $_SESSION['user_id'];
            $branchId = $_SESSION['branch_id'] ?? 1;
            $stmt = $conn->prepare("INSERT INTO expenses (category, amount, expense_date, description, branch_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sdssi", $category, $amount, $expenseDate, $description, $branchId);
            
            if ($stmt->execute()) {
                $expenseId = $stmt->insert_id;

                // --- ACCOUNTING INTEGRATION ---
                require_once __DIR__ . '/../../includes/TransactionManager.php';
                $tm = new TransactionManager($conn);
                $accountId = (int)$_POST['financial_account_id'];

                try {
                    // Expenses are DEBIT (Money Out)
                    $tm->recordTransaction(
                        $accountId, 
                        'debit', 
                        $amount, 
                        'expense', 
                        $expenseId, 
                        "Expense: $category - $description",
                        $userId
                    );
                    setFlashMessage('success', 'Expense created & deducted from account successfully!');
                    redirect(url('/modules/expenses/list.php'));

                } catch (Exception $e) {
                    $errors[] = "Accounting Error: " . $e->getMessage();
                }
            } else {
                $errors[] = 'Failed to create expense';
            }
            $stmt->close();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-wallet2"></i> Add Expense</h1>
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
                                    <option value="Rent">Rent</option>
                                    <option value="Utilities">Utilities</option>
                                    <option value="Salaries">Salaries</option>
                                    <option value="Office Supplies">Office Supplies</option>
                                    <option value="Marketing">Marketing</option>
                                    <option value="Transportation">Transportation</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="expense_date" class="form-label">Date *</label>
                                <input type="date" class="form-control" id="expense_date" name="expense_date" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" class="form-control" id="amount" name="amount" 
                                       step="0.01" min="0.01" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Source (Account)</label>
                            <select class="form-select" name="financial_account_id" required>
                                <?php 
                                $accs = $conn->query("SELECT id, account_name, balance FROM financial_accounts WHERE is_active=1");
                                while ($ac = $accs->fetch_assoc()): 
                                ?>
                                    <option value="<?= $ac['id'] ?>">
                                        <?= escape($ac['account_name']) ?> (<?= formatCurrency($ac['balance']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Create Expense
                        </button>
                        <a href="<?= url('/modules/expenses/list.php') ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
