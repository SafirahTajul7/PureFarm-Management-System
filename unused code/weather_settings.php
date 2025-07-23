<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Set timezone to Malaysia time (MYT/UTC+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// Define default settings if not in database
$defaultSettings = [
    'temperature_high' => 35,
    'temperature_low' => 10,
    'humidity_high' => 90,
    'humidity_low' => 30,
    'wind_speed_high' => 50,
    'precipitation_high' => 50,
    'temperature_unit' => 'celsius',
    'alert_temperature' => 1,
    'alert_humidity' => 1,
    'alert_precipitation' => 1,
    'alert_wind' => 1,
    'auto_refresh' => 60,
    'default_location' => 1,
];

// Initialize message variables
$successMsg = '';
$errorMsg = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if weather_settings table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'weather_settings'")->rowCount() > 0;
        
        if (!$tableExists) {
            // Create the table if it doesn't exist
            $pdo->exec("
                CREATE TABLE weather_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_name VARCHAR(50) NOT NULL,
                    setting_value VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Process each setting
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $settingName = substr($key, 8); // Remove 'setting_' prefix
                
                // Check if setting already exists
                $stmt = $pdo->prepare("SELECT id FROM weather_settings WHERE setting_name = ?");
                $stmt->execute([$settingName]);
                
                if ($stmt->rowCount() > 0) {
                    // Update existing setting
                    $updateStmt = $pdo->prepare("UPDATE weather_settings SET setting_value = ? WHERE setting_name = ?");
                    $updateStmt->execute([$value, $settingName]);
                } else {
                    // Insert new setting
                    $insertStmt = $pdo->prepare("INSERT INTO weather_settings (setting_name, setting_value) VALUES (?, ?)");
                    $insertStmt->execute([$settingName, $value]);
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $successMsg = 'Weather settings saved successfully!';
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMsg = 'Error saving settings: ' . $e->getMessage();
        error_log("Error saving weather settings: " . $e->getMessage());
    }
}

// Fetch current settings
$currentSettings = $defaultSettings;
try {
    // Check if table exists before querying
    $tableExists = $pdo->query("SHOW TABLES LIKE 'weather_settings'")->rowCount() > 0;
    
    if ($tableExists) {
        $stmt = $pdo->query("SELECT setting_name, setting_value FROM weather_settings");
        $settingsFromDb = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Merge with defaults, prioritizing DB values
        if ($settingsFromDb) {
            foreach ($settingsFromDb as $key => $value) {
                $currentSettings[$key] = $value;
            }
        }
    }
} catch (Exception $e) {
    $errorMsg = 'Error loading settings: ' . $e->getMessage();
    error_log("Error loading weather settings: " . $e->getMessage());
}

// Get available farm locations for dropdown
try {
    // Check if fields table exists
    $hasFieldsTable = $pdo->query("SHOW TABLES LIKE 'fields'")->rowCount() > 0;
    
    if ($hasFieldsTable) {
        $locationsStmt = $pdo->query("SELECT id, name FROM fields WHERE status = 'active' ORDER BY name");
        $locations = $locationsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Sample locations if table doesn't exist
        $locations = [
            ['id' => 1, 'name' => 'Main Field'],
            ['id' => 2, 'name' => 'North Field'],
            ['id' => 3, 'name' => 'South Field'],
            ['id' => 4, 'name' => 'East Field'],
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching field locations: " . $e->getMessage());
    // Sample locations as fallback
    $locations = [
        ['id' => 1, 'name' => 'Main Field'],
        ['id' => 2, 'name' => 'North Field'],
        ['id' => 3, 'name' => 'South Field'],
        ['id' => 4, 'name' => 'East Field'],
    ];
}

$pageTitle = 'Weather Settings';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-cog"></i> Weather Settings</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='current_weather.php'">
                <i class="fas fa-cloud-sun"></i> Current Weather
            </button>
            <button class="btn btn-primary" onclick="location.href='notifications.php'">
                <i class="fas fa-bell"></i> Notifications
            </button>
        </div>
    </div>
    
    <?php if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $successMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $errorMsg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="settings-container">
        <form method="post" action="" id="weatherSettingsForm">
            <div class="settings-section">
                <div class="section-header">
                    <h3><i class="fas fa-th-large"></i> General Settings</h3>
                </div>
                <div class="section-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="setting_default_location">Default Location</label>
                                <select class="form-select" id="setting_default_location" name="setting_default_location">
                                    <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['id']; ?>" <?php if ($currentSettings['default_location'] == $location['id']) echo 'selected'; ?>>
                                        <?php echo $location['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Location displayed on the weather dashboard by default</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="setting_temperature_unit">Temperature Unit</label>
                                <select class="form-select" id="setting_temperature_unit" name="setting_temperature_unit">
                                    <option value="celsius" <?php if ($currentSettings['temperature_unit'] === 'celsius') echo 'selected'; ?>>Celsius (°C)</option>
                                    <option value="fahrenheit" <?php if ($currentSettings['temperature_unit'] === 'fahrenheit') echo 'selected'; ?>>Fahrenheit (°F)</option>
                                </select>
                                <small class="form-text text-muted">Preferred temperature unit for display</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="setting_auto_refresh">Auto-Refresh Interval (minutes)</label>
                                <select class="form-select" id="setting_auto_refresh" name="setting_auto_refresh">
                                    <option value="0" <?php if ($currentSettings['auto_refresh'] == 0) echo 'selected'; ?>>Disabled</option>
                                    <option value="15" <?php if ($currentSettings['auto_refresh'] == 15) echo 'selected'; ?>>15 minutes</option>
                                    <option value="30" <?php if ($currentSettings['auto_refresh'] == 30) echo 'selected'; ?>>30 minutes</option>
                                    <option value="60" <?php if ($currentSettings['auto_refresh'] == 60) echo 'selected'; ?>>1 hour</option>
                                    <option value="180" <?php if ($currentSettings['auto_refresh'] == 180) echo 'selected'; ?>>3 hours</option>
                                    <option value="360" <?php if ($currentSettings['auto_refresh'] == 360) echo 'selected'; ?>>6 hours</option>
                                </select>
                                <small class="form-text text-muted">How often weather data should automatically refresh</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label>Weather Data Source</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="setting_weather_source" id="weather_source_api" value="api" <?php if (!isset($currentSettings['weather_source']) || $currentSettings['weather_source'] === 'api') echo 'checked'; ?>>
                                    <label class="form-check-label" for="weather_source_api">
                                        Online Weather API (Internet required)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="setting_weather_source" id="weather_source_station" value="station" <?php if (isset($currentSettings['weather_source']) && $currentSettings['weather_source'] === 'station') echo 'checked'; ?>>
                                    <label class="form-check-label" for="weather_source_station">
                                        Local Weather Station
                                    </label>
                                </div>
                                <small class="form-text text-muted">Where to fetch weather data from</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="settings-section">
                <div class="section-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Alert Thresholds</h3>
                    <p class="section-desc">Set thresholds for when you want to receive weather alerts</p>
                </div>
                <div class="section-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="setting_temperature_high">High Temperature Alert (°C)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="setting_temperature_high" name="setting_temperature_high" value="<?php echo $currentSettings['temperature_high']; ?>" min="0" max="50" step="0.5">
                                    <span class="input-group-text">°C</span>
                                </div>
                                <small class="form-text text-muted">Alert when temperature exceeds this value</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="setting_temperature_low">Low Temperature Alert (°C)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="setting_temperature_low" name="setting_temperature_low" value="<?php echo $currentSettings['temperature_low']; ?>" min="-20" max="30" step="0.5">
                                    <span class="input-group-text">°C</span>
                                </div>
                                <small class="form-text text-muted">Alert when temperature falls below this value</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="setting_humidity_high">High Humidity Alert (%)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="setting_humidity_high" name="setting_humidity_high" value="<?php echo $currentSettings['humidity_high']; ?>" min="0" max="100" step="1">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="form-text text-muted">Alert when humidity exceeds this value</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="setting_humidity_low">Low Humidity Alert (%)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="setting_humidity_low" name="setting_humidity_low" value="<?php echo $currentSettings['humidity_low']; ?>" min="0" max="100" step="1">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="form-text text-muted">Alert when humidity falls below this value</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="setting_wind_speed_high">High Wind Speed Alert (km/h)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="setting_wind_speed_high" name="setting_wind_speed_high" value="<?php echo $currentSettings['wind_speed_high']; ?>" min="0" max="200" step="1">
                                    <span class="input-group-text">km/h</span>
                                </div>
                                <small class="form-text text-muted">Alert when wind speed exceeds this value</small>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="setting_precipitation_high">High Precipitation Alert (mm)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="setting_precipitation_high" name="setting_precipitation_high" value="<?php echo $currentSettings['precipitation_high']; ?>" min="0" max="500" step="1">
                                    <span class="input-group-text">mm</span>
                                </div>
                                <small class="form-text text-muted">Alert when precipitation exceeds this value</small>
                            </div>
                            
                            <div class="mb-4">
                                <label class="mb-2">Alert Types to Enable</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="setting_alert_temperature" id="alert_temperature" value="1" <?php if ($currentSettings['alert_temperature'] == 1) echo 'checked'; ?>>
                                    <label class="form-check-label" for="alert_temperature">
                                        Temperature Alerts
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="setting_alert_humidity" id="alert_humidity" value="1" <?php if ($currentSettings['alert_humidity'] == 1) echo 'checked'; ?>>
                                    <label class="form-check-label" for="alert_humidity">
                                        Humidity Alerts
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="setting_alert_precipitation" id="alert_precipitation" value="1" <?php if ($currentSettings['alert_precipitation'] == 1) echo 'checked'; ?>>
                                    <label class="form-check-label" for="alert_precipitation">
                                        Precipitation Alerts
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="setting_alert_wind" id="alert_wind" value="1" <?php if ($currentSettings['alert_wind'] == 1) echo 'checked'; ?>>
                                    <label class="form-check-label" for="alert_wind">
                                        Wind Alerts
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="settings-section">
                <div class="section-header">
                    <h3><i class="fas fa-bell"></i> Notification Settings</h3>
                    <p class="section-desc">Configure how you want to receive weather alerts</p>
                </div>
                <div class="section-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="mb-2">Notification Preferences</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="setting_notify_system" id="notify_system" value="1" <?php if (!isset($currentSettings['notify_system']) || $currentSettings['notify_system'] == 1) echo 'checked'; ?>>
                                    <label class="form-check-label" for="notify_system">
                                        In-System Notifications
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="setting_notify_daily_summary" id="notify_daily_summary" value="1" <?php if (isset($currentSettings['notify_daily_summary']) && $currentSettings['notify_daily_summary'] == 1) echo 'checked'; ?>>
                                    <label class="form-check-label" for="notify_daily_summary">
                                        Daily Weather Summary
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="setting_notify_forecast_alerts" id="notify_forecast_alerts" value="1" <?php if (!isset($currentSettings['notify_forecast_alerts']) || $currentSettings['notify_forecast_alerts'] == 1) echo 'checked'; ?>>
                                    <label class="form-check-label" for="notify_forecast_alerts">
                                        Severe Weather Forecast Alerts
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="setting_notification_time">Daily Summary Time</label>
                                <input type="time" class="form-control" id="setting_notification_time" name="setting_notification_time" value="<?php echo isset($currentSettings['notification_time']) ? $currentSettings['notification_time'] : '07:00'; ?>">
                                <small class="form-text text-muted">Time to receive daily weather summary (if enabled)</small>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Weather alerts and notifications will be available in the notifications panel. 
                                <a href="notifications.php" class="alert-link">View notifications</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="saveSettingsBtn">
                    <i class="fas fa-save"></i> Save Settings
                </button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">
                    <i class="fas fa-undo"></i> Reset to Defaults
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Settings container */
.settings-container {
    margin-bottom: 40px;
}

/* Settings sections */
.settings-section {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    overflow: hidden;
}

.section-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.section-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
}

.section-header h3 i {
    margin-right: 10px;
    color: #20c997;
}

.section-desc {
    margin: 5px 0 0 0;
    font-size: 14px;
    color: #6c757d;
}

.section-body {
    padding: 20px;
}

/* Form styling */
label {
    font-weight: 500;
    margin-bottom: 6px;
    color: #495057;
}

.form-text {
    color: #6c757d;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

/* Alert styling */
.alert {
    margin-bottom: 20px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions button {
        width: 100%;
        margin-bottom: 10px;
    }
}
</style>

<script>
// Form reset functionality
function resetForm() {
    if (confirm('Are you sure you want to reset all settings to default values?')) {
        document.getElementById('weatherSettingsForm').reset();
        
        // Reset to default values (could be enhanced to use AJAX to fetch defaults from server)
        document.getElementById('setting_temperature_high').value = '35';
        document.getElementById('setting_temperature_low').value = '10';
        document.getElementById('setting_humidity_high').value = '90';
        document.getElementById('setting_humidity_low').value = '30';
        document.getElementById('setting_wind_speed_high').value = '50';
        document.getElementById('setting_precipitation_high').value = '50';
        document.getElementById('setting_auto_refresh').value = '60';
        
        // Reset checkboxes
        document.getElementById('alert_temperature').checked = true;
        document.getElementById('alert_humidity').checked = true;
        document.getElementById('alert_precipitation').checked = true;
        document.getElementById('alert_wind').checked = true;
        document.getElementById('notify_system').checked = true;
        document.getElementById('notify_forecast_alerts').checked = true;
    }
}
</script>

<?php include 'includes/footer.php'; ?>