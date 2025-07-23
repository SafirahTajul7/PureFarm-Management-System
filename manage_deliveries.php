<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$error = '';
$success = '';
$suppliers = [];
$purchases = [];

// Fetch suppliers for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name ASC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching suppliers: " . $e->getMessage());
    $error = "Failed to load suppliers: " . $e->getMessage();
}

// Fetch inventory items for dropdown
try {
    $stmt = $pdo->query("SELECT id, item_name, sku, category_id as category FROM inventory_items WHERE is_active = 1 ORDER BY item_name ASC");
    $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching inventory items: " . $e->getMessage());
    $error = "Failed to load inventory items: " . $e->getMessage();
}

// Handle form submission for adding new purchase/delivery
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_purchase'])) {
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;
    $purchase_date = isset($_POST['purchase_date']) ? trim($_POST['purchase_date']) : null;
    $expected_delivery_date = isset($_POST['expected_delivery_date']) ? trim($_POST['expected_delivery_date']) : null;
    $reference_number = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : null;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
    
    // Validate input
    if ($supplier_id <= 0 || empty($purchase_date)) {
        $error = "Supplier and purchase date are required fields.";
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Create new purchase
            $stmt = $pdo->prepare("
                INSERT INTO purchases 
                (supplier_id, purchase_date, expected_delivery_date, reference_number, notes, status, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())
            ");
            $stmt->execute([$supplier_id, $purchase_date, $expected_delivery_date, $reference_number, $notes, $_SESSION['user_id']]);
            $purchase_id = $pdo->lastInsertId();
            
            // Process single purchase item
            $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
            $quantity = isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0;
            $unit_price = isset($_POST['unit_price']) ? floatval($_POST['unit_price']) : 0;
            $batch_number = isset($_POST['batch_number']) ? trim($_POST['batch_number']) : null;
            $expiry_date = isset($_POST['expiry_date']) ? trim($_POST['expiry_date']) : null;
            
            if ($item_id > 0 && $quantity > 0 && $unit_price > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO purchase_items 
                    (purchase_id, inventory_item_id, quantity, unit_price, batch_number, expiry_date) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $purchase_id, 
                    $item_id, 
                    $quantity, 
                    $unit_price, 
                    !empty($batch_number) ? $batch_number : null, 
                    !empty($expiry_date) ? $expiry_date : null
                ]);
            } else {
                throw new Exception("Invalid item details. Please provide valid item, quantity, and price.");
            }
            
            // Add entry to delivery tracking
            $stmt = $pdo->prepare("
                INSERT INTO delivery_tracking 
                (purchase_id, status_update, status_date, notes) 
                VALUES (?, 'processing', NOW(), 'Purchase order created')
            ");
            $stmt->execute([$purchase_id]);
            
            // Commit transaction
            $pdo->commit();
            
            $success = "Purchase order created successfully. Delivery tracking initiated.";
        } catch(Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log("Error creating purchase: " . $e->getMessage());
            $error = "An error occurred while creating the purchase order: " . $e->getMessage();
        }
    }
}

// Fetch recent purchases
try {
    $stmt = $pdo->query("
        SELECT 
            p.id, 
            p.purchase_date,
            p.expected_delivery_date,
            p.status,
            p.reference_number,
            s.name as supplier_name,
            COUNT(pi.id) as item_count,
            SUM(pi.quantity * pi.unit_price) as total_cost
        FROM purchases p
        JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
        GROUP BY p.id, p.purchase_date, p.expected_delivery_date, p.status, p.reference_number, s.name
        ORDER BY p.purchase_date DESC
        LIMIT 10
    ");
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching purchases: " . $e->getMessage());
    $error = "Failed to load recent purchases.";
}

$pageTitle = 'Manage Deliveries';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-truck"></i> Manage Deliveries</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" data-toggle="modal" data-target="#newPurchaseModal">
                <i class="fas fa-plus"></i> New Purchase Order
            </button>
            <button class="btn btn-secondary" onclick="location.href='supplier_management.php'">
                <i class="fas fa-handshake"></i> Manage Suppliers
            </button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
            <?php if (strpos($error, 'Failed to load inventory items') !== false): ?>
                <br>
                <small>Debug info: inventory_items table exists with <?php echo count($inventory_items ?? []); ?> active items.</small>
            <?php endif; ?>
            <?php if (strpos($error, 'Failed to load suppliers') !== false): ?>
                <br>
                <small>Debug info: suppliers table exists with <?php echo count($suppliers ?? []); ?> active suppliers.</small>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Recent Purchases -->
    <div class="card">
        <div class="card-header">
            <h3>Recent Purchase Orders</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="purchasesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Supplier</th>
                            <th>Purchase Date</th>
                            <th>Expected Delivery</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Total Cost</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchases as $purchase): ?>
                            <tr>
                                <td><?php echo $purchase['id']; ?></td>
                                <td><?php echo htmlspecialchars($purchase['supplier_name']); ?></td>
                                <td><?php echo date('d-m-Y', strtotime($purchase['purchase_date'])); ?></td>
                                <td>
                                    <?php echo $purchase['expected_delivery_date'] ? date('d-m-Y', strtotime($purchase['expected_delivery_date'])) : 'Not specified'; ?>
                                </td>
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
                                <td><?php echo $purchase['item_count']; ?></td>
                                <td>RM <?php echo number_format($purchase['total_cost'] ?? 0, 2); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="location.href='purchase_details.php?id=<?php echo $purchase['id']; ?>'">
                                        <i class="fas fa-eye"></i> Details
                                    </button>
                                    <button class="btn btn-sm btn-primary update-delivery" data-id="<?php echo $purchase['id']; ?>">
                                        <i class="fas fa-truck"></i> Update Status
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($purchases)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No purchase orders found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- New Purchase Order Modal -->
<div class="modal fade" id="newPurchaseModal" tabindex="-1" role="dialog" aria-labelledby="newPurchaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newPurchaseModalLabel">Create New Purchase Order</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="create_purchase" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                                <select class="form-control" id="supplier_id" name="supplier_id" required>
                                    <option value="">Select Supplier</option>
                                    <?php if (!empty($suppliers)): ?>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo $supplier['id']; ?>">
                                                <?php echo htmlspecialchars($supplier['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No suppliers found. Please add suppliers first.</option>
                                    <?php endif; ?>
                                </select>
                                <?php if (empty($suppliers)): ?>
                                    <small class="text-danger">Please add suppliers in the <a href="supplier_management.php">Supplier Management</a> section first.</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="purchase_date">Purchase Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="purchase_date" name="purchase_date" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="expected_delivery_date">Expected Delivery Date</label>
                                <input type="date" class="form-control" id="expected_delivery_date" name="expected_delivery_date">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="reference_number">Reference Number</label>
                                <input type="text" class="form-control" id="reference_number" name="reference_number" placeholder="PO-<?php echo date('Ymd'); ?>-001">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                    
                    <hr>
                    <h5>Purchase Item</h5>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="item_id">Item <span class="text-danger">*</span></label>
                                <select class="form-control" id="item_id" name="item_id" required>
                                    <option value="">Select Item</option>
                                    <?php if (!empty($inventory_items)): ?>
                                        <?php foreach ($inventory_items as $item): ?>
                                            <option value="<?php echo $item['id']; ?>">
                                                <?php echo htmlspecialchars($item['item_name']); ?> (<?php echo htmlspecialchars($item['sku']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No inventory items found.</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="quantity">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="quantity" name="quantity" min="1" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="unit_price">Unit Price <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="unit_price" name="unit_price" min="0.01" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="batch_number">Batch Number</label>
                                <input type="text" class="form-control" id="batch_number" name="batch_number">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="expiry_date">Expiry Date</label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" <?php echo empty($suppliers) ? 'disabled' : ''; ?>>Create Purchase Order</button>
                </div>
            </form>
        </div>
    </div>
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
                    <input type="hidden" name="purchase_id" id="update_purchase_id">
                    
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

<script>
$(document).ready(function() {
    console.log("Document ready - initializing deliveries management");

    // Initialize DataTable
    if ($.fn.DataTable) {
        $('#purchasesTable').DataTable({
            "pageLength": 10,
            "ordering": true,
            "order": [[2, 'desc']], // Sort by purchase date by default
            "responsive": true
        });
    } else {
        console.log("DataTable plugin not found");
    }

    // Update delivery status
    $(document).on('click', '.update-delivery', function() {
        console.log("Update delivery clicked");
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

    // Add click handler for New Purchase Order button
    $(document).on('click', '[data-target="#newPurchaseModal"]', function() {
        console.log("New Purchase Order button clicked");
        $('#newPurchaseModal').modal('show');
    });
});
</script>

<?php include 'includes/footer.php'; ?>