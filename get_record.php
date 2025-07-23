<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

auth()->checkAdmin();

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM health_records WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            // Convert the date to Y-m-d format for the input field
            $record['date'] = date('Y-m-d', strtotime($record['date']));
            echo json_encode($record);
        } else {
            echo json_encode(['error' => 'Record not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No ID provided']);
}