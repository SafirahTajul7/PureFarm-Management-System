<?php
session_start();
require_once 'includes/auth.php';
auth()->checkSupervisor();
require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid feeding schedule ID']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT fs.*, a.animal_id as animal_code 
        FROM feeding_schedules fs
        JOIN animals a ON fs.animal_id = a.id
        WHERE fs.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $feeding = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($feeding) {
        echo json_encode($feeding);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Feeding schedule not found']);
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>