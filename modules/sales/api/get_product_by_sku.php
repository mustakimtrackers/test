<?php
/**
 * API: Get Product by SKU/Barcode
 */

ob_start();
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

if (!isLoggedIn()) {
    jsonResponse(false, null, 'Unauthorized');
}

$sku = cleanInput($_GET['sku'] ?? '');
$sku = trim($sku); // Extra safety against scanner whitespace

// Debug Logging
$logFile = __DIR__ . '/../../pos_debug.log';
$debugMsg = date('Y-m-d H:i:s') . " - SKU: '{$_GET['sku']}' -> Processed: '{$sku}'\n";
file_put_contents($logFile, $debugMsg, FILE_APPEND);

if (empty($sku)) {
    jsonResponse(false, null, 'SKU is required');
}

$stmt = $conn->prepare("
    SELECT 
        id, name, sku, selling_price, stock_quantity, alert_quantity, image
    FROM products 
    WHERE sku = ? AND is_active = 1
    LIMIT 1
");
$stmt->bind_param("s", $sku);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    file_put_contents($logFile, "Found product for SKU '{$sku}'\n", FILE_APPEND);
    jsonResponse(true, ['product' => $result->fetch_assoc()]);
} else {
    // Check if product exists but is inactive
    $checkInactive = $conn->query("SELECT id FROM products WHERE sku = '$sku'");
    if ($checkInactive->num_rows > 0) {
        file_put_contents($logFile, "Product '{$sku}' exists but is INACTIVE.\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, "Product '{$sku}' NOT FOUND in DB.\n", FILE_APPEND);
    }
    jsonResponse(false, null, 'Product not found');
}

$stmt->close();
