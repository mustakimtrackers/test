<?php
/**
 * Master Detailed Ledger
 */

// Enable Error Reporting
// Settings functions moved to includes/settings_helper.php

$pageTitle = 'Detailed Ledger';
$requiredRole = ['admin', 'manager'];
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// 1. Filter Logic
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$queryEndDate = $endDate . ' 23:59:59';
$ledgerType = $_GET['ledger_type'] ?? 'financial_account'; // financial_account, expense, investor, customer, supplier
$entityId = $_GET['entity_id'] ?? '';

// 2. Fetch Entities for Dropdown
$entities = [];
if ($ledgerType === 'financial_account') {
    $entities[] = ['id' => 'all', 'name' => 'All Accounts (Total Liquid Asset)'];
    $res = $conn->query("SELECT id, account_name as name FROM financial_accounts ORDER BY account_name");
    while($r = $res->fetch_assoc()) $entities[] = $r;
} elseif ($ledgerType === 'investor') {
    $res = $conn->query("SELECT id, name FROM contacts WHERE type='investor' ORDER BY name");
    while($r = $res->fetch_assoc()) $entities[] = $r;
} elseif ($ledgerType === 'expense') {
    // For expenses, we use category name as ID for simplicity
    $entities[] = ['id' => 'all', 'name' => 'All Categories'];
    $res = $conn->query("SELECT DISTINCT category as id, category as name FROM expenses ORDER BY category");
    while($r = $res->fetch_assoc()) $entities[] = $r;
} elseif ($ledgerType === 'customer') {
    $res = $conn->query("SELECT id, name FROM contacts WHERE type='customer' ORDER BY name");
    while($r = $res->fetch_assoc()) $entities[] = $r;
} elseif ($ledgerType === 'supplier') {
    $res = $conn->query("SELECT id, name FROM contacts WHERE type='supplier' ORDER BY name");
    while($r = $res->fetch_assoc()) $entities[] = $r;
}

// 3. Transactions & Opening Balance Logic
$transactions = [];
$openingBalance = 0;
$runningBalance = 0;

if (!empty($entityId)) {
    if ($ledgerType === 'financial_account') {
        // A. Opening Balance (Before Start Date)
        // For Financial Accounts: Credit (+), Debit (-) based on TransactionManager logic
        if ($entityId === 'all') {
            $opQuery = "
                SELECT 
                    SUM(opening_balance) as base_opening_balance,
                    (SELECT SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE -amount END) 
                     FROM financial_transactions 
                     WHERE transaction_date < ?) as history_balance
                FROM financial_accounts
            ";
            $stmtOp = $conn->prepare($opQuery);
            $stmtOp->bind_param("s", $startDate);
        } else {
            $opQuery = "
                SELECT 
                    opening_balance as base_opening_balance,
                    (SELECT SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE -amount END) 
                     FROM financial_transactions 
                     WHERE financial_account_id = ? AND transaction_date < ?) as history_balance
                FROM financial_accounts 
                WHERE id = ?
            ";
            $stmtOp = $conn->prepare($opQuery);
            $stmtOp->bind_param("isi", $entityId, $startDate, $entityId);
        }
        $stmtOp->execute();
        $resOp = $stmtOp->get_result()->fetch_assoc();
        $openingBalance = ($resOp['base_opening_balance'] ?? 0) + ($resOp['history_balance'] ?? 0);

        // B. Ledger Transactions
        if ($entityId === 'all') {
            $transQuery = "
                SELECT * FROM financial_transactions 
                WHERE transaction_date BETWEEN ? AND ?
                ORDER BY transaction_date ASC, created_at ASC
            ";
            $stmtTrans = $conn->prepare($transQuery);
            $stmtTrans->bind_param("ss", $startDate, $queryEndDate);
        } else {
            $transQuery = "
                SELECT * FROM financial_transactions 
                WHERE financial_account_id = ? AND transaction_date BETWEEN ? AND ?
                ORDER BY transaction_date ASC, created_at ASC
            ";
            $stmtTrans = $conn->prepare($transQuery);
            $stmtTrans->bind_param("iss", $entityId, $startDate, $queryEndDate);
        }
        $stmtTrans->execute();
        $result = $stmtTrans->get_result();
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
    } 
    elseif ($ledgerType === 'investor') {
        // Investor Logic: Injection = Credit (Payable to investor), Withdrawal = Debit
        // Opening
        $opQuery = "
            SELECT 
                SUM(CASE WHEN type IN ('injection', 'add') THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN type IN ('withdrawal', 'withdraw') THEN amount ELSE 0 END) as total_out
            FROM capital_transactions 
            WHERE investor_id = ? AND trans_date < ?
        ";
        $stmtOp = $conn->prepare($opQuery);
        $stmtOp->bind_param("is", $entityId, $startDate);
        $stmtOp->execute();
        $resOp = $stmtOp->get_result()->fetch_assoc();
        $openingBalance = ($resOp['total_in'] ?? 0) - ($resOp['total_out'] ?? 0);

        // Transactions
        $transQuery = "SELECT * FROM capital_transactions WHERE investor_id = ? AND trans_date BETWEEN ? AND ? ORDER BY trans_date ASC";
        $stmtTrans = $conn->prepare($transQuery);
        $stmtTrans->bind_param("iss", $entityId, $startDate, $queryEndDate);
        $stmtTrans->execute();
        $result = $stmtTrans->get_result();
        while ($row = $result->fetch_assoc()) {
            // Map to standard structure
            $transactions[] = [
                'date' => $row['trans_date'],
                'ref' => '-', 
                'type' => in_array($row['type'], ['add', 'injection']) ? 'Investment' : 'Withdrawal',
                'description' => $row['description'],
                'method' => $row['method'],
                'debit' => in_array($row['type'], ['withdraw', 'withdrawal']) ? $row['amount'] : 0, // Out
                'credit' => in_array($row['type'], ['add', 'injection']) ? $row['amount'] : 0, // In
            ];
        }
    }
    elseif ($ledgerType === 'expense') {
        // Expense Logic: Debit only (Spending).
        // EntityID is Category Name string here
        if ($entityId === 'all') {
            $transQuery = "SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date ASC";
            $stmtTrans = $conn->prepare($transQuery);
            $stmtTrans->bind_param("ss", $startDate, $queryEndDate);
        } else {
            $transQuery = "SELECT * FROM expenses WHERE category = ? AND expense_date BETWEEN ? AND ? ORDER BY expense_date ASC";
            $stmtTrans = $conn->prepare($transQuery);
            $stmtTrans->bind_param("sss", $entityId, $startDate, $queryEndDate);
        }
        $stmtTrans->execute();
        $result = $stmtTrans->get_result();
        while ($row = $result->fetch_assoc()) {
            $transactions[] = [
                'date' => $row['expense_date'],
                'ref' => $row['reference_no'] ?? '-', 
                'type' => 'Expense',
                'description' => $row['description'] . ($entityId === 'all' ? ' ('.$row['category'].')' : ''), // Append category if viewing all
                'method' => '-',
                'debit' => $row['amount'], // Spending
                'credit' => 0, 
            ];
        }
        
        if ($entityId === 'all') {
            $opQuery = "SELECT SUM(amount) as total FROM expenses WHERE expense_date < ?";
            $stmtOp = $conn->prepare($opQuery);
            $stmtOp->bind_param("s", $startDate);
        } else {
            $opQuery = "SELECT SUM(amount) as total FROM expenses WHERE category = ? AND expense_date < ?";
            $stmtOp = $conn->prepare($opQuery);
            $stmtOp->bind_param("ss", $entityId, $startDate);
        }
        $stmtOp->execute();
        $resOp = $stmtOp->get_result()->fetch_assoc();
        $openingBalance = $resOp['total'] ?? 0;
    }
    elseif ($ledgerType === 'supplier') {
        // Unified Supplier Logic (Matches supplier_ledger.php)
        $query = "
            SELECT * FROM (
                -- 1. Purchases (Bill -> Credit)
                SELECT 
                    p.purchase_date as date, 
                    p.reference_no as ref, 
                    'Purchase' as type, 
                    'Purchase Order' as description,
                    COALESCE(GROUP_CONCAT(CONCAT(IFNULL(prod.name, 'Unknown'), ' (', IFNULL(pi.quantity, 0), ')') SEPARATOR ', '), '') as product_names,
                    0 as debit, 
                    p.total_amount as credit 
                FROM purchases p
                LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
                LEFT JOIN products prod ON pi.product_id = prod.id
                WHERE p.supplier_id = ?
                GROUP BY p.id

                UNION ALL

                -- 2. Payments (Linked to Purchase)
                SELECT 
                    p.paid_at as date, 
                    pur.reference_no as ref, 
                    'Payment' as type, 
                    CONCAT('Payment for ', pur.reference_no) as description, 
                    '' as product_names,
                    p.amount as debit, 
                    0 as credit 
                FROM payments p
                JOIN purchases pur ON p.payable_id = pur.id
                WHERE p.payable_type = 'purchase' AND pur.supplier_id = ?

                UNION ALL

                -- 3. Direct Payments (Unlinking)
                SELECT 
                    paid_at as date, 
                    CONCAT('PAY-', id) as ref, 
                    'Payment (Direct)' as type, 
                    CONCAT('Direct Payment - ', method) as description, 
                    '' as product_names,
                    amount as debit, 
                    0 as credit 
                FROM payments 
                WHERE payable_type = 'supplier' AND payable_id = ?

                UNION ALL

                -- 4. Opening Balance
                SELECT 
                    created_at as date, 
                    'OPENING' as ref, 
                    'Opening Balance' as type, 
                    'Account Opening Balance' as description, 
                    '' as product_names,
                    0 as debit, 
                    opening_balance as credit 
                FROM contacts 
                WHERE id = ? AND opening_balance > 0
            ) AS ledger_data
            WHERE date <= ?
            ORDER BY date ASC, type DESC
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiis", $entityId, $entityId, $entityId, $entityId, $queryEndDate);
        $stmt->execute();
        $result = $stmt->get_result();

        // Process results
        while ($row = $result->fetch_assoc()) {
            if ($row['date'] < $startDate) {
                // Add to Opening Balance
                $openingBalance += ($row['credit'] - $row['debit']);
            } else {
                // Add to Current Transactions
                $transactions[] = [
                    'date' => $row['date'],
                    'ref' => $row['ref'],
                    'type' => $row['type'],
                    'description' => $row['description'],
                    'method' => '-',
                    'debit' => $row['debit'],
                    'credit' => $row['credit']
                ];
            }
        }
    }
    elseif ($ledgerType === 'customer') {
        // Unified Customer Logic (Matches customer_ledger.php)
        $query = "
            SELECT * FROM (
                -- 1. Sales (Invoice -> Debit)
                SELECT 
                    s.sale_date as date, 
                    s.reference_no as ref, 
                    'Sale' as type, 
                    'Invoice' as description,
                    COALESCE(GROUP_CONCAT(CONCAT(IFNULL(prod.name, 'Unknown'), ' (', IFNULL(si.quantity, 0), ')') SEPARATOR ', '), '') as product_names,
                    s.total_amount as debit, 
                    0 as credit 
                FROM sales s
                LEFT JOIN sale_items si ON s.id = si.sale_id
                LEFT JOIN products prod ON si.product_id = prod.id
                WHERE s.customer_id = ? AND s.status != 'canceled'
                GROUP BY s.id

                UNION ALL

                -- 2. Payments (Linked to Sale)
                SELECT 
                    p.paid_at as date, 
                    s.reference_no as ref, 
                    'Payment' as type, 
                    CONCAT('Payment for ', s.reference_no) as description, 
                    '' as product_names,
                    0 as debit, 
                    p.amount as credit 
                FROM payments p
                JOIN sales s ON p.payable_id = s.id
                WHERE p.payable_type = 'sale' AND s.customer_id = ?

                UNION ALL

                -- 3. Direct Payments (Received)
                SELECT 
                    paid_at as date, 
                    CONCAT('PAY-REC-', id) as ref, 
                    'Payment (Received)' as type, 
                    CONCAT('Direct Payment - ', method) as description, 
                    '' as product_names,
                    0 as debit, 
                    amount as credit 
                FROM payments 
                WHERE payable_type = 'customer' AND payable_id = ?

                UNION ALL

                -- 4. Opening Balance
                SELECT 
                    created_at as date, 
                    'OPENING' as ref, 
                    'Opening Balance' as type, 
                    'Account Opening Balance' as description, 
                    '' as product_names,
                    opening_balance as debit, 
                    0 as credit 
                FROM contacts 
                WHERE id = ? AND opening_balance > 0
            ) AS ledger_data
            WHERE date <= ?
            ORDER BY date ASC, type DESC
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiis", $entityId, $entityId, $entityId, $entityId, $queryEndDate);
        $stmt->execute();
        $result = $stmt->get_result();

        // Process results
        while ($row = $result->fetch_assoc()) {
            if ($row['date'] < $startDate) {
                // Add to Opening Balance (Customer: Debit increases Due)
                $openingBalance += ($row['debit'] - $row['credit']);
            } else {
                $transactions[] = [
                    'date' => $row['date'],
                    'ref' => $row['ref'],
                    'type' => $row['type'],
                    'description' => $row['description'],
                    'product_names' => $row['product_names'],
                    'method' => '-',
                    'debit' => $row['debit'],
                    'credit' => $row['credit']
                ];
            }
        }
    }
}

// Initial Running Balance
$runningBalance = $openingBalance;

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "Ledger_Export_" . ucfirst($ledgerType) . "_" . date('Ymd_His') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    // Output headers
    fputcsv($output, ['Date', 'Reference', 'Type', 'Description', 'Product Details', 'Debit', 'Credit', 'Balance']);
    
    // Output opening balance row
    $opDebit = ($ledgerType === 'customer' && $openingBalance > 0) ? abs($openingBalance) : (($ledgerType !== 'customer' && $openingBalance < 0) ? abs($openingBalance) : 0);
    $opCredit = ($ledgerType === 'customer' && $openingBalance < 0) ? abs($openingBalance) : (($ledgerType !== 'customer' && $openingBalance > 0) ? abs($openingBalance) : 0);
    
    fputcsv($output, [
        $startDate,
        'OPENING',
        'Opening Balance',
        'Balance Brought Forward',
        '',
        number_format($opDebit, 2, '.', ''),
        number_format($opCredit, 2, '.', ''),
        number_format($openingBalance, 2, '.', '')
    ]);
    
    $runBal = $openingBalance;
    
    foreach ($transactions as $row) {
        if ($ledgerType === 'financial_account') {
            $date = date('Y-m-d', strtotime($row['transaction_date']));
            $ref = $row['reference_type'] . (isset($row['reference_id']) ? '-'.$row['reference_id'] : '');
            $type = ucfirst($row['reference_type']);
            $desc = $row['description'];
            $prod_names = '';
            $debit = ($row['transaction_type'] == 'debit') ? (float)$row['amount'] : 0;
            $credit = ($row['transaction_type'] == 'credit') ? (float)$row['amount'] : 0;
        } else {
            $date = date('Y-m-d', strtotime($row['date']));
            $ref = $row['ref'];
            $type = $row['type'];
            $desc = $row['description'];
            $prod_names = $row['product_names'] ?? '';
            $debit = (float)$row['debit'];
            $credit = (float)$row['credit'];
        }

        if ($debit < 0) {
            $credit += abs($debit);
            $debit = 0;
            $type = 'Refund (' . $type . ')';
        }
        if ($credit < 0) {
            $debit += abs($credit);
            $credit = 0;
            $type = 'Return (' . $type . ')';
        }

        if ($ledgerType === 'customer') {
            $runBal += ($debit - $credit);
        } else {
            $runBal += ($credit - $debit);
        }
        
        fputcsv($output, [
            $date,
            $ref,
            $type,
            $desc,
            $prod_names,
            number_format($debit, 2, '.', ''),
            number_format($credit, 2, '.', ''),
            number_format($runBal, 2, '.', '')
        ]);
    }
    
    fclose($output);
    exit();
}

?>

<div class="container-fluid">
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Ledger Type</label>
                    <select name="ledger_type" class="form-select" onchange="this.form.submit()">
                        <option value="financial_account" <?= $ledgerType == 'financial_account' ? 'selected' : '' ?>>Financial Account (Cash/Bank)</option>
                        <option value="investor" <?= $ledgerType == 'investor' ? 'selected' : '' ?>>Investor / Capital</option>
                        <option value="expense" <?= $ledgerType == 'expense' ? 'selected' : '' ?>>Expense Category</option>
                        <!-- <option value="customer" <?= $ledgerType == 'customer' ? 'selected' : '' ?>>Customer Ledger</option> -->
                        <!-- <option value="supplier" <?= $ledgerType == 'supplier' ? 'selected' : '' ?>>Supplier Ledger</option> -->
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Select Account/Entity</label>
                    <select name="entity_id" class="form-select">
                        <option value="">-- Select --</option>
                        <?php foreach($entities as $ent): ?>
                            <option value="<?= $ent['id'] ?>" <?= $entityId == $ent['id'] ? 'selected' : '' ?>><?= escape($ent['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> View Ledger</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($entityId)): ?>
        <div class="card-header d-flex justify-content-between">
            <h5 class="mb-0">Detailed Ledger Statement</h5>
            <div>
                <a href="<?= url('/modules/reports/ledger.php?ledger_type=' . $ledgerType . '&entity_id=' . $entityId . '&start_date=' . $startDate . '&end_date=' . $endDate . '&export=csv') ?>" class="btn btn-sm btn-success me-2">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                </a>
                <button type="button" onclick="openPrintWindow('<?= url('/modules/reports/print_ledger.php?ledger_type=' . $ledgerType . '&entity_id=' . $entityId . '&start_date=' . $startDate . '&end_date=' . $endDate) ?>')" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-printer"></i> Print Statement
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0 text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <?php if(in_array($ledgerType, ['customer', 'supplier'])): ?>
                            <th>Product Details</th>
                            <?php endif; ?>
                            <th>Type</th>
                            <th>Description</th>
                            <th class="text-end">Debit (Out)</th>
                            <th class="text-end">Credit (In)</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening Balance Row -->
                        <tr class="table-secondary fw-bold">
                            <td colspan="<?= in_array($ledgerType, ['customer', 'supplier']) ? 5 : 4 ?>" class="text-start">Opening Balance</td>
                            <td></td>
                            <td></td>
                            <td class="text-end"><?= formatCurrency($openingBalance) ?></td>
                        </tr>

                        <!-- Transactions -->
                        <?php 
                        $totalDebit = 0;
                        $totalCredit = 0;
                        
                        foreach ($transactions as $row): 
                            // Normalize data based on source
                            if ($ledgerType === 'financial_account') {
                                $date = $row['transaction_date'];
                                $ref = $row['reference_type'] . (isset($row['reference_id']) ? '-'.$row['reference_id'] : '');
                                $type = ucfirst($row['reference_type']);
                                $desc = $row['description'];
                                $debit = ($row['transaction_type'] == 'debit') ? (float)$row['amount'] : 0;
                                $credit = ($row['transaction_type'] == 'credit') ? (float)$row['amount'] : 0;
                            } else {
                                // Investor, Expense, Customer, Supplier already mapped to standard
                                $date = $row['date'];
                                $ref = $row['ref'];
                                $type = $row['type'];
                                $desc = $row['description'];
                                $prod_names = $row['product_names'] ?? '';
                                $debit = (float)$row['debit'];
                                $credit = (float)$row['credit'];
                            }

                            // --- Refund Logic ---
                            // If a system saved a negative payment (Refund), move to Credit
                            if ($debit < 0) {
                                $credit += abs($debit);
                                $debit = 0;
                                $type = 'Refund (' . $type . ')';
                            }
                            // If system saved a negative purchase/bill (Return), move to Debit
                            if ($credit < 0) {
                                $debit += abs($credit);
                                $credit = 0;
                                $type = 'Return (' . $type . ')';
                            }

                            // Update Running Balance
                            if ($ledgerType === 'customer') {
                                // For Customer: Debit (Sale) is Positive (Due increases), Credit (Payment) is Negative (Due decreases)
                                $runningBalance += ($debit - $credit);
                            } else {
                                // For Supplier/Financial/Investor: Credit is Positive, Debit is Negative
                                $runningBalance += ($credit - $debit);
                            }
                            $totalDebit += $debit;
                            $totalCredit += $credit;
                        ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($date)) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= strtoupper($ref ?? '') ?></span></td>
                            <?php if(in_array($ledgerType, ['customer', 'supplier'])): ?>
                            <td><small class="text-muted"><?= escape($prod_names ?? '') ?></small></td>
                            <?php endif; ?>
                            <td><?= $type ?></td>
                            <td class="text-start"><?= escape($desc) ?></td>
                            <td class="text-end text-danger"><?= (float)$debit > 0 ? formatCurrency($debit) : '-' ?></td>
                            <td class="text-end text-success"><?= (float)$credit > 0 ? formatCurrency($credit) : '-' ?></td>
                            <td class="text-end fw-bold"><?= formatCurrency($runningBalance) ?></td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Totals Row -->
                        <tr class="table-dark fw-bold">
                            <td colspan="<?= in_array($ledgerType, ['customer', 'supplier']) ? 5 : 4 ?>" class="text-end">Total Period Movement</td>
                            <td class="text-end"><?= formatCurrency($totalDebit) ?></td>
                            <td class="text-end"><?= formatCurrency($totalCredit) ?></td>
                            <td class="text-end"><?= formatCurrency($runningBalance) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-arrow-up-circle fs-1"></i><br>
            Please select a <strong>Ledger Type</strong> and <strong>Account</strong> to view the detailed statement.
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
