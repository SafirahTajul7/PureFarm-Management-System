<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

auth()->checkAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE health_records 
                              SET animal_id = ?, 
                                  date = ?,
                                  `condition` = ?,
                                  treatment = ?,
                                  vet_name = ?
                              WHERE id = ?");
        
        $result = $stmt->execute([
            $_POST['animal_id'],
            $_POST['date'],
            $_POST['condition'],
            $_POST['treatment'],
            $_POST['vet_name'],
            $_POST['id']
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update record']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}