<?php
/**
 * API: Get Categories for POS
 */

// Clean output buffer
ob_clean();
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $result = $conn->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
    
    if (!$result) {
        throw new Exception($conn->error);
    }
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    echo json_encode(['success' => true, 'categories' => $categories]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to load categories', 'error' => $e->getMessage()]);
}
