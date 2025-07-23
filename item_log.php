<?php
require_once 'includes/auth.php';
auth()->checkAuthenticated(); // Changed from checkAdmin to allow both roles

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$item = [];
$logs = [];

// Check if item ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: item_details.php');
    exit;
}

$itemId = $_GET['id'];

// Fetch item details
try {
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as category_name
        FROM inventory_items i
        LEFT JOIN item_categories c ON i.category_id = c.id
        WHERE i.id = ? AND i.status = 'active'
    ");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        header('Location: item_details.php');
        exit;
    }
} catch(PDOException $e) {
    error_log("Error fetching item details: " . $e->getMessage());
    $errorMsg = "Failed to load item details. Please try again later.";
}

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20; // Number of logs per page
$offset = ($page - 1) * $perPage;

// Set up filtering
$actionFilter = isset($_GET['action']) ? $_GET['action'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Check if inventory_log table exists and has the right structure
try {
    // Check if inventory_log table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'inventory_log'");
    $tableExists = $tableCheck->rowCount() > 0;
    
    if (!$tableExists) {
        // Create inventory_log table if it doesn't exist
        $pdo->exec("
            CREATE TABLE inventory_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                quantity DECIMAL(10,2) NOT NULL,
                notes TEXT NULL,
                user_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                batch_number VARCHAR(50) NULL,
                expiry_date DATE NULL,
                unit_cost DECIMAL(10,2) NULL,
                supplier_id INT NULL
            )
        ");
        error_log("Created inventory_log table");
    } else {
        // Check if required columns exist
        $columnsCheck = $pdo->query("DESCRIBE inventory_log");
        $columns = $columnsCheck->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredColumns = [
            'batch_number' => 'VARCHAR(50) NULL',
            'expiry_date' => 'DATE NULL',
            'unit_cost' => 'DECIMAL(10,2) NULL',
            'supplier_id' => 'INT NULL'
        ];
        
        foreach ($requiredColumns as $column => $definition) {
            if (!in_array($column, $columns)) {
                $pdo->exec("ALTER TABLE inventory_log ADD COLUMN $column $definition");
                error_log("Added $column column to inventory_log table");
            }
        }
    }
} catch(PDOException $e) {
    error_log("Error checking/creating inventory_log table: " . $e->getMessage());
}

// Prepare the base query for logs with error catching
try {
    // Base query with safer JOIN handling
    $logQuery = "
        SELECT l.*, u.username as user_name
        FROM inventory_log l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.item_id = ?
    ";

    $countQuery = "
        SELECT COUNT(*) as total
        FROM inventory_log
        WHERE item_id = ?
    ";

    $queryParams = [$itemId];

    // Add filters if provided
    if (!empty($actionFilter)) {
        $logQuery .= " AND l.action_type = ?";
        $countQuery .= " AND action_type = ?";
        $queryParams[] = $actionFilter;
    }

    if (!empty($dateFrom)) {
        $logQuery .= " AND DATE(l.created_at) >= ?";
        $countQuery .= " AND DATE(created_at) >= ?";
        $queryParams[] = $dateFrom;
    }

    if (!empty($dateTo)) {
        $logQuery .= " AND DATE(l.created_at) <= ?";
        $countQuery .= " AND DATE(created_at) <= ?";
        $queryParams[] = $dateTo;
    }
    
    // Get total count for pagination
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($queryParams);
    $totalLogs = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate total pages
    $totalPages = ceil($totalLogs / $perPage);
    
    // Add order by and limit for pagination - FIXED SQL SYNTAX ERROR
    // MariaDB requires integers directly in the query for LIMIT, not placeholders
    $logQuery .= " ORDER BY l.created_at DESC LIMIT " . intval($perPage) . " OFFSET " . intval($offset);
    
    // Get logs for current page
    $logStmt = $pdo->prepare($logQuery);
    $logStmt->execute($queryParams); // No need for $perPage and $offset as parameters now
    $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Error fetching inventory logs: " . $e->getMessage());
    $errorMsg = "Failed to load inventory logs: " . $e->getMessage();
    $totalLogs = 0;
    $totalPages = 0;
    $logs = [];
}

// Calculate running balance with error handling
$runningBalance = $item['current_quantity'];
try {
    if (!empty($logs) && $totalLogs > 0) {
        // Simplified balance calculation - just use current quantity
        // This is less accurate but won't fail if there are database issues
        $runningBalance = $item['current_quantity'];
    }
} catch(PDOException $e) {
    error_log("Error calculating running balance: " . $e->getMessage());
}

// Get unique action types for filtering with error handling
$actionTypes = [];
try {
    $actionTypeStmt = $pdo->prepare("
        SELECT DISTINCT action_type 
        FROM inventory_log 
        WHERE item_id = ?
        ORDER BY action_type
    ");
    $actionTypeStmt->execute([$itemId]);
    while ($row = $actionTypeStmt->fetch(PDO::FETCH_ASSOC)) {
        $actionTypes[] = $row['action_type'];
    }
} catch(PDOException $e) {
    error_log("Error fetching action types: " . $e->getMessage());
    // If we can't get action types, set some defaults
    $actionTypes = ['manual_add', 'manual_remove', 'initial_add', 'sale', 'purchase', 'waste', 'return'];
}

$pageTitle = 'Item Activity Log';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-history"></i> Activity Log: <?php echo htmlspecialchars($item['item_name']); ?></h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='view_item.php?id=<?php echo $itemId; ?>'">
                <i class="fas fa-arrow-left"></i> Back to Item Details
            </button>
            <button class="btn btn-info" onclick="exportLogToCSV()">
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

    <!-- Item Summary Card -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Item Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <p><strong>Item Name:</strong> <?php echo htmlspecialchars($item['item_name']); ?></p>
                    <p><strong>SKU:</strong> <?php echo htmlspecialchars($item['sku']); ?></p>
                </div>
                <div class="col-md-3">
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($item['category_name']); ?></p>
                    <p><strong>Unit:</strong> <?php echo htmlspecialchars($item['unit_of_measure']); ?></p>
                </div>
                <div class="col-md-3">
                    <p><strong>Current Quantity:</strong> <?php echo $item['current_quantity']; ?> <?php echo htmlspecialchars($item['unit_of_measure']); ?></p>
                    <p><strong>Reorder Level:</strong> <?php echo $item['reorder_level']; ?> <?php echo htmlspecialchars($item['unit_of_measure']); ?></p>
                </div>
                <div class="col-md-3">
                    <p><strong>Unit Cost:</strong> RM <?php echo number_format($item['unit_cost'], 2); ?></p>
                    <p><strong>Total Value:</strong> RM <?php echo number_format($item['unit_cost'] * $item['current_quantity'], 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">Filter Log</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="filter-form">
                <input type="hidden" name="id" value="<?php echo $itemId; ?>">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="action">Action Type</label>
                        <select class="form-control" id="action" name="action">
                            <option value="">All Actions</option>
                            <?php foreach ($actionTypes as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo ($actionFilter == $type) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="date_from">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="date_to">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="form-group col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="item_log.php?id=<?php echo $itemId; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Activity Log Table -->
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">Activity Log</h5>
            <small class="text-white"><?php echo $totalLogs; ?> records found</small>
        </div>
        <div class="card-body">
            <?php if (count($logs) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Action</th>
                                <th>Quantity</th>
                                <th>Balance</th>
                                <th>Batch #</th>
                                <th>Expiry Date</th>
                                <th>Unit Cost</th>
                                <th>Notes</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                    // Adjust running balance
                                    if (in_array($log['action_type'], ['manual_remove', 'sale', 'waste', 'return'])) {
                                        $runningBalance += $log['quantity'];
                                    } else {
                                        $runningBalance -= $log['quantity'];
                                    }
                                ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <?php 
                                            $actionLabel = '';
                                            switch ($log['action_type']) {
                                                case 'initial_add':
                                                    $actionLabel = '<span class="badge badge-success">Initial Add</span>';
                                                    break;
                                                case 'manual_add':
                                                    $actionLabel = '<span class="badge badge-primary">Manual Add</span>';
                                                    break;
                                                case 'manual_remove':
                                                    $actionLabel = '<span class="badge badge-warning">Manual Remove</span>';
                                                    break;
                                                case 'sale':
                                                    $actionLabel = '<span class="badge badge-info">Sale</span>';
                                                    break;
                                                case 'purchase':
                                                    $actionLabel = '<span class="badge badge-success">Purchase</span>';
                                                    break;
                                                case 'waste':
                                                    $actionLabel = '<span class="badge badge-danger">Waste</span>';
                                                    break;
                                                case 'return':
                                                    $actionLabel = '<span class="badge badge-dark">Return</span>';
                                                    break;
                                                default:
                                                    $actionLabel = '<span class="badge badge-secondary">' . ucfirst(str_replace('_', ' ', $log['action_type'])) . '</span>';
                                            }
                                            echo $actionLabel;
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            if (in_array($log['action_type'], ['manual_remove', 'sale', 'waste', 'return'])) {
                                                echo '<span class="text-danger">-';
                                            } else {
                                                echo '<span class="text-success">+';
                                            }
                                            echo $log['quantity'] . '</span>'; 
                                        ?>
                                    </td>
                                    <td><?php echo $runningBalance; ?></td>
                                    <td><?php echo !empty($log['batch_number']) ? htmlspecialchars($log['batch_number']) : '-'; ?></td>
                                    <td><?php echo !empty($log['expiry_date']) ? htmlspecialchars($log['expiry_date']) : '-'; ?></td>
                                    <td>
                                        <?php echo !empty($log['unit_cost']) ? 'RM ' . number_format($log['unit_cost'], 2) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($log['notes']) ? '<span title="' . htmlspecialchars($log['notes']) . '">' . 
                                            (strlen($log['notes']) > 30 ? htmlspecialchars(substr($log['notes'], 0, 30)) . '...' : htmlspecialchars($log['notes']))
                                            . '</span>' : '-'; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Activity log pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="item_log.php?id=<?php echo $itemId; ?>&page=1<?php echo !empty($actionFilter) ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?>">First</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="item_log.php?id=<?php echo $itemId; ?>&page=<?php echo $page - 1; ?><?php echo !empty($actionFilter) ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="item_log.php?id=<?php echo $itemId; ?>&page=<?php echo $i; ?><?php echo !empty($actionFilter) ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="item_log.php?id=<?php echo $itemId; ?>&page=<?php echo $page + 1; ?><?php echo !empty($actionFilter) ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?>">Next</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="item_log.php?id=<?php echo $itemId; ?>&page=<?php echo $totalPages; ?><?php echo !empty($actionFilter) ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?>">Last</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No activity logs found for this item.
                    <?php if (!empty($actionFilter) || !empty($dateFrom) || !empty($dateTo)): ?>
                        Try adjusting your filter criteria.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function exportLogToCSV() {
        // Redirect to export handler with same filters
        window.location.href = 'export_item_log.php?id=<?php echo $itemId; ?><?php echo !empty($actionFilter) ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?>';
    }
</script>

<style>
    .filter-form label {
        font-weight: 500;
        font-size: 0.9rem;
    }
    
    .badge {
        padding: 0.4em 0.6em;
        font-size: 85%;
    }
    
    .table th {
        background-color: #f8f9fa;
        white-space: nowrap;
    }
</style>

<?php include 'includes/footer.php'; ?>