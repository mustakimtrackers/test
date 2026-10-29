<?php
/**
 * Delete User
 * Admin Only
 */
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid security token or request method.');
    redirect(url($_SERVER['HTTP_REFERER'] ?? '/modules/dashboard/index.php'));
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Prevent deleting self
if ($id == $_SESSION['user_id']) {
    setFlashMessage('error', 'Cannot delete your own account');
    redirect(url('/modules/users/index.php'));
}

// Fetch user name and image for logging and deletion
$stmt = $conn->prepare("SELECT name, image FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $user = $res->fetch_assoc();
    $name = $user['name'];
    $imagePath = $user['image'] ?? null;
    $stmt->close();
    
    $delStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $delStmt->bind_param("i", $id);
    
    if ($delStmt->execute()) {
        if (!empty($imagePath)) {
            deleteUploadedFile($imagePath);
        }
        logActivity('delete_user', 'users', $id, null, null, "Deleted user: $name (ID: $id)");
        setFlashMessage('success', 'User deleted successfully');
    } else {
        setFlashMessage('error', 'Failed to delete user: ' . $conn->error);
    }
    $delStmt->close();
} else {
    setFlashMessage('error', 'User not found');
}

redirect(url('/modules/users/index.php'));
?>
