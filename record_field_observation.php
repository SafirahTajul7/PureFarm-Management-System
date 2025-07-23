<?php
require_once 'includes/auth.php';
auth()->checkSupervisor(); // SUPERVISOR ACCESS ONLY

require_once 'includes/db.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $supervisor_id = $_SESSION['user_id'];
        $field_id = $_POST['field_id'];
        $observation_date = $_POST['observation_date'];
        $temperature = $_POST['temperature'];
        $humidity = $_POST['humidity'];
        $soil_moisture = $_POST['soil_moisture'];
        $weather_conditions = $_POST['weather_conditions'];
        $crop_stage = $_POST['crop_stage'];
        $pest_activity = $_POST['pest_activity'];
        $irrigation_status = $_POST['irrigation_status'];
        $notes = $_POST['notes'];
        $recommendations = $_POST['recommendations'];

        // Create field_observations table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS field_observations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                supervisor_id INT NOT NULL,
                field_id INT NOT NULL,
                observation_date DATE NOT NULL,
                temperature DECIMAL(5,2),
                humidity DECIMAL(5,2),
                soil_moisture DECIMAL(5,2),
                weather_conditions VARCHAR(100),
                crop_stage VARCHAR(100),
                pest_activity VARCHAR(255),
                irrigation_status VARCHAR(100),
                notes TEXT,
                recommendations TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        // Insert observation
        $stmt = $pdo->prepare("
            INSERT INTO field_observations 
            (supervisor_id, field_id, observation_date, temperature, humidity, soil_moisture, 
             weather_conditions, crop_stage, pest_activity, irrigation_status, notes, recommendations)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $supervisor_id, $field_id, $observation_date, $temperature, $humidity, $soil_moisture,
            $weather_conditions, $crop_stage, $pest_activity, $irrigation_status, $notes, $recommendations
        ]);

        $success_message = "Field observation recorded successfully!";
        
        // Clear form data
        $_POST = [];
        
    } catch(PDOException $e) {
        $error_message = "Error recording observation: " . $e->getMessage();
    }
}

// Get supervisor's assigned fields
try {
    $supervisor_id = $_SESSION['user_id'];
    
    // Get assigned fields or all fields if no assignments exist
    $fields_stmt = $pdo->prepare("
        SELECT DISTINCT f.id, f.field_name, f.location 
        FROM fields f
        LEFT JOIN staff_field_assignments sfa ON f.id = sfa.field_id 
        WHERE sfa.staff_id = ? AND sfa.status = 'active'
        OR NOT EXISTS (
            SELECT 1 FROM staff_field_assignments 
            WHERE staff_id = ? AND status = 'active'
        )
        ORDER BY f.field_name
    ");
    $fields_stmt->execute([$supervisor_id, $supervisor_id]);
    $assigned_fields = $fields_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($assigned_fields)) {
        // Fallback: get all fields
        $fields_stmt = $pdo->query("SELECT id, field_name, location FROM fields ORDER BY field_name");
        $assigned_fields = $fields_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $assigned_fields = [];
}

$pageTitle = 'Record Field Observation';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-clipboard-list"></i> Record Field Observation</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='supervisor_environmental.php'">
                <i class="fas fa-arrow-left"></i> Back to Environmental Monitoring
            </button>
        </div>
    </div>

    <?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <div class="form-container">
        <form method="POST" class="observation-form">
            <div class="form-section">
                <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="field_id"><i class="fas fa-map-marker-alt"></i> Field *</label>
                            <select name="field_id" id="field_id" class="form-control" required>
                                <option value="">Select Field</option>
                                <?php foreach($assigned_fields as $field): ?>
                                <option value="<?php echo $field['id']; ?>" 
                                        <?php echo (isset($_POST['field_id']) && $_POST['field_id'] == $field['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($field['field_name']); ?>
                                    <?php if($field['location']): ?> - <?php echo htmlspecialchars($field['location']); ?><?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="observation_date"><i class="fas fa-calendar"></i> Observation Date *</label>
                            <input type="date" name="observation_date" id="observation_date" class="form-control" 
                                   value="<?php echo isset($_POST['observation_date']) ? $_POST['observation_date'] : date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-thermometer-half"></i> Environmental Conditions</h3>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="temperature"><i class="fas fa-thermometer-half"></i> Temperature (°C)</label>
                            <input type="number" name="temperature" id="temperature" class="form-control" 
                                   step="0.1" min="-10" max="60" 
                                   value="<?php echo isset($_POST['temperature']) ? $_POST['temperature'] : ''; ?>"
                                   placeholder="e.g., 25.5">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="humidity"><i class="fas fa-tint"></i> Humidity (%)</label>
                            <input type="number" name="humidity" id="humidity" class="form-control" 
                                   step="0.1" min="0" max="100" 
                                   value="<?php echo isset($_POST['humidity']) ? $_POST['humidity'] : ''; ?>"
                                   placeholder="e.g., 65.0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="soil_moisture"><i class="fas fa-water"></i> Soil Moisture (%)</label>
                            <input type="number" name="soil_moisture" id="soil_moisture" class="form-control" 
                                   step="0.1" min="0" max="100" 
                                   value="<?php echo isset($_POST['soil_moisture']) ? $_POST['soil_moisture'] : ''; ?>"
                                   placeholder="e.g., 45.0">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="weather_conditions"><i class="fas fa-cloud-sun"></i> Weather Conditions</label>
                            <select name="weather_conditions" id="weather_conditions" class="form-control">
                                <option value="">Select Weather</option>
                                <option value="Sunny" <?php echo (isset($_POST['weather_conditions']) && $_POST['weather_conditions'] == 'Sunny') ? 'selected' : ''; ?>>Sunny</option>
                                <option value="Partly Cloudy" <?php echo (isset($_POST['weather_conditions']) && $_POST['weather_conditions'] == 'Partly Cloudy') ? 'selected' : ''; ?>>Partly Cloudy</option>
                                <option value="Cloudy" <?php echo (isset($_POST['weather_conditions']) && $_POST['weather_conditions'] == 'Cloudy') ? 'selected' : ''; ?>>Cloudy</option>
                                <option value="Light Rain" <?php echo (isset($_POST['weather_conditions']) && $_POST['weather_conditions'] == 'Light Rain') ? 'selected' : ''; ?>>Light Rain</option>
                                <option value="Heavy Rain" <?php echo (isset($_POST['weather_conditions']) && $_POST['weather_conditions'] == 'Heavy Rain') ? 'selected' : ''; ?>>Heavy Rain</option>
                                <option value="Windy" <?php echo (isset($_POST['weather_conditions']) && $_POST['weather_conditions'] == 'Windy') ? 'selected' : ''; ?>>Windy</option>
                                <option value="Hot & Dry" <?php echo (isset($_POST['weather_conditions']) && $_POST['weather_conditions'] == 'Hot & Dry') ? 'selected' : ''; ?>>Hot & Dry</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="irrigation_status"><i class="fas fa-shower"></i> Irrigation Status</label>
                            <select name="irrigation_status" id="irrigation_status" class="form-control">
                                <option value="">Select Status</option>
                                <option value="Not Required" <?php echo (isset($_POST['irrigation_status']) && $_POST['irrigation_status'] == 'Not Required') ? 'selected' : ''; ?>>Not Required</option>
                                <option value="Required Soon" <?php echo (isset($_POST['irrigation_status']) && $_POST['irrigation_status'] == 'Required Soon') ? 'selected' : ''; ?>>Required Soon</option>
                                <option value="Required Today" <?php echo (isset($_POST['irrigation_status']) && $_POST['irrigation_status'] == 'Required Today') ? 'selected' : ''; ?>>Required Today</option>
                                <option value="Urgent" <?php echo (isset($_POST['irrigation_status']) && $_POST['irrigation_status'] == 'Urgent') ? 'selected' : ''; ?>>Urgent</option>
                                <option value="Recently Irrigated" <?php echo (isset($_POST['irrigation_status']) && $_POST['irrigation_status'] == 'Recently Irrigated') ? 'selected' : ''; ?>>Recently Irrigated</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-seedling"></i> Crop & Field Conditions</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="crop_stage"><i class="fas fa-leaf"></i> Crop Growth Stage</label>
                            <select name="crop_stage" id="crop_stage" class="form-control">
                                <option value="">Select Stage</option>
                                <option value="Planting" <?php echo (isset($_POST['crop_stage']) && $_POST['crop_stage'] == 'Planting') ? 'selected' : ''; ?>>Planting</option>
                                <option value="Germination" <?php echo (isset($_POST['crop_stage']) && $_POST['crop_stage'] == 'Germination') ? 'selected' : ''; ?>>Germination</option>
                                <option value="Seedling" <?php echo (isset($_POST['crop_stage']) && $_POST['crop_stage'] == 'Seedling') ? 'selected' : ''; ?>>Seedling</option>
                                <option value="Vegetative" <?php echo (isset($_POST['crop_stage']) && $_POST['crop_stage'] == 'Vegetative') ? 'selected' : ''; ?>>Vegetative Growth</option>
                                <option value="Flowering" <?php echo (isset($_POST['crop_stage']) && $_POST['crop_stage'] == 'Flowering') ? 'selected' : ''; ?>>Flowering</option>
                                <option value="Fruiting" <?php echo (isset($_POST['crop_stage']) && $_POST['crop_stage'] == 'Fruiting') ? 'selected' : ''; ?>>Fruiting</option>
                                <option value="Maturity" <?php echo (isset($_POST['crop_stage']) && $_POST['crop_stage'] == 'Maturity') ? 'selected' : ''; ?>>Maturity</option>
                                <option value="Harvest Ready" <?php echo (isset($_POST['crop_stage']) && $_POST['crop_stage'] == 'Harvest Ready') ? 'selected' : ''; ?>>Harvest Ready</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="pest_activity"><i class="fas fa-bug"></i> Pest Activity</label>
                            <input type="text" name="pest_activity" id="pest_activity" class="form-control" 
                                   value="<?php echo isset($_POST['pest_activity']) ? htmlspecialchars($_POST['pest_activity']) : ''; ?>"
                                   placeholder="e.g., None observed, Aphids on leaves, etc.">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-sticky-note"></i> Notes & Recommendations</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="notes"><i class="fas fa-clipboard"></i> Field Observations</label>
                            <textarea name="notes" id="notes" class="form-control" rows="4"
                                      placeholder="Describe current field conditions, plant health, any concerns observed..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="recommendations"><i class="fas fa-lightbulb"></i> Recommendations</label>
                            <textarea name="recommendations" id="recommendations" class="form-control" rows="4"
                                      placeholder="Recommended actions, required interventions, maintenance needs..."><?php echo isset($_POST['recommendations']) ? htmlspecialchars($_POST['recommendations']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Record Observation
                </button>
                <button type="button" class="btn btn-secondary" onclick="location.href='supervisor_environmental.php'">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.form-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 30px;
    margin-bottom: 30px;
}

.observation-form .form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #dee2e6;
}

.observation-form .form-section:last-of-type {
    border-bottom: none;
}

.observation-form .form-section h3 {
    color: #333;
    margin-bottom: 20px;
    font-size: 18px;
    font-weight: 600;
}

.observation-form .form-section h3 i {
    color: #20c997;
    margin-right: 8px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    font-weight: 500;
    color: #555;
    margin-bottom: 8px;
    display: block;
}

.form-group label i {
    margin-right: 5px;
    color: #6c757d;
}

.form-control {
    border-radius: 6px;
    border: 1px solid #ddd;
    padding: 10px 12px;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #20c997;
    box-shadow: 0 0 0 0.2rem rgba(32, 201, 151, 0.25);
}

.form-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
    text-align: right;
}

.form-actions .btn {
    margin-left: 10px;
    padding: 10px 20px;
}

.alert {
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-container {
        padding: 20px;
    }
    
    .form-actions {
        text-align: center;
    }
    
    .form-actions .btn {
        margin: 5px;
        width: 48%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>