<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$items = [];
$categories = [];

// Fetch all active categories for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM item_categories WHERE status = 'active' ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $errorMsg = "Failed to load categories. Please try again later.";
}

// Handle Delete Request
if (isset($_POST['delete_item']) && isset($_POST['item_id'])) {
    $itemId = $_POST['item_id'];
    
    try {
        // Soft delete - just mark as inactive
        $stmt = $pdo->prepare("UPDATE inventory_items SET status = 'inactive' WHERE id = ?");
        $result = $stmt->execute([$itemId]);
        
        if ($result) {
            $successMsg = "Item has been successfully deleted.";
        } else {
            $errorMsg = "Failed to delete the item.";
        }
    } catch(PDOException $e) {
        error_log("Error deleting item: " . $e->getMessage());
        $errorMsg = "An error occurred while deleting the item.";
    }
}

// Process search query
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';

// Prepare base query
$query = "SELECT i.id, i.item_name as name, i.sku, i.description, i.category_id, c.name as category_name, 
          i.current_quantity, i.unit_of_measure, i.reorder_level, i.maximum_level,
          i.unit_cost, i.expiry_date, i.batch_number, i.supplier_id, s.name as supplier_name
          FROM inventory_items i
          LEFT JOIN item_categories c ON i.category_id = c.id
          LEFT JOIN suppliers s ON i.supplier_id = s.id
          WHERE i.status = 'active'";

// Add search filter if provided
$params = [];
if (!empty($searchTerm)) {
    $query .= " AND (i.item_name LIKE ? OR i.sku LIKE ? OR i.description LIKE ?)";
    $searchParam = "%{$searchTerm}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Add category filter if provided
if (!empty($categoryFilter)) {
    $query .= " AND i.category_id = ?";
    $params[] = $categoryFilter;
}

// Add sorting
$query .= " ORDER BY i.item_name ASC";

// Fetch the items
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching inventory items: " . $e->getMessage());
    $errorMsg = "Failed to load inventory items. Please try again later.";
}

$pageTitle = 'Item Details';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-tag"></i> Inventory Item Details</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='add_inventory_item.php'">
                <i class="fas fa-plus"></i> Add New Item
            </button>
            <button class="btn btn-secondary" onclick="exportItemsToCSV()">
                <i class="fas fa-file-export"></i> Export CSV
            </button>
            
            <button class="btn btn-secondary" onclick="location.href='inventory.php'">
                <i class="fas fa-arrow-left"></i> Back to Inventory
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

    <!-- Search and Filter Section -->
    <div class="search-filter-container">
        <form method="GET" action="" class="search-filter-form">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <select name="category" class="form-control" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($categoryFilter == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="window.location.href='item_details.php'">
                        <i class="fas fa-sync-alt"></i> Reset
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Items Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Reorder Level</th>
                    <th>Purchase Price</th>
                    <th>Supplier</th>
                    <th>Expiry Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $item): ?>
                        <tr <?php echo ($item['current_quantity'] <= $item['reorder_level']) ? 'class="table-warning"' : ''; ?>>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['sku']); ?></td>
                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                            <td><?php echo $item['current_quantity']; ?></td>
                            <td><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                            <td><?php echo $item['reorder_level']; ?></td>
                            <td><?php echo number_format($item['unit_cost'], 2); ?></td>
                            <td><?php echo htmlspecialchars($item['supplier_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php 
                                    if (!empty($item['expiry_date'])) {
                                        $expiryDate = new DateTime($item['expiry_date']);
                                        $today = new DateTime();
                                        $interval = $today->diff($expiryDate);
                                        $daysRemaining = $expiryDate > $today ? $interval->days : -$interval->days;
                                        
                                        $expiryClass = '';
                                        if ($daysRemaining < 0) {
                                            $expiryClass = 'text-danger';
                                        } elseif ($daysRemaining <= 30) {
                                            $expiryClass = 'text-warning';
                                        }
                                        
                                        echo '<span class="'.$expiryClass.'">' . htmlspecialchars($item['expiry_date']) . '</span>';
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="view_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" title="Delete" 
                                            onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo addslashes(htmlspecialchars($item['name'])); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center">No items found. <?php echo !empty($searchTerm) || !empty($categoryFilter) ? 'Try a different search term or filter.' : ''; ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Item Summary -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Inventory Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Total Items</h6>
                        <p class="stats-number"><?php echo count($items); ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Items Below Reorder Level</h6>
                        <p class="stats-number text-warning">
                            <?php 
                                $lowStockCount = 0;
                                foreach ($items as $item) {
                                    if ($item['current_quantity'] <= $item['reorder_level']) {
                                        $lowStockCount++;
                                    }
                                }
                                echo $lowStockCount;
                            ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Items Expiring Soon</h6>
                        <p class="stats-number text-warning">
                            <?php 
                                $expiringSoonCount = 0;
                                $today = new DateTime();
                                foreach ($items as $item) {
                                    if (!empty($item['expiry_date'])) {
                                        $expiryDate = new DateTime($item['expiry_date']);
                                        $interval = $today->diff($expiryDate);
                                        $daysRemaining = $expiryDate > $today ? $interval->days : -$interval->days;
                                        
                                        if ($daysRemaining >= 0 && $daysRemaining <= 30) {
                                            $expiringSoonCount++;
                                        }
                                    }
                                }
                                echo $expiringSoonCount;
                            ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-item">
                        <h6>Expired Items</h6>
                        <p class="stats-number text-danger">
                            <?php 
                                $expiredCount = 0;
                                $today = new DateTime();
                                foreach ($items as $item) {
                                    if (!empty($item['expiry_date'])) {
                                        $expiryDate = new DateTime($item['expiry_date']);
                                        if ($expiryDate < $today) {
                                            $expiredCount++;
                                        }
                                    }
                                }
                                echo $expiredCount;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete <span id="itemName"></span>?
            </div>
            <div class="modal-footer">
                <form method="POST" action="">
                    <input type="hidden" name="item_id" id="deleteItemId">
                    <input type="hidden" name="delete_item" value="1">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(itemId, itemName) {
        document.getElementById('deleteItemId').value = itemId;
        document.getElementById('itemName').textContent = itemName;
        $('#deleteModal').modal('show');
    }

    function exportItemsToCSV() {
        // Redirect to a CSV export handler
        window.location.href = 'export_items.php?search=<?php echo urlencode($searchTerm); ?>&category=<?php echo urlencode($categoryFilter); ?>';
    }
</script>

<style>
    .search-filter-container {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .stats-item {
        border-left: 3px solid #007bff;
        padding-left: 15px;
        margin-bottom: 10px;
    }

    .stats-number {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 0;
    }

    .table th {
        white-space: nowrap;
    }

    /* Highlight for low stock items */
    .table-warning {
        background-color: rgba(255, 193, 7, 0.2);
    }
</style>

<?php include 'includes/footer.php'; ?>