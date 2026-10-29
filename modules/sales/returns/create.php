<?php
$pageTitle = "Process Sales Return";
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/TransactionManager.php';
require_once __DIR__ . '/../../../includes/InventoryManager.php';

$errors = [];
$success = false;
$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
$saleDetails = null;
$saleItems = [];

// Fetch completed sales for dropdown
$salesQuery = "SELECT s.id, s.reference_no, s.sale_date, s.total_amount, c.name as customer_name FROM sales s LEFT JOIN contacts c ON s.customer_id = c.id WHERE s.status = 'completed' ORDER BY s.id DESC LIMIT 500";
$recentSales = $conn->query($salesQuery)->fetch_all(MYSQLI_ASSOC);

// Fetch active financial accounts for refund
$accountsQuery = "SELECT id, account_name, balance FROM financial_accounts WHERE is_active = 1";
$accounts = $conn->query($accountsQuery)->fetch_all(MYSQLI_ASSOC);

if ($sale_id > 0) {
    // Get Sale Details
    $stmt = $conn->prepare("SELECT * FROM sales WHERE id = ? AND status = 'completed'");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $saleDetails = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($saleDetails) {
        // Get Sale Items
        $stmt = $conn->prepare("
            SELECT si.*, p.name as product_name, p.sku 
            FROM sale_items si 
            JOIN products p ON si.product_id = p.id 
            WHERE si.sale_id = ?
        ");
        $stmt->bind_param("i", $sale_id);
        $stmt->execute();
        $saleItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $errors[] = "Sale not found or not completed.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $return_sale_id = (int)$_POST['sale_id'];
        $financial_account_id = (int)$_POST['financial_account_id'];
        $refund_amount = (float)cleanInput($_POST['refund_amount']);
        $reason = cleanInput($_POST['reason']);
        $return_items = $_POST['return_items'] ?? []; // format: [item_id => qty]
        
        $total_return_amount = 0;
        $items_to_process = [];

        // Validate items
        foreach ($saleItems as $item) {
            $itemId = $item['id'];
            if (isset($return_items[$itemId]) && (int)$return_items[$itemId] > 0) {
                $qty = (int)$return_items[$itemId];
                if ($qty > $item['quantity']) {
                    $errors[] = "Cannot return more than purchased for {$item['product_name']}";
                } else {
                    $subtotal = $qty * $item['unit_price'];
                    $total_return_amount += $subtotal;
                    $items_to_process[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => $qty,
                        'unit_price' => $item['unit_price'],
                        'subtotal' => $subtotal
                    ];
                }
            }
        }

        if (empty($items_to_process)) {
            $errors[] = "Please select at least one item to return.";
        }
        
        if ($refund_amount > 0 && $financial_account_id <= 0) {
            $errors[] = "Please select a financial account for the refund.";
        }
        
        if ($refund_amount > $total_return_amount) {
            $errors[] = "Refund amount cannot exceed the total return value (" . formatCurrency($total_return_amount) . ").";
        }

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                // 1. Insert Return Record
                $stmt = $conn->prepare("INSERT INTO sale_returns (sale_id, return_date, total_amount, refund_amount, status, reason, created_by) VALUES (?, CURDATE(), ?, ?, 'completed', ?, ?)");
                $stmt->bind_param("iddsi", $return_sale_id, $total_return_amount, $refund_amount, $reason, $_SESSION['user_id']);
                $stmt->execute();
                $return_id = $conn->insert_id;
                $stmt->close();

                $invManager = new InventoryManager($conn);
                
                // 2. Insert Items and Update Stock
                foreach ($items_to_process as $rtItem) {
                    $stmt = $conn->prepare("INSERT INTO sale_return_items (return_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iiidd", $return_id, $rtItem['product_id'], $rtItem['quantity'], $rtItem['unit_price'], $rtItem['subtotal']);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Restock inventory
                    $invManager->addStock($rtItem['product_id'], $rtItem['quantity'], "Restocked from Sales Return", null, 'return', 'sale_return', $return_id);
                }

                // 3. Process Financial Refund (Debit to Account)
                if ($refund_amount > 0) {
                    $tm = new TransactionManager($conn);
                    $tm->recordTransaction(
                        $financial_account_id,
                        'debit', // Money going out of the business
                        $refund_amount,
                        'return',
                        $return_id,
                        "Refund for Sales Return #RET-{$return_id}",
                        $_SESSION['user_id']
                    );
                }
                
                // 4. Update Customer Balance (Ledger Adjustments)
                $customerId = $saleDetails['customer_id'] ?? 0;
                if ($customerId > 0) {
                    $net_credit_to_customer = $total_return_amount - $refund_amount;
                    if ($net_credit_to_customer != 0) {
                        $balStmt = $conn->prepare("UPDATE contacts SET balance = balance - ? WHERE id = ?");
                        $balStmt->bind_param("di", $net_credit_to_customer, $customerId);
                        $balStmt->execute();
                        $balStmt->close();
                    }
                }

                $conn->commit();
                logActivity('sale_return_created', 'sale_returns', $return_id, null, null, 'Created sales return');
                setFlashMessage('success', 'Sales return processed successfully.');
                redirect(url('/modules/sales/returns/index.php'));

            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = "Transaction failed: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="container-fluid">
    <h2 class="h3 mb-4 text-gray-800">Process Sales Return</h2>

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
        <!-- Sale Selection Step -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">1. Select Sale</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="mb-3">
                            <label class="form-label">Search Invoice / Sale</label>
                            <select class="form-select select2" name="sale_id" required onchange="this.form.submit()">
                                <option value="">-- Choose Sale --</option>
                                <?php foreach ($recentSales as $rs): ?>
                                    <option value="<?= $rs['id'] ?>" <?= $sale_id == $rs['id'] ? 'selected' : '' ?>>
                                        <?= escape($rs['reference_no']) ?> - <?= escape($rs['customer_name']) ?> (<?= date('d M', strtotime($rs['sale_date'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($saleDetails): ?>
            <div class="card shadow-sm border-info">
                <div class="card-body">
                    <h6 class="text-info fw-bold">Sale Summary</h6>
                    <p class="mb-1"><strong>Invoice:</strong> <?= escape($saleDetails['reference_no']) ?></p>
                    <p class="mb-1"><strong>Customer:</strong> <?= escape($saleDetails['customer_name']) ?></p>
                    <p class="mb-1"><strong>Date:</strong> <?= date('d M Y', strtotime($saleDetails['sale_date'])) ?></p>
                    <p class="mb-0"><strong>Total:</strong> <?= formatCurrency($saleDetails['total_amount']) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Return Processing Step -->
        <div class="col-md-8">
            <?php if ($saleDetails && !empty($saleItems)): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">2. Process Items & Refund</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="sale_id" value="<?= $sale_id ?>">

                        <div class="table-responsive mb-4">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Unit Price</th>
                                        <th>Purchased Qty</th>
                                        <th style="width: 150px;">Return Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($saleItems as $item): ?>
                                    <tr>
                                        <td>
                                            <?= escape($item['product_name']) ?><br>
                                            <small class="text-muted"><?= escape($item['sku']) ?></small>
                                        </td>
                                        <td><?= formatCurrency($item['unit_price']) ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>
                                            <input type="number" class="form-control" name="return_items[<?= $item['id'] ?>]" min="0" max="<?= $item['quantity'] ?>" value="0">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-danger">Refund Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?= getCurrencySymbol() ?></span>
                                    <input type="number" step="0.01" min="0" class="form-control fw-bold text-danger" name="refund_amount" placeholder="0.00">
                                </div>
                                <div class="form-text">Amount to hand back to the customer.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Refund Account (Source)</label>
                                <select class="form-select select2" name="financial_account_id">
                                    <option value="">-- Do Not Refund Cash / Select Account --</option>
                                    <?php foreach ($accounts as $acc): ?>
                                        <option value="<?= $acc['id'] ?>"><?= escape($acc['account_name']) ?> (Bal: <?= formatCurrency($acc['balance']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Where the refund money will be deducted from.</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Reason for Return</label>
                            <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Defective, Wrong item..."></textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="<?= url('/modules/sales/returns/index.php') ?>" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to process this return? This will update inventory and financial accounts immediately.')">
                                Complete Return
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php else: ?>
                <?php if($sale_id > 0): ?>
                    <div class="alert alert-warning">No items found for this sale.</div>
                <?php else: ?>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="bi bi-info-circle fs-4 me-3"></i>
                        <div>Please select a sale from the dropdown on the left to begin processing a return.</div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
