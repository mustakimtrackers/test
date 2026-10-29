<?php
/**
 * Professional Purchase Order Print Template
 * Matches Sales Invoice Design
 */

// Debug 500 Error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include authentication and database
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/settings_helper.php';

// Functions are already included by auth_check -> functions.php
// Database is already included by auth_check -> database.php

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$purchaseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($purchaseId <= 0) die('Invalid purchase ID');

// Get purchase details
$stmt = $conn->prepare("
    SELECT 
        p.*,
        c.name as supplier_name,
        c.company_name,
        c.phone as supplier_phone,
        c.address as supplier_address,
        c.email as supplier_email
    FROM purchases p
    LEFT JOIN contacts c ON p.supplier_id = c.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $purchaseId);
$stmt->execute();
$purchase = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$purchase) die('Purchase not found');

// Get items
$stmt = $conn->prepare("
    SELECT 
        pi.*,
        pr.name as product_name,
        pr.sku,
        u.short_name as unit
    FROM purchase_items pi
    LEFT JOIN products pr ON pi.product_id = pr.id
    LEFT JOIN units u ON pr.unit_id = u.id
    WHERE pi.purchase_id = ?
");
$stmt->bind_param("i", $purchaseId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Company Info
$company = getCompanyInfo();
$invoiceTerms = getSetting('invoice_terms', ''); // Reusing terms or could have specific PO terms
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order #<?= escape($purchase['reference_no']) ?></title>
    <link rel="stylesheet" href="<?= url('/assets/css/print_standard.css?v=' . time()) ?>">
</head>
<body>
    <div class="print-controls">
        <button class="btn btn-secondary" onclick="window.history.back()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back
        </button>
        <button class="btn btn-primary" onclick="window.print()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print PO
        </button>
    </div>

    <div id="printableArea">
        <div class="document-header">
            <div class="company-branding">
                <h1><?= escape($company['name']) ?></h1>
                <div class="company-details">
                    <?php if ($company['address']): ?><div><?= nl2br(escape($company['address'])) ?></div><?php endif; ?>
                    <?php if ($company['phone']): ?><div>P: <?= escape($company['phone']) ?></div><?php endif; ?>
                    <?php if ($company['email']): ?><div>E: <?= escape($company['email']) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="document-meta">
                <div class="document-type">PURCHASE ORDER</div>
                <div class="meta-grid">
                    <div class="meta-label">P.O. No:</div>
                    <div class="meta-value"><?= escape($purchase['reference_no']) ?></div>
                    <div class="meta-label">Date:</div>
                    <div class="meta-value"><?= date('d M Y', strtotime($purchase['purchase_date'])) ?></div>
                    <div class="meta-label">Status:</div>
                    <div class="meta-value text-uppercase"><?= escape($purchase['status']) ?></div>
                </div>
            </div>
        </div>

        <div class="info-blocks">
            <div class="info-box">
                <div class="info-box-title">Vendor / Supplier</div>
                <div class="fw-bold mb-1" style="font-size: 11pt;">
                    <?= escape($purchase['supplier_name']) ?>
                    <?php if (!empty($purchase['company_name'])): ?>
                        <span class="text-muted" style="font-weight: 500; font-size: 9pt;">(<?= escape($purchase['company_name']) ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="text-muted" style="font-size: 9pt;">
                    <?php if (!empty($purchase['supplier_phone'])): ?><div>P: <?= escape($purchase['supplier_phone']) ?></div><?php endif; ?>
                    <?php if (!empty($purchase['supplier_email'])): ?><div>E: <?= escape($purchase['supplier_email']) ?></div><?php endif; ?>
                    <?php if (!empty($purchase['supplier_address'])): ?><div><?= nl2br(escape($purchase['supplier_address'])) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="info-box">
                <div class="info-box-title">Order Status</div>
                <div style="font-size: 9pt; line-height: 1.6;">
                    <div><span class="meta-label">Total Amount:</span> <span class="fw-bold"><?= formatCurrency($purchase['total_amount']) ?></span></div>
                    <?php if ($purchase['paid_amount'] > 0): ?>
                    <div><span class="meta-label">Amount Paid:</span> <span class="fw-bold"><?= formatCurrency($purchase['paid_amount']) ?></span></div>
                    <?php endif; ?>
                    <?php 
                    $due = $purchase['total_amount'] - $purchase['paid_amount'];
                    if ($due > 0): 
                    ?>
                    <div><span class="meta-label text-danger">Amount Due:</span> <span class="fw-bold text-danger"><?= formatCurrency($due) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <table class="table-print">
            <thead>
                <tr>
                    <th style="width: 5%">#</th>
                    <th style="width: 45%">Product Description</th>
                    <th class="text-center" style="width: 15%">Qty</th>
                    <th class="text-end" style="width: 15%">Unit Cost</th>
                    <th class="text-end" style="width: 20%">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i = 1;
                foreach ($items as $item): 
                ?>
                <tr>
                    <td class="text-center"><?= $i++ ?></td>
                    <td>
                        <div class="fw-bold"><?= escape($item['product_name']) ?></div>
                        <?php if ($item['sku']): ?>
                        <div class="text-muted" style="font-size: 8pt;">SKU: <?= escape($item['sku']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= number_format($item['quantity']) ?> <?= escape($item['unit'] ?? '') ?></td>
                    <td class="text-end"><?= formatCurrency($item['unit_cost']) ?></td>
                    <td class="text-end fw-bold"><?= formatCurrency($item['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-container">
            <div class="summary-box">
                <div class="summary-row grand-total">
                    <div class="summary-label">Grand Total</div>
                    <div class="summary-value"><?= formatCurrency($purchase['total_amount']) ?></div>
                </div>
            </div>
        </div>

        <div class="document-footer">
            <div class="signature-box">
                <div class="signature-line">Authorized By</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Received By</div>
            </div>
        </div>

        <div class="system-credit">
            Generated securely by FBM ERP System on <?= date('d M Y, h:i A') ?>
        </div>
    </div>
</body>
</html>
