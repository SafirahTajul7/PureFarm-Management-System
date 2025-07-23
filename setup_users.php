-- First, ensure we have the users table with correct structure
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(20) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Now create PHP script to insert users with proper password hashing
<?php
require_once 'includes/db.php';

// Clear existing users first
$pdo->exec("DELETE FROM users");

// Reset auto-increment
$pdo->exec("ALTER TABLE users AUTO_INCREMENT = 1");

// Prepare the insert statement
$stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");

// Admin user
$admin_username = 'admin';
$admin_password = password_hash('Admin@123', PASSWORD_DEFAULT);
$stmt->execute([$admin_username, $admin_password, 'admin']);

// Supervisor user
$super_username = 'supervisor';
$super_password = password_hash('super@123', PASSWORD_DEFAULT);
$stmt->execute([$super_username, $super_password, 'supervisor']);

echo "Users created successfully!\n";
?>

-- For reference, you can verify users with this query:
-- SELECT id, username, role, created_at FROM users;

-- Note: Don't display or store plain passwords in production!
-- Passwords in this script:
-- admin: Admin@123
-- supervisor: super@123