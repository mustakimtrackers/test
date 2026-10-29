<?php
/**
 * Delete Capital Transaction
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$id = $_GET['id'] ?? 0;
$token = $_GET['csrf_token'] ?? '';

if (!validateCSRFToken($token)) {
    setFlashMessage('error', 'Invalid security token');
    redirect('index.php');
}

// Fetch transaction detail for logging
$stmt = $conn->prepare("SELECT * FROM capital_transactions WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();

if (!$transaction) {
    setFlashMessage('error', 'Transaction not found');
    redirect('index.php');
}

$stmt = $conn->prepare("DELETE FROM capital_transactions WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    logActivity('delete_capital_transaction', 'capital', $id, $transaction, null, "Deleted capital transaction ID: $id Amount: {$transaction['amount']}");
    setFlashMessage('success', 'Transaction deleted successfully');
} else {
    setFlashMessage('error', 'Failed to delete transaction' . $conn->error);
}

redirect('index.php');
?>
