<?php
/**
 * Barcode Generator Page
 * Uses JsBarcode for client-side rendering
 */

$pageTitle = 'Generate Barcodes';
// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: list.php');
    exit;
}

// Fetch Product
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    die("Product not found");
}

require_once __DIR__ . '/../../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>

<div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 1000; display: flex; gap: 10px;">
    <button class="print-btn btn btn-secondary" onclick="window.history.back()">
        <i class="bi bi-arrow-left"></i> Back
    </button>
    <button class="print-btn btn btn-primary" onclick="window.print()">
        <i class="bi bi-printer"></i> Print Barcodes
    </button>
</div>

<div class="container-fluid no-print">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1 class="page-title">
            <i class="bi bi-upc-scan"></i> Generate Barcodes
        </h1>
        <a href="list.php" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Settings</h5>
                </div>
                <div class="card-body">
                    <form id="barcodeForm">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" class="form-control" value="<?= escape($product['name']) ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SKU (Code)</label>
                            <input type="text" class="form-control" value="<?= escape($product['sku']) ?>" readonly id="skuInput">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price</label>
                            <input type="text" class="form-control" value="<?= formatCurrency($product['selling_price']) ?>" readonly id="priceInput">
                        </div>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <label class="form-label">Number of Labels</label>
                            <input type="number" class="form-control" id="labelCount" value="10" min="1" max="100">
                        </div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="showName" checked>
                            <label class="form-check-label" for="showName">Show Product Name</label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="showPrice" checked>
                            <label class="form-check-label" for="showPrice">Show Price</label>
                        </div>

                        <button type="button" class="btn btn-primary w-100" onclick="generateLabels()">
                            <i class="bi bi-arrow-repeat"></i> Update Preview
                        </button>
                        
                        <button type="button" class="btn btn-success w-100 mt-2" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Labels
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Preview</h5>
                </div>
                <div class="card-body bg-light text-center" style="min-height: 400px; overflow-y: auto;">
                    <div id="printArea" class="d-flex flex-wrap justify-content-center gap-3 p-3 bg-white shadow-sm" style="width: 210mm; min-height: 297mm; margin: 0 auto;">
                        <!-- Labels will be injected here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Only Section -->
<div id="printSection" class="print-only">
    <!-- Content copied via JS -->
</div>

<style>
/* Screen Styles */
.print-only {
    display: none;
}

/* Label Styles */
.label-item {
    width: 200px; /* Adjust based on sticker size */
    height: 120px;
    border: 1px dotted #ccc;
    padding: 10px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    background: white;
}

.label-name {
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 5px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
}

.label-price {
    font-size: 14px;
    font-weight: bold;
    margin-top: 5px;
}

/* Print Styles */
@media print {
    body * {
        visibility: hidden;
    }
    
    .no-print {
        display: none !important;
    }

    #printSection, #printSection * {
        visibility: visible;
    }

    #printSection {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-start;
        align-content: flex-start;
        gap: 10px; /* Gap between stickers */
        padding: 10px;
    }
    
    .label-item {
        border: 1px solid #ddd; /* Light border aid for cutting if needed, or none */
        break-inside: avoid;
    }
}
</style>

<script>
function generateLabels() {
    const count = document.getElementById('labelCount').value;
    const sku = document.getElementById('skuInput').value;
    const name = "<?= escape($product['name']) ?>";
    const price = document.getElementById('priceInput').value;
    const showName = document.getElementById('showName').checked;
    const showPrice = document.getElementById('showPrice').checked;
    
    const container = document.getElementById('printArea');
    const printSection = document.getElementById('printSection');
    
    let html = '';
    
    for (let i = 0; i < count; i++) {
        html += `
            <div class="label-item">
                ${showName ? `<div class="label-name">${name}</div>` : ''}
                <svg class="barcode-svg"
                    jsbarcode-format="CODE128"
                    jsbarcode-value="${sku}"
                    jsbarcode-textmargin="0"
                    jsbarcode-fontoptions="bold"
                    jsbarcode-height="40"
                    jsbarcode-width="2"
                    jsbarcode-displayValue="true"
                    jsbarcode-fontSize="14"
                ></svg>
                ${showPrice ? `<div class="label-price">${price}</div>` : ''}
            </div>
        `;
    }
    
    container.innerHTML = html;
    printSection.innerHTML = html;
    
    // Initialize Barcodes
    JsBarcode(".barcode-svg").init();
}

// Initial Generation
document.addEventListener('DOMContentLoaded', generateLabels);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
