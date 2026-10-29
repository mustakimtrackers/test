<?php
// demo_data_seeder.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/TransactionManager.php';
require_once __DIR__ . '/includes/InventoryManager.php';

session_start();
$_SESSION['user_id'] = 1;

$tm = new TransactionManager($conn);

echo "Starting demo data seeding...\n";

// 1. Create Financial Accounts
$conn->query("INSERT INTO financial_accounts (account_name, account_number, account_type, balance, is_active) VALUES ('Cash Box', 'CASH-001', 'cash', 10000, 1)");
$cashAccountId = $conn->insert_id;
echo "Created Cash Account (ID: $cashAccountId)\n";

$conn->query("INSERT INTO financial_accounts (account_name, account_number, account_type, balance, is_active) VALUES ('City Bank', 'BANK-999', 'bank', 50000, 1)");

// 2. Create Customers
$conn->query("INSERT INTO contacts (type, name, phone) VALUES ('customer', 'Walk-in Customer', '00000000')");
$walkInCustomerId = $conn->insert_id;
$conn->query("INSERT INTO contacts (type, name, phone) VALUES ('customer', 'Rahim Store', '01711111111')");
$rahimStoreId = $conn->insert_id;
echo "Created Customers\n";

// Get Supplier and Product
$res = $conn->query("SELECT id FROM contacts WHERE type='supplier' LIMIT 1");
$supplier = $res->fetch_assoc();
if (!$supplier) {
    $conn->query("INSERT INTO contacts (type, name) VALUES ('supplier', 'Default Supplier')");
    $supplier_id = $conn->insert_id;
} else {
    $supplier_id = $supplier['id'];
}

$res = $conn->query("SELECT id FROM products LIMIT 1");
$prod = $res->fetch_assoc();
if (!$prod) {
    $conn->query("INSERT INTO products (name, sku, category_id, unit_id) VALUES ('Demo Product', 'DEMO-1', 1, 1)");
    $product_id = $conn->insert_id;
} else {
    $product_id = $prod['id'];
}

// 3. Create a Purchase
$conn->begin_transaction();
try {
    $total_amount = 2000;
    $paid_amount = 2000;
    $stmt = $conn->prepare("INSERT INTO purchases (supplier_id, reference_no, purchase_date, total_amount, paid_amount, status) VALUES (?, 'PUR-DEMO-01', NOW(), ?, ?, 'received')");
    $stmt->bind_param("idd", $supplier_id, $total_amount, $paid_amount);
    $stmt->execute();
    $purchase_id = $conn->insert_id;

    $stmtItem = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, subtotal) VALUES (?, ?, 100, 20, 2000)");
    $stmtItem->bind_param("ii", $purchase_id, $product_id);
    $stmtItem->execute();

    $conn->query("UPDATE products SET stock_quantity = stock_quantity + 100 WHERE id = $product_id");
    
    // Add payment record
    $conn->query("INSERT INTO payments (payable_type, payable_id, amount, method, paid_at) VALUES ('purchase', $purchase_id, 2000, 'cash', NOW())");

    $tm->recordTransaction($cashAccountId, 'debit', $paid_amount, 'purchase', $purchase_id, "Demo Purchase Payment", 1);
    $conn->commit();
    echo "Created Purchase (ID: $purchase_id)\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "Failed to create purchase: " . $e->getMessage() . "\n";
}

// 4. Create a Sale
$conn->begin_transaction();
try {
    $total_sale = 150;
    $stmt = $conn->prepare("INSERT INTO sales (customer_id, invoice_no, sale_date, subtotal, total_amount, paid_amount, payment_status) VALUES (?, 'INV-DEMO-01', NOW(), ?, ?, ?, 'paid')");
    $stmt->bind_param("iddd", $walkInCustomerId, $total_sale, $total_sale, $total_sale);
    $stmt->execute();
    $sale_id = $conn->insert_id;

    $stmtItem = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, 5, 30, 150)");
    $stmtItem->bind_param("ii", $sale_id, $product_id);
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

echo "Demo Data Seeding Complete!\n";
?>
