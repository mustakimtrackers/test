<?php
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/email_helper.php';

// Only admins should send manual reminders
requireAdmin();

header('Content-Type: application/json');

/**
 * Handle sending due reminders via the Email API
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$customerId = isset($input['customer_id']) ? (int)$input['customer_id'] : 0;
$subject = isset($input['subject']) ? trim($input['subject']) : 'Payment Reminder';
$message = isset($input['message']) ? trim($input['message']) : '';

if ($customerId <= 0 || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
    exit;
}

// Fetch Customer Email & Opt-in
$stmt = $conn->prepare("SELECT name, email, email_opt_in FROM contacts WHERE id = ? AND type = 'customer'");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$customerInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customerInfo) {
    echo json_encode(['success' => false, 'message' => 'Customer not found.']);
    exit;
}

if (empty($customerInfo['email'])) {
    echo json_encode(['success' => false, 'message' => 'Customer does not have an email address on file.']);
    exit;
}

if (!$customerInfo['email_opt_in']) {
    echo json_encode(['success' => false, 'message' => 'Customer has not opted in to receive emails.']);
    exit;
}

// Convert line breaks to HTML for email body
$bodyHTML = nl2br(htmlspecialchars($message));

// Add standard wrapper for professional look
$bodyHTML = "
<div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
    <h2 style='color: #0d6efd;'>Payment Reminder</h2>
    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
    <div style='font-size: 15px;'>
        $bodyHTML
    </div>
    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
    <div style='font-size: 12px; color: #888; text-align: center;'>
        This is an automated message from the FOrbit POS System. Please do not reply directly to this email unless necessary.
    </div>
</div>
";

// Send using helper
$result = sendEmail($customerInfo['email'], $subject, $bodyHTML);

echo json_encode($result);
