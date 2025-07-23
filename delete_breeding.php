<?php
session_start();
require_once 'includes/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Check if we have an ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid breeding record ID";
    header("Location: animals_lifecycle.php");
    exit();
}

$id = $_GET['id'];

try {
    // First, get the record to verify it exists
    $checkStmt = $pdo->prepare("SELECT id FROM breeding_history WHERE id = :id");
    $checkStmt->execute([':id' => $id]);
    
    if ($checkStmt->rowCount() === 0) {
        $_SESSION['error'] = "Breeding record not found";
        header("Location: animals_lifecycle.php");
        exit();
    }
    
    // Delete the breeding record
    $stmt = $pdo->prepare("DELETE FROM breeding_history WHERE id = :id");
    $result = $stmt->execute([':id' => $id]);
    
    if ($result) {
        $_SESSION['success'] = "Breeding record deleted successfully";
    } else {
        $error = $stmt->errorInfo();
        throw new Exception("Delete failed: " . print_r($error, true));
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
} catch(Exception $e) {
    $_SESSION['error'] = "Error deleting breeding record: " . $e->getMessage();
}

// Redirect back to the animal lifecycle page
header("Location: animals_lifecycle.php" . (isset($_SESSION['error']) ? "?error=1" : "?success=1"));
exit();
?>