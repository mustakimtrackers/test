<?php
/**
 * Professional Sales Print Template
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
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$status = $_GET['status'] ?? 'all';
$customerId = $_GET['customer_id'] ?? 'all';

// Build Query
$where = ["s.sale_date BETWEEN ? AND ?"];
$params = [$startDate, $endDate . ' 23:59:59'];
$types = "ss";

if ($status !== 'all') {
    $where[] = "s.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($customerId !== 'all' && is_numeric($customerId)) {
    $where[] = "s.customer_id = ?";
    $params[] = $customerId;
    $types .= "i";
}

$whereSQL = implode(' AND ', $where);

// Get Sales
$query = "
    SELECT 
        s.*,
        c.name as customer_name
    FROM sales s
    LEFT JOIN contacts c ON s.customer_id = c.id
    WHERE $whereSQL
    ORDER BY s.sale_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$sales = $result->fetch_all(MYSQLI_ASSOC);

// Calculate Totals
$totalRevenue = 0;
$totalPaid = 0;
$totalDue = 0;

foreach ($sales as $sale) {
    if (!in_array($sale['status'], ['canceled', 'cancelled'])) {
        $totalRevenue += $sale['total_amount'];
        $totalPaid += $sale['paid_amount'];
        $totalDue += ($sale['total_amount'] - $sale['paid_amount']);
    }
}

$company = getCompanyInfo();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report</title>
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
                <div class="document-type">SALES REPORT</div>
                <div class="meta-grid">
                    <div class="meta-label">Date Generated:</div>
                    <div class="meta-value"><?= date('d M Y') ?></div>
                    <div class="meta-label">Period:</div>
                    <div class="meta-value"><?= date('d M Y', strtotime($startDate)) ?> to <?= date('d M Y', strtotime($endDate)) ?></div>
                </div>
            </div>
        </div>

        <div class="info-blocks">
            <div class="info-box">
                <div class="info-box-title">Total Revenue</div>
                <div class="fw-bold" style="font-size: 14pt;"><?= formatCurrency($totalRevenue) ?></div>
            </div>
            <div class="info-box">
                <div class="info-box-title">Total Collected</div>
                <div class="fw-bold" style="font-size: 14pt; color: #10b981;"><?= formatCurrency($totalPaid) ?></div>
            </div>
            <div class="info-box">
                <div class="info-box-title">Total Due</div>
                <div class="fw-bold" style="font-size: 14pt; color: #ef4444;"><?= formatCurrency($totalDue) ?></div>
            </div>
        </div>

        <table class="table-print">
            <thead>
                <tr>
                    <th style="width: 15%">Date</th>
                    <th style="width: 15%">Invoice #</th>
                    <th style="width: 20%">Customer</th>
                    <th style="width: 10%">Status</th>
                    <th class="text-end" style="width: 15%">Subtotal</th>
                    <th class="text-end" style="width: 15%">Collected</th>
                    <th class="text-end" style="width: 10%">Due</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding: 20px;">No sales found for selected period</td></tr>
                <?php else: ?>
                    <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td>
                            <div><?= date('d M Y', strtotime($sale['sale_date'])) ?></div>
                            <div class="text-muted" style="font-size: 8pt;"><?= date('h:i A', strtotime($sale['sale_date'])) ?></div>
                        </td>
                        <td><?= escape($sale['reference_no']) ?></td>
                        <td class="fw-bold"><?= escape($sale['customer_name'] ?? 'Walk-in Customer') ?></td>
                        <td>
                            <?php if(strtolower($sale['status']) === 'completed'): ?>
                                Completed
                            <?php elseif(strtolower($sale['status']) === 'pending'): ?>
                                <span class="text-danger">Pending</span>
                            <?php else: ?>
                                <?= ucfirst($sale['status']) ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-bold"><?= formatCurrency($sale['total_amount']) ?></td>
                        <td class="text-end"><?= formatCurrency($sale['paid_amount']) ?></td>
                        <td class="text-end fw-bold text-danger"><?= formatCurrency($sale['total_amount'] - $sale['paid_amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="summary-container">
            <div class="summary-box" style="width: 350px;">
                <div class="summary-row grand-total">
                    <div class="summary-label">Total Revenue</div>
                    <div class="summary-value"><?= formatCurrency($totalRevenue) ?></div>
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
