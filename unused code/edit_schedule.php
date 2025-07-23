<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("UPDATE health_schedules 
                              SET animal_id = ?, date = ?, appointment_type = ?, 
                                  details = ?, vet_name = ?, status = ? 
                              WHERE id = ?");
        
        $stmt->execute([
            $_POST['animal_id'],
            $_POST['date'],
            $_POST['appointment_type'],
            $_POST['details'],
            $_POST['vet_name'],
            $_POST['status'],
            $_POST['id']
        ]);

        $_SESSION['success_message'] = "Schedule updated successfully!";
        echo "Success";
    } catch (PDOException $e) {
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
}
?>