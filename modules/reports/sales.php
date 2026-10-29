<?php
/**
 * Sales Report
 * detailed sales analysis
 */

$pageTitle = 'Sales Report';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Date Filter
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$status = $_GET['status'] ?? 'all';
$customerId = $_GET['customer_id'] ?? 'all';

// Build Query
$where = ["s.sale_date BETWEEN ? AND ?"];
$params = [$startDate, $endDate . ' 23:59:59'];
$types = "ss";

if ($status !== 'all') {
    $where[] = "s.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($customerId !== 'all' && is_numeric($customerId)) {
    $where[] = "s.customer_id = ?";
    $params[] = $customerId;
    $types .= "i";
}

$whereSQL = implode(' AND ', $where);

// Get Sales
$query = "
    SELECT 
        s.*,
        c.name as customer_name
    FROM sales s
    LEFT JOIN contacts c ON s.customer_id = c.id
    WHERE $whereSQL
    ORDER BY s.sale_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$sales = $result->fetch_all(MYSQLI_ASSOC);

// Calculate Totals
$totalRevenue = 0;
$totalPaid = 0;
$totalDue = 0;

foreach ($sales as $sale) {
    if ($sale['status'] !== 'canceled') {
        $totalRevenue += $sale['total_amount'];
        $totalPaid += $sale['paid_amount'];
        // Due amount isn't stored directly, but we can calc or use logic if partial payments existed
        // For now, let's assume due = total - paid
        $totalDue += ($sale['total_amount'] - $sale['paid_amount']);
    }
}

// Get Customers for filter
$customers = $conn->query("SELECT id, name FROM contacts WHERE type = 'customer' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1 class="page-title"><i class="bi bi-bar-chart-line"></i> Sales Report</h1>
        <a href="print_sales.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&status=<?= $status ?>&customer_id=<?= $customerId ?>" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-printer"></i> Print
        </a>
    </div>

    <!-- Filters -->
    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="canceled" <?= $status === 'canceled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select select2">
                        <option value="all">All Customers</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $customerId == $c['id'] ? 'selected' : '' ?>>
                                <?= escape($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Revenue</h5>
                    <h2 class="display-6 fw-bold"><?= formatCurrency($totalRevenue) ?></h2>
                    <small><?= count($sales) ?> transactions</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Collected</h5>
                    <h2 class="display-6 fw-bold"><?= formatCurrency($totalPaid) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Total Due</h5>
                    <h2 class="display-6 fw-bold"><?= formatCurrency($totalDue) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sales)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No sales found for selected period</td></tr>
                        <?php else: ?>
                            <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td><?= date('d M Y h:i A', strtotime($sale['sale_date'])) ?></td>
                                <td><a href="<?= url('/modules/sales/view.php?id=' . $sale['id']) ?>">
                                    <?= escape($sale['reference_no'] ?? '-') ?>
                                </a></td>
                                <td><?= escape($sale['customer_name'] ?? 'Walk-in Customer') ?></td>
                                <td>
                                    <?php
                                    $badge = 'secondary';
                                    if ($sale['status'] === 'completed') $badge = 'success';
                                    if ($sale['status'] === 'pending') $badge = 'warning';
                                    if ($sale['status'] === 'canceled') $badge = 'danger';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= ucfirst($sale['status']) ?></span>
                                </td>
                                <td class="text-end fw-bold"><?= formatCurrency($sale['total_amount']) ?></td>
                                <td class="text-end text-success"><?= formatCurrency($sale['paid_amount']) ?></td>
                                <td class="text-end text-danger"><?= formatCurrency($sale['total_amount'] - $sale['paid_amount']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
