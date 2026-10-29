<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid security token or request method.');
    redirect(url($_SERVER['HTTP_REFERER'] ?? '/modules/dashboard/index.php'));
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    setFlashMessage('error', 'Invalid sale ID');
    redirect(url('/modules/sales/list.php'));
}

// Check if sale exists and get items
$stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) {
    setFlashMessage('error', 'Sale not found');
    redirect(url('/modules/sales/list.php'));
}

// Log activity
logActivity('delete_sale', 'sales', $id, $sale, null);

// Begin transaction
$conn->begin_transaction();

try {
    // ONLY restore stock/balance if sale was NOT already canceled
    // (Because cancelling already restores stock, and draft never deducted stock)
    if ($sale['status'] !== 'canceled' && $sale['status'] !== 'draft') {
        // Get sale items to restore stock
        $itemsStmt = $conn->prepare("SELECT product_id, quantity FROM sale_items WHERE sale_id = ?");
        $itemsStmt->bind_param("i", $id);
        $itemsStmt->execute();
        $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $itemsStmt->close();
        
        $saleBranchId = $sale['branch_id'] ?? 1;
        // Restore stock for each product
        foreach ($items as $item) {
            $updateStock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            $updateStock->bind_param("ii", $item['quantity'], $item['product_id']);
            $updateStock->execute();
            $updateStock->close();

            // Restore branch stock in product_stocks
            $bStockStmt = $conn->prepare("
                INSERT INTO product_stocks (product_id, branch_id, quantity) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE quantity = quantity + ?
            ");
            if ($bStockStmt) {
                $bStockStmt->bind_param("iiii", $item['product_id'], $saleBranchId, $item['quantity'], $item['quantity']);
                $bStockStmt->execute();
                $bStockStmt->close();
            }
            
            // Log stock movement
            $logStmt = $conn->prepare("INSERT INTO stock_movements (product_id, type, quantity, reference_type, reference_id, description) VALUES (?, 'adjustment', ?, 'sale_delete', ?, 'Stock restored from deleted sale')");
            $logStmt->bind_param("iii", $item['product_id'], $item['quantity'], $id);
            $logStmt->execute();
            $logStmt->close();
        }
        
        // Revert Customer Balance (if sale had due/credit)
        $dueAmount = $sale['total_amount'] - $sale['paid_amount'];
        if ($sale['customer_id'] && $dueAmount != 0) {
            $balanceStmt = $conn->prepare("UPDATE contacts SET balance = COALESCE(balance, 0) - ? WHERE id = ?");
            $balanceStmt->bind_param("di", $dueAmount, $sale['customer_id']);
            $balanceStmt->execute();
            $balanceStmt->close();
        }
    }

    // Revert Financial Transaction (if paid)
    // Find transaction associated with this sale
    $sqlFin = "SELECT id, financial_account_id, amount, transaction_type FROM financial_transactions WHERE reference_type = 'sale' AND reference_id = ?";
    $stmtFin = $conn->prepare($sqlFin);
    $stmtFin->bind_param("i", $id);
    $stmtFin->execute();
    $resFin = $stmtFin->get_result();

    while ($trans = $resFin->fetch_assoc()) {
        // Reverse balance
        $amount = $trans['amount'];
        $accId = $trans['financial_account_id'];
        
        if ($trans['transaction_type'] === 'credit') {
            // Was Money In -> Deduct to reverse
            $updateAcc = $conn->prepare("UPDATE financial_accounts SET balance = balance - ? WHERE id = ?");
            $updateAcc->bind_param("di", $amount, $accId);
            $updateAcc->execute();
            $updateAcc->close();
        } elseif ($trans['transaction_type'] === 'debit') {
            // Was Money Out (Refund?) -> Add back
            $updateAcc = $conn->prepare("UPDATE financial_accounts SET balance = balance + ? WHERE id = ?");
            $updateAcc->bind_param("di", $amount, $accId);
            $updateAcc->execute();
            $updateAcc->close();
        }

        // Delete the transaction record
        $delTrans = $conn->prepare("DELETE FROM financial_transactions WHERE id = ?");
        $delTrans->bind_param("i", $trans['id']);
        $delTrans->execute();
        $delTrans->close();
    }
    $stmtFin->close();

    // Always delete any payments records linked to this sale, 
    // regardless of whether the sale was canceled or not
    $delPay = $conn->prepare("DELETE FROM payments WHERE payable_type = 'sale' AND payable_id = ?");
    $delPay->bind_param("i", $id);
    $delPay->execute();
    $delPay->close();


    // Delete sale (CASCADE will delete sale_items automatically)
    $deleteStmt = $conn->prepare("DELETE FROM sales WHERE id = ?");
    $deleteStmt->bind_param("i", $id);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    $conn->commit();
    logActivity('sale_deleted', 'sales', $id, ['reference_no' => $sale['reference_no'], 'total' => $sale['total_amount']], null);
    setFlashMessage('success', 'Sale deleted successfully, stock restored, and customer balance updated');
} catch (Exception $e) {
    $conn->rollback();
    setFlashMessage('error', 'Failed to delete sale: ' . $e->getMessage());
}

redirect(url('/modules/sales/list.php'));
