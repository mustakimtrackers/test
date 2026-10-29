<?php
/**
 * Customer Ledger Report
 * Shows detailed transaction history (Sales, Payments, Due)
 */

$pageTitle = 'Customer Ledger (কাস্টমার লেজার)';
$requiredRole = ['admin', 'manager'];
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Get all customers
$customersResult = $conn->query("SELECT id, name, company_name FROM contacts WHERE type = 'customer' ORDER BY name");
$customers = $customersResult->fetch_all(MYSQLI_ASSOC);

$selectedCustomerId = isset($_GET['customer_id']) ? $_GET['customer_id'] : null;
$transactions = [];
$customerDetails = null;

if ($selectedCustomerId) {
    // 1. Determine Customer Mode
    $isWalkIn = ($selectedCustomerId === 'walk_in');

    if ($isWalkIn) {
        $customerDetails = [
            'name' => 'Walk-in Customer',
            'company_name' => 'General (No Profile)',
            'phone' => 'N/A',
            'balance' => 0
        ];
    } else {
        $selectedCustomerId = (int)$selectedCustomerId;
        $custStmt = $conn->prepare("SELECT * FROM contacts WHERE id = ?");
        $custStmt->bind_param("i", $selectedCustomerId);
        $custStmt->execute();
        $customerDetails = $custStmt->get_result()->fetch_assoc();
    }

    // 2. Prepare Query if Customer is Valid
    if ($customerDetails) {
        // Shared Date Logic
        $isAllTime = isset($_GET['all_time']);
        $startDate = $isAllTime ? '2000-01-01' : (isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'));
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        
        // Base SQL parts
        $whereSales = $isWalkIn ? "(s.customer_id IS NULL OR s.customer_id = 0)" : "s.customer_id = ?";
        $wherePayments = $isWalkIn ? "(s.customer_id IS NULL OR s.customer_id = 0)" : "s.customer_id = ?";
        $wherePaymentsDirect = $isWalkIn ? "payable_id = 0" : "payable_id = ?";
        $whereOpening = $isWalkIn ? "id = 0" : "id = ?";

        $query = "
            (SELECT 
                s.sale_date as date, 
                s.reference_no as ref,
                COALESCE(GROUP_CONCAT(CONCAT(IFNULL(prod.name, 'Unknown'), ' (', IFNULL(si.quantity, 0), ')') SEPARATOR ', '), '') as product_names,
                'Invoice' as type, 
                '' as method,
                s.total_amount as debit, 
                0 as credit, 
                s.id as link_id
            FROM sales s
            LEFT JOIN sale_items si ON s.id = si.sale_id
            LEFT JOIN products prod ON si.product_id = prod.id
            WHERE $whereSales AND s.status != 'canceled'
            GROUP BY s.id)

            UNION ALL

            (SELECT 
                p.paid_at as date, 
                s.reference_no as ref, 
                '' as product_names,
                'Payment' as type, 
                p.method as method,
                0 as debit, 
                p.amount as credit, 
                s.id as link_id
            FROM payments p
            JOIN sales s ON p.payable_id = s.id
            WHERE p.payable_type = 'sale' AND $wherePayments)

            UNION ALL

            (SELECT 
                paid_at as date, 
                CONCAT('PAY-REC-', id) as ref, 
                '' as product_names,
                'Payment (Received)' as type, 
                method,
                0 as debit, 
                amount as credit, 
                id as link_id
            FROM payments 
            WHERE payable_type = 'customer' AND $wherePaymentsDirect)

            UNION ALL

            (SELECT 
                r.return_date as date, 
                CONCAT('RET-', r.id) as ref, 
                '' as product_names,
                'Sales Return' as type, 
                '' as method,
                0 as debit, 
                r.total_amount as credit, 
                r.id as link_id
            FROM sale_returns r
            JOIN sales s ON r.sale_id = s.id
            WHERE $whereSales AND r.status = 'completed')

            UNION ALL

            (SELECT 
                r.return_date as date, 
                CONCAT('REFUND-', r.id) as ref, 
                '' as product_names,
                'Cash Refund for Return' as type, 
                '' as method,
                r.refund_amount as debit, 
                0 as credit, 
                r.id as link_id
            FROM sale_returns r
            JOIN sales s ON r.sale_id = s.id
            WHERE $whereSales AND r.status = 'completed' AND r.refund_amount > 0)

            UNION ALL

            (SELECT 
                created_at as date,
                'OPENING' as ref,
                '' as product_names,
                'Opening Balance' as type,
                '' as method,
                opening_balance as debit,
                0 as credit,
                id as link_id
            FROM contacts 
            WHERE $whereOpening AND opening_balance > 0)
            
            ORDER BY date ASC, type DESC
        ";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
             error_log("Ledger prepare failed: " . $conn->error);
             setFlashMessage('error', 'Failed to load ledger. Please try again.');
             redirect(url('/modules/reports/customer_ledger.php'));
             exit;
        }

        if (!$isWalkIn) {
            // Bind params for standard customer (6 times)
            // Use separate variables to avoid reference issues (though unlikely)
            $id1 = $selectedCustomerId;
            $id2 = $selectedCustomerId;
            $id3 = $selectedCustomerId;
            $id4 = $selectedCustomerId;
            $id5 = $selectedCustomerId;
            $id6 = $selectedCustomerId;
            $stmt->bind_param("iiiiii", $id1, $id2, $id3, $id4, $id5, $id6);
        } else {
             // No params for walk-in (placeholders replaced by constants)
             // Except... wait. Walk-in query clauses have NO placeholders?
             // $whereSales = "...". Correct.
        }

        $stmt->execute();
        $allTransactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Filter Loop
        $transactions = [];
        $prevBalance = 0;
        
        foreach ($allTransactions as $t) {
            $txnDate = date('Y-m-d', strtotime($t['date']));
            $debit = (float)$t['debit'];
            $credit = (float)$t['credit'];
            
            // --- Refund/Return Logic for Customers ---
            // Debit (Due/Invoice) - if negative, it's a Return -> Credit
            if ($debit < 0) {
                $credit += abs($debit);
                $debit = 0;
                $t['type'] = 'Return (' . $t['type'] . ')';
            }
            // Credit (Payment) - if negative, it's a Refund -> Debit
            if ($credit < 0) {
                $debit += abs($credit);
                $credit = 0;
                $t['type'] = 'Refund (' . $t['type'] . ')';
            }

            $t['debit'] = $debit;
            $t['credit'] = $credit;

            $net = $debit - $credit;
            
            if ($txnDate < $startDate) {
                $prevBalance += $net;
            } elseif ($txnDate <= $endDate) {
                $transactions[] = $t;
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center">
                <h1 class="page-title"><i class="bi bi-book"></i> Customer Ledger</h1>
                <?php if ($selectedCustomerId): ?>
                <button type="button" onclick="openPrintWindow('<?= url('/modules/reports/print_ledger.php?ledger_type=customer&entity_id=' . $selectedCustomerId . '&start_date=' . $startDate . '&end_date=' . $endDate) ?>')" class="btn btn-primary px-4 shadow-sm">
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
                            <label class="form-label">Select Customer</label>
                            <select name="customer_id" class="form-select select2" required>
                                <option value="">-- Choose Customer --</option>
                                <option value="walk_in" <?= isset($_GET['customer_id']) && $_GET['customer_id'] === 'walk_in' ? 'selected' : '' ?>>Walk-in Customer</option>
                                <?php foreach ($customers as $cust): ?>
                                    <option value="<?= $cust['id'] ?>" <?= $selectedCustomerId == $cust['id'] ? 'selected' : '' ?>>
                                        <?= escape($cust['name']) ?> (<?= escape($cust['company_name']) ?>)
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
                                <?php if($selectedCustomerId): ?>
                                <a href="?customer_id=<?= $selectedCustomerId ?>&all_time=1" class="btn btn-outline-secondary" title="View All History">
                                    All Time
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selectedCustomerId && $customerDetails): ?>
            <div class="card">
                <div class="card-header bg-light">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><?= escape($customerDetails['name']) ?></h5>
                            <small><?= escape($customerDetails['company_name']) ?></small><br>
                            <small><?= escape($customerDetails['phone']) ?></small>
                        </div>
                        <div class="col-md-6 text-end">
                            <h4>Current Balance: <?= formatCurrency($customerDetails['balance']) ?></h4>
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
                                    <th class="text-end">Debit (Bill)</th>
                                    <th class="text-end">Credit (Paid)</th>
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
                                    
                                    // Logic: Debit increases balance (Due), Credit decreases it
                                    $netChange = $debit - $credit;
                                    $runningBalance += $netChange;
                                    
                                    $totalDebit += $debit;
                                    $totalCredit += $credit;
                                ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($txn['date'])) ?></td>
                                    <td><?= escape($txn['ref']) ?></td>
                                    <td><small class="text-muted"><?= escape($txn['product_names']) ?></small></td>
                                    <td>
                                        <?php if($txn['type'] == 'Invoice'): ?>
                                            <a href="<?= url('/modules/sales/view.php?id=' . ($txn['link_id'] ?? '#')) ?>">
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
                                    <td class="text-end text-danger fw-bold"><?= (float)$debit > 0 ? formatCurrency($debit) : '-' ?></td>
                                    <td class="text-end text-success fw-bold"><?= (float)$credit > 0 ? formatCurrency($credit) : '-' ?></td>
                                    <td class="text-end fw-bold"><?= formatCurrency($runningBalance) ?></td>
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
            <?php elseif($selectedCustomerId): ?>
                <div class="alert alert-info">No transactions found for this customer.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
