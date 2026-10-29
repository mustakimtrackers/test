<?php
/**
 * Add/Edit Capital Transaction
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/Validator.php';

$type = $_GET['type'] ?? 'injection'; // injection or withdrawal
$pageTitle = ($type == 'injection') ? 'Add Capital' : 'Withdraw Capital';

// Get Investors
$investorsResult = $conn->query("SELECT id, name FROM contacts WHERE type = 'investor' ORDER BY name");
$investors = $investorsResult->fetch_all(MYSQLI_ASSOC);

// If no investors found, prompt to create one
if (empty($investors)) {
    // Auto-create "Owner" if not exists, for convenience
    $checkOwner = $conn->query("SELECT id FROM contacts WHERE type = 'investor' AND name = 'Owner' LIMIT 1");
    if ($checkOwner->num_rows == 0) {
        $conn->query("INSERT INTO contacts (type, name, credit_limit) VALUES ('investor', 'Owner (Me)', 0)");
        header("Refresh:0"); // Refresh to load the new investor
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $data = [
            'trans_date' => $_POST['trans_date'] ?? date('Y-m-d'),
            'investor_id' => $_POST['investor_id'] ?? '',
            'amount' => $_POST['amount'] ?? 0,
            'method' => $_POST['method'] ?? 'cash',
            'description' => $_POST['description'] ?? ''
        ];

        $rules = [
            'trans_date' => 'required',
            'investor_id' => 'required|numeric',
            'amount' => 'required|numeric',
        ];

        $validator = new Validator($conn);

        if ($validator->validate($data, $rules)) {
            $transDate = cleanInput($data['trans_date']);
            $investorId = (int)$data['investor_id'];
            $amount = (float)$data['amount'];
            $method = cleanInput($data['method']);
            $description = cleanInput($data['description']);
            $userId = $_SESSION['user_id'];

            $stmt = $conn->prepare("INSERT INTO capital_transactions (trans_date, investor_id, type, amount, method, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt === false) {
                 $errors[] = "Database Error (Prepare): " . $conn->error;
            } else {
                $conn->begin_transaction(); // START TRANSACTION
                try {
                    $stmt->bind_param("sisdssi", $transDate, $investorId, $type, $amount, $method, $description, $userId);
        
                    if (!$stmt->execute()) {
                        throw new Exception("Database Error (Execute): " . $stmt->error);
                    }
                    
                    $capitalId = $stmt->insert_id;

                    // --- ACCOUNTING INTEGRATION ---
                    require_once __DIR__ . '/../../includes/TransactionManager.php';
                    $tm = new TransactionManager($conn);
                    $accountId = (int)$_POST['financial_account_id'];
                    
                    // Injection = Money IN (Credit), Withdrawal = Money OUT (Debit)
                    $transType = ($type == 'injection') ? 'credit' : 'debit';
                    
                    $tm->recordTransaction(
                        $accountId, 
                        $transType, 
                        $amount, 
                        'capital_' . $type, 
                        $capitalId, 
                        "Capital $type - Investor: " . $description,
                        $userId
                    );
                    
                    // Log Activity
                    logActivity('capital_' . $type, 'capital', $capitalId, null, $data, "Capital $type of $amount");
                    
                    $conn->commit(); // COMMIT
                    redirect('index.php');
                    
                } catch (Exception $e) {
                    $conn->rollback(); // ROLLBACK ON ERROR
                    $errors[] = "Error: " . $e->getMessage();
                }
            }
        } else {
            $errors = array_values($validator->getErrors());
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title">
            <?= ($type == 'injection') ? '<i class="bi bi-plus-circle text-success"></i> Add Capital' : '<i class="bi bi-dash-circle text-danger"></i> Withdraw Capital' ?>
        </h1>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-6">
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

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="trans_date" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Investor / Source</label>
                            <div class="input-group flex-nowrap">
                                <select class="form-select select2" name="investor_id" required style="width: 100%;">
                                    <option value="">Select Investor</option>
                                    <?php foreach ($investors as $inv): ?>
                                        <option value="<?= $inv['id'] ?>"><?= escape($inv['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="add_investor.php" class="btn btn-outline-secondary" title="Add New Investor">
                                    <i class="bi bi-plus-lg"></i>
                                </a>
                            </div>
                            <div class="form-text">Who is giving/taking the money?</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" class="form-control form-control-lg" name="amount" placeholder="0.00" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Deposit/Withdraw From Account</label>
                            <select class="form-select" name="financial_account_id" required>
                                <?php 
                                $accs = $conn->query("SELECT id, account_name, balance FROM financial_accounts WHERE is_active=1");
                                while ($ac = $accs->fetch_assoc()): 
                                ?>
                                    <option value="<?= $ac['id'] ?>">
                                        <?= escape($ac['account_name']) ?> (Current: <?= formatCurrency($ac['balance']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Payment Method (Reference)</label>
                            <select class="form-select" name="method">
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="mobile">Mobile Banking</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description / Note</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="e.g. Initial investment, Loan from friend..."></textarea>
                        </div>

                        <button type="submit" class="btn w-100 <?= ($type == 'injection') ? 'btn-success' : 'btn-danger' ?>">
                            <?= ($type == 'injection') ? 'Save Investment' : 'Confirm Withdrawal' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
