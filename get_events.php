<?php
// filepath: c:\xampp\htdocs\PureFarm\PureFarm\get_events.php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

// Initialize response array (will be converted to JSON)
$events = [];

try {
    // Check if staff_schedule table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'staff_schedule'")->rowCount() > 0;
    
    if ($tableExists) {
        // Fetch all events
        $stmt = $pdo->prepare("SELECT id, event_date, event_time, title, location FROM staff_schedule ORDER BY event_date, event_time");
        $stmt->execute();
        
        // Fetch all events as associative array
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group events by date
        foreach ($results as $row) {
            $date = $row['event_date'];
            $time = substr($row['event_time'], 0, 5); // Format: HH:MM (remove seconds)
            
            // If this date doesn't exist in our array yet, create it
            if (!isset($events[$date])) {
                $events[$date] = [];
            }
            
            // Add this event to the appropriate date
            $events[$date][] = [
                'id' => $row['id'],
                'time' => $time,
                'title' => $row['title'],
                'location' => $row['location']
            ];
        }
    }
} catch (PDOException $e) {
    error_log('Error fetching events: ' . $e->getMessage());
    // Return empty events array on error
}

// Return events as JSON
header('Content-Type: application/json');
echo json_encode($events);
?>