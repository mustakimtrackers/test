<?php
/**
 * Investor Management List
 */

$pageTitle = 'Manage Investors';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Fetch Investors with Balance Calculation
// We calculate balance from transactions relative to the business
// Net Balance = Total Injections (Liability) - Total Withdrawals (Repayment)
$query = "
    SELECT 
        c.*,
        COALESCE(SUM(CASE WHEN ct.type = 'injection' THEN ct.amount ELSE 0 END), 0) as total_invested,
        COALESCE(SUM(CASE WHEN ct.type = 'withdrawal' THEN ct.amount ELSE 0 END), 0) as total_withdrawn
    FROM contacts c
    LEFT JOIN capital_transactions ct ON c.id = ct.investor_id
    WHERE c.type = 'investor'
    GROUP BY c.id
    ORDER BY c.name ASC
";
$result = $conn->query($query);
?>

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1 class="page-title">
            <i class="bi bi-people"></i> Manage Investors
        </h1>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <a href="add_investor.php" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Add Investor
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Contact Info</th>
                            <th class="text-end">Total Invested</th>
                            <th class="text-end">Total Withdrawn</th>
                            <th class="text-end">Current Balance</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): 
                                $netBalance = $row['total_invested'] - $row['total_withdrawn'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?= escape($row['name']) ?></strong>
                                    <br>
                                    <small class="text-muted">ID: #<?= $row['id'] ?></small>
                                </td>
                                <td>
                                    <?php if ($row['phone']): ?>
                                        <div><i class="bi bi-telephone text-muted"></i> <?= escape($row['phone']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($row['email']): ?>
                                        <div><i class="bi bi-envelope text-muted"></i> <?= escape($row['email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-success">
                                    <?= formatCurrency($row['total_invested']) ?>
                                </td>
                                <td class="text-end text-danger">
                                    <?= formatCurrency($row['total_withdrawn']) ?>
                                </td>
                                <td class="text-end fw-bold">
                                    <?= formatCurrency($netBalance) ?>
                                    <?php if ($netBalance > 0): ?>
                                        <div class="small text-muted">(Payable)</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="edit_investor.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit Profile">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <?php if ($netBalance == 0): ?>
                                        <a href="delete_investor.php?id=<?= $row['id'] ?>&csrf_token=<?= generateCSRFToken() ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('Are you sure you want to delete this investor? This cannot be undone.')"
                                           title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No investors found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
