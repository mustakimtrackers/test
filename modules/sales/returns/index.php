<?php
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Fetch Returns
$query = "
    SELECT r.*, s.reference_no as sale_ref, u.name as creator_name
    FROM sale_returns r
    JOIN sales s ON r.sale_id = s.id
    LEFT JOIN users u ON r.created_by = u.id
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$returns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total for pagination
$total = $conn->query("SELECT COUNT(*) FROM sale_returns")->fetch_row()[0];
$pagination = getPagination($total, $page, $limit);

?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h3 mb-0 text-gray-800">Sales Returns</h2>
        <a href="<?= url('/modules/sales/returns/create.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Process New Return
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Return ID</th>
                            <th>Sale Ref</th>
                            <th>Date</th>
                            <th>Total Amount</th>
                            <th>Refunded</th>
                            <th>Status</th>
                            <th>Processed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($returns)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No returns found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($returns as $ret): ?>
                            <tr>
                                <td>RET-<?= str_pad($ret['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td><a href="<?= url('/modules/sales/view.php?id=' . $ret['sale_id']) ?>"><?= escape($ret['sale_ref']) ?></a></td>
                                <td><?= date('d M Y', strtotime($ret['return_date'])) ?></td>
                                <td><strong><?= formatCurrency($ret['total_amount']) ?></strong></td>
                                <td class="text-danger"><?= formatCurrency($ret['refund_amount']) ?></td>
                                <td>
                                    <?php if($ret['status'] == 'completed'): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php elseif($ret['status'] == 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= escape($ret['creator_name']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= renderPaginationUI($pagination) ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
