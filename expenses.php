<?php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Date filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month

// Pagination
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 20;
$offset = ($current_page - 1) * $items_per_page;

// Sorting
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'date';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Valid sort fields to prevent SQL injection
$valid_sort_fields = ['date', 'amount', 'crop_name', 'category'];
if (!in_array($sort_by, $valid_sort_fields)) {
    $sort_by = 'date'; // Default sort field
}

// Valid sort orders
$valid_sort_orders = ['ASC', 'DESC'];
if (!in_array(strtoupper($sort_order), $valid_sort_orders)) {
    $sort_order = 'DESC'; // Default sort order
}

// Category filter
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Crop filter
$crop_filter = isset($_GET['crop_id']) ? intval($_GET['crop_id']) : 0;

try {
    // Build the query with filters
    $query = "
        SELECT ce.*, c.crop_name
        FROM crop_expenses ce
        JOIN crops c ON ce.crop_id = c.id
        WHERE ce.date BETWEEN :start_date AND :end_date
    ";
    
    $params = [
        'start_date' => $start_date,
        'end_date' => $end_date
    ];
    
    // Add category filter if specified
    if (!empty($category_filter)) {
        $query .= " AND ce.category = :category";
        $params['category'] = $category_filter;
    }
    
    // Add crop filter if specified
    if ($crop_filter > 0) {
        $query .= " AND ce.crop_id = :crop_id";
        $params['crop_id'] = $crop_filter;
    }
    
    // Add sorting
    $query .= " ORDER BY $sort_by $sort_order";
    
    // Get total count for pagination
    $count_stmt = $pdo->prepare(str_replace('ce.*, c.crop_name', 'COUNT(*) as total', $query));
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_items / $items_per_page);
    
    // Add pagination
    $query .= " LIMIT :offset, :limit";
    $params['offset'] = $offset;
    $params['limit'] = $items_per_page;
    
    // Prepare and execute the main query
    $stmt = $pdo->prepare($query);
    
    // Bind parameters (need to specify the type for LIMIT parameters)
    foreach ($params as $key => $value) {
        if ($key === 'offset' || $key === 'limit') {
            $stmt->bindValue(":$key", $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(":$key", $value);
        }
    }
    
    $stmt->execute();
    $expenses = $stmt->fetchAll();
    
    // Get all categories for the filter dropdown
    $categories_stmt = $pdo->query("
        SELECT DISTINCT category 
        FROM crop_expenses 
        ORDER BY category
    ");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all crops for the filter dropdown
    $crops_stmt = $pdo->query("
        SELECT id, crop_name 
        FROM crops 
        ORDER BY crop_name
    ");
    $crops = $crops_stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log("Error fetching expenses: " . $e->getMessage());
    $_SESSION['error_message'] = "Error loading expenses. Please try again.";
    $expenses = [];
    $total_pages = 1;
    $categories = [];
    $crops = [];
}

$pageTitle = 'All Expenses';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-receipt"></i> Expenses List</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='add_expense.php'">
                <i class="fas fa-plus"></i> Add Expense
            </button>
            <button class="btn btn-secondary" onclick="location.href='financial_analysis.php'">
                <i class="fas fa-chart-line"></i> Financial Analysis
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filter-card">
        <form method="GET" action="" class="filters-form">
            <!-- Preserve existing sort parameters -->
            <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
            <input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($sort_order); ?>">
            
            <div class="filter-row">
                <div class="filter-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="category">Category:</label>
                    <select id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category === $category_filter ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="crop_id">Crop:</label>
                    <select id="crop_id" name="crop_id">
                        <option value="0">All Crops</option>
                        <?php foreach ($crops as $crop): ?>
                            <option value="<?php echo $crop['id']; ?>" <?php echo $crop['id'] == $crop_filter ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($crop['crop_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <button type="button" class="btn btn-secondary" onclick="resetFilters()">Reset Filters</button>
            </div>
        </form>
    </div>
    
    <!-- Expenses Table -->
    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-list"></i> Expenses (<?php echo $total_items; ?> total)</h3>
        </div>
        
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>
                            <a href="?sort_by=date&sort_order=<?php echo ($sort_by === 'date' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&category=<?php echo urlencode($category_filter); ?>&crop_id=<?php echo $crop_filter; ?>">
                                Date
                                <?php if ($sort_by === 'date'): ?>
                                    <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort_by=crop_name&sort_order=<?php echo ($sort_by === 'crop_name' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&category=<?php echo urlencode($category_filter); ?>&crop_id=<?php echo $crop_filter; ?>">
                                Crop
                                <?php if ($sort_by === 'crop_name'): ?>
                                    <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?sort_by=category&sort_order=<?php echo ($sort_by === 'category' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&category=<?php echo urlencode($category_filter); ?>&crop_id=<?php echo $crop_filter; ?>">
                                Category
                                <?php if ($sort_by === 'category'): ?>
                                    <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Description</th>
                        <th>
                            <a href="?sort_by=amount&sort_order=<?php echo ($sort_by === 'amount' && $sort_order === 'ASC') ? 'DESC' : 'ASC'; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&category=<?php echo urlencode($category_filter); ?>&crop_id=<?php echo $crop_filter; ?>">
                                Amount
                                <?php if ($sort_by === 'amount'): ?>
                                    <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No expenses found for the selected criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($expense['date'])); ?></td>
                                <td><?php echo htmlspecialchars($expense['crop_name']); ?></td>
                                <td><?php echo htmlspecialchars($expense['category']); ?></td>
                                <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                <td>$<?php echo number_format($expense['amount'], 2); ?></td>
                                <td class="actions">
                                    <a href="edit_expense.php?id=<?php echo $expense['id']; ?>" class="btn-icon" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $expense['id']; ?>)" class="btn-icon" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?page=1&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&category=<?php echo urlencode($category_filter); ?>&crop_id=<?php echo $crop_filter; ?>" class="page-link">First</a>
                    <a href="?page=<?php echo $current_page - 1; ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&category=<?php echo urlencode($category_filter); ?>&crop_id=<?php echo $crop_filter; ?>" class="page-link">Previous</a>
                <?php endif; ?>
                
                <?php
                // Display a range of page numbers
                $range = 2; // Number of pages to show before and after the current page
                $start_page = max(1, $current_page - $range);
                $end_page = min($total_pages, $current_page + $range);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&category=<?php echo urlencode($category_filter); ?>&crop_id=<?php echo $crop_filter; ?>" class="page-link <?php echo $i === $current_page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&category=<?php echo urlencode($category_filter); ?>&crop_id=<?php echo $crop_filter; ?>" class="page-link">Next</a>
                    <a href="?page=<?php echo $total_pages; ?>&sort_by=<?php echo $sort_by; ?>&sort_order=<?php echo $sort_order; ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&category=<?php echo urlencode($category_filter); ?>&crop_id=<?php echo $crop_filter; ?>" class="page-link">Last</a>
                <?php endif; ?>
                
                <span class="pagination-info">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this expense? This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <form id="deleteForm" method="POST" action="delete_expense.php">
                <input type="hidden" name="expense_id" id="expenseIdToDelete" value="">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
// Function to reset all filters
function resetFilters() {
    // Reset to default values (first and last day of current month)
    document.getElementById('start_date').value = '<?php echo date('Y-m-01'); ?>';
    document.getElementById('end_date').value = '<?php echo date('Y-m-t'); ?>';
    document.getElementById('category').selectedIndex = 0;
    document.getElementById('crop_id').selectedIndex = 0;
    
    // Submit the form
    document.querySelector('.filters-form').submit();
}

// Delete modal functions
function confirmDelete(expenseId) {
    document.getElementById('expenseIdToDelete').value = expenseId;
    document.getElementById('deleteModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<style>
.filters-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
    padding: 15px;
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    align-items: center;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-group label {
    font-weight: 500;
    white-space: nowrap;
}

.filter-group select,
.filter-group input {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
    gap: 5px;
    flex-wrap: wrap;
}

.page-link {
    padding: 5px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #4e73df;
}

.page-link:hover {
    background-color: #f0f0f0;
}

.page-link.active {
    background-color: #4e73df;
    color: white;
    border-color: #4e73df;
}

.pagination-info {
    margin-left: 10px;
    align-self: center;
    color: #666;
}

.actions {
    display: flex;
    gap: 10px;
}

.btn-icon {
    padding: 5px;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
}

.btn-icon:hover {
    background-color: #f0f0f0;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 0;
    border-radius: 8px;
    width: 400px;
    max-width: 90%;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.modal-header {
    padding: 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #333;
}

.close {
    color: #aaa;
    font-size: 24px;
    cursor: pointer;
}

.close:hover {
    color: #333;
}

.modal-body {
    padding: 15px;
}

.modal-footer {
    padding: 15px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn-danger {
    background-color: #e74a3b;
    border-color: #e74a3b;
    color: white;
}

.btn-danger:hover {
    background-color: #d52a1a;
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-group select,
    .filter-group input {
        width: 100%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>