<?php
require_once 'includes/auth.php';
auth()->checkAuthenticated();

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$item = [];

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stock'])) {
    $quantity = isset($_POST['quantity']) ? filter_var($_POST['quantity'], FILTER_VALIDATE_FLOAT) : false;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $unitCost = isset($_POST['unit_cost']) ? filter_var($_POST['unit_cost'], FILTER_VALIDATE_FLOAT) : $item['unit_cost'];
    $supplierId = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
    
    // Validate inputs
    if ($quantity === false || $quantity <= 0) {
        $errorMsg = "Please enter a valid quantity greater than zero.";
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update item quantity and unit cost
            $updateSQL = "UPDATE inventory_items SET 
                         current_quantity = current_quantity + ?,
                         unit_cost = ?,
                         updated_at = NOW()
                         WHERE id = ?";
            
            $updateStmt = $pdo->prepare($updateSQL);
            $updateStmt->execute([
                $quantity, 
                $unitCost, 
                $itemId
            ]);
            
            // Get user ID safely with fallback
            $userId = auth()->getUserId();
            if (!$userId) {
                $userId = 1; // Assuming admin has ID 1
            }
            
            // Log the stock addition - using only the columns we know exist
            $logStmt = $pdo->prepare("
                INSERT INTO inventory_log 
                (item_id, action_type, quantity, notes, user_id, created_at)
                VALUES (?, 'manual_add', ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $itemId, 
                $quantity, 
                $notes, 
                $userId
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            $successMsg = "Successfully added $quantity {$item['unit_of_measure']} of {$item['item_name']} to inventory.";
            
            // Refresh item data
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(Exception $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error adding stock: " . $e->getMessage());
            $errorMsg = "Failed to add stock: " . $e->getMessage();
        }
    }
}

// Fetch all active suppliers for dropdown
$suppliers = [];
try {
    $supplierStmt = $pdo->query("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name ASC");
    $suppliers = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching suppliers: " . $e->getMessage());
}

$pageTitle = 'Add Stock';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-plus-circle"></i> Add Stock: <?php echo htmlspecialchars($item['item_name']); ?></h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='view_item.php?id=<?php echo $itemId; ?>'">
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

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Add Stock Form</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="quantity">Quantity to Add</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="quantity" name="quantity" 
                                           min="0.01" step="0.01" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text"><?php echo htmlspecialchars($item['unit_of_measure']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="unit_cost">Unit Cost (RM)</label>
                                <input type="number" class="form-control" id="unit_cost" name="unit_cost" 
                                       min="0.01" step="0.01" value="<?php echo $item['unit_cost']; ?>">
                                <small class="form-text text-muted">Leave unchanged if the cost remains the same</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="supplier_id">Supplier</label>
                            <select class="form-control" id="supplier_id" name="supplier_id">
                                <option value="">-- Select Supplier --</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>" 
                                            <?php echo ($item['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($supplier['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Enter any additional information about this stock addition"></textarea>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" name="add_stock" class="btn btn-success btn-lg">
                                <i class="fas fa-plus-circle"></i> Add Stock
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Current Inventory Status</h5>
                </div>
                <div class="card-body">
                    <div class="inventory-info">
                        <p><strong>Item:</strong> <?php echo htmlspecialchars($item['item_name']); ?></p>
                        <p><strong>Category:</strong> <?php echo htmlspecialchars($item['category_name']); ?></p>
                        <p><strong>SKU:</strong> <?php echo htmlspecialchars($item['sku']); ?></p>
                        <p><strong>Current Quantity:</strong> 
                            <span class="<?php echo ($item['current_quantity'] <= $item['reorder_level']) ? 'text-warning' : ''; ?>">
                                <?php echo $item['current_quantity']; ?> <?php echo htmlspecialchars($item['unit_of_measure']); ?>
                            </span>
                        </p>
                        <p><strong>Reorder Level:</strong> <?php echo $item['reorder_level']; ?> <?php echo htmlspecialchars($item['unit_of_measure']); ?></p>
                        <p><strong>Maximum Level:</strong> 
                            <?php echo ($item['maximum_level'] > 0) ? $item['maximum_level'] . ' ' . htmlspecialchars($item['unit_of_measure']) : 'Not set'; ?>
                        </p>
                        
                        <?php if ($item['maximum_level'] > 0): ?>
                            <div class="stock-progress-container mt-3">
                                <label>Stock Level</label>
                                <?php 
                                    $stockPercentage = min(100, ($item['current_quantity'] / $item['maximum_level']) * 100);
                                    $progressClass = 'bg-success';
                                    
                                    if ($stockPercentage <= 25) {
                                        $progressClass = 'bg-danger';
                                    } elseif ($stockPercentage <= 50) {
                                        $progressClass = 'bg-warning';
                                    }
                                ?>
                                <div class="progress">
                                    <div class="progress-bar <?php echo $progressClass; ?>" role="progressbar" 
                                         style="width: <?php echo $stockPercentage; ?>%" 
                                         aria-valuenow="<?php echo $stockPercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?php echo round($stockPercentage); ?>%
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $item['current_quantity']; ?> of <?php echo $item['maximum_level']; ?> <?php echo htmlspecialchars($item['unit_of_measure']); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Guidelines for Adding Stock -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Guidelines</h5>
                </div>
                <div class="card-body">
                    <ul class="guidelines-list">
                        <li>Enter the exact quantity being added to inventory</li>
                        <li>Update unit cost if the purchase price has changed</li>
                        <li>Add notes for additional context (e.g., "Purchased for special order")</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .guidelines-list {
        padding-left: 20px;
    }
    
    .guidelines-list li {
        margin-bottom: 8px;
    }
    
    .inventory-info p {
        margin-bottom: 8px;
    }
    
    .form-group label {
        font-weight: 500;
    }
</style>

<?php include 'includes/footer.php'; ?>