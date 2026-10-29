<?php
$pageTitle = 'Supplier Payment';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/TransactionManager.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$supplier = null;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ? AND type = 'supplier'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $supplier = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $supplierId = (int)$_POST['supplier_id'];
        $rawAmount = (float)$_POST['amount'];
        $paymentType = $_POST['payment_type']; // 'pay' or 'refund'
        $methods = $_POST['method'] ?? [];
        $accountIds = $_POST['payment_accounts'] ?? [];
        $paymentAmounts = $_POST['payment_amounts'] ?? [];
        $note = cleanInput($_POST['note']);
        $date = date('Y-m-d H:i:s');
        $user_id = $_SESSION['user_id'];

        if ($rawAmount <= 0) {
            $errors[] = "Amount must be greater than zero";
        }
        
        // Sum validation
        $totalSplitAmount = 0;
        foreach($paymentAmounts as $pAmt) {
            $totalSplitAmount += (float)$pAmt;
        }
        if (abs($totalSplitAmount - $rawAmount) > 0.01) {
            $errors[] = "The sum of the payment sources (" . number_format($totalSplitAmount, 2) . ") must exactly match the Total Amount (" . number_format($rawAmount, 2) . ").";
        }

        if (empty($accountIds)) {
            $errors[] = "Please add at least one payment source.";
        } else {
            foreach($accountIds as $acc) {
                if((int)$acc <= 0) {
                    $errors[] = "Please select a valid payment account for all sources.";
                    break;
                }
            }
        }

        if (empty($errors)) {
            // Calculate final signed amount
            // Receive = Positive (Reduces Due)
            // Refund = Negative (Increases Due / Returns money)
            $amount = ($paymentType === 'refund') ? -1 * $rawAmount : $rawAmount;

            $conn->begin_transaction();
            try {
                // For Suppliers:
                // Pay = Positive value in DB (Reduces our debt to them)
                // Refund = Negative value in DB (Increases our debt to them / they return our money)
                
                // Insert into payments table
                $stmt = $conn->prepare("INSERT INTO payments (payable_type, payable_id, amount, method, note, paid_at) VALUES ('supplier', ?, ?, ?, ?, ?)");
                
                $tm = new TransactionManager($conn);
                
                for ($i = 0; $i < count($accountIds); $i++) {
                    $accId = (int)$accountIds[$i];
                    $payAmount = (float)$paymentAmounts[$i];
                    $payMethod = $methods[$i];
                    
                    $splitAmount = ($paymentType === 'refund') ? -1 * $payAmount : $payAmount;
                    
                    $stmt->bind_param("idsss", $supplierId, $splitAmount, $payMethod, $note, $date);
                    $stmt->execute();
                    $payment_id = $conn->insert_id;
                
                // Record Financial Transaction
                // When we Pay supplier -> Money goes OUT -> Debit
                // When they Refund us -> Money comes IN -> Credit
                $finType = ($paymentType === 'refund') ? 'credit' : 'debit';
                $tm->recordTransaction(
                    $accId, 
                    $finType, 
                    $payAmount, 
                    'payment', 
                    $payment_id, 
                    "Supplier Payment " . ucfirst($paymentType) . " - " . ($supplier['name'] ?? 'Unknown'),
                    $user_id
                );
            }
            $stmt->close();
            
            // Update Supplier Balance
            // Pay -> decreases our due to supplier -> balance goes DOWN
            // Refund -> increases our due -> balance goes UP
            $stmtBal = $conn->prepare("UPDATE contacts SET balance = balance - ? WHERE id = ?");
            $stmtBal->bind_param("di", $amount, $supplierId);
            $stmtBal->execute();
            $stmtBal->close();

            $conn->commit();
            setFlashMessage('success', 'Transaction recorded successfully');
            echo "<script>window.location.href = '" . url('/modules/suppliers/list.php') . "';</script>";
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error processing payment: " . $e->getMessage();
        }
    }
}
}
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-cash-coin"></i> Supplier Transaction (সাপ্লায়ার লেনদেন)</h1>
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

    <?php if ($supplier): ?>
        <?php $currentBalance = getContactBalance($conn, $supplier['id']); ?>
        <div class="card mb-4 bg-light">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <h6 class="text-muted mb-1">Supplier Name</h6>
                        <h5 class="mb-0"><?= escape($supplier['name']) ?></h5>
                    </div>
                    <div class="col-md-4">
                        <h6 class="text-muted mb-1">Contact</h6>
                        <h5 class="mb-0"><?= escape($supplier['phone']) ?></h5>
                    </div>
                    <div class="col-md-4">
                        <h6 class="text-muted mb-1">Current Due (You Owe)</h6>
                        <h4 class="<?= $currentBalance > 0 ? 'text-danger' : 'text-success' ?> mb-0">
                            <?= formatCurrency($currentBalance) ?>
                        </h4>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="" id="paymentForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="supplier_id" value="<?= $supplier['id'] ?>">

            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Payment Action</label>
                            <select name="payment_type" class="form-select" id="paymentType" onchange="updateUI()">
                                <option value="pay">Pay Supplier (Decreases Due)</option>
                                <option value="refund">Receive Refund from Supplier (Increases Due)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount (টাকার পরিমাণ)</label>
                            <div class="input-group">
                                <span class="input-group-text">৳</span>
                                <input type="number" name="amount" id="totalAmount" class="form-control fw-bold text-success fs-5" step="0.01" min="0.01" required value="<?= $currentBalance > 0 ? $currentBalance : '' ?>" oninput="checkSplitValidation()">
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label fw-bold mb-0">Payment Sources (অ্যাকাউন্ট) <span id="account_help" class="badge bg-success ms-2">MONEY OUT</span></label>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPaymentSource()">
                                <i class="bi bi-plus-circle"></i> Add Source
                            </button>
                        </div>
                        
                        <div id="paymentSourcesContainer">
                            <div class="payment-source-row row g-2 mb-2 align-items-end rounded bg-light p-2 border">
                                <div class="col-md-5">
                                    <label class="form-label small">Account</label>
                                    <select name="payment_accounts[]" class="form-select source-account" required>
                                        <option value="">Select Account</option>
                                        <?php 
                                        $accs = $conn->query("SELECT id, account_name, balance FROM financial_accounts WHERE is_active=1");
                                        while ($ac = $accs->fetch_assoc()): 
                                        ?>
                                            <option value="<?= $ac['id'] ?>"><?= escape($ac['account_name']) ?> (<?= formatCurrency($ac['balance']) ?>)</option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Method</label>
                                    <select name="method[]" class="form-select">
                                        <option value="cash">Cash</option>
                                        <option value="mobile">Mobile</option>
                                        <option value="bank">Bank</option>
                                        <option value="card">Card</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text px-2">৳</span>
                                        <input type="number" name="payment_amounts[]" class="form-control source-amount" step="0.01" min="0.01" placeholder="0" required oninput="checkSplitValidation()">
                                    </div>
                                </div>
                                <div class="col-md-1 text-end">
                                    <button type="button" class="btn btn-outline-danger remove-source-btn" onclick="removePaymentSource(this)" style="display:none;" title="Remove">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-2"><span id="splitValidationMsg" class="text-danger fw-bold small"></span></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Note</label>
                        <textarea name="note" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg" id="submit_btn" disabled>Confirm Transaction</button>
                    </div>
                </div>
            </div>
        </form>
    <?php else: ?>
        <form method="GET" action="" class="mb-4">
            <div class="row align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Search Supplier</label>
                    <select name="id" class="form-select select2" onchange="this.form.submit()">
                        <option value="">-- Select Supplier --</option>
                        <?php
                        $cStmt = $conn->query("SELECT id, name, phone FROM contacts WHERE type='supplier' ORDER BY name");
                        while($c = $cStmt->fetch_assoc()) {
                            $sel = ($id == $c['id']) ? 'selected' : '';
                            echo "<option value='{$c['id']}' $sel>" . escape($c['name']) . " (" . escape($c['phone']) . ")</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<template id="paymentRowTemplate">
    <div class="payment-source-row row g-2 mb-2 align-items-end rounded bg-light p-2 border">
        <div class="col-md-5">
            <label class="form-label small">Account</label>
            <select name="payment_accounts[]" class="form-select source-account" required>
                <option value="">Select Account</option>
                <?php 
                $accs = $conn->query("SELECT id, account_name, balance FROM financial_accounts WHERE is_active=1");
                while ($ac = $accs->fetch_assoc()): 
                ?>
                    <option value="<?= $ac['id'] ?>"><?= escape($ac['account_name']) ?> (<?= formatCurrency($ac['balance']) ?>)</option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Method</label>
            <select name="method[]" class="form-select">
                <option value="cash">Cash</option>
                <option value="mobile">Mobile</option>
                <option value="bank">Bank</option>
                <option value="card">Card</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Amount</label>
            <div class="input-group">
                <span class="input-group-text px-2">৳</span>
                <input type="number" name="payment_amounts[]" class="form-control source-amount" step="0.01" min="0.01" placeholder="0" required oninput="checkSplitValidation()">
            </div>
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-outline-danger remove-source-btn" onclick="removePaymentSource(this)" title="Remove">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>
</template>

<script>
function addPaymentSource() {
    const container = document.getElementById('paymentSourcesContainer');
    const template = document.getElementById('paymentRowTemplate');
    const clone = template.content.cloneNode(true);
    container.appendChild(clone);
    updateRemoveButtons();
    checkSplitValidation();
}

function removePaymentSource(btn) {
    const row = btn.closest('.payment-source-row');
    if (row && document.querySelectorAll('.payment-source-row').length > 1) {
        row.remove();
        updateRemoveButtons();
        checkSplitValidation();
    }
}

function updateRemoveButtons() {
    const rows = document.querySelectorAll('.payment-source-row');
    rows.forEach(row => row.querySelector('.remove-source-btn').style.display = rows.length > 1 ? 'inline-block' : 'none');
}

function checkSplitValidation() {
    const totalAmount = parseFloat(document.getElementById('totalAmount').value) || 0;
    const splitInputs = document.querySelectorAll('.source-amount');
    let sumSplit = 0;
    splitInputs.forEach(input => sumSplit += parseFloat(input.value) || 0);
    const msg = document.getElementById('splitValidationMsg');
    const btnSubmit = document.getElementById('submit_btn');

    if (totalAmount > 0 && Math.abs(totalAmount - sumSplit) <= 0.01) {
        msg.className = 'text-success fw-bold small';
        msg.innerHTML = '<i class="bi bi-check-circle"></i> Amounts match Total';
        btnSubmit.disabled = false;
    } else {
        msg.className = 'text-danger fw-bold small';
        msg.innerHTML = (sumSplit > totalAmount) ? 'Over by ' + (sumSplit - totalAmount).toFixed(2) : 'Needed ' + (totalAmount - sumSplit).toFixed(2);
        btnSubmit.disabled = true;
    }
}

function updateUI() {
    const type = document.getElementById('paymentType').value;
    const amountInput = document.getElementById('totalAmount');
    const accHelp = document.getElementById('account_help');
    if (type === 'pay') {
        amountInput.classList.remove('text-danger');
        amountInput.classList.add('text-success');
        accHelp.innerHTML = 'MONEY OUT (সাপ্লায়ারকে প্রদান)';
        accHelp.className = 'badge bg-success ms-2';
    } else {
        amountInput.classList.remove('text-success');
        amountInput.classList.add('text-danger');
        accHelp.innerHTML = 'MONEY IN (সাপ্লায়ার থেকে ফেরত)';
        accHelp.className = 'badge bg-danger ms-2';
    }
}
// Init
updateUI();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
