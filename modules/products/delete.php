<?php
/**
 * Delete Product
 * Soft delete by setting is_active to 0
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check authentication
if (!isLoggedIn()) {
    redirect(url('/modules/auth/login.php'));
}

// Check role (only admin can delete)
if (!isAdmin()) {
    setFlashMessage('error', 'You do not have permission to delete products');
    redirect(url('/modules/products/list.php'));
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid security token or request method.');
    redirect(url($_SERVER['HTTP_REFERER'] ?? '/modules/dashboard/index.php'));
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id > 0) {
    // Soft delete - set is_active to 0 AND rename slug to free up the name
    // Fetch current slug first to append timestamp
    $infoStmt = $conn->prepare("SELECT slug FROM products WHERE id = ?");
    $infoStmt->bind_param("i", $id);
    $infoStmt->execute();
    $prodInfo = $infoStmt->get_result()->fetch_assoc();
    $infoStmt->close();

    if ($prodInfo) {
        $newSlug = $prodInfo['slug'] . '-deleted-' . time();
        
        $stmt = $conn->prepare("UPDATE products SET is_active = 0, slug = ? WHERE id = ?");
        $stmt->bind_param("si", $newSlug, $id);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Product deleted successfully');
        } else {
            setFlashMessage('error', 'Failed to delete product');
        }
        $stmt->close();
    } else {
        setFlashMessage('error', 'Product not found');
    }
} else {
    setFlashMessage('error', 'Invalid product ID');
}

redirect(url('/modules/products/list.php'));
