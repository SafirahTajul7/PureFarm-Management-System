<?php
require_once 'includes/auth.php';
auth()->checkAdmin(); // Only allow admin access

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$record = null;
$categories = [];

// Get record ID and type from URL
$recordId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$recordType = isset($_GET['type']) ? $_GET['type'] : '';

if (!$recordId || !in_array($recordType, ['income', 'expense'])) {
    header('Location: financial_data.php');
    exit();
}

// Fetch expense categories for dropdown
try {
    $checkStatusColumn = $pdo->query("SHOW COLUMNS FROM expense_categories LIKE 'status'");
    $statusColumnExists = $checkStatusColumn->rowCount() > 0;
    
    if ($statusColumnExists) {
        $stmt = $pdo->query("SELECT id, name FROM expense_categories WHERE status = 'active' ORDER BY name ASC");
    } else {
        $stmt = $pdo->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
    }
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching expense categories: " . $e->getMessage());
    $errorMsg = "Failed to load expense categories.";
}

// Fetch the record to edit
try {
    $stmt = $pdo->prepare("
        SELECT f.id, f.category_id, ec.name as category_name, f.source, f.description, 
               f.amount, f.transaction_date, f.notes, f.type
        FROM financial_data f
        LEFT JOIN expense_categories ec ON f.category_id = ec.id
        WHERE f.id = ?
    ");
    $stmt->execute([$recordId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        header('Location: financial_data.php');
        exit();
    }
} catch(PDOException $e) {
    error_log("Error fetching financial record: " . $e->getMessage());
    $errorMsg = "Failed to load record.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_record'])) {
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $transactionDate = $_POST['transaction_date'];
    $notes = trim($_POST['notes']);
    
    // Validate input
    if (empty($description) || $amount <= 0 || empty($transactionDate)) {
        $errorMsg = "Please fill in all required fields with valid information.";
    } else {
        try {
            if ($recordType === 'expense') {
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
                // Refresh the record data
                $stmt = $pdo->prepare("
                    SELECT f.id, f.category_id, ec.name as category_name, f.source, f.description, 
                           f.amount, f.transaction_date, f.notes, f.type
                    FROM financial_data f
                    LEFT JOIN expense_categories ec ON f.category_id = ec.id
                    WHERE f.id = ?
                ");
                $stmt->execute([$recordId]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
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

$pageTitle = 'Edit Financial Record';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-edit"></i> Edit <?php echo ucfirst($recordType); ?> Record</h2>
        <div class="action-buttons">
            <a href="financial_data.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Financial Data
            </a>
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

    <?php if ($record): ?>
    <div class="card">
        <div class="card-header <?php echo $recordType === 'income' ? 'bg-success' : 'bg-danger'; ?> text-white">
            <h5 class="mb-0">
                <i class="fas fa-<?php echo $recordType === 'income' ? 'plus-circle' : 'minus-circle'; ?>"></i>
                Edit <?php echo ucfirst($recordType); ?> Record
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <?php if ($recordType === 'expense'): ?>
                        <div class="form-group">
                            <label for="category_id">Expense Category <span class="text-danger">*</span></label>
                            <select class="form-control" id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($record['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php else: ?>
                        <div class="form-group">
                            <label for="source">Income Source <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="source" name="source" value="<?php echo htmlspecialchars($record['source']); ?>" required placeholder="e.g., Crop Sales, Livestock Sales">
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="description">Description <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="description" name="description" value="<?php echo htmlspecialchars($record['description']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">Amount (RM) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="amount" name="amount" value="<?php echo $record['amount']; ?>" min="0.01" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="transaction_date">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?php echo date('Y-m-d', strtotime($record['transaction_date'])); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Optional notes..."><?php echo htmlspecialchars($record['notes']); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Record Info Display -->
                <div class="row">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Current Record Information</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Type:</strong><br>
                                        <span class="badge badge-<?php echo $recordType === 'income' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($recordType); ?>
                                        </span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Current Amount:</strong><br>
                                        RM <?php echo number_format($record['amount'], 2); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Date:</strong><br>
                                        <?php echo date('d M Y', strtotime($record['transaction_date'])); ?>
                                    </div>
                                    <div class="col-md-3">
                                        <strong><?php echo $recordType === 'income' ? 'Source' : 'Category'; ?>:</strong><br>
                                        <?php 
                                            if ($recordType === 'income') {
                                                echo htmlspecialchars($record['source']);
                                            } else {
                                                echo htmlspecialchars($record['category_name'] ?? 'Uncategorized');
                                            }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mt-4">
                    <button type="submit" name="update_record" class="btn btn-<?php echo $recordType === 'income' ? 'success' : 'danger'; ?> btn-lg">
                        <i class="fas fa-save"></i> Update Record
                    </button>
                    <a href="financial_data.php" class="btn btn-secondary btn-lg ml-2">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .card-header.bg-success {
        background-color: #28a745 !important;
    }
    
    .card-header.bg-danger {
        background-color: #dc3545 !important;
    }
    
    .form-group label .text-danger {
        font-size: 0.9em;
    }
    
    .badge {
        font-size: 0.9em;
        padding: 0.5em 0.75em;
    }
    
    .bg-light {
        background-color: #f8f9fa !important;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const amount = parseFloat(document.getElementById('amount').value);
        const description = document.getElementById('description').value.trim();
        const date = document.getElementById('transaction_date').value;
        
        if (amount <= 0) {
            alert('Amount must be greater than 0.');
            e.preventDefault();
            return false;
        }
        
        if (!description) {
            alert('Description is required.');
            e.preventDefault();
            return false;
        }
        
        if (!date) {
            alert('Date is required.');
            e.preventDefault();
            return false;
        }
        
        <?php if ($recordType === 'expense'): ?>
        const category = document.getElementById('category_id').value;
        if (!category) {
            alert('Please select a category.');
            e.preventDefault();
            return false;
        }
        <?php else: ?>
        const source = document.getElementById('source').value.trim();
        if (!source) {
            alert('Source is required.');
            e.preventDefault();
            return false;
        }
        <?php endif; ?>
        
        return true;
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            if (alert.classList.contains('alert-success')) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            }
        });
    }, 5000);
});
</script>

<?php include 'includes/footer.php'; ?>