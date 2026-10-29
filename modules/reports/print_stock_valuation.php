<?php
/**
 * Professional Stock Valuation Print Template
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

$totalStock = 0;
$totalCostValue = 0;
$totalSalesValue = 0;

foreach ($products as $prod) {
    if ($prod['stock_quantity'] > 0) {
        $totalStock += $prod['stock_quantity'];
        $totalCostValue += $prod['total_cost_value'];
        $totalSalesValue += $prod['total_sales_value'];
    }
}
$potentialProfit = $totalSalesValue - $totalCostValue;

$company = getCompanyInfo();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Valuation Report</title>
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
                <div class="document-type">STOCK VALUATION</div>
                <div class="meta-grid">
                    <div class="meta-label">Date:</div>
                    <div class="meta-value"><?= date('d M Y') ?></div>
                    <div class="meta-label">Total Stock Items:</div>
                    <div class="meta-value"><?= number_format($totalStock) ?></div>
                </div>
            </div>
        </div>

        <div class="info-blocks">
            <div class="info-box">
                <div class="info-box-title">Total Cost Asset Value</div>
                <div class="fw-bold" style="font-size: 14pt;"><?= formatCurrency($totalCostValue) ?></div>
            </div>
            <div class="info-box">
                <div class="info-box-title">Potential Sales Value</div>
                <div class="fw-bold" style="font-size: 14pt; color: #10b981;"><?= formatCurrency($totalSalesValue) ?></div>
            </div>
        </div>

        <table class="table-print">
            <thead>
                <tr>
                    <th style="width: 25%">Product Name</th>
                    <th style="width: 15%">SKU / Category</th>
                    <th class="text-center" style="width: 10%">Qty</th>
                    <th class="text-end" style="width: 15%">Cost Price</th>
                    <th class="text-end" style="width: 15%">Selling Price</th>
                    <th class="text-end" style="width: 20%">Total Asset (Cost)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="6" class="text-center text-muted" style="padding: 20px;">No stock items found</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $prod): ?>
                    <tr>
                        <td class="fw-bold"><?= escape($prod['name']) ?></td>
                        <td>
                            <div><?= escape($prod['sku']) ?></div>
                            <div class="text-muted" style="font-size: 8pt;"><?= escape($prod['category'] ?? '-') ?></div>
                        </td>
                        <td class="text-center fw-bold <?= $prod['stock_quantity'] <= 0 ? 'text-danger' : '' ?>">
                            <?php if($prod['stock_quantity'] <= 0): ?>
                                Out
                            <?php else: ?>
                                <?= $prod['stock_quantity'] ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= formatCurrency($prod['cost_price']) ?></td>
                        <td class="text-end"><?= formatCurrency($prod['selling_price']) ?></td>
                        <td class="text-end fw-bold"><?= formatCurrency($prod['total_cost_value']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="summary-container">
            <div class="summary-box">
                <div class="summary-row">
                    <div class="summary-label">Total Potential Profit</div>
                    <div class="summary-value" style="color: #10b981;"><?= formatCurrency($potentialProfit) ?></div>
                </div>
                <div class="summary-row grand-total">
                    <div class="summary-label">Total Asset Value</div>
                    <div class="summary-value"><?= formatCurrency($totalCostValue) ?></div>
                </div>
            </div>
        </div>

        <div class="document-footer">
            <div class="signature-box">
                <div class="signature-line">Prepared By</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Authorized Signature</div>
            </div>
        </div>

        <div class="system-credit">
            Generated securely by FBM ERP System on <?= date('d M Y, h:i A') ?>
        </div>
    </div>
</body>
</html>
