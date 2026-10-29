<?php
/**
 * Add User
 * Admin Only
 */
$pageTitle = 'Add User';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token';
    } else {
        $name = cleanInput($_POST['name']);
        $email = cleanInput($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $branch_id = (int)($_POST['branch_id'] ?? 1);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) $errors[] = "Name is required";
        if (empty($email)) $errors[] = "Email/Username is required";
        
        // Password validation
        if (empty($password)) {
            $errors[] = "Password is required";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        } elseif (!preg_match('/[\W_]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        // Check uniqueness
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Email/Username already exists";
        }
        $stmt->close();

        if (empty($errors)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $imagePath = null;
            
            // Handle Image Upload
            if (!empty($_FILES['image']['name'])) {
                $upload = handleFileUpload($_FILES['image'], 'users');
                if ($upload) {
                    $imagePath = $upload;
                } else {
                    $errors[] = "Image upload failed. Check file type and size.";
                }
            }

            if (empty($errors)) {
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, is_active, image, branch_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssisi", $name, $email, $hashed_password, $role, $is_active, $imagePath, $branch_id);
                
                if ($stmt->execute()) {
                    logActivity('create_user', 'users', $stmt->insert_id, null, null, "Created user: $name ($role)");
                    setFlashMessage('success', 'User created successfully');
                    redirect(url('/modules/users/index.php'));
                } else {
                    $errors[] = "Database error: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}

$branches = $conn->query("SELECT id, name FROM branches ORDER BY name")->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Add New User</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $e): ?><li><?= escape($e) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3 text-center">
                            <label for="image" class="form-label d-block fw-bold">Profile Image</label>
                            <input type="file" class="form-control" name="image" id="image" accept="image/*">
                            <div class="form-text">Optional. Max 5MB.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?= $_POST['name'] ?? '' ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email / Username</label>
                            <input type="text" name="email" class="form-control" value="<?= $_POST['email'] ?? '' ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="staff" <?= (isset($_POST['role']) && $_POST['role'] == 'staff') ? 'selected' : '' ?>>Staff</option>
                                <option value="cashier" <?= (isset($_POST['role']) && $_POST['role'] == 'cashier') ? 'selected' : '' ?>>Cashier</option>
                                <option value="sales" <?= (isset($_POST['role']) && $_POST['role'] == 'sales') ? 'selected' : '' ?>>Sales</option>
                                <option value="manager" <?= (isset($_POST['role']) && $_POST['role'] == 'manager') ? 'selected' : '' ?>>Manager</option>
                                <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : '' ?>>Admin (Full Access)</option>
                            </select>
                            <div class="form-text">
                                <strong>Staff/Sales:</strong> Can access POS, Customers, and Products.<br>
                                <strong>Cashier:</strong> Can access POS and basic Sales functions.<br>
                                <strong>Manager:</strong> Can access Reports, Finance, and manage Inventory (No user/settings access).<br>
                                <strong>Admin:</strong> Full access to all modules, settings, and logs.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Assign to Branch</label>
                            <select name="branch_id" class="form-select" required>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?= $branch['id'] ?>" <?= (isset($_POST['branch_id']) && $_POST['branch_id'] == $branch['id']) ? 'selected' : '' ?>><?= escape($branch['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-primary"><i class="bi bi-info-circle"></i> This user will only be able to view and manage data (POS, Inventory, Sales) for this specific branch.</div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="activeCheck" checked>
                            <label class="form-check-label" for="activeCheck">Active Account</label>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Create User</button>
                            <a href="<?= url('/modules/users/index.php') ?>" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
