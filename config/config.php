<?php
/**
 * Application Configuration
 * Automatically detects base URL and path for localhost and production
 */

// Detect protocol (HTTP or HTTPS)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';

// Get host name
$host = $_SERVER['HTTP_HOST'];

// Get the REQUEST_URI to detect base path
// Get the directory of the current script
$scriptName = $_SERVER['SCRIPT_NAME'];
$scriptDir = dirname($scriptName);

// Normalize slashes (Windows fix)
$scriptDir = str_replace('\\', '/', $scriptDir);

// Remove trailing slash if it's not root
$basePath = rtrim($scriptDir, '/');

// In case we are in a sub-config file (e.g., /config/config.php), we might need to go up
// But usually config is included from a root or module file. 
// Best approach: Define base path relative to the PROJECT ROOT, not the current file.
// Since config.php is in /config/, the project root is one level up.
// However, BASE_PATH is used for URL generation, so it must match the URL structure.
// IF this file is included from /index.php, dirname is / (or /sub).
// IF included from /modules/auth/login.php, dirname is /modules/auth.

// AUTOMATIC DETECTION STRATEGY:
// 1. We know this file is at [ROOT]/config/config.php
// 2. We can find the URL path to this file? No, it's included.
// 3. We rely on the fact that the application structure is fixed.

// Better Strategy:
// Find the relative path of the executing script to the document root.
// But simplest for this legacy-style app:
// Just rely on the fact that usually index.php or module files are entry points.

// REVISED ROBUST LOGIC:
// Detect based on known folder markers if possible, or fall back to dirname of Script Name, 
// ensuring we strip known subdirectories if the script is running 'deep'.

// Actually, the previous logic tried to capture the "Root Folder Name". 
// A safer generic way for typical XAMPP/cPanel hosting:
// If script is /stan2/index.php -> BASE is /stan2
// If script is /index.php -> BASE is ""
// If script is /stan2/modules/pos.php -> BASE is /stan2

// Let's implement a 'marker' check. config.php is in /config/
// The root is one level up from __DIR__.
// But we need the web URL path.

// Use __DIR__ to find physical path, matches against DOCUMENT_ROOT?
$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$dirName = str_replace('\\', '/', __DIR__); 
// $dirName is .../htdocs/stan2/config

// Remove /config from the end to get project root
$projectRoot = dirname($dirName); 
// $projectRoot is .../htdocs/stan2

// Remove DOCUMENT_ROOT from Project Root to get URL Path
$urlPath = str_replace($docRoot, '', $projectRoot);
// $urlPath is /stan2

$basePath = rtrim($urlPath, '/');

// Define global constants
define('BASE_URL', $protocol . '://' . $host . $basePath);
define('BASE_PATH', $basePath);

// Application settings
define('APP_NAME', 'Inventory POS');
define('APP_VERSION', '1.0.0');

// Debug (remove in production)
// error_log("SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME']);
// error_log("BASE_PATH: " . BASE_PATH);
?>
