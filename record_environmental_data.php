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
        $reading_date = $_POST['reading_date'];
        $reading_time = $_POST['reading_time'];
        $temperature = $_POST['temperature'];
        $humidity = $_POST['humidity'];
        $wind_speed = $_POST['wind_speed'];
        $wind_direction = $_POST['wind_direction'];
        $barometric_pressure = $_POST['barometric_pressure'];
        $rainfall = $_POST['rainfall'];
        $uv_index = $_POST['uv_index'];
        $visibility = $_POST['visibility'];
        $notes = $_POST['notes'];

        // Create environmental_readings table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS environmental_readings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                supervisor_id INT NOT NULL,
                field_id INT NOT NULL,
                reading_date DATE NOT NULL,
                reading_time TIME NOT NULL,
                temperature DECIMAL(5,2),
                humidity DECIMAL(5,2),
                wind_speed DECIMAL(5,2),
                wind_direction VARCHAR(20),
                barometric_pressure DECIMAL(8,2),
                rainfall DECIMAL(6,2),
                uv_index DECIMAL(3,1),
                visibility DECIMAL(5,2),
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        // Insert environmental reading
        $stmt = $pdo->prepare("
            INSERT INTO environmental_readings 
            (supervisor_id, field_id, reading_date, reading_time, temperature, humidity, 
             wind_speed, wind_direction, barometric_pressure, rainfall, uv_index, visibility, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $supervisor_id, $field_id, $reading_date, $reading_time, $temperature, $humidity,
            $wind_speed, $wind_direction, $barometric_pressure, $rainfall, $uv_index, $visibility, $notes
        ]);

        $success_message = "Environmental data recorded successfully!";
        
        // Clear form data
        $_POST = [];
        
    } catch(PDOException $e) {
        $error_message = "Error recording environmental data: " . $e->getMessage();
    }
}

// Get supervisor's assigned fields
try {
    $supervisor_id = $_SESSION['user_id'];
    
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
        $fields_stmt = $pdo->query("SELECT id, field_name, location FROM fields ORDER BY field_name");
        $assigned_fields = $fields_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $assigned_fields = [];
}

$pageTitle = 'Record Environmental Data';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-thermometer-half"></i> Record Environmental Data</h2>
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
        <form method="POST" class="environmental-form">
            <div class="form-section">
                <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                <div class="row">
                    <div class="col-md-4">
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
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="reading_date"><i class="fas fa-calendar"></i> Reading Date *</label>
                            <input type="date" name="reading_date" id="reading_date" class="form-control" 
                                   value="<?php echo isset($_POST['reading_date']) ? $_POST['reading_date'] : date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="reading_time"><i class="fas fa-clock"></i> Reading Time *</label>
                            <input type="time" name="reading_time" id="reading_time" class="form-control" 
                                   value="<?php echo isset($_POST['reading_time']) ? $_POST['reading_time'] : date('H:i'); ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-thermometer-half"></i> Temperature & Humidity</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="temperature"><i class="fas fa-thermometer-half"></i> Temperature (°C) *</label>
                            <div class="input-group">
                                <input type="number" name="temperature" id="temperature" class="form-control" 
                                       step="0.1" min="-10" max="60" required
                                       value="<?php echo isset($_POST['temperature']) ? $_POST['temperature'] : ''; ?>"
                                       placeholder="e.g., 25.5">
                                <div class="input-group-append">
                                    <span class="input-group-text">°C</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="humidity"><i class="fas fa-tint"></i> Humidity (%) *</label>
                            <div class="input-group">
                                <input type="number" name="humidity" id="humidity" class="form-control" 
                                       step="0.1" min="0" max="100" required
                                       value="<?php echo isset($_POST['humidity']) ? $_POST['humidity'] : ''; ?>"
                                       placeholder="e.g., 65.0">
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-wind"></i> Wind Conditions</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="wind_speed"><i class="fas fa-wind"></i> Wind Speed (km/h)</label>
                            <div class="input-group">
                                <input type="number" name="wind_speed" id="wind_speed" class="form-control" 
                                       step="0.1" min="0" max="200"
                                       value="<?php echo isset($_POST['wind_speed']) ? $_POST['wind_speed'] : ''; ?>"
                                       placeholder="e.g., 15.5">
                                <div class="input-group-append">
                                    <span class="input-group-text">km/h</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="wind_direction"><i class="fas fa-compass"></i> Wind Direction</label>
                            <select name="wind_direction" id="wind_direction" class="form-control">
                                <option value="">Select Direction</option>
                                <option value="N" <?php echo (isset($_POST['wind_direction']) && $_POST['wind_direction'] == 'N') ? 'selected' : ''; ?>>North (N)</option>
                                <option value="NE" <?php echo (isset($_POST['wind_direction']) && $_POST['wind_direction'] == 'NE') ? 'selected' : ''; ?>>Northeast (NE)</option>
                                <option value="E" <?php echo (isset($_POST['wind_direction']) && $_POST['wind_direction'] == 'E') ? 'selected' : ''; ?>>East (E)</option>
                                <option value="SE" <?php echo (isset($_POST['wind_direction']) && $_POST['wind_direction'] == 'SE') ? 'selected' : ''; ?>>Southeast (SE)</option>
                                <option value="S" <?php echo (isset($_POST['wind_direction']) && $_POST['wind_direction'] == 'S') ? 'selected' : ''; ?>>South (S)</option>
                                <option value="SW" <?php echo (isset($_POST['wind_direction']) && $_POST['wind_direction'] == 'SW') ? 'selected' : ''; ?>>Southwest (SW)</option>
                                <option value="W" <?php echo (isset($_POST['wind_direction']) && $_POST['wind_direction'] == 'W') ? 'selected' : ''; ?>>West (W)</option>
                                <option value="NW" <?php echo (isset($_POST['wind_direction']) && $_POST['wind_direction'] == 'NW') ? 'selected' : ''; ?>>Northwest (NW)</option>
                                <option value="Variable" <?php echo (isset($_POST['wind_direction']) && $_POST['wind_direction'] == 'Variable') ? 'selected' : ''; ?>>Variable</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-eye"></i> Atmospheric Conditions</h3>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="barometric_pressure"><i class="fas fa-weight"></i> Barometric Pressure (hPa)</label>
                            <div class="input-group">
                                <input type="number" name="barometric_pressure" id="barometric_pressure" class="form-control" 
                                       step="0.1" min="900" max="1100"
                                       value="<?php echo isset($_POST['barometric_pressure']) ? $_POST['barometric_pressure'] : ''; ?>"
                                       placeholder="e.g., 1013.2">
                                <div class="input-group-append">
                                    <span class="input-group-text">hPa</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="visibility"><i class="fas fa-eye"></i> Visibility (km)</label>
                            <div class="input-group">
                                <input type="number" name="visibility" id="visibility" class="form-control" 
                                       step="0.1" min="0" max="50"
                                       value="<?php echo isset($_POST['visibility']) ? $_POST['visibility'] : ''; ?>"
                                       placeholder="e.g., 10.0">
                                <div class="input-group-append">
                                    <span class="input-group-text">km</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="uv_index"><i class="fas fa-sun"></i> UV Index</label>
                            <input type="number" name="uv_index" id="uv_index" class="form-control" 
                                   step="0.1" min="0" max="15"
                                   value="<?php echo isset($_POST['uv_index']) ? $_POST['uv_index'] : ''; ?>"
                                   placeholder="e.g., 7.5">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-cloud-rain"></i> Precipitation</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="rainfall"><i class="fas fa-cloud-rain"></i> Rainfall (mm)</label>
                            <div class="input-group">
                                <input type="number" name="rainfall" id="rainfall" class="form-control" 
                                       step="0.1" min="0" max="500"
                                       value="<?php echo isset($_POST['rainfall']) ? $_POST['rainfall'] : ''; ?>"
                                       placeholder="e.g., 5.2">
                                <div class="input-group-append">
                                    <span class="input-group-text">mm</span>
                                </div>
                            </div>
                            <small class="form-text text-muted">Enter 0 for no rainfall</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="precipitation_type"><i class="fas fa-droplet"></i> Precipitation Type</label>
                            <select name="precipitation_type" id="precipitation_type" class="form-control">
                                <option value="">None</option>
                                <option value="Light Rain">Light Rain</option>
                                <option value="Moderate Rain">Moderate Rain</option>
                                <option value="Heavy Rain">Heavy Rain</option>
                                <option value="Drizzle">Drizzle</option>
                                <option value="Thunderstorm">Thunderstorm</option>
                                <option value="Hail">Hail</option>
                                <option value="Snow">Snow</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-sticky-note"></i> Additional Notes</h3>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="notes"><i class="fas fa-clipboard"></i> Environmental Observations</label>
                            <textarea name="notes" id="notes" class="form-control" rows="4"
                                      placeholder="Record any additional environmental observations, unusual weather patterns, or concerns..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Data Entry Tools -->
            <div class="form-section">
                <h3><i class="fas fa-tools"></i> Quick Entry Tools</h3>
                <div class="quick-tools">
                    <button type="button" class="btn btn-outline-primary" onclick="getCurrentWeather()">
                        <i class="fas fa-cloud-sun"></i> Get Current Weather
                    </button>
                    <button type="button" class="btn btn-outline-info" onclick="usePresetValues('morning')">
                        <i class="fas fa-sun"></i> Morning Preset
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="usePresetValues('afternoon')">
                        <i class="fas fa-sun"></i> Afternoon Preset
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="usePresetValues('evening')">
                        <i class="fas fa-moon"></i> Evening Preset
                    </button>
                </div>
                <small class="form-text text-muted">
                    Quick tools to help populate common values. You can modify the values after using these presets.
                </small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Record Environmental Data
                </button>
                <button type="button" class="btn btn-secondary" onclick="location.href='supervisor_environmental_monitoring.php'">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-info" onclick="previewData()">
                    <i class="fas fa-eye"></i> Preview Data
                </button>
            </div>
        </form>
    </div>

    <!-- Recent Readings -->
    <div class="recent-readings-section">
        <h3><i class="fas fa-history"></i> Recent Environmental Readings</h3>
        <div class="readings-grid">
            <?php
            // Get recent readings for context
            try {
                $recent_stmt = $pdo->prepare("
                    SELECT er.*, f.field_name 
                    FROM environmental_readings er
                    LEFT JOIN fields f ON er.field_id = f.id
                    WHERE er.supervisor_id = ?
                    ORDER BY er.reading_date DESC, er.reading_time DESC
                    LIMIT 6
                ");
                $recent_stmt->execute([$supervisor_id]);
                $recent_readings = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach($recent_readings as $reading): ?>
                <div class="reading-card">
                    <div class="reading-header">
                        <strong><?php echo htmlspecialchars($reading['field_name']); ?></strong>
                        <span class="reading-date"><?php echo date('M d, g:i A', strtotime($reading['reading_date'] . ' ' . $reading['reading_time'])); ?></span>
                    </div>
                    <div class="reading-data">
                        <div class="data-item">
                            <i class="fas fa-thermometer-half"></i>
                            <span><?php echo $reading['temperature']; ?>°C</span>
                        </div>
                        <div class="data-item">
                            <i class="fas fa-tint"></i>
                            <span><?php echo $reading['humidity']; ?>%</span>
                        </div>
                        <?php if($reading['wind_speed']): ?>
                        <div class="data-item">
                            <i class="fas fa-wind"></i>
                            <span><?php echo $reading['wind_speed']; ?> km/h</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach;
            } catch(PDOException $e) {
                echo '<p class="text-muted">No recent readings available.</p>';
            }
            ?>
        </div>
    </div>
</div>

<!-- Data Preview Modal -->
<div class="modal fade" id="dataPreviewModal" tabindex="-1" role="dialog" aria-labelledby="dataPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dataPreviewModalLabel">
                    <i class="fas fa-eye"></i> Environmental Data Preview
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="dataPreviewContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="$('#dataPreviewModal').modal('hide'); $('form').submit();">
                    <i class="fas fa-save"></i> Confirm & Save
                </button>
            </div>
        </div>
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

.environmental-form .form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #dee2e6;
}

.environmental-form .form-section:last-of-type {
    border-bottom: none;
}

.environmental-form .form-section h3 {
    color: #333;
    margin-bottom: 20px;
    font-size: 18px;
    font-weight: 600;
}

.environmental-form .form-section h3 i {
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

.input-group-text {
    background-color: #f8f9fa;
    border-color: #ddd;
    color: #6c757d;
    font-weight: 500;
}

.quick-tools {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.quick-tools .btn {
    flex: 1;
    min-width: 140px;
}

.form-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
    text-align: right;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.form-actions .btn {
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

/* Recent Readings Section */
.recent-readings-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 30px;
    margin-bottom: 30px;
}

.recent-readings-section h3 {
    color: #333;
    margin-bottom: 20px;
    font-size: 18px;
    font-weight: 600;
}

.recent-readings-section h3 i {
    color: #6c757d;
    margin-right: 8px;
}

.readings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 15px;
}

.reading-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.reading-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.reading-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #dee2e6;
}

.reading-header strong {
    color: #333;
}

.reading-date {
    font-size: 12px;
    color: #6c757d;
}

.reading-data {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.data-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.data-item i {
    font-size: 16px;
    color: #6c757d;
    margin-bottom: 4px;
}

.data-item span {
    font-size: 14px;
    font-weight: 500;
    color: #333;
}

/* Modal Styles */
.modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.modal-body {
    padding: 20px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .form-container {
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-actions .btn {
        margin-bottom: 10px;
    }
    
    .quick-tools {
        flex-direction: column;
    }
    
    .quick-tools .btn {
        width: 100%;
        margin-bottom: 5px;
    }
    
    .readings-grid {
        grid-template-columns: 1fr;
    }
    
    .reading-data {
        flex-direction: column;
        gap: 8px;
    }
    
    .data-item {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

/* Input validation styles */
.form-control.is-invalid {
    border-color: #dc3545;
}

.form-control.is-valid {
    border-color: #28a745;
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
}

.valid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #28a745;
}
</style>

<?php include 'includes/footer.php'; ?>

<script>
// Preset values for different times of day
const presetValues = {
    morning: {
        temperature: 22,
        humidity: 75,
        wind_speed: 8,
        wind_direction: 'E',
        barometric_pressure: 1013,
        visibility: 10,
        uv_index: 3
    },
    afternoon: {
        temperature: 28,
        humidity: 60,
        wind_speed: 12,
        wind_direction: 'SW',
        barometric_pressure: 1012,
        visibility: 15,
        uv_index: 8
    },
    evening: {
        temperature: 24,
        humidity: 70,
        wind_speed: 6,
        wind_direction: 'W',
        barometric_pressure: 1014,
        visibility: 12,
        uv_index: 1
    }
};

// Use preset values
function usePresetValues(timeOfDay) {
    if (!presetValues[timeOfDay]) return;
    
    const preset = presetValues[timeOfDay];
    
    Object.keys(preset).forEach(field => {
        const element = document.getElementById(field);
        if (element) {
            element.value = preset[field];
            element.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
    
    // Show notification
    showNotification(`Applied ${timeOfDay} preset values`, 'success');
}

// Get current weather (placeholder - would integrate with weather API)
function getCurrentWeather() {
    showNotification('Getting current weather...', 'info');
    
    // Simulate API call
    setTimeout(() => {
        // Simulate getting current weather data
        const currentWeather = {
            temperature: 25.5,
            humidity: 68,
            wind_speed: 10.2,
            wind_direction: 'NW',
            barometric_pressure: 1013.2,
            visibility: 12.5,
            uv_index: 6.0
        };
        
        Object.keys(currentWeather).forEach(field => {
            const element = document.getElementById(field);
            if (element) {
                element.value = currentWeather[field];
                element.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
        
        showNotification('Current weather data loaded successfully', 'success');
    }, 1500);
}

// Preview data before submission
function previewData() {
    const formData = new FormData(document.querySelector('.environmental-form'));
    const previewContent = document.getElementById('dataPreviewContent');
    
    let html = '<div class="preview-grid">';
    
    const fields = [
        { name: 'field_id', label: 'Field', type: 'select' },
        { name: 'reading_date', label: 'Date', type: 'date' },
        { name: 'reading_time', label: 'Time', type: 'time' },
        { name: 'temperature', label: 'Temperature', unit: '°C' },
        { name: 'humidity', label: 'Humidity', unit: '%' },
        { name: 'wind_speed', label: 'Wind Speed', unit: 'km/h' },
        { name: 'wind_direction', label: 'Wind Direction' },
        { name: 'barometric_pressure', label: 'Barometric Pressure', unit: 'hPa' },
        { name: 'rainfall', label: 'Rainfall', unit: 'mm' },
        { name: 'visibility', label: 'Visibility', unit: 'km' },
        { name: 'uv_index', label: 'UV Index' },
        { name: 'notes', label: 'Notes', type: 'textarea' }
    ];
    
    fields.forEach(field => {
        const value = formData.get(field.name);
        if (value) {
            if (field.type === 'select') {
                const selectElement = document.getElementById(field.name);
                const selectedText = selectElement.options[selectElement.selectedIndex].text;
                html += `<div class="preview-item"><strong>${field.label}:</strong> ${selectedText}</div>`;
            } else if (field.type === 'textarea') {
                html += `<div class="preview-item"><strong>${field.label}:</strong><br>${value}</div>`;
            } else {
                html += `<div class="preview-item"><strong>${field.label}:</strong> ${value}${field.unit || ''}</div>`;
            }
        }
    });
    
    html += '</div>';
    previewContent.innerHTML = html;
    $('#dataPreviewModal').modal('show');
}

// Show notification
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification`;
    notification.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.environmental-form');
    const inputs = form.querySelectorAll('input[type="number"]');
    
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            validateNumericInput(this);
        });
    });
    
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            showNotification('Please check the form for errors', 'danger');
        }
    });
});

// Validate numeric input
function validateNumericInput(input) {
    const value = parseFloat(input.value);
    const min = parseFloat(input.min);
    const max = parseFloat(input.max);
    
    input.classList.remove('is-invalid', 'is-valid');
    
    if (input.value === '') {
        // Empty is OK for non-required fields
        if (!input.required) {
            input.classList.add('is-valid');
        }
        return;
    }
    
    if (isNaN(value) || (min !== null && value < min) || (max !== null && value > max)) {
        input.classList.add('is-invalid');
    } else {
        input.classList.add('is-valid');
    }
}

// Validate entire form
function validateForm() {
    const form = document.querySelector('.environmental-form');
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        }
    });
    
    return isValid;
}

// Auto-save draft functionality (optional)
let autoSaveInterval;

function startAutoSave() {
    autoSaveInterval = setInterval(() => {
        const formData = new FormData(document.querySelector('.environmental-form'));
        const draftData = {};
        
        for (let [key, value] of formData.entries()) {
            if (value.trim() !== '') {
                draftData[key] = value;
            }
        }
        
        if (Object.keys(draftData).length > 2) { // More than just field and date
            localStorage.setItem('environmental_data_draft', JSON.stringify(draftData));
        }
    }, 30000); // Save every 30 seconds
}

function loadDraft() {
    const draft = localStorage.getItem('environmental_data_draft');
    if (draft) {
        const draftData = JSON.parse(draft);
        
        if (confirm('A draft of environmental data was found. Would you like to load it?')) {
            Object.keys(draftData).forEach(key => {
                const element = document.getElementById(key);
                if (element) {
                    element.value = draftData[key];
                }
            });
            
            showNotification('Draft data loaded', 'success');
        }
        
        localStorage.removeItem('environmental_data_draft');
    }
}

// Initialize auto-save and check for drafts
document.addEventListener('DOMContentLoaded', function() {
    loadDraft();
    startAutoSave();
    
    // Clear draft when form is submitted successfully
    document.querySelector('.environmental-form').addEventListener('submit', function() {
        localStorage.removeItem('environmental_data_draft');
        if (autoSaveInterval) {
            clearInterval(autoSaveInterval);
        }
    });
});

// Add preview grid styles
const style = document.createElement('style');
style.textContent = `
    .preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }
    
    .preview-item {
        padding: 10px;
        background: #f8f9fa;
        border-radius: 6px;
        border-left: 3px solid #20c997;
    }
    
    .notification {
        animation: slideInRight 0.3s ease-out;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);
</script>