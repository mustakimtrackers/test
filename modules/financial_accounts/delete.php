<?php
/**
 * Financial Accounts - Delete/Deactivate
 * Deactivate financial account
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();

$accountId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($accountId <= 0) {
    setFlashMessage('error', 'Invalid account ID');
    redirect(url('/modules/financial_accounts/list.php'));
}

// Get account name for logging
$stmt = $conn->prepare("SELECT account_name FROM financial_accounts WHERE id = ?");
$stmt->bind_param("i", $accountId);
$stmt->execute();
$result = $stmt->get_result();
$account = $result->fetch_assoc();
$stmt->close();

if (!$account) {
    setFlashMessage('error', 'Account not found');
    redirect(url('/modules/financial_accounts/list.php'));
}

// Deactivate account (soft delete)
$stmt = $conn->prepare("UPDATE financial_accounts SET is_active = 0 WHERE id = ?");
$stmt->bind_param("i", $accountId);

if ($stmt->execute()) {
    logActivity('delete', 'financial_accounts', $accountId, "Deactivated account: {$account['account_name']}");
    setFlashMessage('success', 'Account deactivated successfully!');
} else {
    setFlashMessage('error', 'Failed to deactivate account');
}

$stmt->close();
redirect(url('/modules/financial_accounts/list.php'));
?>
