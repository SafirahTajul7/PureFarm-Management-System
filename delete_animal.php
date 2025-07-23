<?php
// delete_animal.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

if (isset($_GET['id'])) {
    try {
        // First check if there are any related records
        $checkHealth = $pdo->prepare("SELECT COUNT(*) FROM health_records WHERE animal_id = ?");
        $checkVaccinations = $pdo->prepare("SELECT COUNT(*) FROM vaccinations WHERE animal_id = ?");
        $checkWeight = $pdo->prepare("SELECT COUNT(*) FROM weight_records WHERE animal_id = ?");
        $checkBreeding = $pdo->prepare("SELECT COUNT(*) FROM breeding_history WHERE animal_id = ? OR partner_id = ?");
        
        $checkHealth->execute([$_GET['id']]);
        $checkVaccinations->execute([$_GET['id']]);
        $checkWeight->execute([$_GET['id']]);
        $checkBreeding->execute([$_GET['id'], $_GET['id']]);
        
        // If there are related records, delete them first
        if ($checkHealth->fetchColumn() > 0) {
            $pdo->prepare("DELETE FROM health_records WHERE animal_id = ?")->execute([$_GET['id']]);
        }
        if ($checkVaccinations->fetchColumn() > 0) {
            $pdo->prepare("DELETE FROM vaccinations WHERE animal_id = ?")->execute([$_GET['id']]);
        }
        if ($checkWeight->fetchColumn() > 0) {
            $pdo->prepare("DELETE FROM weight_records WHERE animal_id = ?")->execute([$_GET['id']]);
        }
        if ($checkBreeding->fetchColumn() > 0) {
            $pdo->prepare("DELETE FROM breeding_history WHERE animal_id = ? OR partner_id = ?")->execute([$_GET['id'], $_GET['id']]);
        }
        
        // Finally delete the animal record
        $stmt = $pdo->prepare("DELETE FROM animals WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        
        if ($stmt->rowCount() > 0) {
            header("Location: animal_records.php?message=Animal successfully deleted");
        } else {
            header("Location: animal_records.php?error=Animal not found");
        }
        exit();
    } catch(PDOException $e) {
        header("Location: animal_records.php?error=" . urlencode("Error deleting animal: " . $e->getMessage()));
        exit();
    }
} else {
    header("Location: animal_records.php?error=No animal ID specified");
    exit();
}
?>
