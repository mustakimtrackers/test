<?php
$pageTitle = 'Customers';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;

// Get total count
$countStmt = $conn->query("SELECT COUNT(*) as total FROM contacts WHERE type = 'customer'");
$totalCustomers = $countStmt->fetch_assoc()['total'];
$pagination = getPagination($totalCustomers, $page, $perPage);
$offset = $pagination['offset'];

// Get customers with balance
$stmt = $conn->prepare("
    SELECT
        c.*,
        c.balance -- Use stored balance as Source of Truth
    FROM contacts c
    WHERE c.type = 'customer'
    ORDER BY c.name
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();
$customers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-people"></i> Customers</h1>
        <a href="<?= url('/modules/customers/create.php') ?>" class="btn btn-primary d-none d-md-inline-flex align-items-center gap-2 px-4 rounded-pill shadow-sm">
            <i class="bi bi-person-plus-fill"></i> Add Customer
        </a>
    </div>

    <!-- Mobile Floating Action Button -->
    <a href="<?= url('/modules/customers/create.php') ?>" id="mobileFabBtn" class="btn btn-primary d-md-none position-fixed rounded-circle shadow-lg d-flex align-items-center justify-content-center border-0" style="bottom: 30px; right: 24px; width: 64px; height: 64px; z-index: 1050;">
        <i class="bi bi-plus-lg" style="font-size: 2rem;"></i>
    </a>

    <!-- Features Container -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <!-- Search Panel -->
        <div class="search-box position-relative" style="max-width: 400px; width: 100%;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" class="form-control form-control-lg bg-white border-0 shadow-sm ps-5 rounded-pill" id="customerSearch" placeholder="Search customers..." autofocus>
        </div>
        
        <!-- Summary Cards -->
        <div class="d-flex gap-3">
            <div class="bg-white px-4 py-2 rounded-pill shadow-sm border text-center">
                <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Total Customers</small>
                <strong class="fs-5"><?= count($customers) ?></strong>
            </div>
        </div>
    </div>

    <!-- Customers List -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        <div class="card-body p-0">
            <?php if (empty($customers)): ?>
                <div class="text-center p-5">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-people text-muted fs-1"></i>
                    </div>
                    <h5 class="text-muted">No customers found</h5>
                    <p class="text-muted small mb-4">Get started by adding your first customer.</p>
                    <a href="<?= url('/modules/customers/create.php') ?>" class="btn btn-primary px-4 rounded-pill">Add Customer</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="customersTable">
                        <thead class="table-light">
                            <tr>
                                <th>Customer</th>
                                <th class="d-none d-md-table-cell">Email</th>
                                <th class="d-none d-md-table-cell">Phone</th>
                                <th class="d-none d-md-table-cell">Company</th>
                                <th class="text-end">Balance</th>
                                <th class="d-none d-md-table-cell text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                            <?php 
                                $custJson = htmlspecialchars(json_encode([
                                    'id' => $customer['id'],
                                    'name' => $customer['name'],
                                    'phone' => $customer['phone'],
                                    'email' => $customer['email'],
                                    'company' => $customer['company_name'],
                                    'balance' => $customer['balance'],
                                    'balanceFormatted' => formatBalance($customer['balance'], 'customer'),
                                    'image' => !empty($customer['image']) ? url($customer['image']) : null,
                                    'isAdmin' => isAdmin()
                                ]), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr style="cursor: pointer;" onclick="if(window.innerWidth < 768) showCustomerDetails(this)" data-customer='<?= $custJson ?>'>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($customer['image'])): ?>
                                            <img src="<?= url($customer['image']) ?>" alt="Customer" class="rounded-circle me-3 shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle me-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 40px; height: 40px; font-size: 1.2rem;">
                                                <?= strtoupper(substr($customer['name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong class="d-block text-dark"><?= escape($customer['name']) ?></strong>
                                            <small class="d-md-none text-muted"><i class="bi bi-telephone-fill opacity-50"></i> <?= escape($customer['phone'] ?: 'No Phone') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell align-middle"><?= escape($customer['email']) ?: '<span class="text-muted">-</span>' ?></td>
                                <td class="d-none d-md-table-cell align-middle"><?= escape($customer['phone']) ?: '<span class="text-muted">-</span>' ?></td>
                                <td class="d-none d-md-table-cell align-middle"><?= escape($customer['company_name']) ?: '<span class="text-muted">-</span>' ?></td>
                                <td class="align-middle fw-bold text-end"><?= formatBalance($customer['balance'], 'customer') ?></td>
                                <td class="d-none d-md-table-cell align-middle text-end">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a href="<?= url('/modules/customers/payment.php?id=' . $customer['id']) ?>" class="btn btn-sm btn-success rounded-pill px-3" title="Receive Payment">
                                            <i class="bi bi-cash"></i> Pay
                                        </a>
                                        <a href="<?= url('/modules/reports/customer_ledger.php?customer_id=' . $customer['id']) ?>" class="btn btn-sm btn-info text-white rounded-pill px-3" title="View Ledger">
                                            <i class="bi bi-journal-text"></i> Ledger
                                        </a>
                                        <?php if (isAdmin()): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light rounded-circle" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                <?php if ($customer['balance'] > 0 && isset($customer['email_opt_in']) && $customer['email_opt_in']): ?>
                                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="openReminderModal(<?= $customer['id'] ?>); event.stopPropagation();"><i class="bi bi-envelope me-2 text-primary"></i> Send Reminder</a></li>
                                                <?php endif; ?>
                                                <li><a class="dropdown-item" href="<?= url('/modules/customers/edit.php?id=' . $customer['id']) ?>"><i class="bi bi-pencil me-2"></i> Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="<?= url('/modules/customers/delete.php?id=' . $customer['id']) ?>" onclick="return confirm('Delete this customer?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
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
<div class="offcanvas offcanvas-bottom shadow rounded-top-4" tabindex="-1" id="customerOffcanvas" style="height: auto; max-height: 85vh;">
    <div class="offcanvas-header border-bottom align-items-start">
        <div class="d-flex align-items-center">
            <div id="cvImageContainer" class="me-3">
                <!-- Injected via JS -->
            </div>
            <div>
                <h5 class="offcanvas-title fw-bold" id="cvName">Customer Name</h5>
                <span class="badge bg-light text-dark border mt-1" id="cvCompany">Company</span>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded-3 border">
            <div>
                <small class="text-muted d-block">Current Balance</small>
                <strong class="fs-4" id="cvBalance">৳0.00</strong>
            </div>
            <i class="bi bi-wallet2 fs-1 text-muted opacity-25"></i>
        </div>

        <div class="mb-3">
            <small class="text-muted d-block mb-1"><i class="bi bi-telephone"></i> Phone</small>
            <strong id="cvPhone" class="d-block fs-6"></strong>
            
            <small class="text-muted d-block mt-3 mb-1"><i class="bi bi-envelope"></i> Email</small>
            <strong id="cvEmail" class="d-block fs-6"></strong>
        </div>

        <hr class="my-4">

        <h6 class="text-muted mb-3">Actions</h6>
        <div class="d-grid gap-2" id="cvActions">
            <!-- Buttons injected by JS -->
        </div>
    </div>
</div>

<script>
function showCustomerDetails(row) {
    if (window.innerWidth >= 768) return; // Only on mobile
    
    // Parse the JSON data attached to the row
    const dataStr = row.getAttribute('data-customer');
    if(!dataStr) return;
    
    try {
        const customer = JSON.parse(dataStr);
        
        // Populate Offcanvas
        if (customer.image) {
            document.getElementById('cvImageContainer').innerHTML = `<img src="${customer.image}" alt="Customer" class="rounded-circle shadow-sm border" style="width: 56px; height: 56px; object-fit: cover;">`;
        } else {
            const initial = customer.name ? customer.name.charAt(0).toUpperCase() : 'C';
            document.getElementById('cvImageContainer').innerHTML = `<div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 56px; height: 56px; font-size: 1.5rem;">${initial}</div>`;
        }
        
        document.getElementById('cvName').textContent = customer.name;
        document.getElementById('cvCompany').textContent = customer.company || 'No Company';
        document.getElementById('cvCompany').style.display = customer.company ? 'inline-block' : 'none';
        document.getElementById('cvBalance').innerHTML = customer.balanceFormatted;
        
        document.getElementById('cvPhone').textContent = customer.phone || 'N/A';
        document.getElementById('cvEmail').textContent = customer.email || 'N/A';
        
        // Build Action Buttons
        let actionsHtml = `
            <a href="<?= url('/modules/customers/payment.php?id=') ?>${customer.id}" class="btn btn-success btn-lg fw-bold w-100 rounded-pill shadow-sm mb-2">
                <i class="bi bi-cash"></i> Receive Payment
            </a>
            <a href="<?= url('/modules/reports/customer_ledger.php?customer_id=') ?>${customer.id}" class="btn btn-outline-info btn-lg fw-bold w-100 rounded-pill mb-2">
                <i class="bi bi-journal-text text-info"></i> View Full Ledger
            </a>
        `;
        
        if(customer.isAdmin) {
            actionsHtml += `
                <div class="d-flex gap-2 mt-2">
                    <a href="<?= url('/modules/customers/edit.php?id=') ?>${customer.id}" class="btn btn-light flex-fill rounded-pill">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <a href="<?= url('/modules/customers/delete.php?id=') ?>${customer.id}" class="btn btn-outline-danger flex-fill rounded-pill" onclick="return confirm('Delete this customer?')">
                        <i class="bi bi-trash"></i> Delete
                    </a>
                </div>
            `;
        }
        
        document.getElementById('cvActions').innerHTML = actionsHtml;
        
        // Show Offcanvas
        const bsOffcanvas = new bootstrap.Offcanvas(document.getElementById('customerOffcanvas'));
        bsOffcanvas.show();
    } catch(e) {
        console.error("Error parsing customer data", e);
    }
}

// Live Search Panel Logic
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('customerSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const table = document.getElementById('customersTable');
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
    const bsOffcanvasEl = document.getElementById('customerOffcanvas');
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
    // Send Reminder Modal Handlers
    window.openReminderModal = function(id) {
        // Find the tr element that contains the customer JSON (matching the ID using a regex-like approach)
        const rows = document.querySelectorAll('tr[data-customer]');
        let cust = null;
        for (let row of rows) {
            let data = JSON.parse(row.getAttribute('data-customer'));
            if (data.id == id) {
                cust = data;
                break;
            }
        }
        
        if(cust) {
            document.getElementById('reminderCustomerId').value = cust.id;
            document.getElementById('reminderCustomerName').textContent = cust.name;
            document.getElementById('reminderDueAmount').innerHTML = cust.balanceFormatted;
            
            let msg = `Dear ${cust.name},\n\nThis is a friendly reminder that you have an outstanding due of Tk ${cust.balance}.\n\nPlease arrange the payment at your earliest convenience.\n\nThank you for your business!`;
            document.getElementById('reminderMessage').value = msg;
            
            new bootstrap.Modal(document.getElementById('reminderModal')).show();
        }
    };

    window.sendReminderEmail = function() {
        const btn = document.querySelector('#reminderModal .btn-primary');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
        btn.disabled = true;
        
        const data = {
            customer_id: document.getElementById('reminderCustomerId').value,
            subject: document.getElementById('reminderSubject').value,
            message: document.getElementById('reminderMessage').value
        };
        
        fetch('<?= url('/modules/email/api/send_reminder.php') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(res => {
            if(res.success) {
                bootstrap.Modal.getInstance(document.getElementById('reminderModal')).hide();
                alert('Reminder email sent successfully!');
            } else {
                alert('Error: ' + (res.message || 'Failed to send email.'));
            }
        })
        .catch(err => {
            alert('A network error occurred while sending the email.');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    };
});
</script>

<!-- Send Reminder Modal -->
<div class="modal fade" id="reminderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title"><i class="bi bi-envelope text-primary me-2"></i>Send Due Reminder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="reminderForm">
                    <input type="hidden" id="reminderCustomerId">
                    <div class="mb-3 d-flex justify-content-between align-items-center bg-light p-3 rounded">
                        <div>
                            <small class="text-muted d-block">Customer</small>
                            <strong id="reminderCustomerName" class="text-dark"></strong>
                        </div>
                        <div class="text-end">
                            <small class="text-muted d-block">Due Amount</small>
                            <span id="reminderDueAmount" class="fw-bold fs-5"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Subject</label>
                        <input type="text" class="form-control" id="reminderSubject" value="Payment Reminder - FOrbit POS">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message Layout</label>
                        <textarea class="form-control text-muted" id="reminderMessage" rows="6"></textarea>
                        <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>You can edit the text above before sending. The final design will be a rich HTML email.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" onclick="sendReminderEmail()">
                    <i class="bi bi-send-fill me-2"></i> Send Email
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
