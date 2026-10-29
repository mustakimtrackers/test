<?php
/**
 * Stock Adjustment - Professional inventory management
 */
$pageTitle = 'Stock Adjustment';
require_once __DIR__ . '/../../includes/auth_check.php';
// Header moved after POST logic to prevent "headers already sent" error during redirect

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    setFlashMessage('error', 'Invalid product');
    redirect(url('/modules/products/list.php'));
}

// Get product details
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    setFlashMessage('error', 'Product not found');
    redirect(url('/modules/products/list.php'));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $adjustment_type = $_POST['adjustment_type'] ?? 'add';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reason = cleanInput($_POST['reason'] ?? '');
    
    if ($quantity <= 0) {
        $error = 'Quantity must be greater than zero';
    } elseif (empty($reason)) {
        $error = 'Reason is required';
    } else {
        $conn->begin_transaction();
        
        try {
            $final_quantity = $adjustment_type === 'add' ? $quantity : -$quantity;
            
            // Check if removing more than available
            if ($adjustment_type === 'remove' && $product['stock_quantity'] < $quantity) {
                throw new Exception('Cannot remove more stock than available');
            }
            
            // Log stock movement
            $stmt = $conn->prepare("
                INSERT INTO stock_movements (product_id, type, quantity, description)
                VALUES (?, 'adjustment', ?, ?)
            ");
            $stmt->bind_param("iis", $product_id, $final_quantity, $reason);
            $stmt->execute();
            $stmt->close();
            
            // Update product stock
            $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            $stmt->bind_param("ii", $final_quantity, $product_id);
            $stmt->execute();
            $stmt->close();
            
            // Log activity
            logActivity('stock_adjustment', 'products', $product_id, 
                ['old_stock' => $product['stock_quantity']], 
                ['new_stock' => $product['stock_quantity'] + $final_quantity, 'reason' => $reason]
            );
            
            $conn->commit();
            
            setFlashMessage('success', 'Stock adjusted successfully');
            redirect(url('/modules/products/list.php'));
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to adjust stock: ' . $e->getMessage();
        }
    }
    }
}
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-arrow-left-right"></i> Adjust Stock</h1>
        <a href="<?= url('/modules/products/list.php') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Products
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Product: <?= escape($product['name']) ?></h5>
                    <small class="text-muted">SKU: <?= escape($product['sku']) ?></small>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Current Stock:</strong> <?= $product['stock_quantity'] ?> units
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= escape($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <div class="mb-3">
                            <label class="form-label">Adjustment Type <span class="text-danger">*</span></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="adjustment_type" id="add" value="add" checked>
                                <label class="btn btn-outline-success" for="add">
                                    <i class="bi bi-plus-circle"></i> Add Stock
                                </label>

                                <input type="radio" class="btn-check" name="adjustment_type" id="remove" value="remove">
                                <label class="btn btn-outline-danger" for="remove">
                                    <i class="bi bi-dash-circle"></i> Remove Stock
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-lg" id="quantity" 
                                   name="quantity" min="1" required>
                            <div class="invalid-feedback">Please enter quantity</div>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" 
                                      placeholder="e.g., Damaged goods, Stock count correction, Theft, etc." required></textarea>
                            <div class="invalid-feedback">Reason is required for audit trail</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle"></i> Adjust Stock
                            </button>
                            <a href="<?= url('/modules/products/list.php') ?>" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
