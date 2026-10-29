<?php
/**
 * Settings Management Page
 */

$pageTitle = 'System Settings';
$requiredRole = 'admin'; // Only admin can access settings
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/settings_helper.php';

$success = false;
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid request';
    } else {
        $userId = $_SESSION['user_id'];
        
        // Company Settings
        $settings = [
            'company_name', 'company_tagline', 'company_address',
            'company_phone', 'company_email', 'company_website',
            'company_tax_number', 'invoice_note', 'invoice_terms',
            'timezone', 'weather_city'
        ];
        
        foreach ($settings as $key) {
            if (isset($_POST[$key])) {
                updateSetting($key, cleanInput($_POST[$key]), $userId);
            }
        }
        
        // Boolean settings
        updateSetting('print_logo_enabled', isset($_POST['print_logo_enabled']) ? '1' : '0', $userId);
        updateSetting('print_show_tax_number', isset($_POST['print_show_tax_number']) ? '1' : '0', $userId);
        
        $success = true;
        setFlashMessage('success', 'Settings updated successfully');
    }
}

// Load current settings
$company = getCompanyInfo();
$printSettings = [
    'invoice_note' => getSetting('invoice_note'),
    'invoice_terms' => getSetting('invoice_terms'),
    'print_logo_enabled' => getSetting('print_logo_enabled', false),
    'print_show_tax_number' => getSetting('print_show_tax_number', true),
];
$systemSettings = [
    'timezone' => getSetting('timezone', 'Asia/Dhaka'),
    'weather_city' => getSetting('weather_city', 'Dhaka|23.8103|90.4125'),
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title">
            <i class="bi bi-gear"></i> System Settings
        </h1>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> Settings saved successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        
        <!-- Company Information -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-building"></i> Company Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Company Name *</label>
                        <input type="text" class="form-control" name="company_name" 
                               value="<?= escape($company['name']) ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tagline / Slogan</label>
                        <input type="text" class="form-control" name="company_tagline" 
                               value="<?= escape($company['tagline']) ?>" 
                               placeholder="Smart Business Management System">
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="company_address" rows="2"><?= escape($company['address']) ?></textarea>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="company_phone" 
                               value="<?= escape($company['phone']) ?>">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="company_email" 
                               value="<?= escape($company['email']) ?>">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Website</label>
                        <input type="text" class="form-control" name="company_website" 
                               value="<?= escape($company['website']) ?>">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tax/VAT Number</label>
                        <input type="text" class="form-control" name="company_tax_number" 
                               value="<?= escape($company['tax_number']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Print/Invoice Settings -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-printer"></i> Print & Invoice Settings</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Invoice Footer Note</label>
                        <input type="text" class="form-control" name="invoice_note" 
                               value="<?= escape($printSettings['invoice_note']) ?>"
                               placeholder="Thank you for your business!">
                        <small class="text-muted">This will appear at the bottom of invoices</small>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Terms & Conditions</label>
                        <textarea class="form-control" name="invoice_terms" rows="3"><?= escape($printSettings['invoice_terms']) ?></textarea>
                        <small class="text-muted">Printed on invoices and purchase orders</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="print_logo_enabled" 
                                   id="printLogo" <?= $printSettings['print_logo_enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="printLogo">
                                Show Logo on Prints (if uploaded)
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="print_show_tax_number" 
                                   id="showTax" <?= $printSettings['print_show_tax_number'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="showTax">
                                Show Tax Number on Prints
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Preferences -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-sliders"></i> System Preferences</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">System Timezone</label>
                        <select class="form-select" name="timezone">
                            <?php
                            $timezones = [
                                'Asia/Dhaka' => 'Bangladesh (Dhaka)',
                                'Asia/Kolkata' => 'India (Kolkata)',
                                'Asia/Dubai' => 'UAE (Dubai)',
                                'America/New_York' => 'USA (New York)',
                                'Europe/London' => 'UK (London)',
                                'UTC' => 'UTC'
                            ];
                            foreach ($timezones as $val => $label) {
                                $selected = ($systemSettings['timezone'] === $val) ? 'selected' : '';
                                echo "<option value=\"$val\" $selected>$label</option>";
                            }
                            ?>
                        </select>
                        <small class="text-muted">Affects all reports and activity logs.</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Dashboard Weather City</label>
                        <select class="form-select" name="weather_city">
                            <?php
                            require_once __DIR__ . '/../../includes/districts.php';
                            $cities = getBangladeshDistricts();
                            // Sort alphabetically by district name
                            asort($cities);
                            foreach ($cities as $val => $label) {
                                $selected = ($systemSettings['weather_city'] === $val) ? 'selected' : '';
                                echo "<option value=\"$val\" $selected>$label</option>";
                            }
                            ?>
                        </select>
                        <small class="text-muted">Real-time weather data is fetched securely from Open-Meteo.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="d-flex justify-content-end gap-2">
            <button type="submit" name="save_settings" class="btn btn-primary">
                <i class="bi bi-check-circle"></i> Save Settings
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
