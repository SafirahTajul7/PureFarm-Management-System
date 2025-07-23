<?php
require_once 'includes/db.php';

// Generate new password hash
$password = "Super@123";
$hash = password_hash($password, PASSWORD_DEFAULT);

// Update the password in the database
try {
    $sql = "UPDATE users SET password = ? WHERE username = 'supervisor'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hash]);
    
    echo "Password updated successfully!";
} catch(PDOException $e) {
    echo "Error updating password: " . $e->getMessage();
}
?>