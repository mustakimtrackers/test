<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin(); // Strict Rule: Only Admin can Delete


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid security token or request method.');
    redirect(url($_SERVER['HTTP_REFERER'] ?? '/modules/dashboard/index.php'));
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    setFlashMessage('error', 'Invalid supplier ID');
    redirect(url('/modules/suppliers/list.php'));
}

$stmt = $conn->prepare("SELECT image FROM contacts WHERE id = ? AND type = 'supplier'");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$imagePath = ($res->num_rows > 0) ? $res->fetch_assoc()['image'] : null;
$stmt->close();

$stmt = $conn->prepare("DELETE FROM contacts WHERE id = ? AND type = 'supplier'");
$stmt->bind_param("i", $id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    if (!empty($imagePath)) {
        deleteUploadedFile($imagePath);
    }
    setFlashMessage('success', 'Supplier deleted successfully');
} else {
    setFlashMessage('error', 'Failed to delete supplier');
}
$stmt->close();

redirect(url('/modules/suppliers/list.php'));
