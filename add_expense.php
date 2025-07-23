<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

$success_message = '';
$error_message = '';

// Fetch crops for dropdown
try {
    $crops = $pdo->query("SELECT id, crop_name FROM crops ORDER BY crop_name")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching crops: " . $e->getMessage());
    $crops = [];
}

// Define expense categories - using hard-coded values based on database screenshot
$categories = ['Seeds', 'Fertilizer', 'Pesticides', 'Irrigation', 'Labor', 'Equipment', 'Other'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $crop_id = $_POST['crop_id'] ?? null;
    $date = $_POST['date'] ?? date('Y-m-d');
    $amount = $_POST['amount'] ?? 0;
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Validate inputs
    $errors = [];
    if (empty($crop_id)) {
        $errors[] = "Crop is required";
    }
    if (empty($date)) {
        $errors[] = "Date is required";
    }
    if (!is_numeric($amount) || $amount <= 0) {
        $errors[] = "Amount must be a positive number";
    }
    if (empty($category)) {
        $errors[] = "Category is required";
    }
    
    if (empty($errors)) {
        try {
            // Insert directly using the column names we see in the database
            $stmt = $pdo->prepare("
                INSERT INTO crop_expenses (crop_id, date, amount, category, description)
                VALUES (:crop_id, :date, :amount, :category, :description)
            ");
            
            $stmt->execute([
                'crop_id' => $crop_id,
                'date' => $date,
                'amount' => $amount,
                'category' => $category,
                'description' => $description
            ]);
            
            $success_message = "Expense has been successfully added!";
            
            // Clear form after successful submission
            $crop_id = '';
            $date = date('Y-m-d');
            $amount = '';
            $category = '';
            $description = '';
            
        } catch(PDOException $e) {
            error_log("Error adding expense: " . $e->getMessage());
            $error_message = "There was an error adding the expense: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

$pageTitle = 'Add Expense';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-minus-circle"></i> Add Expense</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='financial_analysis.php'">
                <i class="fas fa-arrow-left"></i> Back to Financial Analysis
            </button>
        </div>
    </div>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h5>Expense Details</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="crop_id" class="form-label">Crop <span class="text-danger">*</span></label>
                        <select class="form-select" id="crop_id" name="crop_id" required>
                            <option value="">-- Select Crop --</option>
                            <?php foreach ($crops as $crop): ?>
                                <option value="<?php echo $crop['id']; ?>" <?php echo (isset($crop_id) && $crop_id == $crop['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($crop['crop_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date" name="date" value="<?php echo $date ?? date('Y-m-d'); ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="amount" class="form-label">Amount ($) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" value="<?php echo $amount ?? ''; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo (isset($category) && $category == $cat) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo $description ?? ''; ?></textarea>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="reset" class="btn btn-secondary me-md-2">Reset</button>
                    <button type="submit" name="submit" class="btn btn-primary">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.main-content {
    padding-bottom: 60px;
    min-height: calc(100vh - 60px);
}

footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    padding: 15px 0;
    background-color: #f8f9fa;
    text-align: center;
    border-top: 1px solid #dee2e6;
    height: 50px;
}

body {
    padding-bottom: 60px;
}
</style>

<?php include 'includes/footer.php'; ?>