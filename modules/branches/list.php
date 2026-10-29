<?php
$pageTitle = 'Manage Branches';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Only Admin can manage branches
if (!isAdmin()) {
    die("Access Denied");
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Error: Invalid security token.</div>";
    } else {
        if ($_POST['action'] === 'add') {
            $name = cleanInput($_POST['name']);
            $address = cleanInput($_POST['address']);
            $phone = cleanInput($_POST['phone']);
            
            $stmt = $conn->prepare("INSERT INTO branches (name, address, phone) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $address, $phone);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'><i class='bi bi-check-circle'></i> Branch added successfully.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $name = cleanInput($_POST['name']);
            $address = cleanInput($_POST['address']);
            $phone = cleanInput($_POST['phone']);
            
            $stmt = $conn->prepare("UPDATE branches SET name = ?, address = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $address, $phone, $id);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'><i class='bi bi-check-circle'></i> Branch updated successfully.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            if ($id === 1) {
                $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> <strong>Cannot Delete:</strong> Main Branch (ID 1) cannot be deleted.</div>";
            } else {
                // Check if users are assigned to this branch
                $userCheck = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE branch_id = $id");
                $has_users = $userCheck->fetch_assoc()['cnt'] > 0;
                
                if ($has_users) {
                    $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> <strong>Cannot Delete:</strong> There are users assigned to this branch. Reassign them first.</div>";
                } else {
                    $conn->query("DELETE FROM branches WHERE id = $id");
                    $message = "<div class='alert alert-success'><i class='bi bi-check-circle'></i> Branch deleted successfully.</div>";
                }
            }
        }
    }
}

$branches = $conn->query("SELECT * FROM branches ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
?>
<div class="container-fluid">
    <div class="page-header mb-4">
        <h1 class="page-title"><i class="bi bi-shop"></i> Branch Management</h1>
        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addBranchModal">
            <i class="bi bi-plus-circle"></i> Add New Branch
        </button>
    </div>
    
    <?= $message ?>

    <div class="row g-4">
        <?php foreach ($branches as $branch): ?>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 position-relative overflow-hidden">
                <!-- Decorative Top Border -->
                <div class="position-absolute top-0 start-0 w-100" style="height: 4px; background: linear-gradient(90deg, #4f46e5, #ec4899);"></div>
                
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="badge bg-light text-primary mb-2">Branch #<?= htmlspecialchars($branch['id']) ?></span>
                            <h4 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($branch['name']) ?></h4>
                        </div>
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="bi <?= $branch['id'] == 1 ? 'bi-building-fill' : 'bi-shop' ?> fs-4"></i>
                        </div>
                    </div>
                    
                    <div class="mb-4 text-muted small">
                        <div class="d-flex align-items-start mb-2">
                            <i class="bi bi-geo-alt-fill text-secondary me-2 mt-1"></i>
                            <span><?= nl2br(htmlspecialchars($branch['address'])) ?></span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-telephone-fill text-secondary me-2"></i>
                            <span><?= htmlspecialchars($branch['phone']) ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-auto border-top pt-3 d-flex justify-content-between align-items-center">
                        <a href="<?= url('/modules/branches/view.php?id=' . $branch['id']) ?>" class="btn btn-primary rounded-pill px-4 shadow-sm" style="font-size: 0.9rem;">
                            Dashboard <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                        
                        <div class="dropdown">
                            <button class="btn btn-light btn-sm rounded-circle border-0 text-muted" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 32px; height: 32px;">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                                <li>
                                    <a class="dropdown-item py-2" href="#" data-bs-toggle="modal" data-bs-target="#editBranchModal<?= $branch['id'] ?>">
                                        <i class="bi bi-pencil me-2 text-muted"></i> Edit Branch
                                    </a>
                                </li>
                                <?php if ($branch['id'] != 1): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this branch?');">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $branch['id'] ?>">
                                        <button type="submit" class="dropdown-item text-danger py-2">
                                            <i class="bi bi-trash me-2"></i> Delete Branch
                                        </button>
                                    </form>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal for this branch -->
        <div class="modal fade" id="editBranchModal<?= $branch['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Branch</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= $branch['id'] ?>">
                            <div class="mb-3">
                                <label class="form-label">Branch Name</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($branch['name']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($branch['address']) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($branch['phone']) ?>" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if(empty($branches)): ?>
        <div class="col-12 text-center py-5">
            <div class="text-muted mb-3"><i class="bi bi-shop display-1 opacity-25"></i></div>
            <p class="text-muted">No branches found.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Branch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Branch Name (e.g. Dhanmondi Outlet)</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
