<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is logged in
auth()->checkAuth();

// Set the content type to JSON
header('Content-Type: application/json');

// Validate input
if (!isset($_GET['field_id']) || !is_numeric($_GET['field_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid field ID'
    ]);
    exit;
}

$field_id = (int)$_GET['field_id'];

try {
    // Query the database for the field size
    $stmt = $pdo->prepare("
        SELECT field_size_acres FROM fields WHERE id = ?
    ");
    $stmt->execute([$field_id]);
    $field = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($field) {
        echo json_encode([
            'success' => true,
            'size' => $field['field_size_acres']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Field not found'
        ]);
    }
} catch(PDOException $e) {
    error_log("Error fetching field size: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}