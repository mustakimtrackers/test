<?php
/**
 * Email Helper Class
 * Handles sending emails via OneSignal (and can be extended for other providers)
 */

function sendEmail($toEmail, $subject, $body) {
    global $conn;
    
    // Fetch email settings from database
    $settings = [];
    $stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'email_%'");
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    $provider = $settings['email_provider'] ?? 'onesignal';
    $apiKey = $settings['email_api_key'] ?? '';
    $appId = $settings['email_app_id'] ?? ''; // Added to accommodate OneSignal app_id
    $fromName = $settings['email_from_name'] ?? 'FOrbit POS';
    $fromAddress = $settings['email_from_address'] ?? 'noreply@yourdomain.com';
    
    if (empty($apiKey)) {
        // Return true in local testing if no API key is set so the app doesn't crash, 
        // but log it to PHP error log.
        error_log("Email Helper: Email API Key is not configured. Email to $toEmail was skipped.");
        return ['success' => false, 'message' => 'Email API key is not configured in settings.'];
    }

    if ($provider === 'onesignal') {
        if(empty($appId)) {
             return ['success' => false, 'message' => 'OneSignal App ID is not configured.'];
        }
        
        $fields = [
            'app_id' => $appId,
            'include_email_tokens' => [$toEmail],
            'email_subject' => $subject,
            'email_body' => $body,
            'email_from_name' => $fromName,
            'email_from_address' => $fromAddress
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8', 'Authorization: Basic ' . $apiKey));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300 && isset($resData['id'])) {
            return ['success' => true, 'message' => 'Email sent successfully via OneSignal.'];
        } else {
            error_log("OneSignal Email Error: " . $response);
            return ['success' => false, 'message' => 'Failed to send email via OneSignal.'];
        }
    }
    
    return ['success' => false, 'message' => 'Unsupported email provider.'];
}
