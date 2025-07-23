<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'redirect' => 'manage_deliveries.php'
];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purchase_id = isset($_POST['purchase_id']) ? intval($_POST['purchase_id']) : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $delivery_date = isset($_POST['delivery_date']) ? trim($_POST['delivery_date']) : null;
    $tracking_number = isset($_POST['tracking_number']) ? trim($_POST['tracking_number']) : null;
    $carrier = isset($_POST['carrier']) ? trim($_POST['carrier']) : null;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
    
    // Check if redirecting to details page
    $redirect_to_details = isset($_POST['redirect']) && $_POST['redirect'] === 'details';
    
    // Validate input
    if ($purchase_id <= 0 || empty($status)) {
        $response['message'] = "Purchase ID and status are required fields.";
    } else if ($status === 'delivered' && empty($delivery_date)) {
        $response['message'] = "Delivery date is required when status is 'Delivered'.";
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update purchase status
            $stmt = $pdo->prepare("
                UPDATE purchases 
                SET 
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $purchase_id]);
            
            // Update delivery date if status is delivered
            if ($status === 'delivered' && !empty($delivery_date)) {
                $stmt = $pdo->prepare("
                    UPDATE purchases 
                    SET delivery_date = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$delivery_date, $purchase_id]);
            }
            
            // Add entry to delivery tracking
            $stmt = $pdo->prepare("
                INSERT INTO delivery_tracking 
                (purchase_id, status_update, status_date, tracking_number, carrier, notes) 
                VALUES (?, ?, NOW(), ?, ?, ?)
            ");
            $stmt->execute([$purchase_id, $status, $tracking_number, $carrier, $notes]);
            
            // Commit transaction
            $pdo->commit();
            
            $response['success'] = true;
            $response['message'] = "Delivery status updated successfully.";
            
            // Set redirect URL
            if ($redirect_to_details) {
                $response['redirect'] = "purchase_details.php?id=" . $purchase_id;
            }
        } catch(PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log("Error updating delivery status: " . $e->getMessage());
            $response['message'] = "An error occurred while updating the delivery status: " . $e->getMessage();
        }
    }
}

// Determine if this is an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($is_ajax) {
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    // Set session flash message
    if ($response['success']) {
        $_SESSION['flash_success'] = $response['message'];
    } else {
        $_SESSION['flash_error'] = $response['message'];
    }
    
    // Redirect
    header('Location: ' . $response['redirect']);
    exit;
}