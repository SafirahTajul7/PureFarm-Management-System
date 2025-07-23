<?php
require_once 'includes/auth.php';
auth()->checkAdmin(); // Only allow admin access

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$items = [];
$usageRecords = [];

// Check if the inventory_usage table exists, if not create it
try {
    $tableExists = false;
    $stmt = $pdo->query("SHOW TABLES LIKE 'inventory_usage'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        // Create the table
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
                KEY `created_by` (`created_by`),
                CONSTRAINT `inventory_usage_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
                CONSTRAINT `inventory_usage_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        $successMsg = "Usage tracking system has been initialized. You can now start recording inventory usage.";
    }
} catch(PDOException $e) {
    error_log("Error checking/creating inventory_usage table: " . $e->getMessage());
    $errorMsg = "Failed to initialize usage tracking system. Please contact the administrator.";
}

// Fetch all active inventory items for dropdown
try {
    $stmt = $pdo->query("SELECT id, item_name, current_quantity, unit_of_measure FROM inventory_items WHERE status = 'active' ORDER BY item_name ASC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching items: " . $e->getMessage());
    $errorMsg = "Failed to load inventory items. Please try again later.";
}

// Handle adding a new usage record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_usage'])) {
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (float)$_POST['quantity'] : 0;
    $usageDate = isset($_POST['usage_date']) ? $_POST['usage_date'] : date('Y-m-d');
    $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $assignedTo = isset($_POST['assigned_to']) ? trim($_POST['assigned_to']) : '';
    
    // Basic validation
    if ($itemId <= 0) {
        $errorMsg = "Please select a valid item.";
    } elseif ($quantity <= 0) {
        $errorMsg = "Quantity must be greater than zero.";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Get current item details
            $stmt = $pdo->prepare("SELECT current_quantity, unit_of_measure FROM inventory_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                throw new Exception("Item not found");
            }
            
            // Check if there's enough quantity
            if ($item['current_quantity'] < $quantity) {
                $errorMsg = "Not enough stock available. Current stock: {$item['current_quantity']} {$item['unit_of_measure']}.";
            } else {
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
                
                // Update inventory quantity
                $newQuantity = $item['current_quantity'] - $quantity;
                $stmt = $pdo->prepare("
                    UPDATE inventory_items 
                    SET current_quantity = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newQuantity, $itemId]);
                
                // Commit transaction
                $pdo->commit();
                $successMsg = "Usage record added successfully and inventory updated.";
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log("Error adding usage record: " . $e->getMessage());
            $errorMsg = "Failed to add usage record: " . $e->getMessage();
        }
    }
}

// Handle deletion of usage record
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $usageId = (int)$_GET['id'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get usage record details before deletion
        $stmt = $pdo->prepare("
            SELECT u.item_id, u.quantity, i.item_name 
            FROM inventory_usage u
            JOIN inventory_items i ON u.item_id = i.id
            WHERE u.id = ?
        ");
        $stmt->execute([$usageId]);
        $usageRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usageRecord) {
            throw new Exception("Usage record not found");
        }
        
        // Restore the item quantity (add back to inventory)
        $stmt = $pdo->prepare("
            UPDATE inventory_items 
            SET current_quantity = current_quantity + ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$usageRecord['quantity'], $usageRecord['item_id']]);
        
        // Delete the usage record
        $stmt = $pdo->prepare("DELETE FROM inventory_usage WHERE id = ?");
        $stmt->execute([$usageId]);
        
        // Commit transaction
        $pdo->commit();
        $successMsg = "Usage record for {$usageRecord['item_name']} has been deleted and inventory updated.";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error deleting usage record: " . $e->getMessage());
        $errorMsg = "Failed to delete usage record: " . $e->getMessage();
    }
}

// Process search/filter parameters
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$itemFilter = isset($_GET['item_filter']) ? (int)$_GET['item_filter'] : 0;

// Fetch usage records - only if inventory_usage table exists
$usageRecords = [];

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'inventory_usage'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        // Prepare base query - FIXED: Use username instead of first_name/last_name
        $query = "
            SELECT u.id, u.quantity, u.usage_date, u.purpose, u.assigned_to, u.notes, u.created_at,
                i.item_name, i.unit_of_measure,
                us.username as created_by_name
            FROM inventory_usage u
            JOIN inventory_items i ON u.item_id = i.id
            LEFT JOIN users us ON u.created_by = us.id
            WHERE u.usage_date BETWEEN ? AND ?
        ";

        // Add search and filter conditions
        $params = [$startDate, $endDate];

        if (!empty($searchTerm)) {
            $query .= " AND (i.item_name LIKE ? OR u.purpose LIKE ? OR u.assigned_to LIKE ? OR u.notes LIKE ?)";
            $searchParam = "%{$searchTerm}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        if ($itemFilter > 0) {
            $query .= " AND u.item_id = ?";
            $params[] = $itemFilter;
        }

        // Add order by
        $query .= " ORDER BY u.usage_date DESC, u.created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $usageRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching usage records: " . $e->getMessage());
    $errorMsg = "Failed to load usage records: " . $e->getMessage();
}

// Calculate summary statistics
$totalUsage = 0;
$itemUsage = [];

foreach ($usageRecords as $record) {
    $totalUsage += $record['quantity'];
    
    if (!isset($itemUsage[$record['item_name']])) {
        $itemUsage[$record['item_name']] = [
            'quantity' => 0,
            'unit' => $record['unit_of_measure']
        ];
    }
    
    $itemUsage[$record['item_name']]['quantity'] += $record['quantity'];
}

// Sort by most used
arsort($itemUsage);

$pageTitle = 'Usage Tracking';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-line"></i> Usage Tracking</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='inventory.php'">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </button>
            <button class="btn btn-primary" data-toggle="modal" data-target="#addUsageModal">
                <i class="fas fa-plus"></i> Record Usage
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
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Search & Filter</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row">
                <div class="col-md-3 form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Search items, purpose..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
                <div class="col-md-2 form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                </div>
                <div class="col-md-2 form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                </div>
                <div class="col-md-3 form-group">
                    <label for="item_filter">Item</label>
                    <select id="item_filter" name="item_filter" class="form-control">
                        <option value="0">All Items</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?php echo $item['id']; ?>" <?php echo ($itemFilter == $item['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['item_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 form-group d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="usage_tracking.php" class="btn btn-outline-secondary">
                        <i class="fas fa-sync-alt"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Usage Statistics -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Usage Summary</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Date Range</h6>
                        <p><?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?></p>
                    </div>
                    <div class="mb-3">
                        <h6>Total Records</h6>
                        <p><?php echo count($usageRecords); ?></p>
                    </div>
                    <div class="mb-3">
                        <h6>Most Used Items</h6>
                        <?php if (count($itemUsage) > 0): ?>
                            <ul class="list-group">
                                <?php 
                                $count = 0;
                                foreach ($itemUsage as $item => $data): 
                                    if ($count >= 5) break; // Show only top 5
                                    $count++;
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($item); ?>
                                        <span class="badge badge-primary badge-pill">
                                            <?php echo $data['quantity'] . ' ' . $data['unit']; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>No usage data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Records Table -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Usage Records</h5>
                </div>
                <div class="card-body">
                    <?php if (count($usageRecords) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Purpose</th>
                                        <th>Assigned To</th>
                                        <th>Added By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usageRecords as $record): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d', strtotime($record['usage_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($record['item_name']); ?></td>
                                            <td><?php echo $record['quantity'] . ' ' . $record['unit_of_measure']; ?></td>
                                            <td><?php echo htmlspecialchars($record['purpose']); ?></td>
                                            <td><?php echo htmlspecialchars($record['assigned_to']); ?></td>
                                            <td><?php echo htmlspecialchars($record['created_by_name']); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-info view-record" 
                                                        data-toggle="modal" data-target="#viewUsageModal"
                                                        data-id="<?php echo $record['id']; ?>"
                                                        data-item="<?php echo htmlspecialchars($record['item_name']); ?>"
                                                        data-quantity="<?php echo $record['quantity']; ?>"
                                                        data-unit="<?php echo htmlspecialchars($record['unit_of_measure']); ?>"
                                                        data-date="<?php echo $record['usage_date']; ?>"
                                                        data-purpose="<?php echo htmlspecialchars($record['purpose']); ?>"
                                                        data-assigned="<?php echo htmlspecialchars($record['assigned_to']); ?>"
                                                        data-notes="<?php echo htmlspecialchars($record['notes']); ?>"
                                                        data-created="<?php echo date('Y-m-d H:i', strtotime($record['created_at'])); ?>"
                                                        data-createdby="<?php echo htmlspecialchars($record['created_by_name']); ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="usage_tracking.php?action=delete&id=<?php echo $record['id']; ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Are you sure you want to delete this usage record? This will add the quantity back to inventory.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No usage records found for the selected criteria.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Usage Modal -->
<div class="modal fade" id="addUsageModal" tabindex="-1" role="dialog" aria-labelledby="addUsageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addUsageModalLabel">Record Usage</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="item_id">Item</label>
                                <select class="form-control" id="item_id" name="item_id" required>
                                    <option value="">Select Item</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" data-stock="<?php echo $item['current_quantity']; ?>" data-unit="<?php echo htmlspecialchars($item['unit_of_measure']); ?>">
                                            <?php echo htmlspecialchars($item['item_name']); ?> (<?php echo $item['current_quantity'] . ' ' . $item['unit_of_measure']; ?> available)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="quantity">Quantity</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="quantity" name="quantity" step="0.01" min="0.01" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text" id="quantity-unit">Unit</span>
                                    </div>
                                </div>
                                <small id="stock-info" class="form-text text-muted">Available stock: <span id="available-stock">0</span></small>
                            </div>
                            <div class="form-group">
                                <label for="usage_date">Usage Date</label>
                                <input type="date" class="form-control" id="usage_date" name="usage_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="purpose">Purpose</label>
                                <input type="text" class="form-control" id="purpose" name="purpose" placeholder="Purpose of usage">
                            </div>
                            <div class="form-group">
                                <label for="assigned_to">Assigned To</label>
                                <input type="text" class="form-control" id="assigned_to" name="assigned_to" placeholder="Person or department">
                            </div>
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Additional notes"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_usage" class="btn btn-primary">Record Usage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Usage Modal -->
<div class="modal fade" id="viewUsageModal" tabindex="-1" role="dialog" aria-labelledby="viewUsageModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewUsageModalLabel">Usage Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Item:</strong> <span id="view-item"></span></p>
                        <p><strong>Quantity:</strong> <span id="view-quantity"></span></p>
                        <p><strong>Usage Date:</strong> <span id="view-date"></span></p>
                        <p><strong>Purpose:</strong> <span id="view-purpose"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Assigned To:</strong> <span id="view-assigned"></span></p>
                        <p><strong>Created By:</strong> <span id="view-createdby"></span></p>
                        <p><strong>Created On:</strong> <span id="view-created"></span></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <p><strong>Notes:</strong></p>
                        <div class="p-2 bg-light rounded" id="view-notes"></div>
                    </div>
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
        // Update available stock and unit when item is selected
        $('#item_id').change(function() {
            var selectedOption = $(this).find('option:selected');
            var stock = selectedOption.data('stock');
            var unit = selectedOption.data('unit');
            
            $('#available-stock').text(stock + ' ' + unit);
            $('#quantity-unit').text(unit);
        });
        
        // Set up view modal
        $('.view-record').click(function() {
            $('#view-item').text($(this).data('item'));
            $('#view-quantity').text($(this).data('quantity') + ' ' + $(this).data('unit'));
            $('#view-date').text($(this).data('date'));
            $('#view-purpose').text($(this).data('purpose'));
            $('#view-assigned').text($(this).data('assigned'));
            $('#view-notes').text($(this).data('notes'));
            $('#view-created').text($(this).data('created'));
            $('#view-createdby').text($(this).data('createdby'));
        });
        
        // Validate quantity doesn't exceed available stock
        $('#quantity').on('input', function() {
            var selectedOption = $('#item_id').find('option:selected');
            var availableStock = selectedOption.data('stock');
            var enteredQuantity = parseFloat($(this).val());
            
            if (enteredQuantity > availableStock) {
                $(this).addClass('is-invalid');
                $('#stock-info').addClass('text-danger').removeClass('text-muted');
            } else {
                $(this).removeClass('is-invalid');
                $('#stock-info').removeClass('text-danger').addClass('text-muted');
            }
        });
    });
    
    // Export to CSV function
    function exportToCSV() {
        window.location.href = 'export_usage.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&item_filter=<?php echo $itemFilter; ?>&search=<?php echo urlencode($searchTerm); ?>';
    }
</script>

<style>
    .table th {
        background-color: #f8f9fa;
    }
    
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    .page-header {
        margin-bottom: 20px;
    }
    
    .list-group-item {
        padding: 0.5rem 1rem;
    }
</style>

<?php include 'includes/footer.php'; ?>