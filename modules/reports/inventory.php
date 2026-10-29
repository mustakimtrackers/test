<?php
/**
 * Inventory Report
 * Stock Valuation
 */

$pageTitle = 'Inventory Report';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Get products with stock value
$query = "
    SELECT 
        p.id,
        p.name,
        p.sku,
        p.stock_quantity,
        p.cost_price,
        p.selling_price,
        c.name as category_name,
        (p.stock_quantity * p.cost_price) as stock_value
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1
    ORDER BY p.name
";
$result = $conn->query($query);
$products = $result->fetch_all(MYSQLI_ASSOC);

$totalStockValue = 0;
$totalItems = 0;
foreach ($products as $p) {
    $totalStockValue += $p['stock_value'];
    $totalItems += $p['stock_quantity'];
}
?>

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1 class="page-title"><i class="bi bi-boxes"></i> Inventory Valuation</h1>
        <a href="print_inventory.php" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-printer"></i> Print
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Stock Value</h5>
                    <h2 class="display-6 fw-bold"><?= formatCurrency($totalStockValue) ?></h2>
                    <small>Based on Cost Price</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Items in Stock</h5>
                    <h2 class="display-6 fw-bold"><?= number_format($totalItems) ?></h2>
                    <small>Units across all products</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="reportTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th class="text-center">Stock Qty</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Total Value</th>
                            <th class="text-end">S. Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= escape($product['name']) ?></td>
                            <td><code><?= escape($product['sku']) ?></code></td>
                            <td><?= escape($product['category_name'] ?? 'Uncategorized') ?></td>
                            <td class="text-center">
                                <?php if($product['stock_quantity'] <= 5): ?>
                                    <span class="badge bg-danger"><?= $product['stock_quantity'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-success"><?= $product['stock_quantity'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= formatCurrency($product['cost_price']) ?></td>
                            <td class="text-end fw-bold"><?= formatCurrency($product['stock_value']) ?></td>
                            <td class="text-end text-muted"><?= formatCurrency($product['selling_price']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="fw-bold text-end">Totals:</td>
                            <td class="text-center fw-bold"><?= number_format($totalItems) ?></td>
                            <td></td>
                            <td class="text-end fw-bold"><?= formatCurrency($totalStockValue) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Simple datatable if needed, but standard table is fine for now
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
