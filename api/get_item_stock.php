<?php
require_once '../includes/auth.php';
auth()->checkAdmin(); // Only allow admin access

require_once '../includes/db.php';

// Get item ID from request
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

// Default response
$response = [
    'success' => false,
    'current_quantity' => 0,
    'unit_of_measure' => ''
];

if ($itemId > 0) {
    try {
        // Get item stock information
        $stmt = $pdo->prepare("
            SELECT current_quantity, unit_of_measure 
            FROM inventory_items 
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            $response = [
                'success' => true,
                'current_quantity' => $item['current_quantity'],
                'unit_of_measure' => $item['unit_of_measure']
            ];
        }
    } catch (PDOException $e) {
        error_log("Error fetching item stock: " . $e->getMessage());
    }
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>