<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

auth()->checkAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("INSERT INTO health_records (animal_id, date, `condition`, treatment, vet_name) 
                              VALUES (?, ?, ?, ?, ?)");
        
        $result = $stmt->execute([
            $_POST['animal_id'],
            $_POST['date'],
            $_POST['condition'],
            $_POST['treatment'],
            $_POST['vet_name']
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Record added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add record']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}