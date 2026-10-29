<?php
$pageTitle = 'Suppliers';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;

// Get total count
$countStmt = $conn->query("SELECT COUNT(*) as total FROM contacts WHERE type = 'supplier'");
$totalSuppliers = $countStmt->fetch_assoc()['total'];
$pagination = getPagination($totalSuppliers, $page, $perPage);
$offset = $pagination['offset'];

// Get suppliers with balance
$stmt = $conn->prepare("
    SELECT 
        c.*,
        c.balance
    FROM contacts c
    WHERE c.type = 'supplier'
    ORDER BY c.name
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
$suppliers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-truck"></i> Suppliers</h1>
        <a href="<?= url('/modules/suppliers/create.php') ?>" class="btn btn-primary d-none d-md-inline-flex align-items-center gap-2 px-4 rounded-pill shadow-sm">
            <i class="bi bi-person-plus-fill"></i> Add Supplier
        </a>
    </div>

    <!-- Mobile Floating Action Button -->
    <a href="<?= url('/modules/suppliers/create.php') ?>" id="mobileFabBtn" class="btn btn-primary d-md-none position-fixed rounded-circle shadow-lg d-flex align-items-center justify-content-center border-0" style="bottom: 30px; right: 24px; width: 64px; height: 64px; z-index: 1050;">
        <i class="bi bi-plus-lg" style="font-size: 2rem;"></i>
    </a>

    <!-- Features Container -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <!-- Search Panel -->
        <div class="search-box position-relative" style="max-width: 400px; width: 100%;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" class="form-control form-control-lg bg-white border-0 shadow-sm ps-5 rounded-pill" id="supplierSearch" placeholder="Search suppliers..." autofocus>
        </div>
        
        <!-- Summary Cards -->
        <div class="d-flex gap-3">
            <div class="bg-white px-4 py-2 rounded-pill shadow-sm border text-center">
                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Total Suppliers</small>
                <strong class="fs-5"><?= count($suppliers) ?></strong>
            </div>
        </div>
    </div>

    <!-- Suppliers List -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <?php if (empty($suppliers)): ?>
                <div class="text-center p-5">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-truck text-muted fs-1"></i>
                    </div>
                    <h5 class="text-muted">No suppliers found</h5>
                    <p class="text-muted small mb-4">Get started by adding your first supplier.</p>
                    <a href="<?= url('/modules/suppliers/create.php') ?>" class="btn btn-primary px-4 rounded-pill">Add Supplier</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="suppliersTable">
                        <thead class="table-light">
                            <tr>
                                <th>Supplier</th>
                                <th class="d-none d-md-table-cell">Email</th>
                                <th class="d-none d-md-table-cell">Phone</th>
                                <th class="d-none d-md-table-cell">Company</th>
                                <th class="text-end">Balance</th>
                                <th class="d-none d-md-table-cell text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $supplier): ?>
                            <?php 
                                $suppJson = htmlspecialchars(json_encode([
                                    'id' => $supplier['id'],
                                    'name' => $supplier['name'],
                                    'phone' => $supplier['phone'],
                                    'email' => $supplier['email'],
                                    'company' => $supplier['company_name'],
                                    'balance' => $supplier['balance'],
                                    'balanceFormatted' => formatBalance($supplier['balance'], 'supplier'),
                                    'image' => !empty($supplier['image']) ? url($supplier['image']) : null,
                                    'isAdmin' => isAdmin()
                                ]), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr style="cursor: pointer;" onclick="if(window.innerWidth < 768) showSupplierDetails(this)" data-supplier='<?= $suppJson ?>'>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($supplier['image'])): ?>
                                            <img src="<?= url($supplier['image']) ?>" alt="Supplier" class="rounded-circle me-3 shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle me-3 bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 40px; height: 40px; font-size: 1.2rem;">
                                                <i class="bi bi-truck"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong class="d-block text-dark"><?= escape($supplier['name']) ?></strong>
                                            <small class="d-md-none text-muted"><i class="bi bi-telephone-fill opacity-50"></i> <?= escape($supplier['phone'] ?: 'No Phone') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell align-middle"><?= escape($supplier['email']) ?: '<span class="text-muted">-</span>' ?></td>
                                <td class="d-none d-md-table-cell align-middle"><?= escape($supplier['phone']) ?: '<span class="text-muted">-</span>' ?></td>
                                <td class="d-none d-md-table-cell align-middle"><?= escape($supplier['company_name']) ?: '<span class="text-muted">-</span>' ?></td>
                                <td class="align-middle fw-bold text-end"><?= formatBalance($supplier['balance'], 'supplier') ?></td>
                                <td class="d-none d-md-table-cell align-middle text-end">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a href="<?= url('/modules/suppliers/payment.php?id=' . $supplier['id']) ?>" class="btn btn-sm btn-success rounded-pill px-3" title="Pay Supplier">
                                            <i class="bi bi-cash"></i> Pay
                                        </a>
                                        <a href="<?= url('/modules/reports/supplier_ledger.php?supplier_id=' . $supplier['id']) ?>" class="btn btn-sm btn-info text-white rounded-pill px-3" title="View Ledger">
                                            <i class="bi bi-journal-text"></i> Ledger
                                        </a>
                                        <?php if (isAdmin()): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light rounded-circle" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                <li><a class="dropdown-item" href="<?= url('/modules/suppliers/edit.php?id=' . $supplier['id']) ?>"><i class="bi bi-pencil me-2"></i> Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="<?= url('/modules/suppliers/delete.php?id=' . $supplier['id']) ?>" onclick="return confirm('Delete this supplier?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
                                            </ul>
                                        </div>
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

<!-- Mobile Details Offcanvas -->
<div class="offcanvas offcanvas-bottom shadow rounded-top-4" tabindex="-1" id="supplierOffcanvas" style="height: auto; max-height: 85vh;">
    <div class="offcanvas-header border-bottom align-items-start">
        <div class="d-flex align-items-center">
            <div id="svImageContainer" class="me-3">
                <!-- Injected via JS -->
            </div>
            <div>
                <h5 class="offcanvas-title fw-bold" id="svName">Supplier Name</h5>
                <span class="badge bg-light text-dark border mt-1" id="svCompany">Company</span>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded-3 border">
            <div>
                <small class="text-muted d-block">Current Balance</small>
                <strong class="fs-4" id="svBalance">৳0.00</strong>
            </div>
            <i class="bi bi-wallet2 fs-1 text-muted opacity-25"></i>
        </div>

        <div class="mb-3">
            <small class="text-muted d-block mb-1"><i class="bi bi-telephone"></i> Phone</small>
            <strong id="svPhone" class="d-block fs-6"></strong>
            
            <small class="text-muted d-block mt-3 mb-1"><i class="bi bi-envelope"></i> Email</small>
            <strong id="svEmail" class="d-block fs-6"></strong>
        </div>

        <hr class="my-4">

        <h6 class="text-muted mb-3">Actions</h6>
        <div class="d-grid gap-2" id="svActions">
            <!-- Buttons injected by JS -->
        </div>
    </div>
</div>

<script>
function showSupplierDetails(row) {
    if (window.innerWidth >= 768) return; // Only on mobile
    
    // Parse the JSON data attached to the row
    const dataStr = row.getAttribute('data-supplier');
    if(!dataStr) return;
    
    try {
        const supplier = JSON.parse(dataStr);
        
        // Populate Offcanvas
        if (supplier.image) {
            document.getElementById('svImageContainer').innerHTML = `<img src="${supplier.image}" alt="Supplier" class="rounded-circle shadow-sm border" style="width: 56px; height: 56px; object-fit: cover;">`;
        } else {
            document.getElementById('svImageContainer').innerHTML = `<div class="rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 56px; height: 56px; font-size: 1.5rem;"><i class="bi bi-truck"></i></div>`;
        }
        
        document.getElementById('svName').textContent = supplier.name;
        document.getElementById('svCompany').textContent = supplier.company || 'No Company';
        document.getElementById('svCompany').style.display = supplier.company ? 'inline-block' : 'none';
        document.getElementById('svBalance').innerHTML = supplier.balanceFormatted;
        
        document.getElementById('svPhone').textContent = supplier.phone || 'N/A';
        document.getElementById('svEmail').textContent = supplier.email || 'N/A';
        
        // Build Action Buttons
        let actionsHtml = `
            <a href="<?= url('/modules/suppliers/payment.php?id=') ?>${supplier.id}" class="btn btn-danger btn-lg fw-bold w-100 rounded-pill shadow-sm mb-2">
                <i class="bi bi-cash"></i> Pay Supplier
            </a>
            <a href="<?= url('/modules/reports/supplier_ledger.php?supplier_id=') ?>${supplier.id}" class="btn btn-outline-info btn-lg fw-bold w-100 rounded-pill mb-2">
                <i class="bi bi-journal-text text-info"></i> View Full Ledger
            </a>
        `;
        
        if(supplier.isAdmin) {
            actionsHtml += `
                <div class="d-flex gap-2 mt-2">
                    <a href="<?= url('/modules/suppliers/edit.php?id=') ?>${supplier.id}" class="btn btn-light flex-fill rounded-pill">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <a href="<?= url('/modules/suppliers/delete.php?id=') ?>${supplier.id}" class="btn btn-outline-danger flex-fill rounded-pill" onclick="return confirm('Delete this supplier?')">
                        <i class="bi bi-trash"></i> Delete
                    </a>
                </div>
            `;
        }
        
        document.getElementById('svActions').innerHTML = actionsHtml;
        
        // Show Offcanvas
        const bsOffcanvas = new bootstrap.Offcanvas(document.getElementById('supplierOffcanvas'));
        bsOffcanvas.show();
    } catch(e) {
        console.error("Error parsing supplier data", e);
    }
}

// Live Search Panel Logic
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('supplierSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const table = document.getElementById('suppliersTable');
            if (table) {
                const trs = table.getElementsByTagName('tr');
                // Loop through all table rows, starting from 1 to skip the header
                for (let i = 1; i < trs.length; i++) {
                    // Try to match search text against the whole row text content
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

    // Hide FAB when offcanvas opens
    const fabBtn = document.getElementById('mobileFabBtn');
    const bsOffcanvasEl = document.getElementById('supplierOffcanvas');
    if(fabBtn && bsOffcanvasEl) {
        bsOffcanvasEl.addEventListener('show.bs.offcanvas', function () {
            fabBtn.style.display = 'none';
            fabBtn.classList.remove('d-flex');
        });
        bsOffcanvasEl.addEventListener('hidden.bs.offcanvas', function () {
            fabBtn.style.display = '';
            fabBtn.classList.add('d-flex');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
