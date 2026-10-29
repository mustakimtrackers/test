<?php
/**
 * Commission Report
 * Shows commission earned from sales
 */

$pageTitle = 'Commission Report';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Date Filter
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Fetch Commission Data
$query = "
    SELECT 
        si.sale_id,
        s.sale_date,
        s.reference_no,
        p.name as product_name,
        si.quantity,
        si.unit_price,
        si.subtotal,
        si.commission_amount
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    WHERE s.sale_date BETWEEN ? AND ?
    AND si.commission_amount > 0
    ORDER BY s.sale_date DESC
";

$stmt = $conn->prepare($query);
$endDateFormat = $endDate . ' 23:59:59';
$stmt->bind_param("ss", $startDate, $endDateFormat);
$stmt->execute();
$result = $stmt->get_result();

$totalCommission = 0;
?>

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1 class="page-title">
            <i class="bi bi-cash-coin"></i> Commission Report
        </h1>
        <a href="../dashboard/index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?= $startDate ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?= $endDate ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                    <a href="commission_report.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Commission Details</h5>
            <a href="print_commission_report.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" target="_blank" class="btn btn-sm btn-success">
                <i class="bi bi-printer"></i> Print
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Product</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Total Sale</th>
                            <th class="text-end">Commission Earned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): 
                                $totalCommission += $row['commission_amount'];
                            ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($row['sale_date'])) ?></td>
                                <td>
                                    <a href="../sales/invoice.php?id=<?= $row['sale_id'] ?>">
                                        <?= escape($row['reference_no']) ?>
                                    </a>
                                </td>
                                <td><?= escape($row['product_name']) ?></td>
                                <td class="text-end"><?= $row['quantity'] ?></td>
                                <td class="text-end"><?= formatCurrency($row['subtotal']) ?></td>
                                <td class="text-end fw-bold text-success">
                                    <?= formatCurrency($row['commission_amount']) ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="table-active fw-bold">
                                <td colspan="5" class="text-end">Total Commission:</td>
                                <td class="text-end text-success"><?= formatCurrency($totalCommission) ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No commission records found for this period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
