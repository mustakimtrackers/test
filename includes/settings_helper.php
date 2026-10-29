<?php
/**
 * Settings Helper
 * Get/Set system settings
 */

// Get setting value by key
function getSetting($key, $default = '', $clearCache = false) {
    global $conn;
    
    static $cache = [];
    
    if ($clearCache) {
        unset($cache[$key]);
        return;
    }
    
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    
    $stmt = $conn->prepare("SELECT setting_value, setting_type FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$result) {
        $cache[$key] = $default;
        return $default;
    }
    
    $value = $result['setting_value'];
    
    // Type casting
    if ($result['setting_type'] === 'boolean') {
        $value = (bool)$value;
    } elseif ($result['setting_type'] === 'number') {
        $value = (float)$value;
    } elseif ($result['setting_type'] === 'json') {
        $value = json_decode($value, true);
    }
    
    // Auto-heal corrupted currency symbol
    if ($key === 'currency_symbol' && ($value === 'αº│' || strpos((string)$value, 'α') !== false || empty($value))) {
        $value = '৳';
    }
    
    $cache[$key] = $value;
    return $value;
}

// Update setting value
function updateSetting($key, $value, $userId = null) {
    global $conn;
    
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    $stmt = $conn->prepare("
        INSERT INTO settings (setting_key, setting_value, updated_by) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)
    ");
    $stmt->bind_param("ssi", $key, $value, $userId);
    $result = $stmt->execute();
    $stmt->close();
    
    if ($result) {
        getSetting($key, '', true); // Clear the static cache
    }
    
    return $result;
}

// Get all settings by category
function getSettingsByCategory($category) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE category = ?");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $settings = [];
    foreach ($results as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    return $settings;
}

// Get company info array
function getCompanyInfo() {
    return [
        'name' => getSetting('company_name', 'FOrbit POS'),
        'tagline' => getSetting('company_tagline', 'Smart Business Management System'),
        'address' => getSetting('company_address', ''),
        'phone' => getSetting('company_phone', ''),
        'email' => getSetting('company_email', ''),
        'website' => getSetting('company_website', ''),
        'tax_number' => getSetting('tax_id', ''),
    ];
}
?>
