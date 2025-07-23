<?php
require_once 'includes/auth.php';
auth()->checkAdmin(); // Only allow admin access

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$categories = [];

// Handle form submission for adding a new category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    // Validate input
    if (empty($name)) {
        $errorMsg = "Category name is required.";
    } else {
        try {
            // Check if category with same name already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM item_categories WHERE name = ?");
            $stmt->execute([$name]);
            $exists = $stmt->fetchColumn() > 0;
            
            if ($exists) {
                $errorMsg = "A category with this name already exists.";
            } else {
                // Insert new category
                $stmt = $pdo->prepare("
                    INSERT INTO item_categories (name, description, status, created_at) 
                    VALUES (?, ?, 'active', NOW())
                ");
                $stmt->execute([$name, $description]);
                
                $successMsg = "Category '{$name}' added successfully.";
            }
        } catch (PDOException $e) {
            error_log("Error adding category: " . $e->getMessage());
            $errorMsg = "Failed to add category. Please try again later.";
        }
    }
}

// Handle category deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $categoryId = (int)$_GET['id'];
    
    try {
        // Check if any items are using this category
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_items WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        $itemCount = $stmt->fetchColumn();
        
        if ($itemCount > 0) {
            $errorMsg = "Cannot delete this category as it is used by {$itemCount} inventory items.";
        } else {
            // Delete the category
            $stmt = $pdo->prepare("DELETE FROM item_categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            
            // Or alternatively mark as inactive instead of deleting
            // $stmt = $pdo->prepare("UPDATE item_categories SET status = 'inactive' WHERE id = ?");
            // $stmt->execute([$categoryId]);
            
            $successMsg = "Category deleted successfully.";
        }
    } catch (PDOException $e) {
        error_log("Error deleting category: " . $e->getMessage());
        $errorMsg = "Failed to delete category. Please try again later.";
    }
}

// Handle category update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $categoryId = (int)$_POST['category_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    
    // Validate input
    if (empty($name)) {
        $errorMsg = "Category name is required.";
    } else {
        try {
            // Check if category with same name already exists (excluding current one)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM item_categories WHERE name = ? AND id != ?");
            $stmt->execute([$name, $categoryId]);
            $exists = $stmt->fetchColumn() > 0;
            
            if ($exists) {
                $errorMsg = "Another category with this name already exists.";
            } else {
                // Update category
                $stmt = $pdo->prepare("
                    UPDATE item_categories 
                    SET name = ?, description = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description, $status, $categoryId]);
                
                $successMsg = "Category updated successfully.";
            }
        } catch (PDOException $e) {
            error_log("Error updating category: " . $e->getMessage());
            $errorMsg = "Failed to update category. Please try again later.";
        }
    }
}

// Fetch all categories
try {
    $stmt = $pdo->query("
        SELECT c.*, 
            COALESCE(
                (SELECT COUNT(*) FROM inventory_items i WHERE i.category_id = c.id), 
                0
            ) as item_count
        FROM item_categories c
        ORDER BY c.name ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $errorMsg = "Failed to load categories. Please try again later.";
}

$pageTitle = 'Inventory Categories';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-sitemap"></i> Inventory Categories</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='inventory.php'">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </button>
            <button class="btn btn-primary" data-toggle="modal" data-target="#addCategoryModal">
                <i class="fas fa-plus"></i> Add New Category
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

    <!-- Categories Table -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Inventory Categories</h5>
        </div>
        <div class="card-body">
            <?php if (count($categories) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['description']); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $category['item_count']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($category['status'] === 'active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($category['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-primary edit-category" 
                                                data-id="<?php echo $category['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                data-description="<?php echo htmlspecialchars($category['description']); ?>"
                                                data-status="<?php echo $category['status']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($category['item_count'] == 0): ?>
                                                <a href="categories.php?action=delete&id=<?php echo $category['id']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Are you sure you want to delete this category?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-danger disabled" 
                                                        title="Cannot delete: category is in use">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No categories found. Add a new category to get started.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="edit_category_id" name="category_id">
                    <div class="form-group">
                        <label for="edit_name">Category Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select class="form-control" id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Set up edit category modal
    $(document).ready(function() {
        $('.edit-category').click(function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const description = $(this).data('description');
            const status = $(this).data('status');
            
            $('#edit_category_id').val(id);
            $('#edit_name').val(name);
            $('#edit_description').val(description);
            $('#edit_status').val(status);
            
            $('#editCategoryModal').modal('show');
        });
    });
</script>

<style>
    .table th {
        background-color: #f8f9fa;
    }
    
    .badge {
        font-size: 90%;
    }
</style>

<?php include 'includes/footer.php'; ?>