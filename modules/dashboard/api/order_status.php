<?php
/**
 * API: Order Status Distribution
 * Returns count of orders by status
 */

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isLoggedIn()) {
    jsonResponse(false, null, 'Unauthorized');
}

$branch_sql = isAdmin() ? "" : " WHERE branch_id = " . (int)($_SESSION['branch_id'] ?? 1);

$stmt = $conn->prepare("
    SELECT 
        status,
        COUNT(*) as count
    FROM sales
    $branch_sql
    GROUP BY status
");
$stmt->execute();
$result = $stmt->get_result();

$data = [
    'completed' => 0,
    'pending' => 0,
    'canceled' => 0
];

while ($row = $result->fetch_assoc()) {
    $data[$row['status']] = (int)$row['count'];
}
$stmt->close();

// Return JSON directly
echo json_encode($data);

