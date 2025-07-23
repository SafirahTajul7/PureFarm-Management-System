<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is logged in and authorized
auth()->checkAdmin();

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: soil_nutrients.php?error=invalid_id");
    exit();
}

$id = intval($_GET['id']);

// Fetch the existing record
try {
    $stmt = $pdo->prepare("
        SELECT sn.*, f.field_name 
        FROM soil_nutrients sn
        JOIN fields f ON sn.field_id = f.id
        WHERE sn.id = ?
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        header("Location: soil_nutrients.php?error=record_not_found");
        exit();
    }
} catch(PDOException $e) {
    error_log("Error fetching soil nutrient record: " . $e->getMessage());
    header("Location: soil_nutrients.php?error=database_error");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $field_id = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;
    $test_date = isset($_POST['test_date']) ? $_POST['test_date'] : '';
    $nitrogen = isset($_POST['nitrogen']) ? filter_var($_POST['nitrogen'], FILTER_VALIDATE_FLOAT) : null;
    $phosphorus = isset($_POST['phosphorus']) ? filter_var($_POST['phosphorus'], FILTER_VALIDATE_FLOAT) : null;
    $potassium = isset($_POST['potassium']) ? filter_var($_POST['potassium'], FILTER_VALIDATE_FLOAT) : null;
    $ph_level = isset($_POST['ph_level']) ? filter_var($_POST['ph_level'], FILTER_VALIDATE_FLOAT) : null;
    $organic_matter = isset($_POST['organic_matter']) ? filter_var($_POST['organic_matter'], FILTER_VALIDATE_FLOAT) : null;
    $calcium = isset($_POST['calcium']) ? filter_var($_POST['calcium'], FILTER_VALIDATE_FLOAT) : null;
    $magnesium = isset($_POST['magnesium']) ? filter_var($_POST['magnesium'], FILTER_VALIDATE_FLOAT) : null;
    $sulfur = isset($_POST['sulfur']) ? filter_var($_POST['sulfur'], FILTER_VALIDATE_FLOAT) : null;
    $test_method = isset($_POST['test_method']) ? trim($_POST['test_method']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    $errors = [];
    
    // Validation
    if (empty($field_id)) {
        $errors[] = "Field is required";
    }
    
    if (empty($test_date)) {
        $errors[] = "Test date is required";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $test_date)) {
        $errors[] = "Test date must be in YYYY-MM-DD format";
    }
    
    if ($nitrogen === false || $nitrogen < 0) {
        $errors[] = "Nitrogen must be a positive number";
    }
    
    if ($phosphorus === false || $phosphorus < 0) {
        $errors[] = "Phosphorus must be a positive number";
    }
    
    if ($potassium === false || $potassium < 0) {
        $errors[] = "Potassium must be a positive number";
    }
    
    if ($ph_level === false || $ph_level < 0 || $ph_level > 14) {
        $errors[] = "pH level must be between 0 and 14";
    }
    
    if ($organic_matter === false || $organic_matter < 0 || $organic_matter > 100) {
        $errors[] = "Organic matter must be between 0% and 100%";
    }
    
    if (empty($test_method)) {
        $errors[] = "Test method is required";
    }
    
    // If no errors, update the database
    if (empty($errors)) {
        try {
            $sql = "UPDATE soil_nutrients SET 
                    field_id = ?, 
                    test_date = ?, 
                    nitrogen = ?, 
                    phosphorus = ?, 
                    potassium = ?, 
                    ph_level = ?, 
                    organic_matter = ?, 
                    calcium = ?, 
                    magnesium = ?, 
                    sulfur = ?, 
                    test_method = ?, 
                    notes = ?
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $field_id, 
                $test_date, 
                $nitrogen, 
                $phosphorus, 
                $potassium, 
                $ph_level, 
                $organic_matter, 
                $calcium, 
                $magnesium, 
                $sulfur, 
                $test_method, 
                $notes,
                $id
            ]);
            
            // Redirect to soil nutrients page with success message
            header("Location: soil_nutrients.php?updated=1");
            exit();
            
        } catch(PDOException $e) {
            error_log("Error updating soil nutrient data: " . $e->getMessage());
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
} else {
    // Pre-fill form with existing data
    $_POST = $record;
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

// Available test methods for dropdown
$test_methods = [
    'Lab Analysis',
    'Soil Test Kit',
    'Handheld Device',
    'Digital Sensor',
    'Optical Sensor'
];

// Set page title and include header
$pageTitle = 'Edit Nutrient Reading';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-flask"></i> Edit Soil Nutrient Reading</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="soil_nutrients.php">Soil Nutrients</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Reading</li>
            </ol>
        </nav>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-edit me-2"></i>Edit Soil Nutrient Test for <?php echo htmlspecialchars($record['field_name']); ?>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="field_id" class="form-label">Field <span class="text-danger">*</span></label>
                        <select class="form-select" id="field_id" name="field_id" required>
                            <option value="">Select a field</option>
                            <?php foreach ($fields as $field): ?>
                            <option value="<?php echo $field['id']; ?>" <?php echo ($_POST['field_id'] == $field['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($field['field_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="test_date" class="form-label">Test Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="test_date" name="test_date" 
                               value="<?php echo htmlspecialchars($_POST['test_date']); ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="nitrogen" class="form-label">Nitrogen (N) in ppm <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control" id="nitrogen" name="nitrogen" 
                               value="<?php echo htmlspecialchars($_POST['nitrogen']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="phosphorus" class="form-label">Phosphorus (P) in ppm <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control" id="phosphorus" name="phosphorus" 
                               value="<?php echo htmlspecialchars($_POST['phosphorus']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="potassium" class="form-label">Potassium (K) in ppm <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="form-control" id="potassium" name="potassium" 
                               value="<?php echo htmlspecialchars($_POST['potassium']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="ph_level" class="form-label">pH Level <span class="text-danger">*</span></label>
                        <input type="number" step="0.1" min="0" max="14" class="form-control" id="ph_level" name="ph_level" 
                               value="<?php echo htmlspecialchars($_POST['ph_level']); ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="organic_matter" class="form-label">Organic Matter (%)</label>
                        <input type="number" step="0.01" min="0" max="100" class="form-control" id="organic_matter" name="organic_matter" 
                               value="<?php echo htmlspecialchars($_POST['organic_matter']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="calcium" class="form-label">Calcium (Ca) in ppm</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="calcium" name="calcium" 
                               value="<?php echo htmlspecialchars($_POST['calcium']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="magnesium" class="form-label">Magnesium (Mg) in ppm</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="magnesium" name="magnesium" 
                               value="<?php echo htmlspecialchars($_POST['magnesium']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="sulfur" class="form-label">Sulfur (S) in ppm</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="sulfur" name="sulfur" 
                               value="<?php echo htmlspecialchars($_POST['sulfur']); ?>">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="test_method" class="form-label">Test Method <span class="text-danger">*</span></label>
                        <select class="form-select" id="test_method" name="test_method" required>
                            <option value="">Select test method</option>
                            <?php foreach ($test_methods as $method): ?>
                            <option value="<?php echo $method; ?>" <?php echo ($_POST['test_method'] == $method) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($method); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($_POST['notes']); ?></textarea>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="soil_nutrients.php" class="btn btn-secondary me-md-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Nutrient Reading</button>
                </div>
            </form>
        </div>
        <div class="card-footer text-muted">
            <small><span class="text-danger">*</span> Required fields</small>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>