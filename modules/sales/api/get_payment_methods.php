<?php
/**
 * API: Get Payment Methods (Active Financial Accounts)
 * For POS payment method selection
 */

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Check auth
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get all active financial accounts
$query = "
    SELECT 
        id,
        account_name,
        account_type,
        balance
    FROM financial_accounts
    WHERE is_active = 1
    ORDER BY 
        CASE account_type
            WHEN 'cash' THEN 1
            WHEN 'bank' THEN 2
            WHEN 'mobile_banking' THEN 3
            ELSE 4
        END,
        account_name
";

$result = $conn->query($query);
$accounts = [];

while ($row = $result->fetch_assoc()) {
    $accounts[] = $row;
}

echo json_encode([
    'success' => true,
    'accounts' => $accounts
]);
?>
