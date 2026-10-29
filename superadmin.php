<?php
session_start();

// Basic security for the prototype
$admin_password = 'admin';
if (!isset($_SESSION['superadmin_logged_in'])) {
    if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
        $_SESSION['superadmin_logged_in'] = true;
    } else {
        echo '<html><body style="font-family: Arial; text-align: center; margin-top: 100px;">
                <h2>Super Admin Login</h2>
                <form method="POST">
                    <input type="password" name="password" placeholder="Password (admin)" required>
                    <button type="submit">Login</button>
                </form>
              </body></html>';
        exit;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: superadmin.php");
    exit;
}

// Connect to saas_master_db
$conn = new mysqli('localhost', 'root', '', 'saas_master_db');
if ($conn->connect_error) {
    die("Master DB Connection failed: " . $conn->connect_error);
}

$message = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_tenant') {
        $domain = $conn->real_escape_string($_POST['domain']);
        $db_name = $conn->real_escape_string($_POST['db_name']);
        $company_name = $conn->real_escape_string($_POST['company_name']);
        
        $stmt = $conn->prepare("INSERT INTO tenants (domain, db_name, company_name) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $domain, $db_name, $company_name);
        if ($stmt->execute()) {
            // AUTOMATICALLY CREATE DB AND IMPORT SCHEMA
            $master = new mysqli('localhost', 'root', '');
            if ($master->query("CREATE DATABASE IF NOT EXISTS `$db_name`")) {
                $tenant_conn = new mysqli('localhost', 'root', '', $db_name);
                $sql = file_get_contents(__DIR__ . '/forbitto_at_db.sql');
                if ($sql && $tenant_conn->multi_query($sql)) {
                    // Flush multi_queries
                    do {
                        if ($r = $tenant_conn->store_result()) { $r->free(); }
                    } while ($tenant_conn->more_results() && $tenant_conn->next_result());
                    $message = "<div style='color: green; padding: 10px; background: #e8f5e9; margin-bottom: 15px;'>Success: Tenant added and Database `$db_name` provisioned automatically!</div>";
                } else {
                    $message = "<div style='color: orange; padding: 10px; background: #fff3cd; margin-bottom: 15px;'>Warning: Tenant added, but schema import failed: " . $tenant_conn->error . "</div>";
                }
            } else {
                $message = "<div style='color: orange; padding: 10px; background: #fff3cd; margin-bottom: 15px;'>Warning: Tenant added, but DB creation failed: " . $master->error . "</div>";
            }
        } else {
            $message = "<div style='color: red; padding: 10px; background: #ffebee; margin-bottom: 15px;'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
    
    if ($_POST['action'] === 'toggle_status') {
        $id = (int)$_POST['id'];
        $new_status = $_POST['status'] === 'active' ? 'suspended' : 'active';
        $conn->query("UPDATE tenants SET status = '$new_status' WHERE id = $id");
        $message = "<div style='color: green; padding: 10px; background: #e8f5e9; margin-bottom: 15px;'>Status updated to $new_status!</div>";
    }
}

// Fetch all tenants
$tenants = [];
$res = $conn->query("SELECT * FROM tenants ORDER BY created_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $tenants[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SaaS Super Admin Control Panel</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { color: #333; display: flex; justify-content: space-between; align-items: center; }
        .header-btn { background: #dc3545; color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .badge-active { background: #28a745; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .badge-suspended { background: #dc3545; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .btn { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .card { background: #f8f9fa; padding: 20px; border-radius: 5px; border: 1px solid #ddd; margin-bottom: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>SaaS Super Admin <a href="?logout=1" class="header-btn">Logout</a></h1>
        <?= $message ?>
        
        <div class="card">
            <h3>Add New Tenant (Client)</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_tenant">
                <div style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Company Name</label>
                        <input type="text" name="company_name" placeholder="e.g. X Mart" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Subdomain / Domain</label>
                        <input type="text" name="domain" placeholder="e.g. xmart.com" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Database Name</label>
                        <input type="text" name="db_name" placeholder="e.g. erp_xmart_db" required>
                    </div>
                </div>
                
                <!-- Prototype file inputs (Not fully functional yet) -->
                <div style="display: flex; gap: 15px; margin-top: 10px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Company Logo (Optional)</label>
                        <input type="file" name="logo" accept="image/*">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Favicon (Optional)</label>
                        <input type="file" name="favicon" accept="image/*">
                    </div>
                </div>

                <button type="submit" class="btn">Provision New Client</button>
                <p style="font-size: 12px; color: #666; margin-top: 10px;">* Note: In this prototype, adding a tenant only saves it to saas_master_db. The full automated DB creation and logo saving logic is built during final deployment.</p>
            </form>
        </div>

        <h3>Active & Suspended Clients</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Company Name</th>
                    <th>Domain</th>
                    <th>Database</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tenants as $t): ?>
                <tr>
                    <td><?= $t['id'] ?></td>
                    <td><strong><?= htmlspecialchars($t['company_name']) ?></strong></td>
                    <td><a href="http://<?= htmlspecialchars($t['domain']) ?>" target="_blank"><?= htmlspecialchars($t['domain']) ?></a></td>
                    <td><?= htmlspecialchars($t['db_name']) ?></td>
                    <td>
                        <span class="badge-<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span>
                    </td>
                    <td>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <input type="hidden" name="status" value="<?= $t['status'] ?>">
                            <button type="submit" style="background: <?= $t['status'] === 'active' ? '#dc3545' : '#28a745' ?>; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">
                                <?= $t['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
