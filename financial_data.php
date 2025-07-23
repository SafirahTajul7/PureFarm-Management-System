<?php
require_once 'includes/auth.php';
auth()->checkAdmin(); // Only allow admin access

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$financialData = [];
$categories = [];
$dateRange = '';
$startDate = date('Y-m-01'); // First day of current month
$endDate = date('Y-m-t');    // Last day of current month

// Fetch expense categories for dropdown
try {
    // Check if 'status' column exists in expense_categories table
    $checkStatusColumn = $pdo->query("SHOW COLUMNS FROM expense_categories LIKE 'status'");
    $statusColumnExists = $checkStatusColumn->rowCount() > 0;
    
    if ($statusColumnExists) {
        // Use status column in the query
        $stmt = $pdo->query("SELECT id, name FROM expense_categories WHERE status = 'active' ORDER BY name ASC");
    } else {
        // If status column doesn't exist, fetch all categories without status filter
        $stmt = $pdo->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
    }
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no categories found, add a debug message
    if (empty($categories)) {
        $errorMsg = "No active expense categories found. Please add categories first.";
    }
} catch(PDOException $e) {
    error_log("Error fetching expense categories: " . $e->getMessage());
    $errorMsg = "Failed to load expense categories. Please try again later.";
}

// Handle expense record update
if (isset($_POST['update_record']) && isset($_POST['record_id'])) {
    $recordId = $_POST['record_id'];
    $type = $_POST['record_type'];
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $transactionDate = $_POST['transaction_date'];
    $notes = trim($_POST['notes']);
    
    // Validate input
    if (empty($description) || $amount <= 0 || empty($transactionDate)) {
        $errorMsg = "Please fill in all required fields with valid information.";
    } else {
        try {
            if ($type === 'expense') {
                $categoryId = $_POST['category_id'];
                if (empty($categoryId)) {
                    $errorMsg = "Please select a category for expense.";
                } else {
                    $stmt = $pdo->prepare("UPDATE financial_data SET category_id = ?, description = ?, amount = ?, transaction_date = ?, notes = ? WHERE id = ?");
                    $result = $stmt->execute([$categoryId, $description, $amount, $transactionDate, $notes, $recordId]);
                }
            } else {
                $source = trim($_POST['source']);
                if (empty($source)) {
                    $errorMsg = "Please enter a source for income.";
                } else {
                    $stmt = $pdo->prepare("UPDATE financial_data SET source = ?, description = ?, amount = ?, transaction_date = ?, notes = ? WHERE id = ?");
                    $result = $stmt->execute([$source, $description, $amount, $transactionDate, $notes, $recordId]);
                }
            }
            
            if (isset($result) && $result) {
                $successMsg = "Financial record updated successfully.";
            } else if (!isset($result)) {
                // Error message already set above
            } else {
                $errorMsg = "Failed to update the record.";
            }
        } catch(PDOException $e) {
            error_log("Error updating financial record: " . $e->getMessage());
            $errorMsg = "An error occurred while updating the record.";
        }
    }
}

// Handle expense record deletion
if (isset($_POST['delete_record']) && isset($_POST['record_id'])) {
    $recordId = $_POST['record_id'];
    
    try {
        // Check if 'status' column exists in financial_data table
        $checkStatusColumn = $pdo->query("SHOW COLUMNS FROM financial_data LIKE 'status'");
        $statusColumnExists = $checkStatusColumn->rowCount() > 0;
        
        if ($statusColumnExists) {
            // Soft delete - mark as inactive
            $stmt = $pdo->prepare("UPDATE financial_data SET status = 'inactive' WHERE id = ?");
            $result = $stmt->execute([$recordId]);
        } else {
            // If no status column, use hard delete
            $stmt = $pdo->prepare("DELETE FROM financial_data WHERE id = ?");
            $result = $stmt->execute([$recordId]);
        }
        
        if ($result) {
            $successMsg = "Financial record has been successfully deleted.";
        } else {
            $errorMsg = "Failed to delete the record.";
        }
    } catch(PDOException $e) {
        error_log("Error deleting financial record: " . $e->getMessage());
        $errorMsg = "An error occurred while deleting the record.";
    }
}

// ...existing code for handling new expense and income records...

// Handle new expense record submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $categoryId = $_POST['category_id'];
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $transactionDate = $_POST['transaction_date'];
    $notes = trim($_POST['notes']);
    
    // Validate input
    if (empty($categoryId) || empty($description) || $amount <= 0 || empty($transactionDate)) {
        $errorMsg = "Please fill in all required fields with valid information.";
    } else {
        try {
            // Check if financial_data table has status and created_at columns
            $checkColumns = $pdo->query("SHOW COLUMNS FROM financial_data");
            $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
            
            $hasStatus = in_array('status', $columns);
            $hasCreatedAt = in_array('created_at', $columns);
            
            // Build the query dynamically based on available columns
            $sql = "INSERT INTO financial_data (category_id, description, amount, transaction_date, notes, type";
            $params = [$categoryId, $description, $amount, $transactionDate, $notes, 'expense'];
            
            if ($hasStatus) {
                $sql .= ", status";
                $params[] = 'active';
            }
            
            if ($hasCreatedAt) {
                $sql .= ", created_at";
                $params[] = date('Y-m-d H:i:s');
            }
            
            $sql .= ") VALUES (" . implode(',', array_fill(0, count($params), '?')) . ")";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $successMsg = "Expense record added successfully.";
            } else {
                $errorMsg = "Failed to add expense record.";
            }
        } catch(PDOException $e) {
            error_log("Error adding expense record: " . $e->getMessage());
            $errorMsg = "An error occurred while adding the expense record.";
        }
    }
}

// Handle income record submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_income'])) {
    $source = trim($_POST['source']);
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $transactionDate = $_POST['transaction_date'];
    $notes = trim($_POST['notes']);
    
    // Validate input
    if (empty($source) || empty($description) || $amount <= 0 || empty($transactionDate)) {
        $errorMsg = "Please fill in all required fields with valid information.";
    } else {
        try {
            // Check if financial_data table has status and created_at columns
            $checkColumns = $pdo->query("SHOW COLUMNS FROM financial_data");
            $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
            
            $hasStatus = in_array('status', $columns);
            $hasCreatedAt = in_array('created_at', $columns);
            
            // Build the query dynamically based on available columns
            $sql = "INSERT INTO financial_data (source, description, amount, transaction_date, notes, type";
            $params = [$source, $description, $amount, $transactionDate, $notes, 'income'];
            
            if ($hasStatus) {
                $sql .= ", status";
                $params[] = 'active';
            }
            
            if ($hasCreatedAt) {
                $sql .= ", created_at";
                $params[] = date('Y-m-d H:i:s');
            }
            
            $sql .= ") VALUES (" . implode(',', array_fill(0, count($params), '?')) . ")";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $successMsg = "Income record added successfully.";
            } else {
                $errorMsg = "Failed to add income record.";
            }
        } catch(PDOException $e) {
            error_log("Error adding income record: " . $e->getMessage());
            $errorMsg = "An error occurred while adding the income record.";
        }
    }
}

// ...existing code for date range processing...

// Process date range filter
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    // Validate date format first
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
    
    // Make sure dates are in valid format
    if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $startDate) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $endDate)) {
        // Validate that end date is not before start date
        if (strtotime($endDate) >= strtotime($startDate)) {
            $dateRange = "Custom range: " . date('Y-m-d', strtotime($startDate)) . " to " . date('Y-m-d', strtotime($endDate));
        } else {
            // If end date is before start date, swap them
            $temp = $startDate;
            $startDate = $endDate;
            $endDate = $temp;
            $dateRange = "Custom range: " . date('Y-m-d', strtotime($startDate)) . " to " . date('Y-m-d', strtotime($endDate));
        }
    } else {
        // Invalid date format, use default
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        $dateRange = "Current Month: " . date('F Y');
    }
} else if (isset($_GET['range'])) {
    switch ($_GET['range']) {
        case 'current_month':
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            $dateRange = "Current Month: " . date('F Y');
            break;
        case 'previous_month':
            $startDate = date('Y-m-01', strtotime('first day of last month'));
            $endDate = date('Y-m-t', strtotime('last day of last month'));
            $dateRange = "Previous Month: " . date('F Y', strtotime('last month'));
            break;
        case 'last_30_days':
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $endDate = date('Y-m-d');
            $dateRange = "Last 30 Days";
            break;
        case 'current_year':
            $startDate = date('Y-01-01');
            $endDate = date('Y-12-31');
            $dateRange = "Current Year: " . date('Y');
            break;
        case 'custom':
            // If 'custom' is selected but no dates provided, show date picker but use current month
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            $dateRange = "Current Month: " . date('F Y');
            break;
        default:
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            $dateRange = "Current Month: " . date('F Y');
    }
} else {
    // Default date range (current month)
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
    $dateRange = "Current Month: " . date('F Y');
}

// Check if status column exists in financial_data table
try {
    $checkStatusColumn = $pdo->query("SHOW COLUMNS FROM financial_data LIKE 'status'");
    $statusColumnExists = $checkStatusColumn->rowCount() > 0;
    
    // Prepare query to fetch financial data
    if ($statusColumnExists) {
        $query = "
            SELECT f.id, f.category_id, ec.name as category_name, f.source, f.description, 
                   f.amount, f.transaction_date, f.notes, f.type
            FROM financial_data f
            LEFT JOIN expense_categories ec ON f.category_id = ec.id
            WHERE f.status = 'active' 
            AND f.transaction_date BETWEEN ? AND ?
            ORDER BY f.transaction_date DESC
        ";
    } else {
        $query = "
            SELECT f.id, f.category_id, ec.name as category_name, f.source, f.description, 
                   f.amount, f.transaction_date, f.notes, f.type
            FROM financial_data f
            LEFT JOIN expense_categories ec ON f.category_id = ec.id
            WHERE f.transaction_date BETWEEN ? AND ?
            ORDER BY f.transaction_date DESC
        ";
    }

    // Fetch financial data for the selected date range
    $stmt = $pdo->prepare($query);
    $stmt->execute([$startDate, $endDate]);
    $financialData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching financial data: " . $e->getMessage());
    $errorMsg = "Failed to load financial data. Please try again later.";
}

// Calculate summary statistics
$totalIncome = 0;
$totalExpenses = 0;
$expensesByCategory = [];

foreach ($financialData as $record) {
    if ($record['type'] === 'income') {
        $totalIncome += $record['amount'];
    } else {
        $totalExpenses += $record['amount'];
        
        // Group expenses by category
        $categoryName = $record['category_name'] ?? 'Uncategorized';
        if (!isset($expensesByCategory[$categoryName])) {
            $expensesByCategory[$categoryName] = 0;
        }
        $expensesByCategory[$categoryName] += $record['amount'];
    }
}

$netProfit = $totalIncome - $totalExpenses;

$pageTitle = 'Financial Management';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-line"></i> Financial Management</h2>
        <div class="action-buttons">
            <button class="btn btn-success" id="addExpenseBtn">
                <i class="fas fa-minus-circle"></i> Add Expense
            </button>
            <button class="btn btn-success" id="addIncomeBtn">
                <i class="fas fa-plus-circle"></i> Add Income
            </button>
            <button class="btn btn-secondary" id="exportCSVBtn">
                <i class="fas fa-file-export"></i> Export CSV
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

    <!-- Financial Summary -->
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Total Income</h5>
                </div>
                <div class="card-body text-center">
                    <h3 class="text-success">RM <?php echo number_format($totalIncome, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Total Expenses</h5>
                </div>
                <div class="card-body text-center">
                    <h3 class="text-danger">RM <?php echo number_format($totalExpenses, 2); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Net Profit</h5>
                </div>
                <div class="card-body text-center">
                    <h3 class="<?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                        RM <?php echo number_format($netProfit, 2); ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Date Range Filter</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="dateRangeForm">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="range">Preset Ranges:</label>
                        <select name="range" id="range" class="form-control">
                            <option value="current_month" <?php echo (!isset($_GET['range']) || $_GET['range'] == 'current_month') ? 'selected' : ''; ?>>Current Month</option>
                            <option value="previous_month" <?php echo (isset($_GET['range']) && $_GET['range'] == 'previous_month') ? 'selected' : ''; ?>>Previous Month</option>
                            <option value="last_30_days" <?php echo (isset($_GET['range']) && $_GET['range'] == 'last_30_days') ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="current_year" <?php echo (isset($_GET['range']) && $_GET['range'] == 'current_year') ? 'selected' : ''; ?>>Current Year</option>
                            <option value="custom" <?php echo (isset($_GET['range']) && $_GET['range'] == 'custom') || (isset($_GET['start_date']) && isset($_GET['end_date'])) ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                
                    <div id="custom-date-range" class="form-row col-md-9" <?php echo ((isset($_GET['range']) && $_GET['range'] == 'custom') || (isset($_GET['start_date']) && isset($_GET['end_date']))) ? '' : 'style="display:none;"'; ?>>
                        <div class="form-group col-md-4">
                            <label for="start_date">Start Date:</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="end_date">End Date:</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                        </div>
                        <div class="form-group col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Apply</button>
                        </div>
                    </div>
                </div>
            </form>
            
            <?php if (!empty($dateRange)): ?>
                <div class="mt-3">
                    <h6>Currently showing: <span class="text-primary"><?php echo $dateRange; ?></span></h6>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Financial Data Table -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Financial Transactions</h5>
        </div>
        <div class="card-body">
            <?php if (count($financialData) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount (RM)</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($financialData as $record): ?>
                                <tr class="<?php echo $record['type'] === 'income' ? 'table-success' : 'table-danger'; ?>" id="row-<?php echo $record['id']; ?>">
                                    <td class="view-mode">
                                        <span class="date-display"><?php echo date('Y-m-d', strtotime($record['transaction_date'])); ?></span>
                                        <input type="date" class="form-control form-control-sm date-edit" value="<?php echo date('Y-m-d', strtotime($record['transaction_date'])); ?>" style="display: none;">
                                    </td>
                                    <td>
                                        <?php if ($record['type'] === 'income'): ?>
                                            <span class="badge badge-success">Income</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Expense</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="view-mode">
                                        <span class="category-display">
                                            <?php 
                                                if ($record['type'] === 'income') {
                                                    echo htmlspecialchars($record['source']);
                                                } else {
                                                    echo htmlspecialchars($record['category_name'] ?? 'Uncategorized');
                                                }
                                            ?>
                                        </span>
                                        <div class="category-edit" style="display: none;">
                                            <?php if ($record['type'] === 'income'): ?>
                                                <input type="text" class="form-control form-control-sm source-edit" value="<?php echo htmlspecialchars($record['source']); ?>">
                                            <?php else: ?>
                                                <select class="form-control form-control-sm category-edit-select">
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>" <?php echo ($record['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="view-mode">
                                        <span class="description-display"><?php echo htmlspecialchars($record['description']); ?></span>
                                        <input type="text" class="form-control form-control-sm description-edit" value="<?php echo htmlspecialchars($record['description']); ?>" style="display: none;">
                                    </td>
                                    <td class="text-right view-mode">
                                        <span class="amount-display"><?php echo number_format($record['amount'], 2); ?></span>
                                        <input type="number" class="form-control form-control-sm amount-edit" value="<?php echo $record['amount']; ?>" min="0.01" step="0.01" style="display: none;">
                                    </td>
                                    <td class="view-mode">
                                        <span class="notes-display"><?php echo htmlspecialchars($record['notes']); ?></span>
                                        <textarea class="form-control form-control-sm notes-edit" rows="2" style="display: none;"><?php echo htmlspecialchars($record['notes']); ?></textarea>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="edit_financial_record.php?id=<?php echo $record['id']; ?>&type=<?php echo $record['type']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger delete-btn" title="Delete" data-id="<?php echo $record['id']; ?>" data-type="<?php echo $record['type']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No financial records found for the selected date range.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Expense Breakdown -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Expense Breakdown by Category</h5>
        </div>
        <div class="card-body">
            <?php if (count($expensesByCategory) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead class="thead-light">
                            <tr>
                                <th>Category</th>
                                <th class="text-right">Amount (RM)</th>
                                <th class="text-right">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expensesByCategory as $category => $amount): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category); ?></td>
                                    <td class="text-right"><?php echo number_format($amount, 2); ?></td>
                                    <td class="text-right">
                                        <?php 
                                            $percentage = ($totalExpenses > 0) ? ($amount / $totalExpenses) * 100 : 0;
                                            echo number_format($percentage, 2) . '%';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="font-weight-bold">
                                <td>Total</td>
                                <td class="text-right"><?php echo number_format($totalExpenses, 2); ?></td>
                                <td class="text-right">100.00%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No expense data available for the selected date range.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" role="dialog" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="addExpenseModalLabel">Add New Expense</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="category_id">Expense Category</label>
                        <select class="form-control" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input type="text" class="form-control" id="description" name="description" required>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount (RM)</label>
                        <input type="number" class="form-control" id="amount" name="amount" min="0.01" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="transaction_date">Date</label>
                        <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_expense" class="btn btn-danger">Add Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Income Modal -->
<div class="modal fade" id="addIncomeModal" tabindex="-1" role="dialog" aria-labelledby="addIncomeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addIncomeModalLabel">Add New Income</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="source">Source</label>
                        <input type="text" class="form-control" id="source" name="source" required placeholder="e.g., Crop Sales, Livestock Sales">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input type="text" class="form-control" id="description" name="description" required>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount (RM)</label>
                        <input type="number" class="form-control" id="amount" name="amount" min="0.01" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="transaction_date">Date</label>
                        <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_income" class="btn btn-success">Add Income</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this financial record? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <form method="POST" action="">
                    <input type="hidden" name="record_id" id="deleteRecordId">
                    <input type="hidden" name="delete_record" value="1">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
<style>
    .table th {
        white-space: nowrap;
    }
    
    .badge {
        font-size: 90%;
    }
    
    .table-success {
        background-color: rgba(40, 167, 69, 0.1) !important;
    }
    
    .table-danger {
        background-color: rgba(220, 53, 69, 0.1) !important;
    }
    
    .card-header.bg-primary {
        background-color: #007bff !important;
    }
    
    .btn-success {
        background-color: #28a745;
        border-color: #28a745;
    }
    
    .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
    }
    
    /* Additional custom styles for financial management page */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .insight-card {
        border-left: 4px solid #007bff;
        background-color: #f8f9fa;
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 4px;
    }
    
    /* Fixed inline editing styles - Default state */
    .date-edit, .description-edit, .amount-edit, .notes-edit, .category-edit, .source-edit {
        display: none;
        width: 100%;
    }
    
    .edit-mode {
        display: none;
    }
    
    /* When in editing mode, show edit elements and hide view elements */
    tr.editing .date-edit,
    tr.editing .description-edit,
    tr.editing .amount-edit,
    tr.editing .notes-edit,
    tr.editing .category-edit,
    tr.editing .source-edit {
        display: block !important;
    }
    
    tr.editing .date-display,
    tr.editing .description-display,
    tr.editing .amount-display,
    tr.editing .notes-display,
    tr.editing .category-display {
        display: none !important;
    }
    
    tr.editing .view-mode {
        display: none !important;
    }
    
    tr.editing .edit-mode {
        display: flex !important;
        gap: 5px;
    }
    
    .form-control-sm {
        font-size: 0.875rem;
        height: calc(1.5em + 0.5rem + 2px);
    }
    
    /* Make edit inputs more visible */
    tr.editing .form-control {
        border: 2px solid #007bff;
        background-color: #fff;
    }
    
    tr.editing {
        background-color: #e3f2fd !important;
    }
</style>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add Expense button functionality
    document.getElementById('addExpenseBtn').addEventListener('click', function() {
        $('#addExpenseModal').modal('show');
    });

    // Add Income button functionality
    document.getElementById('addIncomeBtn').addEventListener('click', function() {
        $('#addIncomeModal').modal('show');
    });
    
    // Toggle custom date range fields
    document.getElementById('range').addEventListener('change', function() {
        if (this.value === 'custom') {
            document.getElementById('custom-date-range').style.display = 'flex';
        } else {
            document.getElementById('custom-date-range').style.display = 'none';
            // Auto-submit the form when a preset range is selected
            document.getElementById('dateRangeForm').submit();
        }
    });
    
    // Validate date range before submission
    document.getElementById('dateRangeForm').addEventListener('submit', function(e) {
        if (document.getElementById('range').value === 'custom') {
            var startDate = new Date(document.getElementById('start_date').value);
            var endDate = new Date(document.getElementById('end_date').value);
            
            if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
                alert('Please select valid start and end dates.');
                e.preventDefault();
                return false;
            }
            
            if (startDate > endDate) {
                alert('End date must be after start date.');
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Delete functionality
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-btn')) {
            e.preventDefault();
            const btn = e.target.closest('.delete-btn');
            confirmDelete(btn.dataset.id, btn.dataset.type);
        }
    });
    
    // Function to confirm deletion of a record
    window.confirmDelete = function(recordId, recordType) {
        document.getElementById('deleteRecordId').value = recordId;
        var modalTitle = "Delete " + (recordType === 'income' ? "Income" : "Expense") + " Record";
        document.getElementById('deleteModalLabel').innerText = modalTitle;
        $('#deleteModal').modal('show');
    };
    
    // Export to CSV functionality
    document.getElementById('exportCSVBtn').addEventListener('click', exportFinancialData);
    
    // Function to export financial data as CSV
    function exportFinancialData() {
        const table = document.querySelector('.table-striped.table-hover');
        if (!table) return;
        
        const rows = table.querySelectorAll('tr');
        let csv = [];
        
        // Add header row
        let headerRow = [];
        const headers = rows[0].querySelectorAll('th');
        for (let i = 0; i < headers.length - 1; i++) { // Skip the Actions column
            headerRow.push('"' + headers[i].textContent.trim() + '"');
        }
        csv.push(headerRow.join(','));
        
        // Add data rows
        for (let i = 1; i < rows.length; i++) {
            let row = [];
            const cols = rows[i].querySelectorAll('td');
            
            // Process all columns except the last one (Actions)
            for (let j = 0; j < cols.length - 1; j++) {
                let data = cols[j].textContent.trim();
                
                // Handle special formatting for type column
                if (j === 1) { // Type column
                    data = cols[j].querySelector('.badge').textContent.trim();
                }
                
                // Escape double quotes and wrap in quotes
                data = data.replace(/"/g, '""');
                row.push('"' + data + '"');
            }
            csv.push(row.join(','));
        }
        
        // Create and download the CSV file
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', 'financial_data_' + new Date().toISOString().slice(0, 10) + '.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
});
</script>


<?php include 'includes/footer.php'; ?>