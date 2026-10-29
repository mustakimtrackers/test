<?php
/**
 * Account History / Ledger View
 */
$pageTitle = 'Account History';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

$accountId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($accountId === 0) {
    echo '<div class="alert alert-danger m-4">Invalid Account</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Get Account Details
$stmt = $conn->prepare("SELECT * FROM financial_accounts WHERE id = ?");
$stmt->bind_param("i", $accountId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$account) {
    echo '<div class="alert alert-danger m-4">Account not found</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Get Transactions
$transStmt = $conn->prepare("
    SELECT t.*, u.username 
    FROM account_transactions t
    LEFT JOIN users u ON t.created_by = u.id
    WHERE t.account_id = ?
    ORDER BY t.transaction_date DESC
");
$transStmt->bind_param("i", $accountId);
$transStmt->execute();
$transactions = $transStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$transStmt->close();
?>

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">
                <i class="bi bi-journal-text"></i> Ledger: <?= escape($account['account_name']) ?>
            </h1>
            <p class="text-muted mb-0">
                Number: <?= escape($account['account_number'] ?? 'N/A') ?> | 
                Type: <?= ucfirst($account['account_type']) ?>
            </p>
        </div>
        <div class="text-end">
            <h2 class="mb-0 <?= $account['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= formatCurrency($account['balance']) ?>
            </h2>
            <small class="text-muted">Current Balance</small>
        </div>
    </div>
    
    <div class="mb-3">
        <a href="index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Accounts</a>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($transactions)): ?>
                <div class="empty-state">
                    <p class="text-muted">No transactions found for this account.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Ref Type</th>
                                <th>Debit (Out)</th>
                                <th>Credit (In)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td><?= date('d M Y h:i A', strtotime($t['transaction_date'])) ?></td>
                                <td>
                                    <?= escape($t['description']) ?>
                                    <br><small class="text-muted">By: <?= escape($t['username']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $t['reference_type'])) ?></span>
                                    <small class="text-muted">#<?= $t['reference_id'] ?></small>
                                </td>
                                <td class="text-danger fw-bold">
                                    <?= ($t['transaction_type'] == 'debit') ? formatCurrency($t['amount']) : '-' ?>
                                </td>
                                <td class="text-success fw-bold">
                                    <?= ($t['transaction_type'] == 'credit') ? formatCurrency($t['amount']) : '-' ?>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
