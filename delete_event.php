<?php
// filepath: c:\xampp\htdocs\PureFarm\PureFarm\delete_event.php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

// Get the JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Initialize response array
$response = array('success' => false, 'message' => '');

// Check if we received valid data
if (!$data || !isset($data['id'])) {
    $response['message'] = 'Missing event ID';
    echo json_encode($response);
    exit;
}

try {
    // Check if staff_schedule table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'staff_schedule'")->rowCount() > 0;
    
    if ($tableExists) {
        // Delete the event
        $stmt = $pdo->prepare("DELETE FROM staff_schedule WHERE id = :id");
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Event deleted successfully';
        } else {
            $response['message'] = 'Event not found';
        }
    } else {
        $response['message'] = 'Events table does not exist';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error deleting event: ' . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>