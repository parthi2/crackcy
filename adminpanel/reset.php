<?php
require_once __DIR__ . '/../config/database.php';

// Generate a fresh, valid Bcrypt hash for password "admin123"
$newPassword = 'admin123';
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

// Update password for user 'parthi'
$stmt = $pdo->prepare("UPDATE admin SET password = :hash WHERE username = 'parthi'");
$stmt->execute([':hash' => $newHash]);

echo "Password for <strong>parthi</strong> has been reset to: <strong>admin123</strong><br>";
echo "<a href='login.php'>Go to Login Page</a>";