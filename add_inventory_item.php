<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$categories = [];
$suppliers = [];
$units = ['kg', 'g', 'l', 'ml', 'pieces', 'bags', 'bottles', 'boxes', 'packets'];
$name = '';
$sku = '';
$description = '';
$category_id = '';
$supplier_id = '';
$current_quantity = 0;
$unit_of_measure = '';
$reorder_level = 0;
$maximum_level = 0;
$unit_cost = 0;
$expiry_date = '';
$batch_number = '';

// Fetch categories for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM item_categories WHERE status = 'active' ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $errorMsg = "Failed to load categories. Please try again later.";
}

// Fetch suppliers for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name ASC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching suppliers: " . $e->getMessage());
    $errorMsg = "Failed to load suppliers. Please try again later.";
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate input
    $name = trim($_POST['item_name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $supplier_id = !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null;
    $current_quantity = $_POST['current_quantity'] ?? 0;
    $unit_of_measure = $_POST['unit_of_measure'] ?? '';
    $reorder_level = $_POST['reorder_level'] ?? 0;
    $maximum_level = $_POST['maximum_level'] ?? 0;
    $unit_cost = $_POST['unit_cost'] ?? 0;
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $batch_number = trim($_POST['batch_number'] ?? '');
    
    $errors = [];
    
    // Basic validation
    if (empty($name)) {
        $errors[] = "Item name is required";
    }
    
    if (empty($sku)) {
        $errors[] = "SKU is required";
    } else {
        // Check if SKU already exists
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_items WHERE sku = ? AND status = 'active'");
            $stmt->execute([$sku]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "SKU already exists. Please use a unique SKU.";
            }
        } catch(PDOException $e) {
            error_log("Error checking SKU: " . $e->getMessage());
            $errors[] = "An error occurred while validating SKU.";
        }
    }
    
    if (empty($category_id)) {
        $errors[] = "Category is required";
    }
    
    if ($current_quantity < 0) {
        $errors[] = "Quantity cannot be negative";
    }
    
    if (empty($unit_of_measure)) {
        $errors[] = "Unit of measure is required";
    }
    
    if ($reorder_level < 0) {
        $errors[] = "Reorder level cannot be negative";
    }
    
    if ($maximum_level < 0) {
        $errors[] = "Maximum level cannot be negative";
    }
    
    if ($maximum_level < $reorder_level && $maximum_level > 0) {
        $errors[] = "Maximum level should be greater than or equal to reorder level";
    }
    
    if ($unit_cost < 0) {
        $errors[] = "Purchase price cannot be negative";
    }
    
    // If no errors, proceed with adding the item
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO inventory_items (
                    item_name, sku, description, category_id, supplier_id, current_quantity, 
                    unit_of_measure, reorder_level, maximum_level, unit_cost, 
                    expiry_date, batch_number, created_at, status
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, 
                    ?, ?, NOW(), 'active'
                )
            ");
            
            $result = $stmt->execute([
                $name, $sku, $description, $category_id, $supplier_id, $current_quantity,
                $unit_of_measure, $reorder_level, $maximum_level, $unit_cost,
                $expiry_date, $batch_number
            ]);
            
            if ($result) {
                $newItemId = $pdo->lastInsertId();
                
                // Log inventory addition in inventory_log table
                if ($current_quantity > 0) {
                    try {
                        $logStmt = $pdo->prepare("
                            INSERT INTO inventory_log (
                                item_id, action_type, quantity, notes, batch_number, 
                                expiry_date, unit_cost, supplier_id, user_id, created_at
                            ) VALUES (
                                ?, 'initial_add', ?, 'Initial inventory addition', ?, 
                                ?, ?, ?, ?, NOW()
                            )
                        ");
                        
                        $logStmt->execute([
                            $newItemId, $current_quantity, $batch_number, 
                            $expiry_date, $unit_cost, $supplier_id, auth()->getUserId()
                        ]);
                    } catch(PDOException $e) {
                        error_log("Error logging inventory addition: " . $e->getMessage());
                        // Continue even if logging fails
                    }
                }
                
                // Commit transaction
                $pdo->commit();
                
                $successMsg = "Item successfully added to inventory.";
                
                // Clear form data
                $name = $sku = $description = $category_id = $supplier_id = $unit_of_measure = $batch_number = '';
                $current_quantity = $reorder_level = $maximum_level = $unit_cost = 0;
                $expiry_date = null;
            } else {
                // Rollback transaction
                $pdo->rollBack();
                $errorMsg = "Failed to add the item.";
            }
        } catch(PDOException $e) {
            // Rollback transaction
            $pdo->rollBack();
            error_log("Error adding inventory item: " . $e->getMessage());
            $errorMsg = "An error occurred while adding the item: " . $e->getMessage();
        }
    } else {
        $errorMsg = implode("<br>", $errors);
    }
}

$pageTitle = 'Add Inventory Item';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-plus"></i> Add New Inventory Item</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='item_details.php'">
                <i class="fas fa-arrow-left"></i> Back to Item Details
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
            <h5 class="mb-0">Item Information</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <!-- Basic Information -->
                    <div class="col-md-6">
                        <h5>Basic Details</h5>
                        <hr>
                        
                        <div class="form-group">
                            <label for="item_name">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="item_name" name="item_name" 
                                   value="<?php echo htmlspecialchars($name); ?>" required>
                            <small class="form-text text-muted">Enter a descriptive name for the item</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="sku">SKU (Stock Keeping Unit) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sku" name="sku" 
                                   value="<?php echo htmlspecialchars($sku); ?>" required>
                            <small class="form-text text-muted">Unique identifier for the item</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id">Category <span class="text-danger">*</span></label>
                            <select class="form-control" id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo ($category_id == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Select the category this item belongs to</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="supplier_id">Supplier</label>
                            <select class="form-control" id="supplier_id" name="supplier_id">
                                <option value="">Select Supplier (Optional)</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo htmlspecialchars($supplier['id']); ?>" <?php echo ($supplier_id == $supplier['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($supplier['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Select the supplier for this item</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                            <small class="form-text text-muted">Detailed description of the item</small>
                        </div>
                    </div>
                    
                    <!-- Inventory Information -->
                    <div class="col-md-6">
                        <h5>Inventory Details</h5>
                        <hr>
                        
                        <div class="form-group">
                            <label for="current_quantity">Initial Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="current_quantity" name="current_quantity" 
                                   value="<?php echo htmlspecialchars($current_quantity); ?>" min="0" required>
                            <small class="form-text text-muted">Initial stock quantity</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="unit_of_measure">Unit of Measure <span class="text-danger">*</span></label>
                            <select class="form-control" id="unit_of_measure" name="unit_of_measure" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?php echo htmlspecialchars($unit); ?>" <?php echo ($unit_of_measure == $unit) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($unit); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Unit used to measure this item</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="reorder_level">Reorder Level <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="reorder_level" name="reorder_level" 
                                   value="<?php echo htmlspecialchars($reorder_level); ?>" min="0" required>
                            <small class="form-text text-muted">Minimum stock level before reordering</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="maximum_level">Maximum Level</label>
                            <input type="number" class="form-control" id="maximum_level" name="maximum_level" 
                                   value="<?php echo htmlspecialchars($maximum_level); ?>" min="0">
                            <small class="form-text text-muted">Maximum stock level (to prevent overstocking)</small>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <!-- Additional Information -->
                    <div class="col-md-6">
                        <h5>Procurement Details</h5>
                        <hr>
                        
                        <div class="form-group">
                            <label for="unit_cost">Purchase Price (RM)</label>
                            <input type="number" step="0.01" class="form-control" id="unit_cost" name="unit_cost" 
                                   value="<?php echo htmlspecialchars($unit_cost); ?>" min="0">
                            <small class="form-text text-muted">Cost per unit</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>Quality Control</h5>
                        <hr>
                        
                        <div class="form-group">
                            <label for="batch_number">Batch Number</label>
                            <input type="text" class="form-control" id="batch_number" name="batch_number" 
                                   value="<?php echo htmlspecialchars($batch_number); ?>">
                            <small class="form-text text-muted">Batch or lot number for traceability</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="expiry_date">Expiry Date</label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                   value="<?php echo htmlspecialchars($expiry_date); ?>">
                            <small class="form-text text-muted">Expiry date (if applicable)</small>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save Item
                    </button>
                    <button type="reset" class="btn btn-secondary btn-lg">
                        <i class="fas fa-undo"></i> Reset Form
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Custom validation for maximum level vs reorder level
    document.getElementById('maximum_level').addEventListener('change', function() {
        var reorderLevel = parseFloat(document.getElementById('reorder_level').value) || 0;
        var maximumLevel = parseFloat(this.value) || 0;
        
        if (maximumLevel < reorderLevel && maximumLevel > 0) {
            alert('Maximum level should be greater than or equal to reorder level');
            this.value = reorderLevel;
        }
    });
    
    // Auto-generate SKU based on item name and category
    document.getElementById('item_name').addEventListener('blur', function() {
        var skuField = document.getElementById('sku');
        var categoryField = document.getElementById('category_id');
        
        // Only auto-generate if SKU field is empty
        if (skuField.value === '') {
            var itemName = this.value.trim();
            var categoryId = categoryField.value;
            
            if (itemName && categoryId) {
                // Create SKU from first 3 chars of name + category ID + timestamp
                var namePrefix = itemName.substring(0, 3).toUpperCase();
                var timestamp = new Date().getTime().toString().substring(9, 13);
                skuField.value = namePrefix + '-' + categoryId + '-' + timestamp;
            }
        }
    });
</script>

<style>
    .main-content {
        padding-bottom: 60px; /* Space for footer */
    }
    
    .card {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }
    
    .card-header h5 {
        font-weight: 600;
    }
    
    hr {
        margin-top: 0.5rem;
        margin-bottom: 1.5rem;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    .form-group label {
        font-weight: 500;
    }
    
    .text-danger {
        color: #dc3545;
    }

    /* Hide PHP warnings in dropdown menus */
    select option[title^="Warning:"] {
        display: none;
    }
</style>

<?php include 'includes/footer.php'; ?>