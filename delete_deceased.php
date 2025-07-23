<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Check if ID parameter exists
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: animals_lifecycle.php?error=Invalid record ID");
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Get the animal_id before deleting the record
    $stmt = $pdo->prepare("SELECT animal_id FROM deceased_animals WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new PDOException("Record not found");
    }

    // Delete the deceased animal record
    $stmt = $pdo->prepare("DELETE FROM deceased_animals WHERE id = ?");
    $stmt->execute([$_GET['id']]);

    // Commit transaction
    $pdo->commit();

    // Redirect with success message
    header("Location: animals_lifecycle.php?success=Record deleted successfully");
    exit();

} catch(PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    // Log the error (you should implement proper error logging)
    error_log("Error deleting deceased animal record: " . $e->getMessage());
    
    // Redirect with error message
    header("Location: animals_lifecycle.php?error=" . urlencode("Failed to delete record: " . $e->getMessage()));
    exit();
}
?>