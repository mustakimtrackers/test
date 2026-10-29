<?php
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/email_helper.php';

// Only admins should send manual arbitrary emails
requireAdmin();

header('Content-Type: application/json');

/**
 * Handle sending arbitrary custom emails via the Email API
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$targetEmail = isset($input['email']) ? filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL) : false;
$subject = isset($input['subject']) ? trim($input['subject']) : 'A message from FOrbit POS';
$message = isset($input['message']) ? trim($input['message']) : '';

if (!$targetEmail || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address or empty message provided.']);
    exit;
}

// Convert line breaks to HTML for email body
$bodyHTML = nl2br(htmlspecialchars($message));

// Add standard wrapper for professional look
$bodyHTML = "
<div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
    <h2 style='color: #0d6efd;'>FOrbit POS</h2>
    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
    <div style='font-size: 15px;'>
        $bodyHTML
    </div>
    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
    <div style='font-size: 12px; color: #888; text-align: center;'>
        This message was securely dispatched from the FOrbit POS System.
    </div>
</div>
";

// Send using helper
$result = sendEmail($targetEmail, $subject, $bodyHTML);

echo json_encode($result);
