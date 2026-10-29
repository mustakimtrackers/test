<?php
/**
 * Professional Inventory Print Template
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth_check.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/settings_helper.php';

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

$company = getCompanyInfo();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Report</title>
    <link rel="stylesheet" href="<?= url('/assets/css/print_standard.css?v=' . time()) ?>">
</head>
<body>
    <div class="print-controls">
        <button class="btn btn-secondary" onclick="window.close()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            Close
        </button>
        <button class="btn btn-primary" onclick="window.print()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print Report
        </button>
    </div>

    <div id="printableArea">
        <div class="document-header">
            <div class="company-branding">
                <h1><?= escape($company['name']) ?></h1>
                <div class="company-details">
                    <?php if ($company['address']): ?><div><?= nl2br(escape($company['address'])) ?></div><?php endif; ?>
                    <?php if ($company['phone']): ?><div>P: <?= escape($company['phone']) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="document-meta">
                <div class="document-type">INVENTORY REPORT</div>
                <div class="meta-grid">
                    <div class="meta-label">Date:</div>
                    <div class="meta-value"><?= date('d M Y') ?></div>
                    <div class="meta-label">Total SKUs:</div>
                    <div class="meta-value"><?= count($products) ?></div>
                </div>
            </div>
        </div>

        <div class="info-blocks">
            <div class="info-box">
                <div class="info-box-title">Total Stock Value</div>
                <div class="fw-bold" style="font-size: 14pt;"><?= formatCurrency($totalStockValue) ?></div>
            </div>
            <div class="info-box">
                <div class="info-box-title">Total Physical Items</div>
                <div class="fw-bold" style="font-size: 14pt;"><?= number_format($totalItems) ?></div>
            </div>
        </div>

        <table class="table-print">
            <thead>
                <tr>
                    <th style="width: 25%">Product Name</th>
                    <th style="width: 15%">SKU</th>
                    <th style="width: 15%">Category</th>
                    <th class="text-center" style="width: 10%">Qty</th>
                    <th class="text-end" style="width: 15%">Cost Price</th>
                    <th class="text-end" style="width: 20%">Total Value</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="6" class="text-center text-muted" style="padding: 20px;">No products found</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td class="fw-bold"><?= escape($product['name']) ?></td>
                        <td><?= escape($product['sku']) ?></td>
                        <td><?= escape($product['category_name'] ?? 'Uncategorized') ?></td>
                        <td class="text-center fw-bold <?= $product['stock_quantity'] <= 5 ? 'text-danger' : '' ?>">
                            <?= $product['stock_quantity'] ?>
                        </td>
                        <td class="text-end"><?= formatCurrency($product['cost_price']) ?></td>
                        <td class="text-end fw-bold"><?= formatCurrency($product['stock_value']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="summary-container">
            <div class="summary-box">
                <div class="summary-row">
                    <div class="summary-label">Total Items</div>
                    <div class="summary-value"><?= number_format($totalItems) ?></div>
                </div>
                <div class="summary-row grand-total">
                    <div class="summary-label">Total Value</div>
                    <div class="summary-value"><?= formatCurrency($totalStockValue) ?></div>
                </div>
            </div>
        </div>

        <div class="document-footer">
            <div class="signature-box">
                <div class="signature-line">Prepared By</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Manager Approval</div>
            </div>
        </div>

        <div class="system-credit">
            Generated securely by FBM ERP System on <?= date('d M Y, h:i A') ?>
        </div>
    </div>
</body>
</html>
