<?php
/**
 * Financial Accounts - List
 * Manage all financial accounts (Cash, Bank, Mobile Banking)
 */

$pageTitle = 'Financial Accounts';
require_once __DIR__ . '/../../includes/auth_check.php';

// Get all accounts with transaction count
$query = "
    SELECT 
        fa.*,
        COUNT(ft.id) as transaction_count,
        MAX(ft.transaction_date) as last_transaction
    FROM financial_accounts fa
    LEFT JOIN financial_transactions ft ON fa.id = ft.financial_account_id
    GROUP BY fa.id
    ORDER BY fa.is_active DESC, fa.account_type, fa.account_name
";
$result = $conn->query($query);
$accounts = $result->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$totalBalance = 0;
$activeAccounts = 0;
foreach ($accounts as $account) {
    if ($account['is_active']) {
        $totalBalance += $account['balance'];
        $activeAccounts++;
    }
}

// Add custom CSS
$customCSS = url('/assets/css/dashboard.css?v=' . time());
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-container">
    <!-- Header -->
    <div class="dashboard-header-bar mb-4">
        <div class="d-flex align-items-center gap-3">
            <div>
                <h1 class="dashboard-title fs-3 mb-0" style="font-family: var(--font-display); font-weight: 800;">Financial Accounts</h1>
                <p class="dashboard-subtitle text-secondary fw-normal fs-6 mt-1 mb-0" style="letter-spacing: 0.3px;">Manage Cash, Bank & Mobile Banking</p>
            </div>
        </div>
        <div class="dashboard-info-widget text-end">
            <a href="<?= url('/modules/financial_accounts/create.php') ?>" class="btn btn-primary rounded-pill px-4 shadow-sm fw-medium d-flex align-items-center gap-2">
                <i class="bi bi-plus-circle"></i> New Account
            </a>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="metrics-grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
        <div class="metric-card">
            <div class="metric-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                <i class="bi bi-wallet2"></i>
            </div>
            <div class="metric-content">
                <div class="metric-label">Total Liquid Balance</div>
                <div class="metric-value"><?= formatCurrency($totalBalance) ?></div>
                <div class="metric-change positive">
                    <i class="bi bi-shield-check"></i> Securely managed
                </div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                <i class="bi bi-bank"></i>
            </div>
            <div class="metric-content">
                <div class="metric-label">Active Accounts</div>
                <div class="metric-value"><?= $activeAccounts ?></div>
                <div class="metric-change neutral">
                    <i class="bi bi-activity"></i> Currently operating
                </div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                <i class="bi bi-diagram-3"></i>
            </div>
            <div class="metric-content">
                <div class="metric-label">Total Accounts</div>
                <div class="metric-value"><?= count($accounts) ?></div>
                <div class="metric-change neutral">
                    <i class="bi bi-list-check"></i> Across all types
                </div>
            </div>
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="chart-card mb-5">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-table"></i>
                Chart of Accounts
            </h3>
        </div>
        <div class="card-body p-0" style="min-height: auto;">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="5%" class="text-center">#</th>
                            <th width="25%">Account Info</th>
                            <th width="15%">Balance</th>
                            <th width="15%">Activity</th>
                            <th width="15%">Last Txn</th>
                            <th width="10%">Status</th>
                            <th width="15%" class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                            <tbody>
                                <?php if (empty($accounts)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                        No accounts found. Create your first account!
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php 
                                    $serial = 1;
                                    foreach ($accounts as $account): 
                                        // Account type badge colors
                                        $typeBadges = [
                                            'cash' => 'success',
                                            'bank' => 'primary',
                                            'mobile_banking' => 'info'
                                        ];
                                        $badgeColor = $typeBadges[$account['account_type']] ?? 'secondary';
                                        
                                        // Account type icons
                                        $typeIcons = [
                                            'cash' => 'cash-stack',
                                            'bank' => 'bank',
                                            'mobile_banking' => 'phone'
                                        ];
                                        $icon = $typeIcons[$account['account_type']] ?? 'wallet2';
                                    ?>
                                    <tr class="<?= !$account['is_active'] ? 'table-secondary opacity-75' : '' ?>">
                                        <td class="text-center text-muted"><?= $serial++ ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="account-icon-box flex-shrink-0 bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="bi bi-<?= $icon ?> fs-5"></i>
                                                </div>
                                                <div>
                                                    <strong class="d-block text-dark"><?= escape($account['account_name']) ?></strong>
                                                    <span class="badge bg-light text-muted border mt-1" style="font-size: 11px;">
                                                        <?= ucfirst(str_replace('_', ' ', $account['account_type'])) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong class="<?= $account['balance'] >= 0 ? 'text-success' : 'text-danger' ?> fs-6" style="font-feature-settings: 'tnum';">
                                                <?= formatCurrency($account['balance']) ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-arrow-left-right me-1 text-muted"></i>
                                                <?= number_format($account['transaction_count']) ?> txns
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($account['last_transaction']): ?>
                                                <div class="text-muted small">
                                                    <i class="bi bi-calendar3 me-1"></i>
                                                    <?= date('d M, Y', strtotime($account['last_transaction'])) ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">No activity</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($account['is_active']): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1"><i class="bi bi-circle-fill small me-1" style="font-size:8px;"></i> Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1"><i class="bi bi-pause-circle small me-1" style="font-size:8px;"></i> Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-flex justify-content-end align-items-center gap-2">
                                                <div class="form-check form-switch m-0 p-0 me-2 d-flex align-items-center">
                                                    <input class="form-check-input m-0" type="checkbox" role="switch"
                                                           id="toggle_<?= $account['id'] ?>"
                                                           <?= $account['is_active'] ? 'checked' : '' ?>
                                                           onchange="toggleAccountStatus(<?= $account['id'] ?>, this.checked)"
                                                           title="<?= $account['is_active'] ? 'Active' : 'Inactive' ?>"
                                                           style="cursor: pointer;">
                                                </div>
                                                <a href="<?= url('/modules/financial_accounts/view.php?id=' . $account['id']) ?>" 
                                                   class="btn btn-sm btn-light text-primary border" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="<?= url('/modules/financial_accounts/edit.php?id=' . $account['id']) ?>" 
                                                   class="btn btn-sm btn-light text-warning border" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button class="btn btn-sm btn-light text-danger border" 
                                                        onclick="deleteAccount(<?= $account['id'] ?>, '<?= escape($account['account_name']) ?>')"
                                                        title="Delete Permanently">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
</div>

<style>
/* Custom adjustments for Financial Accounts */
.table th {
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
}
.table td {
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
    vertical-align: middle;
}
</style>

<script>
/**
 * Toggle Account Active/Inactive Status
 */
function toggleAccountStatus(accountId, isActive) {
    const status = isActive ? 1 : 0;
    
    fetch(BASE_PATH + '/modules/financial_accounts/api/toggle_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ account_id: accountId, is_active: status })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const statusBadge = document.querySelector(`#toggle_${accountId}`).closest('tr').querySelector('.badge');
            statusBadge.className = isActive ? 'badge bg-success' : 'badge bg-secondary';
            statusBadge.textContent = isActive ? 'Active' : 'Inactive';
            
            // Show notification
            showToast(isActive ? 'Account activated' : 'Account deactivated', 'success');
            
            // Reload after 500ms to update totals
            setTimeout(() => location.reload(), 500);
        } else {
            alert('Error: ' + data.message);
            // Revert toggle
            document.getElementById(`toggle_${accountId}`).checked = !isActive;
        }
    });
}

/**
 * Delete Account (Password Protected)
 */
function deleteAccount(accountId, accountName) {
    const password = prompt(`⚠️ DELETE PERMANENTLY: ${accountName}\n\nThis will delete ALL transactions for this account!\nThis action CANNOT be undone!\n\nEnter your password to confirm:`);
    
    if (!password) return;
    
    fetch(BASE_PATH + '/modules/financial_accounts/api/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ account_id: accountId, password: password })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Account deleted permanently', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            alert('❌ Error: ' + data.message);
        }
    });
}

/**
 * Simple Toast Notification
 */
function showToast(message, type = 'info') {
    const colors = {
        'success': '#28a745',
        'error': '#dc3545',
        'warning': '#ffc107',
        'info': '#17a2b8'
    };
    
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type] || colors.info};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 9999;
        font-weight: 500;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
