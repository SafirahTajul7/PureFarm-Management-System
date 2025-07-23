<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM health_schedules WHERE id = ?");
        $stmt->execute([$_POST['id']]);

        $_SESSION['success_message'] = "Schedule deleted successfully!";
        echo "Success";
    } catch (PDOException $e) {
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
}
?>