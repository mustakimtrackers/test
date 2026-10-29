<?php
/**
 * User Management List
 * Admin Only
 */
$pageTitle = 'User Management';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin(); 

// Get all users with their branch names
$result = $conn->query("
    SELECT u.*, b.name as branch_name 
    FROM users u 
    LEFT JOIN branches b ON u.branch_id = b.id 
    ORDER BY u.name
");
$users = $result->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title mb-0"><i class="bi bi-people"></i> User Management</h1>
                <a href="<?= url('/modules/users/add.php') ?>" class="btn btn-primary d-none d-md-inline-flex align-items-center gap-2">
                    <i class="bi bi-person-plus"></i> Add User
                </a>
            </div>
            
            <!-- Mobile Floating Action Button -->
            <a href="<?= url('/modules/users/add.php') ?>" id="mobileFabBtn" class="btn btn-primary d-md-none position-fixed rounded-circle shadow-lg d-flex align-items-center justify-content-center border-0" style="bottom: 30px; right: 24px; width: 64px; height: 64px; z-index: 1050;">
                <i class="bi bi-person-plus" style="font-size: 2rem;"></i>
            </a>
            
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th class="d-none d-md-table-cell">Email/Username</th>
                                    <th>Role</th>
                                    <th>Branch</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($user['image']) && $user['image'] !== '0'): ?>
                                            <img src="<?= url($user['image']) ?>" alt="User" class="avatar-circle me-2 rounded-circle" style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="avatar-circle me-2 bg-secondary text-white">
                                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-nowrap"><?= escape($user['name']) ?></span>
                                            <span class="d-md-none text-muted small"><?= escape($user['email']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell"><?= escape($user['email']) ?></td>
                                <td>
                                    <?php 
                                    $badgeClass = match($user['role']) {
                                        'admin' => 'bg-danger',
                                        'manager' => 'bg-warning text-dark',
                                        'cashier', 'staff' => 'bg-info text-dark',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= ucfirst(escape($user['role'])) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><i class="bi bi-shop"></i> <?= escape($user['branch_name'] ?? 'Main Branch') ?></span>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="<?= url('/modules/users/edit.php?id=' . $user['id']) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="<?= url('/modules/users/delete.php?id=' . $user['id']) ?>" 
                                               class="btn btn-sm btn-outline-danger delete-btn"
                                               title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this user?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($users)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No users found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
