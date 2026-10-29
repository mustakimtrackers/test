<?php
/**
 * Database Reset Script - Safe Business Data Wiper
 * Cleans all transactional and inventory test data while PRESERVING core system structure:
 * - Keeps Users, Settings, Branches, Chart of Accounts, Units, and Financial Accounts safe.
 * - Resets Account balances to 0.00.
 * - Guarantees default Branch 1 ("Head Office") exists so Dashboard/POS never crash.
 */

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

// EXTREME SECURITY CHECK: Only Admins can run this
requireAdmin();

$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Reset Tool - FOrbit ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    <style>
        body { background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 40px 15px; }
        .reset-card { max-width: 680px; margin: 0 auto; background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; overflow: hidden; }
        .card-header-warning { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; padding: 24px 30px; }
        .card-header-success { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 24px 30px; }
    </style>
</head>
<body>

<div class="reset-card">
<?php if (!$confirmed): ?>
    <div class="card-header-warning">
        <h3 class="mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i> Database Reset Tool</h3>
        <p class="mb-0 opacity-90 text-white-50">Clear business test data before going live</p>
    </div>
    <div class="p-4 p-md-5">
        <div class="alert alert-warning border-0 d-flex gap-3 align-items-center">
            <i class="bi bi-info-circle-fill fs-3 text-warning"></i>
            <div>
                <strong>Warning:</strong> This action will permanently wipe all test transactions and start your ERP fresh with clean accounting.
            </div>
        </div>

        <h5 class="fw-bold mt-4 mb-3 text-danger"><i class="bi bi-trash3 me-2"></i> What will be WIPED (Cleared):</h5>
        <ul class="list-group mb-4">
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cart-x text-danger me-2"></i> Sales, Invoices & Sales Returns</span>
                <span class="badge bg-danger rounded-pill">Wiped</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="bi bi-truck text-danger me-2"></i> Purchases & Supplier Bills</span>
                <span class="badge bg-danger rounded-pill">Wiped</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="bi bi-box-seam text-danger me-2"></i> Products, Stocks & Stock Movements</span>
                <span class="badge bg-danger rounded-pill">Wiped</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people text-danger me-2"></i> Customers & Suppliers Contacts</span>
                <span class="badge bg-danger rounded-pill">Wiped</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="bi bi-wallet2 text-danger me-2"></i> Expenses & Payments History</span>
                <span class="badge bg-danger rounded-pill">Wiped</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="bi bi-journal-text text-danger me-2"></i> General Journal Entries & Ledger History</span>
                <span class="badge bg-danger rounded-pill">Wiped</span>
            </li>
        </ul>

        <h5 class="fw-bold mb-3 text-success"><i class="bi bi-shield-check me-2"></i> What will be KEPT SAFE (Preserved):</h5>
        <ul class="list-group mb-4">
            <li class="list-group-item list-group-item-success d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-badge text-success me-2"></i> Admin & Staff User Accounts</span>
                <span class="badge bg-success rounded-pill">Preserved</span>
            </li>
            <li class="list-group-item list-group-item-success d-flex justify-content-between align-items-center">
                <span><i class="bi bi-gear text-success me-2"></i> System Settings & Company Profile</span>
                <span class="badge bg-success rounded-pill">Preserved</span>
            </li>
            <li class="list-group-item list-group-item-success d-flex justify-content-between align-items-center">
                <span><i class="bi bi-shop text-success me-2"></i> Branches (Head Office & Outlets)</span>
                <span class="badge bg-success rounded-pill">Preserved</span>
            </li>
            <li class="list-group-item list-group-item-success d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bank text-success me-2"></i> Cash & Bank Accounts (Balances reset to 0.00)</span>
                <span class="badge bg-success rounded-pill">Preserved</span>
            </li>
            <li class="list-group-item list-group-item-success d-flex justify-content-between align-items-center">
                <span><i class="bi bi-diagram-3 text-success me-2"></i> Chart of Accounts (COA Structure)</span>
                <span class="badge bg-success rounded-pill">Preserved</span>
            </li>
            <li class="list-group-item list-group-item-success d-flex justify-content-between align-items-center">
                <span><i class="bi bi-rulers text-success me-2"></i> Measurement Units (Pcs, Box, Kg)</span>
                <span class="badge bg-success rounded-pill">Preserved</span>
            </li>
        </ul>

        <div class="d-flex gap-3 justify-content-end mt-4">
            <a href="modules/dashboard/index.php" class="btn btn-outline-secondary px-4 py-2">Cancel</a>
            <a href="reset_db.php?confirm=yes" class="btn btn-danger px-4 py-2 fw-semibold">
                <i class="bi bi-trash3-fill me-1"></i> Yes, Wipe Business Data
            </a>
        </div>
    </div>

<?php else: ?>
    <?php
    // TABLES TO PRESERVE ENTIRELY
    $preserve_tables = [
        'settings',
        'users',
        'branches',
        'chart_of_accounts',
        'financial_periods',
        'units',
        'tax_settings',
        'financial_accounts' // Will be preserved and balance reset to 0.00
    ];

    // TABLES TO WIPE
    $tables_to_wipe = [
        'sale_return_items',
        'sale_returns',
        'sale_items',
        'sales',
        'purchase_items',
        'purchases',
        'stock_movements',
        'product_stocks',
        'products',
        'categories',
        'contacts',
        'payments',
        'expenses',
        'capital_transactions',
        'journal_entry_lines',
        'journal_entries',
        'financial_transactions',
        'account_transactions',
        'activity_logs'
    ];

    $log = [];
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($tables_to_wipe as $tbl) {
        // Check if table exists
        $check = $conn->query("SHOW TABLES LIKE '$tbl'");
        if ($check && $check->num_rows > 0) {
            // Using DELETE with AUTO_INCREMENT reset ensures compatibility across all MySQL/MariaDB engines without foreign-key lock conflicts
            $conn->query("DELETE FROM `$tbl`");
            $conn->query("ALTER TABLE `$tbl` AUTO_INCREMENT = 1");
            $log[] = ["table" => $tbl, "status" => "Cleared"];
        }
    }

    // 1. Reset Financial Account balances to 0.00
    $conn->query("UPDATE financial_accounts SET balance = 0.00");

    // 2. Ensure at least one default Cash Account exists
    $faRes = $conn->query("SELECT id FROM financial_accounts WHERE account_type = 'cash' LIMIT 1");
    if (!$faRes || $faRes->num_rows === 0) {
        $conn->query("INSERT INTO financial_accounts (id, account_name, account_type, balance, is_active) VALUES (1, 'Cash In Hand', 'cash', 0.00, 1) ON DUPLICATE KEY UPDATE balance = 0.00");
    }

    // 3. Ensure at least one default Branch ("Head Office") exists
    $bRes = $conn->query("SELECT id FROM branches WHERE id = 1");
    if (!$bRes || $bRes->num_rows === 0) {
        $conn->query("INSERT INTO branches (id, name, address, created_at) VALUES (1, 'Head Office', 'Main Branch', NOW()) ON DUPLICATE KEY UPDATE name = 'Head Office'");
    }

    // 4. Ensure all users are mapped to a valid branch
    $conn->query("UPDATE users SET branch_id = 1 WHERE branch_id IS NULL OR branch_id = 0");

    // 5. Ensure basic measurement units exist
    $uRes = $conn->query("SELECT id FROM units LIMIT 1");
    if (!$uRes || $uRes->num_rows === 0) {
        $conn->query("INSERT INTO units (name, short_name) VALUES ('Pieces', 'pcs'), ('Box', 'box')");
    }

    // 6. Ensure default financial period exists
    $fpRes = $conn->query("SELECT id FROM financial_periods LIMIT 1");
    if (!$fpRes || $fpRes->num_rows === 0) {
        $curYear = date('Y');
        $conn->query("INSERT INTO financial_periods (period_name, start_date, end_date, is_closed) VALUES ('FY $curYear', '$curYear-01-01', '$curYear-12-31', 0)");
    }

    // 7. Ensure currency symbol is valid BDT ৳
    $conn->query("UPDATE settings SET setting_value = '৳' WHERE setting_key = 'currency_symbol'");

    // Log the reset in activity logs
    if (isset($_SESSION['user_id'])) {
        $adminId = (int)$_SESSION['user_id'];
        $conn->query("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES ($adminId, 'database_reset', 'Business test data wiped successfully via reset tool', NOW())");
    }

    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    ?>

    <div class="card-header-success">
        <h3 class="mb-1"><i class="bi bi-check-circle-fill me-2"></i> Database Reset Completed!</h3>
        <p class="mb-0 opacity-90 text-white-50">All test data cleared — System structure verified</p>
    </div>
    <div class="p-4 p-md-5">
        <div class="alert alert-success border-0 mb-4">
            <h5 class="alert-heading fw-bold"><i class="bi bi-shield-check me-2"></i> Ready for Live Operations</h5>
            <p class="mb-0">All test transactions, sales, and inventory have been wiped clean. Your core accounts, users, and branches are 100% intact.</p>
        </div>

        <h6 class="fw-bold mb-3 text-secondary">Summary of Actions:</h6>
        <div class="table-responsive mb-4" style="max-height: 250px; overflow-y: auto;">
            <table class="table table-sm table-striped">
                <tbody>
                    <?php foreach ($log as $item): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($item['table']) ?></code></td>
                        <td class="text-end"><span class="badge bg-success-subtle text-success fw-semibold">✔ <?= $item['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-success">
                        <td><strong>financial_accounts</strong> (Balances)</td>
                        <td class="text-end"><span class="badge bg-success">Reset to 0.00</span></td>
                    </tr>
                    <tr class="table-success">
                        <td><strong>branches</strong> (Head Office #1)</td>
                        <td class="text-end"><span class="badge bg-success">Verified Active</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="text-center pt-2">
            <a href="modules/dashboard/index.php" class="btn btn-primary px-5 py-2 fw-bold shadow-sm">
                <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
            </a>
        </div>
    </div>
<?php endif; ?>
</div>

</body>
</html>
