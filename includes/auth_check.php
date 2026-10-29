<?php
/**
 * Authentication Check Middleware
 * Protects routes and enforces role-based access control
 */

if (!isset($_SESSION)) {
    session_start([
        'cookie_lifetime' => 86400 * 30, // 30 days to prevent random logouts
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Enforce HTTPS if available
        'cookie_samesite' => 'Strict' // Better security
    ]);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Check for Remember Me cookie
    if (isset($_COOKIE['remember_token'])) {
        $cookie_data = base64_decode($_COOKIE['remember_token']);
        $parts = explode(':', $cookie_data);
        
        if (count($parts) === 3) {
            $user_id = $parts[0];
            $timestamp = $parts[1];
            $signature = $parts[2];
            
            $payload = $user_id . ':' . $timestamp;
            $expected_signature = hash_hmac('sha256', $payload, SECRET_KEY);
            
            if (hash_equals($expected_signature, $signature)) {
                // Check if token expired (30 days)
                if (time() - (int)$timestamp > (86400 * 30)) {
                    setcookie('remember_token', '', time() - 3600, '/');
                } else {
                    // Fetch user to ensure they still exist and are active
                    $stmt = $conn->prepare("SELECT id, name, email, role, is_active, image, branch_id FROM users WHERE id = ? LIMIT 1");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 1) {
                        $user = $result->fetch_assoc();
                        if ($user['is_active']) {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_name'] = $user['name'];
                            $_SESSION['user_email'] = $user['email'];
                            $_SESSION['user_role'] = $user['role'];
                            $_SESSION['branch_id'] = $user['branch_id'] ?? 1;
                            $_SESSION['user_image'] = $user['image'];
                            $_SESSION['last_activity'] = time();
                            // Refreshed!
                        }
                    }
                    $stmt->close();
                }
            }
        }
    }
    
    // Check again
    if (!isLoggedIn()) {
        header('Location: ' . url('/modules/auth/login.php'));
        exit();
    }
} 

// Periodically check if user is deactivated or role changed by Admin (every 5 minutes)
if (isLoggedIn()) {
    $last_db_check = $_SESSION['last_db_check'] ?? 0;
    if (time() - $last_db_check > 300) {
        $stmt = $conn->prepare("SELECT is_active, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ($row['is_active'] == 0) {
                // User was deactivated! Destroy session instantly.
                session_destroy();
                header('Location: ' . url('/modules/auth/login.php?error=account_deactivated'));
                exit();
            }
            // Keep session role in sync just in case Admin changed it
            if ($_SESSION['user_role'] !== $row['role']) {
                $_SESSION['user_role'] = $row['role'];
            }
            $_SESSION['last_db_check'] = time();
        } else {
            // User deleted from DB
            session_destroy();
            header('Location: ' . url('/modules/auth/login.php'));
            exit();
        }
        $stmt->close();
    }
}

// Auto-repair: If user is logged in but image is missing from session (e.g. stale session)
if (isLoggedIn() && !isset($_SESSION['user_image'])) {
    $stmt = $conn->prepare("SELECT image FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $_SESSION['user_image'] = $row['image'];
    }
    $stmt->close();
}

// Check session timeout
checkSessionTimeout();

// Role-based access control (optional, pass required role)
if (isset($requiredRole)) {
    if (!hasRole($requiredRole)) {
        setFlashMessage('error', 'You do not have permission to access this page');
        header('Location: ' . url('/modules/dashboard/index.php'));
        exit();
    }
}
