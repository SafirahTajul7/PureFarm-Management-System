<?php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header('Location: expenses.php');
    exit;
}

// Check if expense_id is provided
if (!isset($_POST['expense_id']) || empty($_POST['expense_id'])) {
    $_SESSION['error_message'] = "No expense ID provided.";
    header('Location: expenses.php');
    exit;
}

$expense_id = intval($_POST['expense_id']);

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // First, check if the expense exists
    $check_stmt = $pdo->prepare("SELECT id FROM crop_expenses WHERE id = :id");
    $check_stmt->execute(['id' => $expense_id]);
    
    if (!$check_stmt->fetch()) {
        // Expense doesn't exist
        $pdo->rollBack();
        $_SESSION['error_message'] = "Expense not found.";
        header('Location: expenses.php');
        exit;
    }
    
    // Delete the expense
    $stmt = $pdo->prepare("DELETE FROM crop_expenses WHERE id = :id");
    $result = $stmt->execute(['id' => $expense_id]);
    
    if ($result) {
        // Commit transaction
        $pdo->commit();
        $_SESSION['success_message'] = "Expense deleted successfully.";
    } else {
        // Rollback transaction
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error deleting expense.";
    }
    
} catch (PDOException $e) {
    // Rollback transaction
    $pdo->rollBack();
    error_log("Error deleting expense: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error occurred while deleting expense.";
}

// Redirect back to expenses page
header('Location: expenses.php');
exit;
?>