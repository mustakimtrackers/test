<?php
$pageTitle = 'Expenses';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;

// Branch Isolation
$whereSQL = "1=1";
$params = [];
$types = "";
if (!isAdmin()) {
    $whereSQL .= " AND e.branch_id = ?";
    $params[] = $_SESSION['branch_id'] ?? 1;
    $types .= "i";
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM expenses e WHERE $whereSQL";
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalExpenses = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$pagination = getPagination($totalExpenses, $page, $perPage);
$offset = $pagination['offset'];

$query = "
    SELECT e.*, b.name as branch_name 
    FROM expenses e 
    LEFT JOIN branches b ON e.branch_id = b.id 
    WHERE $whereSQL 
    ORDER BY e.expense_date DESC 
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($query);
$types .= "ii";
$params[] = $perPage;
$params[] = $offset;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container-fluid">
    <div class="page-header mb-4">
        <h1 class="page-title"><i class="bi bi-wallet2"></i> Expenses</h1>
        <a href="<?= url('/modules/expenses/create.php') ?>" class="btn btn-primary d-none d-md-inline-flex align-items-center gap-2 px-4 rounded-pill shadow-sm">
            <i class="bi bi-plus-circle"></i> Add Expense
        </a>
    </div>
    
    <!-- Mobile Floating Action Button -->
    <a href="<?= url('/modules/expenses/create.php') ?>" id="mobileFabBtn" class="btn btn-primary d-md-none position-fixed rounded-circle shadow-lg d-flex align-items-center justify-content-center border-0" style="bottom: 30px; right: 24px; width: 64px; height: 64px; z-index: 1050;">
        <i class="bi bi-plus-circle" style="font-size: 2rem;"></i>
    </a>

    <div class="card">
        <div class="card-body">
            <?php if (empty($expenses)): ?>
                <div class="empty-state">
                    <i class="bi bi-wallet2"></i>
                    <p>No expenses recorded yet</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td>
                                    <?= date('d M Y', strtotime($expense['expense_date'])) ?>
                                    <?php if (isAdmin()): ?>
                                        <br><span class="badge bg-secondary" style="font-size: 0.7em;"><?= escape($expense['branch_name'] ?? 'Main Branch') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= escape($expense['category']) ?></td>
                                <td><?= escape($expense['description']) ?></td>
                                <td><strong><?= formatCurrency($expense['amount']) ?></strong></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="<?= url('/modules/expenses/edit.php?id=' . $expense['id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="<?= url('/modules/expenses/delete.php?id=' . $expense['id']) ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Delete this expense?')" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?= renderPaginationUI($pagination) ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
