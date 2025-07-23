<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is admin
auth()->checkAdmin();

// Get reading ID from URL
$reading_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$reading_id) {
    // Redirect to main page if no ID provided
    header("Location: soil_moisture.php");
    exit();
}

// Fetch all fields for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, field_name FROM fields ORDER BY field_name ASC");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

// Fetch the moisture reading record
try {
    $stmt = $pdo->prepare("
        SELECT * FROM soil_moisture WHERE id = ?
    ");
    $stmt->execute([$reading_id]);
    $reading = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reading) {
        // Redirect if reading not found
        header("Location: soil_moisture.php");
        exit();
    }
    
} catch(PDOException $e) {
    error_log("Error fetching moisture reading: " . $e->getMessage());
    header("Location: soil_moisture.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $field_id = $_POST['field_id'] ?? null;
    $reading_date = $_POST['reading_date'] ?? null;
    $moisture_percentage = $_POST['moisture_percentage'] ?? null;
    $reading_depth = $_POST['reading_depth'] ?? null;
    $reading_method = $_POST['reading_method'] ?? null;
    $notes = $_POST['notes'] ?? null;
    
    // Validate required fields
    $errors = [];
    
    if (empty($field_id)) {
        $errors[] = 'Field is required';
    }
    
    if (empty($reading_date)) {
        $errors[] = 'Reading date is required';
    }
    
    if (empty($moisture_percentage)) {
        $errors[] = 'Moisture percentage is required';
    } elseif (!is_numeric($moisture_percentage) || $moisture_percentage < 0 || $moisture_percentage > 100) {
        $errors[] = 'Moisture percentage must be a number between 0 and 100';
    }
    
    // If no errors, update database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE soil_moisture 
                SET field_id = ?, reading_date = ?, moisture_percentage = ?, 
                    reading_depth = ?, reading_method = ?, notes = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $field_id,
                $reading_date,
                $moisture_percentage,
                $reading_depth,
                $reading_method,
                $notes,
                $reading_id
            ]);
            
            // Redirect to main page with success message
            header("Location: soil_moisture.php?updated=1");
            exit();
            
        } catch(PDOException $e) {
            error_log("Error updating soil moisture record: " . $e->getMessage());
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Set page title and include header
$pageTitle = 'Edit Moisture Reading';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-tint"></i> Edit Moisture Reading</h2>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h5>Edit Moisture Reading</h5>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="field_id" class="form-label">Field <span class="text-danger">*</span></label>
                        <select class="form-select" id="field_id" name="field_id" required>
                            <option value="">Select a field</option>
                            <?php foreach($fields as $field): ?>
                                <option value="<?php echo $field['id']; ?>" <?php echo (isset($_POST['field_id']) ? $_POST['field_id'] : $reading['field_id']) == $field['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($field['field_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="reading_date" class="form-label">Reading Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="reading_date" name="reading_date" value="<?php echo isset($_POST['reading_date']) ? $_POST['reading_date'] : $reading['reading_date']; ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="moisture_percentage" class="form-label">Moisture Percentage (%) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="moisture_percentage" name="moisture_percentage" value="<?php echo isset($_POST['moisture_percentage']) ? $_POST['moisture_percentage'] : $reading['moisture_percentage']; ?>" required min="0" max="100" step="0.1">
                    </div>
                    <div class="col-md-4">
                        <label for="reading_depth" class="form-label">Reading Depth (cm)</label>
                        <input type="text" class="form-control" id="reading_depth" name="reading_depth" value="<?php echo isset($_POST['reading_depth']) ? $_POST['reading_depth'] : $reading['reading_depth']; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="reading_method" class="form-label">Reading Method</label>
                        <select class="form-select" id="reading_method" name="reading_method">
                            <option value="">Select a method</option>
                            <option value="Soil Moisture Sensor" <?php echo (isset($_POST['reading_method']) ? $_POST['reading_method'] : $reading['reading_method']) == 'Soil Moisture Sensor' ? 'selected' : ''; ?>>Soil Moisture Sensor</option>
                            <option value="Manual Check" <?php echo (isset($_POST['reading_method']) ? $_POST['reading_method'] : $reading['reading_method']) == 'Manual Check' ? 'selected' : ''; ?>>Manual Check</option>
                            <option value="Tensiometer" <?php echo (isset($_POST['reading_method']) ? $_POST['reading_method'] : $reading['reading_method']) == 'Tensiometer' ? 'selected' : ''; ?>>Tensiometer</option>
                            <option value="TDR Probe" <?php echo (isset($_POST['reading_method']) ? $_POST['reading_method'] : $reading['reading_method']) == 'TDR Probe' ? 'selected' : ''; ?>>TDR Probe</option>
                            <option value="Laboratory Analysis" <?php echo (isset($_POST['reading_method']) ? $_POST['reading_method'] : $reading['reading_method']) == 'Laboratory Analysis' ? 'selected' : ''; ?>>Laboratory Analysis</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? $_POST['notes'] : $reading['notes']; ?></textarea>
                </div>
                
                <div class="mb-3">
                    <button type="submit" class="btn btn-primary">Update Reading</button>
                    <a href="soil_moisture.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>