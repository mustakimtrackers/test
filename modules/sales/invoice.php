<?php
/**
 * Professional Invoice Template - FOrbit POS
 * Redesigned with settings integration
 */

// Start session first
if (!isset($_SESSION)) {
    session_start();
}

// Load dependencies in correct order
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/settings_helper.php';

$pageTitle = 'Invoice';
$hideNavbar = true;

require_once __DIR__ . '/../../includes/auth_check.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($saleId === 0) {
    die('Invalid invoice ID');
}

// Get sale details
$stmt = $conn->prepare("
    SELECT 
        s.*,
        c.name as customer_name,
        c.phone as customer_phone,
        c.address as customer_address,
        u.name as created_by_name
    FROM sales s
    LEFT JOIN contacts c ON s.customer_id = c.id
    LEFT JOIN users u ON s.created_by = u.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $saleId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Invoice not found');
}

$sale = $result->fetch_assoc();
$stmt->close();

// Get sale items
$stmt = $conn->prepare("
    SELECT 
        si.*,
        p.name as product_name,
        p.sku
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
");
$stmt->bind_param("i", $saleId);
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

// Load company settings
$company = getCompanyInfo();
$invoiceNote = getSetting('invoice_note', 'Thank you for your business!');
$invoiceTerms = getSetting('invoice_terms', '');
$showTaxNumber = getSetting('print_show_tax_number', true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= escape($sale['reference_no']) ?> - <?= escape($company['name']) ?></title>
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
            Print Invoice
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
                    <?php if ($showTaxNumber && $company['tax_number']): ?><div>Tax ID: <?= escape($company['tax_number']) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="document-meta">
                <div class="document-type">TAX INVOICE</div>
                <div class="meta-grid">
                    <div class="meta-label">Invoice No:</div>
                    <div class="meta-value"><?= escape($sale['reference_no']) ?></div>
                    <div class="meta-label">Date:</div>
                    <div class="meta-value"><?= date('d M Y', strtotime($sale['sale_date'])) ?></div>
                    <div class="meta-label">Status:</div>
                    <div class="meta-value text-uppercase"><?= escape($sale['status']) ?></div>
                    <div class="meta-label">Served By:</div>
                    <div class="meta-value"><?= escape($sale['created_by_name']) ?></div>
                </div>
            </div>
        </div>

        <div class="info-blocks">
            <div class="info-box">
                <div class="info-box-title">Billed To</div>
                <div class="fw-bold mb-1" style="font-size: 11pt;"><?= escape($sale['customer_name'] ?? 'Walk-in Customer') ?></div>
                <div class="text-muted" style="font-size: 9pt;">
                    <?php if (!empty($sale['customer_phone'])): ?><div>P: <?= escape($sale['customer_phone']) ?></div><?php endif; ?>
                    <?php if (!empty($sale['customer_address'])): ?><div><?= nl2br(escape($sale['customer_address'])) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="info-box">
                <div class="info-box-title">Payment Info</div>
                <div style="font-size: 9pt; line-height: 1.6;">
                    <div><span class="meta-label">Payment Status:</span> <span class="fw-bold"><?= escape(ucfirst($sale['payment_status'])) ?></span></div>
                    <div><span class="meta-label">Total Amount:</span> <span class="fw-bold"><?= formatCurrency($sale['total_amount']) ?></span></div>
                    <?php if ($sale['paid_amount'] > 0): ?>
                    <div><span class="meta-label">Amount Paid:</span> <span class="fw-bold"><?= formatCurrency($sale['paid_amount']) ?></span></div>
                    <?php endif; ?>
                    <?php 
                    $due = $sale['total_amount'] - $sale['paid_amount'];
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
                    <th style="width: 45%">Item Description</th>
                    <th class="text-center" style="width: 15%">Qty</th>
                    <th class="text-end" style="width: 15%">Unit Price</th>
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
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-end"><?= formatCurrency($item['unit_price']) ?></td>
                    <td class="text-end fw-bold"><?= formatCurrency($item['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-container">
            <div class="summary-box">
                <div class="summary-row">
                    <div class="summary-label">Subtotal</div>
                    <div class="summary-value"><?= formatCurrency($sale['total_amount']) ?></div>
                </div>
                <?php if ($sale['discount_amount'] > 0): ?>
                <div class="summary-row">
                    <div class="summary-label">Discount</div>
                    <div class="summary-value">-<?= formatCurrency($sale['discount_amount']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($sale['tax_amount'] > 0): ?>
                <div class="summary-row">
                    <div class="summary-label">Tax</div>
                    <div class="summary-value">+<?= formatCurrency($sale['tax_amount']) ?></div>
                </div>
                <?php endif; ?>
                <div class="summary-row grand-total">
                    <div class="summary-label">Grand Total</div>
                    <div class="summary-value"><?= formatCurrency($sale['total_amount'] - $sale['discount_amount'] + $sale['tax_amount']) ?></div>
                </div>
            </div>
        </div>

        <?php if (!empty($invoiceNote) || !empty($invoiceTerms)): ?>
        <div style="margin-top: 40px; font-size: 9pt;">
            <?php if (!empty($invoiceNote)): ?>
                <div style="margin-bottom: 10px;">
                    <strong>Note:</strong><br>
                    <span class="text-muted"><?= nl2br(escape($invoiceNote)) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($invoiceTerms)): ?>
                <div>
                    <strong>Terms & Conditions:</strong><br>
                    <span class="text-muted"><?= nl2br(escape($invoiceTerms)) ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="document-footer">
            <div class="signature-box">
                <div class="signature-line">Customer Signature</div>
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
