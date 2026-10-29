<?php
/**
 * Activity Logger - Professional audit trail system
 */

function logActivity($action, $module, $record_id = null, $old_values = null, $new_values = null, $description = null) {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Convert arrays to JSON
    $old_json = $old_values ? json_encode($old_values) : null;
    $new_json = $new_values ? json_encode($new_values) : null;
    
    $stmt = $conn->prepare("
        INSERT INTO activity_logs 
        (user_id, action, table_name, record_id, old_values, new_values, description, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("ississsss", $user_id, $action, $module, $record_id, $old_json, $new_json, $description, $ip_address, $user_agent);
    
    return $stmt->execute();
}

/**
 * Get customer/supplier balance
 */
function getContactBalance($contact_id, $type = 'customer') {
    global $conn;
    
    $stmt = $conn->prepare("SELECT balance FROM contacts WHERE id = ? LIMIT 1");
    
    $stmt->bind_param("i", $contact_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['balance'] ?? 0;
}

/**
 * Format balance with color coding
 */
function formatBalance($amount, $type = 'customer') {
    $color = 'text-success';
    $prefix = '';
    
    if ($type === 'customer') {
        // Positive = they owe us (good)
        if ($amount > 0) {
            $color = 'text-success';
            $prefix = '+';
        } elseif ($amount < 0) {
            $color = 'text-danger';
        }
    } else {
        // Positive = we owe them (bad for us)
        if ($amount > 0) {
            $color = 'text-danger';
            $prefix = '+';
        } elseif ($amount < 0) {
            $color = 'text-success';
        }
    }
    
    return '<span class="' . $color . '">' . $prefix . formatCurrency($amount) . '</span>';
}
