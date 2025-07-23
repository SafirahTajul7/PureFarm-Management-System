<?php
session_start();
require_once 'includes/auth.php';
auth()->checkSupervisor();
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_vaccination'])) {
    try {
        // Debug - check what's being passed
        error_log("Vaccination ID: " . $_POST['vaccination_id']);
        error_log("Animal ID: " . $_POST['animal_id']);
        error_log("Schedule Date: " . $_POST['schedule_date']);
        
        // Update the vaccination record with new scheduled date
        $stmt = $pdo->prepare("UPDATE vaccinations
                               SET scheduled_date = ?,
                                   administered_by = ?,
                                   notes = ?
                               WHERE id = ?");
        $stmt->execute([
            $_POST['schedule_date'],
            $_POST['vet_name'],
            $_POST['notes'],
            $_POST['vaccination_id']
        ]);
        
        // Create a task for this vaccination
        $stmt = $pdo->prepare("INSERT INTO tasks (title, description, assigned_to, due_date, status, priority)
                               VALUES (?, ?, ?, ?, 'pending', 'high')");
        $stmt->execute([
            'Vaccination: ' . $_POST['schedule_vaccine_type'],
            'Animal ID: ' . $_POST['schedule_animal_code'] . '. Notes: ' . $_POST['notes'],
            $_POST['vet_name'],
            $_POST['schedule_date']
        ]);
        
        $_SESSION['success'] = "Vaccination scheduled successfully.";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error scheduling vaccination: " . $e->getMessage();
        error_log("Error in schedule_vaccination.php: " . $e->getMessage());
    }
    
    header('Location: supervisor_animal_management.php');
    exit();
}

// If reached here without POST data, redirect
header('Location: supervisor_animal_management.php');
exit();