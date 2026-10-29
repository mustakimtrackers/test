<?php
/**
 * API: Process Sale
 * Creates sale record, sale items, updates stock, and records payment
 */

// Prevent any output before JSON
ob_start();

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

// Clear any accidental output (whitespace, warnings)
ob_clean();

if (!isLoggedIn()) {
    jsonResponse(false, null, 'Unauthorized');
}

// Get JSON data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (empty($data['items'])) {
    jsonResponse(false, null, 'Cart is empty');
}

$customerId = !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
$items = $data['items'];
$totalAmount = (float)$data['total_amount'];
$discountAmount = (float)($data['discount_amount'] ?? 0);
$taxAmount = (float)($data['tax_amount'] ?? 0);
$paymentMethod = $data['payment_method'] ?? 'cash';
$status = $data['status'] ?? 'completed';

// Validate Status
if (!in_array($status, ['completed', 'pending', 'draft'])) {
    $status = 'completed';
}

// Calculate Paid Amount and Validate Payment Sources
$paidAmount = 0;
$hasValidPayment = false;
$financialAccountId = !empty($data['financial_account_id']) ? (int)$data['financial_account_id'] : null;

if (!empty($data['split_payments']) && is_array($data['split_payments'])) {
    foreach ($data['split_payments'] as $split) {
        $amt = (float)($split['amount'] ?? 0);
        $accId = (int)($split['account_id'] ?? 0);
        if ($amt > 0 && $accId > 0) {
            $paidAmount += $amt;
            $hasValidPayment = true;
        }
    }
} elseif ($financialAccountId > 0) {
    // Fallback for older API calls
    $paidAmount = isset($data['paid_amount']) ? (float)$data['paid_amount'] : $totalAmount;
    $hasValidPayment = true;
}

if ($status === 'draft') {
    $paidAmount = 0;
    $hasValidPayment = false;
}

// Validation
if ($status === 'completed' && !$hasValidPayment && $totalAmount > 0) {
    jsonResponse(false, null, 'Payment method is required for completed sales');
}

if ($paidAmount < 0) {
    jsonResponse(false, null, 'Invalid paid amount');
}

// Note: Overpayment (Change) is handled by frontend, backend records strictly what was kept.
$dueAmount = $totalAmount - $paidAmount;
if ($paidAmount > $totalAmount) {
    $paidAmount = $totalAmount; // Record max of total, rest is change
    $dueAmount = 0;
}

// Generate reference number
$referenceNo = generateReference('INV');

// Backdating Logic
$saleDate = date('Y-m-d H:i:s'); // Default
if (!empty($data['sale_date'])) {
    $timestamp = strtotime($data['sale_date']);
    if ($timestamp !== false && date('Y', $timestamp) >= 2000) {
        $saleDate = date('Y-m-d H:i:s', $timestamp);
    } // else keeps default NOW()
}

$createdBy = $_SESSION['user_id'];

// Start transaction
$conn->begin_transaction();

try {
    // 1. Insert sale
    $branch_id = $_SESSION['branch_id'] ?? 1;
    $stmt = $conn->prepare("
        INSERT INTO sales (reference_no, customer_id, sale_date, total_amount, tax_amount, 
                          discount_amount, paid_amount, status, created_by, branch_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sisddddsii",
        $referenceNo, $customerId, $saleDate, $totalAmount,
        $taxAmount, $discountAmount, $paidAmount, $status, $createdBy, $branch_id
    );
    $stmt->execute();
    $saleId = $conn->insert_id;
    $stmt->close();
    
    // 2. Insert sale items and update stock
    // Prepare statement to fetch product details (Commission and Stock Alerts)
    $prodStmt = $conn->prepare("
        SELECT p.name, COALESCE(ps.quantity, 0) as stock_quantity, p.alert_quantity, p.commission_rate, p.commission_type, p.cost_price 
        FROM products p 
        LEFT JOIN product_stocks ps ON p.id = ps.product_id AND ps.branch_id = ? 
        WHERE p.id = ?
    ");

    // Update Insert to include commission_amount
    $itemStmt = $conn->prepare("
        INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, cost_price, subtotal, commission_amount, discount_type, discount_value, discount_amount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stockStmt = $conn->prepare("
        UPDATE product_stocks 
        SET quantity = quantity - ?
        WHERE product_id = ? AND branch_id = ? AND quantity >= ?
    ");
    
    $lowStockItems = [];

    foreach ($items as $item) {
        $productId = (int)$item['id'];
        $quantity = (int)$item['quantity'];
        $unitPrice = (float)$item['price'];
        $subtotal = $unitPrice * $quantity;
        
        // Item Level Discount Logic
        $discountType = $item['discountType'] ?? 'fixed'; // fixed or percentage
        $discountValue = (float)($item['discountValue'] ?? 0);
        $discountAmount = 0;

        if ($discountValue > 0) {
            if ($discountType === 'percentage') {
                $discountAmount = ($subtotal * $discountValue) / 100;
            } else {
                // Fixed discount is PER UNIT (Commission per product)
                $discountAmount = $discountValue * $quantity; 
            }
        }
        
        // Ensure discount doesn't exceed subtotal
        if ($discountAmount > $subtotal) {
            $discountAmount = $subtotal;
        }

        $subtotal = $subtotal - $discountAmount;

        // Fetch Commission Details
        $prodStmt->bind_param("ii", $branch_id, $productId);
        $prodStmt->execute();
        $prodRes = $prodStmt->get_result();
        $product = $prodRes->fetch_assoc();
        
        $commissionAmount = 0;
        $costPrice = 0;
        if ($product) {
            $costPrice = (float)($product['cost_price'] ?? 0);
            $rate = (float)$product['commission_rate'];
            if ($product['commission_type'] === 'fixed') {
                $commissionAmount = $rate * $quantity;
            } else {
                // Percentage of Subtotal (Selling Price * Qty)
                $commissionAmount = ($subtotal * $rate) / 100;
            }
            
            // Check Low Stock Threshold
            $oldStock = (float)$product['stock_quantity'];
            $alertQty = (float)$product['alert_quantity'];
            $newStock = $oldStock - $quantity;
            
            if ($newStock <= $alertQty && $oldStock > $alertQty) {
                // Just crossed the threshold!
                $lowStockItems[] = [
                    'name' => $product['name'],
                    'new_stock' => $newStock,
                    'alert_quantity' => $alertQty
                ];
            }
        }

        // Insert sale item with commission and discount and cost_price
        $itemStmt->bind_param("iiiddddssd", $saleId, $productId, $quantity, $unitPrice, $costPrice, $subtotal, $commissionAmount, $discountType, $discountValue, $discountAmount);
        $itemStmt->execute();
        
        // Update stock (Deduct for both Pending and Completed, skip for Draft)
        if ($status !== 'draft') {
            $stockStmt->bind_param("iiii", $quantity, $productId, $branch_id, $quantity);
            $stockStmt->execute();
            
            if ($stockStmt->affected_rows === 0) {
                throw new Exception("Insufficient branch stock for product ID: $productId");
            }
            
            // Also update global stock as fallback
            $conn->query("UPDATE products SET stock_quantity = stock_quantity - $quantity WHERE id = $productId");
        }
    }
    $prodStmt->close();
    
    $itemStmt->close();
    $stockStmt->close();
    
        // 3. Record payment (Only if paid amount > 0)
    if ($paidAmount > 0) {
        // Determine method (split for multiple, else the first method)
        $methodLabel = 'cash';
        if (isset($data['split_payments']) && is_array($data['split_payments'])) {
            $methodLabel = count($data['split_payments']) > 1 ? 'split' : ($data['split_payments'][0]['method'] ?? 'cash');
        } else {
            $methodLabel = $paymentMethod; // Fallback
        }

        $paymentStmt = $conn->prepare("
            INSERT INTO payments (payable_type, payable_id, amount, method, paid_at)
            VALUES ('sale', ?, ?, ?, ?)
        ");
        $paymentStmt->bind_param("idss", $saleId, $paidAmount, $methodLabel, $saleDate);
        $paymentStmt->execute();
        $paymentStmt->close();

        // --- ACCOUNTING INTEGRATION ---
        require_once __DIR__ . '/../../../includes/TransactionManager.php';
        $tm = new TransactionManager($conn);
        
        if (isset($data['split_payments']) && is_array($data['split_payments']) && count($data['split_payments']) > 0) {
            $remainingToRecord = $paidAmount; // Capped at totalAmount
            foreach ($data['split_payments'] as $split) {
                if ($remainingToRecord <= 0.001) break; // Float safety
                
                $splitAmt = (float)($split['amount'] ?? 0);
                $splitAccId = (int)($split['account_id'] ?? 0);
                
                if ($splitAmt > 0 && $splitAccId > 0) {
                    $recordAmt = min($splitAmt, $remainingToRecord);
                    $tm->recordTransaction(
                        $splitAccId, 
                        'credit', // Money In
                        $recordAmt, 
                        'sale', 
                        $saleId, 
                        "Sale Payment Ref: $referenceNo" . (count($data['split_payments']) > 1 ? " [Split]" : ""),
                        $createdBy
                    );
                    $remainingToRecord -= $recordAmt;
                }
            }
        } elseif ($financialAccountId) {
            // Fallback for older API calls
            $tm->recordTransaction(
                $financialAccountId, 
                'credit', // Money In
                $paidAmount, 
                'sale', 
                $saleId, 
                "Sale Payment Ref: $referenceNo",
                $createdBy
            );
        }
    }

    // 4. Update Customer Balance (Crucial for dynamic balance)
    // If Status is Pending OR Completed with Due -> Add Due to Balance
    if ($customerId && $dueAmount != 0) {
        $balanceStmt = $conn->prepare("UPDATE contacts SET balance = COALESCE(balance, 0) + ? WHERE id = ?");
        $balanceStmt->bind_param("di", $dueAmount, $customerId);
        $balanceStmt->execute();
        $balanceStmt->close();
    }

    $conn->commit();

    // --- LOW STOCK ALERTS ---
    if (!empty($lowStockItems)) {
        require_once __DIR__ . '/../../../includes/email_helper.php';
        
        $checkAlerts = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'enable_low_stock_alerts'");
        $alertsEnabled = ($checkAlerts && $checkAlerts->num_rows > 0) ? $checkAlerts->fetch_assoc()['setting_value'] : '1';
        
        if ($alertsEnabled == '1') {
            $adminRes = $conn->query("SELECT email FROM users WHERE receive_email_alerts = 1 AND email IS NOT NULL AND email != ''");
            if ($adminRes && $adminRes->num_rows > 0) {
                
                $messageHtml = "
                <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <h2 style='color: #dc3545;'>⚠ Low Stock Alert</h2>
                    <p>The following items have just fallen below their required stock levels in the <strong>FOrbit POS</strong> system:</p>
                    <table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%; border-color: #eee;'>
                        <tr style='background-color: #f8f9fa; text-align: left;'>
                            <th>Product</th>
                            <th>Current Stock</th>
                            <th>Alert Threshold</th>
                        </tr>";
                
                foreach ($lowStockItems as $item) {
                    $messageHtml .= "<tr>";
                    $messageHtml .= "<td>" . htmlspecialchars($item['name']) . "</td>";
                    $messageHtml .= "<td style='color: #dc3545; font-weight: bold;'>" . $item['new_stock'] . "</td>";
                    $messageHtml .= "<td>" . $item['alert_quantity'] . "</td>";
                    $messageHtml .= "</tr>";
                }
                
                $messageHtml .= "
                    </table>
                    <p style='margin-top: 20px; font-size: 13px; color: #666;'>Log into your dashboard to update inventory and create purchase orders.</p>
                </div>";
                
                while ($admin = $adminRes->fetch_assoc()) {
                    sendEmail($admin['email'], '⚠ FOrbit POS - Low Stock Alert', $messageHtml);
                }
            }
        }
    }

    // Log Activity (Fail-safe)
    try {
        logActivity('create', 'sales', $saleId, null, null, "Sale Created: $referenceNo");
    } catch (Exception $logEx) {
        // Silently fail logging to allow sale to complete
        error_log("Logging failed for sale $saleId: " . $logEx->getMessage());
    }

    jsonResponse(true, ['sale_id' => $saleId, 'reference_no' => $referenceNo], 'Sale processed successfully');

} catch (Exception $e) {
    $conn->rollback();
    // Log the error
    error_log("Sale Error: " . $e->getMessage());
    jsonResponse(false, null, $e->getMessage());
}
