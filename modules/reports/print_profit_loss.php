<?php
/**
 * Professional Profit & Loss Print Template
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

// 1. Total Sales (Accrual Basis)
$salesStmt = $conn->prepare("SELECT SUM(total_amount) as total FROM sales WHERE sale_date BETWEEN ? AND ? AND status = 'completed'");
$endDateTime = $endDate . ' 23:59:59';
$salesStmt->bind_param("ss", $startDate, $endDateTime);
$salesStmt->execute();
$totalSales = $salesStmt->get_result()->fetch_assoc()['total'] ?? 0;
$salesStmt->close();

// 2. Cost of Goods Sold (COGS)
$cogsStmt = $conn->prepare("
    SELECT SUM(si.quantity * p.cost_price) as cogs
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    WHERE s.sale_date BETWEEN ? AND ? AND s.status = 'completed'
");
$cogsStmt->bind_param("ss", $startDate, $endDateTime);
$cogsStmt->execute();
$cogs = $cogsStmt->get_result()->fetch_assoc()['cogs'] ?? 0;
$cogsStmt->close();

// 3. Gross Profit
$grossProfit = $totalSales - $cogs;

// 4. Expenses
$expStmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ?");
$expStmt->bind_param("ss", $startDate, $endDate);
$expStmt->execute();
$totalExpenses = $expStmt->get_result()->fetch_assoc()['total'] ?? 0;
$expStmt->close();

// 5. Net Profit
$netProfit = $grossProfit - $totalExpenses;

$company = getCompanyInfo();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit & Loss Report</title>
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
                <div class="document-type">PROFIT & LOSS STATEMENT</div>
                <div class="meta-grid">
                    <div class="meta-label">Date Generated:</div>
                    <div class="meta-value"><?= date('d M Y') ?></div>
                    <div class="meta-label">Period:</div>
                    <div class="meta-value"><?= date('d M Y', strtotime($startDate)) ?> to <?= date('d M Y', strtotime($endDate)) ?></div>
                </div>
            </div>
        </div>

        <table class="table-print" style="margin-top: 30px;">
            <tbody>
                <!-- Revenue Section -->
                <tr style="background-color: var(--print-bg) !important; -webkit-print-color-adjust: exact;">
                    <td colspan="2" class="fw-bold" style="font-size: 11pt; text-transform: uppercase;">Revenue</td>
                </tr>
                <tr>
                    <td style="padding-left: 30px;">Sales Revenue</td>
                    <td class="text-end fw-bold"><?= formatCurrency($totalSales) ?></td>
                </tr>
                
                <!-- COGS Section -->
                <tr style="background-color: var(--print-bg) !important; -webkit-print-color-adjust: exact;">
                    <td colspan="2" class="fw-bold" style="font-size: 11pt; text-transform: uppercase;">Cost of Sales</td>
                </tr>
                <tr>
                    <td style="padding-left: 30px;">Cost of Goods Sold</td>
                    <td class="text-end fw-bold text-danger">(<?= formatCurrency($cogs) ?>)</td>
                </tr>
                
                <!-- Gross Profit -->
                <tr style="border-top: 2px solid var(--print-accent);">
                    <td class="fw-bold" style="font-size: 12pt; text-transform: uppercase;">Gross Profit</td>
                    <td class="text-end fw-bold" style="font-size: 12pt;"><?= formatCurrency($grossProfit) ?></td>
                </tr>
                
                <!-- Spacer -->
                <tr><td colspan="2" style="border: none; padding: 10px;"></td></tr>

                <!-- Expenses Section -->
                <tr style="background-color: var(--print-bg) !important; -webkit-print-color-adjust: exact;">
                    <td colspan="2" class="fw-bold" style="font-size: 11pt; text-transform: uppercase;">Operating Expenses</td>
                </tr>
                <tr>
                    <td style="padding-left: 30px;">Total Expenses</td>
                    <td class="text-end fw-bold text-danger">(<?= formatCurrency($totalExpenses) ?>)</td>
                </tr>
                
                <!-- Net Profit -->
                <tr style="border-top: 2px solid var(--print-accent); border-bottom: 4px double var(--print-accent);">
                    <td class="fw-bold" style="font-size: 14pt; text-transform: uppercase; padding: 20px 15px;">Net Profit / (Loss)</td>
                    <td class="text-end fw-bold" style="font-size: 14pt; padding: 20px 15px; color: <?= $netProfit >= 0 ? '#10b981' : '#ef4444' ?>;">
                        <?= formatCurrency($netProfit) ?>
                    </td>
                </tr>
            </tbody>
        </table>

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
