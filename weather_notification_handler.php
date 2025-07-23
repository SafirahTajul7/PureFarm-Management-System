<?php
/**
 * Simplified Weather Notification Handler
 * 
 * This script checks weather conditions against thresholds 
 * and creates in-system notifications when conditions meet alert criteria.
 * 
 * Run this script via cron job (e.g., every 15-30 minutes) to process notifications.
 */

// Set timezone to Malaysia time (MYT/UTC+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// Load database connection
require_once 'includes/db.php';

// Check if running from CLI or web
$isCLI = (php_sapi_name() === 'cli');

// Create logs directory if it doesn't exist
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

// Initialize log
$log = [];
$log[] = "[" . date('Y-m-d H:i:s') . "] Weather notification check started";

// Set output format based on environment
if (!$isCLI) {
    header('Content-Type: text/plain');
}

// Load weather settings
try {
    $settings = [];
    $stmt = $pdo->query("SELECT setting_name, setting_value FROM weather_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
    
    $log[] = "Loaded " . count($settings) . " settings from database";
} catch (PDOException $e) {
    $log[] = "ERROR: Could not load settings: " . $e->getMessage();
    outputLog($log, $isCLI);
    exit;
}

// Check if in-system notifications are enabled
$inSystemEnabled = isset($settings['notify_system']) && $settings['notify_system'] == 1;
$forecastAlertsEnabled = isset($settings['notify_forecast_alerts']) && $settings['notify_forecast_alerts'] == 1;
$dailySummaryEnabled = isset($settings['notify_daily_summary']) && $settings['notify_daily_summary'] == 1;

if (!$inSystemEnabled) {
    $log[] = "In-system notifications are disabled. Exiting.";
    outputLog($log, $isCLI);
    exit;
}

// Check if we're within the daily summary time window (±5 minutes)
$isDailySummaryTime = false;
if ($dailySummaryEnabled && isset($settings['notification_time'])) {
    $now = new DateTime();
    $summaryTime = DateTime::createFromFormat('H:i', $settings['notification_time']);
    
    if ($summaryTime) {
        $summaryTime->setDate($now->format('Y'), $now->format('m'), $now->format('d'));
        $diff = abs($now->getTimestamp() - $summaryTime->getTimestamp());
        
        // If within 5 minutes of the scheduled time
        if ($diff <= 300) {
            $isDailySummaryTime = true;
            $log[] = "It's time for daily weather summary";
        }
    }
}

// Get latest weather data
try {
    // First check if we have a weather_data table
    $hasWeatherTable = false;
    $tables = $pdo->query("SHOW TABLES LIKE 'weather_data'")->fetchAll();
    if (count($tables) > 0) {
        $hasWeatherTable = true;
    }
    
    if ($hasWeatherTable) {
        // Fetch the most recent weather data for default location
        $defaultLocation = $settings['default_location'] ?? 1;
        
        $stmt = $pdo->prepare("
            SELECT * FROM weather_data 
            WHERE location_id = ? 
            ORDER BY recorded_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$defaultLocation]);
        $weatherData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$weatherData) {
            throw new Exception("No weather data found for the default location");
        }
    } else {
        // Sample data if no table exists
        $weatherData = [
            'temperature' => 30.5, // High temperature for testing
            'humidity' => 92,      // High humidity for testing
            'wind_speed' => 55,    // High wind speed for testing
            'precipitation' => 55,  // High precipitation for testing
            'condition' => 'Thunderstorm',
            'recorded_at' => date('Y-m-d H:i:s')
        ];
        $log[] = "WARNING: No weather_data table found. Using sample data.";
    }
    
    $log[] = "Loaded weather data: " . json_encode($weatherData);
} catch (Exception $e) {
    $log[] = "ERROR: Could not load weather data: " . $e->getMessage();
    outputLog($log, $isCLI);
    exit;
}

// Alerts to send
$alerts = [];

// Check conditions against thresholds
if (isset($settings['alert_temperature']) && $settings['alert_temperature'] == 1) {
    $tempHigh = floatval($settings['temperature_high'] ?? 35);
    $tempLow = floatval($settings['temperature_low'] ?? 10);
    
    if ($weatherData['temperature'] > $tempHigh) {
        $alerts[] = [
            'type' => 'temperature_high',
            'message' => "High temperature alert: Current temperature is {$weatherData['temperature']}°C, which exceeds your threshold of {$tempHigh}°C."
        ];
    }
    
    if ($weatherData['temperature'] < $tempLow) {
        $alerts[] = [
            'type' => 'temperature_low',
            'message' => "Low temperature alert: Current temperature is {$weatherData['temperature']}°C, which is below your threshold of {$tempLow}°C."
        ];
    }
}

if (isset($settings['alert_humidity']) && $settings['alert_humidity'] == 1) {
    $humidityHigh = intval($settings['humidity_high'] ?? 90);
    $humidityLow = intval($settings['humidity_low'] ?? 30);
    
    if ($weatherData['humidity'] > $humidityHigh) {
        $alerts[] = [
            'type' => 'humidity_high',
            'message' => "High humidity alert: Current humidity is {$weatherData['humidity']}%, which exceeds your threshold of {$humidityHigh}%."
        ];
    }
    
    if ($weatherData['humidity'] < $humidityLow) {
        $alerts[] = [
            'type' => 'humidity_low',
            'message' => "Low humidity alert: Current humidity is {$weatherData['humidity']}%, which is below your threshold of {$humidityLow}%."
        ];
    }
}

if (isset($settings['alert_wind']) && $settings['alert_wind'] == 1) {
    $windHigh = intval($settings['wind_speed_high'] ?? 50);
    
    if ($weatherData['wind_speed'] > $windHigh) {
        $alerts[] = [
            'type' => 'wind_high',
            'message' => "High wind speed alert: Current wind speed is {$weatherData['wind_speed']} km/h, which exceeds your threshold of {$windHigh} km/h."
        ];
    }
}

if (isset($settings['alert_precipitation']) && $settings['alert_precipitation'] == 1) {
    $precipitationHigh = intval($settings['precipitation_high'] ?? 50);
    
    if ($weatherData['precipitation'] > $precipitationHigh) {
        $alerts[] = [
            'type' => 'precipitation_high',
            'message' => "Heavy precipitation alert: Current precipitation is {$weatherData['precipitation']} mm, which exceeds your threshold of {$precipitationHigh} mm."
        ];
    }
}

// Check for severe weather conditions
$severeConditions = ['thunderstorm', 'storm', 'heavy rain', 'flood', 'typhoon', 'hurricane', 'tornado'];
if ($forecastAlertsEnabled && strpos(strtolower($weatherData['condition']), 'thunderstorm') !== false) {
    $alerts[] = [
        'type' => 'severe_weather',
        'message' => "Severe weather alert: {$weatherData['condition']} detected in your area."
    ];
}

$log[] = "Found " . count($alerts) . " alerts to send";

// Create daily summary if it's time
if ($isDailySummaryTime) {
    // Get forecast data (in a real system, this would be from your forecast API or database)
    $forecastSummary = "Today's forecast: Partly cloudy with temperatures between 24-32°C. 30% chance of rain in the afternoon.";
    
    $dailySummary = [
        'type' => 'daily_summary',
        'message' => "Daily Weather Summary for " . date('F j, Y') . "\n\n" .
                     "Current conditions: {$weatherData['condition']}\n" .
                     "Temperature: {$weatherData['temperature']}°C\n" .
                     "Humidity: {$weatherData['humidity']}%\n" .
                     "Wind Speed: {$weatherData['wind_speed']} km/h\n" .
                     "Precipitation: {$weatherData['precipitation']} mm\n\n" .
                     $forecastSummary
    ];
    
    // Add to alerts
    $alerts[] = $dailySummary;
}

// Process alerts
if (count($alerts) > 0) {
    // Save in-system notifications
    try {
        // Check if notifications table exists
        $hasTable = false;
        $tables = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetchAll();
        if (count($tables) > 0) {
            $hasTable = true;
        }
        
        if (!$hasTable) {
            // Create notifications table
            $pdo->exec("
                CREATE TABLE notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    type VARCHAR(50) NOT NULL,
                    message TEXT NOT NULL,
                    is_read TINYINT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $log[] = "Created notifications table";
        }
        
        // Insert notifications
        $stmt = $pdo->prepare("
            INSERT INTO notifications (type, message) 
            VALUES (?, ?)
        ");
        
        foreach ($alerts as $alert) {
            $stmt->execute([$alert['type'], $alert['message']]);
        }
        
        $log[] = "Saved " . count($alerts) . " in-system notifications";
    } catch (PDOException $e) {
        $log[] = "ERROR: Could not save in-system notifications: " . $e->getMessage();
    }
} else {
    $log[] = "No alerts to send";
}

$log[] = "[" . date('Y-m-d H:i:s') . "] Weather notification check completed";

// Output log
outputLog($log, $isCLI);

/**
 * Output log based on environment
 */
function outputLog($log, $isCLI) {
    foreach ($log as $entry) {
        if ($isCLI) {
            echo $entry . PHP_EOL;
        } else {
            echo htmlspecialchars($entry) . "<br>";
        }
    }
    
    // Additionally, save log to file
    $logFile = 'logs/weather_notifications_' . date('Y-m-d') . '.log';
    
    // Append log entries to file
    file_put_contents($logFile, implode(PHP_EOL, $log) . PHP_EOL, FILE_APPEND);
}