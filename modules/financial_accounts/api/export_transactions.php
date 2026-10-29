<?php
/**
 * Export Account Transactions to Excel (CSV)
 */

require_once __DIR__ . '/../../../includes/auth_check.php';

$accountId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($accountId <= 0) {
    die('Invalid account ID');
}

// Get account details
$stmt = $conn->prepare("SELECT * FROM financial_accounts WHERE id = ?");
$stmt->bind_param("i", $accountId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$account) {
    die('Account not found');
}

// Build filter conditions (same as view.php)
$filterConditions = ["ft.financial_account_id = ?"];
$filterParams = [$accountId];
$filterTypes = "i";

if (!empty($_GET['date_from'])) {
    $filterConditions[] = "ft.transaction_date >= ?";
    $filterParams[] = $_GET['date_from'];
    $filterTypes .= "s";
}

if (!empty($_GET['date_to'])) {
    $filterConditions[] = "ft.transaction_date <= ?";
    $filterParams[] = $_GET['date_to'];
    $filterTypes .= "s";
}

if (!empty($_GET['type'])) {
    $filterConditions[] = "ft.transaction_type = ?";
    $filterParams[] = $_GET['type'];
    $filterTypes .= "s";
}

if (!empty($_GET['min_amount'])) {
    $filterConditions[] = "ft.amount >= ?";
    $filterParams[] = (float)$_GET['min_amount'];
    $filterTypes .= "d";
}

$whereClause = implode(" AND ", $filterConditions);

// Get ALL transactions (no limit)
$transStmt = $conn->prepare("
    SELECT 
        ft.*,
        CASE 
            WHEN ft.reference_type = 'sale' THEN CONCAT('Sale #', s.reference_no)
            WHEN ft.reference_type = 'purchase' THEN CONCAT('Purchase #', p.reference_no)
            WHEN ft.reference_type = 'expense' THEN CONCAT('Expense - ', e.category)
            WHEN ft.reference_type = 'opening_balance' THEN 'Opening Balance'
            ELSE ft.reference_type
        END as reference_display
    FROM financial_transactions ft
    LEFT JOIN sales s ON ft.reference_type = 'sale' AND ft.reference_id = s.id
    LEFT JOIN purchases p ON ft.reference_type = 'purchase' AND ft.reference_id = p.id
    LEFT JOIN expenses e ON ft.reference_type = 'expense' AND ft.reference_id = e.id
    WHERE $whereClause
    ORDER BY ft.transaction_date DESC, ft.id DESC
");

$transStmt->bind_param($filterTypes, ...$filterParams);
$transStmt->execute();
$transactions = $transStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$transStmt->close();

// Generate filename
$filename = "Transactions_" . cleanInput($account['account_name']) . "_" . date('Y-m-d') . ".csv";

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header row
fputcsv($output, [
    'Account Name: ' . $account['account_name'],
    'Account Type: ' . ucfirst(str_replace('_', ' ', $account['account_type'])),
    'Current Balance: ৳' . number_format($account['balance'], 2),
    'Generated: ' . date('d M Y H:i')
]);

// Empty row
fputcsv($output, []);

// Column headers
fputcsv($output, [
    'Date',
    'Time',
    'Type',
    'Reference',
    'Amount',
    'Balance Impact',
    'Description'
]);

// Data rows
foreach ($transactions as $trans) {
    fputcsv($output, [
        date('d M Y', strtotime($trans['transaction_date'])),
        date('H:i', strtotime($trans['created_at'])),
        strtoupper($trans['transaction_type']),
        $trans['reference_display'],
        number_format($trans['amount'], 2),
        ($trans['transaction_type'] === 'credit' ? '+' : '-') . number_format($trans['amount'], 2),
        $trans['description']
    ]);
}

// Summary row
fputcsv($output, []);
$creditTotal = 0;
$debitTotal = 0;
foreach ($transactions as $trans) {
    if ($trans['transaction_type'] === 'credit') {
        $creditTotal += $trans['amount'];
    } else {
        $debitTotal += $trans['amount'];
    }
}

fputcsv($output, ['', '', '', 'TOTAL CREDIT:', number_format($creditTotal, 2)]);
fputcsv($output, ['', '', '', 'TOTAL DEBIT:', number_format($debitTotal, 2)]);
fputcsv($output, ['', '', '', 'NET BALANCE:', number_format($creditTotal - $debitTotal, 2)]);

fclose($output);
exit;
?>
