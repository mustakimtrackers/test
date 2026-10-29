<?php
require_once __DIR__ . '/../../includes/auth_check.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid security token or request method.');
    redirect(url($_SERVER['HTTP_REFERER'] ?? '/modules/dashboard/index.php'));
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    setFlashMessage('error', 'Invalid expense ID');
    redirect(url('/modules/expenses/list.php'));
}

// 1. Fetch expense details to get amount and date
$stmt = $conn->prepare("
    SELECT e.*, t.financial_account_id 
    FROM expenses e
    LEFT JOIN financial_transactions t ON (t.reference_type = 'expense' AND t.reference_id = e.id)
    WHERE e.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$expenseResult = $stmt->get_result();
$expense = $expenseResult->fetch_assoc();
$stmt->close();

if (!$expense) {
    setFlashMessage('error', 'Expense not found');
    redirect(url('/modules/expenses/list.php'));
}

// 2. Start Transaction
$conn->begin_transaction();

try {
    // 3. Delete Financial Transaction and Restore Balance
    $sqlFin = "SELECT id, financial_account_id, amount, transaction_type FROM financial_transactions WHERE reference_type = 'expense' AND reference_id = ?";
    $stmtFin = $conn->prepare($sqlFin);
    $stmtFin->bind_param("i", $id);
    $stmtFin->execute();
    $resFin = $stmtFin->get_result();

    while ($trans = $resFin->fetch_assoc()) {
        $amount = $trans['amount'];
        $accId = $trans['financial_account_id'];
        
        if ($trans['transaction_type'] === 'debit') {
            // Expense was debit (money out) -> Add back
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

    // 4. Delete the Expense
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete expense record");
    }
    $stmt->close();

    $conn->commit();
    setFlashMessage('success', 'Expense deleted and account balance restored successfully');

} catch (Exception $e) {
    $conn->rollback();
    setFlashMessage('error', 'Failed to delete expense: ' . $e->getMessage());
}

redirect(url('/modules/expenses/list.php'));
