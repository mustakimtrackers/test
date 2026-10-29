<?php
/**
 * API: Get Products for POS
 * Supports search and category filtering
 */

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$search = cleanInput($_GET['search'] ?? '');
$category = $_GET['category'] ?? 'all';

$whereConditions = ["p.is_active = 1"];
$params = [];
$types = '';

if (!empty($search)) {
    $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $searchParam = "%$search%";
    $params[] = &$searchParam;
    $params[] = &$searchParam;
    $types .= 'ss';
}

if ($category !== 'all' && is_numeric($category)) {
    $categoryInt = (int)$category;
    $whereConditions[] = "p.category_id = ?";
    $params[] = &$categoryInt;
    $types .= 'i';
}

$whereSQL = implode(' AND ', $whereConditions);

$query = "
    SELECT 
        p.id,
        p.name,
        p.sku,
        p.selling_price,
        COALESCE(ps.quantity, 0) as stock_quantity,
        p.alert_quantity,
        p.image
    FROM products p
    LEFT JOIN product_stocks ps ON p.id = ps.product_id AND ps.branch_id = ?
    WHERE $whereSQL
    ORDER BY p.name ASC
    LIMIT 100
";

$branch_id = $_SESSION['branch_id'] ?? 1;

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param("i" . $types, $branch_id, ...$params);
} else {
    $stmt->bind_param("i", $branch_id);
}
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $row['name'] = escape($row['name']);
    $products[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'products' => $products]);
