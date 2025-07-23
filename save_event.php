<?php
// filepath: c:\xampp\htdocs\PureFarm\PureFarm\save_event.php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

// Get the JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Initialize response array
$response = array('success' => false, 'message' => '');

// Check if we received valid data
if (!$data || !isset($data['event_date']) || !isset($data['event_time']) || !isset($data['title'])) {
    $response['message'] = 'Missing required data';
    echo json_encode($response);
    exit;
}

try {
    // Check if staff_schedule table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'staff_schedule'")->rowCount() > 0;
    
    // Create table if it doesn't exist
    if (!$tableExists) {
        $sql = "CREATE TABLE staff_schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_date DATE NOT NULL,
            event_time TIME NOT NULL,
            title VARCHAR(255) NOT NULL,
            location VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($sql);
    }
    
    // Check if this is an update (id provided) or insert (no id)
    if (!empty($data['id'])) {
        // Update existing event
        $stmt = $pdo->prepare("UPDATE staff_schedule SET 
                            event_date = :event_date,
                            event_time = :event_time,
                            title = :title,
                            location = :location
                            WHERE id = :id");
        
        $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
    } else {
        // Insert new event
        $stmt = $pdo->prepare("INSERT INTO staff_schedule 
                            (event_date, event_time, title, location)
                            VALUES
                            (:event_date, :event_time, :title, :location)");
    }
    
    // Bind parameters
    $stmt->bindParam(':event_date', $data['event_date']);
    $stmt->bindParam(':event_time', $data['event_time']);
    $stmt->bindParam(':title', $data['title']);
    $stmt->bindParam(':location', $data['location']);
    
    // Execute query
    $stmt->execute();
    
    // Return success response
    $response['success'] = true;
    $response['message'] = 'Event saved successfully';
    $response['id'] = !empty($data['id']) ? $data['id'] : $pdo->lastInsertId();
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('Error saving event: ' . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>