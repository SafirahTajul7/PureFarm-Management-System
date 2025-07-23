<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$items = [];
$suppliers = [];

// Fetch active inventory items
try {
    $stmt = $pdo->query("SELECT id, item_name, sku FROM inventory_items WHERE status = 'active' AND batch_tracking_enabled = 1 ORDER BY item_name ASC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching inventory items: " . $e->getMessage());
    $errorMsg = "Failed to load inventory items.";
}

// Fetch active suppliers
try {
    $stmt = $pdo->query("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name ASC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching suppliers: " . $e->getMessage());
    $errorMsg = "Failed to load suppliers.";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_batch'])) {
    $batchNumber = $_POST['batch_number'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $manufacturingDate = $_POST['manufacturing_date'] ?? null;
    $expiryDate = $_POST['expiry_date'] ?? null;
    $receivedDate = $_POST['received_date'] ?? date('Y-m-d');
    $supplierId = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null;
    $purchaseOrderId = !empty($_POST['purchase_order_id']) ? $_POST['purchase_order_id'] : null;
    $costPerUnit = $_POST['cost_per_unit'] ?? null;
    $notes = $_POST['notes'] ?? '';
    
    // Input validation
    $errors = [];
    
    if (empty($batchNumber)) {
        $errors[] = "Batch number is required.";
    }
    
    if (empty($itemId)) {
        $errors[] = "Item selection is required.";
    }
    
    if (empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
        $errors[] = "Valid quantity is required.";
    }
    
    if (empty($receivedDate)) {
        $errors[] = "Received date is required.";
    }
    
    if (!empty($expiryDate) && !empty($manufacturingDate)) {
        if (strtotime($expiryDate) <= strtotime($manufacturingDate)) {
            $errors[] = "Expiry date must be after manufacturing date.";
        }
    }
    
    if (!empty($expiryDate) && strtotime($expiryDate) <= strtotime($receivedDate)) {
        $errors[] = "Expiry date must be after received date.";
    }
    
    // Process if no errors
    if (empty($errors)) {
        try {
            // Check if batch number already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_batches WHERE batch_number = ?");
            $checkStmt->execute([$batchNumber]);
            $batchExists = $checkStmt->fetchColumn() > 0;
            
            if ($batchExists) {
                $errorMsg = "Batch number already exists. Please use a different batch number.";
            } else {
                // Insert new batch
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_batches 
                    (batch_number, item_id, quantity, manufacturing_date, expiry_date, received_date, 
                    supplier_id, purchase_order_id, cost_per_unit, notes, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                
                $result = $stmt->execute([
                    $batchNumber, $itemId, $quantity, $manufacturingDate, $expiryDate, $receivedDate,
                    $supplierId, $purchaseOrderId, $costPerUnit, $notes
                ]);
                
                if ($result) {
                    $batchId = $pdo->lastInsertId();
                    
                    // Update inventory item quantity
                    $updateStmt = $pdo->prepare("
                        UPDATE inventory_items
                        SET current_quantity = current_quantity + ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$quantity, $itemId]);
                    
                    $successMsg = "Batch has been added successfully. <a href='view_batch.php?id={$batchId}'>View Batch</a>";
                    
                    // Reset form values on success
                    $batchNumber = '';
                    $itemId = '';
                    $quantity = '';
                    $manufacturingDate = null;
                    $expiryDate = null;
                    $receivedDate = date('Y-m-d');
                    $supplierId = null;
                    $purchaseOrderId = null;
                    $costPerUnit = null;
                    $notes = '';
                } else {
                    $errorMsg = "Failed to add batch.";
                }
            }
        } catch(PDOException $e) {
            error_log("Error adding batch: " . $e->getMessage());
            $errorMsg = "An error occurred while adding the batch.";
        }
    } else {
        $errorMsg = implode("<br>", $errors);
    }
}

$pageTitle = 'Add New Batch';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-plus-circle"></i> Add New Batch</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='batch_tracking.php'">
                <i class="fas fa-arrow-left"></i> Back to Batches
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

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Batch Details</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="batch_number">Batch Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="batch_number" name="batch_number" required 
                               value="<?php echo htmlspecialchars($batchNumber ?? ''); ?>">
                        <small class="form-text text-muted">Enter a unique identifier for this batch.</small>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="item_id">Item <span class="text-danger">*</span></label>
                        <select class="form-control" id="item_id" name="item_id" required>
                            <option value="">Select Item</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item['id']; ?>" <?php echo (isset($itemId) && $itemId == $item['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($item['item_name']); ?> (<?php echo htmlspecialchars($item['sku']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Select the inventory item this batch belongs to.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="quantity">Quantity <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="quantity" name="quantity" required 
                               value="<?php echo htmlspecialchars($quantity ?? ''); ?>">
                        <small class="form-text text-muted">Enter the quantity received in this batch.</small>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="supplier_id">Supplier</label>
                        <select class="form-control" id="supplier_id" name="supplier_id">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>" <?php echo (isset($supplierId) && $supplierId == $supplier['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Select the supplier of this batch (optional).</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="manufacturing_date">Manufacturing Date</label>
                        <input type="date" class="form-control" id="manufacturing_date" name="manufacturing_date" 
                               value="<?php echo htmlspecialchars($manufacturingDate ?? ''); ?>">
                        <small class="form-text text-muted">When was this batch manufactured (optional).</small>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="expiry_date">Expiry Date</label>
                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                               value="<?php echo htmlspecialchars($expiryDate ?? ''); ?>">
                        <small class="form-text text-muted">When will this batch expire (optional).</small>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="received_date">Received Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="received_date" name="received_date" required 
                               value="<?php echo htmlspecialchars($receivedDate ?? date('Y-m-d')); ?>">
                        <small class="form-text text-muted">When was this batch received.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="purchase_order_id">Purchase Order ID</label>
                        <input type="text" class="form-control" id="purchase_order_id" name="purchase_order_id" 
                               value="<?php echo htmlspecialchars($purchaseOrderId ?? ''); ?>">
                        <small class="form-text text-muted">Reference to purchase order (optional).</small>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="cost_per_unit">Cost Per Unit</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                            </div>
                            <input type="number" step="0.01" min="0" class="form-control" id="cost_per_unit" name="cost_per_unit" 
                                   value="<?php echo htmlspecialchars($costPerUnit ?? ''); ?>">
                        </div>
                        <small class="form-text text-muted">Cost per unit for this batch (optional).</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
                    <small class="form-text text-muted">Any additional information about this batch (optional).</small>
                </div>

                <button type="submit" name="submit_batch" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Batch
                </button>
                <button type="reset" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // Date validation
    document.addEventListener('DOMContentLoaded', function() {
        const manufacturingDateInput = document.getElementById('manufacturing_date');
        const expiryDateInput = document.getElementById('expiry_date');
        const receivedDateInput = document.getElementById('received_date');
        
        // Validate expiry date is after manufacturing date
        expiryDateInput.addEventListener('change', function() {
            if (manufacturingDateInput.value && expiryDateInput.value) {
                if (new Date(expiryDateInput.value) <= new Date(manufacturingDateInput.value)) {
                    alert('Expiry date must be after manufacturing date');
                    expiryDateInput.value = '';
                }
            }
            
            if (receivedDateInput.value && expiryDateInput.value) {
                if (new Date(expiryDateInput.value) <= new Date(receivedDateInput.value)) {
                    alert('Expiry date must be after received date');
                    expiryDateInput.value = '';
                }
            }
        });
        
        // Validate manufacturing date is before received date
        manufacturingDateInput.addEventListener('change', function() {
            if (manufacturingDateInput.value && receivedDateInput.value) {
                if (new Date(manufacturingDateInput.value) > new Date(receivedDateInput.value)) {
                    alert('Manufacturing date must be before or on received date');
                    manufacturingDateInput.value = '';
                }
            }
            
            if (manufacturingDateInput.value && expiryDateInput.value) {
                if (new Date(expiryDateInput.value) <= new Date(manufacturingDateInput.value)) {
                    alert('Expiry date must be after manufacturing date');
                    expiryDateInput.value = '';
                }
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>