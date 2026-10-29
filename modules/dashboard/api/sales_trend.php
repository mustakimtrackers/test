<?php
/**
 * API: Sales Trend Data
 * Returns sales data for the last N days
 */

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isLoggedIn()) {
    jsonResponse(false, null, 'Unauthorized');
}

$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$days = min(max($days, 7), 90); // Between 7 and 90 days

$labels = [];
$values = [];

$branch_sql = isAdmin() ? "" : " AND branch_id = " . (int)($_SESSION['branch_id'] ?? 1);

// Get sales data for last N days
$stmt = $conn->prepare("
    SELECT DATE(sale_date) as date, COALESCE(SUM(total_amount), 0) as revenue
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    AND status = 'completed' $branch_sql
    GROUP BY DATE(sale_date)
    ORDER BY date ASC
");
$stmt->bind_param("i", $days);
$stmt->execute();
$result = $stmt->get_result();

// Create date map
$salesMap = [];
while ($row = $result->fetch_assoc()) {
    $salesMap[$row['date']] = (float)$row['revenue'];
}
$stmt->close();

// Fill in all dates (including days with zero sales)
for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('M j', strtotime($date));
    $values[] = isset($salesMap[$date]) ? $salesMap[$date] : 0;
}

// Return JSON directly
$data = [
    'labels' => $labels,
    'values' => $values
];

echo json_encode($data);

