<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$itemData = [];
$categories = [];

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: inventory.php');
    exit;
}

$itemId = $_GET['id'];

// Function to check database table structure and adapt if needed
function ensureTableStructure($pdo) {
    try {
        // Get actual column names from inventory_items table
        $columnsStmt = $pdo->query("DESCRIBE inventory_items");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Determine which column stores the item name
        $nameColumn = in_array('name', $columns) ? 'name' : 
                     (in_array('item_name', $columns) ? 'item_name' : null);
        
        if (!$nameColumn) {
            return "Could not find name column in inventory_items table.";
        }
        
        return [
            'status' => 'success',
            'nameColumn' => $nameColumn,
            'columns' => $columns
        ];
    } catch (PDOException $e) {
        return "Error checking table structure: " . $e->getMessage();
    }
}

// Run the table structure check
$structureCheck = ensureTableStructure($pdo);
$nameColumn = is_array($structureCheck) && isset($structureCheck['nameColumn']) 
    ? $structureCheck['nameColumn'] 
    : 'name'; // Default fallback

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $itemName = $_POST['item_name'] ?? '';
        $sku = $_POST['sku'] ?? '';
        $categoryId = $_POST['category_id'] ?? null;
        $description = $_POST['description'] ?? '';
        $unitOfMeasure = $_POST['unit_of_measure'] ?? '';
        $currentQuantity = $_POST['current_quantity'] ?? 0;
        $reorderLevel = $_POST['reorder_level'] ?? 0;
        $maximumLevel = $_POST['maximum_level'] ?? null;
        $unitCost = $_POST['unit_cost'] ?? null;
        $status = $_POST['status'] ?? 'active';
        
        // Update the inventory item
        $stmt = $pdo->prepare("
            UPDATE inventory_items
            SET 
                {$nameColumn} = :item_name,
                sku = :sku,
                category_id = :category_id,
                description = :description,
                unit_of_measure = :unit_of_measure,
                current_quantity = :current_quantity,
                reorder_level = :reorder_level,
                maximum_level = :maximum_level,
                unit_cost = :unit_cost,
                status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :item_id
        ");
        
        $stmt->execute([
            'item_name' => $itemName,
            'sku' => $sku,
            'category_id' => $categoryId,
            'description' => $description,
            'unit_of_measure' => $unitOfMeasure,
            'current_quantity' => $currentQuantity,
            'reorder_level' => $reorderLevel,
            'maximum_level' => $maximumLevel,
            'unit_cost' => $unitCost,
            'status' => $status,
            'item_id' => $itemId
        ]);
        
        $successMsg = 'Inventory item updated successfully.';
    } catch (PDOException $e) {
        error_log("Error updating inventory item: " . $e->getMessage());
        $errorMsg = 'Failed to update inventory item: ' . $e->getMessage();
    }
}

// Get item data
try {
    $stmt = $pdo->prepare("
        SELECT 
            inventory_items.*,
            inventory_items.{$nameColumn} AS item_name,
            item_categories.name AS category_name
        FROM inventory_items
        LEFT JOIN item_categories ON inventory_items.category_id = item_categories.id
        WHERE inventory_items.id = :item_id
    ");
    $stmt->execute(['item_id' => $itemId]);
    $itemData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$itemData) {
        header('Location: inventory.php');
        exit;
    }
    
    // Get categories for dropdown
    $categoryStmt = $pdo->query("SELECT id, name FROM item_categories ORDER BY name");
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching item data: " . $e->getMessage());
    $errorMsg = 'Failed to load item data: ' . $e->getMessage();
}

$pageTitle = 'Edit Inventory Item';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-edit"></i> Edit Inventory Item</h2>
        <div class="action-buttons">
            <a href="inventory.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?php echo $errorMsg; ?></div>
    <?php endif; ?>
    
    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?php echo $successMsg; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Edit Item Details</h3>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="item_name">Item Name:</label>
                            <input type="text" class="form-control" id="item_name" name="item_name" 
                                value="<?php echo htmlspecialchars($itemData['item_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="sku">SKU:</label>
                            <input type="text" class="form-control" id="sku" name="sku" 
                                value="<?php echo htmlspecialchars($itemData['sku'] ?? ''); ?>">
                            <small class="form-text text-muted">Stock Keeping Unit identifier</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="category_id">Category:</label>
                            <select class="form-control" id="category_id" name="category_id">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                        <?php echo ($itemData['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="unit_of_measure">Unit of Measure:</label>
                            <input type="text" class="form-control" id="unit_of_measure" name="unit_of_measure" 
                                value="<?php echo htmlspecialchars($itemData['unit_of_measure'] ?? ''); ?>">
                            <small class="form-text text-muted">e.g., kg, pieces, liters</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($itemData['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="current_quantity">Current Quantity:</label>
                            <input type="number" step="0.01" class="form-control" id="current_quantity" name="current_quantity" 
                                value="<?php echo htmlspecialchars($itemData['current_quantity'] ?? '0'); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="reorder_level">Reorder Level:</label>
                            <input type="number" step="0.01" class="form-control" id="reorder_level" name="reorder_level" 
                                value="<?php echo htmlspecialchars($itemData['reorder_level'] ?? '0'); ?>">
                            <small class="form-text text-muted">Minimum stock before reordering</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="maximum_level">Maximum Level:</label>
                            <input type="number" step="0.01" class="form-control" id="maximum_level" name="maximum_level" 
                                value="<?php echo htmlspecialchars($itemData['maximum_level'] ?? ''); ?>">
                            <small class="form-text text-muted">Maximum stock to maintain</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="unit_cost">Unit Cost:</label>
                            <input type="number" step="0.01" class="form-control" id="unit_cost" name="unit_cost" 
                                value="<?php echo htmlspecialchars($itemData['unit_cost'] ?? ''); ?>">
                            <small class="form-text text-muted">Cost per unit</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select class="form-control" id="status" name="status">
                        <option value="active" <?php echo ($itemData['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($itemData['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="damaged" <?php echo ($itemData['status'] ?? '') === 'damaged' ? 'selected' : ''; ?>>Damaged</option>
                        <option value="expired" <?php echo ($itemData['status'] ?? '') === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="inventory.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>