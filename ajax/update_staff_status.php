<?php
// Include authentication and database
require_once '../includes/auth.php';
auth()->checkAdmin();

require_once '../includes/db.php';

// Default response
$response = [
    'success' => false,
    'message' => 'Unknown error occurred'
];

// Check if required parameters are provided
if (isset($_POST['staff_id']) && isset($_POST['status'])) {
    $staff_id = $_POST['staff_id'];
    $status = $_POST['status'];
    
    // Validate status
    $valid_statuses = ['active', 'inactive', 'on-leave'];
    if (!in_array($status, $valid_statuses)) {
        $response['message'] = 'Invalid status provided';
        echo json_encode($response);
        exit;
    }
    
    // Update staff status in database
    try {
        $stmt = $pdo->prepare("UPDATE staff SET status = :status, updated_at = NOW() WHERE id = :staff_id");
        $stmt->execute([
            ':status' => $status,
            ':staff_id' => $staff_id
        ]);
        
        if ($stmt->rowCount() > 0) {
            // Log the status change
            $admin_id = auth()->getUserId();
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs 
                (user_id, action, entity_type, entity_id, details, created_at) 
                VALUES 
                (:user_id, 'update', 'staff', :staff_id, :details, NOW())
            ");
            $log_stmt->execute([
                ':user_id' => $admin_id,
                ':staff_id' => $staff_id,
                ':details' => json_encode(['changed_status' => $status])
            ]);
            
            $response['success'] = true;
            $response['message'] = 'Staff status updated successfully';
        } else {
            $response['message'] = 'Staff member not found or status unchanged';
        }
    } catch (PDOException $e) {
        error_log('Database error in update_staff_status.php: ' . $e->getMessage());
        $response['message'] = 'Database error occurred';
    }
} else {
    $response['message'] = 'Missing required parameters';
}

// Return JSON response
echo json_encode($response);
exit;