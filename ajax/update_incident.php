<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['incident_id'])) {
    try {
        $sql = "UPDATE incidents SET 
                type = ?, 
                description = ?, 
                date_reported = ?, 
                severity = ?, 
                reported_by = ?, 
                affected_area = ?, 
                resolution_notes = ?, 
                status = ?";
        
        $params = [
            $_POST['type'],
            $_POST['description'],
            $_POST['date_reported'],
            $_POST['severity'],
            $_POST['reported_by'],
            $_POST['affected_area'],
            $_POST['resolution_notes'],
            $_POST['status']
        ];
        
        // Add resolution_date if provided
        if (!empty($_POST['resolution_date'])) {
            $sql .= ", resolution_date = ?";
            $params[] = $_POST['resolution_date'];
        } else if ($_POST['status'] === 'open') {
            $sql .= ", resolution_date = NULL";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $_POST['incident_id'];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>