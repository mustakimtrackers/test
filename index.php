<?php
/**
 * Index Page - Redirect to Dashboard or Login
 */

session_start([
    'cookie_lifetime' => 86400 * 30,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'cookie_samesite' => 'Strict'
]);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . url('/modules/dashboard/index.php'));
} else {
    header('Location: ' . url('/modules/auth/login.php'));
}
exit();
