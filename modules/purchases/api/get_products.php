<?php
/**
 * API: Get Products for Purchases
 * Includes Cost Price
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

$search = cleanInput($_GET['search'] ?? '');
$category = $_GET['category'] ?? 'all';
$supplier = $_GET['supplier'] ?? '';

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

// Filter by supplier removed due to architecture change (Idea 1)

$whereSQL = implode(' AND ', $whereConditions);

$query = "
    SELECT 
        p.id,
        p.name,
        p.sku,
        p.cost_price,
        p.stock_quantity,
        u.short_name as unit
    FROM products p
    LEFT JOIN units u ON p.unit_id = u.id
    WHERE $whereSQL
    ORDER BY p.name ASC
    LIMIT 100
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    // Dynamically bind params
    /* This part is tricky with call_user_func_array and references in strict PHP.
       Let's stick to the simple way used in previous files or improve it.
       The previous file used: $stmt->bind_param($types, ...$params); 
       This works in PHP 7.4+ */
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

echo json_encode(['success' => true, 'products' => $products]);
