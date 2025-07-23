<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$batchId = isset($_GET['id']) ? $_GET['id'] : null;
$batch = null;
$items = [];
$suppliers = [];

// Validate batch ID
if (!$batchId) {
    header('Location: batch_tracking.php');
    exit;
}

// Fetch batch details
try {
    $stmt = $pdo->prepare("
        SELECT b.*, i.item_name, i.sku
        FROM inventory_batches b
        JOIN inventory_items i ON b.item_id = i.id
        WHERE b.id = ?
    ");
    $stmt->execute([$batchId]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$batch) {
        header('Location: batch_tracking.php');
        exit;
    }
} catch(PDOException $e) {
    error_log("Error fetching batch: " . $e->getMessage());
    $errorMsg = "Failed to load batch details.";
}

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_batch'])) {
    $batchNumber = $_POST['batch_number'] ?? '';
    $manufacturingDate = $_POST['manufacturing_date'] ?? null;
    $expiryDate = $_POST['expiry_date'] ?? null;
    $receivedDate = $_POST['received_date'] ?? date('Y-m-d');
    $supplierId = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null;
    $purchaseOrderId = !empty($_POST['purchase_order_id']) ? $_POST['purchase_order_id'] : null;
    $costPerUnit = $_POST['cost_per_unit'] ?? null;
    $notes = $_POST['notes'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    // Input validation
    $errors = [];
    
    if (empty($batchNumber)) {
        $errors[] = "Batch number is required.";
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
    
    // Check if batch number already exists (for a different batch)
    try {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_batches WHERE batch_number = ? AND id != ?");
        $checkStmt->execute([$batchNumber, $batchId]);
        $batchExists = $checkStmt->fetchColumn() > 0;
        
        if ($batchExists) {
            $errors[] = "Batch number already exists. Please use a different batch number.";
        }
    } catch(PDOException $e) {
        error_log("Error checking batch number: " . $e->getMessage());
        $errors[] = "Failed to validate batch number.";
    }
    
    // Process if no errors
    if (empty($errors)) {
        try {
            // Update batch
            $stmt = $pdo->prepare("
                UPDATE inventory_batches 
                SET batch_number = ?, 
                    manufacturing_date = ?, 
                    expiry_date = ?, 
                    received_date = ?, 
                    supplier_id = ?, 
                    purchase_order_id = ?, 
                    cost_per_unit = ?, 
                    notes = ?, 
                    status = ?
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $batchNumber, 
                $manufacturingDate, 
                $expiryDate, 
                $receivedDate,
                $supplierId, 
                $purchaseOrderId, 
                $costPerUnit, 
                $notes, 
                $status,
                $batchId
            ]);
            
            if ($result) {
                // If status changed to expired or discarded, add a note to the batch
                if (($status === 'expired' || $status === 'discarded') && $batch['status'] !== $status) {
                    $updateNotesStmt = $pdo->prepare("
                        UPDATE inventory_batches 
                        SET notes = CONCAT(IFNULL(notes, ''), '\n', NOW(), ' - Status changed to ', ?)
                        WHERE id = ?
                    ");
                    $updateNotesStmt->execute([$status, $batchId]);
                }
                
                $successMsg = "Batch has been updated successfully.";
                
                // Refresh batch data
                $stmt = $pdo->prepare("
                    SELECT b.*, i.item_name, i.sku
                    FROM inventory_batches b
                    JOIN inventory_items i ON b.item_id = i.id
                    WHERE b.id = ?
                ");
                $stmt->execute([$batchId]);
                $batch = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $errorMsg = "Failed to update batch.";
            }
        } catch(PDOException $e) {
            error_log("Error updating batch: " . $e->getMessage());
            $errorMsg = "An error occurred while updating the batch.";
        }
    } else {
        $errorMsg = implode("<br>", $errors);
    }
}

$pageTitle = 'Edit Batch';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-edit"></i> Edit Batch</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='view_batch.php?id=<?php echo $batchId; ?>'">
                <i class="fas fa-arrow-left"></i> Back to Batch Details
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
            <h5 class="mb-0">Edit Batch Details</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="batch_number">Batch Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="batch_number" name="batch_number" required 
                               value="<?php echo htmlspecialchars($batch['batch_number']); ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="item_id">Item</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($batch['item_name'] . ' (' . $batch['sku'] . ')'); ?>" readonly>
                        <small class="form-text text-muted">Item cannot be changed after batch creation.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="manufacturing_date">Manufacturing Date</label>
                        <input type="date" class="form-control" id="manufacturing_date" name="manufacturing_date" 
                               value="<?php echo htmlspecialchars($batch['manufacturing_date'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="expiry_date">Expiry Date</label>
                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                               value="<?php echo htmlspecialchars($batch['expiry_date'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="received_date">Received Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="received_date" name="received_date" required 
                               value="<?php echo htmlspecialchars($batch['received_date']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="supplier_id">Supplier</label>
                        <select class="form-control" id="supplier_id" name="supplier_id">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>" <?php echo ($batch['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active" <?php echo ($batch['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="quarantine" <?php echo ($batch['status'] == 'quarantine') ? 'selected' : ''; ?>>Quarantine</option>
                            <option value="consumed" <?php echo ($batch['status'] == 'consumed') ? 'selected' : ''; ?>>Consumed</option>
                            <option value="expired" <?php echo ($batch['status'] == 'expired') ? 'selected' : ''; ?>>Expired</option>
                            <option value="discarded" <?php echo ($batch['status'] == 'discarded') ? 'selected' : ''; ?>>Discarded</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="purchase_order_id">Purchase Order ID</label>
                        <input type="text" class="form-control" id="purchase_order_id" name="purchase_order_id" 
                               value="<?php echo htmlspecialchars($batch['purchase_order_id'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="cost_per_unit">Cost Per Unit</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                            </div>
                            <input type="number" step="0.01" min="0" class="form-control" id="cost_per_unit" name="cost_per_unit" 
                                   value="<?php echo htmlspecialchars($batch['cost_per_unit'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($batch['notes'] ?? ''); ?></textarea>
                    <small class="form-text text-muted">Previous notes will be preserved.</small>
                </div>

                <input type="hidden" name="update_batch" value="1">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Batch
                </button>
                <a href="view_batch.php?id=<?php echo $batchId; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
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