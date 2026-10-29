<?php
$pageTitle = 'Branch Dashboard';
require_once __DIR__ . '/../../includes/auth_check.php';

$branch_id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_SESSION['branch_id'] ?? 1);

// Only Admin can view specific branch dashboards (unless it's their own branch)
if (!isAdmin() && (int)($_SESSION['branch_id'] ?? 1) !== $branch_id) {
    die("Access Denied");
}

require_once __DIR__ . '/../../includes/header.php';

// Get Branch Details
$stmt = $conn->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$branch = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$branch) {
    echo "<div class='container-fluid mt-4'><div class='alert alert-danger'>Branch not found.</div></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// 1. Total Sales this month
$salesStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE branch_id = ? AND MONTH(sale_date) = MONTH(CURRENT_DATE()) AND YEAR(sale_date) = YEAR(CURRENT_DATE()) AND status = 'completed'");
$salesStmt->bind_param("i", $branch_id);
$salesStmt->execute();
$monthlySales = $salesStmt->get_result()->fetch_assoc()['total'];
$salesStmt->close();

// 2. Total Expenses this month
$expStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE branch_id = ? AND MONTH(expense_date) = MONTH(CURRENT_DATE()) AND YEAR(expense_date) = YEAR(CURRENT_DATE())");
$expStmt->bind_param("i", $branch_id);
$expStmt->execute();
$monthlyExpenses = $expStmt->get_result()->fetch_assoc()['total'];
$expStmt->close();

// 3. Total Users
$usrStmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE branch_id = ? AND is_active = 1");
$usrStmt->bind_param("i", $branch_id);
$usrStmt->execute();
$totalUsers = $usrStmt->get_result()->fetch_assoc()['total'];
$usrStmt->close();

// 4. Stock Value (Sum of quantity * cost_price)
$stockStmt = $conn->prepare("SELECT COALESCE(SUM(ps.quantity * p.cost_price), 0) as total_value FROM product_stocks ps JOIN products p ON ps.product_id = p.id WHERE ps.branch_id = ?");
$stockStmt->bind_param("i", $branch_id);
$stockStmt->execute();
$stockValue = $stockStmt->get_result()->fetch_assoc()['total_value'];
$stockStmt->close();

// 5. Users List
$usersStmt = $conn->prepare("SELECT name, email, role FROM users WHERE branch_id = ?");
$usersStmt->bind_param("i", $branch_id);
$usersStmt->execute();
$usersList = $usersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$usersStmt->close();

// 6. Recent Sales
$recSalesStmt = $conn->prepare("
    SELECT s.reference_no, s.sale_date, s.total_amount, c.name as customer_name 
    FROM sales s 
    LEFT JOIN contacts c ON s.customer_id = c.id 
    WHERE s.branch_id = ? AND s.status = 'completed'
    ORDER BY s.sale_date DESC, s.id DESC LIMIT 10
");
$recSalesStmt->bind_param("i", $branch_id);
$recSalesStmt->execute();
$recentSales = $recSalesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recSalesStmt->close();

// 7. Chart Data (Last 7 Days Sales)
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    
    $chartStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE branch_id = ? AND DATE(sale_date) = ? AND status = 'completed'");
    $chartStmt->bind_param("is", $branch_id, $date);
    $chartStmt->execute();
    $amount = $chartStmt->get_result()->fetch_assoc()['total'];
    $chartStmt->close();
    
    $chartData['labels'][] = date('d M', strtotime($date));
    $chartData['amounts'][] = $amount;
}
?>

<div class="container-fluid">
    <div class="page-header mb-4 d-flex justify-content-between align-items-center">
        <h1 class="page-title">
            <i class="bi bi-shop"></i> <?= escape($branch['name']) ?> Dashboard
        </h1>
        <?php if (isAdmin()): ?>
        <a href="<?= url('/modules/branches/list.php') ?>" class="btn btn-secondary rounded-pill shadow-sm">
            <i class="bi bi-arrow-left"></i> Back to Branches
        </a>
        <?php endif; ?>
    </div>

    <!-- Metrics Row -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: white;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-white-50 fw-semibold text-uppercase tracking-wider" style="font-size: 0.75rem;">This Month's Sales</div>
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="bi bi-graph-up-arrow fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0"><?= formatCurrency($monthlySales) ?></h3>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, #ef4444 0%, #f97316 100%); color: white;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-white-50 fw-semibold text-uppercase tracking-wider" style="font-size: 0.75rem;">This Month's Expenses</div>
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="bi bi-wallet2 fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0"><?= formatCurrency($monthlyExpenses) ?></h3>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-white-50 fw-semibold text-uppercase tracking-wider" style="font-size: 0.75rem;">Total Stock Value</div>
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="bi bi-box-seam fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0"><?= formatCurrency($stockValue) ?></h3>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-white-50 fw-semibold text-uppercase tracking-wider" style="font-size: 0.75rem;">Active Staff</div>
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="bi bi-people fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-0"><?= number_format($totalUsers) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Tables -->
    <div class="row g-4">
        <!-- Chart -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                    <h5 class="fw-bold mb-0">Sales Last 7 Days</h5>
                </div>
                <div class="card-body p-4">
                    <canvas id="branchSalesChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Staff List -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                    <h5 class="fw-bold mb-0">Branch Staff</h5>
                </div>
                <div class="card-body p-4">
                    <ul class="list-group list-group-flush">
                        <?php foreach($usersList as $usr): ?>
                        <li class="list-group-item px-0 border-light d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= escape($usr['name']) ?></div>
                                <div class="small text-muted"><?= escape($usr['role']) ?> • <?= escape($usr['email']) ?></div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                        <?php if(empty($usersList)): ?>
                        <li class="list-group-item px-0 text-muted">No staff assigned to this branch.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Recent Sales Table -->
        <div class="col-lg-12 mb-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Recent Sales</h5>
                    <a href="<?= url('/modules/sales/list.php') ?>" class="btn btn-sm btn-outline-primary rounded-pill">View All</a>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Reference</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recentSales as $sale): ?>
                                <tr>
                                    <td><code><?= escape($sale['reference_no']) ?></code></td>
                                    <td><?= date('d M Y, h:i A', strtotime($sale['sale_date'])) ?></td>
                                    <td><?= escape($sale['customer_name'] ?? 'Walk-in Customer') ?></td>
                                    <td class="text-end fw-bold"><?= formatCurrency($sale['total_amount']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($recentSales)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No recent sales found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('branchSalesChart').getContext('2d');
    
    // Gradient for the chart fill
    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(79, 70, 229, 0.5)');
    gradient.addColorStop(1, 'rgba(79, 70, 229, 0.0)');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartData['labels']) ?>,
            datasets: [{
                label: 'Sales (৳)',
                data: <?= json_encode($chartData['amounts']) ?>,
                borderColor: '#4f46e5',
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#4f46e5',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleFont: { size: 13, family: "'Inter', sans-serif" },
                    bodyFont: { size: 14, weight: 'bold', family: "'Inter', sans-serif" },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return '৳ ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0, 0, 0, 0.05)', borderDash: [5, 5] },
                    ticks: {
                        font: { family: "'Inter', sans-serif" },
                        callback: function(value) {
                            return '৳ ' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { family: "'Inter', sans-serif" } }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
