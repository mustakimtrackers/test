<?php
$pageTitle = 'Chart of Accounts';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Check if User is Admin
if (!isAdmin()) {
    die("Access Denied");
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $code = $conn->real_escape_string($_POST['code']);
        $name = $conn->real_escape_string($_POST['name']);
        $type = $conn->real_escape_string($_POST['type']);
        $stmt = $conn->prepare("INSERT INTO chart_of_accounts (code, name, type) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $code, $name, $type);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'><i class='bi bi-check-circle'></i> Account added successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'edit') {
        $id = (int)$_POST['id'];
        $code = $conn->real_escape_string($_POST['code']);
        $name = $conn->real_escape_string($_POST['name']);
        $type = $conn->real_escape_string($_POST['type']);
        
        $stmt = $conn->prepare("UPDATE chart_of_accounts SET code = ?, name = ?, type = ? WHERE id = ?");
        $stmt->bind_param("sssi", $code, $name, $type, $id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'><i class='bi bi-check-circle'></i> Account updated successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        
        // Check if system account
        $checkStmt = $conn->query("SELECT is_system FROM chart_of_accounts WHERE id = $id");
        $is_system = $checkStmt->fetch_assoc()['is_system'] ?? 0;
        
        if ($is_system) {
            $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> <strong>Cannot Delete:</strong> This is a System Default account and is strictly required for background automated journals.</div>";
        } else {
            // Check if it has transactions
            $transCheck = $conn->query("SELECT COUNT(*) as cnt FROM journal_entry_lines WHERE account_id = $id");
            $has_transactions = $transCheck->fetch_assoc()['cnt'] > 0;
            
            if ($has_transactions) {
                $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> <strong>Cannot Delete:</strong> This account already has financial transactions in the ledger. Deleting it would break the balance sheet.</div>";
            } else {
                $conn->query("DELETE FROM chart_of_accounts WHERE id = $id");
                $message = "<div class='alert alert-success'><i class='bi bi-check-circle'></i> Account deleted successfully.</div>";
            }
        }
    }
}

$accounts = $conn->query("SELECT * FROM chart_of_accounts ORDER BY code ASC")->fetch_all(MYSQLI_ASSOC);
?>
<div class="container-fluid">
    <div class="page-header mb-4">
        <h1 class="page-title"><i class="bi bi-diagram-3"></i> Chart of Accounts</h1>
        <button type="button" class="btn btn-primary d-none d-md-inline-flex align-items-center gap-2 px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addAccountModal">
            <i class="bi bi-plus-circle"></i> Add Account
        </button>
    </div>
    
    <?= $message ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $acc): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($acc['code']) ?></strong></td>
                            <td><?= htmlspecialchars($acc['name']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($acc['type']) ?></span></td>
                            <td>
                                <?php if ($acc['is_system']): ?>
                                    <span class="badge bg-info text-dark" title="System accounts cannot be deleted"><i class="bi bi-shield-lock"></i> System Default</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Custom</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAccountModal<?= $acc['id'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this account?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $acc['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" <?= $acc['is_system'] ? 'disabled title="System accounts cannot be deleted"' : '' ?>>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- Edit Modal for this account -->
                        <div class="modal fade" id="editAccountModal<?= $acc['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Account</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="id" value="<?= $acc['id'] ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Account Code</label>
                                                <input type="text" name="code" class="form-control" value="<?= htmlspecialchars($acc['code']) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Account Name</label>
                                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($acc['name']) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Account Type</label>
                                                <select name="type" class="form-select" required>
                                                    <option value="Asset" <?= strcasecmp($acc['type'], 'asset') == 0 ? 'selected' : '' ?>>Asset</option>
                                                    <option value="Liability" <?= strcasecmp($acc['type'], 'liability') == 0 ? 'selected' : '' ?>>Liability</option>
                                                    <option value="Equity" <?= strcasecmp($acc['type'], 'equity') == 0 ? 'selected' : '' ?>>Equity</option>
                                                    <option value="Revenue" <?= strcasecmp($acc['type'], 'revenue') == 0 ? 'selected' : '' ?>>Revenue</option>
                                                    <option value="Expense" <?= strcasecmp($acc['type'], 'expense') == 0 ? 'selected' : '' ?>>Expense</option>
                                                </select>
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
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Account Code (e.g. 1050)</label>
                        <input type="text" name="code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Name (e.g. Petty Cash)</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Type</label>
                        <select name="type" class="form-select" required>
                            <option value="">Select Type...</option>
                            <option value="Asset">Asset</option>
                            <option value="Liability">Liability</option>
                            <option value="Equity">Equity</option>
                            <option value="Revenue">Revenue</option>
                            <option value="Expense">Expense</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
