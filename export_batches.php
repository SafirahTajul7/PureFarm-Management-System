<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Get search/filter parameters
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$itemFilter = isset($_GET['item_id']) ? $_GET['item_id'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$expiryFilter = isset($_GET['expiry_filter']) ? $_GET['expiry_filter'] : '';

// Prepare base query
$query = "SELECT b.id, b.batch_number, b.item_id, i.item_name, i.sku, b.quantity, i.unit_of_measure,
          b.manufacturing_date, b.expiry_date, b.received_date, 
          b.status, s.name as supplier_name, b.cost_per_unit, b.purchase_order_id,
          b.created_at, b.updated_at
          FROM inventory_batches b
          JOIN inventory_items i ON b.item_id = i.id
          LEFT JOIN suppliers s ON b.supplier_id = s.id
          WHERE 1=1";

// Add search filter if provided
$params = [];
if (!empty($searchTerm)) {
    $query .= " AND (b.batch_number LIKE ? OR i.item_name LIKE ?)";
    $searchParam = "%{$searchTerm}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Add item filter if provided
if (!empty($itemFilter)) {
    $query .= " AND b.item_id = ?";
    $params[] = $itemFilter;
}

// Add status filter if provided
if (!empty($statusFilter)) {
    $query .= " AND b.status = ?";
    $params[] = $statusFilter;
}

// Add expiry filter if provided
if (!empty($expiryFilter)) {
    $today = date('Y-m-d');
    if ($expiryFilter == 'expired') {
        $query .= " AND b.expiry_date < ?";
        $params[] = $today;
    } else if ($expiryFilter == 'expiring_soon') {
        $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
        $query .= " AND b.expiry_date BETWEEN ? AND ?";
        $params[] = $today;
        $params[] = $thirtyDaysLater;
    } else if ($expiryFilter == 'valid') {
        $query .= " AND b.expiry_date > ?";
        $params[] = $today;
    }
}

// Add sorting
$query .= " ORDER BY b.received_date DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=batch_export_' . date('Y-m-d_His') . '.csv');
    
    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM to fix UTF-8 in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Output column headers
    fputcsv($output, [
        'Batch Number',
        'Item Name',
        'SKU',
        'Quantity',
        'Unit',
        'Manufacturing Date',
        'Expiry Date',
        'Received Date',
        'Status',
        'Supplier',
        'Cost Per Unit',
        'Purchase Order ID',
        'Created Date',
        'Last Updated'
    ]);
    
    // Format and output each row
    foreach ($batches as $batch) {
        // Calculate days until expiry if applicable
        $expiryInfo = 'N/A';
        if (!empty($batch['expiry_date'])) {
            $expiryDate = new DateTime($batch['expiry_date']);
            $today = new DateTime();
            $interval = $today->diff($expiryDate);
            $daysRemaining = $expiryDate > $today ? $interval->days : -$interval->days;
            
            if ($daysRemaining < 0) {
                $expiryInfo = $batch['expiry_date'] . ' (Expired ' . abs($daysRemaining) . ' days ago)';
            } elseif ($daysRemaining <= 30) {
                $expiryInfo = $batch['expiry_date'] . ' (' . $daysRemaining . ' days left)';
            } else {
                $expiryInfo = $batch['expiry_date'];
            }
        }
        
        fputcsv($output, [
            $batch['batch_number'],
            $batch['item_name'],
            $batch['sku'],
            $batch['quantity'],
            $batch['unit_of_measure'],
            $batch['manufacturing_date'] ?? 'N/A',
            $expiryInfo,
            $batch['received_date'],
            ucfirst($batch['status']),
            $batch['supplier_name'] ?? 'N/A',
            !empty($batch['cost_per_unit']) ? number_format($batch['cost_per_unit'], 2) : 'N/A',
            $batch['purchase_order_id'] ?? 'N/A',
            date('Y-m-d H:i', strtotime($batch['created_at'])),
            date('Y-m-d H:i', strtotime($batch['updated_at']))
        ]);
    }
    
    fclose($output);
    exit();
    
} catch(PDOException $e) {
    error_log("Error exporting batches: " . $e->getMessage());
    
    // Redirect back with error
    header('Location: batch_tracking.php?export_error=1');
    exit;
}