<?php
session_start();
require_once 'db.php';

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM feeding_schedules WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $_SESSION['success'] = "Feeding schedule deleted successfully.";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error deleting feeding schedule: " . $e->getMessage();
    }
}

header('Location: feeding_management.php');
exit();
?>