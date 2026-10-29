<?php
/**
 * Financial Accounts - View
 * View account details and transaction history
 */

$pageTitle = 'Account Details';
require_once __DIR__ . '/../../includes/auth_check.php';

$accountId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($accountId <= 0) {
    setFlashMessage('error', 'Invalid account ID');
    redirect(url('/modules/financial_accounts/list.php'));
}

// Get account details
$stmt = $conn->prepare("SELECT * FROM financial_accounts WHERE id = ?");
$stmt->bind_param("i", $accountId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$account) {
    setFlashMessage('error', 'Account not found');
    redirect(url('/modules/financial_accounts/list.php'));
}

// Get transaction history
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM financial_transactions WHERE financial_account_id = ?");
$countStmt->bind_param("i", $accountId);
$countStmt->execute();
$totalTransactions = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = ceil($totalTransactions / $limit);

// Apply filters
$filterConditions = ["ft.financial_account_id = ?"];
$filterParams = [$accountId];
$filterTypes = "i";

if (!empty($_GET['date_from'])) {
    $filterConditions[] = "ft.transaction_date >= ?";
    $filterParams[] = $_GET['date_from'];
    $filterTypes .= "s";
}

if (!empty($_GET['date_to'])) {
    $filterConditions[] = "ft.transaction_date <= ?";
    $filterParams[] = $_GET['date_to'];
    $filterTypes .= "s";
}

if (!empty($_GET['type'])) {
    $filterConditions[] = "ft.transaction_type = ?";
    $filterParams[] = $_GET['type'];
    $filterTypes .= "s";
}

if (!empty($_GET['min_amount'])) {
    $filterConditions[] = "ft.amount >= ?";
    $filterParams[] = (float)$_GET['min_amount'];
    $filterTypes .= "d";
}

$whereClause = implode(" AND ", $filterConditions);

$transStmt = $conn->prepare("
    SELECT 
        ft.*,
        CASE 
            WHEN ft.reference_type = 'sale' THEN CONCAT('Sale #', s.reference_no)
            WHEN ft.reference_type = 'purchase' THEN CONCAT('Purchase #', p.reference_no)
            WHEN ft.reference_type = 'expense' THEN CONCAT('Expense - ', e.category)
            WHEN ft.reference_type = 'opening_balance' THEN 'Opening Balance'
            ELSE ft.reference_type
        END as reference_display
    FROM financial_transactions ft
    LEFT JOIN sales s ON ft.reference_type = 'sale' AND ft.reference_id = s.id
    LEFT JOIN purchases p ON ft.reference_type = 'purchase' AND ft.reference_id = p.id
    LEFT JOIN expenses e ON ft.reference_type = 'expense' AND ft.reference_id = e.id
    WHERE $whereClause
    ORDER BY ft.transaction_date DESC, ft.id DESC
    LIMIT ? OFFSET ?
");

// Add limit and offset to params
$filterParams[] = $limit;
$filterParams[] = $offset;
$filterTypes .= "ii";

$transStmt->bind_param($filterTypes, ...$filterParams);
$transStmt->execute();
$transactions = $transStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$transStmt->close();

// Calculate statistics from ALL transactions (not just current page)
$statsStmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as total_credit,
        SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as total_debit
    FROM financial_transactions 
    WHERE financial_account_id = ?
");
$statsStmt->bind_param("i", $accountId);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();
$statsStmt->close();

$creditTotal = $stats['total_credit'] ?? 0;
$debitTotal = $stats['total_debit'] ?? 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title">
                    <i class="bi bi-eye"></i> Account Details
                </h1>
                <div>
                    <a href="<?= url('/modules/financial_accounts/edit.php?id=' . $accountId) ?>" class="btn btn-warning me-2">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <a href="<?= url('/modules/financial_accounts/list.php') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Info Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Account Name</h6>
                    <h4><?= escape($account['account_name']) ?></h4>
                    <span class="badge bg-<?= $account['is_active'] ? 'success' : 'secondary' ?>">
                        <?= $account['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Account Type</h6>
                    <h4><?= ucfirst(str_replace('_', ' ', $account['account_type'])) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h6 class="mb-2">Current Balance</h6>
                    <h3><?= formatCurrency($account['balance']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Transactions</h6>
                    <h4><?= number_format($totalTransactions) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Statistics -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-body">
                    <h6 class="text-success">💰 Total Money In (Credit)</h6>
                    <h3 class="text-success"><?= formatCurrency($creditTotal) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-danger">
                <div class="card-body">
                    <h6 class="text-danger">💸 Total Money Out (Debit)</h6>
                    <h3 class="text-danger"><?= formatCurrency($debitTotal) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Export Section -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <input type="hidden" name="id" value="<?= $accountId ?>">
                        
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="date_from" 
                                   value="<?= $_GET['date_from'] ?? '' ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="date_to" 
                                   value="<?= $_GET['date_to'] ?? '' ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <option value="">All</option>
                                <option value="credit" <?= ($_GET['type'] ?? '') === 'credit' ? 'selected' : '' ?>>Credit (IN)</option>
                                <option value="debit" <?= ($_GET['type'] ?? '') === 'debit' ? 'selected' : '' ?>>Debit (OUT)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Min Amount</label>
                            <input type="number" class="form-control" name="min_amount" 
                                   step="0.01" value="<?= $_GET['min_amount'] ?? '' ?>" placeholder="৳0">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-funnel"></i> Filter
                                </button>
                                <a href="?id=<?= $accountId ?>" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <a href="<?= url('/modules/financial_accounts/api/export_transactions.php?id=' . $accountId . '&' . http_build_query($_GET)) ?>" 
                               class="btn btn-success">
                                <i class="bi bi-file-earmark-excel"></i> Export to Excel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Transaction History</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="12%">Date</th>
                                    <th width="10%">Type</th>
                                    <th width="28%">Reference</th>
                                    <th width="15%">Amount</th>
                                    <th width="30%">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                        No transactions yet
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php 
                                    $serial = $offset + 1;
                                    foreach ($transactions as $trans): 
                                    ?>
                                    <tr>
                                        <td><?= $serial++ ?></td>
                                        <td>
                                            <small><?= date('d M Y', strtotime($trans['transaction_date'])) ?></small><br>
                                            <small class="text-muted"><?= date('H:i', strtotime($trans['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($trans['transaction_type'] === 'credit'): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-arrow-down-circle"></i> IN
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">
                                                    <i class="bi bi-arrow-up-circle"></i> OUT
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= escape($trans['reference_display']) ?></strong>
                                        </td>
                                        <td>
                                            <strong class="<?= $trans['transaction_type'] === 'credit' ? 'text-success' : 'text-danger' ?>">
                                                <?= $trans['transaction_type'] === 'credit' ? '+' : '-' ?>
                                                <?= formatCurrency($trans['amount']) ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <small><?= escape($trans['description']) ?></small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <nav>
                        <ul class="pagination pagination-sm mb-0 justify-content-center">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?id=<?= $accountId ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
