<?php
require_once 'includes/auth.php';
auth()->checkSupervisor(); // Only allow supervisor access

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$items = [];
$categories = [];
$recentUsage = [];
$stockRequests = [];

// Debug: Check database connection
if (!isset($pdo)) {
    $errorMsg = "Database connection not available.";
    error_log("PDO connection not found in supervisor_inventory.php");
}

// Fetch all active categories for dropdown
try {
    // First check if item_categories table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'item_categories'");
    $categoryTableExists = $stmt->rowCount() > 0;
    
    if ($categoryTableExists) {
        $stmt = $pdo->query("SELECT id, name FROM item_categories WHERE status = 'active' ORDER BY name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        error_log("item_categories table does not exist");
    }
} catch(PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $errorMsg = "Failed to load categories: " . $e->getMessage();
}

// Handle Stock Request Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $requestedQuantity = isset($_POST['requested_quantity']) ? (float)$_POST['requested_quantity'] : 0;
    $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';
    $priority = isset($_POST['priority']) ? $_POST['priority'] : 'medium';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Basic validation
    if ($itemId <= 0) {
        $errorMsg = "Please select a valid item.";
    } elseif ($requestedQuantity <= 0) {
        $errorMsg = "Requested quantity must be greater than zero.";
    } elseif (empty($purpose)) {
        $errorMsg = "Please specify the purpose for this request.";
    } else {
        try {
            // Check if stock_requests table exists, if not create it
            $stmt = $pdo->query("SHOW TABLES LIKE 'stock_requests'");
            $tableExists = $stmt->rowCount() > 0;
            
            if (!$tableExists) {
                $pdo->exec("
                    CREATE TABLE `stock_requests` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `item_id` int(11) NOT NULL,
                        `requested_by` int(11) NOT NULL,
                        `requested_quantity` decimal(10,2) NOT NULL,
                        `purpose` varchar(255) NOT NULL,
                        `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
                        `status` enum('pending','approved','rejected','fulfilled') DEFAULT 'pending',
                        `notes` text DEFAULT NULL,
                        `admin_notes` text DEFAULT NULL,
                        `requested_date` datetime NOT NULL,
                        `approved_date` datetime DEFAULT NULL,
                        `approved_by` int(11) DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `item_id` (`item_id`),
                        KEY `requested_by` (`requested_by`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                ");
            }
            
            // Insert stock request
            $stmt = $pdo->prepare("
                INSERT INTO stock_requests (
                    item_id, requested_by, requested_quantity, purpose, priority, notes, requested_date
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $itemId, 
                $_SESSION['user_id'], 
                $requestedQuantity, 
                $purpose, 
                $priority, 
                $notes
            ]);
            
            $successMsg = "Stock request submitted successfully. Admin will review your request.";
        } catch (Exception $e) {
            error_log("Error submitting stock request: " . $e->getMessage());
            $errorMsg = "Failed to submit stock request: " . $e->getMessage();
        }
    }
}

// Handle Usage Recording - UPDATED TO REDUCE STOCK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_usage'])) {
    $itemId = isset($_POST['usage_item_id']) ? (int)$_POST['usage_item_id'] : 0;
    $quantity = isset($_POST['usage_quantity']) ? (float)$_POST['usage_quantity'] : 0;
    $usageDate = isset($_POST['usage_date']) ? $_POST['usage_date'] : date('Y-m-d');
    $purpose = isset($_POST['usage_purpose']) ? trim($_POST['usage_purpose']) : '';
    $assignedTo = isset($_POST['assigned_to']) ? trim($_POST['assigned_to']) : '';
    $notes = isset($_POST['usage_notes']) ? trim($_POST['usage_notes']) : '';
    
    // Basic validation
    if ($itemId <= 0) {
        $errorMsg = "Please select a valid item for usage recording.";
    } elseif ($quantity <= 0) {
        $errorMsg = "Usage quantity must be greater than zero.";
    } else {
        try {
            // Start transaction for data integrity
            $pdo->beginTransaction();
            
            // Check if inventory_usage table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'inventory_usage'");
            $tableExists = $stmt->rowCount() > 0;
            
            if (!$tableExists) {
                // Create inventory_usage table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE `inventory_usage` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `item_id` int(11) NOT NULL,
                        `quantity` decimal(10,2) NOT NULL,
                        `usage_date` date NOT NULL,
                        `purpose` varchar(255) DEFAULT NULL,
                        `assigned_to` varchar(100) DEFAULT NULL,
                        `notes` text DEFAULT NULL,
                        `created_by` int(11) NOT NULL,
                        `created_at` datetime NOT NULL,
                        PRIMARY KEY (`id`),
                        KEY `item_id` (`item_id`),
                        KEY `created_by` (`created_by`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                ");
            }
            
            // Get current item details
            $stmt = $pdo->prepare("SELECT item_name, current_quantity, unit_of_measure FROM inventory_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                throw new Exception("Item not found");
            }
            
            // Check if there's enough quantity
            if ($item['current_quantity'] < $quantity) {
                $errorMsg = "Not enough stock available. Current stock: {$item['current_quantity']} {$item['unit_of_measure']}.";
                $pdo->rollBack();
            } else {
                // Calculate new quantity after usage
                $newQuantity = $item['current_quantity'] - $quantity;
                
                // Update inventory item - reduce current stock
                $updateStmt = $pdo->prepare("
                    UPDATE inventory_items 
                    SET current_quantity = ?, 
                        last_updated = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$newQuantity, $itemId]);
                
                // Add usage record
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_usage (
                        item_id, quantity, usage_date, purpose, assigned_to, notes, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $itemId, 
                    $quantity, 
                    $usageDate, 
                    $purpose, 
                    $assignedTo, 
                    $notes, 
                    $_SESSION['user_id']
                ]);
                
                // Commit the transaction
                $pdo->commit();
                
                $successMsg = "Usage recorded successfully! Stock reduced from {$item['current_quantity']} to {$newQuantity} {$item['unit_of_measure']}.";
                
                // Check if stock is now below reorder level and create alert
                $stmt = $pdo->prepare("SELECT reorder_level FROM inventory_items WHERE id = ?");
                $stmt->execute([$itemId]);
                $reorderLevel = $stmt->fetch(PDO::FETCH_COLUMN);
                
                if ($newQuantity <= $reorderLevel) {
                    $successMsg .= " <strong>Warning:</strong> Stock is now at or below reorder level ({$reorderLevel} {$item['unit_of_measure']}).";
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error recording usage: " . $e->getMessage());
            $errorMsg = "Failed to record usage: " . $e->getMessage();
        }
    }
}

// Process search query
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$stockFilter = isset($_GET['stock_filter']) ? $_GET['stock_filter'] : '';

// Check if inventory_items table exists first
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'inventory_items'");
    $inventoryTableExists = $stmt->rowCount() > 0;
    
    if (!$inventoryTableExists) {
        $errorMsg = "Inventory system is not set up. Please contact the administrator.";
        error_log("inventory_items table does not exist");
    } else {
        // Check if last_updated column exists, if not add it
        $stmt = $pdo->query("SHOW COLUMNS FROM inventory_items LIKE 'last_updated'");
        $lastUpdatedExists = $stmt->rowCount() > 0;
        
        if (!$lastUpdatedExists) {
            try {
                $pdo->exec("ALTER TABLE inventory_items ADD COLUMN last_updated DATETIME DEFAULT NULL");
                error_log("Added last_updated column to inventory_items table");
            } catch (Exception $e) {
                error_log("Could not add last_updated column: " . $e->getMessage());
            }
        }
        
        // Prepare base query for viewing items
        $query = "SELECT i.id, i.item_name as name, i.sku, i.description, i.category_id, 
                  COALESCE(c.name, 'Uncategorized') as category_name, 
                  i.current_quantity, i.unit_of_measure, i.reorder_level, i.maximum_level,
                  i.unit_cost, i.expiry_date, i.batch_number, i.supplier_id, 
                  COALESCE(s.name, 'N/A') as supplier_name,
                  i.last_updated
                  FROM inventory_items i
                  LEFT JOIN item_categories c ON i.category_id = c.id
                  LEFT JOIN suppliers s ON i.supplier_id = s.id
                  WHERE 1=1";
        
        // Check if status column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM inventory_items LIKE 'status'");
        $statusExists = $stmt->rowCount() > 0;
        
        if ($statusExists) {
            $query .= " AND (i.status = 'active' OR i.status IS NULL)";
        }

        // Add search filter if provided
        $params = [];
        if (!empty($searchTerm)) {
            $query .= " AND (i.item_name LIKE ? OR i.sku LIKE ? OR COALESCE(i.description, '') LIKE ?)";
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

        // Add stock level filter
        if (!empty($stockFilter)) {
            if ($stockFilter === 'low') {
                $query .= " AND i.current_quantity <= COALESCE(i.reorder_level, 0)";
            } elseif ($stockFilter === 'out') {
                $query .= " AND i.current_quantity = 0";
            } elseif ($stockFilter === 'expiring') {
                $query .= " AND i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)";
            }
        }

        // Add sorting
        $query .= " ORDER BY i.item_name ASC";

        // Fetch the items
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Log the number of items found
        error_log("Found " . count($items) . " inventory items");
    }
} catch(PDOException $e) {
    error_log("Error fetching inventory items: " . $e->getMessage());
    $errorMsg = "Database error: " . $e->getMessage();
}

// Fetch supervisor's recent usage records
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'inventory_usage'");
    $usageTableExists = $stmt->rowCount() > 0;
    
    if ($usageTableExists) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.quantity, u.usage_date, u.purpose, u.assigned_to, u.created_at,
                   i.item_name, i.unit_of_measure
            FROM inventory_usage u
            JOIN inventory_items i ON u.item_id = i.id
            WHERE u.created_by = ?
            ORDER BY u.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $recentUsage = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    error_log("Error fetching recent usage: " . $e->getMessage());
}

// Fetch supervisor's stock requests
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'stock_requests'");
    $requestTableExists = $stmt->rowCount() > 0;
    
    if ($requestTableExists) {
        $stmt = $pdo->prepare("
            SELECT sr.id, sr.requested_quantity, sr.purpose, sr.priority, sr.status, 
                   sr.requested_date, sr.admin_notes, i.item_name, i.unit_of_measure
            FROM stock_requests sr
            JOIN inventory_items i ON sr.item_id = i.id
            WHERE sr.requested_by = ?
            ORDER BY sr.requested_date DESC
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $stockRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    error_log("Error fetching stock requests: " . $e->getMessage());
}

$pageTitle = 'Supervisor Inventory';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-boxes"></i> Inventory Management</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" data-toggle="modal" data-target="#requestStockModal">
                <i class="fas fa-plus"></i> Request Stock
            </button>
            <button class="btn btn-success" data-toggle="modal" data-target="#recordUsageModal">
                <i class="fas fa-clipboard-list"></i> Record Usage
            </button>
            <button class="btn btn-info" onclick="showMyRequests()">
                <i class="fas fa-history"></i> My Requests
            </button>
        </div>
    </div>

    <!-- Debug Information (remove in production) -->
    <?php if (isset($_GET['debug'])): ?>
        <div class="alert alert-info">
            <strong>Debug Info:</strong><br>
            Database Connected: <?php echo isset($pdo) ? 'Yes' : 'No'; ?><br>
            Items Found: <?php echo count($items); ?><br>
            Categories Found: <?php echo count($categories); ?><br>
            Search Term: <?php echo htmlspecialchars($searchTerm); ?><br>
            Category Filter: <?php echo htmlspecialchars($categoryFilter); ?><br>
            Stock Filter: <?php echo htmlspecialchars($stockFilter); ?>
        </div>
    <?php endif; ?>

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
        <form method="GET" action="" class="search-filter-form">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="form-group col-md-3">
                    <select name="category" class="form-control" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($categoryFilter == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <select name="stock_filter" class="form-control" onchange="this.form.submit()">
                        <option value="">All Stock Levels</option>
                        <option value="low" <?php echo ($stockFilter == 'low') ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="out" <?php echo ($stockFilter == 'out') ? 'selected' : ''; ?>>Out of Stock</option>
                        <option value="expiring" <?php echo ($stockFilter == 'expiring') ? 'selected' : ''; ?>>Expiring Soon</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="window.location.href='supervisor_inventory.php'">
                        <i class="fas fa-sync-alt"></i> Reset
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5>Total Items</h5>
                            <h3><?php echo count($items); ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-boxes fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5>Low Stock Items</h5>
                            <h3>
                                <?php 
                                    $lowStockCount = 0;
                                    foreach ($items as $item) {
                                        if ($item['current_quantity'] <= ($item['reorder_level'] ?? 0)) {
                                            $lowStockCount++;
                                        }
                                    }
                                    echo $lowStockCount;
                                ?>
                            </h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5>My Requests</h5>
                            <h3><?php echo count($stockRequests); ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clipboard-list fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5>Usage Records</h5>
                            <h3><?php echo count($recentUsage); ?></h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card">
        <div class="card-header">
            <h3>Inventory Items</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th>Name</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Unit</th>
                            <th>Reorder Level</th>
                            <th>Supplier</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($items) > 0): ?>
                            <?php foreach ($items as $item): ?>
                                <tr <?php echo ($item['current_quantity'] <= ($item['reorder_level'] ?? 0)) ? 'class="table-warning"' : ''; ?>>
                                    <td><?php echo htmlspecialchars($item['name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($item['sku'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>
                                        <span class="<?php echo ($item['current_quantity'] <= ($item['reorder_level'] ?? 0)) ? 'text-danger font-weight-bold' : ''; ?>">
                                            <?php echo $item['current_quantity'] ?? 0; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['unit_of_measure'] ?? 'units'); ?></td>
                                    <td><?php echo $item['reorder_level'] ?? 0; ?></td>
                                    <td><?php echo htmlspecialchars($item['supplier_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                            if (!empty($item['expiry_date'])) {
                                                $expiryDate = new DateTime($item['expiry_date']);
                                                $today = new DateTime();
                                                $interval = $today->diff($expiryDate);
                                                $daysRemaining = $expiryDate > $today ? $interval->days : -$interval->days;
                                                
                                                $expiryClass = '';
                                                if ($daysRemaining < 0) {
                                                    $expiryClass = 'text-danger';
                                                } elseif ($daysRemaining <= 30) {
                                                    $expiryClass = 'text-warning';
                                                }
                                                
                                                echo '<span class="'.$expiryClass.'">' . htmlspecialchars($item['expiry_date']) . '</span>';
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (($item['current_quantity'] ?? 0) == 0): ?>
                                            <span class="badge badge-danger">Out of Stock</span>
                                        <?php elseif (($item['current_quantity'] ?? 0) <= ($item['reorder_level'] ?? 0)): ?>
                                            <span class="badge badge-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            if (!empty($item['last_updated'])) {
                                                echo '<small class="text-muted">' . date('M d, Y H:i', strtotime($item['last_updated'])) . '</small>';
                                            } else {
                                                echo '<small class="text-muted">N/A</small>';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-info" title="View Details" 
                                                    onclick="viewItemDetails(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['name'] ?? 'Unknown')); ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-primary" title="Request Stock"
                                                    onclick="requestItem(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['name'] ?? 'Unknown')); ?>', '<?php echo $item['unit_of_measure'] ?? 'units'; ?>')">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <button class="btn btn-sm btn-success" title="Record Usage"
                                                    onclick="recordUsage(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['name'] ?? 'Unknown')); ?>', <?php echo $item['current_quantity'] ?? 0; ?>, '<?php echo $item['unit_of_measure'] ?? 'units'; ?>')"
                                                    <?php echo (($item['current_quantity'] ?? 0) <= 0) ? 'disabled' : ''; ?>>
                                                <i class="fas fa-minus"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">
                                    <?php if (!empty($errorMsg)): ?>
                                        Unable to load inventory items.
                                    <?php else: ?>
                                        No items found. <?php echo !empty($searchTerm) || !empty($categoryFilter) ? 'Try a different search term or filter.' : 'Please contact the administrator to set up inventory items.'; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Recent Usage Records</h5>
                </div>
                <div class="card-body">
                    <?php if (count($recentUsage) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Purpose</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsage as $usage): ?>
                                        <tr>
                                            <td><?php echo date('M d', strtotime($usage['usage_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($usage['item_name']); ?></td>
                                            <td><?php echo $usage['quantity'] . ' ' . $usage['unit_of_measure']; ?></td>
                                            <td><?php echo htmlspecialchars($usage['purpose']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No recent usage records found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">My Stock Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (count($stockRequests) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stockRequests as $request): ?>
                                        <tr>
                                            <td><?php echo date('M d', strtotime($request['requested_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                                            <td><?php echo $request['requested_quantity'] . ' ' . $request['unit_of_measure']; ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                        switch($request['status']) {
                                                            case 'pending': echo 'badge-warning'; break;
                                                            case 'approved': echo 'badge-info'; break;
                                                            case 'fulfilled': echo 'badge-success'; break;
                                                            case 'rejected': echo 'badge-danger'; break;
                                                            default: echo 'badge-secondary';
                                                        }
                                                    ?>">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No stock requests found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Request Stock Modal -->
<div class="modal fade" id="requestStockModal" tabindex="-1" role="dialog" aria-labelledby="requestStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="requestStockModalLabel">Request Stock</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="item_id">Item <span class="text-danger">*</span></label>
                                <select class="form-control" id="item_id" name="item_id" required>
                                    <option value="">Select Item</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" data-unit="<?php echo htmlspecialchars($item['unit_of_measure'] ?? 'units'); ?>">
                                            <?php echo htmlspecialchars($item['name'] ?? 'Unknown'); ?> 
                                            (Current: <?php echo ($item['current_quantity'] ?? 0) . ' ' . ($item['unit_of_measure'] ?? 'units'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="requested_quantity">Requested Quantity <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="requested_quantity" name="requested_quantity" step="0.01" min="0.01" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text" id="request-unit">Unit</span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="priority">Priority</label>
                                <select class="form-control" id="priority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="purpose">Purpose <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="purpose" name="purpose" placeholder="Purpose for requesting this item" required>
                            </div>
                            <div class="form-group">
                                <label for="notes">Additional Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Additional details or special requirements"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_request" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Record Usage Modal -->
<div class="modal fade" id="recordUsageModal" tabindex="-1" role="dialog" aria-labelledby="recordUsageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="recordUsageModalLabel">Record Usage</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> Recording usage will automatically reduce the current stock quantity.
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="usage_item_id">Item <span class="text-danger">*</span></label>
                                <select class="form-control" id="usage_item_id" name="usage_item_id" required>
                                    <option value="">Select Item</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" 
                                                data-stock="<?php echo $item['current_quantity'] ?? 0; ?>" 
                                                data-unit="<?php echo htmlspecialchars($item['unit_of_measure'] ?? 'units'); ?>"
                                                <?php echo (($item['current_quantity'] ?? 0) <= 0) ? 'disabled' : ''; ?>>
                                            <?php echo htmlspecialchars($item['name'] ?? 'Unknown'); ?> 
                                            (Available: <?php echo ($item['current_quantity'] ?? 0) . ' ' . ($item['unit_of_measure'] ?? 'units'); ?>)
                                            <?php echo (($item['current_quantity'] ?? 0) <= 0) ? ' - OUT OF STOCK' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="usage_quantity">Quantity Used <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="usage_quantity" name="usage_quantity" step="0.01" min="0.01" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text" id="usage-unit">Unit</span>
                                    </div>
                                </div>
                                <small id="usage-stock-info" class="form-text text-muted">Available: <span id="available-usage-stock">0</span></small>
                                <div id="usage-warning" class="alert alert-warning mt-2" style="display: none;">
                                    <i class="fas fa-exclamation-triangle"></i> Quantity exceeds available stock!
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="usage_date">Usage Date</label>
                                <input type="date" class="form-control" id="usage_date" name="usage_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="usage_purpose">Purpose</label>
                                <input type="text" class="form-control" id="usage_purpose" name="usage_purpose" placeholder="Purpose of usage">
                            </div>
                            <div class="form-group">
                                <label for="assigned_to">Assigned To</label>
                                <input type="text" class="form-control" id="assigned_to" name="assigned_to" placeholder="Person or department">
                            </div>
                            <div class="form-group">
                                <label for="usage_notes">Notes</label>
                                <textarea class="form-control" id="usage_notes" name="usage_notes" rows="3" placeholder="Additional notes"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="record_usage" class="btn btn-success" id="submit-usage-btn">Record Usage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Item Details Modal -->
<div class="modal fade" id="itemDetailsModal" tabindex="-1" role="dialog" aria-labelledby="itemDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="itemDetailsModalLabel">Item Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="itemDetailsContent">
                <!-- Item details will be loaded here via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- My Requests Modal -->
<div class="modal fade" id="myRequestsModal" tabindex="-1" role="dialog" aria-labelledby="myRequestsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="myRequestsModalLabel">My Stock Requests</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Purpose</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Admin Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stockRequests as $request): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($request['requested_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                                    <td><?php echo $request['requested_quantity'] . ' ' . $request['unit_of_measure']; ?></td>
                                    <td><?php echo htmlspecialchars($request['purpose']); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                                switch($request['priority']) {
                                                    case 'low': echo 'badge-secondary'; break;
                                                    case 'medium': echo 'badge-primary'; break;
                                                    case 'high': echo 'badge-warning'; break;
                                                    case 'urgent': echo 'badge-danger'; break;
                                                    default: echo 'badge-secondary';
                                                }
                                            ?>">
                                            <?php echo ucfirst($request['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                                switch($request['status']) {
                                                    case 'pending': echo 'badge-warning'; break;
                                                    case 'approved': echo 'badge-info'; break;
                                                    case 'fulfilled': echo 'badge-success'; break;
                                                    case 'rejected': echo 'badge-danger'; break;
                                                    default: echo 'badge-secondary';
                                                }
                                            ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['admin_notes'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
        // Update unit display when item is selected in request modal
        $('#item_id').change(function() {
            var selectedOption = $(this).find('option:selected');
            var unit = selectedOption.data('unit');
            $('#request-unit').text(unit || 'Unit');
        });
        
        // Update unit display and stock info when item is selected in usage modal
        $('#usage_item_id').change(function() {
            var selectedOption = $(this).find('option:selected');
            var stock = selectedOption.data('stock');
            var unit = selectedOption.data('unit');
            
            $('#available-usage-stock').text(stock + ' ' + unit);
            $('#usage-unit').text(unit || 'Unit');
            
            // Reset quantity field and validation
            $('#usage_quantity').val('').removeClass('is-invalid');
            $('#usage-warning').hide();
            $('#submit-usage-btn').prop('disabled', false);
        });
        
        // Validate quantity doesn't exceed available stock
        $('#usage_quantity').on('input', function() {
            var selectedOption = $('#usage_item_id').find('option:selected');
            var availableStock = parseFloat(selectedOption.data('stock')) || 0;
            var enteredQuantity = parseFloat($(this).val()) || 0;
            
            if (enteredQuantity > availableStock) {
                $(this).addClass('is-invalid');
                $('#usage-stock-info').addClass('text-danger').removeClass('text-muted');
                $('#usage-warning').show();
                $('#submit-usage-btn').prop('disabled', true);
            } else {
                $(this).removeClass('is-invalid');
                $('#usage-stock-info').removeClass('text-danger').addClass('text-muted');
                $('#usage-warning').hide();
                $('#submit-usage-btn').prop('disabled', false);
            }
        });
        
        // Auto-refresh page every 5 minutes to get updated stock levels
        setInterval(function() {
            // Only refresh if no modals are open
            if (!$('.modal').hasClass('show')) {
                window.location.reload();
            }
        }, 300000); // 5 minutes
    });
    
    function requestItem(itemId, itemName, unit) {
        $('#item_id').val(itemId);
        $('#request-unit').text(unit);
        $('#requestStockModal').modal('show');
    }
    
    function recordUsage(itemId, itemName, currentStock, unit) {
        if (currentStock <= 0) {
            alert('Cannot record usage for out-of-stock items.');
            return;
        }
        
        $('#usage_item_id').val(itemId);
        $('#available-usage-stock').text(currentStock + ' ' + unit);
        $('#usage-unit').text(unit);
        $('#usage_quantity').val('').removeClass('is-invalid');
        $('#usage-warning').hide();
        $('#submit-usage-btn').prop('disabled', false);
        $('#recordUsageModal').modal('show');
    }
    
    function viewItemDetails(itemId, itemName) {
        $('#itemDetailsModalLabel').text('Item Details: ' + itemName);
        $('#itemDetailsContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading item details...</p></div>');
        $('#itemDetailsModal').modal('show');
        
        // AJAX request to get item details
        $.ajax({
            url: 'get_item_details.php',
            type: 'GET',
            data: {item_id: itemId},
            success: function(response) {
                $('#itemDetailsContent').html(response);
            },
            error: function(xhr, status, error) {
                $('#itemDetailsContent').html('<div class="alert alert-danger">Failed to load item details. Error: ' + error + '</div>');
            }
        });
    }
    
    function showMyRequests() {
        $('#myRequestsModal').modal('show');
    }
</script>

<style>
    .search-filter-container {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border: 1px solid rgba(0, 0, 0, 0.125);
    }

    .table th {
        white-space: nowrap;
    }

    /* Highlight for low stock items */
    .table-warning {
        background-color: rgba(255, 193, 7, 0.2);
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
    }

    /* Card hover effects */
    .card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        transition: box-shadow 0.15s ease-in-out;
    }

    /* Badge styles */
    .badge {
        font-size: 0.75em;
        padding: 0.25em 0.5em;
    }

    /* Stats cards */
    .card-body h5 {
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }

    .card-body h3 {
        font-size: 1.5rem;
        margin-bottom: 0;
        font-weight: bold;
    }

    /* Modal improvements */
    .modal-header.bg-primary,
    .modal-header.bg-success,
    .modal-header.bg-info {
        border-bottom: none;
    }

    .modal-header .close {
        color: white;
        opacity: 0.8;
    }

    .modal-header .close:hover {
        opacity: 1;
    }

    /* Form validation */
    .is-invalid {
        border-color: #dc3545;
    }

    .text-danger {
        color: #dc3545 !important;
    }

    /* Usage warning */
    #usage-warning {
        font-size: 0.9rem;
    }

    /* Disabled button */
    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Last updated styling */
    .table td small.text-muted {
        font-size: 0.75rem;
    }

    /* Stock status indicators */
    .text-danger.font-weight-bold {
        font-weight: 600 !important;
    }

    /* Out of stock item styling in dropdown */
    option:disabled {
        color: #6c757d;
        font-style: italic;
    }

    /* Debug info styling */
    .alert-info {
        border-left: 4px solid #17a2b8;
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .action-buttons {
            flex-direction: column;
            gap: 5px;
        }
        
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .page-header h2 {
            margin-bottom: 15px;
        }
        
        .table-responsive {
            font-size: 0.9rem;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>