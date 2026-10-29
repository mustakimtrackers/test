<?php
/**
 * View Purchase Details
 * Shows complete purchase information with items
 */

$pageTitle = 'Purchase Details';
require_once __DIR__ . '/../../includes/auth_check.php';

$purchaseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($purchaseId <= 0) {
    setFlashMessage('error', 'Invalid purchase ID');
    redirect(url('/modules/purchases/list.php'));
}

// Get purchase with supplier info
$stmt = $conn->prepare("
    SELECT 
        p.*,
        c.name as supplier_name,
        c.company_name,
        c.phone,
        c.email
    FROM purchases p
    LEFT JOIN contacts c ON p.supplier_id = c.id
    WHERE p.id = ?
");
$stmt->bind_param("i", $purchaseId);
$stmt->execute();
$result = $stmt->get_result();
$purchase = $result->fetch_assoc();
$stmt->close();

if (!$purchase) {
    setFlashMessage('error', 'Purchase not found');
    redirect(url('/modules/purchases/list.php'));
}

// Get purchase items
$itemsStmt = $conn->prepare("
    SELECT 
        pi.*,
        pr.name as product_name,
        pr.sku,
        u.short_name as unit
    FROM purchase_items pi
    LEFT JOIN products pr ON pi.product_id = pr.id
    LEFT JOIN units u ON pr.unit_id = u.id
    WHERE pi.purchase_id = ?
    ORDER BY pi.id
");
$itemsStmt->bind_param("i", $purchaseId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$items = $itemsResult->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title">
                    <i class="bi bi-receipt"></i> Purchase Details
                </h1>
                <div class="d-print-none">
                    <a href="<?= url('/modules/purchases/print_order.php?id=' . $purchase['id']) ?>" class="btn btn-success me-2">
                        <i class="bi bi-printer"></i> Print PO
                    </a>
                    <a href="<?= url('/modules/purchases/list.php') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Purchase Info Card -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> Purchase Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td width="40%" class="text-muted">Reference No:</td>
                            <td><strong><?= escape($purchase['reference_no']) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Purchase Date:</td>
                            <td><?= date('d M Y', strtotime($purchase['purchase_date'])) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status:</td>
                            <td>
                                <?php
                                $statusColors = [
                                    'received' => 'success',
                                    'pending' => 'warning',
                                    'ordered' => 'info',
                                    'canceled' => 'danger',
                                    'cancelled' => 'danger'
                                ];
                                $color = $statusColors[$purchase['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $color ?>"><?= ucfirst($purchase['status']) ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Created:</td>
                            <td><?= date('d M Y H:i', strtotime($purchase['created_at'])) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Supplier Info Card -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-truck"></i> Supplier Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td width="40%" class="text-muted">Name:</td>
                            <td><strong><?= escape($purchase['supplier_name']) ?></strong></td>
                        </tr>
                        <?php if ($purchase['company_name']): ?>
                        <tr>
                            <td class="text-muted">Company:</td>
                            <td><?= escape($purchase['company_name']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($purchase['phone']): ?>
                        <tr>
                            <td class="text-muted">Phone:</td>
                            <td><i class="bi bi-telephone"></i> <?= escape($purchase['phone']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($purchase['email']): ?>
                        <tr>
                            <td class="text-muted">Email:</td>
                            <td><i class="bi bi-envelope"></i> <?= escape($purchase['email']) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-box-seam"></i> Purchase Items</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="35%">Product</th>
                                    <th width="15%">SKU</th>
                                    <th width="10%">Unit Cost</th>
                                    <th width="10%">Quantity</th>
                                    <th width="10%">Unit</th>
                                    <th width="15%" class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $serial = 1;
                                foreach ($items as $item): 
                                ?>
                                <tr>
                                    <td><?= $serial++ ?></td>
                                    <td><strong><?= escape($item['product_name']) ?></strong></td>
                                    <td><code><?= escape($item['sku']) ?></code></td>
                                    <td><?= formatCurrency($item['unit_cost']) ?></td>
                                    <td><?= number_format($item['quantity']) ?></td>
                                    <td><?= escape($item['unit'] ?? '-') ?></td>
                                    <td class="text-end"><strong><?= formatCurrency($item['subtotal']) ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="6" class="text-end fw-bold">Total Amount:</td>
                                    <td class="text-end">
                                        <h5 class="mb-0 text-primary"><?= formatCurrency($purchase['total_amount']) ?></h5>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="6" class="text-end">Paid Amount:</td>
                                    <td class="text-end">
                                        <strong class="text-success"><?= formatCurrency($purchase['paid_amount']) ?></strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="6" class="text-end fw-bold">Due Amount:</td>
                                    <td class="text-end">
                                        <h5 class="mb-0 text-danger">
                                            <?= formatCurrency($purchase['total_amount'] - $purchase['paid_amount']) ?>
                                        </h5>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 8px;
}

.card-header {
    border-radius: 8px 8px 0 0 !important;
}

.table-borderless td {
    padding: 0.5rem 0;
}

/* Print Styles */
@media print {
    /* Hide navigation and buttons */
    .page-header .d-print-none,
    .btn,
    nav,
    .sidebar,
    footer {
        display: none !important;
    }
    
    /* Full width for print */
    .container-fluid {
        width: 100%;
        max-width: 100%;
        padding: 0;
    }
    
    /* Page setup */
    @page {
        size: A4;
        margin: 15mm;
    }
    
    body {
        background: white !important;
        color: black !important;
        font-size: 12pt;
    }
    
    /* Keep cards together */
    .card {
        page-break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        margin-bottom: 10pt;
    }
    
    .card-header {
        background-color: #f8f9fa !important;
        color: black !important;
        border-bottom: 2px solid #333 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    /* Table styling */
    table {
        page-break-inside: auto;
        border-collapse: collapse !important;
    }
    
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    
    thead {
        display: table-header-group;
    }
    
    tfoot {
        display: table-footer-group;
        background-color: #f8f9fa !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    /* Ensure colors print */
    .badge,
    .text-primary,
    .text-success,
    .text-danger {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    /* Page title */
    .page-title {
        font-size: 20pt;
        margin-bottom: 10pt;
        border-bottom: 2px solid #333;
        padding-bottom: 5pt;
    }
    
    /* Better spacing */
    .row {
        margin-bottom: 0 !important;
    }
    
    .col-md-6 {
        width: 48%;
        float: left;
        margin-right: 2%;
    }
    
    .col-md-6:last-child {
        margin-right: 0;
    }
    
    /* Clear floats */
    .row::after {
        content: "";
        display: table;
        clear: both;
    }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
