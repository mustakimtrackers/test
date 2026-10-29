<?php
$pageTitle = 'Balance Sheet';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

if (!isAdmin()) {
    die("Access Denied");
}

// Fetch balances
$query = "SELECT c.code, c.name, c.type, SUM(l.debit) as total_debit, SUM(l.credit) as total_credit 
          FROM chart_of_accounts c 
          LEFT JOIN journal_entry_lines l ON c.id = l.account_id 
          GROUP BY c.id ORDER BY c.code ASC";
$result = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

$assets = []; $total_assets = 0;
$liabilities = []; $total_liabilities = 0;
$equity = []; $total_equity = 0;
// We also need net income (Revenue - Expenses) to add to Equity
$total_revenue = 0;
$total_expense = 0;

// Fetch opening balances from financial_accounts
$openBalQ = $conn->query("SELECT SUM(opening_balance) as tot_open FROM financial_accounts");
$openBal = (float)($openBalQ->fetch_assoc()['tot_open'] ?? 0);

if ($openBal > 0) {
    $assets[] = ['name' => 'Cash/Bank (Opening Balances)', 'bal' => $openBal];
    $total_assets += $openBal;
    
    $equity[] = ['name' => 'Opening Balance Equity', 'bal' => $openBal];
    $total_equity += $openBal;
}

foreach ($result as $row) {
    $debit = $row['total_debit'] ?? 0;
    $credit = $row['total_credit'] ?? 0;
    
    $type = strtolower($row['type']);
    if ($type == 'asset') {
        $bal = $debit - $credit;
        if ($bal != 0) { $assets[] = ['name' => $row['name'], 'bal' => $bal]; $total_assets += $bal; }
    }
    elseif ($type == 'liability') {
        $bal = $credit - $debit;
        if ($bal != 0) { $liabilities[] = ['name' => $row['name'], 'bal' => $bal]; $total_liabilities += $bal; }
    }
    elseif ($type == 'equity') {
        $bal = $credit - $debit;
        if ($bal != 0) { $equity[] = ['name' => $row['name'], 'bal' => $bal]; $total_equity += $bal; }
    }
    elseif ($type == 'revenue') {
        $total_revenue += ($credit - $debit);
    }
    elseif ($type == 'expense') {
        $total_expense += ($debit - $credit);
    }
}

$net_income = $total_revenue - $total_expense;
$total_equity += $net_income;
$total_liabilities_and_equity = $total_liabilities + $total_equity;

?>
<div class="container-fluid">
    <div class="page-header mb-4">
        <h1 class="page-title"><i class="bi bi-bank2"></i> Balance Sheet</h1>
        <button class="btn btn-outline-secondary px-4 rounded-pill shadow-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>
    
    <div class="row">
        <!-- ASSETS -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Assets</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <tbody>
                            <?php foreach ($assets as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['name']) ?></td>
                                <td class="text-end fw-bold"><?= number_format($a['bal'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td class="fw-bold">Total Assets</td>
                                <td class="text-end fw-bold fs-5 text-primary"><?= number_format($total_assets, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- LIABILITIES & EQUITY -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Liabilities & Equity</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <tbody>
                            <tr><td colspan="2" class="fw-bold bg-light">Liabilities</td></tr>
                            <?php foreach ($liabilities as $l): ?>
                            <tr>
                                <td><?= htmlspecialchars($l['name']) ?></td>
                                <td class="text-end fw-bold"><?= number_format($l['bal'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <tr><td colspan="2" class="fw-bold bg-light">Equity</td></tr>
                            <?php foreach ($equity as $e): ?>
                            <tr>
                                <td><?= htmlspecialchars($e['name']) ?></td>
                                <td class="text-end fw-bold"><?= number_format($e['bal'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td>Retained Earnings (Net Income)</td>
                                <td class="text-end fw-bold"><?= number_format($net_income, 2) ?></td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td class="fw-bold">Total Liabilities & Equity</td>
                                <td class="text-end fw-bold fs-5 text-danger"><?= number_format($total_liabilities_and_equity, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (abs($total_assets - $total_liabilities_and_equity) > 0.01): ?>
        <div class="alert alert-danger mt-3">
            <i class="bi bi-exclamation-triangle-fill"></i> Warning: Balance Sheet does not match! Difference: <?= number_format(abs($total_assets - $total_liabilities_and_equity), 2) ?>
        </div>
    <?php else: ?>
        <div class="alert alert-success mt-3">
            <i class="bi bi-check-circle-fill"></i> Balance Sheet is perfectly balanced.
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
