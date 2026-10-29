<?php
/**
 * Product List with modern table design
 */

// Start output buffering to prevent header issues
ob_start();

$pageTitle = 'Products';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Search and filter
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$stockFilter = isset($_GET['stock']) ? $_GET['stock'] : '';
$branchFilter = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($_SESSION['branch_id'] ?? 1);

// Force non-admins to their own branch
if (!isAdmin()) {
    $branchFilter = $_SESSION['branch_id'] ?? 1;
}

// Build query
$whereConditions = ["p.is_active = 1"];
$params = [];
$types = '';

if (!empty($search)) {
    $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

if ($categoryFilter > 0) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $categoryFilter;
    $types .= 'i';
}

if ($stockFilter === 'low') {
    $whereConditions[] = "p.stock_quantity <= p.alert_quantity";
} elseif ($stockFilter === 'out') {
    $whereConditions[] = "p.stock_quantity = 0";
}

$whereSQL = implode(' AND ', $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM products p WHERE $whereSQL";
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalProducts = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// Get products
$query = "
    SELECT 
        p.*,
        COALESCE(ps.quantity, 0) as display_stock,
        c.name as category_name,
        u.short_name as unit_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN units u ON p.unit_id = u.id
    LEFT JOIN product_stocks ps ON p.id = ps.product_id AND ps.branch_id = ?
    WHERE $whereSQL
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$paramsWithLimit = array_merge([$branchFilter], $params, [$perPage, $offset]);
$typesWithLimit = 'i' . $types . 'ii';
$stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
$stmt->execute();
$result = $stmt->get_result();

// Get categories for filter
$categoriesStmt = $conn->query("SELECT id, name FROM categories ORDER BY name");
$categories = $categoriesStmt->fetch_all(MYSQLI_ASSOC);

// Get suppliers for filter
$suppliersStmt = $conn->query("SELECT id, name FROM contacts WHERE type = 'supplier' ORDER BY name");
$suppliers = $suppliersStmt->fetch_all(MYSQLI_ASSOC);

$pagination = getPagination($totalProducts, $page, $perPage);
?>

<div class="container-fluid">
    <div class="page-header mb-4">
        <h1 class="page-title">
            <i class="bi bi-box-seam"></i> Products
        </h1>
        <div class="page-actions">
            <!-- Desktop Add Button -->
            <a href="<?= url('/modules/products/create.php') ?>" class="btn btn-primary d-none d-md-inline-flex align-items-center gap-2 px-4 rounded-pill shadow-sm">
                <i class="bi bi-plus-lg"></i> Add Product
            </a>
        </div>
    </div>
    
    <!-- Mobile Floating Action Button -->
    <a href="<?= url('/modules/products/create.php') ?>" id="mobileFabBtn" class="btn btn-primary d-md-none position-fixed rounded-circle shadow-lg d-flex align-items-center justify-content-center border-0" style="bottom: 30px; right: 24px; width: 64px; height: 64px; z-index: 1050;">
        <i class="bi bi-plus-lg" style="font-size: 2rem;"></i>
    </a>
    
    <!-- Filters & UI Controls -->
    <div class="card mb-4 border-0 shadow-sm rounded-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-3">
                
                <!-- Search & Mobile View Toggle Row -->
                <div class="col-12 d-flex gap-2">
                    <div class="search-box position-relative flex-grow-1">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        <input type="text" class="form-control form-control-lg bg-light border-0 ps-5 rounded-pill" name="search" placeholder="Search by name or SKU..." value="<?= escape($search) ?>">
                    </div>
                    
                    <!-- Mobile View Toggle (Inside search row for better UI) -->
                    <div class="btn-group d-md-none bg-light rounded-pill p-1 border shadow-sm align-items-center" role="group">
                        <button type="button" class="btn btn-sm btn-outline-primary border-0 rounded-pill active" id="btnListView" onclick="setMobileView('list')"><i class="bi bi-list-ul fs-5"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-primary border-0 rounded-pill" id="btnCardView" onclick="setMobileView('card')"><i class="bi bi-grid fs-5"></i></button>
                    </div>
                </div>

                <div class="col-md-3">
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                <?= escape($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="stock" class="form-select">
                        <option value="">All Stock Status</option>
                        <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                <?php if (isAdmin()): ?>
                <div class="col-md-3 mt-3 mt-md-0">
                    <select name="branch_id" class="form-select" onchange="this.form.submit()">
                        <?php 
                        $bRes = $conn->query("SELECT id, name FROM branches");
                        while ($bRow = $bRes->fetch_assoc()):
                        ?>
                            <option value="<?= $bRow['id'] ?>" <?= $branchFilter == $bRow['id'] ? 'selected' : '' ?>>
                                <?= escape($bRow['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-secondary w-100 h-100 rounded-3">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Products Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="dataTable">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th class="text-end">Purchase Price</th>
                            <th class="text-end">Selling Price</th>
                            <th class="text-center">Stock</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($product = $result->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Product" class="py-2">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="product-thumb shadow-sm">
                                        <?php if (!empty($product['image'])): ?>
                                            <img src="<?= url($product['image']) ?>" alt="Product" class="w-100 h-100 object-fit-cover rounded">
                                        <?php else: ?>
                                            <i class="bi bi-image"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong class="d-block text-dark"><?= escape($product['name']) ?></strong>
                                        <?php if ($product['description']): ?>
                                            <small class="text-muted d-none d-md-block"><?= escape(substr($product['description'], 0, 50)) ?>...</small>
                                        <?php endif; ?>
                                        <small class="d-md-none text-muted">SKU: <?= escape($product['sku']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="SKU" class="d-none d-md-table-cell align-middle"><code><?= escape($product['sku']) ?></code></td>
                            <td data-label="Category" class="d-none d-md-table-cell align-middle">
                                <?= $product['category_name'] ? escape($product['category_name']) : '<span class="text-muted">N/A</span>' ?>
                            </td>
                            <td data-label="Supplier" class="d-none d-md-table-cell align-middle">
                                <?= $product['supplier_name'] ? '<span class="badge bg-info">' . escape($product['supplier_name']) . '</span>' : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td data-label="Cost Price" class="d-none d-md-table-cell align-middle"><?= formatCurrency($product['cost_price']) ?></td>
                            <td data-label="Price" class="align-middle fw-bold"><?= formatCurrency($product['selling_price']) ?></td>
                            <td data-label="Stock" class="align-middle">
                                <?php
                                $stockClass = 'success';
                                if ($product['display_stock'] == 0) {
                                    $stockClass = 'danger';
                                } elseif ($product['display_stock'] <= $product['alert_quantity']) {
                                    $stockClass = 'warning';
                                }
                                ?>
                                <span class="badge bg-<?= $stockClass ?> px-2 py-1">
                                    <?= $product['display_stock'] ?> <?= escape($product['unit_name'] ?? 'pc') ?>
                                </span>
                                <?php if (isAdmin() && $branchFilter > 1): ?>
                                    <br><small class="text-muted" style="font-size:0.65rem;">Stock for Branch #<?= $branchFilter ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status" class="d-none d-md-table-cell align-middle">
                                <?= $product['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?>
                            </td>
                            <td data-label="Actions" class="align-middle text-end">
                                <div class="btn-group btn-group-sm">
                                    <?php if (isAdmin()): ?>
                                    <a href="<?= url('/modules/products/edit.php?id=' . $product['id']) ?>" class="btn btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="<?= url('/modules/products/barcode.php?id=' . $product['id']) ?>" class="btn btn-outline-dark d-none d-md-inline-block" title="Barcode">
                                        <i class="bi bi-upc-scan"></i>
                                    </a>
                                    <?php if (isAdmin()): ?>
                                    <button class="btn btn-outline-danger" onclick="deleteProduct(<?= $product['id'] ?>)" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if ($result->num_rows == 0): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bi bi-inbox text-muted" style="font-size: 48px;"></i>
                                <p class="text-muted mt-3">No products found</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?= renderPaginationUI($pagination) ?>
        </div>
    </div>
</div>

<style>
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text-primary);
}

.product-thumb {
    width: 50px;
    height: 50px;
    background: var(--bg-tertiary);
    border-radius: var(--border-radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: var(--text-tertiary);
}
</style>

<script>
function deleteProduct(id) {
    if (confirm('Are you sure you want to delete this product?')) {
        window.location.href = BASE_PATH + '/modules/products/delete.php?id=' + id;
    }
}

// Mobile View Toggle Logic
function setMobileView(view) {
    const table = document.getElementById('dataTable');
    if (!table) return;
    
    const btnList = document.getElementById('btnListView');
    const btnCard = document.getElementById('btnCardView');
    
    if(view === 'card') {
        table.classList.add('table-responsive-card');
        if(btnCard) btnCard.classList.add('active');
        if(btnList) btnList.classList.remove('active');
        localStorage.setItem('products_view_pref', 'card');
    } else {
        table.classList.remove('table-responsive-card');
        if(btnList) btnList.classList.add('active');
        if(btnCard) btnCard.classList.remove('active');
        localStorage.setItem('products_view_pref', 'list');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if(window.innerWidth < 768) {
        // Default to list if not set
        let pref = localStorage.getItem('products_view_pref') || 'list';
        setMobileView(pref);
    }
});
</script>

<?php
$stmt->close();
require_once __DIR__ . '/../../includes/footer.php';
?>
