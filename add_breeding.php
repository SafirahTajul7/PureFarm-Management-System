<?php
session_start();
require_once 'includes/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log the POST data
    error_log("POST data received: " . print_r($_POST, true));
    
    try {
        // Verify database connection
        if (!$pdo) {
            throw new Exception("Database connection failed");
        }

        // Validate that all required fields are present
        if (empty($_POST['animal_id']) || empty($_POST['partner_id']) || 
            empty($_POST['date']) || empty($_POST['outcome'])) {
            throw new Exception("All required fields must be filled");
        }
        
        // Fix potential issue with date format
        $date = date('Y-m-d', strtotime($_POST['date']));
        
        // Simply use the animal_id and partner_id directly
        // This assumes your breeding_history table has foreign keys that reference animals.animal_id
        $stmt = $pdo->prepare("
            INSERT INTO breeding_history (animal_id, partner_id, date, outcome, notes) 
            VALUES (:animal_id, :partner_id, :date, :outcome, :notes)
        ");
        
        // Debug: Log the data being inserted
        $params = [
            ':animal_id' => $_POST['animal_id'],      // Use animal_id directly
            ':partner_id' => $_POST['partner_id'],    // Use partner_id directly
            ':date' => $date,
            ':outcome' => $_POST['outcome'],
            ':notes' => $_POST['notes'] ?? ''
        ];
        error_log("Attempting to insert with parameters: " . print_r($params, true));

        // Execute the statement
        $result = $stmt->execute($params);
        
        if ($result) {
            $_SESSION['success'] = "Breeding record added successfully";
            error_log("Insert successful");
        } else {
            $error = $stmt->errorInfo();
            throw new Exception("Insert failed: " . print_r($error, true));
        }

    } catch(PDOException $e) {
        error_log("Database error in add_breeding.php: " . $e->getMessage());
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    } catch(Exception $e) {
        error_log("Error in add_breeding.php: " . $e->getMessage());
        $_SESSION['error'] = "Error adding breeding record: " . $e->getMessage();
    }
}

// Redirect back to the correct page name
header("Location: animals_lifecycle.php" . (isset($_SESSION['error']) ? "?error=1" : "?success=1"));
exit();
?>