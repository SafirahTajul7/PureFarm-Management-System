<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Set default values for filters
$days_ahead = isset($_GET['days_ahead']) ? intval($_GET['days_ahead']) : 7;
$field_id = isset($_GET['field_id']) ? intval($_GET['field_id']) : 0;

// Fetch all fields for dropdown
try {
    $fields_stmt = $pdo->query("SELECT id, field_name FROM fields ORDER BY field_name");
    $fields = $fields_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

// Check if weather_forecast table exists
$forecast_table_exists = false;
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'weather_forecast'");
    $forecast_table_exists = $table_check->rowCount() > 0;
    
    if (!$forecast_table_exists) {
        // Create the weather_forecast table if it doesn't exist
        $pdo->exec("
            CREATE TABLE `weather_forecast` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `field_id` int(11) NOT NULL,
                `forecast_date` date NOT NULL,
                `temperature_high` float NOT NULL,
                `temperature_low` float NOT NULL,
                `humidity` float NOT NULL,
                `precipitation_chance` float NOT NULL,
                `precipitation_amount` float DEFAULT NULL,
                `wind_speed` float DEFAULT NULL,
                `wind_direction` int(11) DEFAULT NULL,
                `conditions` varchar(50) DEFAULT NULL,
                `notes` text DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `field_id` (`field_id`),
                KEY `forecast_date` (`forecast_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        // Insert sample forecast data
        $field_ids = array_column($fields, 'id');
        if (!empty($field_ids)) {
            // Generate data for the next 7 days
            $sample_data = [];
            for ($i = 1; $i <= 14; $i++) {
                foreach ($field_ids as $field_id) {
                    // Generate random but somewhat realistic weather patterns
                    $base_temp = 20 + rand(-5, 5) + sin($i / 7 * M_PI) * 5; // Simulate temperature patterns
                    $temp_high = $base_temp + rand(2, 6);
                    $temp_low = $base_temp - rand(3, 8);
                    $humidity = rand(40, 90);
                    $precip_chance = rand(0, 100);
                    $precip_amount = $precip_chance > 60 ? rand(1, 30) / 10 : 0;
                    $conditions = $precip_chance > 80 ? 'Rain' : ($precip_chance > 60 ? 'Cloudy' : ($precip_chance > 30 ? 'Partly Cloudy' : 'Clear'));
                    
                    $sample_data[] = [
                        'field_id' => $field_id,
                        'forecast_date' => date('Y-m-d', strtotime("+$i days")),
                        'temperature_high' => $temp_high,
                        'temperature_low' => $temp_low,
                        'humidity' => $humidity,
                        'precipitation_chance' => $precip_chance,
                        'precipitation_amount' => $precip_amount,
                        'wind_speed' => rand(0, 40),
                        'wind_direction' => rand(0, 359),
                        'conditions' => $conditions,
                        'notes' => $conditions == 'Rain' ? 'Possible thunderstorms in the afternoon.' : null
                    ];
                }
            }
            
            // Insert sample data in batches
            $insert_stmt = $pdo->prepare("
                INSERT INTO weather_forecast
                (field_id, forecast_date, temperature_high, temperature_low, humidity, 
                precipitation_chance, precipitation_amount, wind_speed, wind_direction, conditions, notes)
                VALUES 
                (:field_id, :forecast_date, :temperature_high, :temperature_low, :humidity,
                :precipitation_chance, :precipitation_amount, :wind_speed, :wind_direction, :conditions, :notes)
            ");
            
            foreach ($sample_data as $data) {
                $insert_stmt->execute($data);
            }
        }
        
        $forecast_table_exists = true;
    }
} catch(PDOException $e) {
    error_log("Error checking or creating weather_forecast table: " . $e->getMessage());
}

// Fetch forecast data based on filters
try {
    if ($forecast_table_exists) {
        $query = "
            SELECT 
                f.forecast_date as date,
                f.temperature_high,
                f.temperature_low,
                f.humidity,
                f.precipitation_chance,
                f.precipitation_amount,
                f.wind_speed,
                f.wind_direction,
                f.conditions,
                f.notes,
                fd.field_name
            FROM 
                weather_forecast f
            JOIN
                fields fd ON f.field_id = fd.id
            WHERE 
                f.forecast_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL :days_ahead DAY)
        ";
        
        $params = [
            ':days_ahead' => $days_ahead
        ];
        
        if ($field_id > 0) {
            $query .= " AND f.field_id = :field_id";
            $params[':field_id'] = $field_id;
        }
        
        $query .= " ORDER BY f.forecast_date ASC, fd.field_name ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $forecast_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get data for charts
        $chart_query = "
            SELECT 
                forecast_date as date,
                AVG(temperature_high) as avg_temp_high,
                AVG(temperature_low) as avg_temp_low,
                AVG(humidity) as avg_humidity,
                AVG(precipitation_chance) as avg_precip_chance
            FROM 
                weather_forecast
            WHERE 
                forecast_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL :days_ahead DAY)
        ";
        
        $chart_params = [
            ':days_ahead' => $days_ahead
        ];
        
        if ($field_id > 0) {
            $chart_query .= " AND field_id = :field_id";
            $chart_params[':field_id'] = $field_id;
        }
        
        $chart_query .= " GROUP BY forecast_date ORDER BY forecast_date ASC";
        
        $chart_stmt = $pdo->prepare($chart_query);
        $chart_stmt->execute($chart_params);
        $chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data for charts
        $dates = [];
        $temps_high = [];
        $temps_low = [];
        $humidity = [];
        $precip_chance = [];
        
        foreach($chart_data as $row) {
            $dates[] = date('M d', strtotime($row['date']));
            $temps_high[] = round($row['avg_temp_high'], 1);
            $temps_low[] = round($row['avg_temp_low'], 1);
            $humidity[] = round($row['avg_humidity'], 1);
            $precip_chance[] = round($row['avg_precip_chance'], 1);
        }
    } else {
        $forecast_data = [];
        $dates = [];
        $temps_high = [];
        $temps_low = [];
        $humidity = [];
        $precip_chance = [];
    }
    
    // Convert to JSON for JavaScript
    $dates_json = json_encode($dates);
    $temps_high_json = json_encode($temps_high);
    $temps_low_json = json_encode($temps_low);
    $humidity_json = json_encode($humidity);
    $precip_chance_json = json_encode($precip_chance);
    
} catch(PDOException $e) {
    error_log("Error fetching forecast data: " . $e->getMessage());
    $forecast_data = [];
    
    // Set empty arrays for chart data
    $dates_json = json_encode([]);
    $temps_high_json = json_encode([]);
    $temps_low_json = json_encode([]);
    $humidity_json = json_encode([]);
    $precip_chance_json = json_encode([]);
}

$pageTitle = 'Weather Forecast';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-calendar-day"></i> Weather Forecast</h2>
        <div class="action-buttons">
            <a href="current_weather.php" class="btn btn-primary">
                <i class="fas fa-cloud-sun"></i> Current Weather
            </a>
            <a href="weather_history.php" class="btn btn-primary">
                <i class="fas fa-history"></i> Weather History
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="filter-section mb-4">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="days_ahead" class="form-label">Forecast Period</label>
                <select class="form-select" id="days_ahead" name="days_ahead">
                    <option value="3" <?php echo ($days_ahead == 3) ? 'selected' : ''; ?>>3 Days</option>
                    <option value="7" <?php echo ($days_ahead == 7) ? 'selected' : ''; ?>>7 Days</option>
                    <option value="14" <?php echo ($days_ahead == 14) ? 'selected' : ''; ?>>14 Days</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="field_id" class="form-label">Field</label>
                <select class="form-select" id="field_id" name="field_id">
                    <option value="0">All Fields</option>
                    <?php foreach($fields as $field): ?>
                        <option value="<?php echo $field['id']; ?>" <?php echo ($field_id == $field['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($field['field_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Weather Forecast Charts -->
    <div class="section-header">
        <h3><i class="fas fa-chart-line"></i> Forecast Overview</h3>
    </div>

    <div class="charts-container mb-4">
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="chart-card">
                    <h4>Temperature Forecast</h4>
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="temperatureChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="chart-card">
                    <h4>Precipitation Chance</h4>
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="precipitationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Forecast Cards -->
    <div class="section-header">
        <h3><i class="fas fa-calendar-alt"></i> Daily Forecast</h3>
    </div>

    <div class="daily-forecast mb-4">
        <div class="row">
            <?php 
            // Group forecast data by date
            $forecasts_by_date = [];
            $current_date = '';
            
            if (!empty($forecast_data)) {
                foreach ($forecast_data as $forecast) {
                    $date = $forecast['date'];
                    if (!isset($forecasts_by_date[$date])) {
                        $forecasts_by_date[$date] = [];
                    }
                    $forecasts_by_date[$date][] = $forecast;
                }
                
                // Display forecasts by date
                foreach ($forecasts_by_date as $date => $forecasts) {
                    // Calculate averages for this date
                    $avg_high = 0;
                    $avg_low = 0;
                    $avg_humidity = 0;
                    $avg_precip_chance = 0;
                    $count = count($forecasts);
                    
                    foreach ($forecasts as $f) {
                        $avg_high += $f['temperature_high'];
                        $avg_low += $f['temperature_low'];
                        $avg_humidity += $f['humidity'];
                        $avg_precip_chance += $f['precipitation_chance'];
                    }
                    
                    if ($count > 0) {
                        $avg_high /= $count;
                        $avg_low /= $count;
                        $avg_humidity /= $count;
                        $avg_precip_chance /= $count;
                    }
                    
                    // Determine the most common condition
                    $conditions_count = [];
                    foreach ($forecasts as $f) {
                        if (!isset($conditions_count[$f['conditions']])) {
                            $conditions_count[$f['conditions']] = 0;
                        }
                        $conditions_count[$f['conditions']]++;
                    }
                    arsort($conditions_count);
                    $common_condition = key($conditions_count);
                    
                    // Get icon for the condition
                    $icon = 'cloud';
                    $condition = strtolower($common_condition);
                    
                    if (strpos($condition, 'clear') !== false || strpos($condition, 'sunny') !== false) {
                        $icon = 'sun';
                    } elseif (strpos($condition, 'rain') !== false) {
                        $icon = 'cloud-rain';
                    } elseif (strpos($condition, 'snow') !== false) {
                        $icon = 'snowflake';
                    } elseif (strpos($condition, 'thunder') !== false || strpos($condition, 'storm') !== false) {
                        $icon = 'bolt';
                    } elseif (strpos($condition, 'fog') !== false || strpos($condition, 'mist') !== false) {
                        $icon = 'smog';
                    } elseif (strpos($condition, 'cloud') !== false) {
                        $icon = 'cloud';
                    } elseif (strpos($condition, 'partly') !== false) {
                        $icon = 'cloud-sun';
                    }
                    
                    // Format date
                    $formatted_date = date('D, M d', strtotime($date));
                    $is_today = (date('Y-m-d') == $date);
                    
                    // Now display the forecast card
                    ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="forecast-card <?php echo $is_today ? 'today' : ''; ?>">
                            <div class="forecast-date">
                                <?php echo $is_today ? 'Today' : $formatted_date; ?>
                            </div>
                            <div class="forecast-icon">
                                <i class="fas fa-<?php echo $icon; ?>"></i>
                            </div>
                            <div class="forecast-condition">
                                <?php echo htmlspecialchars($common_condition); ?>
                            </div>
                            <div class="forecast-temp">
                                <span class="high"><?php echo round($avg_high, 1); ?>°C</span> / 
                                <span class="low"><?php echo round($avg_low, 1); ?>°C</span>
                            </div>
                            <div class="forecast-details">
                                <div class="forecast-detail">
                                    <i class="fas fa-tint"></i> 
                                    <span><?php echo round($avg_humidity, 1); ?>%</span>
                                </div>
                                <div class="forecast-detail">
                                    <i class="fas fa-cloud-rain"></i> 
                                    <span><?php echo round($avg_precip_chance, 1); ?>%</span>
                                </div>
                            </div>
                            <?php if ($field_id == 0 && count($fields) > 1): ?>
                                <div class="forecast-fields small text-muted mt-2">
                                    <i class="fas fa-info-circle"></i> 
                                    Average across <?php echo count($forecasts); ?> fields
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo '<div class="col-12"><div class="alert alert-info">No forecast data available for the selected period.</div></div>';
            }
            ?>
        </div>
    </div>

    <!-- Detailed Forecast Table -->
    <div class="section-header">
        <h3><i class="fas fa-table"></i> Detailed Forecast</h3>
    </div>

    <div class="data-table-container mb-4">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Field</th>
                    <th>High/Low</th>
                    <th>Humidity</th>
                    <th>Precipitation</th>
                    <th>Wind</th>
                    <th>Conditions</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($forecast_data)): ?>
                <tr>
                    <td colspan="8" class="text-center">No forecast data available for the selected period.</td>
                </tr>
                <?php else: ?>
                    <?php foreach($forecast_data as $data): ?>
                    <tr>
                        <td><?php echo date('D, M d', strtotime($data['date'])); ?></td>
                        <td><?php echo htmlspecialchars($data['field_name']); ?></td>
                        <td>
                            <span class="text-danger"><?php echo round($data['temperature_high'], 1); ?>°C</span> / 
                            <span class="text-primary"><?php echo round($data['temperature_low'], 1); ?>°C</span>
                        </td>
                        <td><?php echo round($data['humidity'], 1); ?>%</td>
                        <td>
                            <?php echo round($data['precipitation_chance'], 1); ?>% chance
                            <?php if ($data['precipitation_amount'] > 0): ?>
                                <br><small><?php echo round($data['precipitation_amount'], 1); ?> mm</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo round($data['wind_speed'], 1); ?> km/h 
                            <i class="fas fa-arrow-up" style="transform: rotate(<?php echo $data['wind_direction']; ?>deg);"></i>
                        </td>
                        <td>
                            <?php
                            $condition = strtolower($data['conditions']);
                            $icon = 'cloud';
                            
                            if (strpos($condition, 'clear') !== false || strpos($condition, 'sunny') !== false) {
                                $icon = 'sun';
                            } elseif (strpos($condition, 'rain') !== false) {
                                $icon = 'cloud-rain';
                            } elseif (strpos($condition, 'snow') !== false) {
                                $icon = 'snowflake';
                            } elseif (strpos($condition, 'thunder') !== false || strpos($condition, 'storm') !== false) {
                                $icon = 'bolt';
                            } elseif (strpos($condition, 'fog') !== false || strpos($condition, 'mist') !== false) {
                                $icon = 'smog';
                            } elseif (strpos($condition, 'cloud') !== false) {
                                $icon = 'cloud';
                            } elseif (strpos($condition, 'partly') !== false) {
                                $icon = 'cloud-sun';
                            }
                            ?>
                            <i class="fas fa-<?php echo $icon; ?> me-1"></i> <?php echo htmlspecialchars($data['conditions']); ?>
                        </td>
                        <td><?php echo !empty($data['notes']) ? htmlspecialchars($data['notes']) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Weather Advisory Section -->
    <?php if (!empty($forecast_data)): ?>
    <div class="section-header">
        <h3><i class="fas fa-exclamation-triangle"></i> Weather Advisories</h3>
    </div>

    <div class="weather-advisories mb-4">
        <?php
        // Check for potential weather issues
        $high_rain_days = 0;
        $high_wind_days = 0;
        $extreme_temp_days = 0;
        
        foreach ($forecast_data as $data) {
            if ($data['precipitation_chance'] > 70) $high_rain_days++;
            if ($data['wind_speed'] > 30) $high_wind_days++;
            if ($data['temperature_high'] > 32 || $data['temperature_low'] < 5) $extreme_temp_days++;
        }
        
        if ($high_rain_days == 0 && $high_wind_days == 0 && $extreme_temp_days == 0) {
            echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> No significant weather advisories for the forecast period.</div>';
        } else {
            if ($high_rain_days > 0) {
                echo '<div class="alert alert-warning">
                    <i class="fas fa-cloud-rain"></i> <strong>Rainfall Alert:</strong> ' . 
                    'High precipitation expected on ' . $high_rain_days . ' day(s) during the forecast period. ' .
                    'Consider postponing outdoor activities or implementing drainage measures.
                </div>';
            }
            
            if ($high_wind_days > 0) {
                echo '<div class="alert alert-warning">
                    <i class="fas fa-wind"></i> <strong>Wind Alert:</strong> ' . 
                    'Strong winds expected on ' . $high_wind_days . ' day(s) during the forecast period. ' .
                    'Secure loose items and check structures for stability.
                </div>';
            }
            
            if ($extreme_temp_days > 0) {
                echo '<div class="alert alert-warning">
                    <i class="fas fa-thermometer-full"></i> <strong>Temperature Alert:</strong> ' . 
                    'Extreme temperatures expected on ' . $extreme_temp_days . ' day(s) during the forecast period. ' .
                    'Take precautions to protect sensitive crops and livestock.
                </div>';
            }
        }
        ?>
    </div>
    <?php endif; ?>

    <!-- Farm Operations Recommendations -->
    <div class="section-header">
        <h3><i class="fas fa-tasks"></i> Recommended Actions</h3>
    </div>

    <div class="recommendations-container mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Weather-Based Farm Recommendations</h5>
                <div class="row">
                    <?php
                    // Generate recommendations based on forecast data
                    $recommendations = [];
                    
                    if (!empty($forecast_data)) {
                        $avg_precip_chance = 0;
                        $count = count($forecast_data);
                        
                        foreach ($forecast_data as $data) {
                            $avg_precip_chance += $data['precipitation_chance'];
                        }
                        
                        if ($count > 0) {
                            $avg_precip_chance /= $count;
                        }
                        
                        // Irrigation recommendation
                        if ($avg_precip_chance < 30) {
                            $recommendations[] = [
                                'icon' => 'fa-water',
                                'title' => 'Irrigation',
                                'text' => 'Low precipitation forecast. Consider scheduling irrigation for crops.'
                            ];
                        } elseif ($avg_precip_chance > 70) {
                            $recommendations[] = [
                                'icon' => 'fa-tint-slash',
                                'title' => 'Irrigation',
                                'text' => 'High precipitation forecast. Reduce irrigation to prevent over-watering.'
                            ];
                        }
                        
                        // Planting recommendation
                        $good_planting_days = 0;
                        foreach ($forecast_data as $data) {
                            if ($data['precipitation_chance'] < 40 && $data['wind_speed'] < 20 && 
                                $data['temperature_high'] > 15 && $data['temperature_high'] < 30) {
                                $good_planting_days++;
                            }
                        }
                        
                        if ($good_planting_days > 2) {
                            $recommendations[] = [
                                'icon' => 'fa-seedling',
                                'title' => 'Planting',
                                'text' => 'Favorable conditions for planting in the next few days.'
                            ];
                        } else {
                            $recommendations[] = [
                                'icon' => 'fa-seedling',
                                'title' => 'Planting',
                                'text' => 'Limited favorable planting windows. Consider delaying non-urgent planting.'
                            ];
                        }
                        
                        // Crop protection recommendation
                        $needs_protection = false;
                        foreach ($forecast_data as $data) {
                            if ($data['temperature_high'] > 32 || $data['temperature_low'] < 5 || 
                                $data['wind_speed'] > 30 || ($data['precipitation_chance'] > 70 && $data['precipitation_amount'] > 2)) {
                                $needs_protection = true;
                                break;
                            }
                        }
                        
                        if ($needs_protection) {
                            $recommendations[] = [
                                'icon' => 'fa-shield-alt',
                                'title' => 'Crop Protection',
                                'text' => 'Extreme weather conditions expected. Consider protective measures for sensitive crops.'
                            ];
                        }
                        
                        // Harvesting recommendation
                        $good_harvest_days = 0;
                        foreach ($forecast_data as $data) {
                            if ($data['precipitation_chance'] < 30 && $data['humidity'] < 70) {
                                $good_harvest_days++;
                            }
                        }
                        
                        if ($good_harvest_days > 2) {
                            $recommendations[] = [
                                'icon' => 'fa-cut',
                                'title' => 'Harvesting',
                                'text' => 'Good conditions for harvesting in the next few days. Plan accordingly.'
                            ];
                        }
                    }
                    
                    // Add general recommendations if no specific ones
                    if (empty($recommendations)) {
                        $recommendations[] = [
                            'icon' => 'fa-clipboard-check',
                            'title' => 'General',
                            'text' => 'Monitor weather conditions regularly and adjust farm operations as needed.'
                        ];
                        $recommendations[] = [
                            'icon' => 'fa-tools',
                            'title' => 'Maintenance',
                            'text' => 'Good time to perform routine equipment maintenance and facility repairs.'
                        ];
                    }
                    
                    // Display recommendations
                    foreach ($recommendations as $index => $rec) {
                        echo '<div class="col-md-6 mb-3">
                            <div class="recommendation-item">
                                <div class="recommendation-icon">
                                    <i class="fas ' . $rec['icon'] . '"></i>
                                </div>
                                <div class="recommendation-content">
                                    <h6>' . $rec['title'] . '</h6>
                                    <p>' . $rec['text'] . '</p>
                                </div>
                            </div>
                        </div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .main-content {
        padding-bottom: 60px;
        min-height: calc(100vh - 60px);
    }

    .filter-section {
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .chart-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        padding: 20px;
        height: 100%;
    }

    .chart-card h4 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #333;
        font-size: 18px;
    }

    .data-table-container {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        padding: 20px;
        overflow-x: auto;
    }

    .section-header {
        margin: 20px 0;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 10px;
    }
    
    .btn {
        margin-left: 5px;
    }
    
    /* Forecast cards */
    .forecast-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        padding: 20px;
        text-align: center;
        height: 100%;
        transition: all 0.3s ease;
    }
    
    .forecast-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }
    
    .forecast-card.today {
        border-left: 4px solid #0d6efd;
    }
    
    .forecast-date {
        font-weight: bold;
        font-size: 16px;
        margin-bottom: 15px;
        color: #333;
    }
    
    .forecast-icon {
        font-size: 36px;
        margin-bottom: 15px;
        color: #0d6efd;
    }
    
    .forecast-condition {
        font-size: 18px;
        margin-bottom: 10px;
    }
    
    .forecast-temp {
        margin-bottom: 15px;
    }
    
    .forecast-temp .high {
        color: #dc3545;
        font-weight: bold;
    }
    
    .forecast-temp .low {
        color: #0d6efd;
    }
    
    .forecast-details {
        display: flex;
        justify-content: space-around;
        margin-bottom: 10px;
    }
    
    .forecast-detail {
        text-align: center;
    }
    
    .forecast-detail i {
        display: block;
        font-size: 16px;
        margin-bottom: 5px;
        color: #6c757d;
    }
    
    /* Recommendations */
    .recommendation-item {
        display: flex;
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        height: 100%;
    }
    
    .recommendation-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 50px;
        font-size: 24px;
        color: #0d6efd;
        margin-right: 15px;
    }
    
    .recommendation-content {
        flex: 1;
    }
    
    .recommendation-content h6 {
        margin-top: 0;
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    .recommendation-content p {
        margin-bottom: 0;
        color: #6c757d;
    }
</style>

<footer class="footer">
    <div class="container">
        <p>&copy; 2025 PureFarm Management System. All rights reserved. <span class="float-end">Version 1.0</span></p>
    </div>
</footer>

<!-- Add Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get data from PHP
    const dates = <?php echo $dates_json; ?>;
    const tempsHigh = <?php echo $temps_high_json; ?>;
    const tempsLow = <?php echo $temps_low_json; ?>;
    const humidity = <?php echo $humidity_json; ?>;
    const precipChance = <?php echo $precip_chance_json; ?>;
    
    // Temperature Chart
    if (dates.length > 0) {
        const tempCtx = document.getElementById('temperatureChart').getContext('2d');
        
        new Chart(tempCtx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'High Temperature (°C)',
                        data: tempsHigh,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Low Temperature (°C)',
                        data: tempsLow,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Humidity (%)',
                        data: humidity,
                        borderColor: '#20c997',
                        backgroundColor: 'rgba(32, 201, 151, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y1',
                        hidden: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Temperature (°C)'
                        },
                        suggestedMin: 0,
                        suggestedMax: 40
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Humidity (%)'
                        },
                        min: 0,
                        max: 100,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
        
        // Precipitation Chart
        const precipCtx = document.getElementById('precipitationChart').getContext('2d');
        
        new Chart(precipCtx, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Precipitation Chance (%)',
                        data: precipChance,
                        backgroundColor: function(context) {
                            const value = context.dataset.data[context.dataIndex];
                            return value > 70 ? 'rgba(220, 53, 69, 0.6)' : 
                                   value > 40 ? 'rgba(255, 193, 7, 0.6)' : 
                                   'rgba(13, 110, 253, 0.6)';
                        },
                        borderColor: '#0d6efd',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Chance of Precipitation (%)'
                        }
                    }
                }
            }
        });
    } else {
        // If no data, display message in chart containers
        document.getElementById('temperatureChart').parentNode.innerHTML = 
            '<div style="display: flex; align-items: center; justify-content: center; height: 300px;">' +
            '<p><i class="fas fa-info-circle" style="font-size: 24px; margin-right: 10px;"></i> ' +
            'No forecast data available for the selected period.</p></div>';
            
        document.getElementById('precipitationChart').parentNode.innerHTML = 
            '<div style="display: flex; align-items: center; justify-content: center; height: 300px;">' +
            '<p><i class="fas fa-info-circle" style="font-size: 24px; margin-right: 10px;"></i> ' +
            'No precipitation data available for the selected period.</p></div>';
    }
});
</script>