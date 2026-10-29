<?php
/**
 * Update Purchase Status
 * Handles stock adjustments when status changes
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Invalid request method');
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(false, null, 'Invalid security token');
}

$purchaseId = (int)($_POST['purchase_id'] ?? 0);
$newStatus = $_POST['status'] ?? '';

if ($purchaseId <= 0 || !in_array($newStatus, ['ordered', 'received', 'pending', 'canceled'])) {
    jsonResponse(false, null, 'Invalid input');
}

// Get current status and items
$stmt = $conn->prepare("SELECT status FROM purchases WHERE id = ?");
$stmt->bind_param("i", $purchaseId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    jsonResponse(false, null, 'Purchase not found');
}

$row = $result->fetch_assoc();
$currentStatus = $row['status']; // Allow empty status
$stmt->close();

if ($currentStatus === $newStatus) {
    jsonResponse(true, null, 'Status updated'); // No change needed
}

$conn->begin_transaction();
try {
    // 1. Update Status
    $updateStmt = $conn->prepare("UPDATE purchases SET status = ? WHERE id = ?");
    $updateStmt->bind_param("si", $newStatus, $purchaseId);
    $updateStmt->execute();
    $updateStmt->close();

    // 2. Handle Stock Adjustment
    require_once __DIR__ . '/../../includes/InventoryManager.php';
    $invManager = new InventoryManager($conn);
    
    $stmtItems = $conn->prepare("SELECT product_id, quantity FROM purchase_items WHERE purchase_id = ?");
    $stmtItems->bind_param("i", $purchaseId);
    $stmtItems->execute();
    $items = $stmtItems->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtItems->close();
    if ($newStatus === 'received' && $currentStatus !== 'received') {
        // Add Stock
        foreach ($items as $item) {
            $invManager->addStock(
                $item['product_id'], 
                $item['quantity'], 
                "Received from Purchase #{$purchaseId}", 
                null, 
                'purchase', 
                'purchase', 
                $purchaseId
            );
        }
    } elseif ($currentStatus === 'received' && $newStatus !== 'received') {
        // Remove Stock (Undo receipt)
        foreach ($items as $item) {
            // Check if there is enough stock before removing? 
            // We'll just remove it as per previous logic, but now logged
            $invManager->removeStock(
                $item['product_id'], 
                $item['quantity'], 
                "Undid receipt for Purchase #{$purchaseId}", 
                null, 
                'purchase_undo', 
                'purchase', 
                $purchaseId
            );
        }
    }

    $conn->commit();
    jsonResponse(true, null, 'Status updated successfully');

} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(false, null, 'Error updating status: ' . $e->getMessage());
}
