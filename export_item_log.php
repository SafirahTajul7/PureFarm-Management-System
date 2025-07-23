<?php
require_once 'includes/auth.php';
auth()->checkAdmin(); // Only allow admins to export data

require_once 'includes/db.php';

// Check if item ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: item_details.php');
    exit;
}

$itemId = $_GET['id'];

// Fetch item details to include in the export filename
try {
    $stmt = $pdo->prepare("
        SELECT item_name, sku FROM inventory_items 
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        header('Location: item_details.php');
        exit;
    }
} catch(PDOException $e) {
    error_log("Error fetching item details for export: " . $e->getMessage());
    header('Location: item_log.php?id=' . $itemId . '&error=export_failed');
    exit;
}

// Set up filtering - same as in item_log.php
$actionFilter = isset($_GET['action']) ? $_GET['action'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="item_log_' . preg_replace('/[^a-zA-Z0-9]/', '_', $item['item_name']) . '_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Set CSV column headers
fputcsv($output, [
    'Date & Time',
    'Action Type',
    'Quantity',
    'Batch Number',
    'Expiry Date',
    'Unit Cost',
    'Notes',
    'User'
]);

// Prepare the query for logs
$logQuery = "
    SELECT l.*, u.username as user_name
    FROM inventory_log l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE l.item_id = ?
";

$queryParams = [$itemId];

// Add filters if provided
if (!empty($actionFilter)) {
    $logQuery .= " AND l.action_type = ?";
    $queryParams[] = $actionFilter;
}

if (!empty($dateFrom)) {
    $logQuery .= " AND DATE(l.created_at) >= ?";
    $queryParams[] = $dateFrom;
}

if (!empty($dateTo)) {
    $logQuery .= " AND DATE(l.created_at) <= ?";
    $queryParams[] = $dateTo;
}

// Order by date for the export (most recent first)
$logQuery .= " ORDER BY l.created_at DESC";

try {
    $logStmt = $pdo->prepare($logQuery);
    $logStmt->execute($queryParams);
    
    // Running balance calculation
    $currentBalance = 0;
    
    // First, get current quantity
    $balanceStmt = $pdo->prepare("SELECT current_quantity FROM inventory_items WHERE id = ?");
    $balanceStmt->execute([$itemId]);
    $currentBalance = $balanceStmt->fetch(PDO::FETCH_ASSOC)['current_quantity'];
    
    // Store logs in array to reverse for proper balance calculation
    $logs = [];
    while ($row = $logStmt->fetch(PDO::FETCH_ASSOC)) {
        $logs[] = $row;
    }
    
    // Reverse the array to get chronological order (oldest first)
    $logs = array_reverse($logs);
    
    // Calculate initial balance (before first log entry)
    $initialBalance = $currentBalance;
    foreach ($logs as $log) {
        if (in_array($log['action_type'], ['manual_remove', 'sale', 'waste', 'return'])) {
            $initialBalance += $log['quantity'];
        } else {
            $initialBalance -= $log['quantity'];
        }
    }
    
    $runningBalance = $initialBalance;
    
    // Write data rows to CSV (in chronological order)
    foreach ($logs as $log) {
        // Update running balance
        if (in_array($log['action_type'], ['manual_remove', 'sale', 'waste', 'return'])) {
            $quantityFormatted = '-' . $log['quantity'];
            $runningBalance -= $log['quantity'];
        } else {
            $quantityFormatted = '+' . $log['quantity'];
            $runningBalance += $log['quantity'];
        }
        
        // Format unit cost
        $unitCost = !empty($log['unit_cost']) ? number_format($log['unit_cost'], 2) : 'N/A';
        
        // Format action type for better readability
        $actionType = ucfirst(str_replace('_', ' ', $log['action_type']));
        
        // Prepare row data
        $csvRow = [
            date('Y-m-d H:i:s', strtotime($log['created_at'])),
            $actionType,
            $quantityFormatted,
            $log['batch_number'] ?? 'N/A',
            $log['expiry_date'] ?? 'N/A',
            $unitCost,
            $log['notes'] ?? '',
            $log['user_name'] ?? 'System'
        ];
        
        // Write the row to CSV
        fputcsv($output, $csvRow);
    }
    
} catch(PDOException $e) {
    // Log the error
    error_log("Error exporting item log: " . $e->getMessage());
    
    // In case of error, write an error message to the CSV
    fputcsv($output, ['Error exporting data. Please contact system administrator.']);
}

// Close the file handle
fclose($output);
exit;