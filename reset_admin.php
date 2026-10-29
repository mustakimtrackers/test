<?php
require_once __DIR__ . '/config/database.php';

// Ensure default Head Office branch exists
$bRes = $conn->query("SELECT id FROM branches WHERE id = 1");
if (!$bRes || $bRes->num_rows === 0) {
    $conn->query("INSERT INTO branches (id, name, address, created_at) VALUES (1, 'Head Office', 'Main Branch', NOW()) ON DUPLICATE KEY UPDATE name = 'Head Office'");
}

$email = 'admin@admin.com';
$password = '12345678';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Find first admin
$res = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $stmt = $conn->prepare("UPDATE users SET email = ?, password = ?, is_active = 1, branch_id = 1 WHERE id = ?");
    $stmt->bind_param("ssi", $email, $hashed_password, $row['id']);
    $stmt->execute();
    echo "<h1>✅ Admin User Reset Successful!</h1>";
    echo "<p><b>Email:</b> $email</p>";
    echo "<p><b>Password:</b> $password</p>";
} else {
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, is_active, branch_id) VALUES ('Super Admin', ?, ?, 'admin', 1, 1)");
    $stmt->bind_param("ss", $email, $hashed_password);
    $stmt->execute();
    echo "<h1>✅ Admin User Created!</h1>";
    echo "<p><b>Email:</b> $email</p>";
    echo "<p><b>Password:</b> $password</p>";
}

echo "<br><a href='modules/auth/login.php'>Go to Login</a>";
?>
