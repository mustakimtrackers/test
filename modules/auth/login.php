<?php
/**
 * Login Page - Standalone Version
 * Beautiful UI with inline CSS (no external dependencies)
 */

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true,
    'cookie_samesite' => 'Strict'
]);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . url('/modules/dashboard/index.php'));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate Limiting Logic
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt_time'] = time();
    }
    
    if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_attempt_time'] < 900)) {
        $error = 'Too many failed login attempts. Please try again in 15 minutes.';
    } elseif (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $email = cleanInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password';
        } else {
            $stmt = $conn->prepare("SELECT id, name, email, password, role, is_active, image, two_factor_secret, branch_id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if (!$user['is_active']) {
                    $error = 'Account deactivated. Contact administrator.';
                } elseif (password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    
                    if (!empty($user['two_factor_secret'])) {
                        // User needs 2FA
                        $_SESSION['2fa_pending_user_id'] = $user['id'];
                        $_SESSION['2fa_pending_user_name'] = $user['name'];
                        $_SESSION['2fa_pending_user_email'] = $user['email'];
                        $_SESSION['2fa_pending_user_role'] = $user['role'];
                        $_SESSION['2fa_pending_branch_id'] = $user['branch_id'] ?? 1;
                        $_SESSION['2fa_pending_user_image'] = $user['image'];
                        $_SESSION['2fa_pending_secret'] = $user['two_factor_secret'];
                        $_SESSION['2fa_remember'] = isset($_POST['remember']) ? 1 : 0;
                        
                        header('Location: ' . url('/modules/auth/login_2fa.php'));
                        exit();
                    }
                    
                    // No 2FA required, login directly
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['branch_id'] = $user['branch_id'] ?? 1;
                    $_SESSION['user_image'] = $user['image']; // Store image path
                    $_SESSION['last_activity'] = time();
                    
                    // Remember Me Logic
                    if (isset($_POST['remember'])) {
                        $token_payload = $user['id'] . ':' . time();
                        $signature = hash_hmac('sha256', $token_payload, SECRET_KEY);
                        $cookie_value = base64_encode($token_payload . ':' . $signature);
                        // Set cookie for 30 days
                        setcookie('remember_token', $cookie_value, time() + (86400 * 30), '/', '', true, true);
                    }
                    
                    // Log Login Activity
                    logActivity('login', 'auth', $user['id'], null, null, 'User logged in successfully');
                    
                    $_SESSION['login_attempts'] = 0; // Reset attempts on success
                    
                    header('Location: ' . url('/modules/dashboard/index.php'));
                    exit();
                } else {
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                    $error = 'Invalid email or password';
                }
            } else {
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                $error = 'Invalid email or password';
            }
            
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= escape(getSetting('company_name', 'Inventory & POS System')) ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@700;800&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column; /* Stack vertically */
            /* align-items: center; removed to prevent clipping */
            /* justify-content: center; removed to prevent clipping */
            background-color: #0f172a;
            position: relative;
            padding: 0;
            overflow-x: hidden; /* Prevent horizontal scroll from shapes */
        }

        /* FIXED Background Wrapper - Prevents Scrollbar Glitch */
        .background-wrapper {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            overflow: hidden;
            z-index: -1;
            pointer-events: none;
        }

        /* Animated Mesh Gradient Background */
        .background-animate {
            position: absolute;
            top: -20%; left: -20%; right: -20%; bottom: -20%; /* Oversize to prevent gaps */
            background: radial-gradient(circle at 15% 50%, rgba(79, 70, 229, 0.5), transparent 25%), 
                        radial-gradient(circle at 85% 30%, rgba(236, 72, 153, 0.5), transparent 25%);
            filter: blur(60px);
            animation: meshMove 8s ease-in-out infinite alternate;
        }

        @keyframes meshMove {
            0% { transform: scale(1) translate(0, 0); }
            50% { transform: scale(1.1) translate(10px, -10px); }
            100% { transform: scale(1.2) translate(-10px, 10px); }
        }
        
        /* Floating Shapes for 'Relatable' Tech feel */
        .shape {
            position: absolute;
            filter: blur(50px);
            opacity: 0.7;
            animation: floatShape 15s infinite linear;
        }
        .shape-1 {
            top: -10%; left: -10%; width: 60vw; height: 60vw;
            background: #4f46e5;
            border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%;
        }
        .shape-2 {
            bottom: -10%; right: -10%; width: 60vw; height: 60vw;
            background: #06b6d4; /* Cyan */
            border-radius: 60% 40% 30% 70% / 50% 40% 50% 60%;
            animation-direction: reverse;
        }
        
        @keyframes floatShape {
            0% { transform: rotate(0deg) translate(0, 0); }
            50% { transform: rotate(180deg) translate(20px, 20px); }
            100% { transform: rotate(360deg) translate(0, 0); }
        }

        /* Login Card */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px; /* Slightly wider */
            padding: 20px;
            margin: auto; /* This centers it vertically and vertically safely */
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.7); /* More transparent */
            backdrop-filter: blur(20px); /* Stronger blur for 'ghola' effect */
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px 32px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.4);
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Logo */
        .logo-container {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .logo-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }
        
        .company-name {
            font-family: 'Outfit', sans-serif; /* Stylish modern font */
            font-size: 32px; /* Bigger */
            font-weight: 800;
            background: linear-gradient(to right, #1e293b, #4f46e5);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -1px;
            line-height: 1.2;
            display: block;
        }
        
        /* Form Inputs & Alignment Fixes */
        .form-label {
            font-weight: 600;
            font-size: 14px;
            color: #1f2937;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control {
            width: 100%; /* Ensure full width */
            border: 1px solid rgba(209, 213, 219, 0.8); /* Slightly transparent border */
            background: rgba(255, 255, 255, 0.9); /* Slightly transparent input */
            border-radius: 12px; /* Smoother rounding */
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.2s;
            box-sizing: border-box; /* Critical for alignment */
        }
        
        .form-control:focus {
            border-color: #4f46e5;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            outline: none;
        }
        
        /* Wrapper alignment fix */
        .password-wrapper { 
            position: relative; 
            width: 100%; 
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 8px;
            font-size: 18px;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: #4f46e5;
        }

        /* Submit Button - Restored */
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
            margin-top: 12px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
        }

        /* Footer Credit - Restored */
        .login-footer {
            margin-top: 32px;
            text-align: center;
            font-size: 13px;
            color: #6b7280;
            border-top: 1px solid rgba(0,0,0,0.05); /* Subtle separator */
            padding-top: 20px;
        }
        
        .login-footer a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Animated Background Elements (Fixed) -->
    <div class="background-wrapper">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="background-animate"></div>
    </div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="company-name"><?= escape(getSetting('company_name', 'InvenPOS')) ?></div>
            </div>
            
            <div class="login-header">
                <p>Welcome back! Please sign in to continue.</p>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="background:#fee2e2; color:#991b1b; padding:12px; border-radius:8px; font-size:14px; margin-bottom:20px; border:1px solid #fecaca;">
                <i class="bi bi-exclamation-circle-fill me-2"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <div class="mb-3" style="margin-bottom: 20px;">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required autofocus placeholder="name@company.com">
                </div>
                
                <div class="mb-4" style="margin-bottom: 24px;">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-4" style="margin-bottom: 24px; display:flex; justify-content:space-between;">
                    <div class="form-check" style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" id="remember" name="remember" style="accent-color:#4f46e5;">
                        <label for="remember" style="font-size:13px; color:#4b5563;">Remember me</label>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">Sign In</button>
            </form>
            
            <div class="login-footer">
                &copy; <?= date('Y') ?> Powered by <a href="#" target="_blank">FBM</a>
            </div>
        </div>
    </div>
    
    <script>
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
    </script>
</body>
</html>
