<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$error = '';
$success = '';
$purchase = null;
$purchase_items = [];
$delivery_tracking = [];

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_deliveries.php');
    exit;
}

$purchase_id = intval($_GET['id']);

// Fetch purchase details
try {
    // Simplified query to debug the issue
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            s.name as supplier_name,
            s.phone as supplier_phone,
            s.email as supplier_email,
            s.address as supplier_address
        FROM purchases p
        JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.id = ?
    ");
    $stmt->execute([$purchase_id]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$purchase) {
        $error = "Purchase order not found. ID: " . $purchase_id;
    }
} catch(PDOException $e) {
    error_log("Error fetching purchase details: " . $e->getMessage());
    $error = "Failed to load purchase details: " . $e->getMessage();
}

// If purchase was found, fetch related data
if ($purchase) {
    error_log("Successfully retrieved purchase with ID: " . $purchase_id);
    
    // Dump database structure info to logs
    try {
        // Check inventory_items table structure
        $stmt = $pdo->query("DESCRIBE inventory_items");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("inventory_items table structure:");
        foreach ($columns as $column) {
            error_log("Column: " . $column['Field'] . " - Type: " . $column['Type']);
        }
        
        // Check purchase_items table structure
        $stmt = $pdo->query("DESCRIBE purchase_items");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("purchase_items table structure:");
        foreach ($columns as $column) {
            error_log("Column: " . $column['Field'] . " - Type: " . $column['Type']);
        }
        
        // List available inventory items for reference
        $stmt = $pdo->query("SELECT id, item_name, sku FROM inventory_items ORDER BY id ASC LIMIT 10");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Available inventory items:");
        foreach ($items as $item) {
            error_log("ID: " . $item['id'] . " - Name: " . $item['item_name'] . " - SKU: " . $item['sku']);
        }
        
        // Check the specific purchase items for this order
        $stmt = $pdo->prepare("SELECT * FROM purchase_items WHERE purchase_id = ?");
        $stmt->execute([$purchase_id]);
        $rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Raw purchase items data for purchase ID " . $purchase_id . ":");
        foreach ($rawItems as $item) {
            error_log("Item ID: " . $item['id'] . 
                " - Inventory Item ID: " . $item['inventory_item_id'] . 
                " - Quantity: " . $item['quantity'] . 
                " - Unit Price: " . $item['unit_price']);
        }
    } catch(PDOException $e) {
        error_log("Error in debugging queries: " . $e->getMessage());
    }
    
    // Fetch purchase items - FIXED QUERY
    try {
        // Modified query with explicit selection and debugging
        $stmt = $pdo->prepare("
            SELECT 
                pi.id,
                pi.purchase_id,
                pi.inventory_item_id,
                pi.quantity,
                pi.unit_price,
                pi.batch_number,
                pi.expiry_date,
                pi.notes,
                i.item_name,
                i.sku,
                COALESCE(c.name, 'Uncategorized') as category_name
            FROM purchase_items pi
            LEFT JOIN inventory_items i ON pi.inventory_item_id = i.id
            LEFT JOIN inventory_categories c ON i.category_id = c.id
            WHERE pi.purchase_id = ?
            ORDER BY pi.id ASC
        ");
        $stmt->execute([$purchase_id]);
        $purchase_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug logging to track retrieved items
        error_log("Retrieved " . count($purchase_items) . " items for purchase ID: " . $purchase_id);
        foreach ($purchase_items as $index => $item) {
            error_log("Item " . ($index + 1) . ": ID=" . $item['id'] . 
                      ", Name=" . ($item['item_name'] ?? 'Unknown') . 
                      ", Inventory Item ID=" . $item['inventory_item_id']);
        }
        
        // If no items are found, log a warning
        if (empty($purchase_items)) {
            error_log("Warning: No purchase items found for purchase ID: " . $purchase_id);
        }
    } catch(PDOException $e) {
        error_log("Error fetching purchase items: " . $e->getMessage());
        $error = "Failed to load purchase items. Please check the database structure.";
    }
    
    // Add this fallback to handle missing item names and ensure proper data display
    foreach ($purchase_items as &$item) {
        // Ensure item_name has a value
        if (empty($item['item_name'])) {
            // Try to get the item name directly from the database if the join failed
            try {
                $itemStmt = $pdo->prepare("SELECT item_name, sku FROM inventory_items WHERE id = ?");
                $itemStmt->execute([$item['inventory_item_id']]);
                $inventoryItem = $itemStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($inventoryItem) {
                    $item['item_name'] = $inventoryItem['item_name'];
                    $item['sku'] = $inventoryItem['sku'];
                    error_log("Retrieved missing item name from database: " . $item['item_name']);
                } else {
                    $item['item_name'] = 'Item #' . $item['inventory_item_id'];
                    error_log("Could not find item name in database for ID: " . $item['inventory_item_id']);
                }
            } catch(PDOException $e) {
                error_log("Error retrieving individual item details: " . $e->getMessage());
                $item['item_name'] = 'Item #' . $item['inventory_item_id'];
            }
        }
        
        // Ensure SKU has a value
        if (empty($item['sku'])) {
            $item['sku'] = 'N/A';
        }
    }
    
    // Fetch delivery tracking
    try {
        $stmt = $pdo->prepare("
            SELECT * 
            FROM delivery_tracking 
            WHERE purchase_id = ? 
            ORDER BY status_date DESC
        ");
        $stmt->execute([$purchase_id]);
        $delivery_tracking = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching delivery tracking: " . $e->getMessage());
        $error = "Failed to load delivery tracking information: " . $e->getMessage();
    }
}

// Calculate total cost
$total_cost = 0;
foreach ($purchase_items as $item) {
    $total_cost += $item['quantity'] * $item['unit_price'];
}

// Check for flash messages
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$pageTitle = 'Purchase Order Details';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2>
            <i class="fas fa-file-invoice"></i> 
            Purchase Order Details 
            <?php if ($purchase): ?>
                <small>#<?php echo $purchase['id']; ?></small>
            <?php endif; ?>
        </h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='manage_deliveries.php'">
                <i class="fas fa-arrow-left"></i> Back to Deliveries
            </button>
            <?php if ($purchase): ?>
                <button class="btn btn-primary update-delivery" data-id="<?php echo $purchase['id']; ?>">
                    <i class="fas fa-truck"></i> Update Status
                </button>
                <button class="btn btn-info" onclick="printPurchaseOrder()">
                    <i class="fas fa-print"></i> Print
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($purchase): ?>
        <div class="row">
            <!-- Purchase Order Details -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Order Information</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th width="35%">Purchase Order ID</th>
                                <td><?php echo $purchase['id']; ?></td>
                            </tr>
                            <tr>
                                <th>Reference Number</th>
                                <td><?php echo htmlspecialchars($purchase['reference_number'] ?? 'Not specified'); ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <?php 
                                        $statusClass = '';
                                        switch($purchase['status']) {
                                            case 'delivered':
                                                $statusClass = 'badge badge-success';
                                                break;
                                            case 'in_transit':
                                                $statusClass = 'badge badge-info';
                                                break;
                                            case 'pending':
                                                $statusClass = 'badge badge-warning';
                                                break;
                                            case 'delayed':
                                                $statusClass = 'badge badge-danger';
                                                break;
                                            default:
                                                $statusClass = 'badge badge-secondary';
                                        }
                                    ?>
                                    <span class="<?php echo $statusClass; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $purchase['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Purchase Date</th>
                                <td><?php echo date('d-m-Y', strtotime($purchase['purchase_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Expected Delivery Date</th>
                                <td>
                                    <?php echo $purchase['expected_delivery_date'] ? date('d-m-Y', strtotime($purchase['expected_delivery_date'])) : 'Not specified'; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Created By</th>
                                <td><?php echo isset($purchase['created_by']) ? 'User #' . $purchase['created_by'] : 'System'; ?></td>
                            </tr>
                            <tr>
                                <th>Created At</th>
                                <td><?php echo isset($purchase['created_at']) ? date('d-m-Y H:i:s', strtotime($purchase['created_at'])) : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Notes</th>
                                <td><?php echo nl2br(htmlspecialchars($purchase['notes'] ?? 'No notes')); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Supplier Information -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Supplier Information</h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th width="35%">Supplier Name</th>
                                <td><?php echo htmlspecialchars($purchase['supplier_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Phone</th>
                                <td><?php echo htmlspecialchars($purchase['supplier_phone'] ?? 'Not specified'); ?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?php echo htmlspecialchars($purchase['supplier_email'] ?? 'Not specified'); ?></td>
                            </tr>
                            <tr>
                                <th>Address</th>
                                <td><?php echo nl2br(htmlspecialchars($purchase['supplier_address'] ?? 'Not specified')); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        
        <!-- Purchased Items -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Purchased Items</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Batch Number</th>
                                <th>Expiry Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($purchase_items) > 0): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($purchase_items as $item): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                        <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>RM <?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td>RM <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($item['batch_number'] ?? 'Not specified'); ?></td>
                                        <td>
                                            <?php echo $item['expiry_date'] ? date('d-m-Y', strtotime($item['expiry_date'])) : 'Not specified'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-info font-weight-bold">
                                    <td colspan="6" class="text-right">Total:</td>
                                    <td>RM <?php echo number_format($total_cost, 2); ?></td>
                                    <td colspan="2"></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">No items found for this purchase order.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Delivery Tracking -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Delivery Tracking</h3>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php if (count($delivery_tracking) > 0): ?>
                        <?php foreach ($delivery_tracking as $tracking): ?>
                            <div class="timeline-item status-<?php echo $tracking['status_update']; ?>">
                                <div class="timeline-marker">
                                    <?php
                                        $iconClass = 'fa-circle';
                                        switch($tracking['status_update']) {
                                            case 'processing':
                                                $iconClass = 'fa-cog';
                                                break;
                                            case 'in_transit':
                                                $iconClass = 'fa-truck';
                                                break;
                                            case 'delivered':
                                                $iconClass = 'fa-check-circle';
                                                break;
                                            case 'delayed':
                                                $iconClass = 'fa-exclamation-triangle';
                                                break;
                                            case 'cancelled':
                                                $iconClass = 'fa-times-circle';
                                                break;
                                        }
                                    ?>
                                    <i class="fas <?php echo $iconClass; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <h4><?php echo ucfirst(str_replace('_', ' ', $tracking['status_update'])); ?></h4>
                                    <p>
                                        <small class="text-muted">
                                            <?php echo date('d-m-Y H:i:s', strtotime($tracking['status_date'])); ?>
                                        </small>
                                    </p>
                                    <?php if (!empty($tracking['notes'])): ?>
                                        <p><?php echo nl2br(htmlspecialchars($tracking['notes'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($tracking['tracking_number'])): ?>
                                        <p><strong>Tracking Number:</strong> <?php echo htmlspecialchars($tracking['tracking_number']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($tracking['carrier'])): ?>
                                        <p><strong>Carrier:</strong> <?php echo htmlspecialchars($tracking['carrier']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center">No tracking information available for this purchase order.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Update Delivery Status Modal -->
<div class="modal fade" id="updateDeliveryModal" tabindex="-1" role="dialog" aria-labelledby="updateDeliveryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateDeliveryModalLabel">Update Delivery Status</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="update_delivery_status.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="purchase_id" id="update_purchase_id" value="<?php echo $purchase_id; ?>">
                    <input type="hidden" name="redirect" value="details">
                    
                    <div class="form-group">
                        <label for="status">Status <span class="text-danger">*</span></label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="">Select Status</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="delayed">Delayed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="delivery_date_container">
                        <label for="delivery_date">Delivery Date</label>
                        <input type="date" class="form-control" id="delivery_date" name="delivery_date">
                        <small class="form-text text-muted">Required if status is "Delivered"</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="tracking_number">Tracking Number</label>
                        <input type="text" class="form-control" id="tracking_number" name="tracking_number">
                    </div>
                    
                    <div class="form-group">
                        <label for="carrier">Carrier/Shipping Company</label>
                        <input type="text" class="form-control" id="carrier" name="carrier">
                    </div>
                    
                    <div class="form-group">
                        <label for="update_notes">Notes</label>
                        <textarea class="form-control" id="update_notes" name="notes" rows="3"></textarea>
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

<style>
.timeline {
    position: relative;
    padding: 20px 0 20px 40px;
    margin-left: 20px;
}

.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    left: 15px;
    height: 100%;
    width: 2px;
    background: #e0e0e0;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    width: 30px;
    height: 30px;
    left: -55px;
    top: 5px;
    border-radius: 50%;
    background: #f8f9fa;
    border: 3px solid #007bff;
    text-align: center;
    line-height: 24px;
    color: #007bff;
    z-index: 100;
}

.timeline-content {
    background: #f8f9fa;
    border-radius: 5px;
    padding: 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    position: relative;
}

.timeline-content:before {
    content: '';
    position: absolute;
    left: -10px;
    top: 15px;
    width: 0;
    height: 0;
    border-top: 10px solid transparent;
    border-bottom: 10px solid transparent;
    border-right: 10px solid #f8f9fa;
}

.timeline-content h4 {
    margin-top: 0;
    color: #333;
    font-size: 1.1rem;
    font-weight: 600;
}

/* Status Colors */
.timeline-item.status-delivered .timeline-marker {
    border-color: #28a745;
    color: #28a745;
}

.timeline-item.status-processing .timeline-marker {
    border-color: #17a2b8;
    color: #17a2b8;
}

.timeline-item.status-in_transit .timeline-marker {
    border-color: #007bff;
    color: #007bff;
}

.timeline-item.status-pending .timeline-marker {
    border-color: #ffc107;
    color: #ffc107;
}

.timeline-item.status-delayed .timeline-marker {
    border-color: #dc3545;
    color: #dc3545;
}

.timeline-item.status-cancelled .timeline-marker {
    border-color: #6c757d;
    color: #6c757d;
}

@media print {
    .action-buttons, .update-delivery, .timeline-marker {
        display: none !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
    
    .timeline-content {
        background-color: #fff !important;
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .timeline:before {
        display: none;
    }
    
    .timeline-content:before {
        display: none;
    }
    
    /* Hide the debug information when printing */
    .bg-info {
        display: none !important;
    }
}
</style>

<script>
$(document).ready(function() {
    // Update delivery status
    $(document).on('click', '.update-delivery', function() {
        var purchaseId = $(this).data('id');
        $('#update_purchase_id').val(purchaseId);
        $('#updateDeliveryModal').modal('show');
    });

    // Show/hide delivery date based on status
    $('#status').change(function() {
        if ($(this).val() === 'delivered') {
            $('#delivery_date_container').show();
            $('#delivery_date').attr('required', true);
        } else {
            $('#delivery_date_container').hide();
            $('#delivery_date').attr('required', false);
        }
    });

    // Hide delivery date field initially
    $('#delivery_date_container').hide();
});

function printPurchaseOrder() {
    window.print();
}
</script>

<?php include 'includes/footer.php'; ?>