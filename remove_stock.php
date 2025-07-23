<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_stock'])) {
    $quantity = isset($_POST['quantity']) ? filter_var($_POST['quantity'], FILTER_VALIDATE_FLOAT) : false;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Validate inputs
    if ($quantity === false || $quantity <= 0) {
        $errorMsg = "Please enter a valid quantity greater than zero.";
    } elseif ($quantity > $item['current_quantity']) {
        $errorMsg = "Cannot remove more than the current quantity ({$item['current_quantity']} {$item['unit_of_measure']}).";
    } elseif (empty($reason)) {
        $errorMsg = "Please select a reason for removing stock.";
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update item quantity
            $updateStmt = $pdo->prepare("
                UPDATE inventory_items 
                SET current_quantity = current_quantity - ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$quantity, $itemId]);
            
            // Log the stock removal
            $userId = auth()->user()->id;
            $actionType = '';
            
            switch ($reason) {
                case 'sale':
                    $actionType = 'sale';
                    break;
                case 'waste':
                    $actionType = 'waste';
                    break;
                case 'adjustment':
                    $actionType = 'manual_remove';
                    break;
                case 'return':
                    $actionType = 'return';
                    break;
                default:
                    $actionType = 'manual_remove';
            }
            
            $logStmt = $pdo->prepare("
                INSERT INTO inventory_log 
                (item_id, action_type, quantity, notes, user_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $itemId, 
                $actionType, 
                $quantity, 
                $notes, 
                $userId
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            $successMsg = "Successfully removed $quantity {$item['unit_of_measure']} of {$item['item_name']} from inventory.";
            
            // Refresh item data
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log("Error removing stock: " . $e->getMessage());
            $errorMsg = "Failed to remove stock. Please try again later.";
        }
    }
}

$pageTitle = 'Remove Stock';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-minus-circle"></i> Remove Stock: <?php echo htmlspecialchars($item['item_name']); ?></h2>
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
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Remove Stock Form</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="quantity">Quantity to Remove</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="quantity" name="quantity" 
                                           min="0.01" step="0.01" max="<?php echo $item['current_quantity']; ?>" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text"><?php echo htmlspecialchars($item['unit_of_measure']); ?></span>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Maximum: <?php echo $item['current_quantity']; ?> <?php echo htmlspecialchars($item['unit_of_measure']); ?></small>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="reason">Reason for Removal</label>
                                <select class="form-control" id="reason" name="reason" required>
                                    <option value="">-- Select Reason --</option>
                                    <option value="sale">Sale/Usage</option>
                                    <option value="waste">Damaged/Expired</option>
                                    <option value="return">Return to Supplier</option>
                                    <option value="adjustment">Inventory Adjustment</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Enter any additional information about this stock removal"></textarea>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" name="remove_stock" class="btn btn-warning btn-lg">
                                <i class="fas fa-minus-circle"></i> Remove Stock
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
                        
                        <?php if ($item['current_quantity'] <= $item['reorder_level']): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i> This item is already at or below the reorder level.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Reasons for Stock Removal -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Stock Removal Guidelines</h5>
                </div>
                <div class="card-body">
                    <ul class="guidelines-list">
                        <li><strong>Sale/Usage:</strong> Regular consumption in farm operations</li>
                        <li><strong>Damaged/Expired:</strong> Items no longer usable due to damage or expiration</li>
                        <li><strong>Return to Supplier:</strong> Items being returned to the supplier</li>
                        <li><strong>Inventory Adjustment:</strong> Correcting discrepancies between system and physical count</li>
                    </ul>
                    <div class="alert alert-info mt-3">
                        <small>
                            <i class="fas fa-info-circle"></i> Always provide detailed notes when removing stock, especially for inventory adjustments.
                        </small>
                    </div>
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