<?php
/**
 * Sidebar Navigation
 * Modern sidebar with icons and role-based menu items
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['user_role'] ?? 'cashier';
?>

<aside class="sidebar">
    <div class="sidebar-header d-flex justify-content-between align-items-center w-100">
        <div class="logo d-flex align-items-center gap-2">
            <i class="bi bi-shop-window text-primary"></i>
            <span class="sidebar-text text-dark fw-bold"><?= escape(getSetting('company_name', 'InvenPOS')) ?></span>
        </div>
        <button class="btn btn-link text-secondary p-0 d-none d-lg-block transition" id="desktopSidebarToggle" title="Toggle Sidebar">
            <i class="bi bi-list fs-4"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="<?= url('/modules/dashboard/index.php') ?>" class="nav-link <?= $currentPage === 'index.php' && strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <!-- Products -->
            <li class="nav-item">
                <a href="<?= url('/modules/products/list.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'products') !== false ? 'active' : '' ?>">
                    <i class="bi bi-box-seam"></i>
                    <span>Products</span>
                </a>
            </li>
            
            <!-- Categories -->
            <li class="nav-item">
                <a href="<?= url('/modules/categories/list.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'categories') !== false ? 'active' : '' ?>">
                    <i class="bi bi-tags"></i>
                    <span>Categories</span>
                </a>
            </li>
            
            <!-- Sales Section -->
            <li class="nav-section">Sales</li>
            
            <li class="nav-item">
                <a href="<?= url('/modules/sales/pos.php') ?>" class="nav-link <?= $currentPage === 'pos.php' ? 'active' : '' ?>">
                    <i class="bi bi-cart-check"></i>
                    <span>POS</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= url('/modules/sales/list.php') ?>" class="nav-link <?= $currentPage === 'list.php' && strpos($_SERVER['REQUEST_URI'], 'sales') !== false ? 'active' : '' ?>">
                    <i class="bi bi-receipt"></i>
                    <span>Orders & Invoices</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= url('/modules/sales/returns/index.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'sales/returns') !== false ? 'active' : '' ?>">
                    <i class="bi bi-arrow-return-left"></i>
                    <span>Sales Returns</span>
                </a>
            </li>
            
            <?php if (true): // Allow all (or restricting specific items below) ?>
            <!-- Purchases -->
            <li class="nav-item">
                <a href="<?= url('/modules/purchases/list.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'purchases') !== false ? 'active' : '' ?>">
                    <i class="bi bi-cart-plus"></i>
                    <span>Purchases</span>
                </a>
            </li>
            
            <!-- People Section -->
            <li class="nav-section">People</li>
            
            <li class="nav-item">
                <a href="<?= url('/modules/customers/list.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'customers') !== false ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    <span>Customers</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= url('/modules/suppliers/list.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'suppliers') !== false ? 'active' : '' ?>">
                    <i class="bi bi-truck"></i>
                    <span>Suppliers</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= url('/modules/finance/quick_adjustment.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'quick_adjustment') !== false ? 'active' : '' ?>">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>Quick Transaction</span>
                </a>
            </li>
            
            <?php endif; // End 'true' block (Purchases/People) ?>
            
            <!-- Finance Section (Admin Only) -->
            <?php if (isAdmin()): ?>
            <li class="nav-section">Finance</li>
            
            <li class="nav-item">
                <a href="<?= url('/modules/financial_accounts/list.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'financial_accounts') !== false ? 'active' : '' ?>">
                    <i class="bi bi-cash-coin"></i>
                    <span>Financial Accounts</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= url('/modules/expenses/list.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'expenses') !== false ? 'active' : '' ?>">
                    <i class="bi bi-wallet2"></i>
                    <span>Expenses</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= url('/modules/capital/index.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'capital') !== false ? 'active' : '' ?>">
                    <i class="bi bi-bank"></i>
                    <span>Capital & Loans</span>
                </a>
            </li>

            <!-- Reports (Admin Only) -->
            <li class="nav-section">Reports</li>

            <li class="nav-item">
                <a href="<?= url('/modules/reports/inventory.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'inventory.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-boxes"></i>
                    <span>Inventory Count</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= url('/modules/reports/stock_valuation.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'stock_valuation.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Stock Valuation</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= url('/modules/reports/ledger.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'ledger.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-journal-album"></i>
                    <span>Advanced Ledger</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= url('/modules/reports/sales.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'sales.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart"></i>
                    <span>Sales Report</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= url('/modules/reports/profit_loss.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'profit_loss.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-cash-coin"></i>
                    <span>Profit & Loss</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= url('/modules/reports/customer_ledger.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'customer_ledger.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-person-lines-fill"></i>
                    <span>Customer Ledger</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= url('/modules/reports/supplier_ledger.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'supplier_ledger.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-journal-text"></i>
                    <span>Supplier Ledger</span>
                </a>
            </li>

            <!-- Advanced Accounting (Admin Only) -->
            <li class="nav-section">Advanced Accounting</li>
            
            <li class="nav-item">
                <a href="<?= url('/modules/accounting/chart_of_accounts.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'chart_of_accounts') !== false ? 'active' : '' ?>">
                    <i class="bi bi-diagram-3"></i>
                    <span>Chart of Accounts</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('/modules/accounting/manual_journal.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'manual_journal') !== false ? 'active' : '' ?>">
                    <i class="bi bi-journal-plus"></i>
                    <span>Manual Journal</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('/modules/accounting/trial_balance.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'trial_balance') !== false ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                    <span>Trial Balance</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('/modules/accounting/balance_sheet.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'balance_sheet') !== false ? 'active' : '' ?>">
                    <i class="bi bi-bank2"></i>
                    <span>Balance Sheet</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('/modules/accounting/period_locking.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'period_locking') !== false ? 'active' : '' ?>">
                    <i class="bi bi-lock"></i>
                    <span>Period Locking</span>
                </a>
            </li>

            <!-- System (Admin Only) -->
            <li class="nav-section">System</li>
            <li class="nav-item">
                <a href="<?= url('/modules/branches/list.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/branches/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-shop"></i>
                    <span>Branches</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('/modules/users/index.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/users/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= url('/modules/email/index.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/email/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-envelope-paper"></i>
                    <span>Email Station</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="<?= url('/modules/reports/activity_log.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'activity_log.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-activity"></i>
                    <span>Activity Logs</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= url('/modules/settings/company.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'company.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-gear-fill"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('/modules/helpers/user_guide.php') ?>" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'user_guide.php') !== false ? 'active' : '' ?>">
                    <i class="bi bi-book"></i>
                    <span>User Guide</span>
                </a>
            </li>

            <?php endif; ?>
            
            <!-- PWA Install Button (Hidden by default, shown via JS) -->
             <li class="nav-item" id="sidebarInstallItem" style="display: none;">
                <a href="#" class="nav-link" id="sidebarInstallBtn">
                    <i class="bi bi-download"></i>
                    <span>Install App</span>
                </a>
            </li>

    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php if (!empty($_SESSION['user_image']) && $_SESSION['user_image'] !== '0'): ?>
                    <img src="<?= url($_SESSION['user_image']) ?>" alt="User" class="rounded-circle" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <i class="bi bi-person-circle"></i>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?= escape($_SESSION['user_name'] ?? 'User') ?></div>
                <div class="user-role"><?= ucfirst($userRole) ?></div>
            </div>
        </div>
        <div class="footer-actions">

            <a href="<?= url('/modules/auth/logout.php') ?>" class="logout-btn" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
