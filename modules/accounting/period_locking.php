<?php
$pageTitle = 'Period Locking';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';

if (!isAdmin()) {
    die("Access Denied");
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_period') {
        $name = $conn->real_escape_string($_POST['period_name']);
        $start = $conn->real_escape_string($_POST['start_date']);
        $end = $conn->real_escape_string($_POST['end_date']);
        
        $stmt = $conn->prepare("INSERT INTO financial_periods (period_name, start_date, end_date) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $start, $end);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Period added successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'toggle_lock') {
        $id = (int)$_POST['id'];
        $status = (int)$_POST['status'];
        $new_status = $status === 1 ? 0 : 1;
        $conn->query("UPDATE financial_periods SET is_closed = $new_status WHERE id = $id");
        $action = $new_status === 1 ? "Locked" : "Unlocked";
        $message = "<div class='alert alert-success'>Period $action successfully.</div>";
    }
}

$periods = $conn->query("SELECT * FROM financial_periods ORDER BY start_date DESC")->fetch_all(MYSQLI_ASSOC);
?>
<div class="container-fluid">
    <div class="page-header mb-4">
        <h1 class="page-title"><i class="bi bi-lock"></i> Financial Period Locking</h1>
    </div>
    
    <?= $message ?>
    
    <div class="row">
        <!-- Add New Period -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Create New Period</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_period">
                        <div class="mb-3">
                            <label class="form-label">Period Name</label>
                            <input type="text" name="period_name" class="form-control" placeholder="e.g. FY 2026" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Create Period</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Period List -->
        <div class="col-md-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Manage Periods</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Period Name</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periods as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['period_name']) ?></strong></td>
                                <td><?= date('d M Y', strtotime($p['start_date'])) ?></td>
                                <td><?= date('d M Y', strtotime($p['end_date'])) ?></td>
                                <td>
                                    <?php if ($p['is_closed']): ?>
                                        <span class="badge bg-danger"><i class="bi bi-lock-fill"></i> Closed (Locked)</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="bi bi-unlock-fill"></i> Open</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_lock">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $p['is_closed'] ?>">
                                        <?php if ($p['is_closed']): ?>
                                            <button type="submit" class="btn btn-sm btn-outline-success">Unlock Period</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Close Period</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($periods)): ?>
                            <tr><td colspan="5" class="text-center py-3">No financial periods defined.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
