<?php
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

$range = $_GET['range'] ?? 'today';

$currentStart = '';
$currentEnd = '';
$prevStart = '';
$prevEnd = '';

if ($range === 'today') {
    $currentStart = date('Y-m-d');
    $currentEnd = date('Y-m-d');
    $prevStart = date('Y-m-d', strtotime('-1 day'));
    $prevEnd = date('Y-m-d', strtotime('-1 day'));
} elseif ($range === 'week') {
    $currentStart = date('Y-m-d', strtotime('monday this week'));
    $currentEnd = date('Y-m-d', strtotime('sunday this week'));
    $prevStart = date('Y-m-d', strtotime('monday last week'));
    $prevEnd = date('Y-m-d', strtotime('sunday last week'));
} elseif ($range === 'month') {
    $currentStart = date('Y-m-01');
    $currentEnd = date('Y-m-t');
    $prevStart = date('Y-m-01', strtotime('last month'));
    $prevEnd = date('Y-m-t', strtotime('last month'));
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid range']);
    exit;
}

$branch_sql = isAdmin() ? "" : " AND branch_id = " . (int)($_SESSION['branch_id'] ?? 1);

// Current period revenue
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE sale_date >= ? AND sale_date <= ? AND status = 'completed' $branch_sql");
$stmt->bind_param("ss", $currentStart, $currentEnd);
$stmt->execute();
$currentRevenue = $stmt->get_result()->fetch_assoc()['revenue'];
$stmt->close();

// Previous period revenue
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE sale_date >= ? AND sale_date <= ? AND status = 'completed' $branch_sql");
$stmt->bind_param("ss", $prevStart, $prevEnd);
$stmt->execute();
$prevRevenue = $stmt->get_result()->fetch_assoc()['revenue'];
$stmt->close();

// Calculate Change
$changePercent = 0;
$direction = 'neutral';

if ($prevRevenue > 0) {
    $change = $currentRevenue - $prevRevenue;
    $changePercent = ($change / $prevRevenue) * 100;
} elseif ($currentRevenue > 0) {
    $changePercent = 100;
}

if ($changePercent > 0) {
    $direction = 'positive';
} elseif ($changePercent < 0) {
    $direction = 'negative';
}

echo json_encode([
    'success' => true,
    'revenue' => (float)$currentRevenue,
    'formatted_revenue' => formatCurrency($currentRevenue),
    'change_percent' => number_format(abs($changePercent), 1),
    'direction' => $direction
]);
