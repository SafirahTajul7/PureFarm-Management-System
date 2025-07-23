<?php
// Disable error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

// Required files
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Set JSON header
header('Content-Type: application/json');

// Main process
try {
    // Validate input
    if (!isset($_GET['crop_id']) || empty($_GET['crop_id'])) {
        echo json_encode(['error' => 'Crop ID is required']);
        exit;
    }
    
    $cropId = (int)$_GET['crop_id']; // Force integer type
    
    // Check if crop exists
    $stmt = $pdo->prepare("SELECT id FROM crops WHERE id = ?");
    $stmt->execute([$cropId]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Crop not found']);
        exit;
    }
    
    // Fetch milestones
    $stmt = $pdo->prepare("SELECT * FROM purefarm_growth_milestones WHERE crop_id = ? ORDER BY date_reached DESC");
    $stmt->execute([$cropId]);
    
    $milestones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Send JSON response
    echo json_encode($milestones);
} 
catch (Exception $e) {
    // Log error but don't expose details
    error_log("Error in get_milestones.php: " . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while fetching data']);
}

// End execution
exit;