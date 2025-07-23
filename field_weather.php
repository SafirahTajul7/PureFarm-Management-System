<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Set timezone to Malaysia time (MYT/UTC+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// Get field ID from URL parameter
$field_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$field_id) {
    // Redirect back to weather page if no valid ID
    header('Location: weather.php');
    exit;
}

try {
    // Define field alerts variable early to avoid undefined variable later
    $fieldAlerts = [];
    $activities = [];
    
    // Fetch field information
    $stmt = $pdo->prepare("SELECT * FROM fields WHERE id = ?");
    $stmt->execute([$field_id]);
    $field = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$field) {
        // Try to get field information by name instead (for demonstration)
        $stmt = $pdo->prepare("SELECT field_name, location, area, soil_type, last_crop, notes FROM fields WHERE id = ?");
        $stmt->execute([$field_id]);
        $fieldData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fieldData) {
            // Create a field array with the fetched data
            $field = [
                'id' => $field_id,
                'name' => $fieldData['field_name'],
                'location' => $fieldData['location'],
                'size' => $fieldData['area'],
                'crop_type' => $fieldData['last_crop'],
                'notes' => $fieldData['notes'],
                'planting_date' => date('Y-m-d'),
                'growth_stage' => 'Vegetative',
                'next_activity' => 'Inspection (scheduled)'
            ];
        } else {
            // If still no field data, create default field data
            $field = [
                'id' => $field_id,
                'name' => 'Field ' . $field_id,
                'location' => 'Farm Area',
                'size' => '0',
                'crop_type' => 'Unknown',
                'planting_date' => date('Y-m-d'),
                'growth_stage' => 'Vegetative',
                'next_activity' => 'Inspection (scheduled)'
            ];
        }
    } else {
        // If field exists but the 'name' key doesn't exist (it might be named differently in your DB)
        if (!isset($field['name']) && isset($field['field_name'])) {
            $field['name'] = $field['field_name'];
        } else if (!isset($field['name'])) {
            $field['name'] = 'Field ' . $field_id;
        }
    }
    
    // Fetch specific weather data for this field
    // In a real implementation, this might come from sensors or a weather API
    // For now, we'll use sample data with slight variations
    
    // Check if we have a field_weather_data table
    $hasFieldWeatherTable = false;
    $tables = $pdo->query("SHOW TABLES LIKE 'field_weather_data'")->fetchAll();
    if (count($tables) > 0) {
        $hasFieldWeatherTable = true;
    }
    
    if ($hasFieldWeatherTable) {
        // Fetch the most recent weather data for this specific field
        $stmt = $pdo->prepare("
            SELECT * FROM field_weather_data 
            WHERE field_id = ? 
            ORDER BY recorded_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$field_id]);
        $weatherData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no specific weather data found for this field
        if (!$weatherData) {
            // Use sample data
            $weatherData = [
                'temperature' => 23 + (mt_rand(-15, 15) / 10),
                'humidity' => 66 + mt_rand(-8, 8),
                'wind_speed' => 9 + (mt_rand(-20, 20) / 10),
                'wind_direction' => 'NE',
                'pressure' => 1010 + mt_rand(-5, 5),
                'precipitation' => mt_rand(0, 10) / 10,
                'condition' => 'Partly Cloudy',
                'soil_moisture' => 42 + mt_rand(-5, 5),
                'soil_temperature' => 19 + (mt_rand(-10, 10) / 10),
                'recorded_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Fetch historical data for graphs (last 7 days)
        $stmt = $pdo->prepare("
            SELECT 
                DATE(recorded_at) as date,
                AVG(temperature) as avg_temp,
                MAX(temperature) as max_temp,
                MIN(temperature) as min_temp,
                AVG(humidity) as avg_humidity,
                AVG(soil_moisture) as avg_soil_moisture
            FROM field_weather_data
            WHERE field_id = ? 
                AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(recorded_at)
            ORDER BY DATE(recorded_at)
        ");
        $stmt->execute([$field_id]);
        $historicalData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no historical data found, create sample data
        if (empty($historicalData)) {
            $historicalData = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $historicalData[] = [
                    'date' => $date,
                    'avg_temp' => 23 + (mt_rand(-30, 30) / 10),
                    'max_temp' => 26 + (mt_rand(-10, 20) / 10),
                    'min_temp' => 19 + (mt_rand(-20, 10) / 10),
                    'avg_humidity' => 65 + mt_rand(-10, 10),
                    'avg_soil_moisture' => 40 + mt_rand(-8, 8)
                ];
            }
        }
    } else {
        // Use sample data if no table exists
        $weatherData = [
            'temperature' => 23 + (mt_rand(-15, 15) / 10),
            'humidity' => 66 + mt_rand(-8, 8),
            'wind_speed' => 9 + (mt_rand(-20, 20) / 10),
            'wind_direction' => 'NE',
            'pressure' => 1010 + mt_rand(-5, 5),
            'precipitation' => mt_rand(0, 10) / 10,
            'condition' => 'Partly Cloudy',
            'soil_moisture' => 42 + mt_rand(-5, 5),
            'soil_temperature' => 19 + (mt_rand(-10, 10) / 10),
            'recorded_at' => date('Y-m-d H:i:s')
        ];
        
        // Sample historical data
        $historicalData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $historicalData[] = [
                'date' => $date,
                'avg_temp' => 23 + (mt_rand(-30, 30) / 10),
                'max_temp' => 26 + (mt_rand(-10, 20) / 10),
                'min_temp' => 19 + (mt_rand(-20, 10) / 10),
                'avg_humidity' => 65 + mt_rand(-10, 10),
                'avg_soil_moisture' => 40 + mt_rand(-8, 8)
            ];
        }
    }
    
    // Get field specific alerts/recommendations
    
    // Check soil moisture and provide recommendations
    if (isset($weatherData['soil_moisture']) && $weatherData['soil_moisture'] < 30) {
        $fieldAlerts[] = [
            'type' => 'warning',
            'title' => 'Low Soil Moisture',
            'description' => 'Soil moisture is below optimal levels. Consider irrigation in the next 24 hours.',
            'icon' => 'tint-slash'
        ];
    } elseif (isset($weatherData['soil_moisture']) && $weatherData['soil_moisture'] > 75) {
        $fieldAlerts[] = [
            'type' => 'info',
            'title' => 'High Soil Moisture',
            'description' => 'Soil moisture is higher than optimal. Consider reducing irrigation.',
            'icon' => 'water'
        ];
    }
    
    // Check temperature for extreme conditions
    if (isset($weatherData['temperature']) && $weatherData['temperature'] > 30) {
        $fieldAlerts[] = [
            'type' => 'danger',
            'title' => 'High Temperature Alert',
            'description' => 'Temperature is above optimal for current crop. Consider additional irrigation and shade if possible.',
            'icon' => 'thermometer-full'
        ];
    } elseif (isset($weatherData['temperature']) && $weatherData['temperature'] < 15) {
        $fieldAlerts[] = [
            'type' => 'warning',
            'title' => 'Low Temperature Alert',
            'description' => 'Temperature is below optimal for current crop. Monitor for frost conditions.',
            'icon' => 'thermometer-empty'
        ];
    }
    
    // Sample crop-specific recommendation
    $fieldAlerts[] = [
        'type' => 'success',
        'title' => 'Optimal Growing Conditions',
        'description' => 'Current weather conditions are ideal for ' . (isset($field['crop_type']) ? htmlspecialchars($field['crop_type']) : 'your crop') . ' growth.',
        'icon' => 'seedling'
    ];
    
    // Get activities for this field
    
    // Try to fetch from database if exists
    $hasFieldActivitiesTable = false;
    $tables = $pdo->query("SHOW TABLES LIKE 'field_activities'")->fetchAll();
    if (count($tables) > 0) {
        $hasFieldActivitiesTable = true;
    }
    
    if ($hasFieldActivitiesTable) {
        $stmt = $pdo->prepare("
            SELECT * FROM field_activities
            WHERE field_id = ?
            ORDER BY activity_date DESC
            LIMIT 5
        ");
        $stmt->execute([$field_id]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // If no activities, use sample data
    if (empty($activities)) {
        // Sample activities
        $activities = [
            [
                'activity_type' => 'Irrigation',
                'description' => 'Scheduled irrigation completed',
                'activity_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'status' => 'completed',
                'performed_by' => 'System'
            ],
            [
                'activity_type' => 'Inspection',
                'description' => 'Field inspection - crop growth on target',
                'activity_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
                'status' => 'completed',
                'performed_by' => 'John Doe'
            ],
            [
                'activity_type' => 'Fertilization',
                'description' => 'Applied NPK fertilizer',
                'activity_date' => date('Y-m-d H:i:s', strtotime('-1 week')),
                'status' => 'completed',
                'performed_by' => 'Jane Smith'
            ]
        ];
    }
    
} catch(PDOException $e) {
    error_log("Error fetching field data: " . $e->getMessage());
    // Set default error message
    $error = "An error occurred while fetching field data. Please try again later.";
    
    // Make sure all necessary variables are defined even when an error occurs
    $field = [
        'id' => $field_id,
        'name' => 'Unknown Field',
        'location' => 'Unknown Location',
        'size' => '0',
        'crop_type' => 'Unknown',
        'planting_date' => date('Y-m-d'),
        'growth_stage' => 'Unknown',
        'next_activity' => 'None'
    ];
    
    $weatherData = [
        'temperature' => 24,
        'humidity' => 65,
        'wind_speed' => 10,
        'wind_direction' => 'NE',
        'pressure' => 1010,
        'precipitation' => 0,
        'condition' => 'Unknown',
        'soil_moisture' => 40,
        'soil_temperature' => 20,
        'recorded_at' => date('Y-m-d H:i:s')
    ];
    
    $historicalData = [];
    $fieldAlerts = [];
    $activities = [];
}

// Get weather condition icon class - define the function here
function getWeatherIconClass($condition) {
    $condition = strtolower($condition);
    if (strpos($condition, 'sun') !== false && strpos($condition, 'cloud') !== false) {
        return 'cloud-sun';
    } elseif (strpos($condition, 'sun') !== false || strpos($condition, 'clear') !== false) {
        return 'sun';
    } elseif (strpos($condition, 'cloud') !== false) {
        return 'cloud';
    } elseif (strpos($condition, 'rain') !== false) {
        return 'cloud-rain';
    } elseif (strpos($condition, 'storm') !== false || strpos($condition, 'thunder') !== false) {
        return 'bolt';
    } elseif (strpos($condition, 'snow') !== false) {
        return 'snowflake';
    } elseif (strpos($condition, 'fog') !== false || strpos($condition, 'mist') !== false) {
        return 'smog';
    } else {
        return 'cloud';
    }
}

// Make sure $pageTitle is defined with a default value
$pageTitle = isset($field['name']) ? htmlspecialchars($field['name']) . ' Weather Details' : 'Field Weather Details';
include 'includes/header.php';
?>
<!-- Rest of your HTML code remains the same -->
<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($field['name']); ?> Details</h2>
        <div class="action-buttons">
            <button class="btn btn-outline-secondary" onclick="window.history.back()">
                <i class="fas fa-arrow-left"></i> Back to Weather
            </button>
            <button class="btn btn-primary" onclick="refreshFieldData()">
                <i class="fas fa-sync-alt"></i> Refresh Data
            </button>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <?php echo $error; ?>
    </div>
    <?php else: ?>
    
    <!-- Field Information Card -->
    <div class="row mb-4">
        <div class="col-md-5">
            <div class="field-info-card">
                <div class="field-header">
                    <h3><?php echo htmlspecialchars($field['name']); ?></h3>
                    <?php if (isset($field['location'])): ?>
                    <p class="text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($field['location']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="field-details">
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-ruler-combined"></i> Size:</div>
                        <div class="detail-value"><?php echo isset($field['size']) ? htmlspecialchars($field['size']) : 'N/A'; ?> hectares</div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-seedling"></i> Current Crop:</div>
                        <div class="detail-value"><?php echo isset($field['crop_type']) ? htmlspecialchars($field['crop_type']) : 'N/A'; ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-calendar-alt"></i> Planting Date:</div>
                        <div class="detail-value">
                            <?php 
                            echo isset($field['planting_date']) ? date('M d, Y', strtotime($field['planting_date'])) : 'N/A'; 
                            ?>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-leaf"></i> Growth Stage:</div>
                        <div class="detail-value"><?php echo isset($field['growth_stage']) ? htmlspecialchars($field['growth_stage']) : 'Vegetative'; ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label"><i class="fas fa-calendar-check"></i> Next Activity:</div>
                        <div class="detail-value"><?php echo isset($field['next_activity']) ? htmlspecialchars($field['next_activity']) : 'Inspection (scheduled)'; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-7">
            <div class="field-weather-card">
                <div class="weather-header">
                    <div class="current-condition">
                        <i class="fas fa-<?php echo getWeatherIconClass($weatherData['condition']); ?> weather-icon"></i>
                        <div class="condition-text"><?php echo htmlspecialchars($weatherData['condition']); ?></div>
                    </div>
                    
                    <div class="current-temp">
                        <span class="temp-value"><?php echo round($weatherData['temperature']); ?></span>
                        <span class="temp-unit">°C</span>
                    </div>
                    
                    <div class="last-updated">
                        <i class="fas fa-clock"></i> Updated: <?php echo date('g:i A', strtotime($weatherData['recorded_at'])); ?>
                    </div>
                </div>
                
                <div class="weather-metrics">
                    <div class="metric-item">
                        <div class="metric-icon"><i class="fas fa-tint"></i></div>
                        <div class="metric-value"><?php echo round($weatherData['humidity']); ?>%</div>
                        <div class="metric-label">Humidity</div>
                    </div>
                    
                    <div class="metric-item">
                        <div class="metric-icon"><i class="fas fa-wind"></i></div>
                        <div class="metric-value"><?php echo round($weatherData['wind_speed'], 1); ?> km/h</div>
                        <div class="metric-label">Wind</div>
                    </div>
                    
                    <div class="metric-item">
                        <div class="metric-icon"><i class="fas fa-water"></i></div>
                        <div class="metric-value"><?php echo round($weatherData['soil_moisture']); ?>%</div>
                        <div class="metric-label">Soil Moisture</div>
                    </div>
                    
                    <div class="metric-item">
                        <div class="metric-icon"><i class="fas fa-temperature-low"></i></div>
                        <div class="metric-value"><?php echo round($weatherData['soil_temperature']); ?>°C</div>
                        <div class="metric-label">Soil Temp</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Field Tabs -->
    <ul class="nav nav-tabs field-tabs mb-3" id="fieldTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="overview-tab" data-toggle="tab" href="#overview" role="tab">
                <i class="fas fa-chart-line"></i> Overview
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="alerts-tab" data-toggle="tab" href="#alerts" role="tab">
                <i class="fas fa-exclamation-triangle"></i> Alerts &amp; Recommendations
                <?php if (count($fieldAlerts) > 0): ?>
                <span class="badge badge-pill badge-warning"><?php echo count($fieldAlerts); ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="history-tab" data-toggle="tab" href="#history" role="tab">
                <i class="fas fa-history"></i> Weather History
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="activities-tab" data-toggle="tab" href="#activities" role="tab">
                <i class="fas fa-tasks"></i> Activities
            </a>
        </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content" id="fieldTabsContent">
        <!-- Overview Tab -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="m-0"><i class="fas fa-temperature-high"></i> Temperature Trends (7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="temperatureChart" width="100%" height="300"></canvas>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="m-0"><i class="fas fa-tint"></i> Soil Moisture &amp; Humidity Trends (7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="moistureChart" width="100%" height="300"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="m-0"><i class="fas fa-leaf"></i> Crop Health Status</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="health-gauge">
                                <?php 
                                // Sample crop health calculation (in real app, this would be from sensors or AI)
                                $healthPercentage = 85;
                                $healthClass = 'success';
                                if ($healthPercentage < 60) {
                                    $healthClass = 'danger';
                                } elseif ($healthPercentage < 80) {
                                    $healthClass = 'warning';
                                }
                                ?>
                                <div class="gauge-value"><?php echo $healthPercentage; ?>%</div>
                                <div class="gauge-label text-<?php echo $healthClass; ?>">Good</div>
                                <div class="progress mt-2">
                                    <div class="progress-bar bg-<?php echo $healthClass; ?>" role="progressbar" 
                                         style="width: <?php echo $healthPercentage; ?>%" 
                                         aria-valuenow="<?php echo $healthPercentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            
                            <div class="health-factors mt-4">
                                <h6>Contributing Factors:</h6>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Moisture Levels
                                        <span class="badge badge-success badge-pill">Optimal</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Temperature
                                        <span class="badge badge-success badge-pill">Optimal</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Pest Pressure
                                        <span class="badge badge-warning badge-pill">Low</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="m-0"><i class="fas fa-calendar-day"></i> 3-Day Forecast</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php
                                // Sample forecast data
                                $forecast = [
                                    ['day' => 'Today', 'condition' => 'Partly Cloudy', 'high' => 25, 'low' => 18, 'icon' => 'cloud-sun'],
                                    ['day' => 'Tomorrow', 'condition' => 'Sunny', 'high' => 26, 'low' => 19, 'icon' => 'sun'],
                                    ['day' => date('D', strtotime('+2 days')), 'condition' => 'Partly Cloudy', 'high' => 24, 'low' => 17, 'icon' => 'cloud-sun']
                                ];
                                
                                foreach ($forecast as $day):
                                ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="forecast-day"><?php echo $day['day']; ?></div>
                                        <div class="forecast-icon">
                                            <i class="fas fa-<?php echo $day['icon']; ?>"></i>
                                        </div>
                                        <div class="forecast-condition"><?php echo $day['condition']; ?></div>
                                        <div class="forecast-temp">
                                            <span class="high-temp"><?php echo $day['high']; ?>°</span> /
                                            <span class="low-temp"><?php echo $day['low']; ?>°</span>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Alerts & Recommendations Tab -->
        <div class="tab-pane fade" id="alerts" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="m-0">Field Specific Alerts &amp; Recommendations</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($fieldAlerts)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                        <h5>No alerts at this time</h5>
                        <p class="text-muted">Current conditions are optimal for your crop.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($fieldAlerts as $alert): ?>
                        <div class="alert alert-<?php echo $alert['type']; ?> d-flex align-items-center">
                            <div class="alert-icon mr-3">
                                <i class="fas fa-<?php echo $alert['icon']; ?> fa-2x"></i>
                            </div>
                            <div class="alert-content">
                                <h5 class="alert-heading"><?php echo $alert['title']; ?></h5>
                                <p class="mb-0"><?php echo $alert['description']; ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Weather History Tab -->
        <div class="tab-pane fade" id="history" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="m-0">Weather History Data</h5>
                    <div class="history-filters">
                        <select class="form-control form-control-sm" id="historyRange">
                            <option value="7">Last 7 days</option>
                            <option value="14">Last 14 days</option>
                            <option value="30">Last 30 days</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Avg Temp (°C)</th>
                                    <th>High (°C)</th>
                                    <th>Low (°C)</th>
                                    <th>Humidity (%)</th>
                                    <th>Soil Moisture (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historicalData as $data): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($data['date'])); ?></td>
                                    <td><?php echo round($data['avg_temp'], 1); ?></td>
                                    <td><?php echo round($data['max_temp'], 1); ?></td>
                                    <td><?php echo round($data['min_temp'], 1); ?></td>
                                    <td><?php echo round($data['avg_humidity']); ?></td>
                                    <td><?php echo round($data['avg_soil_moisture']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Activities Tab -->
        <div class="tab-pane fade" id="activities" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="m-0">Recent Field Activities</h5>
                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addActivityModal">
                        <i class="fas fa-plus"></i> Add Activity
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($activities)): ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No activities recorded for this field yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="activity-timeline">
                        <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php 
                                $iconClass = 'info';
                                $icon = 'calendar-check';
                                
                                // Determine icon based on activity type
                                switch(strtolower($activity['activity_type'])) {
                                    case 'irrigation':
                                        $icon = 'tint';
                                        $iconClass = 'primary';
                                        break;
                                    case 'fertilization':
                                        $icon = 'seedling';
                                        $iconClass = 'success';
                                        break;
                                    case 'pesticide':
                                        $icon = 'bug';
                                        $iconClass = 'warning';
                                        break;
                                    case 'harvest':
                                        $icon = 'shopping-basket';
                                        $iconClass = 'success';
                                        break;
                                    case 'planting':
                                        $icon = 'leaf';
                                        $iconClass = 'success';
                                        break;
                                    case 'inspection':
                                    default:
                                        $icon = 'clipboard-check';
                                        $iconClass = 'info';
                                }
                                ?>
                                <div class="icon-circle bg-<?php echo $iconClass; ?>">
                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                </div>
                            </div>
                            <div class="activity-content">
                                <h6><?php echo htmlspecialchars($activity['activity_type']); ?></h6>
                                <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                <div class="activity-meta">
                                    <span class="meta-date">
                                        <i class="far fa-calendar-alt"></i> 
                                        <?php echo date('M d, Y', strtotime($activity['activity_date'])); ?>
                                    </span>
                                    <span class="meta-time">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('g:i A', strtotime($activity['activity_date'])); ?>
                                    </span>
                                    <span class="meta-user">
                                        <i class="far fa-user"></i>
                                        <?php echo htmlspecialchars($activity['performed_by']); ?>
                                    </span>
                                    <span class="meta-status badge badge-<?php echo $activity['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($activity['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Activity Modal -->
    <div class="modal fade" id="addActivityModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Field Activity</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="addActivityForm" action="process_activity.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="field_id" value="<?php echo $field_id; ?>">
                        
                        <div class="form-group">
                            <label for="activityType">Activity Type</label>
                            <select class="form-control" id="activityType" name="activity_type" required>
                                <option value="">Select Activity Type</option>
                                <option value="Irrigation">Irrigation</option>
                                <option value="Fertilization">Fertilization</option>
                                <option value="Pesticide">Pesticide Application</option>
                                <option value="Inspection">Inspection</option>
                                <option value="Planting">Planting</option>
                                <option value="Harvest">Harvest</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="activityDate">Date & Time</label>
                            <input type="datetime-local" class="form-control" id="activityDate" name="activity_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="performedBy">Performed By</label>
                            <input type="text" class="form-control" id="performedBy" name="performed_by" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="completed">Completed</option>
                                <option value="in_progress">In Progress</option>
                                <option value="scheduled">Scheduled</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Activity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<style>
/* Field Info Card */
.field-info-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    overflow: hidden;
    height: 100%;
}

.field-header {
    padding: 20px;
    background: linear-gradient(to right, #20c997, #0d6efd);
    color: white;
}

.field-header h3 {
    margin: 0 0 10px 0;
    font-size: 24px;
    font-weight: 600;
}

.field-details {
    padding: 20px;
}

.detail-row {
    display: flex;
    margin-bottom: 15px;
    align-items: center;
}

.detail-label {
    width: 140px;
    font-weight: 500;
    color: #6c757d;
}

.detail-label i {
    margin-right: 8px;
    width: 16px;
}

.detail-value {
    flex: 1;
}

/* Field Weather Card */
.field-weather-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    height: 100%;
}

.weather-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.current-condition {
    text-align: center;
}

.weather-icon {
    font-size: 32px;
    color: #6c757d;
    margin-bottom: 5px;
}

.condition-text {
    font-size: 14px;
    color: #6c757d;
}

.current-temp {
    display: flex;
    align-items: baseline;
}

.temp-value {
    font-size: 48px;
    font-weight: 300;
    line-height: 1;
}

.temp-unit {
    font-size: 20px;
    margin-left: 3px;
}

.last-updated {
    font-size: 12px;
    color: #6c757d;
}

.weather-metrics {
    display: flex;
    padding: 20px;
}

.metric-item {
    flex: 1;
    text-align: center;
    border-right: 1px solid #eee;
    padding: 0 10px;
}

.metric-item:last-child {
    border-right: none;
}

.metric-icon {
    font-size: 24px;
    color: #6c757d;
    margin-bottom: 8px;
}

.metric-value {
    font-size: 20px;
    font-weight: 500;
    margin-bottom: 5px;
}

.metric-label {
    font-size: 12px;
    color: #6c757d;
}

/* Field Tabs */
.field-tabs .nav-link {
    display: flex;
    align-items: center;
    padding: 10px 15px;
}

.field-tabs .nav-link i {
    margin-right: 8px;
}

.field-tabs .badge {
    margin-left: 8px;
}

/* Health Gauge */
.health-gauge {
    margin: 20px 0;
}

.gauge-value {
    font-size: 48px;
    font-weight: 300;
    margin-bottom: 5px;
}

.gauge-label {
    font-size: 18px;
    font-weight: 500;
    margin-bottom: 10px;
}

/* Activity Timeline */
.activity-timeline {
    position: relative;
}

.activity-item {
    display: flex;
    margin-bottom: 25px;
    position: relative;
}

.activity-item:last-child {
    margin-bottom: 0;
}

.activity-icon {
    margin-right: 20px;
}

.icon-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.activity-content {
    flex: 1;
}

.activity-content h6 {
    margin: 0 0 5px 0;
    font-weight: 600;
}

.activity-content p {
    margin-bottom: 8px;
}

.activity-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    font-size: 12px;
    color: #6c757d;
}

/* Forecast styles */
.forecast-day {
    font-weight: 500;
}

.forecast-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
}

.high-temp {
    font-weight: 500;
}

.low-temp {
    color: #6c757d;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .weather-header, .weather-metrics {
        flex-direction: column;
        text-align: center;
    }
    
    .metric-item {
        margin-bottom: 15px;
        border-right: none;
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
    }
    
    .metric-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }
    
    .activity-item {
        flex-direction: column;
    }
    
    .activity-icon {
        margin-right: 0;
        margin-bottom: 15px;
    }
}
</style>

<script>
// Function to refresh field data
function refreshFieldData() {
    // Add loading indicator
    const contentDiv = document.querySelector('.main-content');
    contentDiv.style.opacity = '0.6';
    contentDiv.style.pointerEvents = 'none';
    
    const loadingEl = document.createElement('div');
    loadingEl.classList.add('text-center', 'position-fixed');
    loadingEl.style.top = '50%';
    loadingEl.style.left = '50%';
    loadingEl.style.transform = 'translate(-50%, -50%)';
    loadingEl.innerHTML = '<i class="fas fa-sync fa-spin fa-3x text-primary"></i><p class="mt-2">Updating field data...</p>';
    document.body.appendChild(loadingEl);
    
    // Simulate API call delay
    setTimeout(() => {
        // In a real app, this would be an AJAX call to refresh the data
        location.reload();
    }, 1500);
}

// Set up charts once DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Temperature Chart
    const tempCtx = document.getElementById('temperatureChart').getContext('2d');
    const tempChart = new Chart(tempCtx, {
        type: 'line',
        data: {
            labels: [
                <?php 
                foreach (array_reverse($historicalData) as $data) {
                    echo "'" . date('M d', strtotime($data['date'])) . "', ";
                }
                ?>
            ],
            datasets: [
                {
                    label: 'Max Temperature',
                    data: [
                        <?php 
                        foreach (array_reverse($historicalData) as $data) {
                            echo round($data['max_temp'], 1) . ", ";
                        }
                        ?>
                    ],
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderWidth: 2,
                    tension: 0.3
                },
                {
                    label: 'Avg Temperature',
                    data: [
                        <?php 
                        foreach (array_reverse($historicalData) as $data) {
                            echo round($data['avg_temp'], 1) . ", ";
                        }
                        ?>
                    ],
                    borderColor: '#fd7e14',
                    backgroundColor: 'rgba(253, 126, 20, 0.1)',
                    borderWidth: 2,
                    tension: 0.3
                },
                {
                    label: 'Min Temperature',
                    data: [
                        <?php 
                        foreach (array_reverse($historicalData) as $data) {
                            echo round($data['min_temp'], 1) . ", ";
                        }
                        ?>
                    ],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Temperature (°C)'
                    }
                }
            }
        }
    });
    
    // Moisture & Humidity Chart
    const moistureCtx = document.getElementById('moistureChart').getContext('2d');
    const moistureChart = new Chart(moistureCtx, {
        type: 'line',
        data: {
            labels: [
                <?php 
                foreach (array_reverse($historicalData) as $data) {
                    echo "'" . date('M d', strtotime($data['date'])) . "', ";
                }
                ?>
            ],
            datasets: [
                {
                    label: 'Humidity (%)',
                    data: [
                        <?php 
                        foreach (array_reverse($historicalData) as $data) {
                            echo round($data['avg_humidity']) . ", ";
                        }
                        ?>
                    ],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    tension: 0.3
                },
                {
                    label: 'Soil Moisture (%)',
                    data: [
                        <?php 
                        foreach (array_reverse($historicalData) as $data) {
                            echo round($data['avg_soil_moisture']) . ", ";
                        }
                        ?>
                    ],
                    borderColor: '#20c997',
                    backgroundColor: 'rgba(32, 201, 151, 0.1)',
                    borderWidth: 2,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Percentage (%)'
                    }
                }
            }
        }
    });
    
    // Handle tabs functionality if not using Bootstrap's built-in tabs
    const tabLinks = document.querySelectorAll('.nav-link');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabLinks.forEach(tabLink => {
        tabLink.addEventListener('click', function(e) {
            if (!this.classList.contains('active')) {
                // Remove active class from all tabs
                tabLinks.forEach(link => link.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Hide all tab panes
                tabPanes.forEach(pane => {
                    pane.classList.remove('show', 'active');
                });
                
                // Show the corresponding tab pane
                const tabId = this.getAttribute('href').substring(1);
                document.getElementById(tabId).classList.add('show', 'active');
            }
            
            e.preventDefault();
        });
    });
    
    // Set up date picker for activity form
    const activityDateField = document.getElementById('activityDate');
    if (activityDateField) {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        
        activityDateField.value = `${year}-${month}-${day}T${hours}:${minutes}`;
    }
});
</script>

<?php include 'includes/footer.php'; ?>