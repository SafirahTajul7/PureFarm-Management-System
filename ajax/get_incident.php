<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM incidents WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $incident = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($incident) {
            echo json_encode(['success' => true, 'data' => $incident]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Incident not found']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>