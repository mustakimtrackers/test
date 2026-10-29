<?php
/**
 * Financial Accounts List
 */
$pageTitle = 'Financial Accounts';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Fetch Accounts
$result = $conn->query("SELECT * FROM financial_accounts ORDER BY id ASC");
?>

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1 class="page-title">
            <i class="bi bi-wallet2"></i> Financial Accounts
        </h1>
        <a href="create.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add Account
        </a>
    </div>

    <div class="row">
        <?php while ($row = $result->fetch_assoc()): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="card-title mb-1"><?= escape($row['account_name']) ?></h5>
                            <small class="text-muted"><?= ucfirst($row['account_type']) ?></small>
                            <?php if ($row['account_number']): ?>
                                <br><small class="text-muted"><?= escape($row['account_number']) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="fs-1 text-primary opacity-25">
                            <?php if ($row['account_type'] == 'cash'): ?>
                                <i class="bi bi-cash-stack"></i>
                            <?php elseif ($row['account_type'] == 'mobile_banking'): ?>
                                <i class="bi bi-phone"></i>
                            <?php else: ?>
                                <i class="bi bi-bank"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-3"><?= formatCurrency($row['balance']) ?></h3>
                    <div class="d-grid gap-2">
                        <a href="history.php?id=<?= $row['id'] ?>" class="btn btn-outline-primary btn-sm">View History</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
