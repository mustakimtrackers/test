<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/TransactionManager.php';

session_start();
$_SESSION['user_id'] = 1;

$tm = new TransactionManager($conn);

echo "Starting Sale seeding...\n";

// Get Walk-in Customer
$res = $conn->query("SELECT id FROM contacts WHERE type='customer' ORDER BY id ASC LIMIT 1");
$cust = $res->fetch_assoc();
$customer_id = $cust ? $cust['id'] : 1;

// Get Product
$res = $conn->query("SELECT id, cost_price FROM products LIMIT 1");
$prod = $res->fetch_assoc();
$product_id = $prod['id'];
$cost_price = $prod['cost_price'] ?? 20;

// Get Cash Account
$res = $conn->query("SELECT id FROM financial_accounts LIMIT 1");
$acc = $res->fetch_assoc();
$cashAccountId = $acc['id'];

// Create a Sale
$conn->begin_transaction();
try {
    $total_sale = 150;
    $stmt = $conn->prepare("INSERT INTO sales (customer_id, reference_no, sale_date, total_amount, paid_amount, status, created_by) VALUES (?, 'SALE-DEMO-01', NOW(), ?, ?, 'completed', 1)");
    $stmt->bind_param("idd", $customer_id, $total_sale, $total_sale);
    $stmt->execute();
    $sale_id = $conn->insert_id;

    $stmtItem = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, cost_price, subtotal) VALUES (?, ?, 5, 30, ?, 150)");
    $stmtItem->bind_param("iid", $sale_id, $product_id, $cost_price);
    $stmtItem->execute();

    $conn->query("UPDATE products SET stock_quantity = stock_quantity - 5 WHERE id = $product_id");

    $conn->query("INSERT INTO payments (payable_type, payable_id, amount, method, paid_at) VALUES ('sale', $sale_id, 150, 'cash', NOW())");

    $tm->recordTransaction($cashAccountId, 'credit', $total_sale, 'sale', $sale_id, "Demo Sale Payment", 1);
    $conn->commit();
    echo "Created Sale (ID: $sale_id)\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "Failed to create sale: " . $e->getMessage() . "\n";
}

echo "Demo Sale Seeding Complete!\n";
?>
