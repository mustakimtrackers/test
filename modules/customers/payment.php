<?php
$pageTitle = 'Receive Payment';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/TransactionManager.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = null;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ? AND type = 'customer'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)$_POST['customer_id'];
    $rawAmount = (float)$_POST['amount'];
    $paymentType = $_POST['payment_type']; // 'receive' or 'refund'
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
            $methodLabel = count($accountIds) > 1 ? 'split' : ($methods[0] ?? 'cash');
            
            // 1. Record Payment
            $stmt = $conn->prepare("INSERT INTO payments (payable_type, payable_id, amount, method, note, paid_at) VALUES ('customer', ?, ?, ?, ?, ?)");
            $stmt->bind_param("idsss", $customerId, $amount, $methodLabel, $note, $date);
            $stmt->execute();
            $payment_id = $conn->insert_id;
            $stmt->close();

            // 2. Update Customer Balance
            // Logic: Balance = Amount They Owe (Due).
            // Positive Payment: Balance Decreases.
            // Negative Payment (Refund): Balance Increases.
            $stmt = $conn->prepare("UPDATE contacts SET balance = balance - ? WHERE id = ?");
            $stmt->bind_param("di", $amount, $customerId);
            $stmt->execute();
            $stmt->close();

            // 3. Record Financial Transaction (Money IN - Credit)
            // - Positive: Money In (Credit)
            // - Negative: Money Out (Debit / Return)
            $tm = new TransactionManager($conn);
            $txnType = $amount >= 0 ? 'credit' : 'debit';
            $description = $amount >= 0 ? "Payment from Customer" : "Refund to Customer";

            foreach ($accountIds as $index => $accId) {
                $splitRawVal = (float)$paymentAmounts[$index];
                if ($splitRawVal <= 0) continue;
                
                $tm->recordTransaction(
                    (int)$accId,
                    $txnType, 
                    abs($splitRawVal),
                    'customer_payment', 
                    $payment_id,
                    "$description: " . ($customer ? $customer['name'] : 'ID: ' . $customerId) . ($note ? " ($note)" : "") . (count($accountIds) > 1 ? " [Split]" : ""),
                    $user_id
                );
            }

            $conn->commit();
            setFlashMessage('success', 'Transaction recorded successfully');
            echo "<script>window.location.href = '" . url('/modules/customers/list.php') . "';</script>";
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error processing payment: " . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-cash-coin"></i> Customer Transaction (কাস্টমার লেনদেন)</h1>
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

    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="customer_id" value="<?= $customer ? $customer['id'] : '' ?>">
                
                <div class="mb-4 text-center">
                    <h5>Customer: <strong><?= $customer ? escape($customer['name']) : 'Unknown' ?></strong></h5>
                    <p class="text-muted mb-0">Current Due (বাকি): <span class="text-danger fw-bold"><?= $customer ? formatCurrency($customer['balance']) : '0' ?></span></p>
                </div>

                <!-- Transaction Type Selection -->
                <div class="mb-4 p-3 bg-light rounded border">
                    <label class="form-label fw-bold mb-3 d-block">Transaction Type (লেনদেনের ধরণ)</label>
                    <div class="d-flex gap-4 justify-content-center">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_type" id="type_receive" value="receive" checked onchange="updateUI()">
                            <label class="form-check-label fw-bold text-success" for="type_receive">
                                <i class="bi bi-arrow-down-circle"></i> Receive Payment<br>
                                <small class="text-muted">(কাস্টমার থেকে জমা)</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_type" id="type_refund" value="refund" onchange="updateUI()">
                            <label class="form-check-label fw-bold text-danger" for="type_refund">
                                <i class="bi bi-arrow-up-circle"></i> Give Refund<br>
                                <small class="text-muted">(কাস্টমারকে ফেরত/রিফান্ড)</small>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Amount (টাকার পরিমাণ)</label>
                    <div class="input-group">
                        <span class="input-group-text">৳</span>
                        <input type="number" name="amount" class="form-control form-control-lg fw-bold" step="0.01" min="0" required autofocus placeholder="Enter positive amount">
                    </div>
                    <div class="form-text">Just enter the positive amount. Logic will handle the rest.</div>
                </div>

                <!-- Split Account Sections -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <label class="form-label fw-bold mb-0">Payment Sources (অ্যাকাউন্ট) <span id="account_help" class="badge bg-secondary ms-2"></span></label>
                        <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="addPaymentSource()">
                            <i class="bi bi-plus-circle"></i> Payment Sources
                        </button>
                    </div>
                    
                    <div id="paymentSourcesContainer">
                        <!-- Initial Row -->
                        <div class="payment-source-row row g-2 mb-2 align-items-end rounded bg-light p-2 border">
                            <div class="col-md-5">
                                <label class="form-label small">Account</label>
                                <select name="payment_accounts[]" class="form-select source-account" required>
                                    <option value="">Select Account</option>
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
                                <button type="button" class="btn btn-outline-danger remove-source-btn" onclick="removePaymentSource(this)" style="display:none;" title="Remove this split">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-2 text-end">
                        <span id="splitValidationMsg" class="text-danger fw-bold small"><i class="bi bi-exclamation-triangle"></i> Amounts do not match Total</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Note (নোট - অপশনাল)</label>
                    <textarea name="note" class="form-control" rows="2" placeholder="e.g. Paid via bKash TrxID..."></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-success btn-lg" id="submit_btn" disabled>
                        <i class="bi bi-check-circle"></i> Confirm Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Template for cloning rows safely -->
<template id="paymentRowTemplate">
    <div class="payment-source-row row g-2 mb-2 align-items-end rounded bg-light p-2 border">
        <div class="col-md-5">
            <label class="form-label small">Account</label>
            <select name="payment_accounts[]" class="form-select source-account" required>
                <option value="">Select Account</option>
                <?php 
                // Re-query or reuse, actually better to reuse but in standard PHP template we just redo query if small or recreate from variable
                $accs = $conn->query("SELECT id, account_name, balance FROM financial_accounts WHERE is_active=1");
                while ($ac = $accs->fetch_assoc()): 
                ?>
                    <option value="<?= $ac['id'] ?>">
                        <?= escape($ac['account_name']) ?> (<?= formatCurrency($ac['balance']) ?>)
                    </option>
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
            <button type="button" class="btn btn-outline-danger remove-source-btn" onclick="removePaymentSource(this)" title="Remove this split">
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
    
    // Auto-fill logic
    const totalAmount = parseFloat(document.getElementById('totalAmount').value) || 0;
    const splitInputs = document.querySelectorAll('.source-amount');
    let sumSplit = 0;
    splitInputs.forEach(input => {
        sumSplit += parseFloat(input.value) || 0;
    });
    
    const newAmountInput = clone.querySelector('.source-amount');
    if (totalAmount > sumSplit) {
        newAmountInput.value = (totalAmount - sumSplit).toFixed(2);
    } else {
        newAmountInput.value = "";
    }
    
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
    rows.forEach((row, index) => {
        const btn = row.querySelector('.remove-source-btn');
        if (btn) {
            btn.style.display = rows.length > 1 ? 'inline-block' : 'none';
        }
    });
}

function checkSplitValidation() {
    const totalAmount = parseFloat(document.getElementById('totalAmount').value) || 0;
    const splitInputs = document.querySelectorAll('.source-amount');
    
    let sumSplit = 0;
    splitInputs.forEach(input => {
        sumSplit += parseFloat(input.value) || 0;
    });

    const msg = document.getElementById('splitValidationMsg');
    const btnSubmit = document.getElementById('submit_btn');

    // Auto-fill the first split if total is dialed in and there's only 1 row
    if(splitInputs.length === 1) {
        if (document.activeElement && document.activeElement.id === 'totalAmount') {
            splitInputs[0].value = totalAmount > 0 ? totalAmount : '';
            sumSplit = totalAmount;
        } else if(splitInputs[0].value === "" && totalAmount > 0) {
            splitInputs[0].value = totalAmount;
            sumSplit = totalAmount;
        }
    }

    if (totalAmount > 0 && Math.abs(totalAmount - sumSplit) <= 0.01) {
        msg.classList.replace('text-danger', 'text-success');
        msg.innerHTML = '<i class="bi bi-check-circle"></i> Amounts match Total';
        btnSubmit.disabled = false;
    } else {
        msg.classList.replace('text-success', 'text-danger');
        if (sumSplit < totalAmount) {
            msg.innerHTML = '<i class="bi bi-exclamation-circle"></i> Allocated: ৳' + sumSplit.toFixed(2) + ' (Need ৳' + (totalAmount - sumSplit).toFixed(2) + ' more)';
        } else if (sumSplit > totalAmount) {
            msg.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Allocated: ৳' + sumSplit.toFixed(2) + ' (Over by ৳' + (sumSplit - totalAmount).toFixed(2) + ')';
        } else {
            msg.innerHTML = '<i class="bi bi-info-circle"></i> Please enter Amount';
        }
        btnSubmit.disabled = true;
    }
}

function updateUI() {
    const isReceive = document.getElementById('type_receive').checked;
    const btn = document.getElementById('submit_btn');
    const accHelp = document.getElementById('account_help');

    if (isReceive) {
        btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirm Payment (জমা নিশ্চিত করুন)';
        btn.className = 'btn btn-success btn-lg';
        accHelp.innerHTML = 'MONEY IN (জমা)';
        accHelp.className = 'badge bg-success ms-2';
    } else {
        btn.innerHTML = '<i class="bi bi-arrow-return-left"></i> Confirm Refund (ফেরত নিশ্চিত করুন)';
        btn.className = 'btn btn-danger btn-lg';
        accHelp.innerHTML = 'MONEY OUT (কাটা হবে)';
        accHelp.className = 'badge bg-danger ms-2';
    }
}
// Init
updateUI();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
