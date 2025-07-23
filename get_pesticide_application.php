<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid application ID']);
    exit;
}

$applicationId = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.crop_name, f.field_name, pt.name as pesticide_name, pt.type as pesticide_type
        FROM pesticide_applications p
        JOIN crops c ON p.crop_id = c.id
        JOIN fields f ON c.field_id = f.id
        JOIN pesticide_types pt ON p.pesticide_type_id = pt.id
        WHERE p.id = ?
    ");
    
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        echo json_encode(['error' => 'Application not found']);
        exit;
    }
    
    echo json_encode($application);
    
} catch(PDOException $e) {
    error_log("Error fetching pesticide application: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}