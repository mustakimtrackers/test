<?php
/**
 * Delete Investor
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

$id = $_GET['id'] ?? 0;
$token = $_GET['csrf_token'] ?? '';

if (!validateCSRFToken($token)) {
    setFlashMessage('error', 'Invalid security token');
    redirect('investors.php');
}

// Check if investor has transactions
$check = $conn->query("SELECT COUNT(*) as count FROM capital_transactions WHERE investor_id = $id");
$count = $check->fetch_assoc()['count'];

if ($count > 0) {
    // Ideally we shouldn't delete if there are transactions, but for now let's just warn or allow if balance is 0?
    // The UI only shows delete button if balance is 0.
    // But let's be strict: if transactions exist, don't delete to preserve history.
    setFlashMessage('error', 'Cannot delete investor with existing transaction history. Edit name instead to deactivate.');
    redirect('investors.php');
}

$stmt = $conn->prepare("DELETE FROM contacts WHERE id = ? AND type = 'investor'");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    logActivity('delete_investor', 'capital', $id, null, null, "Deleted investor ID: $id");
    setFlashMessage('success', 'Investor deleted successfully');
} else {
    setFlashMessage('error', 'Failed to delete investor');
}

redirect('investors.php');
?>
