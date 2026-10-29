<?php
/**
 * Profit & Loss Report
 * Financial Overview
 */

$pageTitle = 'Profit & Loss Statement';
$requiredRole = ['admin', 'manager'];
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Date Filter
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-t');   // Last day of current month

// 1. Total Sales (Accrual Basis)
// Sum of all 'completed' sales in period
$salesStmt = $conn->prepare("SELECT SUM(total_amount) as total FROM sales WHERE sale_date BETWEEN ? AND ? AND status = 'completed'");
$endDateTime = $endDate . ' 23:59:59';
$salesStmt->bind_param("ss", $startDate, $endDateTime);
$salesStmt->execute();
$totalGrossSales = $salesStmt->get_result()->fetch_assoc()['total'] ?? 0;
$salesStmt->close();

$returnStmt = $conn->prepare("SELECT SUM(total_amount) as total_return FROM sale_returns WHERE return_date BETWEEN ? AND ? AND status = 'completed'");
$returnStmt->bind_param("ss", $startDate, $endDateTime);
$returnStmt->execute();
$totalSalesReturns = $returnStmt->get_result()->fetch_assoc()['total_return'] ?? 0;
$returnStmt->close();

$totalSales = $totalGrossSales - $totalSalesReturns;

// 2. Cost of Goods Sold (COGS)
// Sum of (Quantity * Cost Price) for all items in those sales
$cogsStmt = $conn->prepare("
    SELECT SUM(si.quantity * si.cost_price) as cogs
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE s.sale_date BETWEEN ? AND ? AND s.status = 'completed'
");
$cogsStmt->bind_param("ss", $startDate, $endDateTime);
$cogsStmt->execute();
$grossCogs = $cogsStmt->get_result()->fetch_assoc()['cogs'] ?? 0;
$cogsStmt->close();

$cogsReturnStmt = $conn->prepare("
    SELECT SUM(sri.quantity * si.cost_price) as return_cogs 
    FROM sale_return_items sri
    JOIN sale_returns sr ON sri.return_id = sr.id
    JOIN (
        SELECT sale_id, product_id, MAX(cost_price) as cost_price 
        FROM sale_items 
        GROUP BY sale_id, product_id
    ) si ON sr.sale_id = si.sale_id AND sri.product_id = si.product_id
    WHERE sr.return_date BETWEEN ? AND ? AND sr.status = 'completed'
");
$cogsReturnStmt->bind_param("ss", $startDate, $endDateTime);
$cogsReturnStmt->execute();
$returnCogs = $cogsReturnStmt->get_result()->fetch_assoc()['return_cogs'] ?? 0;
$cogsReturnStmt->close();

$cogs = $grossCogs - $returnCogs;

// 3. Gross Profit
$grossProfit = $totalSales - $cogs;

// 4. Expenses
$expStmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ?");
$expStmt->bind_param("ss", $startDate, $endDate);
$expStmt->execute();
$totalExpenses = $expStmt->get_result()->fetch_assoc()['total'] ?? 0;
$expStmt->close();

// 5. Net Profit
$netProfit = $grossProfit - $totalExpenses;
?>

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1 class="page-title"><i class="bi bi-graph-up-arrow"></i> Profit & Loss</h1>
        <a href="print_profit_loss.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-printer"></i> Print
        </a>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-auto">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- P&L Statement -->
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white text-center py-4">
                    <h3 class="mb-0">Statement of Profit or Loss</h3>
                    <p class="text-muted mb-0">From <?= date('d M Y', strtotime($startDate)) ?> to <?= date('d M Y', strtotime($endDate)) ?></p>
                </div>
                <div class="card-body p-0">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <!-- Revenue -->
                            <tr class="table-light">
                                <td colspan="2" class="fw-bold text-uppercase ps-4">Revenue</td>
                            </tr>
                            <tr>
                                <td class="ps-5">Sales Revenue</td>
                                <td class="text-end pe-5"><?= formatCurrency($totalSales) ?></td>
                            </tr>
                            
                            <!-- COGS -->
                            <tr class="table-light">
                                <td colspan="2" class="fw-bold text-uppercase ps-4">Cost of Sales</td>
                            </tr>
                            <tr>
                                <td class="ps-5">Cost of Goods Sold</td>
                                <td class="text-end pe-5 text-danger">(<?= formatCurrency($cogs) ?>)</td>
                            </tr>
                            
                            <!-- Gross Profit -->
                            <tr class="border-top border-bottom border-2">
                                <td class="ps-4 fw-bold">GROSS PROFIT</td>
                                <td class="text-end pe-5 fw-bold"><?= formatCurrency($grossProfit) ?></td>
                            </tr>
                            
                            <!-- Expenses -->
                            <tr class="table-light">
                                <td colspan="2" class="fw-bold text-uppercase ps-4">Operating Expenses</td>
                            </tr>
                            <tr>
                                <td class="ps-5">Total Expenses</td>
                                <td class="text-end pe-5 text-danger">(<?= formatCurrency($totalExpenses) ?>)</td>
                            </tr>
                            
                            <!-- Net Profit -->
                            <tr class="bg-light border-top border-2">
                                <td class="ps-4 fw-bold fs-5">NET PROFIT / (LOSS)</td>
                                <td class="text-end pe-5 fw-bold fs-5 <?= $netProfit >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= formatCurrency($netProfit) ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
