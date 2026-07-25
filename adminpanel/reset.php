<?php
require_once __DIR__ . '/../config/database.php';

// Generate a fresh, valid Bcrypt hash for password "test123"
$newPassword = 'test123';
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

// Update password for user 'test'
$stmt = $pdo->prepare("UPDATE admin SET password = :hash WHERE username = 'test'");
$stmt->execute([':hash' => $newHash]);

echo "Password for <strong>test</strong> has been reset to: <strong>admin123</strong><br>";
echo "<a href='login.php'>Go to Login Page</a>";