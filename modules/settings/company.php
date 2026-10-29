<?php
$pageTitle = 'System Settings';
$requiredRole = 'admin';
require_once __DIR__ . '/../../includes/auth_check.php';
// Header moved to bottom to prevent "headers already sent" error during redirect

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid security token');
    } else {
        // List of allowed settings to save
        $allowedSettings = [
            'company_name', 
            'company_email', 
            'company_phone', 
            'company_address', 
            'currency_symbol', 
            'timezone',
            'tax_id',
            'default_tax_rate',
            'email_provider',
            'email_app_id',
            'email_api_key',
            'email_from_name',
            'email_from_address',
            'enable_low_stock_alerts',
            'weather_city'
        ];
        
        $success = true;
        foreach ($allowedSettings as $key) {
            if (isset($_POST[$key])) {
                if (!updateSetting($key, $_POST[$key])) {
                    $success = false;
                }
            }
        }
        
        if ($success) {
            $logData = $_POST;
            unset($logData['csrf_token']);
            logActivity('update_settings', 'settings', null, null, $logData, 'Updated system settings');
            setFlashMessage('success', 'Settings saved successfully');
        } else {
            setFlashMessage('error', 'Failed to save some settings');
        }
        
        // Redirect to refresh settings
        redirect(url('/modules/settings/company.php'));
    }
}

// Get current settings
$companyName = getSetting('company_name', 'My Shop');
$companyEmail = getSetting('company_email', '');
$companyPhone = getSetting('company_phone', '');
$companyAddress = getSetting('company_address', '');
$currencySymbol = getSetting('currency_symbol', '৳');
$timezone = getSetting('timezone', 'Asia/Dhaka');

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-gear"></i> System Settings</h1>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Business Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control" name="company_name" value="<?= escape($companyName) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="company_email" value="<?= escape($companyEmail) ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="company_phone" value="<?= escape($companyPhone) ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Currency Symbol</label>
                                <input type="text" class="form-control" name="currency_symbol" value="<?= escape($currencySymbol) ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Timezone</label>
                                <select class="form-select" name="timezone">
                                    <option value="Asia/Dhaka" <?= $timezone == 'Asia/Dhaka' ? 'selected' : '' ?>>Asia/Dhaka</option>
                                    <option value="UTC" <?= $timezone == 'UTC' ? 'selected' : '' ?>>UTC</option>
                                    <option value="America/New_York" <?= $timezone == 'America/New_York' ? 'selected' : '' ?>>New York</option>
                                    <option value="Europe/London" <?= $timezone == 'Europe/London' ? 'selected' : '' ?>>London</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dashboard Weather City</label>
                                <select class="form-select" name="weather_city">
                                    <?php
                                    require_once __DIR__ . '/../../includes/districts.php';
                                    $cities = getBangladeshDistricts();
                                    // Sort alphabetically by district name
                                    asort($cities);
                                    $currentCity = getSetting('weather_city', 'Dhaka|23.8103|90.4125');
                                    foreach ($cities as $val => $label) {
                                        $selected = ($currentCity === $val) ? 'selected' : '';
                                        echo "<option value=\"$val\" $selected>$label</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tax/VAT ID</label>
                                <input type="text" class="form-control" name="tax_id" value="<?= escape(getSetting('tax_id', '')) ?>" placeholder="e.g. BIN-12345678">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Default Tax Rate (%)</label>
                                <input type="number" step="0.01" class="form-control" name="default_tax_rate" value="<?= escape(getSetting('default_tax_rate', '0.00')) ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="company_address" rows="3"><?= escape($companyAddress) ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- Email Settings Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-envelope-at"></i> Email Configuration (OneSignal)</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="email_provider" value="onesignal">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">From Name</label>
                                <input type="text" class="form-control" name="email_from_name" value="<?= escape(getSetting('email_from_name', 'FOrbit POS')) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">From Email Address</label>
                                <input type="email" class="form-control" name="email_from_address" value="<?= escape(getSetting('email_from_address', 'noreply@yourdomain.com')) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">OneSignal App ID</label>
                            <input type="text" class="form-control" name="email_app_id" value="<?= escape(getSetting('email_app_id', '')) ?>" placeholder="e.g. xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">OneSignal API Key</label>
                            <input type="password" class="form-control" name="email_api_key" value="<?= escape(getSetting('email_api_key', '')) ?>" placeholder="Enter REST API Key">
                        </div>

                        <div class="mb-3 form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" id="enable_low_stock_alerts" name="enable_low_stock_alerts" value="1" <?= getSetting('enable_low_stock_alerts', '1') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="enable_low_stock_alerts">Enable Low Stock Email Alerts (Triggers during POS Checkout)</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Email Settings
                        </button>
                    </form>
                </div>
            </div>
            
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">System Info</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><strong>Version:</strong></td>
                            <td>1.0.0</td>
                        </tr>
                        <tr>
                            <td><strong>PHP Version:</strong></td>
                            <td><?= phpversion() ?></td>
                        </tr>
                        <tr>
                            <td><strong>Database:</strong></td>
                            <td>MySQL</td>
                        </tr>
                        <tr>
                            <td><strong>Server:</strong></td>
                            <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
