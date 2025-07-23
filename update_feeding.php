<?php
session_start();
require_once 'includes/auth.php';
auth()->checkSupervisor();
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_feeding'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE feeding_schedules 
            SET food_type = ?, quantity = ?, frequency = ?, special_diet = ?, notes = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['food_type'],
            $_POST['quantity'],
            $_POST['frequency'],
            $_POST['special_diet'] ?? '',
            $_POST['notes'] ?? '',
            $_POST['feeding_id']
        ]);
        
        $_SESSION['success'] = "Feeding schedule updated successfully.";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error updating feeding schedule: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "Invalid request.";
}

header('Location: supervisor_animal_management.php');
exit();
?>