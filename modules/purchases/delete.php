<?php
require_once __DIR__ . '/../../includes/auth_check.php';

requireAdmin(); // Strict Rule: Only Admin can Delete


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid security token or request method.');
    redirect(url($_SERVER['HTTP_REFERER'] ?? '/modules/dashboard/index.php'));
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    setFlashMessage('error', 'Invalid purchase ID');
    redirect(url('/modules/purchases/list.php'));
}

// Check if purchase exists
$stmt = $conn->prepare("SELECT * FROM purchases WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$purchase = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$purchase) {
    setFlashMessage('error', 'Purchase not found');
    redirect(url('/modules/purchases/list.php'));
}

// Begin transaction
$conn->begin_transaction();

try {
    // Get purchase items to reduce stock
    $itemsStmt = $conn->prepare("SELECT product_id, quantity FROM purchase_items WHERE purchase_id = ?");
    $itemsStmt->bind_param("i", $id);
    $itemsStmt->execute();
    $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemsStmt->close();
    
    // Reduce stock for each product ONLY if status was 'received'
    if ($purchase['status'] === 'received') {
        foreach ($items as $item) {
            $updateStock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $updateStock->bind_param("ii", $item['quantity'], $item['product_id']);
            $updateStock->execute();
            $updateStock->close();
            
            // Log stock movement
            $logStmt = $conn->prepare("INSERT INTO stock_movements (product_id, type, quantity, reference_type, reference_id, description) VALUES (?, 'adjustment', ?, 'purchase_delete', ?, 'Stock reduced from deleted purchase')");
            $negQty = -$item['quantity'];
            $logStmt->bind_param("iii", $item['product_id'], $negQty, $id);
            $logStmt->execute();
            $logStmt->close();
        }
    }

    // 2. Reverse Supplier Balance (We owe them less)
    $dueAmount = $purchase['total_amount'] - $purchase['paid_amount'];
    if ($purchase['supplier_id'] && $dueAmount != 0) {
        $stmtBal = $conn->prepare("UPDATE contacts SET balance = balance - ? WHERE id = ?");
        $stmtBal->bind_param("di", $dueAmount, $purchase['supplier_id']);
        $stmtBal->execute();
        $stmtBal->close();
    }

    // 3. Reverse Payments / Financial Transactions
    $sqlFin = "SELECT id, financial_account_id, amount, transaction_type FROM financial_transactions WHERE reference_type = 'purchase' AND reference_id = ?";
    $stmtFin = $conn->prepare($sqlFin);
    $stmtFin->bind_param("i", $id);
    $stmtFin->execute();
    $resFin = $stmtFin->get_result();

    while ($trans = $resFin->fetch_assoc()) {
        $amount = $trans['amount'];
        $accId = $trans['financial_account_id'];

        if ($trans['transaction_type'] === 'debit') {
            $updateAcc = $conn->prepare("UPDATE financial_accounts SET balance = balance + ? WHERE id = ?");
            $updateAcc->bind_param("di", $amount, $accId);
            $updateAcc->execute();
            $updateAcc->close();
        } elseif ($trans['transaction_type'] === 'credit') {
            $updateAcc = $conn->prepare("UPDATE financial_accounts SET balance = balance - ? WHERE id = ?");
            $updateAcc->bind_param("di", $amount, $accId);
            $updateAcc->execute();
            $updateAcc->close();
        }

        $delTrans = $conn->prepare("DELETE FROM financial_transactions WHERE id = ?");
        $delTrans->bind_param("i", $trans['id']);
        $delTrans->execute();
        $delTrans->close();
    }
    $stmtFin->close();

    // Delete Payments Record
    $delPay = $conn->prepare("DELETE FROM payments WHERE payable_type = 'purchase' AND payable_id = ?");
    $delPay->bind_param("i", $id);
    $delPay->execute();
    $delPay->close();
    
    // Delete purchase (CASCADE will delete purchase_items automatically)
    $deleteStmt = $conn->prepare("DELETE FROM purchases WHERE id = ?");
    $deleteStmt->bind_param("i", $id);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    $conn->commit();
    logActivity('purchase_deleted', 'purchases', $id, ['reference_no' => $purchase['reference_no'], 'total' => $purchase['total_amount']], null);
    setFlashMessage('success', 'Purchase deleted successfully and stock adjusted');
} catch (Exception $e) {
    $conn->rollback();
    setFlashMessage('error', 'Failed to delete purchase: ' . $e->getMessage());
}

redirect(url('/modules/purchases/list.php'));
