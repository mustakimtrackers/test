<?php
/**
 * Purchases List
 * View all purchase orders
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = 'Purchase Orders';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;

// Get total count
$countStmt = $conn->query("SELECT COUNT(*) as total FROM purchases");
$totalPurchases = $countStmt->fetch_assoc()['total'];
$pagination = getPagination($totalPurchases, $page, $perPage);
$offset = $pagination['offset'];

// Get purchases
$stmt = $conn->prepare("
    SELECT 
        p.id,
        p.reference_no,
        p.purchase_date,
        p.total_amount,
        p.paid_amount,
        p.status,
        s.name as supplier_name,
        GROUP_CONCAT(CONCAT(prod.name, ' (', pi.quantity, ')') SEPARATOR ', ') as product_details
    FROM purchases p
    LEFT JOIN contacts s ON p.supplier_id = s.id
    LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
    LEFT JOIN products prod ON pi.product_id = prod.id
    GROUP BY p.id
    ORDER BY p.purchase_date DESC, p.id DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
$purchases = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container-fluid">
    <div class="page-header mb-4">
        <h1 class="page-title">
            <i class="bi bi-cart-plus"></i> Purchase Orders
        </h1>
        <div class="page-actions">
            <!-- Desktop Add Button -->
            <a href="<?= url('/modules/purchases/create.php') ?>" class="btn btn-primary d-none d-md-inline-flex align-items-center gap-2 px-4 rounded-pill shadow-sm">
                <i class="bi bi-plus-circle"></i> New Purchase
            </a>
        </div>
    </div>
    
    <!-- Mobile Floating Action Button -->
    <a href="<?= url('/modules/purchases/create.php') ?>" id="mobileFabBtn" class="btn btn-primary d-md-none position-fixed rounded-circle shadow-lg d-flex align-items-center justify-content-center border-0" style="bottom: 30px; right: 24px; width: 64px; height: 64px; z-index: 1050;">
        <i class="bi bi-cart-plus" style="font-size: 2rem;"></i>
    </a>

    <!-- UI Controls -->
    <div class="card mb-4 border-0 shadow-sm rounded-4">
        <div class="card-body p-3">
            <!-- Search & Mobile View Toggle Row -->
            <div class="d-flex gap-2">
                <div class="search-box position-relative flex-grow-1">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="purchasesSearch" class="form-control form-control-lg bg-light border-0 ps-5 rounded-pill" placeholder="Live search purchases...">
                </div>
                
                <!-- Mobile View Toggle (Inside search row for better UI) -->
                <div class="btn-group d-md-none bg-light rounded-pill p-1 border shadow-sm align-items-center" role="group">
                    <button type="button" class="btn btn-sm btn-outline-primary border-0 rounded-pill active" id="btnListView" onclick="setMobileView('list')"><i class="bi bi-list-ul fs-5"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-primary border-0 rounded-pill" id="btnCardView" onclick="setMobileView('card')"><i class="bi bi-grid fs-5"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($purchases)): ?>
                <div class="empty-state">
                    <i class="bi bi-cart-plus"></i>
                    <p>No purchases yet</p>
                    <a href="<?= url('/modules/purchases/create.php') ?>" class="btn btn-primary">Create First Purchase</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="dataTable">
                        <thead class="table-light">
                            <tr>
                                <th class="d-none d-md-table-cell">Reference #</th>
                                <th class="d-none d-md-table-cell">Date</th>
                                <th>Supplier</th>
                                <th class="d-none d-md-table-cell">Products</th>
                                <th>Total</th>
                                <th class="d-none d-md-table-cell">Paid</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $purchase): ?>
                            <tr>
                                <td data-label="Reference #" class="d-none d-md-table-cell align-middle"><code><?= escape($purchase['reference_no']) ?></code></td>
                                <td data-label="Date" class="d-none d-md-table-cell align-middle"><?= date('d M Y', strtotime($purchase['purchase_date'])) ?></td>
                                <td data-label="Supplier" class="align-middle">
                                    <strong><?= escape($purchase['supplier_name'] ?? 'N/A') ?></strong>
                                    <small class="d-block d-md-none text-muted"><?= escape($purchase['reference_no']) ?> • <?= date('j M', strtotime($purchase['purchase_date'])) ?></small>
                                </td>
                                <td data-label="Products" class="d-none d-md-table-cell align-middle"><small class="text-muted"><?= escape($purchase['product_details']) ?></small></td>
                                <td data-label="Total" class="align-middle"><strong><?= formatCurrency($purchase['total_amount']) ?></strong></td>
                                <td data-label="Paid" class="d-none d-md-table-cell align-middle"><?= formatCurrency($purchase['paid_amount']) ?></td>
                                <td data-label="Status" class="align-middle">
                                    <button class="btn badge status-btn 
                                        <?= $purchase['status'] === 'received' ? 'bg-success' : 
                                           ($purchase['status'] === 'pending' ? 'bg-warning' : 
                                           ($purchase['status'] === 'ordered' ? 'bg-info' : 
                                           ($purchase['status'] === 'canceled' ? 'bg-danger' : 'bg-secondary'))) ?>"
                                        data-id="<?= $purchase['id'] ?>"
                                        data-status="<?= htmlspecialchars($purchase['status']) ?>"
                                        style="border:none; cursor:pointer;"
                                        title="Click to update status">
                                        <?= ucfirst($purchase['status'] ?: 'Unknown') ?>
                                    </button>
                                </td>
                                <td data-label="Actions" class="align-middle text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="<?= url('/modules/purchases/view.php?id=' . $purchase['id']) ?>" 
                                           class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (isAdmin()): ?>
                                        <a href="<?= url('/modules/purchases/delete.php?id=' . $purchase['id']) ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Delete this purchase? Stock will be adjusted.')" title="Delete">
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
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="purchase_id" id="statusPurchaseId">
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select class="form-select" name="status" id="statusSelect">
                            <option value="ordered">Ordered (Pending Delivery)</option>
                            <option value="received">Received (Stock Added)</option>
                            <option value="pending">Pending</option>
                            <option value="canceled">Canceled</option>
                        </select>
                    </div>
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle"></i>
                        Changing status to <strong>Received</strong> will add items to stock.<br>
                        Changing from <strong>Received</strong> to other status will deduct items from stock.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveStatusBtn">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
// Live Search Panel Logic
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('purchasesSearch');
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

document.addEventListener('DOMContentLoaded', function() {
    const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
    const statusSelect = document.getElementById('statusSelect');
    const purchaseIdInput = document.getElementById('statusPurchaseId');
    
    // Attach click event to status badges or action buttons
    document.querySelectorAll('.status-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const currentStatus = this.dataset.status;
            
            purchaseIdInput.value = id;
            statusSelect.value = currentStatus;
            
            statusModal.show();
        });
    });

    document.getElementById('saveStatusBtn').addEventListener('click', function() {
        const formData = new FormData(document.getElementById('statusForm'));
        
        fetch('update_status.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert(data.message || 'Error updating status');
            }
        });
    });
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
        localStorage.setItem('purchases_view_pref', 'card');
    } else {
        table.classList.remove('table-responsive-card');
        if(btnList) btnList.classList.add('active');
        if(btnCard) btnCard.classList.remove('active');
        localStorage.setItem('purchases_view_pref', 'list');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if(window.innerWidth < 768) {
        let pref = localStorage.getItem('purchases_view_pref') || 'list';
        setMobileView(pref);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
