/**
 * POS JavaScript
 * Cart management, checkout logic, and product loading
 */

let cart = [];
let commissionType = 'percentage'; // 'percentage' or 'fixed'
let financialAccounts = []; // Cache accounts for split payment

function setCommissionType(type) {
    commissionType = type;
    const btn = document.getElementById('commissionTypeBtn');
    const input = document.getElementById('commissionInput');

    if (type === 'percentage') {
        btn.textContent = '%';
        input.placeholder = '0.00';
        input.max = 100;
    } else {
        btn.textContent = '৳';
        input.placeholder = '0.00';
        input.removeAttribute('max');
    }
    // Re-apply commission when type changes
    applyProductCommission();
}

document.addEventListener('DOMContentLoaded', async function () {
    loadCategories();
    loadProducts();

    // Accounts are injected directly via PHP into window.financialAccounts in pos.php.

    // Search functionality
    const searchInput = document.getElementById('productSearch');
    searchInput.addEventListener('input', debounce(function () {
        loadProducts(searchInput.value);
    }, 300));

    // Commission (Product Discount) and Less (Global Discount) listeners
    document.getElementById('commissionInput').addEventListener('input', applyProductCommission);
    document.getElementById('lessInput').addEventListener('input', updateTotals);
    document.getElementById('taxPercent').addEventListener('input', updateTotals);

    // Clear cart
    document.getElementById('clearCart').addEventListener('click', function () {
        if (confirm('Clear entire cart?')) {
            cart = [];
            renderCart();
        }
    });

    // Checkout (Event attached directly via HTML onclick)

    // Barcode scanner
    document.getElementById('scanBarcodeBtn').addEventListener('click', openBarcodeScanner);
    document.getElementById('manualBarcode').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            searchBySKU(this.value);
        }
    });

    // Discount Modal Logic
    document.getElementById('applyDiscountBtn').addEventListener('click', applyItemDiscount);

    // Load cart from localStorage
    loadCartFromStorage();

    // Mobile Navigation Logic
    const navProductsBtn = document.getElementById('navProductsBtn');
    const navCartBtn = document.getElementById('navCartBtn');
    const productsSection = document.querySelector('.pos-products-section');
    const cartSection = document.querySelector('.pos-cart-section');

    if (navProductsBtn && navCartBtn) {
        navProductsBtn.addEventListener('click', () => {
            productsSection.style.display = 'flex';
            cartSection.classList.remove('active');
            navProductsBtn.classList.add('active');
            navCartBtn.classList.remove('active');
        });

        navCartBtn.addEventListener('click', () => {
            productsSection.style.display = 'none';
            cartSection.classList.add('active');
            navCartBtn.classList.add('active');
            navProductsBtn.classList.remove('active');
        });
    }
});

// ==========================================
// SPLIT PAYMENT MANAGEMENT FOR POS
// ==========================================

function posAddPaymentSource() {
    const container = document.getElementById('posPaymentSourcesContainer');
    if (!container) return;

    // Remove loading state element if present
    const loadingEl = document.getElementById('posAccountsLoading');
    if (loadingEl) loadingEl.remove();

    // Create Row Wrapper
    const row = document.createElement('div');
    row.className = 'payment-source-row row g-2 mb-2 align-items-end rounded bg-light p-2 border';

    // Account Options
    let accountOptions = '<option value="">Select Account</option>';
    if (window.financialAccounts && window.financialAccounts.length > 0) {
        window.financialAccounts.forEach(ac => {
            accountOptions += `<option value="${ac.id}">${ac.account_name} (৳${parseFloat(ac.balance).toFixed(2)})</option>`;
        });
    }

    row.innerHTML = `
        <div class="col-md-5">
            <label class="form-label small mb-1">Account</label>
            <select class="form-select form-select-sm source-account" required>
                ${accountOptions}
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Method</label>
            <select class="form-select form-select-sm source-method">
                <option value="cash">Cash</option>
                <option value="mobile">Mobile</option>
                <option value="bank">Bank</option>
                <option value="card">Card</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Amount</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text px-1">৳</span>
                <input type="number" class="form-control source-amount" step="0.01" min="0" placeholder="0">
            </div>
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger remove-source-btn" onclick="posRemovePaymentSource(this)" style="display:none;" title="Remove this split">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;

    // Attach listeners
    row.querySelector('.source-amount').addEventListener('input', posCheckSplitValidation);
    row.querySelector('.source-account').addEventListener('change', posCheckSplitValidation);

    // Auto-fill logic based on grandTotal if available
    const grandTotalEl = document.getElementById('grandTotal');
    const totalAmount = parseFloat(grandTotalEl ? grandTotalEl.textContent.replace('৳', '') : 0) || 0;

    const splitInputs = document.querySelectorAll('.source-amount');
    let sumSplit = 0;
    splitInputs.forEach(input => {
        sumSplit += parseFloat(input.value) || 0;
    });

    const newAmountInput = row.querySelector('.source-amount');
    if (totalAmount > sumSplit && container.children.length > 0) {
        newAmountInput.value = (totalAmount - sumSplit).toFixed(2);
    }

    container.appendChild(row);
    posUpdateRemoveButtons();
    posCheckSplitValidation();
}

function posRemovePaymentSource(btn) {
    const row = btn.closest('.payment-source-row');
    if (row && document.querySelectorAll('.payment-source-row').length > 1) {
        row.remove();
        posUpdateRemoveButtons();
        posCheckSplitValidation();
    }
}

function posUpdateRemoveButtons() {
    const rows = document.querySelectorAll('.payment-source-row');
    rows.forEach((row, index) => {
        const btn = row.querySelector('.remove-source-btn');
        if (btn) {
            btn.style.display = rows.length > 1 ? 'inline-block' : 'none';
        }
    });
}

function posCheckSplitValidation() {
    const grandTotalEl = document.getElementById('grandTotal');
    const totalAmount = parseFloat(grandTotalEl ? grandTotalEl.textContent.replace('৳', '') : 0) || 0;

    const splitInputs = document.querySelectorAll('.source-amount');
    let sumSplit = 0;
    let hasEmpty = false;
    splitInputs.forEach(input => {
        const val = parseFloat(input.value);
        if (isNaN(val) || input.value === "") {
            hasEmpty = true;
        } else {
            sumSplit += val;
        }
    });

    const msg = document.getElementById('posSplitValidationMsg');
    const btnSubmit = document.getElementById('confirmCheckoutBtn');
    const changeAmtEl = document.getElementById('posChangeAmount');

    if (!msg) return;

    if (splitInputs.length === 1 && totalAmount > 0) {
        const isEditingAmount = document.activeElement && document.activeElement.classList.contains('source-amount');
        if (!isEditingAmount && splitInputs[0].value === "") {
            splitInputs[0].value = totalAmount;
            sumSplit = totalAmount;
        }
    }

    const saleStatus = document.getElementById('saleStatus') ? document.getElementById('saleStatus').value : 'completed';
    const paymentSection = document.querySelector('.split-payment-section');
    
    if (paymentSection) {
        if (saleStatus === 'draft') {
            paymentSection.style.display = 'none';
        } else {
            paymentSection.style.display = 'block';
        }
    }

    // Calculate Change
    if (sumSplit > totalAmount && saleStatus === 'completed') {
        const change = sumSplit - totalAmount;
        changeAmtEl.textContent = '৳' + change.toFixed(2);
    } else {
        changeAmtEl.textContent = '৳0.00';
    }

    if (saleStatus === 'draft') {
        msg.className = 'text-primary fw-bold small';
        msg.innerHTML = '<i class="bi bi-info-circle"></i> Save as Quotation/Draft';
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="bi bi-save"></i> Save Draft';
        }
        return;
    } else {
        if (btnSubmit) {
            btnSubmit.innerHTML = '<i class="bi bi-check2"></i> Confirm & Print Invoice';
        }
    }

    if (totalAmount === 0) {
        msg.className = 'text-success fw-bold small';
        msg.innerHTML = '<i class="bi bi-check-circle"></i> Ready';
        if (btnSubmit) btnSubmit.disabled = false;
        return;
    }

    if (sumSplit === 0 && hasEmpty) {
        msg.className = 'text-secondary fw-bold small';
        msg.innerHTML = '<i class="bi bi-info-circle"></i> Enter amounts (Due: ৳' + totalAmount.toFixed(2) + ')';
        if (btnSubmit) btnSubmit.disabled = (saleStatus === 'completed');
        return;
    }

    if (Math.abs(totalAmount - sumSplit) <= 0.01) {
        msg.className = 'text-success fw-bold small';
        msg.innerHTML = '<i class="bi bi-check-circle"></i> Exact Amount';
        if (btnSubmit) btnSubmit.disabled = false;
    } else if (sumSplit < totalAmount) {
        msg.className = 'text-warning text-dark fw-bold small';
        msg.innerHTML = '<i class="bi bi-exclamation-circle"></i> Partial: ৳' + sumSplit.toFixed(2) + ' (Due: ৳' + (totalAmount - sumSplit).toFixed(2) + ')';
        if (btnSubmit) btnSubmit.disabled = (saleStatus === 'completed');
    } else {
        msg.className = 'text-primary fw-bold small';
        msg.innerHTML = '<i class="bi bi-info-circle"></i> Excess Given (Change: ৳' + (sumSplit - totalAmount).toFixed(2) + ')';
        if (btnSubmit) btnSubmit.disabled = false;
    }
}

/**
 * Load categories
 */
async function loadCategories() {
    try {
        const response = await fetch('api/get_categories.php');
        const data = await response.json();

        if (data.success) {
            const container = document.getElementById('categoryFilters');
            data.categories.forEach(cat => {
                const btn = document.createElement('button');
                btn.className = 'category-btn';
                btn.dataset.category = cat.id;
                btn.innerHTML = `<i class="bi bi-tag"></i> ${cat.name}`;
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    loadProducts('', this.dataset.category);
                });
                container.appendChild(btn);
            });
        }
    } catch (error) {
        console.error('Failed to load categories:', error);
    }
}

/**
 * Load products
 */
async function loadProducts(search = '', category = 'all') {
    const grid = document.getElementById('productsGrid');
    grid.innerHTML = '<div class="loading-state"><div class="spinner-border text-primary"></div><p>Loading products...</p></div>';

    try {
        const params = new URLSearchParams({ search, category });
        const url = 'api/get_products.php?' + params;
        console.log('Loading products from:', url);

        const response = await fetch(url);
        console.log('Response status:', response.status);

        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

        const data = await response.json();
        console.log('Products data:', data);

        if (data.success && data.products.length > 0) {
            grid.innerHTML = '';
            data.products.forEach(product => {
                const card = createProductCard(product);
                grid.appendChild(card);
            });
        } else {
            grid.innerHTML = `<div class="empty-state">
                <i class="bi bi-box-seam"></i>
                <p>No products found</p>
                <small>${data.products ? data.products.length : 0} products available</small>
            </div>`;
            console.warn('No products found or API error:', data);
        }
    } catch (error) {
        console.error('Failed to load products:', error);
        grid.innerHTML = `<div class="error-state">
            <i class="bi bi-exclamation-triangle text-danger"></i>
            <p>Failed to load products</p>
            <small>${error.message}</small>
            <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadProducts()">Retry</button>
        </div>`;
    }
}

/**
 * Create product card element
 */
function createProductCard(product) {
    const div = document.createElement('div');
    div.className = 'product-card';
    div.innerHTML = `
        <div class="product-image">
            ${product.image ? `<img src="${BASE_PATH}/${product.image}" alt="${product.name}" style="width:100%; height:100%; object-fit:cover;">` : '<i class="bi bi-box"></i>'}
        </div>
        <div class="product-name">${product.name}</div>
        <div class="product-price">৳${parseFloat(product.selling_price).toFixed(2)}</div>
        <div class="product-stock ${product.stock_quantity <= product.alert_quantity ? 'low' : ''}">
            ${product.stock_quantity} in stock
        </div>
    `;

    div.addEventListener('click', () => addToCart(product));

    return div;
}

/**
 * Add product to cart
 */
function addToCart(product) {
    if (product.stock_quantity <= 0) {
        showToast('error', 'Product is out of stock');
        return;
    }

    const existingItem = cart.find(item => item.id === product.id);

    if (existingItem) {
        if (existingItem.quantity >= product.stock_quantity) {
            showToast('warning', 'Cannot exceed available stock');
            return;
        }
        existingItem.quantity++;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: parseFloat(product.selling_price),
            quantity: 1,
            max_stock: product.stock_quantity,
            discountType: 'fixed',
            discountValue: 0
        });
    }

    // If commission details are present in inputs, apply them to new item too?
    // Or just apply the current commission settings from input to the new item immediately
    const commissionInputVal = parseFloat(document.getElementById('commissionInput').value) || 0;
    if (commissionInputVal > 0) {
        const newItem = existingItem || cart[cart.length - 1];
        newItem.discountType = commissionType;
        newItem.discountValue = commissionInputVal;
    }

    renderCart();
    saveCartToStorage();

    // Visual feedback
    if (typeof showToast === 'function') {
        showToast('success', `${product.name} added to cart`);
    }
}

/**
 * Remove from cart
 */
function removeFromCart(productId) {
    cart = cart.filter(item => item.id != productId); // Loose equality
    renderCart();
    saveCartToStorage();
}

/**
 * Update quantity
 */
function updateQuantity(productId, delta) {
    const item = cart.find(item => item.id == productId); // Loose equality for string/int IDs
    if (!item) return;

    // Ensure numeric type
    let currentQty = parseInt(item.quantity) || 0;
    const newQty = currentQty + delta;

    if (newQty <= 0) {
        removeFromCart(productId);
    } else if (newQty <= item.max_stock) {
        item.quantity = newQty;
        renderCart();
        saveCartToStorage();
    } else {
        showToast('warning', 'Cannot exceed available stock');
    }
}

/**
 * Update quantity manually from input
 */
function updateQuantityManual(productId, newQty) {
    const item = cart.find(item => item.id == productId); // Loose equality
    if (!item) return;

    let parsedQty = parseInt(newQty);

    if (isNaN(parsedQty) || parsedQty < 1) {
        parsedQty = 1; // Default to 1 if invalid
    }

    if (parsedQty > item.max_stock) {
        showToast('warning', `Use max available stock: ${item.max_stock}`);
        parsedQty = item.max_stock;
    }

    item.quantity = parsedQty;
    renderCart(); // Re-render to ensure UI reflects valid state
    saveCartToStorage();
}

/**
 * Render cart
 */
function renderCart() {
    const cartItems = document.getElementById('cartItems');
    const countBadge = document.getElementById('cartCount');
    const mobileBadge = document.getElementById('mobileCartCount');

    // Update counts
    const totalCount = cart.reduce((sum, item) => sum + item.quantity, 0);
    countBadge.textContent = totalCount;
    if (mobileBadge) mobileBadge.textContent = totalCount;

    if (cart.length === 0) {
        cartItems.innerHTML = `
            <div class="empty-cart">
                <i class="bi bi-cart-x"></i>
                <p>Cart is empty</p>
                <small>Add products to start a sale</small>
            </div>`;
        updateTotals();
        document.getElementById('checkoutBtn').disabled = true;
        return;
    }

    cartItems.innerHTML = cart.map((item, index) => `
        <div class="cart-item">
            <!-- Top Row: Name and Delete -->
            <div class="cart-item-top">
                <div class="cart-item-name">${item.name}</div>
                <button class="btn-remove" onclick="removeFromCart(${item.id})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            
            <!-- Bottom Row: Controls and Price -->
            <div class="cart-item-bottom d-flex justify-content-between align-items-center mt-2">
                <!-- Quantity Controls -->
                <div class="quantity-controls d-flex align-items-center bg-light rounded-pill border p-1">
                    <button class="btn btn-sm btn-white rounded-circle shadow-sm text-primary d-flex align-items-center justify-content-center" 
                            style="width: 28px; height: 28px; padding: 0;"
                            onclick="updateQuantity(${item.id}, -1)">
                        <i class="bi bi-dash fw-bold"></i>
                    </button>
                    
                    <input type="number" class="quantity-input text-center border-0 bg-transparent fw-bold text-dark mx-2" 
                           value="${item.quantity}" 
                           min="1" max="${item.max_stock}"
                           onchange="updateQuantityManual(${item.id}, this.value)"
                           onclick="this.select()"
                           style="width: 45px; font-size: 15px; outline: none; -moz-appearance: textfield;">
                           
                    <button class="btn btn-sm btn-white rounded-circle shadow-sm text-primary d-flex align-items-center justify-content-center" 
                            style="width: 28px; height: 28px; padding: 0;"
                            onclick="updateQuantity(${item.id}, 1)">
                        <i class="bi bi-plus fw-bold"></i>
                    </button>
                </div>
                
                <!-- Pricing Details -->
                <div class="item-price text-end">
                    <div class="d-flex flex-column align-items-end gap-1">
                        <div class="input-group input-group-sm" style="width: 100px;">
                            <span class="input-group-text bg-light border-end-0 px-2 text-muted">৳</span>
                            <input type="number" class="form-control form-control-sm text-end border-start-0 ps-0 fw-semibold" 
                                   value="${item.price}" 
                                   min="0" step="0.01"
                                   onchange="updateCartItemPrice(${item.id}, this.value)"
                                   onclick="this.select()"
                                   style="box-shadow: none;">
                        </div>
                        <div class="text-primary fw-bold" style="font-size: 15px;">
                            Total: ৳${((item.price * item.quantity) - calculateItemDiscount(item)).toFixed(2)}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    document.getElementById('checkoutBtn').disabled = false;
    updateTotals();
}

/**
 * Update totals
 */
function updateTotals() {
    // Calculate subtotal (Item discounts/commissions are already applied to item.price * quantity)
    const subtotal = cart.reduce((sum, item) => sum + ((item.price * item.quantity) - calculateItemDiscount(item)), 0);

    const lessInputVal = parseFloat(document.getElementById('lessInput').value) || 0;
    const taxPercent = parseFloat(document.getElementById('taxPercent').value) || 0;

    // Cap "Less" at subtotal
    let lessAmount = lessInputVal;
    if (lessAmount > subtotal) {
        lessAmount = subtotal;
    }

    const afterLess = subtotal - lessAmount;
    const taxAmount = afterLess * (taxPercent / 100);
    const grandTotal = afterLess + taxAmount;

    document.getElementById('subtotal').textContent = '৳' + subtotal.toFixed(2);
    document.getElementById('lessAmount').textContent = '-৳' + lessAmount.toFixed(2);
    document.getElementById('taxAmount').textContent = '৳' + taxAmount.toFixed(2);
    document.getElementById('grandTotal').textContent = '৳' + grandTotal.toFixed(2);
}

/**
 * Show checkout modal
 */
function showCheckoutModal() {
    if (cart.length === 0) {
        showToast('error', 'Cart is empty!');
        return;
    }

    const grandTotal = document.getElementById('grandTotal').textContent;
    const checkoutTotalId = document.getElementById('checkoutTotal');
    if (checkoutTotalId) {
        checkoutTotalId.textContent = grandTotal;
    }

    // Auto add a payment row if none exists
    const container = document.getElementById('posPaymentSourcesContainer');
    if (container && container.children.length === 0) {
        posAddPaymentSource();
    } else {
        // Trigger validation to update change return and button state
        posCheckSplitValidation();
    }

    const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
    modal.show();
}

async function processCheckout() {
    if (cart.length === 0) {
        showToast('error', 'Cart is empty!');
        return;
    }

    // Calculate totals
    const subtotal = cart.reduce((sum, item) => sum + ((item.price * item.quantity) - calculateItemDiscount(item)), 0);
    const lessInputVal = parseFloat(document.getElementById('lessInput').value) || 0;
    const taxPercent = parseFloat(document.getElementById('taxPercent').value) || 0;

    let lessAmount = lessInputVal;
    if (lessAmount > subtotal) {
        lessAmount = subtotal;
    }

    const taxAmount = (subtotal - lessAmount) * (taxPercent / 100);
    const grandTotal = subtotal - lessAmount + taxAmount;

    // Gather Split Payments Data
    const splitPayments = [];
    let sumPaid = 0;
    let hasInvalidAccount = false;
    let hasInvalidAmount = false;

    document.querySelectorAll('.payment-source-row').forEach(row => {
        const accId = row.querySelector('.source-account').value;
        const method = row.querySelector('.source-method').value;
        const amt = parseFloat(row.querySelector('.source-amount').value) || 0;

        if (amt > 0) {
            if (!accId) hasInvalidAccount = true;
            splitPayments.push({
                account_id: parseInt(accId),
                method: method,
                amount: amt
            });
            sumPaid += amt;
        } else if (amt < 0) {
            hasInvalidAmount = true;
        }
    });

    const status = document.getElementById('saleStatus').value;

    if (hasInvalidAmount) {
        showToast('error', 'Payment amounts cannot be negative.');
        return;
    }
    if (hasInvalidAccount) {
        showToast('error', 'Please select an account for all entered payments.');
        return;
    }
    if (status === 'completed' && sumPaid < grandTotal) {
        showToast('error', 'Full payment is required for Completed sales.');
        return;
    }

    const saleData = {
        customer_id: document.getElementById('customerId').value || null,
        sale_date: document.getElementById('saleDate').value,
        items: cart,
        subtotal,
        discount_percent: 0,
        discount_amount: lessAmount, // Stores the 'Less' amount (Global Discount)
        tax_percent: taxPercent,
        tax_amount: taxAmount,
        total_amount: grandTotal,
        split_payments: splitPayments,
        status: status || 'completed'
    };

    console.log('Processing checkout:', saleData);

    try {
        const response = await fetch('api/process_sale.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(saleData)
        });

        console.log('Checkout response status:', response.status);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

        const data = await response.json();
        console.log('Checkout response data:', data);

        if (data.success) {
            // Show the modal implicitly on success
            const modalEl = document.getElementById('checkoutModal');
            const successModal = new bootstrap.Modal(modalEl);
            successModal.show();

            // Success State
            const modalBody = modalEl.querySelector('.modal-body');
            const modalHeader = modalEl.querySelector('.modal-header');

            // Change Header to Success
            modalHeader.classList.remove('bg-success'); // Reset
            modalHeader.style.background = '#10b981'; // Green
            modalHeader.innerHTML = `
                <h5 class="modal-title text-white">
                    <i class="bi bi-check-circle-fill"></i> Sale Completed!
                </h5>
                <button type="button" class="btn-close btn-close-white" onclick="resetPOS()"></button>
            `;

            // New Body Content
            modalBody.innerHTML = `
                <div class="text-center py-4">
                    <div class="success-icon-wrapper">
                        <i class="bi bi-printer"></i>
                    </div>
                    <h4>Invoice Ready</h4>
                    <p class="text-muted">Sale ID: #${data.data.sale_id || '---'}</p>
                    
                    <div class="d-grid gap-3 mt-4" style="max-width: 300px; margin: 0 auto;">
                        <a href="${BASE_PATH}/modules/sales/invoice.php?id=${data.data.sale_id}" 
                           class="btn btn-primary btn-lg shadow-sm">
                            <i class="bi bi-printer-fill me-2"></i> Print Invoice
                        </a>
                        <button onclick="resetPOS()" 
                                class="btn btn-outline-secondary">
                            <i class="bi bi-plus-circle me-2"></i> New Sale
                        </button>
                    </div>
                </div>
            `;

            // Hide Footer (if exists)
            const modalFooter = modalEl.querySelector('.modal-footer');
            if (modalFooter) modalFooter.style.display = 'none';

            // Clear Cart Data silently
            cart = [];
            localStorage.removeItem('pos_cart');
            renderCart();

            // Reset input fields
            resetPOSInputs();

        } else {
            console.error('Checkout failed:', data);
            showToast('error', data.message || 'Failed to process sale');
        }
    } catch (error) {
        console.error('Checkout error:', error);
        showToast('error', 'Failed to process sale: ' + error.message);
    }
}

/**
 * Cart storage
 */
function saveCartToStorage() {
    localStorage.setItem('pos_cart', JSON.stringify(cart));
}

function loadCartFromStorage() {
    const saved = localStorage.getItem('pos_cart');
    if (saved) {
        cart = JSON.parse(saved);
        renderCart();
    }
}

/**
 * Barcode scanner
 */
let html5QrcodeScanner = null;

function openBarcodeScanner() {
    const modalEl = document.getElementById('barcodeModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    // Initialize scanner when modal is fully shown
    modalEl.addEventListener('shown.bs.modal', function () {
        if (!html5QrcodeScanner) {
            html5QrcodeScanner = new Html5QrcodeScanner(
                "barcodeReader",
                { fps: 10, qrbox: { width: 250, height: 250 } },
                /* verbose= */ false
            );
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        }
    }, { once: true });

    // Cleanup when modal is hidden
    modalEl.addEventListener('hidden.bs.modal', function () {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear().catch(error => console.error("Failed to clear scanner", error));
            html5QrcodeScanner = null;
            // Reset UI
            document.getElementById('barcodeReader').innerHTML = '<p class="text-muted">Camera feed will appear here</p><p><small>Or enter barcode manually:</small></p><input type="text" class="form-control" id="manualBarcode" placeholder="Enter SKU or barcode">';
            // Re-attach event listener for manual input
            document.getElementById('manualBarcode').addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    searchBySKU(this.value);
                }
            });
        }
    }, { once: true });
}

function onScanSuccess(decodedText, decodedResult) {
    if (html5QrcodeScanner.isScanning) { // custom flag to prevent double processing
        return;
    }
    html5QrcodeScanner.isScanning = true;

    // Visual & Audio Feedback
    playBeep();

    // Process the scan
    searchBySKU(decodedText).then((product) => {
        const feedbackEl = document.getElementById('scannerFeedback');
        if (product) {
            feedbackEl.innerHTML = `<div class="alert alert-success py-1 mb-2 fw-bold"><i class="bi bi-check-circle"></i> Added: ${product.name}</div>`;
        } else {
            feedbackEl.innerHTML = `<div class="alert alert-danger py-1 mb-2 fw-bold"><i class="bi bi-x-circle"></i> Product Not Found</div>`;
        }

        // Reset flag after 1.5s to allow next scan
        setTimeout(() => {
            feedbackEl.innerHTML = '';
            html5QrcodeScanner.isScanning = false;
        }, 1500);
    });
}

function onScanFailure(error) {
    // console.warn(`Code scan error = ${error}`);
}

// Beep Sound (Base64) - function for oscillator beep
function playBeep() {
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioCtx.createOscillator();
    const gainNode = audioCtx.createGain();

    oscillator.connect(gainNode);
    gainNode.connect(audioCtx.destination);

    oscillator.type = "sine";
    oscillator.frequency.setValueAtTime(800, audioCtx.currentTime); // 800Hz
    gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);

    oscillator.start();
    gainNode.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + 0.1);
    oscillator.stop(audioCtx.currentTime + 0.1);
}

async function searchBySKU(sku) {
    if (!sku) return null;

    try {
        const response = await fetch(`api/get_product_by_sku.php?sku=${encodeURIComponent(sku)}`);
        const data = await response.json();

        if (data.success) {
            // Handle nested data structure from jsonResponse wrapper
            const product = data.data ? data.data.product : data.product;

            if (product) {
                addToCart(product);
                // Don't close modal anymore
                // Don't clear manual input if we are in camera mode (optional)
                const manualInput = document.getElementById('manualBarcode');
                if (manualInput) {
                    manualInput.value = '';
                }
                return product;
            } else {
                return null;
            }
        } else {
            // showToast('error', 'Product not found'); // handled by inline feedback now
            return null;
        }
    } catch (error) {
        console.error('SKU search error:', error);
        return null;
    }
}

/**
 * Edit cart item price
 */
function editCartItemPrice(productId) {
    const item = cart.find(i => i.id === productId);
    if (!item) return;

    const newPrice = prompt(
        `Edit price for: ${item.name}\nCurrent price: ৳${item.price.toFixed(2)}\n\nEnter new price:`,
        item.price.toFixed(2)
    );

    if (newPrice === null) return; // Cancelled

    const parsedPrice = parseFloat(newPrice);

    if (isNaN(parsedPrice) || parsedPrice < 0) {
        showToast('error', 'Invalid price');
        return;
    }

    if (parsedPrice === 0) {
        if (!confirm('Set price to ৳0.00? This will make the item free.')) {
            return;
        }
    }

    // Update price
    item.price = parsedPrice;
    renderCart();
    saveCartToStorage();

    showToast('success', `Price updated to ৳${parsedPrice.toFixed(2)}`);
}

/**
 * Reset POS for new sale
 */
function resetPOS() {
    window.location.reload();
}

function resetPOSInputs() {
    document.getElementById('customerId').value = '';
    document.getElementById('commissionInput').value = '';
    document.getElementById('lessInput').value = '';
    setCommissionType('percentage');
    document.getElementById('taxPercent').value = '0';
    document.getElementById('manualBarcode').value = '';
}

/**
 * Load Payment Methods (Financial Accounts)
 */
function loadPaymentMethods() {
    const grid = document.getElementById('paymentMethodsGrid');

    fetch(`${BASE_PATH}/modules/sales/api/get_payment_methods.php`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.accounts.length > 0) {
                grid.innerHTML = '';
                data.accounts.forEach(account => {
                    const card = createPaymentMethodCard(account);
                    grid.appendChild(card);
                });

                // Auto-select first method
                const firstCard = grid.querySelector('.payment-method-card');
                if (firstCard) {
                    firstCard.click();
                }
            } else {
                grid.innerHTML = '<div class="alert alert-warning mb-0"><small>No active payment methods found. Please add a financial account.</small></div>';
            }
        })
        .catch(err => {
            console.error('Error loading payment methods:', err);
            grid.innerHTML = '<div class="alert alert-danger mb-0"><small>Failed to load payment methods</small></div>';
        });
}

/**
 * Create payment method card element
 */
function createPaymentMethodCard(account) {
    const card = document.createElement('div');
    card.className = 'payment-method-card';
    card.dataset.accountId = account.id;
    card.dataset.type = account.account_type;

    const icons = {
        'cash': 'cash-stack',
        'bank': 'bank',
        'mobile_banking': 'phone'
    };
    const icon = icons[account.account_type] || 'wallet2';

    card.innerHTML = `
        <i class="bi bi-${icon} payment-icon"></i>
        <div class="payment-name">${account.account_name}</div>
        <div class="payment-balance">৳${parseFloat(account.balance).toFixed(2)}</div>
        <div class="payment-check"><i class="bi bi-check-circle-fill"></i></div>
    `;

    card.addEventListener('click', function () {
        selectPaymentMethod(this);
    });

    return card;
}

/**
 * Select payment method
 */
function selectPaymentMethod(card) {
    // Remove selection from all cards
    document.querySelectorAll('.payment-method-card').forEach(c => {
        c.classList.remove('selected');
    });

    // Select this card
    card.classList.add('selected');

    // Store selected account ID
    document.getElementById('selectedAccountId').value = card.dataset.accountId;
    document.getElementById('paymentMethodError').classList.add('d-none');

    // Update global payment method based on account type
    const typeMap = {
        'cash': 'cash',
        'bank': 'card',
        'mobile_banking': 'mobile'
    };
    selectedPaymentMethod = typeMap[card.dataset.type] || 'cash';
    console.log('Payment method set to:', selectedPaymentMethod);
}

/**
 * Update cart item price directly from input
 */
function updateCartItemPrice(productId, newPrice) {
    const item = cart.find(i => i.id === productId);
    if (!item) return;

    const price = parseFloat(newPrice);

    if (isNaN(price) || price < 0) {
        showToast('error', 'Invalid price');
        renderCart(); // Reset to valid state
        return;
    }

    item.price = price;
    renderCart();
    saveCartToStorage();
}

/**
 * Apply Product Commission (Bulk Item Discount)
 * Updates ALL items with the specified % or Fixed amount
 */
function applyProductCommission() {
    const inputVal = parseFloat(document.getElementById('commissionInput').value) || 0;

    // Validation
    let val = inputVal;
    if (commissionType === 'percentage') {
        if (val > 100) val = 100;
    }
    if (val < 0) val = 0;

    // Update all items
    cart.forEach(item => {
        item.discountType = commissionType;
        item.discountValue = val;
    });

    // Render to show per-item changes and update totals
    renderCart();
}

/**
 * Item Discount Logic
 */
function openDiscountModal(productId) {
    const item = cart.find(i => i.id === productId);
    if (!item) return;

    document.getElementById('discountProductId').value = productId;
    document.getElementById('discountValue').value = item.discountValue || 0;

    // Set Radio
    const type = item.discountType || 'fixed';
    document.querySelector(`input[name="discountType"][value="${type}"]`).checked = true;

    new bootstrap.Modal(document.getElementById('discountModal')).show();
}

function applyItemDiscount() {
    const productId = parseInt(document.getElementById('discountProductId').value);
    const item = cart.find(i => i.id === productId);

    if (item) {
        const type = document.querySelector('input[name="discountType"]:checked').value;
        const value = parseFloat(document.getElementById('discountValue').value) || 0;

        // Validation
        if (value < 0) {
            showToast('error', 'Discount cannot be negative');
            return;
        }

        if (type === 'percentage' && value > 100) {
            showToast('error', 'Percentage cannot exceed 100%');
            return;
        }

        item.discountType = type;
        item.discountValue = value;

        renderCart();
        saveCartToStorage();

        // Close Modal
        bootstrap.Modal.getInstance(document.getElementById('discountModal')).hide();
        showToast('success', 'Discount applied');
    }
}

function calculateItemDiscount(item) {
    if (!item.discountValue || item.discountValue <= 0) return 0;

    // Line Total (Price * Qty)
    const lineTotal = item.price * item.quantity;

    if (item.discountType === 'percentage') {
        return lineTotal * (item.discountValue / 100);
    } else {
        // Fixed Discount on Line Total (treated as per-unit if specified? or flat deduction?)
        // The user asked for "per product" commission. 
        // If fixed "10tk", is it 10tk off the line total? Or 10tk off EACH unit?
        // Standard commission/product discount usually implies Per Unit.
        // Let's implement as Per Unit: (Discount * Qty).
        return item.discountValue * item.quantity;
    }
}

// Helper: Debounce function
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        const context = this;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}


