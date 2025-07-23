<?php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header('Location: revenue.php');
    exit;
}

// Check if revenue_id is provided
if (!isset($_POST['revenue_id']) || empty($_POST['revenue_id'])) {
    $_SESSION['error_message'] = "No revenue ID provided.";
    header('Location: revenue.php');
    exit;
}

$revenue_id = intval($_POST['revenue_id']);

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // First, check if the revenue entry exists
    $check_stmt = $pdo->prepare("SELECT id FROM crop_revenue WHERE id = :id");
    $check_stmt->execute(['id' => $revenue_id]);
    
    if (!$check_stmt->fetch()) {
        // Revenue entry doesn't exist
        $pdo->rollBack();
        $_SESSION['error_message'] = "Revenue entry not found.";
        header('Location: revenue.php');
        exit;
    }
    
    // Delete the revenue entry
    $stmt = $pdo->prepare("DELETE FROM crop_revenue WHERE id = :id");
    $result = $stmt->execute(['id' => $revenue_id]);
    
    if ($result) {
        // Commit transaction
        $pdo->commit();
        $_SESSION['success_message'] = "Revenue entry deleted successfully.";
    } else {
        // Rollback transaction
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error deleting revenue entry.";
    }
    
} catch (PDOException $e) {
    // Rollback transaction
    $pdo->rollBack();
    error_log("Error deleting revenue entry: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error occurred while deleting revenue entry.";
}

// Redirect back to revenue page
header('Location: revenue.php');
exit;
?>