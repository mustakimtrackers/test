<?php
/**
 * Professional Ledger Print Template
 */

// Debug 500 Error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/../../includes/auth_check.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/../../includes/settings_helper.php';

// 1. Filter Logic (Copy from ledger.php)
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$queryEndDate = $endDate . ' 23:59:59';
$ledgerType = $_GET['ledger_type'] ?? 'financial_account';
$entityId = $_GET['entity_id'] ?? '';

if (empty($entityId)) die("Invalid Account Selected");

// 2. Fetch Entity Name
$entityName = "Unknown";
if ($ledgerType === 'financial_account') {
    if ($entityId === 'all') {
        $entityName = "All Accounts (Total Liquid Asset)";
    } else {
        $stmt = $conn->prepare("SELECT account_name FROM financial_accounts WHERE id = ?");
        $stmt->bind_param("i", $entityId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) $entityName = $r['account_name'];
    }
} elseif ($ledgerType === 'investor') {
    $stmt = $conn->prepare("SELECT name FROM contacts WHERE id = ?");
    $stmt->bind_param("i", $entityId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($r = $res->fetch_assoc()) $entityName = $r['name'];
} elseif ($ledgerType === 'expense') {
    $entityName = ($entityId === 'all') ? 'All Expense Categories' : $entityId;
} elseif ($ledgerType === 'customer' || $ledgerType === 'supplier') {
    $stmt = $conn->prepare("SELECT name FROM contacts WHERE id = ?");
    $stmt->bind_param("i", $entityId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($r = $res->fetch_assoc()) $entityName = $r['name'];
}

// 3. Transactions & Opening Balance Logic (Exact Copy)
$transactions = [];
$openingBalance = 0;
$runningBalance = 0;

if ($ledgerType === 'financial_account') {
    // Opening
    if ($entityId === 'all') {
        $opQuery = "SELECT SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as total_credit, SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as total_debit FROM financial_transactions WHERE transaction_date < ?";
        $stmtOp = $conn->prepare($opQuery);
        $stmtOp->bind_param("s", $startDate);
    } else {
        $opQuery = "SELECT SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as total_credit, SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as total_debit FROM financial_transactions WHERE financial_account_id = ? AND transaction_date < ?";
        $stmtOp = $conn->prepare($opQuery);
        $stmtOp->bind_param("is", $entityId, $startDate);
    }
    $stmtOp->execute();
    $resOp = $stmtOp->get_result()->fetch_assoc();
    $openingBalance = ($resOp['total_credit'] ?? 0) - ($resOp['total_debit'] ?? 0);

    // Transactions
    if ($entityId === 'all') {
        $transQuery = "SELECT * FROM financial_transactions WHERE transaction_date BETWEEN ? AND ? ORDER BY transaction_date ASC, created_at ASC";
        $stmtTrans = $conn->prepare($transQuery);
        $stmtTrans->bind_param("ss", $startDate, $endDate);
    } else {
        $transQuery = "SELECT * FROM financial_transactions WHERE financial_account_id = ? AND transaction_date BETWEEN ? AND ? ORDER BY transaction_date ASC, created_at ASC";
        $stmtTrans = $conn->prepare($transQuery);
        $stmtTrans->bind_param("iss", $entityId, $startDate, $queryEndDate);
    }
    $stmtTrans->execute();
    $result = $stmtTrans->get_result();
    while ($row = $result->fetch_assoc()) $transactions[] = $row;

} elseif ($ledgerType === 'investor') {
    // Opening
    $opQuery = "SELECT SUM(CASE WHEN type = 'injection' THEN amount ELSE 0 END) as total_in, SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as total_out FROM capital_transactions WHERE investor_id = ? AND trans_date < ?";
    $stmtOp = $conn->prepare($opQuery);
    $stmtOp->bind_param("is", $entityId, $startDate);
    $stmtOp->execute();
    $resOp = $stmtOp->get_result()->fetch_assoc();
    $openingBalance = ($resOp['total_in'] ?? 0) - ($resOp['total_out'] ?? 0);

    // Transactions
    $transQuery = "SELECT * FROM capital_transactions WHERE investor_id = ? AND trans_date BETWEEN ? AND ? ORDER BY trans_date ASC";
    $stmtTrans = $conn->prepare($transQuery);
    $stmtTrans->bind_param("iss", $entityId, $startDate, $endDate);
    $stmtTrans->execute();
    $result = $stmtTrans->get_result();
    while ($row = $result->fetch_assoc()) {
        $transactions[] = [
            'date' => $row['trans_date'],
            'ref' => '-',
            'description' => $row['description'],
            'debit' => $row['type'] == 'withdrawal' ? $row['amount'] : 0,
            'credit' => $row['type'] == 'injection' ? $row['amount'] : 0
        ];
    }
} elseif ($ledgerType === 'expense') {
    // Expense
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

    if ($entityId === 'all') {
        $transQuery = "SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date ASC";
        $stmtTrans = $conn->prepare($transQuery);
        $stmtTrans->bind_param("ss", $startDate, $endDate);
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
            'description' => $row['description'] . ($entityId === 'all' ? ' ('.$row['category'].')' : ''),
            'debit' => $row['amount'],
            'credit' => 0
        ];
    }
} elseif ($ledgerType === 'supplier') {
    // Unified Supplier Logic (Mirrors supplier_ledger.php)
    $query = "
        (SELECT p.purchase_date as date, p.reference_no as ref, 'Purchase' as type, 'Purchase Order' as description, 0 as debit, p.total_amount as credit, COALESCE(GROUP_CONCAT(CONCAT(IFNULL(prod.name, 'Unknown'), ' (', IFNULL(pi.quantity, 0), ')') SEPARATOR ', '), '') as prod_names FROM purchases p LEFT JOIN purchase_items pi ON p.id = pi.purchase_id LEFT JOIN products prod ON pi.product_id = prod.id WHERE p.supplier_id = ? AND p.status != 'canceled' GROUP BY p.id)
        UNION ALL
        (SELECT p.paid_at as date, pur.reference_no as ref, 'Payment' as type, CONCAT('Payment for ', pur.reference_no) as description, p.amount as debit, 0 as credit, '' as prod_names FROM payments p JOIN purchases pur ON p.payable_id = pur.id WHERE p.payable_type = 'purchase' AND pur.supplier_id = ?)
        UNION ALL
        (SELECT paid_at as date, CONCAT('PAY-', id) as ref, 'Payment (Direct)' as type, 'Direct Payment / Refund' as description, amount as debit, 0 as credit, '' as prod_names FROM payments WHERE payable_type = 'supplier' AND payable_id = ?)
        UNION ALL
        (SELECT created_at as date, 'OPENING' as ref, 'Opening Balance' as type, 'Account Opening Balance' as description, 0 as debit, opening_balance as credit, '' as prod_names FROM contacts WHERE id = ? AND opening_balance > 0)
        ORDER BY date ASC, type DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $entityId, $entityId, $entityId, $entityId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $txnDate = date('Y-m-d', strtotime($row['date']));
        $debit = (float)$row['debit'];
        $credit = (float)$row['credit'];

        if ($debit < 0) { 
            $credit += abs($debit);
            $debit = 0;
            $row['type'] = 'Refund (' . $row['type'] . ')';
        }
        if ($credit < 0) { 
            $debit += abs($credit);
            $credit = 0;
            $row['type'] = 'Return (' . $row['type'] . ')';
        }
        
        $row['debit'] = $debit;
        $row['credit'] = $credit;
        $net = $credit - $debit;

        if ($txnDate < $startDate) {
            $openingBalance += $net;
        } elseif ($txnDate <= $endDate) {
            $transactions[] = ['date' => $row['date'], 'ref' => $row['ref'], 'description' => $row['description'], 'debit' => $row['debit'], 'credit' => $row['credit'], 'prod_names' => $row['prod_names']];
        }
    }
} elseif ($ledgerType === 'customer') {
    // Unified Customer Logic (Mirrors customer_ledger.php)
    $query = "
        (SELECT s.sale_date as date, s.reference_no as ref, 'Invoice' as type, '' as description, s.total_amount as debit, 0 as credit, COALESCE(GROUP_CONCAT(CONCAT(IFNULL(prod.name, 'Unknown'), ' (', IFNULL(si.quantity, 0), ')') SEPARATOR ', '), '') as prod_names FROM sales s LEFT JOIN sale_items si ON s.id = si.sale_id LEFT JOIN products prod ON si.product_id = prod.id WHERE s.customer_id = ? AND s.status != 'canceled' GROUP BY s.id)
        UNION ALL
        (SELECT p.paid_at as date, s.reference_no as ref, 'Payment' as type, CONCAT('Payment for ', s.reference_no) as description, 0 as debit, p.amount as credit, '' as prod_names FROM payments p JOIN sales s ON p.payable_id = s.id WHERE p.payable_type = 'sale' AND s.customer_id = ?)
        UNION ALL
        (SELECT paid_at as date, CONCAT('PAY-REC-', id) as ref, 'Payment (Received)' as type, 'Direct Payment / Refund' as description, 0 as debit, amount as credit, '' as prod_names FROM payments WHERE payable_type = 'customer' AND payable_id = ?)
        UNION ALL
        (SELECT r.return_date as date, CONCAT('RET-', r.id) as ref, 'Sales Return' as type, 'Sales Return' as description, 0 as debit, r.total_amount as credit, '' as prod_names FROM sale_returns r JOIN sales s ON r.sale_id = s.id WHERE s.customer_id = ? AND r.status = 'completed')
        UNION ALL
        (SELECT r.return_date as date, CONCAT('REFUND-', r.id) as ref, 'Cash Refund for Return' as type, 'Cash Refund for Return' as description, r.refund_amount as debit, 0 as credit, '' as prod_names FROM sale_returns r JOIN sales s ON r.sale_id = s.id WHERE s.customer_id = ? AND r.status = 'completed' AND r.refund_amount > 0)
        UNION ALL
        (SELECT created_at as date, 'OPENING' as ref, 'Opening Balance' as type, 'Account Opening Balance' as description, opening_balance as debit, 0 as credit, '' as prod_names FROM contacts WHERE id = ? AND opening_balance > 0)
        ORDER BY date ASC, type DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiiii", $entityId, $entityId, $entityId, $entityId, $entityId, $entityId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $txnDate = date('Y-m-d', strtotime($row['date']));
        $debit = (float)$row['debit'];
        $credit = (float)$row['credit'];

        if ($debit < 0) { 
            $credit += abs($debit);
            $debit = 0;
            $row['type'] = 'Return (' . $row['type'] . ')';
        }
        if ($credit < 0) { 
            $debit += abs($credit);
            $credit = 0;
            $row['type'] = 'Refund (' . $row['type'] . ')';
        }

        $row['debit'] = $debit;
        $row['credit'] = $credit;
        $net = $debit - $credit;

        if ($txnDate < $startDate) {
            $openingBalance += $net;
        } elseif ($txnDate <= $endDate) {
            $transactions[] = ['date' => $row['date'], 'ref' => $row['ref'], 'description' => $row['description'], 'debit' => $row['debit'], 'credit' => $row['credit'], 'prod_names' => $row['prod_names']];
        }
    }
}

$runningBalance = $openingBalance;
$company = getCompanyInfo();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ledger - <?= escape($entityName) ?></title>
    <link rel="stylesheet" href="<?= url('/assets/css/print_standard.css?v=' . time()) ?>">
</head>
<body>
    <div class="print-controls">
        <button class="btn btn-secondary" onclick="window.history.back()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back
        </button>
        <button class="btn btn-primary" onclick="window.print()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print Ledger
        </button>
    </div>

    <div id="printableArea">
        <div class="document-header">
            <div class="company-branding">
                <h1><?= escape($company['name']) ?></h1>
                <div class="company-details">
                    <?php if ($company['address']): ?><div><?= nl2br(escape($company['address'])) ?></div><?php endif; ?>
                    <?php if ($company['phone']): ?><div>P: <?= escape($company['phone']) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="document-meta">
                <div class="document-type">LEDGER STATEMENT</div>
                <div class="meta-grid">
                    <div class="meta-label">Date Generated:</div>
                    <div class="meta-value"><?= date('d M Y') ?></div>
                    <div class="meta-label">Period:</div>
                    <div class="meta-value"><?= date('d M Y', strtotime($startDate)) ?> to <?= date('d M Y', strtotime($endDate)) ?></div>
                </div>
            </div>
        </div>

        <div class="info-blocks">
            <div class="info-box">
                <div class="info-box-title">Account Details</div>
                <div class="fw-bold mb-1" style="font-size: 14pt;"><?= escape($entityName) ?></div>
                <div class="text-muted" style="font-size: 10pt;">
                    Type: <?= ucwords(str_replace('_', ' ', $ledgerType)) ?>
                </div>
            </div>
            <div class="info-box">
                <div class="info-box-title">Opening Balance</div>
                <div class="fw-bold" style="font-size: 14pt;"><?= formatCurrency($openingBalance) ?></div>
            </div>
        </div>

        <table class="table-print">
            <thead>
                <tr>
                    <th style="width: 12%">Date</th>
                    <th style="width: 15%">Reference</th>
                    <?php if(in_array($ledgerType, ['customer', 'supplier'])): ?>
                    <th style="width: 25%">Product Details</th>
                    <th style="width: 18%">Description</th>
                    <?php else: ?>
                    <th style="width: 43%">Description</th>
                    <?php endif; ?>
                    <th class="text-end" style="width: 10%">Debit</th>
                    <th class="text-end" style="width: 10%">Credit</th>
                    <th class="text-end" style="width: 10%">Balance</th>
                </tr>
            </thead>
            <tbody>
                <!-- Opening Balance Row -->
                <tr style="background-color: #f8f9fa !important; -webkit-print-color-adjust: exact;">
                    <td colspan="<?= in_array($ledgerType, ['customer', 'supplier']) ? 4 : 3 ?>" class="fw-bold">Opening Balance</td>
                    <td></td>
                    <td></td>
                    <td class="text-end fw-bold"><?= formatCurrency($openingBalance) ?></td>
                </tr>

                <?php 
                $totalDebit = 0;
                $totalCredit = 0;
                foreach ($transactions as $row): 
                    if (isset($row['transaction_date'])) {
                        // Financial Account Raw Data
                        $date = $row['transaction_date'];
                        $ref = (isset($row['reference_type']) ? ucfirst($row['reference_type']) : '-') . (isset($row['reference_id']) && $row['reference_id'] ? '-'.$row['reference_id'] : '');
                        $desc = $row['description'];
                        $debit = ($row['transaction_type'] == 'debit') ? $row['amount'] : 0;
                        $credit = ($row['transaction_type'] == 'credit') ? $row['amount'] : 0;
                    } else {
                        // Standardized Data (Expense, Customer, Supplier, Investor)
                        $date = $row['date'];
                        $ref = $row['ref'] ?? '-';
                        $desc = $row['description'] ?? '';
                        $debit = (float)$row['debit'];
                        $credit = (float)$row['credit'];
                    }

                    // --- Refund Logic ---
                    if ($debit < 0) {
                        $credit += abs($debit);
                        $debit = 0;
                        $desc .= ' [Refund]';
                    }
                    if ($credit < 0) {
                        $debit += abs($credit);
                        $credit = 0;
                        $desc .= ' [Return]';
                    }
                    
                    if ($ledgerType === 'customer') {
                        $runningBalance += ($debit - $credit);
                    } else {
                        $runningBalance += ($credit - $debit);
                    }
                    $totalDebit += $debit;
                    $totalCredit += $credit;
                ?>
                <tr>
                    <td><?= date('d M Y', strtotime($date)) ?></td>
                    <td><?= escape($ref) ?></td>
                    <?php if(in_array($ledgerType, ['customer', 'supplier'])): ?>
                    <td><div style="font-size: 8pt; color: #666;"><?= escape($row['prod_names'] ?? '') ?></div></td>
                    <?php endif; ?>
                    <td><?= escape($desc) ?></td>
                    <td class="text-end"><?= (float)$debit > 0 ? formatCurrency($debit) : '-' ?></td>
                    <td class="text-end"><?= (float)$credit > 0 ? formatCurrency($credit) : '-' ?></td>
                    <td class="text-end fw-bold"><?= formatCurrency($runningBalance) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-container">
            <div class="summary-box" style="width: 350px;">
                <div class="summary-row">
                    <div class="summary-label">Total Debit</div>
                    <div class="summary-value"><?= formatCurrency($totalDebit) ?></div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">Total Credit</div>
                    <div class="summary-value"><?= formatCurrency($totalCredit) ?></div>
                </div>
                <div class="summary-row grand-total">
                    <div class="summary-label">Closing Balance</div>
                    <div class="summary-value"><?= formatCurrency($runningBalance) ?></div>
                </div>
            </div>
        </div>

        <div class="document-footer">
            <div class="signature-box">
                <div class="signature-line">Prepared By</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Authorized Signature</div>
            </div>
        </div>

        <div class="system-credit">
            Generated securely by FBM ERP System on <?= date('d M Y, h:i A') ?>
        </div>
    </div>
</body>
</html>
