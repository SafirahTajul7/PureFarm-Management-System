<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/soil_test_manager.php';

// Check if user is admin
auth()->checkAdmin();

// Create soil test manager
$soilTestManager = new SoilTestManager($pdo);

// Initialize variables for form values
$field_id = '';
$test_date = date('Y-m-d'); // Default to current date
$ph_level = '';
$moisture_percentage = '';
$temperature = '';
$nitrogen_level = '';
$phosphorus_level = '';
$potassium_level = '';
$organic_matter = '';
$notes = '';

// Array to store validation errors
$errors = [];

// Get all fields for dropdown
// Get all fields for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, field_name, location FROM fields ORDER BY field_name ASC");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($fields)) {
        error_log("No fields found in the database");
    }
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data
    $field_id = isset($_POST['field_id']) ? trim($_POST['field_id']) : '';
    $test_date = isset($_POST['test_date']) ? trim($_POST['test_date']) : '';
    $ph_level = isset($_POST['ph_level']) ? trim($_POST['ph_level']) : '';
    $moisture_percentage = isset($_POST['moisture_percentage']) ? trim($_POST['moisture_percentage']) : '';
    $temperature = isset($_POST['temperature']) ? trim($_POST['temperature']) : '';
    $nitrogen_level = isset($_POST['nitrogen_level']) ? trim($_POST['nitrogen_level']) : '';
    $phosphorus_level = isset($_POST['phosphorus_level']) ? trim($_POST['phosphorus_level']) : '';
    $potassium_level = isset($_POST['potassium_level']) ? trim($_POST['potassium_level']) : '';
    $organic_matter = isset($_POST['organic_matter']) ? trim($_POST['organic_matter']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    // Validate form data
    $errors = validateSoilTestData([
        'field_id' => $field_id,
        'test_date' => $test_date,
        'ph_level' => $ph_level,
        'moisture_percentage' => $moisture_percentage,
        'organic_matter' => $organic_matter
    ]);

    // If no errors, insert the soil test into the database
    if (empty($errors)) {
        $result = $soilTestManager->addSoilTest([
            'field_id' => $field_id,
            'test_date' => $test_date,
            'ph_level' => $ph_level,
            'moisture_percentage' => $moisture_percentage,
            'temperature' => $temperature,
            'nitrogen_level' => $nitrogen_level,
            'phosphorus_level' => $phosphorus_level,
            'potassium_level' => $potassium_level,
            'organic_matter' => $organic_matter,
            'notes' => $notes
        ]);

        if ($result) {
            // Redirect to the soil tests page with success message
            header('Location: soil_tests.php?success=1');
            exit;
        } else {
            $errors['db'] = 'Database error occurred. Please try again.';
        }
    }
}

$pageTitle = 'Add Soil Test';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-flask"></i> Add Soil Test</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='soil_tests.php'">
                <i class="fas fa-arrow-left"></i> Back to Soil Tests
            </button>
        </div>
    </div>

    <?php if (isset($errors['db'])): ?>
        <div class="alert alert-danger">
            <?php echo $errors['db']; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($fields)): ?>
        <div class="alert alert-warning">
            No fields are available in the database. Please <a href="add_field.php">add fields</a> first.
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" action="add_soil_test.php">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="field_id" class="form-label">Field <span class="text-danger">*</span></label>
                        <select class="form-select <?php echo isset($errors['field_id']) ? 'is-invalid' : ''; ?>" 
                                id="field_id" name="field_id" required>
                            <option value="">Select Field</option>
                            <?php foreach ($fields as $field): ?>
                                <option value="<?php echo $field['id']; ?>" 
                                        <?php echo $field_id == $field['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($field['field_name'] . ' (' . $field['location'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['field_id'])): ?>
                            <div class="invalid-feedback">
                                <?php echo $errors['field_id']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="test_date" class="form-label">Test Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control <?php echo isset($errors['test_date']) ? 'is-invalid' : ''; ?>" 
                               id="test_date" name="test_date" value="<?php echo htmlspecialchars($test_date); ?>" required>
                        <?php if (isset($errors['test_date'])): ?>
                            <div class="invalid-feedback">
                                <?php echo $errors['test_date']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="ph_level" class="form-label">pH Level</label>
                        <input type="number" step="0.1" min="0" max="14" 
                               class="form-control <?php echo isset($errors['ph_level']) ? 'is-invalid' : ''; ?>" 
                               id="ph_level" name="ph_level" value="<?php echo htmlspecialchars($ph_level); ?>"
                               placeholder="Enter pH level (0-14)">
                        <?php if (isset($errors['ph_level'])): ?>
                            <div class="invalid-feedback">
                                <?php echo $errors['ph_level']; ?>
                            </div>
                        <?php endif; ?>
                        <small class="form-text text-muted">Optimal range: 6.0-7.5</small>
                    </div>
                    <div class="col-md-4">
                        <label for="moisture_percentage" class="form-label">Moisture (%)</label>
                        <input type="number" step="0.1" min="0" max="100" 
                               class="form-control <?php echo isset($errors['moisture_percentage']) ? 'is-invalid' : ''; ?>" 
                               id="moisture_percentage" name="moisture_percentage" 
                               value="<?php echo htmlspecialchars($moisture_percentage); ?>"
                               placeholder="Enter moisture percentage">
                        <?php if (isset($errors['moisture_percentage'])): ?>
                            <div class="invalid-feedback">
                                <?php echo $errors['moisture_percentage']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label for="temperature" class="form-label">Temperature (°C)</label>
                        <input type="number" step="0.1" 
                               class="form-control" 
                               id="temperature" name="temperature" 
                               value="<?php echo htmlspecialchars($temperature); ?>"
                               placeholder="Enter soil temperature">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="nitrogen_level" class="form-label">Nitrogen Level</label>
                        <select class="form-select" id="nitrogen_level" name="nitrogen_level">
                            <option value="" <?php echo empty($nitrogen_level) ? 'selected' : ''; ?>>Select Level</option>
                            <option value="Low" <?php echo $nitrogen_level === 'Low' ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo $nitrogen_level === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo $nitrogen_level === 'High' ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="phosphorus_level" class="form-label">Phosphorus Level</label>
                        <select class="form-select" id="phosphorus_level" name="phosphorus_level">
                            <option value="" <?php echo empty($phosphorus_level) ? 'selected' : ''; ?>>Select Level</option>
                            <option value="Low" <?php echo $phosphorus_level === 'Low' ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo $phosphorus_level === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo $phosphorus_level === 'High' ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="potassium_level" class="form-label">Potassium Level</label>
                        <select class="form-select" id="potassium_level" name="potassium_level">
                            <option value="" <?php echo empty($potassium_level) ? 'selected' : ''; ?>>Select Level</option>
                            <option value="Low" <?php echo $potassium_level === 'Low' ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo $potassium_level === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo $potassium_level === 'High' ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="organic_matter" class="form-label">Organic Matter (%)</label>
                        <input type="number" step="0.1" min="0" max="100" 
                               class="form-control <?php echo isset($errors['organic_matter']) ? 'is-invalid' : ''; ?>" 
                               id="organic_matter" name="organic_matter" 
                               value="<?php echo htmlspecialchars($organic_matter); ?>"
                               placeholder="Enter organic matter percentage">
                        <?php if (isset($errors['organic_matter'])): ?>
                            <div class="invalid-feedback">
                                <?php echo $errors['organic_matter']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4" 
                              placeholder="Enter any additional notes or observations"><?php echo htmlspecialchars($notes); ?></textarea>
                </div>

                <div class="text-end">
                    <button type="reset" class="btn btn-secondary">Reset</button>
                    <button type="submit" class="btn btn-primary">Save Soil Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .main-content {
        padding-bottom: 60px;
    }
    
    .card {
        margin-top: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .card-body {
        padding: 25px;
    }
    
    .form-label {
        font-weight: 500;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Add Soil Test page loaded');
    
    // Date validation - prevent future dates
    const testDateInput = document.getElementById('test_date');
    if (testDateInput) {
        const today = new Date().toISOString().split('T')[0];
        testDateInput.setAttribute('max', today);
    }
    
    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(event) {
        let isValid = true;
        
        // Check required fields
        const fieldId = document.getElementById('field_id');
        if (!fieldId.value) {
            fieldId.classList.add('is-invalid');
            isValid = false;
        } else {
            fieldId.classList.remove('is-invalid');
        }
        
        const testDate = document.getElementById('test_date');
        if (!testDate.value) {
            testDate.classList.add('is-invalid');
            isValid = false;
        } else {
            testDate.classList.remove('is-invalid');
        }
        
        // Validate pH if provided
        const phLevel = document.getElementById('ph_level');
        if (phLevel.value && (phLevel.value < 0 || phLevel.value > 14)) {
            phLevel.classList.add('is-invalid');
            isValid = false;
        } else {
            phLevel.classList.remove('is-invalid');
        }
        
        if (!isValid) {
            event.preventDefault();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>