<?php
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM health_schedules WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode($schedule);
    } catch (PDOException $e) {
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
}
?>