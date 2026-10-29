<?php
/**
 * Sales List
 * View all sales transactions
 */


$pageTitle = 'Sales History';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build Filter Query
$where = ["1=1"];
$params = [];
$types = "";

if (!empty($_GET['start_date'])) {
    $where[] = "s.sale_date >= ?";
    $params[] = $_GET['start_date'];
    $types .= "s";
}

if (!empty($_GET['end_date'])) {
    $where[] = "s.sale_date <= ?";
    $params[] = $_GET['end_date'] . ' 23:59:59';
    $types .= "s";
}

if (!empty($_GET['status'])) {
    $where[] = "s.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

// Branch Isolation
if (!isAdmin()) {
    $where[] = "s.branch_id = ?";
    $params[] = $_SESSION['branch_id'] ?? 1;
    $types .= "i";
}

$whereSQL = implode(' AND ', $where);

// Get total count with filters
$countQuery = "SELECT COUNT(*) as total FROM sales s WHERE $whereSQL";
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalSales = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$pagination = getPagination($totalSales, $page, $perPage);
$offset = $pagination['offset']; // override offset based on max pages
// Get sales with filters
$query = "
    SELECT 
        s.id,
        s.reference_no,
        s.sale_date,
        s.total_amount,
        s.paid_amount,
        s.status,
        c.name as customer_name,
        u.name as user_name,
        b.name as branch_name,
        GROUP_CONCAT(CONCAT(p.name, ' (', si.quantity, ')') SEPARATOR ', ') as product_details
    FROM sales s
    LEFT JOIN contacts c ON s.customer_id = c.id
    LEFT JOIN users u ON s.created_by = u.id
    LEFT JOIN branches b ON s.branch_id = b.id
    LEFT JOIN sale_items si ON s.id = si.sale_id
    LEFT JOIN products p ON si.product_id = p.id
    WHERE $whereSQL
    GROUP BY s.id
    ORDER BY s.sale_date DESC, s.id DESC
    LIMIT ? OFFSET ?
";

// Add pagination params
$types .= "ii";
$params[] = $perPage;
$params[] = $offset;

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$sales = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container-fluid">
    <div class="page-header mb-4">
        <h1 class="page-title">
            <i class="bi bi-receipt"></i> Sales History
        </h1>
        <div class="page-actions">
            <!-- Desktop Add Button -->
            <a href="<?= url('/modules/sales/pos.php') ?>" class="btn btn-primary d-none d-md-inline-flex align-items-center gap-2 px-4 rounded-pill shadow-sm">
                <i class="bi bi-cart-check"></i> New Sale
            </a>
        </div>
    </div>
    
    <!-- Mobile Floating Action Button -->
    <a href="<?= url('/modules/sales/pos.php') ?>" id="mobileFabBtn" class="btn btn-primary d-md-none position-fixed rounded-circle shadow-lg d-flex align-items-center justify-content-center border-0" style="bottom: 30px; right: 24px; width: 64px; height: 64px; z-index: 1050;">
        <i class="bi bi-cart-check" style="font-size: 2rem;"></i>
    </a>

    <!-- Filters & UI Controls -->
    <div class="card mb-4 border-0 shadow-sm rounded-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-3 align-items-end">
                
                <!-- Search & Mobile View Toggle Row -->
                <div class="col-12 d-flex gap-2">
                    <div class="search-box position-relative flex-grow-1">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        <input type="text" id="salesSearch" class="form-control form-control-lg bg-light border-0 ps-5 rounded-pill" placeholder="Live search sales...">
                    </div>
                    
                    <!-- Mobile View Toggle (Inside search row for better UI) -->
                    <div class="btn-group d-md-none bg-light rounded-pill p-1 border shadow-sm align-items-center" role="group">
                        <button type="button" class="btn btn-sm btn-outline-primary border-0 rounded-pill active" id="btnListView" onclick="setMobileView('list')"><i class="bi bi-list-ul fs-5"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-primary border-0 rounded-pill" id="btnCardView" onclick="setMobileView('card')"><i class="bi bi-grid fs-5"></i></button>
                    </div>
                </div>

                <div class="col-md-3 mt-3">
                    <label class="form-label ms-1 text-muted small">Start Date</label>
                    <input type="date" name="start_date" class="form-control rounded-pill bg-light border-0" value="<?= escape($_GET['start_date'] ?? '') ?>">
                </div>
                <div class="col-md-3 mt-3">
                    <label class="form-label ms-1 text-muted small">End Date</label>
                    <input type="date" name="end_date" class="form-control rounded-pill bg-light border-0" value="<?= escape($_GET['end_date'] ?? '') ?>">
                </div>
                <div class="col-md-3 mt-3">
                    <label class="form-label ms-1 text-muted small">Status</label>
                    <select name="status" class="form-select rounded-pill bg-light border-0">
                        <option value="">All Status</option>
                        <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="canceled" <?= ($_GET['status'] ?? '') === 'canceled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3 mt-3">
                    <button type="submit" class="btn btn-secondary w-100 rounded-pill"><i class="bi bi-funnel"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
        <div class="card-body">
            <?php if (empty($sales)): ?>
                <div class="empty-state">
                    <i class="bi bi-receipt"></i>
                    <p>No sales yet</p>
                    <a href="<?= url('/modules/sales/pos.php') ?>" class="btn btn-primary">Make First Sale</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="dataTable">
                        <thead class="table-light">
                            <tr>
                                <th class="d-none d-md-table-cell">Reference #</th>
                                <th class="d-none d-md-table-cell">Date</th>
                                <th>Customer</th>
                                <th class="d-none d-md-table-cell">Products</th>
                                <th>Total</th>
                                <th class="d-none d-md-table-cell">Paid</th>
                                <th>Status</th>
                                <th class="d-none d-md-table-cell">Sold By</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td data-label="Reference #" class="d-none d-md-table-cell align-middle"><code><?= escape($sale['reference_no']) ?></code></td>
                                <td data-label="Date" class="d-none d-md-table-cell align-middle"><?= date('d M Y', strtotime($sale['sale_date'])) ?></td>
                                <td data-label="Customer" class="align-middle">
                                    <strong><?= escape($sale['customer_name'] ?? 'Walk-in') ?></strong>
                                    <?php if (isAdmin()): ?>
                                        <br><span class="badge bg-secondary" style="font-size: 0.7em;"><?= escape($sale['branch_name'] ?? 'Main Branch') ?></span>
                                    <?php endif; ?>
                                    <small class="d-block d-md-none text-muted"><?= escape($sale['reference_no']) ?> • <?= date('j M', strtotime($sale['sale_date'])) ?></small>
                                </td>
                                <td data-label="Products" class="d-none d-md-table-cell align-middle"><small class="text-muted"><?= escape($sale['product_details']) ?></small></td>
                                <td data-label="Total" class="align-middle"><strong><?= formatCurrency($sale['total_amount']) ?></strong></td>
                                <td data-label="Paid" class="d-none d-md-table-cell align-middle"><?= formatCurrency($sale['paid_amount']) ?></td>
                                <td data-label="Status" class="align-middle">
                                    <select class="form-select form-select-sm status-select" 
                                            data-sale-id="<?= $sale['id'] ?>"
                                            style="width: 130px; 
                                                   border-color: <?= $sale['status'] === 'completed' ? '#198754' : ($sale['status'] === 'pending' ? '#ffc107' : ($sale['status'] === 'draft' ? '#0dcaf0' : '#dc3545')) ?>;
                                                   background-color: <?= $sale['status'] === 'completed' ? '#d1e7dd' : ($sale['status'] === 'pending' ? '#fff3cd' : ($sale['status'] === 'draft' ? '#cff4fc' : '#f8d7da')) ?>;"
                                            <?= !isAdmin() ? 'disabled' : '' ?>>
                                        <option value="completed" <?= $sale['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="pending" <?= $sale['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="draft" <?= $sale['status'] === 'draft' ? 'selected' : '' ?>>Draft / Quote</option>
                                        <option value="canceled" <?= $sale['status'] === 'canceled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </td>
                                <td data-label="Sold By" class="d-none d-md-table-cell align-middle"><small><?= escape($sale['user_name']) ?></small></td>
                                <td data-label="Actions" class="align-middle text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <?php if ($sale['status'] === 'draft'): ?>
                                        <a href="<?= url('/modules/sales/pos.php?draft_id=' . $sale['id']) ?>" 
                                           class="btn btn-sm btn-outline-info" title="Continue Sale">
                                            <i class="bi bi-cart-check"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="<?= url('/modules/sales/invoice.php?id=' . $sale['id']) ?>" 
                                           class="btn btn-sm btn-outline-primary" title="View Invoice">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <?php if (isAdmin()): ?>
                                        <a href="<?= url('/modules/sales/delete.php?id=' . $sale['id']) ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Delete this sale? Stock will be restored (if completed).')" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?= renderPaginationUI($pagination) ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Live Search Panel Logic
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('salesSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const table = document.getElementById('dataTable');
            if (table) {
                const trs = table.getElementsByTagName('tr');
                for (let i = 1; i < trs.length; i++) {
                    const rowText = trs[i].textContent || trs[i].innerText;
                    if (rowText.toLowerCase().indexOf(filter) > -1) {
                        trs[i].style.display = "";
                    } else {
                        trs[i].style.display = "none";
                    }
                }
            }
        });
    }
});

document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', async function() {
        const saleId = this.dataset.saleId;
        const newStatus = this.value;
        const originalColor = this.style.backgroundColor;
        const originalBorder = this.style.borderColor;

        if (!confirm('Are you sure you want to change the status to ' + newStatus + '?')) {
            this.value = this.dataset.originalValue; // Revert
            return;
        }

        try {
            const response = await fetch(BASE_PATH + '/modules/sales/update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '<?= generateCSRFToken() ?>'
                },
                body: JSON.stringify({
                    sale_id: saleId,
                    status: newStatus
                })
            });

            const data = await response.json();

            if (data.success) {
                // Update styling based on new status
                if (newStatus === 'completed') {
                    this.style.borderColor = '#198754';
                    this.style.backgroundColor = '#d1e7dd';
                } else if (newStatus === 'pending') {
                    this.style.borderColor = '#ffc107';
                    this.style.backgroundColor = '#fff3cd';
                }
                // alert('Status updated successfully');
            } else {
                alert('Failed: ' + data.message);
                window.location.reload(); // Reload to revert
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred');
        }
    });

    // Store original value for revert
    select.dataset.originalValue = select.value;
});

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
        localStorage.setItem('sales_view_pref', 'card');
    } else {
        table.classList.remove('table-responsive-card');
        if(btnList) btnList.classList.add('active');
        if(btnCard) btnCard.classList.remove('active');
        localStorage.setItem('sales_view_pref', 'list');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if(window.innerWidth < 768) {
        let pref = localStorage.getItem('sales_view_pref') || 'list';
        setMobileView(pref);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
