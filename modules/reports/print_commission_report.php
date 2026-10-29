<?php
/**
 * Professional Commission Print Template
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

$company = getCompanyInfo();

$totalCommission = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Report</title>
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
                <div class="document-type">COMMISSION REPORT</div>
                <div class="meta-grid">
                    <div class="meta-label">Date Generated:</div>
                    <div class="meta-value"><?= date('d M Y') ?></div>
                    <div class="meta-label">Period:</div>
                    <div class="meta-value"><?= date('d M Y', strtotime($startDate)) ?> to <?= date('d M Y', strtotime($endDate)) ?></div>
                </div>
            </div>
        </div>

        <table class="table-print" style="margin-top: 30px;">
            <thead>
                <tr>
                    <th style="width: 15%">Date</th>
                    <th style="width: 20%">Invoice</th>
                    <th style="width: 25%">Product</th>
                    <th class="text-center" style="width: 10%">Qty</th>
                    <th class="text-end" style="width: 15%">Total Sale</th>
                    <th class="text-end" style="width: 15%">Commission Earned</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): 
                        $totalCommission += $row['commission_amount'];
                    ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($row['sale_date'])) ?></td>
                        <td><?= escape($row['reference_no']) ?></td>
                        <td class="fw-bold"><?= escape($row['product_name']) ?></td>
                        <td class="text-center"><?= $row['quantity'] ?></td>
                        <td class="text-end"><?= formatCurrency($row['subtotal']) ?></td>
                        <td class="text-end fw-bold" style="color: #10b981;">
                            <?= formatCurrency($row['commission_amount']) ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No commission records found for this period.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($result->num_rows > 0): ?>
        <div class="summary-container">
            <div class="summary-box" style="width: 350px;">
                <div class="summary-row grand-total">
                    <div class="summary-label">Total Commission</div>
                    <div class="summary-value" style="color: #10b981;"><?= formatCurrency($totalCommission) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
