<?php
/**
 * Global Header Template
 * Premium UI with dark mode support
 */

// Note: Session and database connection are already initialized in auth_check.php
// This file just needs to load functions for template helpers
if (!function_exists('escape')) {
    require_once __DIR__ . '/functions.php';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <script>const BASE_PATH = '<?= BASE_PATH ?>';</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= isset($pageTitle) ? escape($pageTitle) . ' - ' : '' ?><?= escape(getSetting('company_name', 'Inventory & POS')) ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= url('/assets/images/favicon.ico') ?>">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css">
    
    <!-- jQuery (Required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Custom Select2 Bootstrap 5 Theme Overrides -->
    <style>
        .select2-container .select2-selection--single {
            height: calc(3.5rem + 2px); 
            padding: 0.8rem 0.75rem; 
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background-color: #fff;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            color: #212529;
            padding-left: 0;
            font-size: 1rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: calc(3.5rem + 2px);
            right: 0.75rem;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            padding: 0.5rem;
        }
        .select2-dropdown {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
            background-color: #0d6efd;
            color: white;
        }
    </style>

    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?= url('/assets/css/style.css?v=' . time() . '1') ?>">
    <?php if (isset($customCSS)): ?>
        <link rel="stylesheet" href="<?= $customCSS ?>?v=<?= time() ?>">
    <?php endif; ?>
    <!-- PWA -->
    <link rel="manifest" href="<?= url('/manifest.json') ?>">
    <meta name="theme-color" content="#4f46e5">
    <link rel="apple-touch-icon" href="<?= url('/assets/images/icon-512.png') ?>">

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?= url("/service-worker.js") ?>')
                    .then(registration => {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(err => {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }

        // Global Install Prompt Logic
        let globalDeferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            globalDeferredPrompt = e;
            const installBtn = document.getElementById('sidebarInstallBtn');
            const installItem = document.getElementById('sidebarInstallItem');
            
            if(installBtn && installItem) {
                installItem.style.display = 'block'; // Show the list item
                installBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    installItem.style.display = 'none';
                    globalDeferredPrompt.prompt();
                    globalDeferredPrompt.userChoice.then((choiceResult) => {
                        globalDeferredPrompt = null;
                    });
                });
            }
        });
    </script>
</head>
<body>
    <!-- Mobile Sticky Header -->
    <div class="mobile-header">
        <button class="btn btn-link text-dark p-0" id="sidebarToggle">
            <i class="bi bi-list fs-1"></i>
        </button>
        <span class="fs-5 fw-bold text-primary"><?= escape(getSetting('company_name', 'InvenPOS')) ?></span>
        <div class="d-flex align-items-center gap-3">
             <a href="<?= url('/modules/sales/pos.php') ?>" class="text-dark"><i class="bi bi-cart-check fs-4"></i></a>
             <!-- User Profile Pic Small -->
             <?php if (!empty($_SESSION['user_image']) && $_SESSION['user_image'] !== '0'): ?>
                <img src="<?= url($_SESSION['user_image']) ?>" alt="User" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
             <?php else: ?>
                 <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 14px;">
                    <?= substr($_SESSION['user_name'] ?? 'U', 0, 1) ?>
                 </div>
             <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <?php if (isLoggedIn() && !isset($hideNavbar)): ?>
        <?php include __DIR__ . '/navbar.php'; ?>
    <?php endif; ?>
    
    <main class="main-content <?= isLoggedIn() && !isset($hideNavbar) ? 'with-sidebar' : '' ?>">
        <?php 
        // Display flash messages
        $flash = getFlashMessage();
        if ($flash): 
        ?>
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999">
            <div class="toast show" role="alert">
                <div class="toast-header bg-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> text-white">
                    <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <strong class="me-auto"><?= ucfirst($flash['type']) ?></strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    <?= escape($flash['message']) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
