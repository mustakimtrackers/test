<?php
/**
 * API: Process Capital Add/Withdraw
 */
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/TransactionManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

try {
    $conn->begin_transaction();

    $type = $_POST['type'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $transDate = $_POST['trans_date'] ?? date('Y-m-d');
    $investorId = !empty($_POST['investor_id']) ? (int)$_POST['investor_id'] : null;
    $reference = $_POST['reference'] ?? '';
    $description = $_POST['description'] ?? '';
    $createdBy = $_SESSION['user_id'];

    $paymentAccountId = $_POST['payment_account_id'] ?? 0;

    if (!in_array($type, ['add', 'withdraw'])) {
        throw new Exception("Invalid transaction type selected.");
    }
    
    // Map to exact ENUM values used in database schema
    $dbType = ($type === 'add') ? 'injection' : 'withdrawal';
    
    if ($amount <= 0) {
        throw new Exception("Amount must be greater than zero.");
    }

    if ($paymentAccountId <= 0) {
        throw new Exception("Please select a valid financial account.");
    }

    // Get the account type to save as method
    $accQuery = $conn->query("SELECT account_type FROM financial_accounts WHERE id = $paymentAccountId");
    $accRow = $accQuery->fetch_assoc();
    $primaryMethod = $accRow ? $accRow['account_type'] : 'cash';

    // Insert into capital_transactions
    $stmt = $conn->prepare("INSERT INTO capital_transactions (trans_date, investor_id, type, amount, method, reference, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("sisdisss", $transDate, $investorId, $dbType, $amount, $primaryMethod, $reference, $description, $createdBy);
    
    if (!$stmt->execute()) {
        throw new Exception("Error saving capital transaction: " . $stmt->error);
    }
    $capitalTransId = $stmt->insert_id;

    // Use TransactionManager to update financial balance
    $tm = new TransactionManager($conn);
    
    $finType = ($type === 'add') ? 'credit' : 'debit';
    $refType = ($type === 'add') ? 'capital_injection' : 'capital_withdrawal';
    $descPrefix = ($type === 'add') ? 'Capital Added' : 'Capital Withdrawn';

    $finDesc = "$descPrefix (Ref: $reference)";

    $tm->recordTransaction(
        (int)$paymentAccountId,
        $finType,
        $amount,
        $refType,
        $capitalTransId,
        $finDesc,
        $createdBy
    );

    // Activity Log
    $logAction = ($type === 'add') ? 'Capital Injected' : 'Capital Withdrawn';
    logActivity($logAction, 'capital', $capitalTransId, null, null, "Amount: " . getCurrencySymbol() . number_format($amount, 2) . " via Account ID: " . $paymentAccountId);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Capital successfully " . ($type === 'add' ? 'added' : 'withdrawn') . "."]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
