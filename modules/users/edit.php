<?php
/**
 * Edit User
 * Admin Only
 */
$pageTitle = 'Edit User';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    redirect(url('/modules/users/index.php'));
}

require_once __DIR__ . '/../../includes/GoogleAuthenticator.php';
$ga = new PHPGangsta_GoogleAuthenticator();

// Fetch user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    setFlashMessage('error', 'User not found');
    redirect(url('/modules/users/index.php'));
}

$errors = [];
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'enable_2fa') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token';
        } else {
            $secret = $_POST['secret'];
            $code = $_POST['code'];
            if ($ga->verifyCode($secret, $code, 2)) {
                $stmt = $conn->prepare("UPDATE users SET two_factor_secret = ? WHERE id = ?");
                $stmt->bind_param("si", $secret, $id);
                $stmt->execute();
                $stmt->close();
                $user['two_factor_secret'] = $secret;
                $success_msg = 'Two-Factor Authentication enabled successfully.';
            } else {
                $errors[] = 'Invalid 2FA code. Please try again.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'disable_2fa') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token';
        } else {
            $stmt = $conn->prepare("UPDATE users SET two_factor_secret = NULL WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            $user['two_factor_secret'] = null;
            $success_msg = 'Two-Factor Authentication disabled successfully.';
        }
    } else {
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
        
        // Password validation (only if changing)
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters long";
            } elseif (!preg_match('/[0-9]/', $password)) {
                $errors[] = "Password must contain at least one number";
            } elseif (!preg_match('/[\W_]/', $password)) {
                $errors[] = "Password must contain at least one special character";
            }
        }
        
        // Check uniqueness excluding current user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Email/Username already exists";
        }
        $stmt->close();

        if (empty($errors)) {
            $sql = "UPDATE users SET name = ?, email = ?, role = ?, is_active = ?, branch_id = ? WHERE id = ?";
            $params = [$name, $email, $role, $is_active, $branch_id, $id];
            $types = "sssiii";
            
            // Handle Password Change
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET name = ?, email = ?, password = ?, role = ?, is_active = ?, branch_id = ? WHERE id = ?";
                $params = [$name, $email, $hashed_password, $role, $is_active, $branch_id, $id];
                $types = "ssssiii";
            }
            
            // Handle Image Upload
            $newImageUploaded = false;
            if (!empty($_FILES['image']['name'])) {
                $upload = handleFileUpload($_FILES['image'], 'users');
                if ($upload) {
                    $imagePath = $upload;
                    $newImageUploaded = true;
                    // Append image update to query
                    // We need to rebuild the query slightly. 
                    // Easiest is to execute a separate update or modify logic above.
                    // Let's modify logic above to be more flexible? 
                    // No, let's just create a dynamic update array.
                    
                    // Actually, let's just append to the existing params/sql for this turn.
                    if (!empty($password)) {
                         $sql = "UPDATE users SET name = ?, email = ?, password = ?, role = ?, is_active = ?, branch_id = ?, image = ? WHERE id = ?";
                         $params = [$name, $email, $hashed_password, $role, $is_active, $branch_id, $imagePath, $id];
                         $types = "ssssiisi"; 
                    } else {
                         $sql = "UPDATE users SET name = ?, email = ?, role = ?, is_active = ?, branch_id = ?, image = ? WHERE id = ?";
                         $params = [$name, $email, $role, $is_active, $branch_id, $imagePath, $id];
                         $types = "sssiisi"; 
                    }
                } else {
                    $errors[] = "Image upload failed.";
                }
            }

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                if ($newImageUploaded && !empty($user['image'])) {
                    deleteUploadedFile($user['image']);
                }
                
                // Update session if editing current user
                if ($id == $_SESSION['user_id']) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = $role;
                    if (isset($imagePath)) {
                        $_SESSION['user_image'] = $imagePath;
                    }
                }

                logActivity('update_user', "Updated user: $name ($role)");
                setFlashMessage('success', 'User updated successfully');
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
                    <h4 class="card-title mb-0">Edit User</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= escape($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($success_msg)): ?>
                        <div class="alert alert-success">
                            <?= escape($success_msg) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-4 text-center">
                            <label for="image" class="form-label d-block fw-bold position-relative">
                                <?php if (!empty($user['image'])): ?>
                                    <img src="<?= url($user['image']) ?>" alt="Profile" class="rounded-circle mb-3 border shadow-sm" style="width: 100px; height: 100px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle mb-3 border shadow-sm d-inline-flex align-items-center justify-content-center bg-light text-secondary" style="width: 100px; height: 100px; font-size: 2.5rem;">
                                        <i class="bi bi-person"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="small text-muted mb-2">Change Profile Image</div>
                            </label>
                            <input type="file" class="form-control form-control-sm w-75 mx-auto" name="image" id="image" accept="image/*">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?= escape($user['name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email / Username</label>
                            <input type="text" name="email" class="form-control" value="<?= escape($user['email']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password <small class="text-muted">(Leave blank to keep current)</small></label>
                            <input type="password" name="password" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="staff" <?= $user['role'] == 'staff' ? 'selected' : '' ?>>Staff</option>
                                <option value="cashier" <?= $user['role'] == 'cashier' ? 'selected' : '' ?>>Cashier</option>
                                <option value="sales" <?= $user['role'] == 'sales' ? 'selected' : '' ?>>Sales</option>
                                <option value="manager" <?= $user['role'] == 'manager' ? 'selected' : '' ?>>Manager</option>
                                <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin (Full Access)</option>
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
                                    <option value="<?= $branch['id'] ?>" <?= $user['branch_id'] == $branch['id'] ? 'selected' : '' ?>><?= escape($branch['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-primary"><i class="bi bi-info-circle"></i> This user will only be able to view and manage data (POS, Inventory, Sales) for this specific branch.</div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="activeCheck" <?= $user['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activeCheck">Active Account</label>
                        </div>
                        
                        <div class="mb-4 form-check mt-3 bg-light p-3 rounded border">
                            <input type="checkbox" name="receive_email_alerts" class="form-check-input ms-1" id="emailAlertCheck" <?= isset($user['receive_email_alerts']) && $user['receive_email_alerts'] ? 'checked' : '' ?>>
                            <label class="form-check-label ms-4 fw-bold text-primary" for="emailAlertCheck">Receive Admin Email Alerts</label>
                            <div class="form-text ms-4"><i class="bi bi-info-circle"></i> If checked, this user will receive automated Low Stock warnings and the Daily Admin Report emails.</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Update User</button>
                            <a href="<?= url('/modules/users/index.php') ?>" class="btn btn-light">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 2FA Section -->
            <div class="card mt-4">
                <div class="card-header bg-danger text-white">
                    <h4 class="card-title mb-0"><i class="bi bi-shield-lock"></i> Two-Factor Authentication (2FA)</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($user['two_factor_secret'])): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill"></i> Two-Factor Authentication is currently <strong>Enabled</strong>.
                        </div>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to disable 2FA for this user?');">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="disable_2fa">
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Disable 2FA</button>
                        </form>
                    <?php else: 
                        $secret = $ga->createSecret();
                        $qrCodeUrl = $ga->getQRCodeGoogleUrl(getSetting('company_name', 'ERP'), $secret);
                    ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> Two-Factor Authentication is currently <strong>Disabled</strong>.
                        </div>
                        <p>To enable 2FA, scan the QR code below with Google Authenticator or Authy, then enter the 6-digit code.</p>
                        
                        <div class="text-center mb-4">
                            <img src="<?= $qrCodeUrl ?>" alt="QR Code" class="border p-2 bg-white rounded shadow-sm">
                            <div class="mt-2 text-muted font-monospace small">Secret: <?= $secret ?></div>
                        </div>
                        
                        <form method="POST" class="row justify-content-center">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="enable_2fa">
                            <input type="hidden" name="secret" value="<?= $secret ?>">
                            
                            <div class="col-md-6 text-center">
                                <input type="text" name="code" class="form-control form-control-lg text-center letter-spacing-2 mb-3" placeholder="000000" maxlength="6" required autocomplete="off">
                                <button type="submit" class="btn btn-primary w-100">Verify & Enable 2FA</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
