<?php
/**
 * Supplier Ledger Report
 * Shows detailed transaction history (Purchases, Payments, Due)
 */

$pageTitle = 'Supplier Ledger';
$requiredRole = ['admin', 'manager'];
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Get all suppliers
$suppliersResult = $conn->query("SELECT id, name, company_name FROM contacts WHERE type = 'supplier' ORDER BY name");
$suppliers = $suppliersResult->fetch_all(MYSQLI_ASSOC);

$selectedSupplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
$transactions = [];
$supplierDetails = null;

if ($selectedSupplierId) {
    // Get Supplier Details
    $suppStmt = $conn->prepare("SELECT * FROM contacts WHERE id = ?");
    $suppStmt->bind_param("i", $selectedSupplierId);
    $suppStmt->execute();
    $supplierDetails = $suppStmt->get_result()->fetch_assoc();

    // Similar logic to Customer Ledger but for Purchases
    // We owe them money (Credit), Payments reduce debt (Debit)
    
    // Purchases increase Payable (Credit)
    // Payments decrease Payable (Debit)
    
    // 1. Purchases - CREDIT (We owe)
    $query = "
        (SELECT 
            p.purchase_date as date, 
            p.reference_no as ref,
            COALESCE(GROUP_CONCAT(CONCAT(IFNULL(prod.name, 'Unknown'), ' (', IFNULL(pi.quantity, 0), ')') SEPARATOR ', '), '') as product_names,
            'Purchase' as type, 
            '' as method,
            0 as debit, 
            p.total_amount as credit, 
            p.id as link_id
        FROM purchases p
        LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
        LEFT JOIN products prod ON pi.product_id = prod.id
        WHERE p.supplier_id = ?
        GROUP BY p.id)

        UNION ALL

        (SELECT 
            p.paid_at as date, 
            pur.reference_no as ref, 
            '' as product_names,
            'Payment' as type, 
            p.method as method,
            p.amount as debit, 
            0 as credit, 
            pur.id as link_id
        FROM payments p
        JOIN purchases pur ON p.payable_id = pur.id
        WHERE p.payable_type = 'purchase' AND pur.supplier_id = ?)

        UNION ALL

        (SELECT 
            paid_at as date, 
            CONCAT('PAY-', id) as ref, 
            '' as product_names,
            'Payment (Direct)' as type, 
            method,
            amount as debit, 
            0 as credit, 
            id as link_id
        FROM payments 
        WHERE payable_type = 'supplier' AND payable_id = ?)

        UNION ALL

        (SELECT 
            created_at as date,
            'OPENING' as ref,
            '' as product_names,
            'Opening Balance' as type,
            '' as method,
            0 as debit,
            opening_balance as credit, 
            id as link_id
        FROM contacts 
        WHERE id = ? AND opening_balance > 0)
        
        ORDER BY date ASC, type DESC
    ";

    // Date Filter Logic
    $isAllTime = isset($_GET['all_time']);
    $startDate = $isAllTime ? '2000-01-01' : (isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $queryEndDate = $endDate . ' 23:59:59';

    // Query mostly same, just fetching EVERYTHING for this supplier to calculate running balance correctly
    // Optimization: We could filter date <= $endDate in SQL, but for now fetch all to be safe or just <= endDate
    // Let's filter <= endDate in SQL to avoid fetching future data unnecessary
    
    // We wrap the previous Union query in a subquery or just add WHERE clauses.
    // Given the complexity, let's fetch ALL for this supplier and filter in PHP for Opening Balance
    // This ensures accurate running balance even if we change dates.

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("iiii", $selectedSupplierId, $selectedSupplierId, $selectedSupplierId, $selectedSupplierId);
    $stmt->execute();
    $result = $stmt->get_result();
    $allTransactions = $result->fetch_all(MYSQLI_ASSOC);
    
    // Filter and Calculate Opening Balance
    $transactions = [];
    $prevBalance = 0;
    
    foreach ($allTransactions as $t) {
        $txnDate = date('Y-m-d', strtotime($t['date']));
        
        $debit = (float)$t['debit'];
        $credit = (float)$t['credit'];
        
        // --- Refund Logic ---
        // If system saved a negative payment (Refund), it should be a Credit
        if ($debit < 0) {
            $credit += abs($debit);
            $debit = 0;
            $t['type'] = 'Refund (' . $t['type'] . ')';
        }
        // If system saved a negative purchase/bill (Return), it should be a Debit
        if ($credit < 0) {
            $debit += abs($credit);
            $credit = 0;
            $t['type'] = 'Return (' . $t['type'] . ')';
        }

        // Update the array with processed values so HTML renders it correctly
        $t['debit'] = $debit;
        $t['credit'] = $credit;

        // Net change: Credit (Bill/Due) - Debit (Payment)
        $net = $credit - $debit;
        
        if ($txnDate < $startDate) {
            $prevBalance += $net;
        } elseif ($txnDate <= $endDate) {
            $transactions[] = $t;
        }
    }
    
    // Add "Opening Balance Forward" as first item if non-zero
    if ($prevBalance != 0 || empty($transactions)) {
        // Only if we have transactions or balance
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center">
                <h1 class="page-title"><i class="bi bi-book"></i> Supplier Ledger</h1>
                <?php if ($selectedSupplierId): ?>
                <button type="button" onclick="openPrintWindow('<?= url('/modules/reports/print_ledger.php?ledger_type=supplier&entity_id=' . $selectedSupplierId . '&start_date=' . $startDate . '&end_date=' . $endDate) ?>')" class="btn btn-primary px-4 shadow-sm">
                    <i class="bi bi-printer"></i> Print Ledger
                </button>
                <?php else: ?>
                <button type="button" class="btn btn-secondary disabled px-4" title="Please generate a ledger first to print">
                    <i class="bi bi-printer"></i> Print Ledger
                </button>
                <?php endif; ?>
            </div>
            
            <div class="card mb-4 no-print">
                <div class="card-body">
                    <form method="GET" class="row align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Select Supplier</label>
                            <select name="supplier_id" class="form-select select2" required>
                                <option value="">-- Choose Supplier --</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?= $sup['id'] ?>" <?= $selectedSupplierId == $sup['id'] ? 'selected' : '' ?>>
                                        <?= escape($sup['name']) ?> (<?= escape($sup['company_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From</label>
                            <input type="date" name="start_date" class="form-control" value="<?= isset($_GET['start_date']) ? escape($_GET['start_date']) : date('Y-m-01') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To</label>
                            <input type="date" name="end_date" class="form-control" value="<?= isset($_GET['end_date']) ? escape($_GET['end_date']) : date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> View
                                </button>
                                <?php if($selectedSupplierId): ?>
                                <a href="?supplier_id=<?= $selectedSupplierId ?>&all_time=1" class="btn btn-outline-secondary" title="View All History">
                                    All Time
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selectedSupplierId && $supplierDetails): ?>
            <div class="card">
                <div class="card-header bg-light">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><?= escape($supplierDetails['name']) ?></h5>
                            <small><?= escape($supplierDetails['company_name']) ?></small><br>
                            <small><?= escape($supplierDetails['phone']) ?></small>
                        </div>
                        <div class="col-md-6 text-end">
                            <h4>Current Balance: <?= formatCurrency($supplierDetails['balance']) ?></h4>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0 text-nowrap">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Product Details</th>
                                    <th>Type</th>
                                    <th>Method</th>
                                    <th class="text-end">Debit (Paid)</th>
                                    <th class="text-end">Credit (Bill)</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $runningBalance = $prevBalance;
                                $totalDebit = 0;
                                $totalCredit = 0;
                                
                                // Show Previous Balance Row
                                if (true): // Always show start line
                                ?>
                                <tr class="table-info fw-bold">
                                    <td colspan="5" class="text-end">Opening Balance Forward</td>
                                    <td class="text-end">-</td>
                                    <td class="text-end">-</td>
                                    <td class="text-end"><?= formatCurrency($prevBalance) ?></td>
                                </tr>
                                <?php endif; ?>

                                <?php foreach ($transactions as $txn): 
                                    $debit = (float)$txn['debit'];
                                    $credit = (float)$txn['credit'];
                                    
                                    // For Suppliers: Credit increases what we owe, Debit (Payments) decrease it.
                                    $netChange = $credit - $debit;
                                    $runningBalance += $netChange;
                                    
                                    $totalDebit += $debit;
                                    $totalCredit += $credit;
                                ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($txn['date'])) ?></td>
                                    <td><?= escape($txn['ref']) ?></td>
                                    <td><small class="text-muted"><?= escape($txn['product_names']) ?></small></td>
                                    <td>
                                        <?php if($txn['type'] == 'Purchase'): ?>
                                            <a href="<?= url('/modules/purchases/view.php?id=' . ($txn['link_id'] ?? '#')) ?>" target="_blank">
                                                <?= escape($txn['type']) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= escape($txn['type']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($txn['method']): ?>
                                            <span class="badge bg-secondary"><?= strtoupper(escape($txn['method'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-success fw-bold"><?= (float)$debit > 0 ? formatCurrency($debit) : '-' ?></td>
                                    <td class="text-end text-danger fw-bold"><?= (float)$credit > 0 ? formatCurrency($credit) : '-' ?></td>
                                    <td class="text-end"><?= formatCurrency($runningBalance) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="5" class="text-end">Total</td>
                                    <td class="text-end"><?= formatCurrency($totalDebit) ?></td>
                                    <td class="text-end"><?= formatCurrency($totalCredit) ?></td>
                                    <td class="text-end"><?= formatCurrency($runningBalance) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php elseif($selectedSupplierId): ?>
                <div class="alert alert-info">No transactions found for this supplier.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
