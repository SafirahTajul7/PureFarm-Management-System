<?php
require_once '../includes/auth.php';
auth()->checkAdmin();

require_once '../includes/db.php';

// Default response
$response = [
    'success' => false,
    'message' => 'Unknown error occurred'
];

// Check for required parameters
if (!isset($_POST['staff_id']) || !isset($_POST['status'])) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

$staff_id = $_POST['staff_id'];
$status = $_POST['status'];

// Validate status value
$allowed_statuses = ['active', 'inactive', 'on-leave'];
if (!in_array($status, $allowed_statuses)) {
    $response['message'] = 'Invalid status value';
    echo json_encode($response);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update staff status in the database
    $stmt = $pdo->prepare("UPDATE staff SET status = :status, updated_at = NOW() WHERE id = :staff_id");
    $stmt->execute([
        ':status' => $status,
        ':staff_id' => $staff_id
    ]);
    
    // Check if update was successful
    if ($stmt->rowCount() > 0) {
        // Try to log the activity if the table exists
        try {
            // Check if activity_logs table exists
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
            
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                $admin_id = auth()->getUserId();
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, entity_type, entity_id, details, created_at) 
                    VALUES 
                    (:user_id, 'update_status', 'staff', :staff_id, :details, NOW())
                ");
                
                $log_stmt->execute([
                    ':user_id' => $admin_id,
                    ':staff_id' => $staff_id,
                    ':details' => json_encode(['status' => $status])
                ]);
            }
        } catch (PDOException $logError) {
            // Just log the error but don't fail the status update
            error_log("Error logging status change: " . $logError->getMessage());
        }
        
        // Commit transaction
        $pdo->commit();
        
        $response = [
            'success' => true,
            'message' => 'Staff status updated successfully to ' . ucfirst($status),
            'status' => $status
        ];
    } else {
        // Staff not found or status unchanged
        $pdo->rollBack();
        $response['message'] = 'Staff member not found or status unchanged';
    }
} catch (PDOException $e) {
    // Roll back transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error updating staff status: " . $e->getMessage());
    $response['message'] = 'Database error occurred: ' . $e->getMessage();
}
?>