<?php
require_once __DIR__ . '/config/database.php';

$sql = "SHOW COLUMNS FROM users LIKE 'two_factor_secret'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    echo "Adding two_factor_secret column...\n";
    $conn->query("ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(255) NULL DEFAULT NULL AFTER password");
    echo "Done.\n";
} else {
    echo "Column already exists.\n";
}
?>
