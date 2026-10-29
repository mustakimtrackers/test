<?php
/**
 * Point of Sale (POS) Interface
 * Modern, touch-friendly POS system
 */

require_once __DIR__ . '/../../includes/auth_check.php';

// Fetch Active Financial Accounts for Split Payments UI
$accountsQuery = $conn->query("SELECT id, account_name, balance FROM financial_accounts WHERE is_active = 1 ORDER BY account_name");
$financialAccounts = [];
while ($row = $accountsQuery->fetch_assoc()) {
    $financialAccounts[] = $row;
}
$accountsJson = json_encode($financialAccounts);

$pageTitle = 'Point of Sale';
$version = defined('APP_VERSION') ? APP_VERSION : '1.0.0';
$customCSS = url('/assets/css/pos.css?v=' . time());
$customJS = url('/assets/js/pos.js?v=' . time());

require_once __DIR__ . '/../../includes/header.php';
?>

<script>
    // Inject accounts globally for pos.js split payments
    window.financialAccounts = <?= $accountsJson ?>;
</script>

<?php
// Get customers for dropdown
$customersStmt = $conn->query("SELECT id, name, credit_limit, balance FROM contacts WHERE type = 'customer' ORDER BY name");
$customers = $customersStmt->fetch_all(MYSQLI_ASSOC);

// Handle Draft Loading
$draftItemsJson = "[]";
$draftCustomerId = "null";
if (isset($_GET['draft_id'])) {
    $draftId = (int)$_GET['draft_id'];
    $stmt = $conn->prepare("SELECT customer_id FROM sales WHERE id = ? AND status = 'draft'");
    $stmt->bind_param("i", $draftId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($sale = $res->fetch_assoc()) {
        $draftCustomerId = $sale['customer_id'] ? $sale['customer_id'] : "null";
        
        $itemStmt = $conn->prepare("
            SELECT si.product_id as id, p.name, p.image, si.unit_price as selling_price, si.quantity, p.stock_quantity, p.alert_quantity
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = ?
        ");
        $itemStmt->bind_param("i", $draftId);
        $itemStmt->execute();
        $draftItems = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Convert to cart format
        $cartFormat = [];
        foreach($draftItems as $di) {
            $cartFormat[] = [
                'id' => $di['id'],
                'name' => $di['name'],
                'selling_price' => $di['selling_price'],
                'image' => $di['image'],
                'quantity' => (int)$di['quantity'],
                'stock_quantity' => (int)$di['stock_quantity'],
                'alert_quantity' => (int)$di['alert_quantity']
            ];
        }
        $draftItemsJson = json_encode($cartFormat);
    }
}
?>

<!-- Barcode Scanner Library -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<div class="pos-container">
    <!-- Left: Product Grid -->
    <div class="pos-products-section">
        <div class="pos-header d-flex align-items-center gap-2 p-3">
            <div class="search-bar flex-grow-1 position-relative">
                <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                <input type="text" 
                       id="productSearch" 
                       class="form-control form-control-lg ps-5 rounded-pill border-2" 
                       placeholder="Search products..."
                       autocomplete="off">
            </div>

            <button class="btn btn-primary btn-lg rounded-circle shadow-sm p-0 d-none d-md-flex align-items-center justify-content-center" 
                    id="scanBarcodeBtn" 
                    style="width: 48px; height: 48px;"
                    title="Scan Barcode">
                <i class="bi bi-upc-scan fs-5"></i>
            </button>
        </div>
        
        <div class="category-filters" id="categoryFilters">
            <button class="category-btn active" data-category="all">
                <i class="bi bi-grid"></i> All
            </button>
            <!-- Categories will be loaded dynamically -->
        </div>
        
        <div class="products-grid" id="productsGrid">
            <div class="loading-state">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading products...</p>
            </div>
        </div>
    </div>
    
    <!-- Right: Cart & Checkout -->
    <div class="pos-cart-section">
        <div class="cart-header">
            <div class="cart-title-container">
                <h3>
                    <i class="bi bi-cart3"></i> Current Sale
                    <span class="badge bg-primary badge-count" id="cartCount">0</span>
                </h3>
            </div>
        </div>
        
        <!-- Unified Scrollable Area -->
        <div class="cart-scroll-content">
            <div class="cart-items" id="cartItems">
                <div class="empty-cart">
                    <i class="bi bi-cart-x"></i>
                    <p>Cart is empty</p>
                    <small>Add products to start a sale</small>
                </div>
            </div>
            
            <div class="cart-summary-section">
                <!-- Summary Header with Clear Button -->
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <h6 class="m-0 fw-bold text-muted small text-uppercase">Bill Details</h6>
                    <button class="btn btn-sm btn-light text-danger border-0" id="clearCart" style="font-size: 0.85rem;">
                        <i class="bi bi-trash3-fill"></i> Clear Cart
                    </button>
                </div>

                <!-- Customer Selection -->
                <div class="customer-select-wrapper mb-3">
                    <label class="form-label fw-bold"><i class="bi bi-person"></i> Customer (Optional)</label>
                    <select class="form-select form-select-sm select2" id="customerId" style="width: 100%;">
                        <option value="0">Walk-in Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>">
                                <?= escape($customer['name']) ?>
                                <?php if ($customer['balance'] != 0): ?>
                                    (Due: <?= formatCurrency($customer['balance']) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Sale Date (Backdating) -->
                <div class="mb-3">
                    <label for="saleDate" class="form-label fw-bold">
                        <i class="bi bi-calendar-date"></i> Sale Date
                    </label>
                    <input type="datetime-local" class="form-control" id="saleDate" 
                           value="<?= date('Y-m-d\TH:i') ?>">
                </div>
                
                <!-- Calculations -->
                <div class="calculation-row">
                    <span>Subtotal:</span>
                    <span class="amount" id="subtotal">৳0.00</span>
                </div>
                
                <div class="discount-row">
                    <label>
                        <i class="bi bi-percent"></i> Commission:
                    </label>
                    <div class="input-group input-group-sm">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" id="commissionTypeBtn">%</button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="setCommissionType('percentage')">% Percentage</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="setCommissionType('fixed')">৳ Fixed</a></li>
                        </ul>
                        <input type="number" 
                               class="form-control" 
                               id="commissionInput" 
                               placeholder="0.00" 
                               min="0" 
                               step="0.01">
                    </div>
                </div>

                <div class="less-row mt-2">
                    <label>
                        <i class="bi bi-dash-circle"></i> Less:
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">৳</span>
                        <input type="number" 
                               class="form-control" 
                               id="lessInput" 
                               placeholder="0.00" 
                               min="0" 
                               step="0.01">
                    </div>
                    <span class="amount text-danger" id="lessAmount">-৳0.00</span>
                </div>
                
                <div class="tax-row">
                    <label>
                        <i class="bi bi-receipt"></i> Tax:
                    </label>
                    <div class="input-group input-group-sm">
                        <input type="number" 
                               class="form-control" 
                               id="taxPercent" 
                               placeholder="%" 
                               min="0" 
                               max="50"
                               step="0.1"
                               value="<?= getSetting('default_tax_rate', '0') ?>">
                        <span class="input-group-text">%</span>
                    </div>
                    <span class="amount" id="taxAmount">৳0.00</span>
                </div>
                
                <div class="total-row">
                    <span>TOTAL:</span>
                    <span class="total-amount" id="grandTotal">৳0.00</span>
                </div>

                <div class="mt-4">
                    <button class="btn btn-success btn-lg w-100 checkout-btn" id="checkoutBtn" disabled onclick="showCheckoutModal()">
                        <i class="bi bi-check-circle"></i>
                        Checkout / Pay
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Mobile Bottom Navigation -->
    <div class="pos-mobile-nav">
        <button class="pos-nav-item active" id="navProductsBtn">
            <i class="bi bi-grid-3x3-gap"></i>
            <span>Products</span>
        </button>
        <button class="pos-nav-item" id="navCartBtn">
            <i class="bi bi-cart3"></i>
            <span>Cart</span>
            <span class="nav-badge" id="mobileCartCount">0</span>
        </button>
    </div>
</div>

<!-- Mobile Only Barcode Scanner FAB -->
<button class="btn btn-primary d-md-none position-fixed rounded-circle shadow-lg d-flex align-items-center justify-content-center border-0" 
        id="mobileScanBtn" 
        title="Scan Barcode"
        style="z-index: 9998;"
        onclick="document.getElementById('scanBarcodeBtn').click()">
    <i class="bi bi-upc-scan" style="font-size: 24px;"></i>
</button>

<!-- Barcode Scanner Modal -->
<div class="modal fade" id="barcodeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-bottom-sheet modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-upc-scan"></i>
                    Barcode Scanner
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
                <div class="modal-body text-center">
                <div id="scannerFeedback"></div> <!-- Feedback Area -->
                <div id="barcodeReader" style="width: 100%; max-width: 500px; margin: 0 auto;">
                    <p class="text-muted">Camera feed will appear here</p>
                    <p><small>Or enter barcode manually:</small></p>
                    <input type="text" 
                           class="form-control" 
                           id="manualBarcode" 
                           placeholder="Enter SKU or barcode">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Checkout Confirmation Modal -->
<div class="modal fade" id="checkoutModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-bottom-sheet">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-check-circle"></i>
                    Confirm Sale
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="checkoutModalBody">
                <div class="checkout-summary">
                    <div class="summary-row mb-2">
                        <span>Sale Status:</span>
                        <select class="form-select form-select-sm w-auto" id="saleStatus" onchange="posCheckSplitValidation()">
                            <option value="completed">Completed</option>
                            <option value="pending">Pending (Due)</option>
                            <option value="draft">Quotation / Draft</option>
                        </select>
                    </div>
                    <div class="summary-row mb-3">
                        <span>Total Amount:</span>
                        <strong class="text-success fs-5" id="checkoutTotal">৳0.00</strong>
                    </div>
                </div>
                
                <div class="split-payment-section mt-3 border-top pt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold"><i class="bi bi-wallet2"></i> Payment Sources</span>
                        <span class="text-muted small">Change: <b id="posChangeAmount" class="text-success">৳0.00</b></span>
                    </div>
                    
                    <!-- Dynamic Payment Rows Container -->
                    <div id="posPaymentSourcesContainer">
                        <!-- Populated by JS -->
                        <div id="posAccountsLoading" class="text-center p-2 text-muted small"><i class="bi bi-hourglass-split"></i> Loading accounts...</div>
                    </div>
                    
                    <div class="mt-2 d-flex justify-content-between align-items-center">
                        <div id="posSplitValidationMsg" class="small">
                            <!-- Validation message -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="posAddPaymentSource()">
                            <i class="bi bi-plus-circle"></i> Add Payment Source
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex align-items-center justify-content-end gap-2" id="checkoutModalFooter">
                <button type="button" class="btn btn-light border px-4 py-2" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success px-4 py-2 d-flex align-items-center gap-2" id="confirmCheckoutBtn" onclick="processCheckout()">
                    <i class="bi bi-check2 fs-5"></i>
                    Confirm & Print Invoice
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Discount Modal -->
<div class="modal fade" id="discountModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Item Discount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="discountProductId">
                
                <div class="mb-3">
                    <label class="form-label">Discount Type</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="discountType" id="discTypeFixed" value="fixed" checked>
                        <label class="btn btn-outline-primary" for="discTypeFixed">Fixed (৳)</label>
                        
                        <input type="radio" class="btn-check" name="discountType" id="discTypePercent" value="percentage">
                        <label class="btn btn-outline-primary" for="discTypePercent">Percent (%)</label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Discount Value</label>
                    <input type="number" class="form-control form-control-lg" id="discountValue" step="0.01" min="0">
                </div>
            </div>
            <div class="modal-footer d-flex align-items-center justify-content-end gap-2">
                <button type="button" class="btn btn-light border px-4 py-2" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4 py-2" id="applyDiscountBtn">Apply</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Payment Methods Grid */
.payment-methods-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.payment-method-card {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 12px 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    background: white;
}

.payment-method-card:hover {
    border-color: #0d6efd;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.payment-method-card.selected {
    border-color: #198754;
    background: #f0f9ff;
}

.payment-method-card .payment-icon {
    font-size: 24px;
    color: #666;
    display: block;
    margin-bottom: 6px;
}

.payment-method-card.selected .payment-icon {
    color: #198754;
}

.payment-method-card .payment-name {
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.payment-method-card .payment-balance {
    font-size: 11px;
    color: #666;
}

.payment-method-card .payment-check {
    position: absolute;
    top: 4px;
    right: 4px;
    color: #198754;
    font-size: 18px;
    opacity: 0;
    transition: opacity 0.2s;
}

.payment-method-card.selected .payment-check {
    opacity: 1;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const draftItems = <?= $draftItemsJson ?>;
    const draftCustomer = <?= $draftCustomerId ?>;
    
    if (draftItems && draftItems.length > 0) {
        // We simulate adding to cart
        // 'cart' is a global array in pos.js
        if (typeof cart !== 'undefined') {
            draftItems.forEach(item => {
                cart.push({
                    id: item.id,
                    name: item.name,
                    price: parseFloat(item.selling_price),
                    image: item.image,
                    stock: parseInt(item.stock_quantity),
                    quantity: parseInt(item.quantity)
                });
            });
            updateCartUI();
        }
        
        if (draftCustomer) {
            const customerSelect = document.getElementById('customerId');
            if (customerSelect) {
                customerSelect.value = draftCustomer;
                // Trigger change event for select2 if it exists
                if (typeof $ !== 'undefined') {
                    $(customerSelect).trigger('change');
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

