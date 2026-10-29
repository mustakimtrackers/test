<?php
/**
 * API: Toggle Account Status (Active/Inactive)
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$accountId = (int)($input['account_id'] ?? 0);
$isActive = (int)($input['is_active'] ?? 0);

if ($accountId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
    exit;
}

// Update status
$stmt = $conn->prepare("UPDATE financial_accounts SET is_active = ? WHERE id = ?");
$stmt->bind_param("ii", $isActive, $accountId);

if ($stmt->execute()) {
    logActivity('update', 'financial_accounts', $accountId, "Status changed to " . ($isActive ? 'Active' : 'Inactive'));
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}
?>
