<?php
/**
 * Stock Valuation Report
 * Shows Total Value of Inventory (Cost vs Selling Price)
 */

$pageTitle = 'Stock Valuation Report';
$requiredRole = ['admin', 'manager'];
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Get Product Stock Data
$query = "
    SELECT 
        p.id, 
        p.name, 
        p.sku, 
        c.name as category,
        p.stock_quantity,
        p.cost_price,
        p.selling_price,
        (p.stock_quantity * p.cost_price) as total_cost_value,
        (p.stock_quantity * p.selling_price) as total_sales_value
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1
    ORDER BY p.name ASC
";

$result = $conn->query($query);
$products = $result->fetch_all(MYSQLI_ASSOC);

// Calculate Totals
$totalStock = 0;
$totalCostValue = 0;
$totalSalesValue = 0;
$potentialProfit = 0;

foreach ($products as $prod) {
    if ($prod['stock_quantity'] > 0) {
        $totalStock += $prod['stock_quantity'];
        $totalCostValue += $prod['total_cost_value'];
        $totalSalesValue += $prod['total_sales_value'];
    }
}

$potentialProfit = $totalSalesValue - $totalCostValue;

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1 class="page-title"><i class="bi bi-box-seam"></i> Stock Valuation</h1>
        <a href="print_stock_valuation.php" target="_blank" class="btn btn-outline-primary">
            <i class="bi bi-printer"></i> Print
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title opacity-75">Total Products</h6>
                    <h3 class="mb-0"><?= count($products) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title opacity-75">Total Stock Qty</h6>
                    <h3 class="mb-0"><?= number_format($totalStock) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="card-title opacity-75">Inventory Asset Value (Cost)</h6>
                    <h3 class="mb-0"><?= formatCurrency($totalCostValue) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title opacity-75">Potential Profit</h6>
                    <h3 class="mb-0"><?= formatCurrency($potentialProfit) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th class="text-center">Stock Qty</th>
                            <th class="text-end">Cost Price</th>
                            <th class="text-end">Selling Price</th>
                            <th class="text-end bg-warning bg-opacity-10">Total Asset (Cost)</th>
                            <th class="text-end bg-success bg-opacity-10">Total Potential (Sale)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $prod): ?>
                        <tr>
                            <td><?= escape($prod['name']) ?></td>
                            <td><small class="text-muted"><?= escape($prod['sku']) ?></small></td>
                            <td><?= escape($prod['category'] ?? '-') ?></td>
                            <td class="text-center">
                                <?php if($prod['stock_quantity'] <= 0): ?>
                                    <span class="badge bg-danger">Out</span>
                                <?php else: ?>
                                    <?= $prod['stock_quantity'] ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= formatCurrency($prod['cost_price']) ?></td>
                            <td class="text-end"><?= formatCurrency($prod['selling_price']) ?></td>
                            <td class="text-end fw-bold bg-warning bg-opacity-10"><?= formatCurrency($prod['total_cost_value']) ?></td>
                            <td class="text-end bg-success bg-opacity-10"><?= formatCurrency($prod['total_sales_value']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-dark">
                        <tr>
                            <td colspan="6" class="text-end">Grand Total</td>
                            <td class="text-end"><?= formatCurrency($totalCostValue) ?></td>
                            <td class="text-end"><?= formatCurrency($totalSalesValue) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
