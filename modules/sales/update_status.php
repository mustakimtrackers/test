<?php
/**
 * API: Update Sale Status
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

// Strict Rule: Only Admin can change status (Edit/Delete equivalent)
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied: Admin privileges required']);
    exit;
}

// Check if POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get Data
$input = json_decode(file_get_contents('php://input'), true);
$saleId = isset($input['sale_id']) ? (int)$input['sale_id'] : 0;
$status = isset($input['status']) ? $input['status'] : '';

$headers = getallheaders();
$csrfToken = $headers['X-CSRF-TOKEN'] ?? ($input['csrf_token'] ?? '');
if (!validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Validation
if ($saleId <= 0 || !in_array($status, ['pending', 'completed', 'canceled'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Start Transaction
$conn->begin_transaction();

try {
    // 1. Get current status and details
    $stmt = $conn->prepare("SELECT status, customer_id, total_amount, paid_amount FROM sales WHERE id = ?");
    $stmt->bind_param("i", $saleId);
    $stmt->execute();
    $sale = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sale) {
        throw new Exception("Sale not found");
    }

    $oldStatus = $sale['status'];
    $customerId = $sale['customer_id'];
    $dueAmount = $sale['total_amount'] - $sale['paid_amount'];

    // 2. Update the status
    $updateStmt = $conn->prepare("UPDATE sales SET status = ? WHERE id = ?");
    $updateStmt->bind_param("si", $status, $saleId);
    $updateStmt->execute();
    $updateStmt->close();

    // 3. Handle Stock & Balance Updates based on Status Change
    
    // CASE A: Cancelling a Sale (Void)
    if ($status === 'canceled' && $oldStatus !== 'canceled') {
        // 1. Restore Stock (only if it was not draft, because draft never deducted stock)
        if ($oldStatus !== 'draft') {
            $saleBranchId = $sale['branch_id'] ?? 1;
            $itemsStmt = $conn->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ?");
            $itemsStmt->bind_param("i", $saleId);
            $itemsStmt->execute();
            $result = $itemsStmt->get_result();
            
            $restoreStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            $restoreBranchStmt = $conn->prepare("
                INSERT INTO product_stocks (product_id, branch_id, quantity) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE quantity = quantity + ?
            ");
            $logStmt = $conn->prepare("INSERT INTO stock_movements (product_id, type, quantity, reference_type, reference_id, description) VALUES (?, 'return', ?, 'sale_cancel', ?, 'Stock restored from sale cancellation')");

            while ($row = $result->fetch_assoc()) {
                $restoreStmt->bind_param("ii", $row['quantity'], $row['product_id']);
                $restoreStmt->execute();

                if ($restoreBranchStmt) {
                    $restoreBranchStmt->bind_param("iiii", $row['product_id'], $saleBranchId, $row['quantity'], $row['quantity']);
                    $restoreBranchStmt->execute();
                }

                $logStmt->bind_param("iii", $row['product_id'], $row['quantity'], $saleId);
                $logStmt->execute();
            }
            $itemsStmt->close();
            $restoreStmt->close();
            if ($restoreBranchStmt) $restoreBranchStmt->close();
            $logStmt->close();
        }

        // 2. Revert Customer Balance (Remove Total Amount)
        // By removing the total amount, any previously paid amount becomes an advance (negative balance).
        $totalAmount = $sale['total_amount'];
        if ($customerId && $totalAmount != 0) {
            $balStmt = $conn->prepare("UPDATE contacts SET balance = balance - ? WHERE id = ?");
            $balStmt->bind_param("di", $totalAmount, $customerId);
            $balStmt->execute();
            $balStmt->close();
        }
        
        // We DO NOT delete payments or financial transactions, nor do we zero out paid_amount.
        // The cash received remains in our accounts, and the customer has a credit balance.
    }

    // CASE B: Re-activating a Canceled Sale (Un-Cancel)
    elseif ($oldStatus === 'canceled' && $status !== 'canceled') {
        // We do not un-cancel draft, so it must be pending/completed
        
        // 1. Get Items
        $itemsStmt = $conn->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ?");
        $itemsStmt->bind_param("i", $saleId);
        $itemsStmt->execute();
        $result = $itemsStmt->get_result();
        $items = $result->fetch_all(MYSQLI_ASSOC);
        $itemsStmt->close();

        // 2. Check & Deduct Stock
        $saleBranchId = $sale['branch_id'] ?? 1;
        $checkStmt = $conn->prepare("SELECT stock_quantity, name FROM products WHERE id = ?");
        $deductStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
        $deductBranchStmt = $conn->prepare("
            INSERT INTO product_stocks (product_id, branch_id, quantity) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE quantity = quantity - ?
        ");
        $logStmt = $conn->prepare("INSERT INTO stock_movements (product_id, type, quantity, reference_type, reference_id, description) VALUES (?, 'sale', ?, 'sale_uncancel', ?, 'Stock deducted from sale un-cancellation')");
        
        foreach ($items as $item) {
            $checkStmt->bind_param("i", $item['product_id']);
            $checkStmt->execute();
            $prod = $checkStmt->get_result()->fetch_assoc();
            
            if ($prod['stock_quantity'] < $item['quantity']) {
                throw new Exception("Insufficient stock to un-cancel. Product '{$prod['name']}' has only {$prod['stock_quantity']} left.");
            }

            $deductStmt->bind_param("ii", $item['quantity'], $item['product_id']);
            $deductStmt->execute();

            if ($deductBranchStmt) {
                $negQty = (int)-$item['quantity'];
                $deductBranchStmt->bind_param("iiii", $item['product_id'], $saleBranchId, $negQty, $item['quantity']);
                $deductBranchStmt->execute();
            }

            $logStmt->bind_param("iii", $item['product_id'], $item['quantity'], $saleId);
            $logStmt->execute();
        }
        $checkStmt->close();
        $deductStmt->close();
        if ($deductBranchStmt) $deductBranchStmt->close();
        $logStmt->close();

        // 3. Re-apply Customer Balance (Add Total Amount — all is now due)
        // On cancel: paid_amount was zeroed, payments deleted, financial transactions reversed.
        // On un-cancel: sale reopens as FULLY DUE. Previous payment history is gone.
        // The customer must re-pay via the payment module. This is intentional by design.
        $totalAmount = $sale['total_amount'];
        if ($customerId && $totalAmount != 0) {
            $balStmt = $conn->prepare("UPDATE contacts SET balance = balance + ? WHERE id = ?");
            $balStmt->bind_param("di", $totalAmount, $customerId);
            $balStmt->execute();
            $balStmt->close();
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
