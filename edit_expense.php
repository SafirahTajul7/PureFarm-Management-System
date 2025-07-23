<?php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check if expense ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No expense ID provided.";
    header('Location: expenses.php');
    exit;
}

$expense_id = intval($_GET['id']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate inputs
        $crop_id = isset($_POST['crop_id']) ? intval($_POST['crop_id']) : 0;
        $category = isset($_POST['category']) ? trim($_POST['category']) : '';
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';

        // Basic validation
        if ($crop_id <= 0) {
            throw new Exception("Please select a valid crop.");
        }
        
        if (empty($category)) {
            throw new Exception("Category cannot be empty.");
        }
        
        if ($amount <= 0) {
            throw new Exception("Amount must be greater than zero.");
        }
        
        // Update expense
        $stmt = $pdo->prepare("
            UPDATE crop_expenses 
            SET crop_id = :crop_id, 
                category = :category,
                amount = :amount,
                date = :date,
                description = :description
            WHERE id = :expense_id
        ");
        
        $result = $stmt->execute([
            'crop_id' => $crop_id,
            'category' => $category,
            'amount' => $amount,
            'date' => $date,
            'description' => $description,
            'expense_id' => $expense_id
        ]);
        
        if ($result) {
            $_SESSION['success_message'] = "Expense updated successfully!";
            header('Location: expenses.php');
            exit;
        } else {
            throw new Exception("Failed to update expense.");
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Fetch expense data
try {
    $stmt = $pdo->prepare("
        SELECT e.*, c.crop_name 
        FROM crop_expenses e
        JOIN crops c ON e.crop_id = c.id
        WHERE e.id = :expense_id
    ");
    
    $stmt->execute(['expense_id' => $expense_id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expense) {
        $_SESSION['error_message'] = "Expense not found.";
        header('Location: expenses.php');
        exit;
    }
    
    // Fetch all crops for dropdown
    $crops_stmt = $pdo->query("SELECT id, crop_name FROM crops ORDER BY crop_name");
    $crops = $crops_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch common expense categories for suggestions
    $categories_stmt = $pdo->query("
        SELECT DISTINCT category 
        FROM crop_expenses 
        ORDER BY category
    ");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    error_log("Error fetching expense data: " . $e->getMessage());
    $_SESSION['error_message'] = "Error retrieving expense data. Please try again.";
    header('Location: expenses.php');
    exit;
}

$pageTitle = 'Edit Expense';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-edit"></i> Edit Expense</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='expenses.php'">
                <i class="fas fa-arrow-left"></i> Back to Expenses
            </button>
        </div>
    </div>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-money-bill-alt"></i> Edit Expense Details</h3>
        </div>
        
        <form method="POST" action="" class="form">
            <div class="form-row">
                <div class="form-group">
                    <label for="crop_id">Crop:</label>
                    <select id="crop_id" name="crop_id" required>
                        <option value="">Select Crop</option>
                        <?php foreach ($crops as $crop): ?>
                            <option value="<?php echo $crop['id']; ?>" <?php echo ($crop['id'] == $expense['crop_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($crop['crop_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="category">Category:</label>
                    <input type="text" id="category" name="category" list="category-list" value="<?php echo htmlspecialchars($expense['category']); ?>" required>
                    <datalist id="category-list">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="amount">Amount ($):</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" value="<?php echo htmlspecialchars($expense['amount']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="date">Date:</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($expense['date']); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($expense['description']); ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Expense</button>
                <button type="button" class="btn btn-secondary" onclick="location.href='expenses.php'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.form {
    padding: 20px;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-danger {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
}
</style>

<?php include 'includes/footer.php'; ?>