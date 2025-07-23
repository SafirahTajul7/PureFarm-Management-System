<?php
require_once 'includes/auth.php';
auth()->checkAdmin(); // Only allow admins to export data

require_once 'includes/db.php';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="inventory_items_export_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Set CSV column headers
fputcsv($output, [
    'Item Name',
    'SKU',
    'Category',
    'Description',
    'Current Quantity',
    'Unit of Measure',
    'Reorder Level',
    'Maximum Level',
    'Unit Cost',
    'Total Value',
    'Expiry Date',
    'Batch Number',
    'Supplier',
    'Last Updated'
]);

// Process search query from source page
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';

// Prepare base query - similar to the query in item_details.php but with more details for export
$query = "SELECT i.id, i.item_name, i.sku, i.description, i.category_id, c.name as category_name, 
          i.current_quantity, i.unit_of_measure, i.reorder_level, i.maximum_level,
          i.unit_cost, i.expiry_date, i.batch_number, i.supplier_id, s.name as supplier_name,
          i.updated_at
          FROM inventory_items i
          LEFT JOIN item_categories c ON i.category_id = c.id
          LEFT JOIN suppliers s ON i.supplier_id = s.id
          WHERE i.status = 'active'";

// Add search filter if provided
$params = [];
if (!empty($searchTerm)) {
    $query .= " AND (i.item_name LIKE ? OR i.sku LIKE ? OR i.description LIKE ?)";
    $searchParam = "%{$searchTerm}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Add category filter if provided
if (!empty($categoryFilter)) {
    $query .= " AND i.category_id = ?";
    $params[] = $categoryFilter;
}

// Add sorting
$query .= " ORDER BY i.item_name ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    // Write data rows to CSV
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Calculate total value
        $totalValue = $row['current_quantity'] * $row['unit_cost'];
        
        // Format dates 
        $expiryDate = !empty($row['expiry_date']) ? $row['expiry_date'] : 'N/A';
        $updatedAt = !empty($row['updated_at']) ? $row['updated_at'] : 'N/A';
        
        // Prepare row data
        $csvRow = [
            $row['item_name'],
            $row['sku'],
            $row['category_name'],
            $row['description'],
            $row['current_quantity'],
            $row['unit_of_measure'],
            $row['reorder_level'],
            $row['maximum_level'],
            $row['unit_cost'],
            $totalValue,
            $expiryDate,
            $row['batch_number'] ?? 'N/A',
            $row['supplier_name'] ?? 'N/A',
            $updatedAt
        ];
        
        // Write the row to CSV
        fputcsv($output, $csvRow);
    }
    
} catch(PDOException $e) {
    // Log the error
    error_log("Error exporting inventory items: " . $e->getMessage());
    
    // In case of error, write an error message to the CSV
    fputcsv($output, ['Error exporting data. Please contact system administrator.']);
}

// Close the file handle
fclose($output);
exit;
?>