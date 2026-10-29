<?php
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/email_helper.php';

// Only admins can trigger this
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$today = date('Y-m-d');
$todayDisplay = date('l, F j, Y');

/**
 * 1. Gather Data
 */
 
// Total Sales Today
$salesStmt = $conn->prepare("SELECT SUM(total_amount) as total FROM sales WHERE DATE(sale_date) = ? AND status = 'completed'");
$salesStmt->bind_param("s", $today);
$salesStmt->execute();
$totalSales = (float)$salesStmt->get_result()->fetch_assoc()['total'];
$salesStmt->close();

// Total Collections (Money In)
$collStmt = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE payable_type IN ('sale', 'customer_payment') AND DATE(paid_at) = ?");
$collStmt->bind_param("s", $today);
$collStmt->execute();
$totalCollection = (float)$collStmt->get_result()->fetch_assoc()['total'];
$collStmt->close();

// Total Expenses
$expStmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE DATE(expense_date) = ?");
$expStmt->bind_param("s", $today);
$expStmt->execute();
$totalExpenses = (float)$expStmt->get_result()->fetch_assoc()['total'];
$expStmt->close();

// Cash In Hand (Sum of all Cash accounts)
$cashStmt = $conn->query("SELECT SUM(balance) as total FROM financial_accounts WHERE type = 'cash' AND status = 'active'");
$cashInHand = (float)($cashStmt ? $cashStmt->fetch_assoc()['total'] : 0);

// Top Dues (Top 5 Customers owing us money)
$duesResult = $conn->query("SELECT name, phone, balance FROM contacts WHERE type = 'customer' AND balance > 0 ORDER BY balance DESC LIMIT 5");
$topDues = [];
if ($duesResult) {
    while ($row = $duesResult->fetch_assoc()) {
        $topDues[] = $row;
    }
}

// Low Stock Summary
$stockResult = $conn->query("SELECT name, stock_quantity, alert_quantity FROM products WHERE stock_quantity <= alert_quantity ORDER BY stock_quantity ASC LIMIT 10");
$lowStock = [];
if ($stockResult) {
    while ($row = $stockResult->fetch_assoc()) {
        $lowStock[] = $row;
    }
}

/**
 * 2. Generate Beautiful HTML Email
 */
$html = "
<div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;'>
    <div style='background-color: #0d6efd; color: #fff; padding: 20px; text-align: center;'>
        <h2 style='margin: 0;'>Daily Admin Report</h2>
        <p style='margin: 5px 0 0 0; opacity: 0.9;'>$todayDisplay</p>
    </div>
    
    <div style='padding: 20px;'>
        <h3 style='border-bottom: 2px solid #eee; padding-bottom: 5px; color: #0d6efd;'>Financial Overview</h3>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
            <tr>
                <td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Total Sales:</strong></td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right; color: #198754; font-weight: bold;'>৳ " . number_format($totalSales, 2) . "</td>
            </tr>
            <tr>
                <td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Total Collection:</strong></td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right; color: #0d6efd; font-weight: bold;'>৳ " . number_format($totalCollection, 2) . "</td>
            </tr>
            <tr>
                <td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Total Expenses:</strong></td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right; color: #dc3545; font-weight: bold;'>৳ " . number_format($totalExpenses, 2) . "</td>
            </tr>
            <tr style='background-color: #f8f9fa;'>
                <td style='padding: 10px; border-bottom: 1px solid #eee;'><strong>Cash in Register:</strong></td>
                <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;'>৳ " . number_format($cashInHand, 2) . "</td>
            </tr>
        </table>
";

if (!empty($topDues)) {
    $html .= "
        <h3 style='border-bottom: 2px solid #eee; padding-bottom: 5px; color: #dc3545; margin-top: 30px;'>Top 5 Customer Dues</h3>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;'>
            <tr style='background-color: #f8f9fa;'>
                <th style='padding: 8px; text-align: left; border: 1px solid #eee;'>Customer</th>
                <th style='padding: 8px; text-align: right; border: 1px solid #eee;'>Due Amount</th>
            </tr>";
    foreach ($topDues as $due) {
        $html .= "
            <tr>
                <td style='padding: 8px; border: 1px solid #eee;'>" . htmlspecialchars($due['name']) . "</td>
                <td style='padding: 8px; text-align: right; color: #dc3545; font-weight: bold; border: 1px solid #eee;'>৳ " . number_format($due['balance'], 2) . "</td>
            </tr>";
    }
    $html .= "</table>";
}

if (!empty($lowStock)) {
    $html .= "
        <h3 style='border-bottom: 2px solid #eee; padding-bottom: 5px; color: #fd7e14; margin-top: 30px;'>Low Stock Items (Top 10)</h3>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;'>
            <tr style='background-color: #f8f9fa;'>
                <th style='padding: 8px; text-align: left; border: 1px solid #eee;'>Product</th>
                <th style='padding: 8px; text-align: center; border: 1px solid #eee;'>Stock</th>
            </tr>";
    foreach ($lowStock as $item) {
        $html .= "
            <tr>
                <td style='padding: 8px; border: 1px solid #eee;'>" . htmlspecialchars($item['name']) . "</td>
                <td style='padding: 8px; text-align: center; color: #dc3545; font-weight: bold; border: 1px solid #eee;'>" . $item['stock_quantity'] . "</td>
            </tr>";
    }
    $html .= "</table>";
}

$html .= "
        <p style='text-align: center; color: #888; font-size: 12px; margin-top: 30px;'>
            FOrbit POS - End of Day Automated Report
        </p>
    </div>
</div>
";

/**
 * 3. Dispatch to Admins
 */
$adminRes = $conn->query("SELECT email FROM users WHERE receive_email_alerts = 1 AND email IS NOT NULL AND email != ''");
$sentCount = 0;
$errors = [];

if ($adminRes && $adminRes->num_rows > 0) {
    while ($admin = $adminRes->fetch_assoc()) {
        $res = sendEmail($admin['email'], "Daily Admin Report - FOrbit POS [$today]", $html);
        if ($res['success']) {
            $sentCount++;
        } else {
            $errors[] = $admin['email'] . ': ' . $res['message'];
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No admins configured to receive alerts.']);
    exit;
}

if ($sentCount > 0) {
    echo json_encode(['success' => true, 'message' => "Successfully sent daily report to $sentCount admin(s)."]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send reports.', 'errors' => $errors]);
}
