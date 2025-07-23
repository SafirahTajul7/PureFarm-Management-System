<?php
require_once 'includes/auth.php';
auth()->checkAdmin(); // Only allow admin access

require_once 'includes/db.php';

// Check if this is an export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Get the filter parameters
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
    $dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
    $wasteTypeFilter = isset($_GET['waste_type']) ? $_GET['waste_type'] : '';

    // Prepare base query - similar to the one for displaying records
    $query = "SELECT w.id, w.item_id, w.quantity, w.waste_type, w.reason, w.date, w.notes, 
              i.item_name, i.sku, i.unit_of_measure, c.name as category_name, 
              u.username as recorded_by
              FROM waste_management w
              LEFT JOIN inventory_items i ON w.item_id = i.id
              LEFT JOIN item_categories c ON i.category_id = c.id
              LEFT JOIN users u ON w.created_by = u.id
              WHERE 1=1";

    // Add search filter if provided
    $params = [];
    if (!empty($searchTerm)) {
        $query .= " AND (i.item_name LIKE ? OR w.reason LIKE ? OR w.notes LIKE ?)";
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

    // Add date filter if provided
    if (!empty($dateFilter)) {
        switch ($dateFilter) {
            case 'today':
                $query .= " AND DATE(w.date) = CURDATE()";
                break;
            case 'yesterday':
                $query .= " AND DATE(w.date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'this_week':
                $query .= " AND YEARWEEK(w.date, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'this_month':
                $query .= " AND MONTH(w.date) = MONTH(CURDATE()) AND YEAR(w.date) = YEAR(CURDATE())";
                break;
            case 'this_year':
                $query .= " AND YEAR(w.date) = YEAR(CURDATE())";
                break;
        }
    }

    // Add waste type filter if provided
    if (!empty($wasteTypeFilter)) {
        $query .= " AND w.waste_type = ?";
        $params[] = $wasteTypeFilter;
    }

    // Add sorting
    $query .= " ORDER BY w.date DESC, i.item_name ASC";

    try {
        // Execute the query
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $wasteRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="waste_records_' . date('Y-m-d') . '.csv"');
        
        // Create a file pointer connected to the output stream
        $output = fopen('php://output', 'w');
        
        // Set column headers for the CSV file
        fputcsv($output, [
            'ID',
            'Date',
            'Item Name',
            'Item SKU',
            'Category',
            'Quantity',
            'Unit',
            'Waste Type',
            'Reason',
            'Notes',
            'Recorded By'
        ]);
        
        // Output each row of data
        foreach ($wasteRecords as $waste) {
            fputcsv($output, [
                $waste['id'],
                $waste['date'],
                $waste['item_name'],
                $waste['sku'],
                $waste['category_name'],
                $waste['quantity'],
                $waste['unit_of_measure'],
                ucfirst($waste['waste_type']),
                $waste['reason'],
                $waste['notes'],
                $waste['recorded_by']
            ]);
        }
        
        // Close the file pointer
        fclose($output);
        
        // Stop execution to prevent the rest of the page from loading
        exit;
        
    } catch (PDOException $e) {
        // Log the error
        error_log("Error exporting waste records: " . $e->getMessage());
        
        // Set error message to be displayed when redirected
        $_SESSION['error_message'] = "Failed to export waste records. Please try again later.";
        
        // Redirect back to the waste management page
        header("Location: waste_management.php");
        exit;
    }
}

// Initialize variables
$errorMsg = '';
$successMsg = '';
$wasteRecords = [];
$categories = [];
$items = [];

// Check for session error message (from export)
if (isset($_SESSION['error_message'])) {
    $errorMsg = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Fetch categories for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM item_categories WHERE status = 'active' ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $errorMsg = "Failed to load categories. Please try again later.";
}

// Fetch items for dropdown
try {
    $stmt = $pdo->query("SELECT id, item_name FROM inventory_items WHERE status = 'active' ORDER BY item_name ASC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching items: " . $e->getMessage());
    $errorMsg = "Failed to load inventory items. Please try again later.";
}

// Handle form submission for adding a new waste record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_waste'])) {
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (float)$_POST['quantity'] : 0;
    $wasteType = isset($_POST['waste_type']) ? trim($_POST['waste_type']) : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $date = isset($_POST['date']) ? trim($_POST['date']) : date('Y-m-d');
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Validate input
    if ($itemId <= 0) {
        $errorMsg = "Please select a valid inventory item.";
    } elseif ($quantity <= 0) {
        $errorMsg = "Quantity must be greater than zero.";
    } elseif (empty($wasteType)) {
        $errorMsg = "Waste type is required.";
    } elseif (empty($reason)) {
        $errorMsg = "Reason for waste is required.";
    } else {
        try {
            // Check if we have enough stock for the waste recording
            $stmt = $pdo->prepare("SELECT current_quantity FROM inventory_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $currentStock = $stmt->fetchColumn();
            
            if ($currentStock < $quantity) {
                $errorMsg = "Cannot record waste greater than available stock ({$currentStock} units).";
            } else {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Insert waste record
                $stmt = $pdo->prepare("
                    INSERT INTO waste_management (
                        item_id, quantity, waste_type, reason, date, notes, created_by, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, NOW()
                    )
                ");
                $stmt->execute([
                    $itemId, 
                    $quantity, 
                    $wasteType, 
                    $reason, 
                    $date, 
                    $notes,
                    $_SESSION['user_id'] // Assuming user_id is stored in session
                ]);
                
                // Update inventory quantity
                $stmt = $pdo->prepare("
                    UPDATE inventory_items 
                    SET current_quantity = current_quantity - ?, 
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$quantity, $itemId]);
                
                // Commit transaction
                $pdo->commit();
                
                $successMsg = "Waste record added successfully.";
            }
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log("Error adding waste record: " . $e->getMessage());
            $errorMsg = "Failed to add waste record. Please try again later.";
        }
    }
}

// Handle waste record deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $wasteId = (int)$_GET['id'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get waste record details to restore inventory
        $stmt = $pdo->prepare("
            SELECT item_id, quantity 
            FROM waste_management 
            WHERE id = ?
        ");
        $stmt->execute([$wasteId]);
        $wasteRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($wasteRecord) {
            // Restore inventory quantity
            $stmt = $pdo->prepare("
                UPDATE inventory_items 
                SET current_quantity = current_quantity + ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$wasteRecord['quantity'], $wasteRecord['item_id']]);
            
            // Delete waste record
            $stmt = $pdo->prepare("DELETE FROM waste_management WHERE id = ?");
            $stmt->execute([$wasteId]);
            
            // Commit transaction
            $pdo->commit();
            
            $successMsg = "Waste record deleted successfully and inventory restored.";
        } else {
            // Rollback transaction
            $pdo->rollBack();
            $errorMsg = "Waste record not found.";
        }
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error deleting waste record: " . $e->getMessage());
        $errorMsg = "Failed to delete waste record. Please try again later.";
    }
}

// Process search query
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$wasteTypeFilter = isset($_GET['waste_type']) ? $_GET['waste_type'] : '';

// Prepare base query
$query = "SELECT w.id, w.item_id, w.quantity, w.waste_type, w.reason, w.date, w.notes, 
          i.item_name, i.sku, i.unit_of_measure, c.name as category_name
          FROM waste_management w
          LEFT JOIN inventory_items i ON w.item_id = i.id
          LEFT JOIN item_categories c ON i.category_id = c.id
          WHERE 1=1";

// Add search filter if provided
$params = [];
if (!empty($searchTerm)) {
    $query .= " AND (i.item_name LIKE ? OR w.reason LIKE ? OR w.notes LIKE ?)";
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

// Add date filter if provided
if (!empty($dateFilter)) {
    switch ($dateFilter) {
        case 'today':
            $query .= " AND DATE(w.date) = CURDATE()";
            break;
        case 'yesterday':
            $query .= " AND DATE(w.date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $query .= " AND YEARWEEK(w.date, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'this_month':
            $query .= " AND MONTH(w.date) = MONTH(CURDATE()) AND YEAR(w.date) = YEAR(CURDATE())";
            break;
        case 'this_year':
            $query .= " AND YEAR(w.date) = YEAR(CURDATE())";
            break;
    }
}

// Add waste type filter if provided
if (!empty($wasteTypeFilter)) {
    $query .= " AND w.waste_type = ?";
    $params[] = $wasteTypeFilter;
}

// Add sorting
$query .= " ORDER BY w.date DESC, i.item_name ASC";

// Fetch the waste records
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $wasteRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching waste records: " . $e->getMessage());
    $errorMsg = "Failed to load waste records. Please try again later.";
}

$pageTitle = 'Waste Management';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-trash-alt"></i> Waste Management</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='inventory.php'">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </button>
            <button class="btn btn-primary" data-toggle="modal" data-target="#addWasteModal">
                <i class="fas fa-plus"></i> Record New Waste
            </button>
            <button class="btn btn-secondary" onclick="exportWasteToCSV()">
                <i class="fas fa-file-export"></i> Export CSV
            </button>
        </div>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $errorMsg; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $successMsg; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Search and Filter Section -->
    <div class="search-filter-container">
        <form method="GET" action="" class="search-filter-form" id="filterForm">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search waste records..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="form-group col-md-2">
                    <select name="category" class="form-control" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($categoryFilter == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <select name="date_filter" class="form-control" onchange="this.form.submit()">
                        <option value="">All Dates</option>
                        <option value="today" <?php echo ($dateFilter == 'today') ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo ($dateFilter == 'yesterday') ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="this_week" <?php echo ($dateFilter == 'this_week') ? 'selected' : ''; ?>>This Week</option>
                        <option value="this_month" <?php echo ($dateFilter == 'this_month') ? 'selected' : ''; ?>>This Month</option>
                        <option value="this_year" <?php echo ($dateFilter == 'this_year') ? 'selected' : ''; ?>>This Year</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <select name="waste_type" class="form-control" onchange="this.form.submit()">
                        <option value="">All Waste Types</option>
                        <option value="expired" <?php echo ($wasteTypeFilter == 'expired') ? 'selected' : ''; ?>>Expired</option>
                        <option value="damaged" <?php echo ($wasteTypeFilter == 'damaged') ? 'selected' : ''; ?>>Damaged</option>
                        <option value="spoiled" <?php echo ($wasteTypeFilter == 'spoiled') ? 'selected' : ''; ?>>Spoiled</option>
                        <option value="lost" <?php echo ($wasteTypeFilter == 'lost') ? 'selected' : ''; ?>>Lost</option>
                        <option value="other" <?php echo ($wasteTypeFilter == 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="window.location.href='waste_management.php'">
                        <i class="fas fa-sync-alt"></i> Reset Filters
                    </button>
                </div>
            </div>
            <!-- Hidden field for export -->
            <input type="hidden" name="export" id="exportField" value="">
        </form>
    </div>

    <!-- Waste Records Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Waste Type</th>
                    <th>Reason</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($wasteRecords) > 0): ?>
                    <?php foreach ($wasteRecords as $waste): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($waste['date']); ?></td>
                            <td><?php echo htmlspecialchars($waste['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($waste['category_name']); ?></td>
                            <td><?php echo $waste['quantity'] . ' ' . htmlspecialchars($waste['unit_of_measure']); ?></td>
                            <td>
                                <?php 
                                    $badgeClass = '';
                                    switch ($waste['waste_type']) {
                                        case 'expired':
                                            $badgeClass = 'badge-warning';
                                            break;
                                        case 'damaged':
                                            $badgeClass = 'badge-danger';
                                            break;
                                        case 'spoiled':
                                            $badgeClass = 'badge-info';
                                            break;
                                        case 'lost':
                                            $badgeClass = 'badge-secondary';
                                            break;
                                        default:
                                            $badgeClass = 'badge-dark';
                                    }
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>">
                                    <?php echo ucfirst(htmlspecialchars($waste['waste_type'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($waste['reason']); ?></td>
                            <td><?php echo htmlspecialchars($waste['notes']); ?></td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-primary view-waste" 
                                        data-toggle="modal" 
                                        data-target="#viewWasteModal"
                                        data-id="<?php echo $waste['id']; ?>"
                                        data-item="<?php echo htmlspecialchars($waste['item_name']); ?>"
                                        data-quantity="<?php echo $waste['quantity']; ?>"
                                        data-unit="<?php echo htmlspecialchars($waste['unit_of_measure']); ?>"
                                        data-type="<?php echo htmlspecialchars($waste['waste_type']); ?>"
                                        data-reason="<?php echo htmlspecialchars($waste['reason']); ?>"
                                        data-date="<?php echo htmlspecialchars($waste['date']); ?>"
                                        data-notes="<?php echo htmlspecialchars($waste['notes']); ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="waste_management.php?action=delete&id=<?php echo $waste['id']; ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Are you sure you want to delete this waste record? This will restore the quantity to inventory.')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">No waste records found. <?php echo !empty($searchTerm) || !empty($categoryFilter) || !empty($dateFilter) || !empty($wasteTypeFilter) ? 'Try different search criteria.' : ''; ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Waste Summary -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Waste Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Total Waste Records</h6>
                        <p class="stats-number"><?php echo count($wasteRecords); ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Expired Items</h6>
                        <p class="stats-number text-warning">
                            <?php 
                                $expiredCount = 0;
                                foreach ($wasteRecords as $waste) {
                                    if ($waste['waste_type'] === 'expired') {
                                        $expiredCount++;
                                    }
                                }
                                echo $expiredCount;
                            ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Damaged Items</h6>
                        <p class="stats-number text-danger">
                            <?php 
                                $damagedCount = 0;
                                foreach ($wasteRecords as $waste) {
                                    if ($waste['waste_type'] === 'damaged') {
                                        $damagedCount++;
                                    }
                                }
                                echo $damagedCount;
                            ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Lost Items</h6>
                        <p class="stats-number text-secondary">
                            <?php 
                                $lostCount = 0;
                                foreach ($wasteRecords as $waste) {
                                    if ($waste['waste_type'] === 'lost') {
                                        $lostCount++;
                                    }
                                }
                                echo $lostCount;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Waste Modal -->
<div class="modal fade" id="addWasteModal" tabindex="-1" role="dialog" aria-labelledby="addWasteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addWasteModalLabel">Record New Waste</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="item_id">Inventory Item <span class="text-danger">*</span></label>
                            <select class="form-control" id="item_id" name="item_id" required>
                                <option value="">Select Item</option>
                                <?php foreach ($items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="quantity">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" step="0.01" min="0.01" required>
                            <small class="form-text text-muted">Current stock: <span id="current_stock">N/A</span></small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="waste_type">Waste Type <span class="text-danger">*</span></label>
                            <select class="form-control" id="waste_type" name="waste_type" required>
                                <option value="">Select Type</option>
                                <option value="expired">Expired</option>
                                <option value="damaged">Damaged</option>
                                <option value="spoiled">Spoiled</option>
                                <option value="lost">Lost</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="date">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="reason">Reason <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="reason" name="reason" required>
                    </div>
                    <div class="form-group">
                        <label for="notes">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_waste" class="btn btn-primary">Record Waste</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Waste Modal -->
<div class="modal fade" id="viewWasteModal" tabindex="-1" role="dialog" aria-labelledby="viewWasteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewWasteModalLabel">Waste Record Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="waste-details">
                    <p><strong>Item:</strong> <span id="view_item"></span></p>
                    <p><strong>Quantity:</strong> <span id="view_quantity"></span> <span id="view_unit"></span></p>
                    <p><strong>Waste Type:</strong> <span id="view_type"></span></p>
                    <p><strong>Date:</strong> <span id="view_date"></span></p>
                    <p><strong>Reason:</strong> <span id="view_reason"></span></p>
                    <p><strong>Notes:</strong> <span id="view_notes"></span></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Handle item selection for checking current stock
        $('#item_id').change(function() {
            const itemId = $(this).val();
            if (itemId) {
                // Fetch current stock via AJAX
                $.ajax({
                    url: 'api/get_item_stock.php',
                    type: 'GET',
                    data: { item_id: itemId },
                    dataType: 'json',
                    success: function(response) {
                        $('#current_stock').text(response.current_quantity + ' ' + response.unit_of_measure);
                    },
                    error: function() {
                        $('#current_stock').text('Error loading stock');
                    }
                });
            } else {
                $('#current_stock').text('N/A');
            }
        });

        // Handle waste record view modal
        $('.view-waste').click(function() {
            const id = $(this).data('id');
            const item = $(this).data('item');
            const quantity = $(this).data('quantity');
            const unit = $(this).data('unit');
            const type = $(this).data('type');
            const reason = $(this).data('reason');
            const date = $(this).data('date');
            const notes = $(this).data('notes');
            
            $('#view_item').text(item);
            $('#view_quantity').text(quantity);
            $('#view_unit').text(unit);
            $('#view_type').text(type.charAt(0).toUpperCase() + type.slice(1));
            $('#view_date').text(date);
            $('#view_reason').text(reason);
            $('#view_notes').text(notes || 'None');
        });
    });

    function exportWasteToCSV() {
        // Set the export field value
        document.getElementById('exportField').value = 'csv';
        // Submit the form to trigger the export
        document.getElementById('filterForm').submit();
    }
</script>

<style>
    .search-filter-container {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .stats-item {
        border-left: 3px solid #007bff;
        padding-left: 15px;
        margin-bottom: 10px;
    }

    .stats-number {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 0;
    }

    .badge {
        font-size: 90%;
    }
</style>

<?php include 'includes/footer.php'; ?>