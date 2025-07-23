<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$batches = [];
$items = [];

// Process batch status update if submitted
if (isset($_POST['update_batch_status']) && isset($_POST['batch_id']) && isset($_POST['new_status'])) {
    $batchId = $_POST['batch_id'];
    $newStatus = $_POST['new_status'];
    $notes = isset($_POST['status_notes']) ? $_POST['status_notes'] : '';
    
    try {
        $stmt = $pdo->prepare("UPDATE inventory_batches SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n', NOW(), ' - Status changed to ', ?, ' - ', ?) WHERE id = ?");
        $result = $stmt->execute([$newStatus, $newStatus, $notes, $batchId]);
        
        if ($result) {
            $successMsg = "Batch status has been updated successfully.";
        } else {
            $errorMsg = "Failed to update batch status.";
        }
    } catch(PDOException $e) {
        error_log("Error updating batch status: " . $e->getMessage());
        $errorMsg = "An error occurred while updating the batch.";
    }
}

// Fetch all inventory items
try {
    $stmt = $pdo->query("SELECT id, item_name FROM inventory_items WHERE status = 'active' AND batch_tracking_enabled = 1 ORDER BY item_name ASC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching inventory items: " . $e->getMessage());
    $errorMsg = "Failed to load inventory items.";
}

// Process search and filter
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$itemFilter = isset($_GET['item_id']) ? $_GET['item_id'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$expiryFilter = isset($_GET['expiry_filter']) ? $_GET['expiry_filter'] : '';

// Prepare base query
$query = "SELECT b.id, b.batch_number, b.item_id, i.item_name, b.quantity, 
          b.manufacturing_date, b.expiry_date, b.received_date, 
          b.status, s.name as supplier_name, b.cost_per_unit
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

// Fetch the batches
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching batches: " . $e->getMessage());
    $errorMsg = "Failed to load batches.";
}

$pageTitle = 'Batch Tracking';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-boxes"></i> Batch Tracking</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='add_batch.php'">
                <i class="fas fa-plus"></i> Add New Batch
            </button>
            <button class="btn btn-primary" onclick="location.href='batch_quality_check.php'">
                <i class="fas fa-plus"></i> Batch Tracking
            </button>
            <button class="btn btn-secondary" onclick="exportBatchesToCSV()">
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
                        <input type="text" class="form-control" name="search" placeholder="Search batches..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="form-group col-md-2">
                    <select name="item_id" class="form-control" onchange="this.form.submit()">
                        <option value="">All Items</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?php echo $item['id']; ?>" <?php echo ($itemFilter == $item['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['item_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo ($statusFilter == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="quarantine" <?php echo ($statusFilter == 'quarantine') ? 'selected' : ''; ?>>Quarantine</option>
                        <option value="consumed" <?php echo ($statusFilter == 'consumed') ? 'selected' : ''; ?>>Consumed</option>
                        <option value="expired" <?php echo ($statusFilter == 'expired') ? 'selected' : ''; ?>>Expired</option>
                        <option value="discarded" <?php echo ($statusFilter == 'discarded') ? 'selected' : ''; ?>>Discarded</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <select name="expiry_filter" class="form-control" onchange="this.form.submit()">
                        <option value="">All Expiry Dates</option>
                        <option value="expired" <?php echo ($expiryFilter == 'expired') ? 'selected' : ''; ?>>Expired</option>
                        <option value="expiring_soon" <?php echo ($expiryFilter == 'expiring_soon') ? 'selected' : ''; ?>>Expiring Soon (30 days)</option>
                        <option value="valid" <?php echo ($expiryFilter == 'valid') ? 'selected' : ''; ?>>Valid</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="window.location.href='batch_tracking.php'">
                        <i class="fas fa-sync-alt"></i> Reset
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Batches Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Batch #</th>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Received Date</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                    <th>Supplier</th>
                    <th>Cost/Unit</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($batches) > 0): ?>
                    <?php foreach ($batches as $batch): ?>
                        <tr class="<?php 
                            if ($batch['status'] == 'quarantine') echo 'table-warning';
                            else if ($batch['status'] == 'expired' || $batch['status'] == 'discarded') echo 'table-danger';
                            else if (!empty($batch['expiry_date']) && strtotime($batch['expiry_date']) < strtotime('+30 days')) echo 'table-warning';
                        ?>">
                            <td><?php echo htmlspecialchars($batch['batch_number']); ?></td>
                            <td><?php echo htmlspecialchars($batch['item_name']); ?></td>
                            <td><?php echo $batch['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($batch['received_date']); ?></td>
                            <td>
                                <?php 
                                    if (!empty($batch['expiry_date'])) {
                                        $expiryDate = new DateTime($batch['expiry_date']);
                                        $today = new DateTime();
                                        $interval = $today->diff($expiryDate);
                                        $daysRemaining = $expiryDate > $today ? $interval->days : -$interval->days;
                                        
                                        $expiryClass = '';
                                        if ($daysRemaining < 0) {
                                            $expiryClass = 'text-danger';
                                        } elseif ($daysRemaining <= 30) {
                                            $expiryClass = 'text-warning';
                                        }
                                        
                                        echo '<span class="'.$expiryClass.'">' . htmlspecialchars($batch['expiry_date']);
                                        if ($daysRemaining < 0) {
                                            echo ' (Expired)';
                                        } elseif ($daysRemaining <= 30) {
                                            echo ' (' . $daysRemaining . ' days left)';
                                        }
                                        echo '</span>';
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </td>
                            <td>
                                <span class="badge <?php 
                                    switch($batch['status']) {
                                        case 'active': echo 'badge-success'; break;
                                        case 'quarantine': echo 'badge-warning'; break;
                                        case 'consumed': echo 'badge-info'; break;
                                        case 'expired': echo 'badge-danger'; break;
                                        case 'discarded': echo 'badge-dark'; break;
                                        default: echo 'badge-secondary';
                                    }
                                ?>">
                                    <?php echo ucfirst($batch['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($batch['supplier_name'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($batch['cost_per_unit'], 2); ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="view_batch.php?id=<?php echo $batch['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_batch.php?id=<?php echo $batch['id']; ?>" class="btn btn-sm btn-primary" title="Edit Batch">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-warning" title="Change Status" 
                                            onclick="showStatusModal(<?php echo $batch['id']; ?>, '<?php echo $batch['status']; ?>')">
                                        <i class="fas fa-exchange-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">No batches found. <?php echo !empty($searchTerm) || !empty($itemFilter) || !empty($statusFilter) || !empty($expiryFilter) ? 'Try adjusting your search or filters.' : ''; ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Batch Summary -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Batch Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Total Batches</h6>
                        <p class="stats-number"><?php echo count($batches); ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Batches in Quarantine</h6>
                        <p class="stats-number text-warning">
                            <?php 
                                $quarantineCount = 0;
                                foreach ($batches as $batch) {
                                    if ($batch['status'] == 'quarantine') {
                                        $quarantineCount++;
                                    }
                                }
                                echo $quarantineCount;
                            ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Batches Expiring Soon</h6>
                        <p class="stats-number text-warning">
                            <?php 
                                $expiringSoonCount = 0;
                                $today = new DateTime();
                                $thirtyDaysLater = (new DateTime())->add(new DateInterval('P30D'));
                                
                                foreach ($batches as $batch) {
                                    // Count batches that are not already expired or discarded or consumed
                                    if (!empty($batch['expiry_date']) && 
                                        $batch['status'] != 'expired' && 
                                        $batch['status'] != 'discarded' &&
                                        $batch['status'] != 'consumed') {
                                        
                                        $expiryDate = new DateTime($batch['expiry_date']);
                                        
                                        // Check if expiry date is in the future but within 30 days
                                        if ($expiryDate > $today && $expiryDate <= $thirtyDaysLater) {
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
                        <h6>Expired Batches</h6>
                        <p class="stats-number text-danger">
                            <?php 
                                $expiredCount = 0;
                                $today = new DateTime();
                                foreach ($batches as $batch) {
                                    // Count batches explicitly marked as expired
                                    if ($batch['status'] == 'expired') {
                                        $expiredCount++;
                                    }
                                    // Also count active batches that have passed their expiry date
                                    else if ($batch['status'] == 'active' && !empty($batch['expiry_date'])) {
                                        $expiryDate = new DateTime($batch['expiry_date']);
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
            </div>
        </div>
    </div>
</div>

<!-- Status Change Modal -->
<div class="modal fade" id="changeStatusModal" tabindex="-1" role="dialog" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeStatusModalLabel">Change Batch Status</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="batch_id" id="statusBatchId">
                    <input type="hidden" name="update_batch_status" value="1">
                    
                    <div class="form-group">
                        <label for="new_status">New Status:</label>
                        <select class="form-control" id="new_status" name="new_status" required>
                            <option value="active">Active</option>
                            <option value="quarantine">Quarantine</option>
                            <option value="consumed">Consumed</option>
                            <option value="expired">Expired</option>
                            <option value="discarded">Discarded</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status_notes">Notes:</label>
                        <textarea class="form-control" id="status_notes" name="status_notes" rows="3" placeholder="Reason for status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function showStatusModal(batchId, currentStatus) {
        document.getElementById('statusBatchId').value = batchId;
        document.getElementById('new_status').value = currentStatus;
        $('#changeStatusModal').modal('show');
    }

    function exportBatchesToCSV() {
        // Redirect to a CSV export handler
        window.location.href = 'export_batches.php?search=<?php echo urlencode($searchTerm); ?>&item_id=<?php echo urlencode($itemFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&expiry_filter=<?php echo urlencode($expiryFilter); ?>';
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
    
    /* Fix badge text colors for better visibility */
    .badge-success {
        color: #fff !important;
        background-color: #28a745 !important;
    }
    
    .badge-warning {
        color: #212529 !important;
        background-color: #ffc107 !important;
    }
    
    .badge-info {
        color: #fff !important;
        background-color: #17a2b8 !important;
    }
    
    .badge-danger {
        color: #fff !important;
        background-color: #dc3545 !important;
    }
    
    .badge-dark {
        color: #fff !important;
        background-color: #343a40 !important;
    }
    
    .badge-secondary {
        color: #fff !important;
        background-color: #6c757d !important;
    }
    
    /* Make sure table row colors don't override badge colors */
    .table-warning .badge, .table-danger .badge {
        color: inherit !important;
    }
</style>

<?php include 'includes/footer.php'; ?>