<?php
/**
 * Global Helper Functions
 */

require_once __DIR__ . '/activity_logger.php';
/**
 * Reusable utility functions for security, formatting, and common operations
 */

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * @param string $token
 * @return bool
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function cleanInput($data) {
    if (is_array($data)) return array_map('cleanInput', $data);
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Escape output for HTML display
 * @param string $data
 * @return string
 */
function escape($string) {
    if ($string === null) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 * @param string|array $roles
 * @return bool
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = $_SESSION['user_role'] ?? '';
    
    if (is_array($roles)) {
        return in_array($userRole, $roles);
    }
    
    return $userRole === $roles;
}

/**
 * Redirect to a specific page
 * @param string $url
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Generate URL with BASE_PATH prefix
 * @param string $path Path relative to base (e.g., '/modules/products/list.php')
 * @return string Full path with BASE_PATH
 */
function url($path) {
    if (!defined('BASE_PATH')) {
        // Fallback if config not loaded
        return $path;
    }
    
    // Ensure path starts with /
    if (strpos($path, '/') !== 0) {
        $path = '/' . $path;
    }
    
    return BASE_PATH . $path;
}

/**
 * Get currency symbol
 * @return string
 */
function getCurrencySymbol() {
    if (!function_exists('getSetting')) {
        require_once __DIR__ . '/settings_helper.php';
    }
    $sym = getSetting('currency_symbol', '৳');
    if ($sym === 'αº│' || strpos($sym, 'α') !== false || empty($sym)) return '৳';
    return $sym;
}

/**
 * Format currency (BDT)
 * @param float $amount
 * @return string
 */
function formatCurrency($amount) {
    if (!function_exists('getSetting')) {
        require_once __DIR__ . '/settings_helper.php';
    }
    $symbol = getSetting('currency_symbol', '৳');
    if ($symbol === 'αº│' || strpos($symbol, 'α') !== false || empty($symbol)) $symbol = '৳';
    return $symbol . ' ' . number_format((float)($amount ?? 0), 2);
}

/**
 * Format date
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

/**
 * Generate unique reference number
 * @param string $prefix
 * @return string
 */
function generateReference($prefix = 'INV') {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Generate SKU
 * @param string $productName
 * @return string
 */
function generateSKU($productName) {
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $productName), 0, 3));
    $random = strtoupper(substr(uniqid(), -6));
    return $prefix . '-' . $random;
}

/**
 * Generate slug from string
 * @param string $text
 * @return string
 */
function generateSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

/**
 * Set flash message
 * @param string $type (success, error, warning, info)
 * @param string $message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 * @return array|null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Validate file upload
 * @param array $file $_FILES array element
 * @param array $allowedTypes
 * @param int $maxSize Maximum size in bytes
 * @return array ['success' => bool, 'message' => string, 'filename' => string]
 */
function validateFileUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'], $maxSize = 5242880) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error occurred'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size exceeds limit (5MB)'];
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and WebP allowed'];
    }
    
    // Generate safe filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFilename = uniqid() . '_' . time() . '.' . $extension;
    
    return ['success' => true, 'message' => 'Valid file', 'filename' => $newFilename];
}

/**
 * Get pagination data
 * @param int $totalItems
 * @param int $currentPage
 * @param int $itemsPerPage
 * @return array
 */
function getPagination($totalItems, $currentPage = 1, $itemsPerPage = 20) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, max(1, $totalPages))); // Ensure current page makes sense even if totalItems is 0
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'items_per_page' => $itemsPerPage,
        'offset' => max(0, $offset),
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Render Bootstrap Pagination UI (Mobile Friendly sliding window)
 * @param array $pagination The array returned by getPagination()
 * @param string $baseUrl The base URL for the page (can be empty to use current URL)
 * @return string HTML output
 */
function renderPaginationUI($pagination, $baseUrl = '') {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }

    $current = $pagination['current_page'];
    $total = $pagination['total_pages'];
    
    // Preserve existing GET parameters except 'page'
    $queryParams = $_GET;
    unset($queryParams['page']);
    $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';

    // Smart sliding window logic
    $window = 2; // Number of pages to show around current
    
    $start = max(1, $current - $window);
    $end = min($total, $current + $window);
    
    $html = '<nav class="mt-4" aria-label="Page navigation">';
    $html .= '<ul class="pagination justify-content-center">';
    
    // Previous button
    if ($pagination['has_prev']) {
        $html .= '<li class="page-item"><a class="page-link shadow-sm" href="?page=' . ($current - 1) . $queryString . '">Prev</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Prev</span></li>';
    }
    
    // First page & ellipsis
    if ($start > 1) {
        $html .= '<li class="page-item d-none d-sm-block"><a class="page-link" href="?page=1' . $queryString . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled d-none d-sm-block"><span class="page-link border-0">...</span></li>';
        }
    }
    
    // Numbered buttons
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current) {
            $html .= '<li class="page-item active"><span class="page-link shadow-sm">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="?page=' . $i . $queryString . '">' . $i . '</a></li>';
        }
    }
    
    // Last page & ellipsis
    if ($end < $total) {
        if ($end < $total - 1) {
            $html .= '<li class="page-item disabled d-none d-sm-block"><span class="page-link border-0">...</span></li>';
        }
        $html .= '<li class="page-item d-none d-sm-block"><a class="page-link" href="?page=' . $total . $queryString . '">' . $total . '</a></li>';
    }
    
    // Next button
    if ($pagination['has_next']) {
        $html .= '<li class="page-item"><a class="page-link shadow-sm" href="?page=' . ($current + 1) . $queryString . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Send JSON response
 * @param bool $success
 * @param mixed $data
 * @param string $message
 */
function jsonResponse($success, $data = null, $message = '') {
    ob_clean(); // Discard any prior output (whitespace/warnings)
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit();
}

/**
 * Check session timeout (30 minutes)
 */
function checkSessionTimeout() {
    $timeout = 1800; // 30 minutes
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        redirect(url('/modules/auth/login.php?timeout=1'));
    }
    
    $_SESSION['last_activity'] = time();
}

// --- Role & Permission Helpers ---

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isStaff() {
    // Staff includes 'staff', 'cashier', 'manager' (simplified role system)
    $role = $_SESSION['user_role'] ?? '';
    return in_array($role, ['staff', 'cashier', 'manager']);
}

function requireAdmin() {
    if (!isAdmin()) {
        if (function_exists('logActivity')) {
            // logActivity($action, $module, $record_id, $old, $new, $description)
            logActivity('access_denied', 'security', null, null, null, "Attempted to access admin page: " . $_SERVER['REQUEST_URI']);
        }
        setFlashMessage('error', 'Access Denied: Admin privileges required.');
        redirect(url('/modules/dashboard/index.php'));
    }
}

// Settings functions moved to includes/settings_helper.php
// This prevents redeclaration errors

/**
 * Handle File Upload
 * @param array $file $_FILES['input_name']
 * @param string $directory Target directory (relative to uploads/)
 * @return string|false File path or false on failure
 */
function handleFileUpload($file, $directory = 'misc') {
    // Check for upload errors
    if (!isset($file['error']) || is_array($file['error'])) {
        return false; 
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false; 
    }

    // Validate size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }

    // Validate allowed mime types
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    $mimeType = false;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
    } elseif (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($file['tmp_name']);
    } else {
        $mimeType = $file['type']; // Unsecure fallback if server has no fileinfo extensions
    }

    if (!$mimeType || !in_array($mimeType, $allowedTypes)) {
        return false; 
    }

    // Generate unique name
    $extension = array_search($mimeType, [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp'
    ], true);
    
    if ($extension === false) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    }

    $filename = uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetDir = __DIR__ . '/../assets/uploads/' . $directory;
    
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    
    $targetPath = $targetDir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'assets/uploads/' . $directory . '/' . $filename;
    }
    
    return false;
}

/**
 * Safely delete an uploaded file
 * @param string|null $path
 * @return bool
 */
function deleteUploadedFile($path) {
    if (empty($path)) return false;
    
    // Only allow deleting from assets/uploads/
    if (strpos($path, 'assets/uploads/') !== 0) return false;
    
    $fullPath = __DIR__ . '/../' . $path;
    if (file_exists($fullPath) && is_file($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}


// Auto-load settings helper if not already loaded
// Include settings helper and apply global timezone
require_once __DIR__ . '/settings_helper.php';
date_default_timezone_set(getSetting('timezone', 'Asia/Dhaka'));
