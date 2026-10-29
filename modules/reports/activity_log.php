<?php
/**
 * Activity Log Report
 * Admin Only
 */
$pageTitle = 'Activity Log (অ্যাক্টিভিটি লগ)';
$requiredRole = 'admin'; // Only admin can see activity logs
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filters
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build Query
$where = "1=1";
$params = [];
$types = "";

if ($user_id > 0) {
    $where .= " AND l.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

if (!empty($date_from)) {
    $where .= " AND DATE(l.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $where .= " AND DATE(l.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Clear Logs Action
if (isset($_POST['clear_logs']) && isAdmin()) {
    if (validateCSRFToken($_POST['csrf_token'])) {
        $conn->query("TRUNCATE TABLE activity_logs");
        logActivity('clear_logs', 'security', null, null, null, 'Cleared all activity logs');
        setFlashMessage('success', 'Logs cleared successfully');
        redirect($_SERVER['PHP_SELF']);
    }
}

// Get Total Count
$countSql = "SELECT COUNT(*) as total FROM activity_logs l WHERE $where";
$stmt = $conn->prepare($countSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalLogs = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get Logs
// Note: activity_logs table structure might differ. 
// I created: id, user_id, action, details, ip_address, created_at.
// Existing activity_logger.php uses: description, table_name, etc.
// Let's assume the table I created in setup_roles_logs.php is the one being used IF it didn't exist.
// BUT existing activity_logger.php writes to: user_id, action, table_name, record_id, old_values, new_values, description, ip_address, user_agent.
// I should select relevant columns and fallback.
// If I use the *existing* activity_logger.php, it writes 'description' as the details.
// So I should display 'description'.
// And 'action'.
// And 'table_name' maybe?
$sql = "SELECT l.*, u.name as user_name, u.role as user_role 
        FROM activity_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        WHERE $where 
        ORDER BY l.created_at DESC 
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h3 mb-0 text-gray-800 d-inline-block me-2">Activity Logs</h1>
                    <span class="badge bg-primary rounded-pill"><?= number_format($totalLogs) ?> Records</span>
                </div>
                <form method="POST" onsubmit="return confirm('Are you sure you want to delete ALL logs?');">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <button type="submit" name="clear_logs" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash"></i> Clear All Logs
                    </button>
                </form>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">User</label>
                            <select name="user_id" class="form-select">
                                <option value="">All Users</option>
                                <?php
                                $users = $conn->query("SELECT id, name FROM users")->fetch_all(MYSQLI_ASSOC);
                                foreach ($users as $u) {
                                    $selected = $user_id == $u['id'] ? 'selected' : '';
                                    echo "<option value='{$u['id']}' $selected>{$u['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="card shadow mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Date/Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">No logs found</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $index => $log): ?>
                                    <tr>
                                        <td><span class="text-muted small"><?= $offset + $index + 1 ?></span></td>
                                        <td class="small text-nowrap"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></td>
                                        <td>
                                            <span class="fw-bold"><?= escape($log['user_name'] ?? 'Unknown') ?></span>
                                            <br>
                                            <small class="text-muted"><?= ucfirst($log['user_role'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= escape($log['action']) ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            // Handle different detail columns
                                            $details = $log['details'] ?? $log['description'] ?? '';
                                            if (empty($details) && !empty($log['table_name'])) {
                                                $details = "Table: " . $log['table_name'] . " ID: " . $log['record_id'];
                                            }
                                            echo nl2br(escape($details));
                                            ?>
                                        </td>
                                        <td class="small font-monospace"><?= escape($log['ip_address']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <?php 
                    $totalPages = ceil($totalLogs / $limit);
                    if ($totalPages > 1):
                    ?>
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&user_id=<?= $user_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
