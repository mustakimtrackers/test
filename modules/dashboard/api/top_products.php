<?php
/**
 * API: Top 5 Selling Products
 * Returns top 5 products by quantity sold
 */

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isLoggedIn()) {
    jsonResponse(false, null, 'Unauthorized');
}

$branch_sql = isAdmin() ? "" : " AND s.branch_id = " . (int)($_SESSION['branch_id'] ?? 1);

$stmt = $conn->prepare("
    SELECT 
        p.name,
        SUM(si.quantity) as total_sold
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN sales s ON si.sale_id = s.id
    WHERE s.status = 'completed' $branch_sql
    GROUP BY p.id, p.name
    ORDER BY total_sold DESC
    LIMIT 5
");
$stmt->execute();
$result = $stmt->get_result();

$labels = [];
$values = [];

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['name'];
    $values[] = (int)$row['total_sold'];
}
$stmt->close();

// Fallback for empty data
if (empty($labels)) {
    $labels = ['Product A', 'Product B', 'Product C', 'Product D', 'Product E'];
    $values = [0, 0, 0, 0, 0];
}

// Return JSON directly
$data = [
    'labels' => $labels,
    'values' => $values
];

echo json_encode($data);

