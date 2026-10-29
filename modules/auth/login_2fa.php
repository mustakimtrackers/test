<?php
/**
 * 2FA Verification Page
 */
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true,
    'cookie_samesite' => 'Strict'
]);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/GoogleAuthenticator.php';

if (isLoggedIn()) {
    header('Location: ' . url('/modules/dashboard/index.php'));
    exit();
}

if (!isset($_SESSION['2fa_pending_user_id'])) {
    header('Location: ' . url('/modules/auth/login.php'));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $secret = $_SESSION['2fa_pending_secret'];
        
        $ga = new PHPGangsta_GoogleAuthenticator();
        $checkResult = $ga->verifyCode($secret, $code, 2); // 2 = 2*30sec clock tolerance
        
        if ($checkResult) {
            // Valid code, login!
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $_SESSION['2fa_pending_user_id'];
            $_SESSION['user_name'] = $_SESSION['2fa_pending_user_name'];
            $_SESSION['user_email'] = $_SESSION['2fa_pending_user_email'];
            $_SESSION['user_role'] = $_SESSION['2fa_pending_user_role'];
            $_SESSION['branch_id'] = $_SESSION['2fa_pending_branch_id'] ?? 1;
            $_SESSION['user_image'] = $_SESSION['2fa_pending_user_image'];
            $_SESSION['last_activity'] = time();
            
            // Remember Me
            if ($_SESSION['2fa_remember']) {
                $token_payload = $_SESSION['user_id'] . ':' . time();
                $signature = hash_hmac('sha256', $token_payload, SECRET_KEY);
                $cookie_value = base64_encode($token_payload . ':' . $signature);
                setcookie('remember_token', $cookie_value, time() + (86400 * 30), '/', '', false, true);
            }
            
            logActivity('login', 'auth', $_SESSION['user_id'], null, null, 'User logged in successfully with 2FA');
            
            // Clean up 2FA session data
            unset($_SESSION['2fa_pending_user_id']);
            unset($_SESSION['2fa_pending_user_name']);
            unset($_SESSION['2fa_pending_user_email']);
            unset($_SESSION['2fa_pending_user_role']);
            unset($_SESSION['2fa_pending_branch_id']);
            unset($_SESSION['2fa_pending_user_image']);
            unset($_SESSION['2fa_pending_secret']);
            unset($_SESSION['2fa_remember']);
            
            header('Location: ' . url('/modules/dashboard/index.php'));
            exit();
        } else {
            $error = 'Invalid 2FA code. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - <?= escape(getSetting('company_name', 'Inventory & POS System')) ?></title>
    <!-- Use system's local bootstrap -->
    <link href="<?= url('/assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= url('/assets/css/bootstrap-icons.css') ?>" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 400px;
            width: 100%;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: none;
        }
        .login-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }
    </style>
</head>
<body>

<div class="card login-card">
    <div class="login-header">
        <h2 class="mb-0"><i class="bi bi-shield-lock"></i> 2FA Verification</h2>
        <p class="mb-0 mt-2 text-white-50">Enter the code from your authenticator app.</p>
    </div>
    
    <div class="card-body p-4">
        <?php if ($error): ?>
            <div class="alert alert-danger p-2 small">
                <i class="bi bi-exclamation-triangle"></i> <?= escape($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            
            <div class="mb-4 text-center">
                <img src="<?= url('/assets/images/2fa-icon.svg') ?>" alt="2FA" onerror="this.src='https://cdn-icons-png.flaticon.com/512/6195/6195699.png'" style="width: 80px; opacity: 0.8; margin-bottom: 15px;">
                <input type="text" name="code" class="form-control form-control-lg text-center fw-bold letter-spacing-2" placeholder="000 000" maxlength="6" required autofocus autocomplete="one-time-code" style="font-size: 1.5rem; letter-spacing: 5px;">
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">Verify</button>
                <a href="<?= url('/modules/auth/logout.php') ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
