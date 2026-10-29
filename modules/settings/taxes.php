<?php
$pageTitle = 'Tax Settings';
$requiredRole = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Handle Form Submission (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid security token');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            // Check if used? For now just soft delete or hard delete if not used. 
            // Let's hard delete for simplicity unless we added FKs. We didn't add FKs yet.
            $stmt = $conn->prepare("DELETE FROM tax_settings WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                setFlashMessage('success', 'Tax rate deleted');
            } else {
                setFlashMessage('error', 'Failed to delete tax rate');
            }
        } else {
            // Add/Update
            $name = cleanInput($_POST['tax_name']);
            $rate = (float)$_POST['tax_rate'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            
            if (empty($name)) {
                setFlashMessage('error', 'Tax name is required');
            } else {
                $conn->begin_transaction();
                try {
                    // If setting as default, unset others
                    if ($is_default) {
                        $conn->query("UPDATE tax_settings SET is_default = 0");
                    }
                    
                    if ($id > 0) {
                        // Update
                        $stmt = $conn->prepare("UPDATE tax_settings SET tax_name = ?, tax_rate = ?, is_active = ?, is_default = ? WHERE id = ?");
                        $stmt->bind_param("sdiii", $name, $rate, $is_active, $is_default, $id);
                    } else {
                        // Insert
                        $stmt = $conn->prepare("INSERT INTO tax_settings (tax_name, tax_rate, is_active, is_default) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("sdii", $name, $rate, $is_active, $is_default);
                    }
                    $stmt->execute();
                    $conn->commit();
                    setFlashMessage('success', 'Tax rate saved');
                } catch (Exception $e) {
                    $conn->rollback();
                    setFlashMessage('error', 'Database error: ' . $e->getMessage());
                }
            }
        }
        redirect(url('/modules/settings/taxes.php'));
    }
}

// Fetch Taxes
$taxes = $conn->query("SELECT * FROM tax_settings ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h1 class="page-title"><i class="bi bi-percent"></i> Tax Settings</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taxModal" onclick="resetForm()">
            <i class="bi bi-plus-lg"></i> Add New Tax
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Rate (%)</th>
                            <th>Default</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($taxes as $tax): ?>
                        <tr>
                            <td><?= escape($tax['tax_name']) ?></td>
                            <td><?= number_format($tax['tax_rate'], 2) ?>%</td>
                            <td>
                                <?php if ($tax['is_default']): ?>
                                    <span class="badge bg-primary">Default</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tax['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info text-white" 
                                    onclick='editTax(<?= json_encode($tax) ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if (!$tax['is_default']): ?>
                                <button class="btn btn-sm btn-danger" 
                                    onclick="deleteTax(<?= $tax['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($taxes)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No tax rates found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="taxModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Tax Rate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="id" id="tax_id" value="">
                    
                    <div class="mb-3">
                        <label class="form-label">Tax Name</label>
                        <input type="text" class="form-control" name="tax_name" id="tax_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" name="tax_rate" id="tax_rate" required>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_default" id="is_default">
                        <label class="form-check-label" for="is_default">Set as Default Rate</label>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function resetForm() {
    document.getElementById('modalTitle').innerText = 'Add Tax Rate';
    document.getElementById('tax_id').value = '';
    document.getElementById('tax_name').value = '';
    document.getElementById('tax_rate').value = '';
    document.getElementById('is_default').checked = false;
    document.getElementById('is_active').checked = true;
}

function editTax(tax) {
    document.getElementById('modalTitle').innerText = 'Edit Tax Rate';
    document.getElementById('tax_id').value = tax.id;
    document.getElementById('tax_name').value = tax.tax_name;
    document.getElementById('tax_rate').value = tax.tax_rate;
    document.getElementById('is_default').checked = tax.is_default == 1;
    document.getElementById('is_active').checked = tax.is_active == 1;
    
    new bootstrap.Modal(document.getElementById('taxModal')).show();
}

function deleteTax(id) {
    if(confirm('Are you sure you want to delete this tax rate?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
