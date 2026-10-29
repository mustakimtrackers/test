<?php
/**
 * Create Purchase
 * Add stock from suppliers
 */

$pageTitle = 'New Purchase';
require_once __DIR__ . '/../../includes/auth_check.php';
// Header moved after logic to prevent "Headers already sent" error
$suppliers = $conn->query("SELECT id, name, company_name FROM contacts WHERE type = 'supplier' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Handle Form Submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        // Validation
        $supplier_id = (int)$_POST['supplier_id'];
        $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : date('Y-m-d');
        
        // Robust Date Parsing
        $timestamp = strtotime($purchase_date);
        if ($timestamp === false || date('Y', $timestamp) < 2000) {
            // If invalid or year is suspicious (like 0001 or 1970), default to today
             $purchase_date = date('Y-m-d');
        } else {
             $purchase_date = date('Y-m-d', $timestamp);
        }
        $reference_no = cleanInput($_POST['reference_no']);
        $status = in_array($_POST['status'] ?? '', ['received', 'pending', 'ordered']) ? $_POST['status'] : 'pending';
        $items = $_POST['items'] ?? []; // JSON string or array? Usually form array
        // Wait, dynamic rows usually submit arrays like items[0][id], items[0][qty] OR we parse JSON. 
        // Let's use array syntax: name="items[${index}][id]"
        
        $total_amount = (float)$_POST['total_amount'];
        $methods = $_POST['method'] ?? [];
        $accountIds = $_POST['payment_accounts'] ?? [];
        $paymentAmounts = $_POST['payment_amounts'] ?? [];
        
        // Sum up the split paid amounts
        $paid_amount = 0;
        foreach($paymentAmounts as $pAmt) {
            $paid_amount += (float)$pAmt;
        }
        
        if ($supplier_id <= 0) $errors[] = "Supplier is required";
        if (empty($items)) $errors[] = "Please add at least one product";
        if ($paid_amount > $total_amount) {
            $errors[] = "Paid amount cannot exceed total purchase amount. Overpayments are not allowed.";
        }
        
        // Ensure that if any money is paid, valid sources are given
        if ($paid_amount > 0) {
            if (empty($accountIds)) {
                $errors[] = "Please add at least one payment source for the paid amount.";
            } else {
                foreach($accountIds as $acc) {
                    if((int)$acc <= 0) {
                        $errors[] = "Please select a valid financial account for all payment sources.";
                        break;
                    }
                }
            }
        }
        
        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $user_id = $_SESSION['user_id'];
                
                // 1. Insert Purchase
                $stmt = $conn->prepare("INSERT INTO purchases (supplier_id, reference_no, purchase_date, total_amount, paid_amount, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issdds", $supplier_id, $reference_no, $purchase_date, $total_amount, $paid_amount, $status);
                $stmt->execute();
                $purchase_id = $conn->insert_id;
                $stmt->close();
                
                // 2. Insert Items & Update Stock
                $stmtItem = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmtStock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                
                // Add statement for updating cost price
                $stmtUpdateCost = $conn->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
                $shouldUpdateCost = isset($_POST['update_cost_price']) && $_POST['update_cost_price'] == '1';

                foreach ($items as $item) {
                    $pid = (int)$item['product_id'];
                    $qty = (int)$item['quantity'];
                    $cost = (float)$item['unit_cost'];
                    $sub = $qty * $cost;
                    
                    $stmtItem->bind_param("iiddd", $purchase_id, $pid, $qty, $cost, $sub);
                    $stmtItem->execute();
                    
                    if ($status === 'received') {
                        $stmtStock->bind_param("ii", $qty, $pid);
                        $stmtStock->execute();
                    }

                    // Update Product Cost Price if requested
                    if ($shouldUpdateCost) {
                        $stmtUpdateCost->bind_param("di", $cost, $pid);
                        $stmtUpdateCost->execute();
                    }
                }
                $stmtItem->close();
                $stmtStock->close();
                $stmtUpdateCost->close();
                
                // 3. Payment
                if ($paid_amount > 0) {
                    $methodLabel = count($accountIds) > 1 ? 'split' : ($methods[0] ?? 'cash');
                
                    $stmtPay = $conn->prepare("INSERT INTO payments (payable_type, payable_id, amount, method, paid_at) VALUES ('purchase', ?, ?, ?, ?)");
                    $stmtPay->bind_param("idss", $purchase_id, $paid_amount, $methodLabel, $purchase_date);
                    $stmtPay->execute();
                    $stmtPay->close();

                    // --- ACCOUNTING INTEGRATION ---
                    require_once __DIR__ . '/../../includes/TransactionManager.php';
                    $tm = new TransactionManager($conn);

                    // Purchase Payment = Money OUT (Debit)
                    foreach ($accountIds as $index => $accId) {
                        $splitRawVal = (float)($paymentAmounts[$index] ?? 0);
                        if ($splitRawVal <= 0) continue;
                        
                        $tm->recordTransaction(
                            (int)$accId, 
                            'debit', 
                            abs($splitRawVal), 
                            'purchase', 
                            $purchase_id, 
                            "Purchase Payment Ref: $reference_no" . (count($accountIds) > 1 ? " [Split]" : ""),
                            $user_id
                        );
                    }
                }
                // 4. Update Supplier Balance (Credit Logic: We owe them money)
                // Balance = Balance + (Total - Paid)
                $due = $total_amount - $paid_amount;
                if ($due != 0) { 
                    $stmtBal = $conn->prepare("UPDATE contacts SET balance = balance + ? WHERE id = ?");
                    $stmtBal->bind_param("di", $due, $supplier_id);
                    $stmtBal->execute();
                    $stmtBal->close();
                }
                
                $conn->commit();

                // Log Activity
                logActivity('create', 'purchases', $purchase_id, null, null, "Purchase Created: $reference_no");

                setFlashMessage('success', 'Purchase created successfully');
                redirect(url('/modules/purchases/list.php'));
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "System Error: " . $e->getMessage();
            }
        }
    }
}

$ref_def = generateReference('PO');

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center">
                <h1 class="page-title"><i class="bi bi-cart-plus"></i> New Purchase</h1>
                <a href="<?= url('/modules/purchases/list.php') ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?><li><?= escape($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" id="purchaseForm">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        
        <!-- Top Card: Info -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Supplier *</label>
                        <select class="form-select select2" name="supplier_id" required>
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= escape($s['name']) ?> (<?= escape($s['company_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Purchase Date *</label>
                        <input type="date" class="form-control" name="purchase_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Reference No</label>
                        <input type="text" class="form-control" name="reference_no" value="<?= $ref_def ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="received">Received</option>
                            <option value="pending">Pending</option>
                            <option value="ordered">Ordered</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Middle Section: Product Selection & Order Items -->
        <div class="row">
            <!-- Left Column: Product Gallery -->
            <div class="col-md-5 mb-4">
                <div class="card h-100 border-primary border-opacity-25 shadow-sm">
                    <div class="card-header bg-light border-bottom-0">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="productSearch" class="form-control border-start-0 ps-0" placeholder="Search by name or SKU...">
                        </div>
                    </div>
                    <div class="card-body p-2" style="max-height: 500px; overflow-y: auto; background-color: #f8f9fa;">
                        <div class="row g-2" id="productGallery">
                            <div class="col-12 text-center text-muted py-4">
                                <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                                <p class="mt-2 small">Loading products...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Order Items -->
            <div class="col-md-7 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-light border-bottom-0">
                        <h5 class="mb-0 fs-6"><i class="bi bi-cart3"></i> Cart / Order Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-hover table-bordered mb-0 align-middle" id="itemsTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width: 40%">Product</th>
                                        <th style="width: 20%">Cost</th>
                                        <th style="width: 15%">Qty</th>
                                        <th style="width: 20%">Subtotal</th>
                                        <th style="width: 5%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr id="emptyRow"><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-inboxes fs-1 d-block mb-2 text-black-50"></i>Click products from the left to add</td></tr>
                                </tbody>
                                <tfoot class="table-light sticky-bottom">
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold align-middle">Total Amount:</td>
                                        <td colspan="2">
                                            <input type="number" name="total_amount" id="grandTotal" class="form-control fw-bold bg-white" readonly value="0.00">
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Card: Payment -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Payment Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Split Account Sections -->
                    <div class="col-12 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label fw-bold mb-0">Payment Sources (অ্যাকাউন্ট)</label>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="addPaymentSource()">
                                <i class="bi bi-plus-circle"></i> Payment Sources
                            </button>
                        </div>
                        
                        <div id="paymentSourcesContainer">
                            <!-- Initial Row -->
                            <div class="payment-source-row row g-2 mb-2 align-items-end rounded bg-light p-2 border">
                                <div class="col-md-5">
                                    <label class="form-label small">Account</label>
                                    <select name="payment_accounts[]" class="form-select source-account">
                                        <option value="">Select Account</option>
                                        <?php 
                                        $accs_result = $conn->query("SELECT id, account_name, balance FROM financial_accounts WHERE is_active=1");
                                        $accounts_list = [];
                                        while ($ac = $accs_result->fetch_assoc()) {
                                            $accounts_list[] = $ac;
                                        }
                                        foreach ($accounts_list as $ac): 
                                        ?>
                                            <option value="<?= $ac['id'] ?>">
                                                <?= escape($ac['account_name']) ?> (<?= formatCurrency($ac['balance']) ?>)
                                            </option>
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
                                    <label class="form-label small">Amount Paid</label>
                                    <div class="input-group">
                                        <span class="input-group-text px-2">৳</span>
                                        <input type="number" name="payment_amounts[]" class="form-control source-amount" step="0.01" min="0" placeholder="0" oninput="checkSplitValidation()">
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
                            <span id="splitValidationMsg" class="text-secondary fw-bold small"><i class="bi bi-info-circle"></i> Enter payment amount if paying now</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-5 d-flex justify-content-between align-items-center">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="update_cost_price" id="updateCost" value="1" checked>
                <label class="form-check-label" for="updateCost">
                    Update Product Cost Price based on this purchase
                </label>
            </div>
                <div class="form-actions-sticky">
                <button type="submit" class="btn btn-primary btn-lg w-100" id="submit_btn"><i class="bi bi-check-circle"></i> Submit Purchase</button>
            </div>
        </div>
    </form>
</div>

<!-- Template for cloning rows safely -->
<template id="paymentRowTemplate">
    <div class="payment-source-row row g-2 mb-2 align-items-end rounded bg-light p-2 border">
        <div class="col-md-5">
            <label class="form-label small">Account</label>
            <select name="payment_accounts[]" class="form-select source-account">
                <option value="">Select Account</option>
                <?php foreach($accounts_list as $ac): ?>
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
            <label class="form-label small">Amount Paid</label>
            <div class="input-group">
                <span class="input-group-text px-2">৳</span>
                <input type="number" name="payment_amounts[]" class="form-control source-amount" step="0.01" min="0" placeholder="0" oninput="checkSplitValidation()">
            </div>
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-outline-danger remove-source-btn" onclick="removePaymentSource(this)" title="Remove this split">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>
</template>

<style>
/* Mobile Responsive Table for Purchase Items */
@media screen and (max-width: 768px) {
    /* Hide table headers */
    #itemsTable thead {
        display: none;
    }

    /* Make table rows act like cards */
    #itemsTable tbody tr {
        display: block;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        margin-bottom: 15px;
        padding: 15px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    /* Make cells block level */
    #itemsTable tbody td {
        display: block;
        width: 100%;
        text-align: right; /* Values on right */
        padding: 5px 0;
        border: none;
        position: relative;
    }

    /* Add labels before content */
    #itemsTable tbody td::before {
        content: attr(data-label);
        float: left;
        font-weight: bold;
        color: #666;
    }

    /* Adjust inputs to full width */
    #itemsTable input.form-control {
        width: 100%;
        display: inline-block;
        margin-top: 5px;
    }

    /* Special handling for Name cell to look like header */
    #itemsTable tbody td:first-child {
        text-align: left;
        font-size: 1.1em;
        font-weight: bold;
        color: #333;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
        margin-bottom: 10px;
    }
    #itemsTable tbody td:first-child::before {
        display: none;
    }

    /* Key-Value Pair alignment */
    #itemsTable tbody td:not(:first-child):not(:last-child) {
        display: flex;
        justify-content: space-between;
        align-items: center;
        text-align: right;
    }
    
    #itemsTable tbody td:last-child {
        text-align: center;
        margin-top: 10px;
        border-top: 1px solid #eee;
        padding-top: 10px;
    }

    /* Footer Totals */
    #itemsTable tfoot tr {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    #itemsTable tfoot td {
        display: block;
        width: 100%;
        text-align: center;
    }
}
</style>

<!-- JavaScript for Dynamic Rows & Search -->
<script>
// --- Split Payment Logic ---
function addPaymentSource() {
    const container = document.getElementById('paymentSourcesContainer');
    const template = document.getElementById('paymentRowTemplate');
    const clone = template.content.cloneNode(true);
    
    // Auto-fill logic
    const totalAmount = parseFloat(document.getElementById('grandTotal').value) || 0;
    const splitInputs = document.querySelectorAll('.source-amount');
    let sumSplit = 0;
    splitInputs.forEach(input => {
        sumSplit += parseFloat(input.value) || 0;
    });
    
    // Find the input in the cloned node
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
    const totalAmount = parseFloat(document.getElementById('grandTotal').value) || 0;
    const splitInputs = document.querySelectorAll('.source-amount');
    
    let sumSplit = 0;
    let hasEmpty = false;
    splitInputs.forEach(input => {
        const val = parseFloat(input.value);
        if (isNaN(val) || input.value === "") {
            hasEmpty = true;
        } else {
            sumSplit += val;
        }
    });

    const msg = document.getElementById('splitValidationMsg');
    const btnSubmit = document.getElementById('submit_btn');

    // Auto-fill the first split if total is dialed in and there's only 1 row
    if(splitInputs.length === 1) {
        const isEditingAmount = document.activeElement && document.activeElement.classList.contains('source-amount');
        if(!isEditingAmount) {
            if(splitInputs[0].value === "" && totalAmount > 0) {
               splitInputs[0].value = totalAmount;
               sumSplit = totalAmount;
            }
        }
    }

    if (sumSplit === 0 && hasEmpty) {
       msg.className = 'text-secondary fw-bold small';
       msg.innerHTML = '<i class="bi bi-info-circle"></i> Enter payment amount if paying now (Due: '+totalAmount.toFixed(2)+')';
       btnSubmit.disabled = false; // Allow submission with 0 payment (Credit)
       return;
    }

    // Floating point math wiggle room
    if (Math.abs(totalAmount - sumSplit) <= 0.01) {
        msg.className = 'text-success fw-bold small';
        msg.innerHTML = '<i class="bi bi-check-circle"></i> Fully Paid';
        btnSubmit.disabled = false;
    } else if (sumSplit < totalAmount) {
        msg.className = 'text-warning text-dark fw-bold small';
        msg.innerHTML = '<i class="bi bi-exclamation-circle"></i> Partial Payment: ৳' + sumSplit.toFixed(2) + ' (Due: ৳' + (totalAmount - sumSplit).toFixed(2) + ')';
        btnSubmit.disabled = false; // Purchases allow partial payments
    } else {
        msg.className = 'text-danger fw-bold small';
        msg.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Overpaid: ৳' + sumSplit.toFixed(2) + ' (Over by ৳' + (sumSplit - totalAmount).toFixed(2) + ')';
        btnSubmit.disabled = true; // Prevent overpaying
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('productSearch');
    const tableBody = document.querySelector('#itemsTable tbody');
    const grandTotalInput = document.getElementById('grandTotal');
    const emptyRow = document.getElementById('emptyRow');
    const productGallery = document.getElementById('productGallery');
    
    let productIndex = 0;
    let allProducts = [];

    // Fetch all products on load for the POS Gallery
    fetch(`${BASE_PATH}/modules/purchases/api/get_products.php`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                allProducts = data.products;
                renderProductGallery(allProducts);
            } else {
                productGallery.innerHTML = '<div class="col-12 text-danger p-3 text-center">Failed to load products.</div>';
            }
        })
        .catch(err => {
            console.error('Error loading products:', err);
            productGallery.innerHTML = '<div class="col-12 text-danger p-3 text-center">Connection error.</div>';
        });

    // Render Product Gallery
    function renderProductGallery(productsToRender) {
        productGallery.innerHTML = '';
        if (productsToRender.length === 0) {
            productGallery.innerHTML = '<div class="col-12 text-muted p-4 text-center"><i class="bi bi-search fs-3 d-block mb-2"></i>No products found</div>';
            return;
        }

        productsToRender.forEach(p => {
            const col = document.createElement('div');
            col.className = 'col-6 col-lg-4';
            
            const card = document.createElement('div');
            card.className = 'card h-100 product-card cursor-pointer border-0 shadow-sm transition-transform';
            card.style.cursor = 'pointer';
            card.innerHTML = `
                <div class="card-body p-2 text-center d-flex flex-column justify-content-between">
                    <div>
                        <div class="fw-bold small lh-sm mb-1 text-truncate" title="${p.name}">${p.name}</div>
                        <div class="text-muted" style="font-size: 0.7rem;">${p.sku}</div>
                    </div>
                    <div class="mt-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">৳${p.cost_price}</span>
                        <div class="text-muted mt-1" style="font-size: 0.65rem;">Stock: ${p.stock_quantity}</div>
                    </div>
                </div>
            `;
            
            // Add click effect and function
            card.addEventListener('mousedown', () => card.style.transform = 'scale(0.95)');
            card.addEventListener('mouseup', () => card.style.transform = 'scale(1)');
            card.addEventListener('mouseleave', () => card.style.transform = 'scale(1)');
            card.addEventListener('click', () => {
                addProductRow(p);
                // Optional visual feedback on click
                card.style.backgroundColor = '#e8f0fe';
                setTimeout(() => card.style.backgroundColor = '', 200);
            });
            
            col.appendChild(card);
            productGallery.appendChild(col);
        });
    }

    // Client-side search filtering
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        if (query.length === 0) {
            renderProductGallery(allProducts);
            return;
        }
        
        const filtered = allProducts.filter(p => 
            p.name.toLowerCase().includes(query) || 
            p.sku.toLowerCase().includes(query)
        );
        renderProductGallery(filtered);
    });

    function addProductRow(product) {
        if (emptyRow) emptyRow.style.display = 'none';
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                ${product.name}
                <input type="hidden" name="items[${productIndex}][product_id]" value="${product.id}">
            </td>
            <td data-label="Unit Cost">
                <input type="number" class="form-control cost-input" name="items[${productIndex}][unit_cost]" value="${product.cost_price}" step="0.01">
            </td>
            <td data-label="Quantity">
                <input type="number" class="form-control qty-input" name="items[${productIndex}][quantity]" value="1" min="1">
            </td>
            <td class="subtotal" data-label="Subtotal">
                ${(product.cost_price * 1).toFixed(2)}
            </td>
            <td data-label="Action">
                <button type="button" class="btn btn-sm btn-danger remove-btn"><i class="bi bi-trash"></i></button>
            </td>
        `;
        
        tableBody.appendChild(tr);
        productIndex++;
        calculateTotal();
        
        // Event Listeners for new row
        tr.querySelector('.remove-btn').addEventListener('click', function() {
            tr.remove();
            calculateTotal();
            if(tableBody.children.length === 0 || (tableBody.children.length === 1 && tableBody.children[0].id === 'emptyRow')) {
               if(emptyRow) emptyRow.style.display = 'table-row';
            }
        });
        
        tr.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', calculateRowAndTotal);
        });
    }

    function calculateRowAndTotal(e) {
        const row = e.target.closest('tr');
        const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const sub = cost * qty;
        row.querySelector('.subtotal').textContent = sub.toFixed(2);
        calculateTotal();
    }

    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('#itemsTable tbody tr').forEach(row => {
            if(row.id === 'emptyRow') return;
            const sub = parseFloat(row.querySelector('.subtotal').textContent) || 0;
            total += sub;
        });
        grandTotalInput.value = total.toFixed(2);
        checkSplitValidation(); // Ensure split validity updates on cart change
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
