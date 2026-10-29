<?php
$pageTitle = 'Quick Adjustment (দ্রুত লেনদেন)';
$requiredRole = ['admin', 'manager'];
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/TransactionManager.php';

$errors = [];
$successMessage = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contactType = $_POST['contact_type'] ?? ''; // 'customer' or 'supplier'
    $entityId = (int)($_POST['entity_id'] ?? 0);
    $rawAmount = (float)($_POST['amount'] ?? 0);
    $paymentType = $_POST['payment_type'] ?? ''; // 'receive' or 'pay'
    $note = cleanInput($_POST['note'] ?? '');
    
    // Split Payment Arrays
    $methods = $_POST['method'] ?? [];
    $accountIds = $_POST['payment_accounts'] ?? [];
    $paymentAmounts = $_POST['payment_amounts'] ?? [];
    
    $date = date('Y-m-d H:i:s');
    $user_id = $_SESSION['user_id'];

    if (!in_array($contactType, ['customer', 'supplier'])) {
        $errors[] = "Invalid contact type selected.";
    }
    if ($entityId <= 0) {
        $errors[] = "Please select a valid " . ucfirst($contactType) . ".";
    }
    if ($rawAmount <= 0) {
        $errors[] = "Amount must be greater than zero.";
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
    }
    foreach($accountIds as $acc) {
        if((int)$acc <= 0) {
            $errors[] = "Please select a valid financial account for all sources.";
            break;
        }
    }

    if (empty($errors)) {
        // Fetch Contact Name for Transaction Log
        $stmtName = $conn->prepare("SELECT name FROM contacts WHERE id = ? AND type = ?");
        $stmtName->bind_param("is", $entityId, $contactType);
        $stmtName->execute();
        $contactRes = $stmtName->get_result()->fetch_assoc();
        $contactName = $contactRes ? $contactRes['name'] : 'Unknown ID: ' . $entityId;
        $stmtName->close();

        $conn->begin_transaction();
        try {
            $methodLabel = count($accountIds) > 1 ? 'split' : ($methods[0] ?? 'cash');

            if ($contactType === 'customer') {
                // ---- CUSTOMER LOGIC ----
                // Always work with positive amount
                // payment_type determines direction

                $stmt = $conn->prepare("INSERT INTO payments (payable_type, payable_id, amount, method, note, paid_at) VALUES ('customer', ?, ?, ?, ?, ?)");
                $stmt->bind_param("idsss", $entityId, $rawAmount, $methodLabel, $note, $date);
                $stmt->execute();
                $payment_id = $conn->insert_id;
                $stmt->close();

                if ($paymentType === 'receive') {
                    // Customer paid us -> their due decreases
                    $stmt = $conn->prepare("UPDATE contacts SET balance = balance - ? WHERE id = ?");
                    $txnType = 'credit'; // Money IN to our account
                    $description = "Quick Payment from Customer";
                } else {
                    // We refunded customer -> their due decreases (or credit balance increases)
                    $stmt = $conn->prepare("UPDATE contacts SET balance = balance + ? WHERE id = ?");
                    $txnType = 'debit'; // Money OUT of our account
                    $description = "Quick Refund to Customer";
                }
                $stmt->bind_param("di", $rawAmount, $entityId);
                $stmt->execute();
                $stmt->close();

                $tm = new TransactionManager($conn);

                foreach ($accountIds as $index => $accId) {
                    $splitRawVal = (float)$paymentAmounts[$index];
                    if ($splitRawVal <= 0) continue;
                    
                    $tm->recordTransaction(
                        (int)$accId, $txnType, abs($splitRawVal), 'customer_payment', $payment_id,
                        "$description: $contactName" . ($note ? " ($note) [Split]" : " [Split]"), $user_id
                    );
                }

            } else {
                // ---- SUPPLIER LOGIC ----
                $stmt = $conn->prepare("INSERT INTO payments (payable_type, payable_id, amount, method, note, paid_at) VALUES ('supplier', ?, ?, ?, ?, ?)");
                $stmt->bind_param("idsss", $entityId, $rawAmount, $methodLabel, $note, $date);
                $stmt->execute();
                $payment_id = $conn->insert_id;
                $stmt->close();

                if ($paymentType === 'pay') {
                    // We paid supplier -> their due decreases
                    $stmt = $conn->prepare("UPDATE contacts SET balance = balance - ? WHERE id = ?");
                    $txnType = 'debit'; // Money OUT of our account
                    $description = "Quick Payment to Supplier";
                } else {
                    // Supplier refunded us -> their due increases (we recover money)
                    $stmt = $conn->prepare("UPDATE contacts SET balance = balance + ? WHERE id = ?");
                    $txnType = 'credit'; // Money IN to our account
                    $description = "Quick Refund from Supplier";
                }
                $stmt->bind_param("di", $rawAmount, $entityId);
                $stmt->execute();
                $stmt->close();

                $tm = new TransactionManager($conn);

                foreach ($accountIds as $index => $accId) {
                    $splitRawVal = (float)$paymentAmounts[$index];
                    if ($splitRawVal <= 0) continue;
                    
                    $tm->recordTransaction(
                        (int)$accId, $txnType, abs($splitRawVal), 'supplier_payment', $payment_id,
                        "$description: $contactName" . ($note ? " ($note) [Split]" : " [Split]"), $user_id
                    );
                }
            }

            $conn->commit();
            $successMessage = "Transaction recorded successfully!";
            
            // Unset Post Data so form clears on reload
            $_POST = array(); 
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error processing adjustment: " . $e->getMessage();
        }
    }
}

// Fetch all necessary dropdown data
$customers = $conn->query("SELECT id, name, company_name, balance FROM contacts WHERE type = 'customer' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$suppliers = $conn->query("SELECT id, name, company_name, balance FROM contacts WHERE type = 'supplier' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$accounts = $conn->query("SELECT id, account_name, balance FROM financial_accounts WHERE is_active=1")->fetch_all(MYSQLI_ASSOC);

?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-arrow-left-right"></i> Quick Adjustment (দ্রুত লেনদেন)</h1>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= escape($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm" style="max-width: 750px; margin: 0 auto;">
        <div class="card-body p-4">
            <form method="POST" id="quickAdjustmentForm" onsubmit="return validateForm()">
                
                <!-- 1. Select Contact Type -->
                <div class="mb-4">
                    <label class="form-label fw-bold">Select Contact Type (ধরণ নির্বাচন করুন)</label>
                    <div class="d-flex flex-column flex-sm-row gap-3">
                        <input type="radio" class="btn-check" name="contact_type" id="typeContactCustomer" value="customer" checked onchange="toggleContactType()">
                        <label class="btn btn-outline-primary flex-fill fw-bold py-2" for="typeContactCustomer">
                            <i class="bi bi-people-fill me-2"></i>Customer (কাস্টমার)
                        </label>

                        <input type="radio" class="btn-check" name="contact_type" id="typeContactSupplier" value="supplier" onchange="toggleContactType()">
                        <label class="btn btn-outline-info flex-fill fw-bold py-2 text-dark" for="typeContactSupplier">
                            <i class="bi bi-truck me-2"></i>Supplier (সাপ্লায়ার)
                        </label>
                    </div>
                </div>

                <!-- 2. Select Entity (Customer or Supplier drop-down) -->
                <div class="mb-4" id="customerSelectWrapper">
                    <label class="form-label fw-bold">Select Customer</label>
                    <select name="entity_id" id="customerSelect" class="form-select select2" style="width: 100%;">
                        <option value="">-- Search Customer --</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" data-balance="<?= $c['balance'] ?>">
                                <?= escape($c['name']) ?> <?= $c['company_name'] ? "({$c['company_name']})" : '' ?> [Due: <?= formatCurrency($c['balance']) ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4" id="supplierSelectWrapper" style="display: none;">
                    <label class="form-label fw-bold">Select Supplier</label>
                    <select name="entity_id" id="supplierSelect" class="form-select select2" style="width: 100%;" disabled>
                        <option value="">-- Search Supplier --</option>
                        <?php foreach($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>" data-balance="<?= $s['balance'] ?>">
                                <?= escape($s['name']) ?> <?= $s['company_name'] ? "({$s['company_name']})" : '' ?> [Due: <?= formatCurrency($s['balance']) ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Current Balance Display -->
                <div class="mb-4 p-3 bg-light rounded text-center border" id="balanceDisplayWrapper" style="display: none;">
                    <p class="text-muted mb-1">Current Due (বর্তমান বাকি)</p>
                    <h3 class="text-danger fw-bold mb-0" id="currentBalanceDisplay">৳0.00</h3>
                </div>

                <!-- 3. Transaction Type (Receive or Pay) -->
                <div class="mb-4 p-3 rounded border" style="background-color: #f8f9fa;">
                    <label class="form-label fw-bold d-block mb-3">Transaction Action (লেনদেনের ধরণ)</label>
                    <div class="d-flex flex-column flex-md-row gap-3 justify-content-center">
                        <div class="form-check border rounded p-3 bg-white flex-fill text-center" style="cursor:pointer;" onclick="document.getElementById('actionReceive').click()">
                            <input class="form-check-input float-none ms-0 mb-2" type="radio" name="payment_type" id="actionReceive" value="receive" checked onchange="updateActionUI()">
                            <label class="form-check-label fw-bold text-success fs-5 d-block" for="actionReceive" id="lblReceive">
                                <i class="bi bi-arrow-down-circle"></i> Receive Money (টাকা গ্রহণ)
                            </label>
                            <small class="d-block text-muted" id="subReceiveTxt">(From Customer)</small>
                        </div>
                        <div class="form-check border rounded p-3 bg-white flex-fill text-center" style="cursor:pointer;" onclick="document.getElementById('actionPay').click()">
                            <input class="form-check-input float-none ms-0 mb-2" type="radio" name="payment_type" id="actionPay" value="pay" onchange="updateActionUI()">
                            <label class="form-check-label fw-bold text-danger fs-5 d-block" for="actionPay" id="lblPay">
                                <i class="bi bi-arrow-up-circle"></i> Pay Money (টাকা প্রদান)
                            </label>
                            <small class="d-block text-muted" id="subPayTxt">(To Supplier)</small>
                        </div>
                    </div>
                </div>

                <!-- 4. Total Amount -->
                <div class="mb-4">
                    <label class="form-label fw-bold">Total Amount to Process (মোট টাকার পরিমাণ)</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light text-dark fw-bold">৳</span>
                        <input type="number" id="totalAmount" name="amount" class="form-control fw-bold fs-4" step="0.01" min="0" required placeholder="Enter Total Amount" value="<?= isset($_POST['amount']) ? $_POST['amount'] : '' ?>" oninput="checkSplitValidation()">
                    </div>
                </div>

                <!-- 5. Split Account Sections -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <label class="form-label fw-bold mb-0">Payment Sources (অ্যাকাউন্ট) <span id="payment_action_badge" class="badge bg-secondary ms-2"></span></label>
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
                                    <?php foreach($accounts as $ac): ?>
                                        <option value="<?= $ac['id'] ?>"><?= escape($ac['account_name']) ?> (<?= formatCurrency($ac['balance']) ?>)</option>
                                    <?php endforeach; ?>
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

                <!-- 6. Note -->
                <div class="mb-4">
                    <label class="form-label fw-bold">Note (নোট - অপশনাল)</label>
                    <textarea name="note" class="form-control" rows="2" placeholder="Example: TrxID..."><?= escape($_POST['note'] ?? '') ?></textarea>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg py-3 fw-bold fs-5" id="submit_btn">
                        <i class="bi bi-check2-circle"></i> Confirm Transaction
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
                <?php foreach($accounts as $ac): ?>
                    <option value="<?= $ac['id'] ?>"><?= escape($ac['account_name']) ?> (<?= formatCurrency($ac['balance']) ?>)</option>
                <?php endforeach; ?>
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
document.addEventListener('DOMContentLoaded', function() {
    toggleContactType(); // Init UI based on default load
    checkSplitValidation();
    
    // Listen to Select2 changes to update balance display
    if(typeof $ !== 'undefined') {
        $('#customerSelect, #supplierSelect').on('select2:select', function (e) {
            updateBalanceDisplay(e.params.data.element);
        });
    }
});

function toggleContactType() {
    const isCustomer = document.getElementById('typeContactCustomer').checked;
    
    const custWrapper = document.getElementById('customerSelectWrapper');
    const custSelect = document.getElementById('customerSelect');
    
    const suppWrapper = document.getElementById('supplierSelectWrapper');
    const suppSelect = document.getElementById('supplierSelect');
    
    const actionReceive = document.getElementById('actionReceive');
    const actionPay = document.getElementById('actionPay');

    const lblReceive = document.getElementById('lblReceive');
    const lblPay = document.getElementById('lblPay');
    const subReceive = document.getElementById('subReceiveTxt');
    const subPay = document.getElementById('subPayTxt');

    document.getElementById('balanceDisplayWrapper').style.display = 'none';

    if (isCustomer) {
        // Show Customer UI
        custWrapper.style.display = 'block';
        custSelect.disabled = false;
        custSelect.setAttribute('name', 'entity_id');
        
        suppWrapper.style.display = 'none';
        suppSelect.disabled = true;
        suppSelect.removeAttribute('name');

        // Customer Action Defaults
        actionReceive.checked = true;
        lblReceive.innerHTML = '<i class="bi bi-arrow-down-circle"></i> Receive Money (টাকা গ্রহণ)';
        subReceive.innerText = '(Received from Customer)';
        
        lblPay.innerHTML = '<i class="bi bi-arrow-up-circle"></i> Give Refund (ফেরত দিন)';
        subPay.innerText = '(Returned to Customer)';

    } else {
        // Show Supplier UI
        suppWrapper.style.display = 'block';
        suppSelect.disabled = false;
        suppSelect.setAttribute('name', 'entity_id');
        
        custWrapper.style.display = 'none';
        custSelect.disabled = true;
        custSelect.removeAttribute('name');

        // Supplier Action Defaults
        actionPay.checked = true;
        lblReceive.innerHTML = '<i class="bi bi-arrow-down-circle"></i> Receive Refund (ফেরত নিন)';
        subReceive.innerText = '(Refund from Supplier)';
        
        lblPay.innerHTML = '<i class="bi bi-arrow-up-circle"></i> Pay Money (টাকা প্রদান)';
        subPay.innerText = '(Paid to Supplier)';
    }

    updateActionUI();
}

function updateBalanceDisplay(selectedOption) {
    const balanceWrapper = document.getElementById('balanceDisplayWrapper');
    const balanceText = document.getElementById('currentBalanceDisplay');
    
    if (selectedOption && selectedOption.value !== "") {
        const balance = parseFloat(selectedOption.getAttribute('data-balance') || 0);
        // Uses the simple formatted local version purely for display.
        balanceText.innerText = '৳' + balance.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        balanceWrapper.style.display = 'block';
    } else {
        balanceWrapper.style.display = 'none';
    }
}

function updateActionUI() {
    const isCustomer = document.getElementById('typeContactCustomer').checked;
    const isReceiveAction = document.getElementById('actionReceive').checked;
    
    const badge = document.getElementById('payment_action_badge');

    // Logic: Identify whether we are taking money IN or sending money OUT
    let isMoneyIn = false;
    
    // Customer logic
    if (isCustomer) {
        if (isReceiveAction) { isMoneyIn = true; } // Receive from customer
    } else {
        if (isReceiveAction) { isMoneyIn = true; } // Receive refund from supplier
    }
    
    if (isMoneyIn) {
        badge.textContent = 'MONEY IN (জমা)';
        badge.className = 'badge bg-success ms-2';
    } else {
        badge.textContent = 'MONEY OUT (প্রদান)';
        badge.className = 'badge bg-danger ms-2';
    }
}

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
    
    // Find the input in the cloned node
    const newAmountInput = clone.querySelector('.source-amount');
    
    if (totalAmount > sumSplit) {
        // Provide the exact remaining balance
        newAmountInput.value = (totalAmount - sumSplit).toFixed(2);
    } else {
        newAmountInput.value = ""; // Default empty if already over or equal
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

    // Floating point math wiggle room (e.g. 0.01)
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

function validateForm() {
    checkSplitValidation(); // final check just in case
    return !document.getElementById('submit_btn').disabled;
}

</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
