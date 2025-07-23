<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$items = [];
$categories = [];

// Fetch all active categories for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM item_categories WHERE status = 'active' ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $errorMsg = "Failed to load categories. Please try again later.";
}

// Handle update expiry action
if (isset($_POST['update_expiry']) && isset($_POST['item_id']) && isset($_POST['new_expiry_date'])) {
    $itemId = $_POST['item_id'];
    $newExpiryDate = $_POST['new_expiry_date'];
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    try {
        // Update the expiry date and add to notes
        $stmt = $pdo->prepare("UPDATE inventory_items SET 
                              expiry_date = ?, 
                              notes = CONCAT(IFNULL(notes, ''), '\n', NOW(), ' - Expiry date updated to ', ?, ' - ', ?) 
                              WHERE id = ?");
        $result = $stmt->execute([$newExpiryDate, $newExpiryDate, $notes, $itemId]);
        
        if ($result) {
            $successMsg = "Expiry date has been updated successfully.";
        } else {
            $errorMsg = "Failed to update expiry date.";
        }
    } catch(PDOException $e) {
        error_log("Error updating expiry date: " . $e->getMessage());
        $errorMsg = "An error occurred while updating the expiry date.";
    }
}

// Handle batch expiry update
if (isset($_POST['update_batch_expiry']) && isset($_POST['batch_id']) && isset($_POST['new_batch_expiry_date'])) {
    $batchId = $_POST['batch_id'];
    $newExpiryDate = $_POST['new_batch_expiry_date'];
    $notes = isset($_POST['batch_notes']) ? $_POST['batch_notes'] : '';
    
    try {
        // Update the batch expiry date and add to notes
        $stmt = $pdo->prepare("UPDATE inventory_batches SET 
                              expiry_date = ?, 
                              notes = CONCAT(IFNULL(notes, ''), '\n', NOW(), ' - Expiry date updated to ', ?, ' - ', ?) 
                              WHERE id = ?");
        $result = $stmt->execute([$newExpiryDate, $newExpiryDate, $notes, $batchId]);
        
        if ($result) {
            $successMsg = "Batch expiry date has been updated successfully.";
        } else {
            $errorMsg = "Failed to update batch expiry date.";
        }
    } catch(PDOException $e) {
        error_log("Error updating batch expiry date: " . $e->getMessage());
        $errorMsg = "An error occurred while updating the batch expiry date.";
    }
}

// Handle mark as expired action
if (isset($_POST['mark_expired']) && isset($_POST['item_id'])) {
    $itemId = $_POST['item_id'];
    $notes = isset($_POST['expired_notes']) ? $_POST['expired_notes'] : 'Manually marked as expired';
    
    try {
        // Update the item status to expired
        $stmt = $pdo->prepare("UPDATE inventory_items SET 
                              status = 'expired', 
                              notes = CONCAT(IFNULL(notes, ''), '\n', NOW(), ' - Marked as expired - ', ?) 
                              WHERE id = ?");
        $result = $stmt->execute([$notes, $itemId]);
        
        if ($result) {
            $successMsg = "Item has been marked as expired successfully.";
        } else {
            $errorMsg = "Failed to mark item as expired.";
        }
    } catch(PDOException $e) {
        error_log("Error marking item as expired: " . $e->getMessage());
        $errorMsg = "An error occurred while marking the item as expired.";
    }
}

// Process search query and filters
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$expiryFilter = isset($_GET['expiry_filter']) ? $_GET['expiry_filter'] : 'all';
$batchFilter = isset($_GET['batch_filter']) ? $_GET['batch_filter'] : '';

// Prepare base query for individual items with expiry dates
$query = "SELECT i.id, i.item_name as name, i.sku, i.description, i.category_id, 
          c.name as category_name, i.current_quantity, i.unit_of_measure,
          i.expiry_date, i.batch_tracking_enabled, NULL as batch_id, NULL as batch_number,
          'single' as tracking_type, i.supplier_id, s.name as supplier_name,
          i.status
          FROM inventory_items i
          LEFT JOIN item_categories c ON i.category_id = c.id
          LEFT JOIN suppliers s ON i.supplier_id = s.id
          WHERE i.status != 'inactive' AND i.expiry_date IS NOT NULL";

// Query for batch items with expiry dates
$batchQuery = "SELECT i.id as item_id, i.item_name as name, i.sku, i.description as description, i.category_id, 
              c.name as category_name, b.quantity as current_quantity, i.unit_of_measure,
              b.expiry_date, i.batch_tracking_enabled, b.id as batch_id, b.batch_number,
              'batch' as tracking_type, b.supplier_id, s.name as supplier_name,
              b.status
              FROM inventory_batches b
              JOIN inventory_items i ON b.item_id = i.id
              LEFT JOIN item_categories c ON i.category_id = c.id
              LEFT JOIN suppliers s ON b.supplier_id = s.id
              WHERE b.status != 'inactive' AND b.expiry_date IS NOT NULL";

// Build parameters arrays for each query
$queryParams = [];
$batchQueryParams = [];

// Add search filter if provided
if (!empty($searchTerm)) {
    $query .= " AND (i.item_name LIKE ? OR i.sku LIKE ? OR i.description LIKE ?)";
    $batchQuery .= " AND (i.item_name LIKE ? OR i.sku LIKE ? OR b.batch_number LIKE ?)";
    $searchParam = "%{$searchTerm}%";
    $queryParams = array_merge($queryParams, [$searchParam, $searchParam, $searchParam]);
    $batchQueryParams = array_merge($batchQueryParams, [$searchParam, $searchParam, $searchParam]);
}

// Add category filter if provided
if (!empty($categoryFilter)) {
    $query .= " AND i.category_id = ?";
    $batchQuery .= " AND i.category_id = ?";
    $queryParams[] = $categoryFilter;
    $batchQueryParams[] = $categoryFilter;
}

// Add batch filter if provided
if (!empty($batchFilter)) {
    $query .= " AND 1=0"; // Exclude single items if filtering by batch
    $batchQuery .= " AND b.batch_number LIKE ?";
    $batchQueryParams[] = "%{$batchFilter}%";
}

// Add expiry filter
$today = date('Y-m-d');
if ($expiryFilter == 'expired') {
    $query .= " AND i.expiry_date < ?";
    $batchQuery .= " AND b.expiry_date < ?";
    $queryParams[] = $today;
    $batchQueryParams[] = $today;
} else if ($expiryFilter == 'expiring_soon') {
    $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
    $query .= " AND i.expiry_date BETWEEN ? AND ?";
    $batchQuery .= " AND b.expiry_date BETWEEN ? AND ?";
    $queryParams = array_merge($queryParams, [$today, $thirtyDaysLater]);
    $batchQueryParams = array_merge($batchQueryParams, [$today, $thirtyDaysLater]);
} else if ($expiryFilter == 'valid') {
    $query .= " AND i.expiry_date > ?";
    $batchQuery .= " AND b.expiry_date > ?";
    $queryParams[] = $today;
    $batchQueryParams[] = $today;
}

// Execute queries separately and combine results
$items = [];
try {
    // Execute single items query
    $stmt = $pdo->prepare($query);
    $stmt->execute($queryParams);
    $singleItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Execute batch items query
    $stmt = $pdo->prepare($batchQuery);
    $stmt->execute($batchQueryParams);
    $batchItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine and sort results
    $items = array_merge($singleItems, $batchItems);
    
    // Sort by expiry date
    usort($items, function($a, $b) {
        return strcmp($a['expiry_date'], $b['expiry_date']);
    });
    
} catch(PDOException $e) {
    error_log("Error fetching expiry data: " . $e->getMessage());
    $errorMsg = "Failed to load expiry data. Please try again later.";
}

$pageTitle = 'Expiry Tracking';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-calendar-times"></i> Expiry Tracking</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="exportExpiryToCSV()">
                <i class="fas fa-file-export"></i> Export CSV
            </button>
            
            <button class="btn btn-secondary" onclick="location.href='inventory.php'">
                <i class="fas fa-arrow-left"></i> Back to Inventory
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
        <form method="GET" action="" class="search-filter-form">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($searchTerm); ?>">
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
                    <input type="text" class="form-control" name="batch_filter" placeholder="Batch Number..." value="<?php echo htmlspecialchars($batchFilter); ?>">
                </div>
                <div class="form-group col-md-3">
                    <select name="expiry_filter" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?php echo ($expiryFilter == 'all') ? 'selected' : ''; ?>>All Items</option>
                        <option value="expired" <?php echo ($expiryFilter == 'expired') ? 'selected' : ''; ?>>Expired</option>
                        <option value="expiring_soon" <?php echo ($expiryFilter == 'expiring_soon') ? 'selected' : ''; ?>>Expiring Soon (30 days)</option>
                        <option value="valid" <?php echo ($expiryFilter == 'valid') ? 'selected' : ''; ?>>Valid</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="window.location.href='expiry_tracking.php'">
                        <i class="fas fa-sync-alt"></i> Reset
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Expiry Tracking Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Item/Batch</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Tracking Type</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                    <th>Supplier</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                            $rowClass = '';
                            if (!empty($item['expiry_date'])) {
                                $expiryDate = new DateTime($item['expiry_date']);
                                $today = new DateTime();
                                $interval = $today->diff($expiryDate);
                                $daysRemaining = $expiryDate > $today ? $interval->days : -$interval->days;
                                
                                if ($daysRemaining < 0) {
                                    $rowClass = 'table-danger';
                                } elseif ($daysRemaining <= 30) {
                                    $rowClass = 'table-warning';
                                }
                            }
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td>
                                <?php echo htmlspecialchars($item['name']); ?>
                                <?php if ($item['tracking_type'] == 'batch'): ?>
                                    <br><small class="text-muted">Batch: <?php echo htmlspecialchars($item['batch_number']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['sku']); ?></td>
                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                            <td><?php echo $item['current_quantity']; ?> <?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                            <td>
                                <span class="badge <?php echo ($item['tracking_type'] == 'batch') ? 'badge-info' : 'badge-secondary'; ?>">
                                    <?php echo ucfirst($item['tracking_type']); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                    if (!empty($item['expiry_date'])) {
                                        $expiryDate = new DateTime($item['expiry_date']);
                                        $today = new DateTime();
                                        $interval = $today->diff($expiryDate);
                                        $daysRemaining = $expiryDate > $today ? $interval->days : -$interval->days;
                                        
                                        $expiryClass = '';
                                        if ($daysRemaining < 0) {
                                            $expiryClass = 'text-danger font-weight-bold';
                                            echo '<span class="'.$expiryClass.'">' . htmlspecialchars($item['expiry_date']) . ' (Expired)</span>';
                                        } elseif ($daysRemaining <= 30) {
                                            $expiryClass = 'text-warning font-weight-bold';
                                            echo '<span class="'.$expiryClass.'">' . htmlspecialchars($item['expiry_date']) . ' (' . $daysRemaining . ' days left)</span>';
                                        } else {
                                            echo htmlspecialchars($item['expiry_date']);
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </td>
                            <td>
                                <span class="badge <?php 
                                    switch($item['status']) {
                                        case 'active': echo 'badge-success'; break;
                                        case 'quarantine': echo 'badge-warning'; break;
                                        case 'consumed': echo 'badge-info'; break;
                                        case 'expired': echo 'badge-danger'; break;
                                        case 'discarded': echo 'badge-dark'; break;
                                        default: echo 'badge-secondary';
                                    }
                                ?>">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($item['supplier_name'] ?? 'N/A'); ?></td>
                            <td>
                                <div class="btn-group">
                                    <?php if ($item['tracking_type'] == 'single'): ?>
                                        <a href="view_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-primary" title="Update Expiry" 
                                                onclick="showUpdateExpiryModal(<?php echo $item['id']; ?>, '<?php echo $item['expiry_date']; ?>')">
                                            <i class="fas fa-calendar-alt"></i>
                                        </button>
                                        <?php if ($item['status'] != 'expired' && $item['status'] != 'discarded'): ?>
                                            <button type="button" class="btn btn-sm btn-danger" title="Mark as Expired" 
                                                    onclick="showMarkExpiredModal(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['name'])); ?>')">
                                                <i class="fas fa-times-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="view_batch.php?id=<?php echo $item['batch_id']; ?>" class="btn btn-sm btn-info" title="View Batch Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-primary" title="Update Batch Expiry" 
                                                onclick="showUpdateBatchExpiryModal(<?php echo $item['batch_id']; ?>, '<?php echo $item['expiry_date']; ?>')">
                                            <i class="fas fa-calendar-alt"></i>
                                        </button>
                                        <a href="batch_quality_check.php?batch_id=<?php echo $item['batch_id']; ?>" class="btn btn-sm btn-success" title="Quality Check">
                                            <i class="fas fa-clipboard-check"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">No items with expiry dates found. <?php echo !empty($searchTerm) || !empty($categoryFilter) || !empty($batchFilter) || $expiryFilter != 'all' ? 'Try adjusting your search or filters.' : ''; ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Expiry Summary -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Expiry Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Total Items/Batches</h6>
                        <p class="stats-number"><?php echo count($items); ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Expired</h6>
                        <p class="stats-number text-danger">
                            <?php 
                                $expiredCount = 0;
                                $today = new DateTime();
                                foreach ($items as $item) {
                                    if (!empty($item['expiry_date'])) {
                                        $expiryDate = new DateTime($item['expiry_date']);
                                        if ($expiryDate < $today) {
                                            $expiredCount++;
                                        }
                                    }
                                }
                                echo $expiredCount;
                            ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Expiring Soon (30 days)</h6>
                        <p class="stats-number text-warning">
                            <?php 
                                $expiringSoonCount = 0;
                                $today = new DateTime();
                                foreach ($items as $item) {
                                    if (!empty($item['expiry_date'])) {
                                        $expiryDate = new DateTime($item['expiry_date']);
                                        $interval = $today->diff($expiryDate);
                                        $daysRemaining = $expiryDate > $today ? $interval->days : -$interval->days;
                                        
                                        if ($daysRemaining >= 0 && $daysRemaining <= 30) {
                                            $expiringSoonCount++;
                                        }
                                    }
                                }
                                echo $expiringSoonCount;
                            ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Valid</h6>
                        <p class="stats-number text-success">
                            <?php 
                                $validCount = 0;
                                $today = new DateTime();
                                foreach ($items as $item) {
                                    if (!empty($item['expiry_date'])) {
                                        $expiryDate = new DateTime($item['expiry_date']);
                                        $interval = $today->diff($expiryDate);
                                        $daysRemaining = $expiryDate > $today ? $interval->days : -$interval->days;
                                        
                                        if ($daysRemaining > 30) {
                                            $validCount++;
                                        }
                                    }
                                }
                                echo $validCount;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Expiry Date Modal -->
<div class="modal fade" id="updateExpiryModal" tabindex="-1" role="dialog" aria-labelledby="updateExpiryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateExpiryModalLabel">Update Expiry Date</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="updateItemId">
                    <input type="hidden" name="update_expiry" value="1">
                    
                    <div class="form-group">
                        <label for="new_expiry_date">New Expiry Date:</label>
                        <input type="date" class="form-control" id="new_expiry_date" name="new_expiry_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Reason for expiry date change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Expiry Date</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Batch Expiry Date Modal -->
<div class="modal fade" id="updateBatchExpiryModal" tabindex="-1" role="dialog" aria-labelledby="updateBatchExpiryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateBatchExpiryModalLabel">Update Batch Expiry Date</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="batch_id" id="updateBatchId">
                    <input type="hidden" name="update_batch_expiry" value="1">
                    
                    <div class="form-group">
                        <label for="new_batch_expiry_date">New Expiry Date:</label>
                        <input type="date" class="form-control" id="new_batch_expiry_date" name="new_batch_expiry_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="batch_notes">Notes:</label>
                        <textarea class="form-control" id="batch_notes" name="batch_notes" rows="3" placeholder="Reason for expiry date change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Batch Expiry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mark as Expired Modal -->
<div class="modal fade" id="markExpiredModal" tabindex="-1" role="dialog" aria-labelledby="markExpiredModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="markExpiredModalLabel">Mark Item as Expired</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <p>Are you sure you want to mark <span id="expiredItemName"></span> as expired?</p>
                    <input type="hidden" name="item_id" id="expiredItemId">
                    <input type="hidden" name="mark_expired" value="1">
                    
                    <div class="form-group">
                        <label for="expired_notes">Notes:</label>
                        <textarea class="form-control" id="expired_notes" name="expired_notes" rows="3" placeholder="Reason for marking as expired..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Mark as Expired</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function showUpdateExpiryModal(itemId, currentExpiryDate) {
        document.getElementById('updateItemId').value = itemId;
        document.getElementById('new_expiry_date').value = currentExpiryDate;
        $('#updateExpiryModal').modal('show');
    }

    function showUpdateBatchExpiryModal(batchId, currentExpiryDate) {
        document.getElementById('updateBatchId').value = batchId;
        document.getElementById('new_batch_expiry_date').value = currentExpiryDate;
        $('#updateBatchExpiryModal').modal('show');
    }

    function showMarkExpiredModal(itemId, itemName) {
        document.getElementById('expiredItemId').value = itemId;
        document.getElementById('expiredItemName').textContent = itemName;
        $('#markExpiredModal').modal('show');
    }

    function exportExpiryToCSV() {
        // Redirect to a CSV export handler
        window.location.href = 'export_expiry.php?search=<?php echo urlencode($searchTerm); ?>&category=<?php echo urlencode($categoryFilter); ?>&batch_filter=<?php echo urlencode($batchFilter); ?>&expiry_filter=<?php echo urlencode($expiryFilter); ?>';
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

    .table th {
        white-space: nowrap;
    }

    /* Highlight statuses */
    .table-warning {
        background-color: rgba(255, 193, 7, 0.2);
    }
    
    .table-danger {
        background-color: rgba(220, 53, 69, 0.2);
    }
</style>

<?php include 'includes/footer.php'; ?>