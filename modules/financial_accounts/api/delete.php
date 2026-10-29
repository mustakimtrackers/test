<?php
/**
 * API: Delete Account (Password Protected - Hard Delete)
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin privileges required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$accountId = (int)($input['account_id'] ?? 0);
$password = $input['password'] ?? '';

if ($accountId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
    exit;
}

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password required']);
    exit;
}

// Verify password
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password']);
    exit;
}

// Get account name for logging
$stmt = $conn->prepare("SELECT account_name FROM financial_accounts WHERE id = ?");
$stmt->bind_param("i", $accountId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$account) {
    echo json_encode(['success' => false, 'message' => 'Account not found']);
    exit;
}

// Delete transactions first (foreign key)
$conn->query("DELETE FROM financial_transactions WHERE financial_account_id = $accountId");

// Delete account
$stmt = $conn->prepare("DELETE FROM financial_accounts WHERE id = ?");
$stmt->bind_param("i", $accountId);

if ($stmt->execute()) {
    logActivity('delete', 'financial_accounts', $accountId, "HARD DELETE: {$account['account_name']}");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete account']);
}
?>
