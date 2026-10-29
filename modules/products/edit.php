<?php
/**
 * Edit Product
 * Update product information
 */

$pageTitle = 'Edit Product';
require_once __DIR__ . '/../../includes/auth_check.php';
// Header moved to after POST logic // Ensure DB is loaded

requireAdmin(); // Strict Rule: Only Admin can Edit

$errors = [];
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    setFlashMessage('error', 'Invalid product ID');
    redirect(url('/modules/products/list.php'));
}

// Get product data
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $productId);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    setFlashMessage('error', 'Product not found');
    redirect(url('/modules/products/list.php'));
}

// Get categories, units, and suppliers
$categoriesStmt = $conn->query("SELECT id, name FROM categories ORDER BY name");
$categories = $categoriesStmt->fetch_all(MYSQLI_ASSOC);

$unitsStmt = $conn->query("SELECT id, name, short_name FROM units ORDER BY name");
$units = $unitsStmt->fetch_all(MYSQLI_ASSOC);

$suppliersStmt = $conn->query("SELECT id, name, company_name FROM contacts WHERE type = 'supplier' ORDER BY name");
$suppliers = $suppliersStmt->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/Validator.php';

// Process Form Submission (BEFORE including header)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        // Data
        $data = [
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'cost_price' => $_POST['cost_price'] ?? 0,
            'selling_price' => $_POST['selling_price'] ?? 0
        ];

        // Validation Rules
        $rules = [
            'name' => 'required|min:3',
            'cost_price' => 'numeric',
            'selling_price' => 'numeric'
        ];

        $validator = new Validator($conn);

        if ($validator->validate($data, $rules)) {
            $name = cleanInput($data['name']);
            $description = cleanInput($data['description']);
            $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $unitId = !empty($_POST['unit_id']) ? (int)$_POST['unit_id'] : null;
            $costPrice = (float)$data['cost_price'];
            $sellingPrice = (float)$data['selling_price'];
            $alertQuantity = !empty($_POST['alert_quantity']) ? (int)$_POST['alert_quantity'] : 10;
            $barcodeSymbology = $_POST['barcode_symbology'] ?? 'C128';
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Removed restriction: selling price can be less than cost price (e.g. for clearance/loss sale)
            
            // Slug Logic
            $postedSlug = trim($_POST['slug'] ?? '');
            if (empty($postedSlug)) {
                $postedSlug = generateSlug($name);
            } else {
                $postedSlug = generateSlug($postedSlug); // Sanitize manual input
            }

            // Robust Deduplication
            $slug = $postedSlug;
            $counter = 1;
            while (true) {
                // Check if slug exists AND belongs to a DIFFERENT product
                $slugCheck = $conn->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
                $slugCheck->bind_param("si", $slug, $productId);
                $slugCheck->execute();
                if ($slugCheck->get_result()->num_rows === 0) {
                    $slugCheck->close();
                    break;
                }
                $slugCheck->close();
                $slug = $postedSlug . '-' . $counter;
                $counter++;
            }

            // NEW: Handle SKU update
            $sku = !empty($_POST['sku']) ? cleanInput($_POST['sku']) : $product['sku'];
            
            // Check if SKU exists (excluding current product)
            $skuCheck = $conn->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
            $skuCheck->bind_param("si", $sku, $productId);
            $skuCheck->execute();
            if ($skuCheck->get_result()->num_rows > 0) {
                 $errors[] = "SKU/Barcode '$sku' is already used by another product.";
            }
            $skuCheck->close();

                // Handle Image Upload
                $newImageUploaded = false;
                $imagePath = $product['image']; // Default to existing
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = handleFileUpload($_FILES['image'], 'products');
                    if ($uploadResult) {
                        $imagePath = $uploadResult;
                        $newImageUploaded = true;
                    } else {
                        $errors[] = "Failed to upload image";
                    }
                }

                if (empty($errors)) {
                    $commissionRate = (float)($_POST['commission_rate'] ?? 0);
                    $commissionType = $_POST['commission_type'] ?? 'percentage';
    
                    $stmt = $conn->prepare("
                        UPDATE products 
                        SET name = ?, slug = ?, sku = ?, barcode_symbology = ?, description = ?, category_id = ?, 
                            unit_id = ?, cost_price = ?, selling_price = ?, alert_quantity = ?, is_active = ?,
                            commission_rate = ?, commission_type = ?, image = ?
                        WHERE id = ?
                    ");
                    
                    $stmt->bind_param(
                        "sssssiiiddiissi",
                        $name, $slug, $sku, $barcodeSymbology, $description,
                        $categoryId, $unitId, $costPrice, $sellingPrice, $alertQuantity, $isActive, 
                        $commissionRate, $commissionType, $imagePath, $productId
                    );
                    
                    if ($stmt->execute()) {
                        if ($newImageUploaded && !empty($product['image'])) {
                            deleteUploadedFile($product['image']);
                        }
                        logActivity('update', 'products', $productId, "Old Name: {$product['name']}", "New Name: $name", "Updated product: $name");
                        setFlashMessage('success', 'Product updated successfully!');
                        redirect(url('/modules/products/list.php'));
                    } else {
                        $errors[] = 'Failed to update product: ' . $conn->error;
                    }
                    $stmt->close();
                }
        } else {
            $errors = array_values($validator->getErrors());
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title">
            <i class="bi bi-pencil-square"></i>
            Edit Product
        </h1>
        <a href="<?= url('/modules/products/list.php') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Products
        </a>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= escape($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Product Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Product Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= escape($product['name']) ?>" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">URL Slug (Unique ID)</label>
                            <input type="text" class="form-control" id="slug" name="slug" 
                                   value="<?= escape($product['slug']) ?>">
                            <div class="form-text">Leave as-is or edit to change the product URL identifier.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Product Image</label>
                            <?php if (!empty($product['image'])): ?>
                                <div class="mb-2">
                                    <img src="<?= url($product['image']) ?>" alt="Current Image" class="img-thumbnail" style="max-height: 150px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <div class="form-text">Allowed formats: JPG, PNG, GIF, WEBP. Max size: 5MB.</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" 
                                            <?= ($product['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= escape($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                                    <option value="">No Default Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= $supplier['id'] ?>"
                                            <?= (($product['supplier_id'] ?? '') == $supplier['id']) ? 'selected' : '' ?>>
                                            <?= escape($supplier['name']) ?>
                                            <?php if (!empty($supplier['company_name'])): ?>
                                                (<?= escape($supplier['company_name']) ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select preferred supplier for reordering</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="unit_id" class="form-label">Unit</label>
                                <select class="form-select" id="unit_id" name="unit_id">
                                    <option value="">Select Unit</option>
                                    <?php foreach ($units as $unit): ?>
                                        <option value="<?= $unit['id'] ?>"
                                            <?= ($product['unit_id'] == $unit['id']) ? 'selected' : '' ?>>
                                            <?= escape($unit['name']) ?> (<?= escape($unit['short_name']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= escape($product['description']) ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cost_price" class="form-label">Cost Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">৳</span>
                                    <input type="number" class="form-control" id="cost_price" name="cost_price" 
                                           step="0.01" min="0" value="<?= $product['cost_price'] ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="selling_price" class="form-label">Selling Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">৳</span>
                                    <input type="number" class="form-control" id="selling_price" name="selling_price" 
                                           step="0.01" min="0" value="<?= $product['selling_price'] ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Current Stock</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="<?= $product['stock_quantity'] ?>" disabled>
                                    <a href="<?= url('/modules/products/stock_adjustment.php?id=' . $productId) ?>" 
                                       class="btn btn-outline-primary" title="Adjust Stock">
                                        <i class="bi bi-arrow-left-right"></i> Adjust
                                    </a>
                                </div>
                                <small class="text-muted">Stock changes must go through adjustment workflow</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="alert_quantity" class="form-label">Low Stock Alert Quantity</label>
                                <input type="number" class="form-control" id="alert_quantity" name="alert_quantity" 
                                       min="1" value="<?= $product['alert_quantity'] ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sku" class="form-label">SKU / Barcode</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="sku" name="sku" 
                                       value="<?= escape($product['sku']) ?>" placeholder="Scan or enter code">
                                <button class="btn btn-outline-secondary" type="button" id="scanSkuBtn" title="Scan Barcode">
                                    <i class="bi bi-upc-scan"></i>
                                </button>
                            </div>
                            <small class="text-muted">Edit to change SKU</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="barcode_symbology" class="form-label">Barcode Type</label>
                            <select class="form-select" id="barcode_symbology" name="barcode_symbology">
                                <option value="C128" <?= $product['barcode_symbology'] === 'C128' ? 'selected' : '' ?>>Code 128</option>
                                <option value="C39" <?= $product['barcode_symbology'] === 'C39' ? 'selected' : '' ?>>Code 39</option>
                                <option value="EAN13" <?= $product['barcode_symbology'] === 'EAN13' ? 'selected' : '' ?>>EAN-13</option>
                                <option value="EAN8" <?= $product['barcode_symbology'] === 'EAN8' ? 'selected' : '' ?>>EAN-8</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="commission_rate" class="form-label">Commission Rate</label>
                                <input type="number" class="form-control" id="commission_rate" name="commission_rate" 
                                       step="0.01" min="0" value="<?= $product['commission_rate'] ?? 0 ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="commission_type" class="form-label">Commission Type</label>
                                <select class="form-select" id="commission_type" name="commission_type">
                                    <option value="percentage" <?= ($product['commission_type'] ?? 'percentage') == 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                                    <option value="fixed" <?= ($product['commission_type'] ?? 'percentage') == 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                   <?= $product['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">
                                Active (Visible in POS and reports)
                            </label>
                        </div>
                        
                        <div class="form-actions-sticky">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Product
                            </button>
                            <a href="<?= url('/modules/products/list.php') ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Product Details</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <strong>Created:</strong><br>
                            <small class="text-muted"><?= date('d M Y, g:i A', strtotime($product['created_at'])) ?></small>
                        </li>
                        <li class="mb-2">
                            <strong>Last Updated:</strong><br>
                            <small class="text-muted"><?= date('d M Y, g:i A', strtotime($product['updated_at'])) ?></small>
                        </li>
                        <li class="mb-2">
                            <strong>Status:</strong><br>
                            <?php if ($product['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </li>
                        <li>
                            <strong>Stock Status:</strong><br>
                            <?php if ($product['stock_quantity'] <= 0): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($product['stock_quantity'] <= $product['alert_quantity']): ?>
                                <span class="badge bg-warning">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Barcode Scanner Modal -->
<div class="modal fade" id="barcodeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Scan Barcode</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="barcodeReader" style="width: 100%;"></div>
                <p class="text-muted mt-2">Point camera at barcode</p>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const scanBtn = document.getElementById('scanSkuBtn');
    const skuInput = document.getElementById('sku');
    let html5QrcodeScanner = null;

    if (scanBtn) {
        scanBtn.addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('barcodeModal'));
            modal.show();
            
            document.getElementById('barcodeModal').addEventListener('shown.bs.modal', function () {
                if (!html5QrcodeScanner) {
                    html5QrcodeScanner = new Html5QrcodeScanner(
                        "barcodeReader",
                        { fps: 10, qrbox: { width: 250, height: 250 } },
                        /* verbose= */ false
                    );
                    html5QrcodeScanner.render(onScanSuccess);
                }
            }, { once: true });
        });
    }

    function onScanSuccess(decodedText) {
        // Play beep
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx) {
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            oscillator.type = "sine";
            oscillator.frequency.setValueAtTime(800, audioCtx.currentTime);
            gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.1);
        }

        // Fill input and close
        skuInput.value = decodedText;
        
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear().then(() => {
                html5QrcodeScanner = null;
                const modal = bootstrap.Modal.getInstance(document.getElementById('barcodeModal'));
                modal.hide();
            });
        }
    }

    // Clean up on hide
    document.getElementById('barcodeModal').addEventListener('hidden.bs.modal', function () {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear();
            html5QrcodeScanner = null;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
