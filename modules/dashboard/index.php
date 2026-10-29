<?php
/**
 * Dashboard - Premium Analytics Interface
 * Stunning UI with Chart.js visualizations
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Now BASE_PATH is defined, safe to use url()
$pageTitle = 'Dashboard';
$customCSS = url('/assets/css/dashboard.css?v=' . time());
$customJS = url('/assets/js/dashboard.js');

require_once __DIR__ . '/../../includes/header.php';

// Role specific filters
$isAdmin = isAdmin();
$branch_id = (int)($_SESSION['branch_id'] ?? 1);
$branch_sql = $isAdmin ? "" : " AND branch_id = $branch_id ";
$branch_sql_s = $isAdmin ? "" : " AND s.branch_id = $branch_id ";
$branch_sql_u = $isAdmin ? "" : " AND u.branch_id = $branch_id ";
$branch_sql_ps = $isAdmin ? "" : " AND ps.branch_id = $branch_id ";

// Fetch dashboard metrics
$todayRevenue = 0;
$totalProducts = 0;
$lowStockCount = 0;
$pendingOrders = 0;

// Today's Revenue
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE sale_date = CURDATE() AND status = 'completed' $branch_sql");
$stmt->execute();
$result = $stmt->get_result();
$todayRow = $result ? $result->fetch_assoc() : null;
$todayRevenue = (float)($todayRow['revenue'] ?? 0);
$stmt->close();

// Yesterday's Revenue
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE sale_date = SUBDATE(CURDATE(), 1) AND status = 'completed' $branch_sql");
$stmt->execute();
$yesterdayRes = $stmt->get_result();
$yesterdayRow = $yesterdayRes ? $yesterdayRes->fetch_assoc() : null;
$yesterdayRevenue = (float)($yesterdayRow['revenue'] ?? 0);
$stmt->close();

// Calculate Percentage Change
$revenueChangePercent = 0;
$revenueDirection = 'neutral'; // positive, negative, neutral

if ($yesterdayRevenue > 0) {
    $change = $todayRevenue - $yesterdayRevenue;
    $revenueChangePercent = ($change / $yesterdayRevenue) * 100;
} elseif ($todayRevenue > 0) {
    $revenueChangePercent = 100; // 0 to something is 100% increase effectively (or infinite)
}

if ($revenueChangePercent > 0) {
    $revenueDirection = 'positive';
} elseif ($revenueChangePercent < 0) {
    $revenueDirection = 'negative';
}


// Total Products (Global for all)
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE is_active = 1");
$stmt->execute();
$result = $stmt->get_result();
$totalProducts = (int)(($result ? $result->fetch_assoc()['total'] : 0) ?? 0);
$stmt->close();

// Low Stock Products (Branch Specific if Staff)
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM product_stocks ps JOIN products p ON ps.product_id = p.id WHERE ps.quantity <= p.alert_quantity AND p.is_active = 1 $branch_sql_ps");
$stmt->execute();
$result = $stmt->get_result();
$lowStockCount = (int)(($result ? $result->fetch_assoc()['total'] : 0) ?? 0);
$stmt->close();

// Pending Orders
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM sales WHERE status = 'pending' $branch_sql");
$stmt->execute();
$result = $stmt->get_result();
$pendingOrders = (int)(($result ? $result->fetch_assoc()['total'] : 0) ?? 0);
$stmt->close();

// Financial Metrics (Assets) - Only for Admin
$totalAssets = 0;
$accounts = [];
if ($isAdmin) {
    $accStmt = $conn->query("SELECT account_name, account_type, balance FROM financial_accounts WHERE is_active = 1");
    while ($row = $accStmt->fetch_assoc()) {
        $accounts[] = $row;
        $totalAssets += $row['balance'];
    }
}

// Get low stock products for table
$lowStockProducts = [];
$stmt = $conn->prepare("
    SELECT p.name, p.sku, ps.quantity as stock_quantity, p.alert_quantity, c.name as category_name, b.name as branch_name
    FROM product_stocks ps
    JOIN products p ON ps.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN branches b ON ps.branch_id = b.id
    WHERE ps.quantity <= p.alert_quantity AND p.is_active = 1 $branch_sql_ps
    ORDER BY ps.quantity ASC
    LIMIT 5
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $lowStockProducts[] = $row;
}
$stmt->close();

// Fetch Recent Activities
$recentActivities = [];
$stmt = $conn->prepare("
    SELECT a.action, a.description, a.created_at, u.name as user_name 
    FROM activity_logs a 
    JOIN users u ON a.user_id = u.id 
    WHERE 1=1 $branch_sql_u
    ORDER BY a.created_at DESC 
    LIMIT 5
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recentActivities[] = $row;
}
$stmt->close();
?>

<div class="dashboard-container">
    <!-- Header -->
    <div class="dashboard-header-bar mb-5">
        <div class="d-flex align-items-center gap-3">
            <div>
                <h1 class="dashboard-title fs-3 mb-0" style="font-family: var(--font-display); font-weight: 800;">Dashboard</h1>
                <p class="dashboard-subtitle text-secondary fw-normal fs-6 mt-1 mb-0" style="letter-spacing: 0.3px;">Welcome back, <?= escape($_SESSION['user_name']) ?>!</p>
            </div>
        </div>
        <?php
        $weatherSetting = getSetting('weather_city', 'Dhaka|23.8103|90.4125');
        $weatherParts = explode('|', $weatherSetting);
        $weatherCity = $weatherParts[0] ?? 'Dhaka';
        $weatherLat = $weatherParts[1] ?? '23.8103';
        $weatherLon = $weatherParts[2] ?? '90.4125';
        ?>
        <div class="dashboard-info-widget text-end d-flex align-items-center justify-content-end gap-3" 
             data-lat="<?= escape($weatherLat) ?>" 
             data-lon="<?= escape($weatherLon) ?>"
             data-city="<?= escape($weatherCity) ?>">
             
            <!-- Live Time & Date -->
            <div class="d-flex align-items-center gap-2 gap-md-3">
                <h3 id="liveClock" class="mb-0 fw-bold text-dark" style="font-feature-settings: 'tnum'; font-variant-numeric: tabular-nums;">
                    <div class="spinner-grow spinner-grow-sm text-primary" role="status"></div>
                </h3>
                <div class="border-start ps-2 ps-md-3 d-none d-sm-flex flex-column align-items-start text-start">
                    <span id="liveDate" class="text-muted fw-medium"><i class="bi bi-calendar3"></i> <?= date('l, d M') ?></span>
                </div>
                <span class="text-muted opacity-25 ms-1 ms-md-2">|</span>
            </div>

            <!-- Weather Widget (Visible on Mobile & Desktop) -->
            <div class="d-flex align-items-center gap-1">
                <button class="btn btn-sm btn-link p-0 text-muted transition" id="weatherPrev" style="display:none;"><i class="bi bi-chevron-left fs-6"></i></button>
                <span id="weatherWidget" class="fw-medium text-primary text-center" style="min-width: 100px;">
                    <span class="spinner-border spinner-border-sm" role="status"></span>
                </span>
                <button class="btn btn-sm btn-link p-0 text-muted transition" id="weatherNext" style="display:none;"><i class="bi bi-chevron-right fs-6"></i></button>
            </div>
            
        </div>
    </div>

    
    <?php if ($isAdmin): ?>
    <!-- Premium Financial Command Center -->
    <div class="card border-0 mb-5 overflow-hidden financial-command-center" style="border-radius: 24px; box-shadow: 0 10px 40px -10px rgba(0,0,0,0.08);">
        <div class="row g-0">
            <!-- Left Side: Total Assets -->
            <div class="col-lg-4 p-4 p-xl-5 text-white d-flex flex-column justify-content-center position-relative command-left-panel" style="background: linear-gradient(135deg, var(--primary), #3b82f6);">
                <!-- Subtle background pattern -->
                <i class="bi bi-wallet2 position-absolute bg-icon" style="font-size: 180px; right: -20px; bottom: -40px; opacity: 0.1; transform: rotate(-15deg);"></i>
                <div class="position-relative" style="z-index: 1;">
                    <h6 class="mb-2 fw-medium opacity-75 text-uppercase command-subtitle" style="letter-spacing: 1px;">Total Liquid Assets</h6>
                    <h1 class="fw-bold mb-0 command-amount text-nowrap" style="font-size: clamp(1.75rem, 2.2vw, 2.5rem); letter-spacing: -0.5px; line-height: 1.2;">
                        <span class="sensitive-amount text-nowrap"><?= formatCurrency($totalAssets) ?></span>
                    </h1>
                    <p class="opacity-75 mt-3 mb-0 command-footer"><i class="bi bi-shield-check me-2"></i>Securely Managed</p>
                </div>
            </div>
            <!-- Right Side: Accounts List -->
            <div class="col-lg-8 p-4 p-lg-5 command-right-panel" style="background-color: #f4f7fa;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0 text-dark">Accounts Overview</h5>
                    <button class="btn btn-light rounded-pill px-3 shadow-sm border-0 d-flex align-items-center gap-2 transition" id="togglePrivacy" title="Toggle Privacy">
                        <i class="bi bi-eye"></i> <span class="small fw-medium privacy-text">Privacy</span>
                    </button>
                </div>
                <div class="row g-3">
                    <?php foreach ($accounts as $acc): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <a href="<?= url('/modules/financial_accounts/list.php') ?>" class="text-decoration-none d-block h-100">
                            <div class="p-3 rounded-4 transition account-card d-flex flex-column h-100 gap-3">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="account-icon-box flex-shrink-0 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                        <?php
                                        $icon = 'bi-bank2'; // default
                                        if (strtolower($acc['account_type'] ?? '') === 'cash') $icon = 'bi-cash-stack';
                                        elseif (strtolower($acc['account_type'] ?? '') === 'mobile_banking') $icon = 'bi-phone';
                                        ?>
                                        <i class="bi <?= $icon ?> fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 text-dark fw-bold" style="font-size: 0.95rem; line-height: 1.4; word-break: break-word;"><?= escape($acc['account_name']) ?></h6>
                                        <span class="badge bg-light text-muted border fw-normal"><?= escape(ucwords(str_replace('_', ' ', $acc['account_type'] ?? ''))) ?></span>
                                    </div>
                                </div>
                                <div class="text-end mt-auto pt-2 border-top">
                                    <div class="text-muted small mb-1">Current Balance</div>
                                    <h4 class="mb-0 fw-bold text-dark fs-5" style="font-feature-settings: 'tnum';"><span class="sensitive-amount"><?= formatCurrency($acc['balance']) ?></span></h4>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Metric Cards -->
    <div class="metrics-grid mt-5">
        <div class="metric-card">
            <div class="metric-icon primary-soft">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div class="metric-content">
                <div class="metric-label d-flex justify-content-between align-items-center">
                    <span>Revenue</span>
                    <select id="revenueRange" class="form-select form-select-sm border-0 bg-transparent shadow-none p-0 pe-3 text-muted" style="width: auto; font-size: 13px;">
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                    </select>
                </div>
                <div class="metric-value"><span class="sensitive-amount" id="dashboardRevenue"><?= formatCurrency($todayRevenue) ?></span></div>
                <div class="metric-change <?= $revenueDirection ?>" id="dashboardRevenueChange">
                    <?php if ($revenueDirection === 'positive'): ?>
                        <i class="bi bi-arrow-up"></i>
                    <?php elseif ($revenueDirection === 'negative'): ?>
                        <i class="bi bi-arrow-down"></i>
                    <?php else: ?>
                        <i class="bi bi-dash"></i>
                    <?php endif; ?>
                    <?= number_format(abs($revenueChangePercent), 1) ?>% from previous
                </div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon primary-soft">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="metric-content">
                <div class="metric-label">Total Products</div>
                <div class="metric-value"><?= number_format($totalProducts) ?></div>
                <div class="metric-change neutral">
                    <i class="bi bi-dash"></i> Active items
                </div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon primary-soft">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="metric-content">
                <div class="metric-label">Low Stock Items</div>
                <div class="metric-value"><?= number_format($lowStockCount) ?></div>
                <div class="metric-change <?= $lowStockCount > 0 ? 'negative' : 'positive' ?>">
                    <?php if ($lowStockCount > 0): ?>
                        <i class="bi bi-arrow-down"></i> Needs attention
                    <?php else: ?>
                        <i class="bi bi-check"></i> All good
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon primary-soft">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="metric-content">
                <div class="metric-label">Pending Orders</div>
                <div class="metric-value"><?= number_format($pendingOrders) ?></div>
                <div class="metric-change neutral">
                    <i class="bi bi-hourglass"></i> Awaiting processing
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="charts-grid">
        <!-- Sales Trend Chart -->
        <div class="chart-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-graph-up"></i>
                    Sales Trend (Last 30 Days)
                </h3>
                <div class="card-actions">
                    <select class="form-select form-select-sm" id="salesPeriod">
                        <option value="30">Last 30 Days</option>
                        <option value="7">Last 7 Days</option>
                        <option value="90">Last 90 Days</option>
                    </select>
                </div>
            </div>
            <div class="card-body">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>
        
        <!-- Order Status Distribution -->
        <div class="chart-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-pie-chart"></i>
                    Order Status Distribution
                </h3>
            </div>
            <div class="card-body">
                <canvas id="orderStatusChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Second Row Charts -->
    <div class="charts-grid">
        <!-- Top 5 Products Chart -->
        <div class="chart-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-star"></i>
                    Top 5 Selling Products
                </h3>
            </div>
            <div class="card-body">
                <canvas id="topProductsChart"></canvas>
            </div>
        </div>
        
        <!-- Low Stock Alert Table -->
        <div class="chart-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-exclamation-octagon"></i>
                    Low Stock Alerts
                </h3>
                <a href="<?= url('/modules/products/list.php?filter=low_stock') ?>" class="btn btn-sm btn-outline-primary">
                    View All
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($lowStockProducts)): ?>
                    <div class="empty-state">
                        <i class="bi bi-check-circle text-success"></i>
                        <p>All products are well stocked!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Stock</th>
                                    <th>Alert Level</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $product): ?>
                                <tr>
                                    <td>
                                        <div class="product-cell">
                                            <strong><?= escape($product['name']) ?></strong>
                                            <?php if ($product['category_name']): ?>
                                                <small class="text-muted"><?= escape($product['category_name']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($isAdmin && $product['branch_name']): ?>
                                                <small class="text-info d-block"><i class="bi bi-shop"></i> <?= escape($product['branch_name']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><code><?= escape($product['sku']) ?></code></td>
                                    <td>
                                        <span class="badge bg-danger">
                                            <?= $product['stock_quantity'] ?>
                                        </span>
                                    </td>
                                    <td><?= $product['alert_quantity'] ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="location.href=BASE_PATH + '/modules/purchases/create.php'">
                                            <i class="bi bi-cart-plus"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Third Row: Activity Timeline -->
    <div class="row mt-5 mb-5">
        <div class="col-12">
            <div class="chart-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-activity"></i>
                        Recent Activity
                    </h3>
                </div>
                <div class="card-body p-4">
                    <?php if(empty($recentActivities)): ?>
                        <div class="empty-state py-4">
                            <i class="bi bi-clock-history fs-1 text-muted opacity-50"></i>
                            <p class="mt-2 mb-0">No recent activity found.</p>
                        </div>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach($recentActivities as $activity): 
                                $timeAgo = time() - strtotime($activity['created_at']);
                                if ($timeAgo < 60) $timeStr = 'Just now';
                                elseif ($timeAgo < 3600) $timeStr = floor($timeAgo / 60) . ' mins ago';
                                elseif ($timeAgo < 86400) $timeStr = floor($timeAgo / 3600) . ' hours ago';
                                else $timeStr = floor($timeAgo / 86400) . ' days ago';
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="mb-0 fw-bold text-dark"><?= escape($activity['user_name']) ?></h6>
                                        <span class="badge bg-light text-muted border"><?= $timeStr ?></span>
                                    </div>
                                    <p class="mb-0 text-muted" style="font-size: 14px;"><?= escape($activity['description'] ?: $activity['action']) ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
