<?php
/**
 * Database Configuration
 * Supports both single-tenant and SaaS multi-tenant modes.
 */

// Simple .env parser function
function loadEnv($path) {
    if (!file_exists($path)) return;
    // Read as binary to handle any encoding issues
    $content = file_get_contents($path);
    // Strip null bytes (UTF-16 BOM issue fix)
    $content = str_replace("\0", '', $content);
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load .env from project root
loadEnv(__DIR__ . '/../.env');

// Robust environment variable retrieval (checks $_ENV, $_SERVER, then getenv)
$env_host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$env_user = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: 'root';
$env_pass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS');
if ($env_pass === false || $env_pass === null) $env_pass = '';
$default_db = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: 'erp';
$env_secret = $_ENV['SECRET_KEY'] ?? $_SERVER['SECRET_KEY'] ?? getenv('SECRET_KEY') ?: '7f8a9d1b2c3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9';

define('DB_HOST', $env_host);
define('DB_USER', $env_user);
define('DB_PASS', $env_pass);

// Dynamic SaaS Tenant Resolution (graceful fallback to single-tenant)
$http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$domain_parts = explode(':', $http_host);
$tenant_domain = $domain_parts[0];

$target_db = $default_db; // Default: use .env DB

// Try to resolve tenant from saas_master_db (safely suppressed for shared hosting)
mysqli_report(MYSQLI_REPORT_OFF);
try {
    $saas_conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, 'saas_master_db');
    if ($saas_conn && !$saas_conn->connect_error) {
        $stmt = $saas_conn->prepare("SELECT db_name, status FROM tenants WHERE domain = ?");
        if ($stmt) {
            $stmt->bind_param("s", $tenant_domain);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            if ($res) {
                if ($res['status'] === 'suspended') {
                    http_response_code(503);
                    die("<!DOCTYPE html><html><head><title>Account Suspended</title></head><body style='font-family:Arial;text-align:center;margin-top:100px;'><h2>&#128683; Account Suspended</h2><p>Your subscription has been suspended. Please contact support to reactivate.</p></body></html>");
                }
                $target_db = $res['db_name']; // Use tenant-specific DB
            }
            $stmt->close();
        }
        $saas_conn->close();
    }
} catch (Throwable $e) {
    // saas_master_db not available - continue with default .env DB
}

define('DB_NAME', $target_db);

// Now enable strict reporting for the main connection
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Create actual tenant connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    die("Database connection failed. Please check your configuration.");
}

// Set charset
$conn->set_charset('utf8mb4');

// Security Key
define('SECRET_KEY', $env_secret);
