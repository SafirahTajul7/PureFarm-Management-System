<?php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

$message = '';
$messageType = '';

// Fetch categories for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT id, name FROM suppliers ORDER BY name");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching categories/suppliers: " . $e->getMessage());
    $categories = [];
    $suppliers = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO inventory_items (
                item_name, sku, category_id, description, unit_of_measure,
                current_quantity, reorder_level, maximum_level, unit_cost,
                expiry_date, batch_number, supplier_id
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $_POST['item_name'],
            $_POST['sku'],
            $_POST['category_id'],
            $_POST['description'],
            $_POST['unit_of_measure'],
            $_POST['current_quantity'],
            $_POST['reorder_level'],
            $_POST['maximum_level'],
            $_POST['unit_cost'],
            $_POST['expiry_date'],
            $_POST['batch_number'],
            $_POST['supplier_id']
        ]);

        $message = "Item added successfully!";
        $messageType = "success";
    } catch(PDOException $e) {
        $message = "Error adding item: " . $e->getMessage();
        $messageType = "error";
    }
}

$pageTitle = 'Add New Inventory Item';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-plus-circle"></i> Add New Inventory Item</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='inventory.php'">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType == 'success' ? 'success' : 'danger'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="item_name">Item Name*</label>
                        <input type="text" class="form-control" id="item_name" name="item_name" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="sku">SKU*</label>
                        <input type="text" class="form-control" id="sku" name="sku" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="category_id">Category</label>
                        <select class="form-control" id="category_id" name="category_id">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="supplier_id">Supplier</label>
                        <select class="form-control" id="supplier_id" name="supplier_id">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>">
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="unit_of_measure">Unit of Measure</label>
                        <input type="text" class="form-control" id="unit_of_measure" name="unit_of_measure">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="unit_cost">Unit Cost</label>
                        <input type="number" step="0.01" class="form-control" id="unit_cost" name="unit_cost">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="current_quantity">Current Quantity</label>
                        <input type="number" class="form-control" id="current_quantity" name="current_quantity">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="reorder_level">Reorder Level</label>
                        <input type="number" class="form-control" id="reorder_level" name="reorder_level">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="maximum_level">Maximum Level</label>
                        <input type="number" class="form-control" id="maximum_level" name="maximum_level">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="expiry_date">Expiry Date</label>
                        <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="batch_number">Batch Number</label>
                        <input type="text" class="form-control" id="batch_number" name="batch_number">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Item
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>