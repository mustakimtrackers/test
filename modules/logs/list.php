<?php
/**
 * Activity Logs Viewer
 * Professional audit trail for all system activities
 */
$pageTitle = 'Activity Logs';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

// Only admins can view logs
if ($_SESSION['role'] !== 'admin') {
    setFlashMessage('error', 'Access denied');
    redirect('/');
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Filters
$action_filter = $_GET['action'] ?? '';
$table_filter = $_GET['table'] ?? '';
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : 0;

// Build query
$where = [];
$params = [];
$types = '';

if (!empty($action_filter)) {
    $where[] = "action LIKE ?";
    $params[] = "%{$action_filter}%";
    $types .= 's';
}

if (!empty($table_filter)) {
    $where[] = "table_name = ?";
    $params[] = $table_filter;
    $types .= 's';
}

if ($user_filter > 0) {
    $where[] = "user_id = ?";
    $params[] = $user_filter;
    $types .= 'i';
}

$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get logs
$sql = "
    SELECT 
        l.*,
        u.name as user_name
    FROM activity_logs l
    LEFT JOIN users u ON l.user_id = u.id
    $whereSQL
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total count
$countSQL = "SELECT COUNT(*) as total FROM activity_logs $whereSQL";
$countStmt = $conn->prepare($countSQL);
if (!empty($where)) {
    // Remove last 2 params (limit/offset) for count
    $countTypes = substr($types, 0, -2);
    $countParams = array_slice($params, 0, -2);
    if (!empty($countParams)) {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$pagination = getPagination($total, $page, $perPage);
$offset = $pagination['offset']; // Override offset

// Get users for filter
$users = $conn->query("SELECT id, name FROM users ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-clock-history"></i> Activity Logs</h1>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Action</label>
                    <input type="text" name="action" class="form-control" 
                           placeholder="e.g., delete, update" value="<?= escape($action_filter) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Table</label>
                    <select name="table" class="form-select">
                        <option value="">All Tables</option>
                        <option value="sales" <?= $table_filter === 'sales' ? 'selected' : '' ?>>Sales</option>
                        <option value="purchases" <?= $table_filter === 'purchases' ? 'selected' : '' ?>>Purchases</option>
                        <option value="products" <?= $table_filter === 'products' ? 'selected' : '' ?>>Products</option>
                        <option value="contacts" <?= $table_filter === 'contacts' ? 'selected' : '' ?>>Contacts</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <select name="user" class="form-select">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                <?= escape($user['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Filter
                    </button>
                    <a href="<?= url('/modules/logs/list.php') ?>" class="btn btn-secondary">
                        <i class="bi bi-x"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <i class="bi bi-clock-history"></i>
                    <p>No activity logs found</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>Record ID</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><small><?= date('d M Y, g:i A', strtotime($log['created_at'])) ?></small></td>
                                <td><?= escape($log['user_name'] ?? 'System') ?></td>
                                <td>
                                    <code class="text-primary"><?= escape($log['action']) ?></code>
                                </td>
                                <td><?= escape($log['table_name'] ?? '-') ?></td>
                                <td><?= $log['record_id'] ?? '-' ?></td>
                                <td><small><?= escape($log['ip_address']) ?></small></td>
                                <td>
                                    <?php if (!empty($log['old_values']) || !empty($log['new_values'])): ?>
                                        <button class="btn btn-sm btn-outline-secondary" 
                                                onclick="showDetails(<?= htmlspecialchars(json_encode([
                                                    'old' => json_decode($log['old_values'], true),
                                                    'new' => json_decode($log['new_values'], true)
                                                ])) ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    <?php endif; ?>
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

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Activity Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Old Values</h6>
                        <pre id="oldValues" class="bg-light p-2 rounded"></pre>
                    </div>
                    <div class="col-md-6">
                        <h6>New Values</h6>
                        <pre id="newValues" class="bg-light p-2 rounded"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showDetails(data) {
    document.getElementById('oldValues').textContent = JSON.stringify(data.old, null, 2) || 'N/A';
    document.getElementById('newValues').textContent = JSON.stringify(data.new, null, 2) || 'N/A';
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
