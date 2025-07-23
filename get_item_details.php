<?php
require_once 'includes/auth.php';
auth()->checkSupervisor(); // Only allow supervisor access

require_once 'includes/db.php';

// Check if item_id is provided
if (!isset($_GET['item_id']) || !is_numeric($_GET['item_id'])) {
    echo '<div class="alert alert-danger">Invalid item ID provided.</div>';
    exit;
}

$itemId = (int)$_GET['item_id'];

try {
    // Fetch detailed item information
    $stmt = $pdo->prepare("
        SELECT i.id, i.item_name, i.sku, i.description, i.category_id, c.name as category_name, 
               i.current_quantity, i.unit_of_measure, i.reorder_level, i.maximum_level,
               i.unit_cost, i.expiry_date, i.batch_number, i.supplier_id, s.name as supplier_name,
               s.phone as supplier_phone, s.email as supplier_email, i.created_at, i.updated_at
        FROM inventory_items i
        LEFT JOIN item_categories c ON i.category_id = c.id
        LEFT JOIN suppliers s ON i.supplier_id = s.id
        WHERE i.id = ? AND i.status = 'active'
    ");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        echo '<div class="alert alert-danger">Item not found or no longer active.</div>';
        exit;
    }
    
    // Get recent usage history for this item
    $recentUsage = [];
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'inventory_usage'");
        $usageTableExists = $stmt->rowCount() > 0;
        
        if ($usageTableExists) {
            $stmt = $pdo->prepare("
                SELECT u.quantity, u.usage_date, u.purpose, u.assigned_to, u.created_at,
                       us.username as created_by_name
                FROM inventory_usage u
                LEFT JOIN users us ON u.created_by = us.id
                WHERE u.item_id = ?
                ORDER BY u.usage_date DESC, u.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$itemId]);
            $recentUsage = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(PDOException $e) {
        error_log("Error fetching usage history: " . $e->getMessage());
    }
    
    // Get current stock requests for this item
    $currentRequests = [];
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'stock_requests'");
        $requestTableExists = $stmt->rowCount() > 0;
        
        if ($requestTableExists) {
            $stmt = $pdo->prepare("
                SELECT sr.requested_quantity, sr.purpose, sr.priority, sr.status, 
                       sr.requested_date, us.username as requested_by_name
                FROM stock_requests sr
                LEFT JOIN users us ON sr.requested_by = us.id
                WHERE sr.item_id = ? AND sr.status IN ('pending', 'approved')
                ORDER BY sr.requested_date DESC
                LIMIT 5
            ");
            $stmt->execute([$itemId]);
            $currentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch(PDOException $e) {
        error_log("Error fetching stock requests: " . $e->getMessage());
    }
    
    // Calculate stock status
    $stockStatus = 'Good';
    $stockClass = 'text-success';
    
    if ($item['current_quantity'] == 0) {
        $stockStatus = 'Out of Stock';
        $stockClass = 'text-danger';
    } elseif ($item['current_quantity'] <= $item['reorder_level']) {
        $stockStatus = 'Low Stock';
        $stockClass = 'text-warning';
    }
    
    // Check expiry status
    $expiryStatus = '';
    $expiryClass = '';
    
    if (!empty($item['expiry_date'])) {
        $expiryDate = new DateTime($item['expiry_date']);
        $today = new DateTime();
        $interval = $today->diff($expiryDate);
        $daysRemaining = $expiryDate > $today ? $interval->days : -$interval->days;
        
        if ($daysRemaining < 0) {
            $expiryStatus = 'Expired (' . abs($daysRemaining) . ' days ago)';
            $expiryClass = 'text-danger';
        } elseif ($daysRemaining <= 30) {
            $expiryStatus = 'Expiring soon (' . $daysRemaining . ' days remaining)';
            $expiryClass = 'text-warning';
        } else {
            $expiryStatus = $daysRemaining . ' days remaining';
            $expiryClass = 'text-success';
        }
    }
    
} catch(PDOException $e) {
    error_log("Error fetching item details: " . $e->getMessage());
    echo '<div class="alert alert-danger">Failed to load item details. Please try again later.</div>';
    exit;
}
?>

<div class="row">
    <!-- Basic Information -->
    <div class="col-md-6">
        <h5 class="text-primary mb-3"><i class="fas fa-info-circle"></i> Basic Information</h5>
        <table class="table table-sm table-borderless">
            <tr>
                <td><strong>Item Name:</strong></td>
                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
            </tr>
            <tr>
                <td><strong>SKU:</strong></td>
                <td><?php echo htmlspecialchars($item['sku']); ?></td>
            </tr>
            <tr>
                <td><strong>Category:</strong></td>
                <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td><strong>Description:</strong></td>
                <td><?php echo htmlspecialchars($item['description'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <td><strong>Batch Number:</strong></td>
                <td><?php echo htmlspecialchars($item['batch_number'] ?? 'N/A'); ?></td>
            </tr>
        </table>
    </div>
    
    <!-- Stock Information -->
    <div class="col-md-6">
        <h5 class="text-primary mb-3"><i class="fas fa-warehouse"></i> Stock Information</h5>
        <table class="table table-sm table-borderless">
            <tr>
                <td><strong>Current Stock:</strong></td>
                <td>
                    <span class="<?php echo $stockClass; ?> font-weight-bold">
                        <?php echo $item['current_quantity'] . ' ' . $item['unit_of_measure']; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Stock Status:</strong></td>
                <td>
                    <span class="<?php echo $stockClass; ?> font-weight-bold">
                        <?php echo $stockStatus; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Reorder Level:</strong></td>
                <td><?php echo $item['reorder_level'] . ' ' . $item['unit_of_measure']; ?></td>
            </tr>
            <tr>
                <td><strong>Maximum Level:</strong></td>
                <td><?php echo $item['maximum_level'] . ' ' . $item['unit_of_measure']; ?></td>
            </tr>
            <tr>
                <td><strong>Unit Cost:</strong></td>
                <td>RM <?php echo number_format($item['unit_cost'], 2); ?></td>
            </tr>
        </table>
    </div>
</div>

<!-- Supplier Information -->
<?php if (!empty($item['supplier_name'])): ?>
<div class="row mt-3">
    <div class="col-12">
        <h5 class="text-primary mb-3"><i class="fas fa-handshake"></i> Supplier Information</h5>
        <table class="table table-sm table-borderless">
            <tr>
                <td style="width: 150px;"><strong>Supplier Name:</strong></td>
                <td><?php echo htmlspecialchars($item['supplier_name']); ?></td>
            </tr>
            <?php if (!empty($item['supplier_phone'])): ?>
            <tr>
                <td><strong>Phone:</strong></td>
                <td><?php echo htmlspecialchars($item['supplier_phone']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($item['supplier_email'])): ?>
            <tr>
                <td><strong>Email:</strong></td>
                <td><?php echo htmlspecialchars($item['supplier_email']); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Expiry Information -->
<?php if (!empty($item['expiry_date'])): ?>
<div class="row mt-3">
    <div class="col-12">
        <h5 class="text-primary mb-3"><i class="fas fa-calendar-times"></i> Expiry Information</h5>
        <table class="table table-sm table-borderless">
            <tr>
                <td style="width: 150px;"><strong>Expiry Date:</strong></td>
                <td><?php echo htmlspecialchars($item['expiry_date']); ?></td>
            </tr>
            <tr>
                <td><strong>Expiry Status:</strong></td>
                <td>
                    <span class="<?php echo $expiryClass; ?> font-weight-bold">
                        <?php echo $expiryStatus; ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Current Stock Requests -->
<?php if (count($currentRequests) > 0): ?>
<div class="row mt-3">
    <div class="col-12">
        <h5 class="text-primary mb-3"><i class="fas fa-clipboard-list"></i> Current Stock Requests</h5>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead class="thead-light">
                    <tr>
                        <th>Date</th>
                        <th>Requested By</th>
                        <th>Quantity</th>
                        <th>Purpose</th>
                        <th>Priority</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currentRequests as $request): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($request['requested_date'])); ?></td>
                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                            <td><?php echo $request['requested_quantity'] . ' ' . $item['unit_of_measure']; ?></td>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Usage History -->
<?php if (count($recentUsage) > 0): ?>
<div class="row mt-3">
    <div class="col-12">
        <h5 class="text-primary mb-3"><i class="fas fa-history"></i> Recent Usage History</h5>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead class="thead-light">
                    <tr>
                        <th>Date</th>
                        <th>Quantity</th>
                        <th>Purpose</th>
                        <th>Assigned To</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentUsage as $usage): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($usage['usage_date'])); ?></td>
                            <td><?php echo $usage['quantity'] . ' ' . $item['unit_of_measure']; ?></td>
                            <td><?php echo htmlspecialchars($usage['purpose'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($usage['assigned_to'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($usage['created_by_name'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Item Timestamps -->
<div class="row mt-3">
    <div class="col-12">
        <h5 class="text-primary mb-3"><i class="fas fa-clock"></i> Record Information</h5>
        <table class="table table-sm table-borderless">
            <tr>
                <td style="width: 150px;"><strong>Created:</strong></td>
                <td><?php echo date('Y-m-d H:i:s', strtotime($item['created_at'])); ?></td>
            </tr>
            <?php if (!empty($item['updated_at'])): ?>
            <tr>
                <td><strong>Last Updated:</strong></td>
                <td><?php echo date('Y-m-d H:i:s', strtotime($item['updated_at'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Action Buttons -->
<div class="row mt-4">
    <div class="col-12">
        <div class="d-flex justify-content-end">
            <button type="button" class="btn btn-primary mr-2" 
                    onclick="$('#itemDetailsModal').modal('hide'); requestItem(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['item_name'])); ?>', '<?php echo $item['unit_of_measure']; ?>')">
                <i class="fas fa-plus"></i> Request Stock
            </button>
            <?php if ($item['current_quantity'] > 0): ?>
            <button type="button" class="btn btn-success" 
                    onclick="$('#itemDetailsModal').modal('hide'); recordUsage(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['item_name'])); ?>', <?php echo $item['current_quantity']; ?>, '<?php echo $item['unit_of_measure']; ?>')">
                <i class="fas fa-minus"></i> Record Usage
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .table-borderless td {
        border: none;
        padding: 0.25rem 0.5rem;
    }
    
    .table-borderless td:first-child {
        padding-left: 0;
    }
    
    .text-primary {
        color: #007bff !important;
    }
    
    .badge {
        font-size: 0.75em;
    }
    
    .table-responsive {
        border-radius: 0.25rem;
    }
    
    .thead-light th {
        background-color: #e9ecef;
        border-color: #dee2e6;
        font-weight: 600;
        font-size: 0.875rem;
    }
    
    .table-sm td, .table-sm th {
        padding: 0.3rem;
    }
    
    .font-weight-bold {
        font-weight: 700 !important;
    }
    
    h5 {
        border-bottom: 2px solid #007bff;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .row {
        margin-bottom: 0.5rem;
    }
    
    .alert {
        border-radius: 0.25rem;
        margin-bottom: 1rem;
    }
</style>