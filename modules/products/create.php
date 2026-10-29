<?php
/**
 * Create New Product
 * Form with image upload and SKU auto-generation
 */

$pageTitle = 'Add Product';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';


// requireAdmin(); // Allow Manager/Cashier/Staff to Add Products (per new rules)
if (!isLoggedIn()) {
    redirect(url('/modules/auth/login.php'));
}

// Get categories and units
$categoriesStmt = $conn->query("SELECT id, name FROM categories ORDER BY name");
$categories = $categoriesStmt->fetch_all(MYSQLI_ASSOC);

$unitsStmt = $conn->query("SELECT id, name, short_name FROM units ORDER BY name");
$units = $unitsStmt->fetch_all(MYSQLI_ASSOC);

// Get suppliers
$suppliersStmt = $conn->query("SELECT id, name, company_name FROM contacts WHERE type = 'supplier' ORDER BY name");
$suppliers = $suppliersStmt->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/Validator.php';

// Process Form Submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $errors[] = 'The uploaded file is too large. Please select a smaller image.';
    } elseif (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Your session may have expired, please refresh the page.';
    } else {
        // Data
        $data = [
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'category_id' => $_POST['category_id'] ?? '',
            'unit_id' => $_POST['unit_id'] ?? '',
            'cost_price' => $_POST['cost_price'] ?? 0,
            'selling_price' => $_POST['selling_price'] ?? 0,
            'stock_quantity' => $_POST['stock_quantity'] ?? 0,
            'alert_quantity' => $_POST['alert_quantity'] ?? 10
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
            $categoryId = !empty($data['category_id']) ? (int)$data['category_id'] : null;
            $unitId = !empty($data['unit_id']) ? (int)$data['unit_id'] : null;
            $costPrice = (float)$data['cost_price'];
            $sellingPrice = (float)$data['selling_price'];
            $stockQuantity = (int)$data['stock_quantity'];
            $alertQuantity = (int)$data['alert_quantity'];
            $barcodeSymbology = $_POST['barcode_symbology'] ?? 'C128';

            // Removed restriction: selling price can be less than cost price (e.g. for clearance/loss sale)


            if (empty($errors)) {
                try {
                // Verify database connection
                if (!$conn || $conn->connect_error) {
                    throw new Exception("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'));
                }
                
                // Generate SKU and slug
                $sku = !empty($_POST['sku']) ? cleanInput($_POST['sku']) : generateSKU($name);
                
                // Check if SKU already exists
                $skuCheck = $conn->prepare("SELECT id FROM products WHERE sku = ?");
                $skuCheck->bind_param("s", $sku);
                $skuCheck->execute();
                if ($skuCheck->get_result()->num_rows > 0) {
                     throw new Exception("SKU/Barcode '$sku' already exists. Please use a unique code.");
                }
                $skuCheck->close();

                // Robust Slug Generation
                $postedSlug = trim($_POST['slug'] ?? '');
                if (!empty($postedSlug)) {
                    $baseSlug = generateSlug($postedSlug); // Sanitize manual input
                } else {
                    $baseSlug = generateSlug($name);
                }
                
                $slug = $baseSlug;
                $counter = 1;

                while (true) {
                    $slugCheck = $conn->prepare("SELECT id FROM products WHERE slug = ?");
                    $slugCheck->bind_param("s", $slug);
                    $slugCheck->execute();
                    if ($slugCheck->get_result()->num_rows === 0) {
                        $slugCheck->close();
                        break;
                    }
                    $slugCheck->close();
                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }

                $commissionRate = (float)($_POST['commission_rate'] ?? 0);
                $commissionType = $_POST['commission_type'] ?? 'percentage';

                // Handle Image Upload
                $imagePath = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = handleFileUpload($_FILES['image'], 'products');
                    if ($uploadResult) {
                        $imagePath = $uploadResult;
                    } else {
                        $errors[] = "Failed to upload image";
                    }
                }

                if (empty($errors)) {
                    $stmt = $conn->prepare("
                        INSERT INTO products (name, slug, sku, barcode_symbology, description, category_id, unit_id, 
                                             cost_price, selling_price, stock_quantity, alert_quantity, commission_rate, commission_type, image, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    
                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
    
                    $stmt->bind_param(
                        "sssssiiddiidss", // Added 's' for image
                        $name, $slug, $sku, $barcodeSymbology, $description,
                        $categoryId, $unitId, $costPrice, $sellingPrice, $stockQuantity, $alertQuantity, $commissionRate, $commissionType, $imagePath
                    );
                    
                    if ($stmt->execute()) {
                        $productId = $conn->insert_id;
                        
                        // Initialize branch stock in product_stocks table
                        $branch_id = $_SESSION['branch_id'] ?? 1;
                        $psStmt = $conn->prepare("
                            INSERT INTO product_stocks (product_id, branch_id, quantity) 
                            VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE quantity = quantity + ?
                        ");
                        if ($psStmt) {
                            $psStmt->bind_param("iiii", $productId, $branch_id, $stockQuantity, $stockQuantity);
                            $psStmt->execute();
                            $psStmt->close();
                        }

                        // Log activity
                        logActivity('create', 'products', $productId, null, null, "Created product: $name (SKU: $sku)");
                        
                        setFlashMessage('success', 'Product created successfully!');
                        
                        // Ensure redirect happens
                        header("Location: " . url("/modules/products/list.php"));
                        exit();
                    } else {
                        throw new Exception('Failed to create product: ' . $stmt->error);
                    }
                    $stmt->close();
                }
                } catch (Exception $e) {
                    $errors[] = "System Error: " . $e->getMessage();
                    error_log("Product Create Error: " . $e->getMessage() . "\nStack Trace: " . $e->getTraceAsString());
                }
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
            <i class="bi bi-plus-circle"></i>
            Add New Product
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
                                   value="<?= escape($_POST['name'] ?? '') ?>" required autofocus>
                        </div>
                        
                        <div class="mb-3">
                            <label for="slug" class="form-label">URL Slug (Optional)</label>
                            <input type="text" class="form-control" id="slug" name="slug" 
                                   value="<?= escape($_POST['slug'] ?? '') ?>" placeholder="Auto-generated if empty">
                            <div class="form-text">Unique ID for the product URL. Leave empty to auto-generate.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Product Image</label>
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
                                            <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                            <?= escape($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                                    <option value="">No Default Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= $supplier['id'] ?>"
                                            <?= (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $supplier['id']) ? 'selected' : '' ?>>
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
                                            <?= (isset($_POST['unit_id']) && $_POST['unit_id'] == $unit['id']) ? 'selected' : '' ?>>
                                            <?= escape($unit['name']) ?> (<?= escape($unit['short_name']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= escape($_POST['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cost_price" class="form-label">Cost Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">৳</span>
                                    <input type="number" class="form-control" id="cost_price" name="cost_price" 
                                           step="0.01" min="0" value="<?= $_POST['cost_price'] ?? '0' ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="selling_price" class="form-label">Selling Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">৳</span>
                                    <input type="number" class="form-control" id="selling_price" name="selling_price" 
                                           step="0.01" min="0" value="<?= $_POST['selling_price'] ?? '0' ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="stock_quantity" class="form-label">Initial Stock Quantity</label>
                                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" 
                                       min="0" value="<?= $_POST['stock_quantity'] ?? '0' ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="alert_quantity" class="form-label">Low Stock Alert Quantity</label>
                                <input type="number" class="form-control" id="alert_quantity" name="alert_quantity" 
                                       min="1" value="<?= $_POST['alert_quantity'] ?? '10' ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sku" class="form-label">SKU / Barcode</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="sku" name="sku" 
                                           value="<?= escape($_POST['sku'] ?? '') ?>" placeholder="Scan or enter code (Auto-generated if empty)">
                                    <button class="btn btn-outline-secondary" type="button" id="scanSkuBtn" title="Scan Barcode">
                                        <i class="bi bi-upc-scan"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Leave empty to auto-generate</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="barcode_symbology" class="form-label">Barcode Type</label>
                                <select class="form-select" id="barcode_symbology" name="barcode_symbology">
                                    <option value="C128" selected>Code 128 (Standard)</option>
                                    <option value="C39">Code 39</option>
                                    <option value="EAN13">EAN-13</option>
                                    <option value="EAN8">EAN-8</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="commission_rate" class="form-label">Commission Rate</label>
                                <input type="number" class="form-control" id="commission_rate" name="commission_rate" 
                                       step="0.01" min="0" value="<?= $_POST['commission_rate'] ?? '0' ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="commission_type" class="form-label">Commission Type</label>
                                <select class="form-select" id="commission_type" name="commission_type">
                                    <option value="percentage" <?= (isset($_POST['commission_type']) && $_POST['commission_type'] == 'percentage') ? 'selected' : '' ?>>Percentage (%)</option>
                                    <option value="fixed" <?= (isset($_POST['commission_type']) && $_POST['commission_type'] == 'fixed') ? 'selected' : '' ?>>Fixed Amount</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Create Product
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
                    <h5 class="card-title mb-0">Quick Tips</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            SKU will be generated automatically
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Set alert quantity for low stock notifications
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Selling price should be higher than cost price
                        </li>
                        <li>
                            <i class="bi bi-check-circle text-success"></i>
                            Initial stock can be updated later via purchases
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
