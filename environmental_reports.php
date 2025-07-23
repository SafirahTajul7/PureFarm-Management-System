<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Handle form submissions for filtering
$filter_field = isset($_GET['field']) ? $_GET['field'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$filter_type = isset($_GET['data_type']) ? $_GET['data_type'] : 'soil';

// Fetch all fields for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, field_name FROM fields ORDER BY field_name ASC");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

// Define report types
$report_types = [
    'soil' => 'Soil Tests',
    'moisture' => 'Soil Moisture',
    'temperature' => 'Temperature',
    'humidity' => 'Humidity'
];

// Get environmental data based on filter type
try {
    $data = [];
    $chart_data = [];
    
    // Base query parts
    $select_soil = "
        SELECT st.id, st.field_id, f.field_name, st.test_date as date, 
               st.ph_level, st.moisture_percentage, st.temperature,
               st.nitrogen_level, st.phosphorus_level, st.potassium_level
        FROM soil_tests st
        JOIN fields f ON st.field_id = f.id
    ";
    
    $select_weather = "
        SELECT w.id, w.field_id, f.field_name, DATE(w.recorded_at) as date, 
               w.temperature, w.humidity, w.pressure, w.precipitation,
               w.wind_speed, w.wind_direction, w.conditions
        FROM weather_data w
        JOIN fields f ON w.field_id = f.id
    ";
    
    // Build where clause
    $where = " WHERE 1=1";
    $params = [];
    
    if (!empty($filter_field)) {
        $where .= " AND field_id = ?";
        $params[] = $filter_field;
    }
    
    if (!empty($filter_date_from)) {
        if ($filter_type == 'soil' || $filter_type == 'moisture') {
            $where .= " AND test_date >= ?";
        } else {
            $where .= " AND DATE(recorded_at) >= ?";
        }
        $params[] = $filter_date_from;
    }
    
    if (!empty($filter_date_to)) {
        if ($filter_type == 'soil' || $filter_type == 'moisture') {
            $where .= " AND test_date <= ?";
        } else {
            $where .= " AND DATE(recorded_at) <= ?";
        }
        $params[] = $filter_date_to;
    }
    
    // Get data based on filter type
    if ($filter_type == 'soil') {
        // Get soil test data
        $query = $select_soil . $where . " ORDER BY test_date DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get chart data for pH levels
        $chart_query = "
            SELECT 
                DATE(test_date) as date,
                AVG(ph_level) as avg_ph,
                AVG(moisture_percentage) as avg_moisture,
                field_id,
                f.field_name
            FROM 
                soil_tests
            JOIN
                fields f ON soil_tests.field_id = f.id
            " . $where . "
            GROUP BY DATE(test_date), field_id
            ORDER BY date ASC
        ";
        
        $chart_stmt = $pdo->prepare($chart_query);
        $chart_stmt->execute($params);
        $chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($filter_type == 'moisture') {
        // Get soil moisture data
        $query = "
            SELECT sm.id, sm.field_id, f.field_name, sm.reading_date as date, 
                   sm.moisture_percentage, sm.reading_depth
            FROM soil_moisture sm
            JOIN fields f ON sm.field_id = f.id
        " . $where . " ORDER BY reading_date DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get chart data for moisture levels
        $chart_query = "
            SELECT 
                DATE(reading_date) as date,
                AVG(moisture_percentage) as avg_moisture,
                field_id,
                f.field_name
            FROM 
                soil_moisture
            JOIN
                fields f ON soil_moisture.field_id = f.id
            " . $where . "
            GROUP BY DATE(reading_date), field_id
            ORDER BY date ASC
        ";
        
        $chart_stmt = $pdo->prepare($chart_query);
        $chart_stmt->execute($params);
        $chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } elseif ($filter_type == 'temperature' || $filter_type == 'humidity') {
        // Get weather data
        $query = $select_weather . $where . " ORDER BY recorded_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get chart data for temperature or humidity
        $avg_field = ($filter_type == 'temperature') ? 'AVG(temperature) as avg_value' : 'AVG(humidity) as avg_value';
        
        $chart_query = "
            SELECT 
                DATE(recorded_at) as date,
                $avg_field,
                field_id,
                f.field_name
            FROM 
                weather_data
            JOIN
                fields f ON weather_data.field_id = f.id
            " . $where . "
            GROUP BY DATE(recorded_at), field_id
            ORDER BY date ASC
        ";
        
        $chart_stmt = $pdo->prepare($chart_query);
        $chart_stmt->execute($params);
        $chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Prepare data for charts
    $chart_dates = [];
    $chart_fields = [];
    $chart_values = [];
    
    foreach ($chart_data as $row) {
        $date = date('M d', strtotime($row['date']));
        $field = $row['field_name'];
        
        if (!in_array($date, $chart_dates)) {
            $chart_dates[] = $date;
        }
        
        if (!in_array($field, $chart_fields)) {
            $chart_fields[] = $field;
            $chart_values[$field] = [];
        }
        
        if ($filter_type == 'soil') {
            $chart_values[$field][$date] = round($row['avg_ph'], 1);
        } elseif ($filter_type == 'moisture') {
            $chart_values[$field][$date] = round($row['avg_moisture'], 1);
        } else {
            $chart_values[$field][$date] = round($row['avg_value'], 1);
        }
    }
    
    // Create chart datasets
    $datasets = [];
    $colors = [
        '#36a2eb', '#ff6384', '#4bc0c0', '#ffcd56', '#9966ff',
        '#fd7e14', '#20c997', '#6c757d', '#6f42c1', '#e83e8c'
    ];
    
    foreach ($chart_fields as $index => $field) {
        $color = $colors[$index % count($colors)];
        $field_data = [];
        
        foreach ($chart_dates as $date) {
            $field_data[] = isset($chart_values[$field][$date]) ? $chart_values[$field][$date] : null;
        }
        
        $datasets[] = [
            'label' => $field,
            'data' => $field_data,
            'borderColor' => $color,
            'backgroundColor' => $color . '33',  // with opacity
            'borderWidth' => 2,
            'tension' => 0.3,
            'fill' => false
        ];
    }
    
    $dates_json = json_encode($chart_dates);
    $datasets_json = json_encode($datasets);
    
} catch(PDOException $e) {
    error_log("Error fetching environmental data: " . $e->getMessage());
    $data = [];
    $chart_dates = [];
    $datasets = [];
    $dates_json = json_encode([]);
    $datasets_json = json_encode([]);
}

// Calculate summary statistics
try {
    if ($filter_type == 'soil') {
        // Calculate average pH
        $stmt = $pdo->prepare("
            SELECT AVG(ph_level) as avg_ph
            FROM soil_tests
            WHERE test_date BETWEEN ? AND ?
        ");
        $stmt->execute([$filter_date_from, $filter_date_to]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $avg_ph = ($result['avg_ph'] !== null) ? number_format($result['avg_ph'], 1) : 'N/A';
        
        // Calculate average moisture
        $stmt = $pdo->prepare("
            SELECT AVG(moisture_percentage) as avg_moisture
            FROM soil_tests
            WHERE test_date BETWEEN ? AND ?
        ");
        $stmt->execute([$filter_date_from, $filter_date_to]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $avg_moisture = ($result['avg_moisture'] !== null) ? number_format($result['avg_moisture'], 1) . '%' : 'N/A';
        
        // Count fields with low pH
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT field_id) as low_ph_count
            FROM soil_tests
            WHERE ph_level < 6.0
            AND test_date BETWEEN ? AND ?
        ");
        $stmt->execute([$filter_date_from, $filter_date_to]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $low_ph_fields = $result['low_ph_count'];
        
        // Create summary stats array
        $summary_stats = [
            ['title' => 'Average pH', 'value' => $avg_ph, 'bg_class' => 'bg-primary'],
            ['title' => 'Average Moisture', 'value' => $avg_moisture, 'bg_class' => 'bg-success'],
            ['title' => 'Fields with Low pH', 'value' => $low_ph_fields, 'bg_class' => 'bg-warning']
        ];
        
    } elseif ($filter_type == 'moisture') {
        // Calculate average moisture
        $stmt = $pdo->prepare("
            SELECT AVG(moisture_percentage) as avg_moisture
            FROM soil_moisture
            WHERE reading_date BETWEEN ? AND ?
        ");
        $stmt->execute([$filter_date_from, $filter_date_to]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $avg_moisture = ($result['avg_moisture'] !== null) ? number_format($result['avg_moisture'], 1) . '%' : 'N/A';
        
        // Count fields with low moisture
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT field_id) as low_moisture_count
            FROM soil_moisture
            WHERE moisture_percentage < 30
            AND reading_date BETWEEN ? AND ?
        ");
        $stmt->execute([$filter_date_from, $filter_date_to]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $low_moisture_fields = $result['low_moisture_count'];
        
        // Calculate moisture variability
        $stmt = $pdo->prepare("
            SELECT 
                STDDEV(moisture_percentage) as moisture_stddev
            FROM soil_moisture
            WHERE reading_date BETWEEN ? AND ?
        ");
        $stmt->execute([$filter_date_from, $filter_date_to]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $moisture_variability = ($result['moisture_stddev'] !== null) ? number_format($result['moisture_stddev'], 1) : 'N/A';
        
        // Create summary stats array
        $summary_stats = [
            ['title' => 'Average Moisture', 'value' => $avg_moisture, 'bg_class' => 'bg-primary'],
            ['title' => 'Fields with Low Moisture', 'value' => $low_moisture_fields, 'bg_class' => 'bg-warning'],
            ['title' => 'Moisture Variability', 'value' => $moisture_variability, 'bg_class' => 'bg-info']
        ];
        
    } elseif ($filter_type == 'temperature') {
        // Calculate average temperature
        $stmt = $pdo->prepare("
            SELECT AVG(temperature) as avg_temp
            FROM weather_data
            WHERE DATE(recorded_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$filter_date_from, $filter_date_to]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $avg_temp = ($result['avg_temp'] !== null) ? number_format($result['avg_temp'], 1) . '°C' : 'N/A';
        
        // Calculate max temperature
        $stmt = $pdo->prepare("
            SELECT MAX(temperature) as max_temp
            FROM weather_data
            WHERE DATE(recorded_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$filter_date_from, $filter_date_to]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $max_temp = ($result['max_temp'] !== null) ? number_format($result['max_temp'], 1) . '°C' : 'N/A';
        
        // Calculate min temperature
        $stmt = $pdo->prepare("
            SELECT MIN(temperature) as min_temp
            FROM weather_data
            WHERE DATE(recorded_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$filter_date_from, $filter_date_to]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $min_temp = ($result['min_temp'] !== null) ? number_format($result['min_temp'], 1) . '°C' : 'N/A';
        
        // Create summary stats array
        $summary_stats = [
            ['title' => 'Average Temperature', 'value' => $avg_temp, 'bg_class' => 'bg-primary'],
            ['title' => 'Maximum Temperature', 'value' => $max_temp, 'bg_class' => 'bg-danger'],
            ['title' => 'Minimum Temperature', 'value' => $min_temp, 'bg_class' => 'bg-info']
        ];
        
    } elseif ($filter_type == 'humidity') {
        // Calculate average humidity
        $stmt = $pdo->prepare("
            SELECT AVG(humidity) as avg_humidity
            FROM weather_data
            WHERE DATE(recorded_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$filter_date_from, $filter_date_to]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $avg_humidity = ($result['avg_humidity'] !== null) ? number_format($result['avg_humidity'], 1) . '%' : 'N/A';
        
        // Calculate max humidity
        $stmt = $pdo->prepare("
            SELECT MAX(humidity) as max_humidity
            FROM weather_data
            WHERE DATE(recorded_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$filter_date_from, $filter_date_to]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $max_humidity = ($result['max_humidity'] !== null) ? number_format($result['max_humidity'], 1) . '%' : 'N/A';
        
        // Calculate min humidity
        $stmt = $pdo->prepare("
            SELECT MIN(humidity) as min_humidity
            FROM weather_data
            WHERE DATE(recorded_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$filter_date_from, $filter_date_to]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $min_humidity = ($result['min_humidity'] !== null) ? number_format($result['min_humidity'], 1) . '%' : 'N/A';
        
        // Create summary stats array
        $summary_stats = [
            ['title' => 'Average Humidity', 'value' => $avg_humidity, 'bg_class' => 'bg-primary'],
            ['title' => 'Maximum Humidity', 'value' => $max_humidity, 'bg_class' => 'bg-info'],
            ['title' => 'Minimum Humidity', 'value' => $min_humidity, 'bg_class' => 'bg-warning']
        ];
    } else {
        $summary_stats = [];
    }
    
} catch(PDOException $e) {
    error_log("Error calculating statistics: " . $e->getMessage());
    $summary_stats = [];
}

// Set page title based on report type
$pageTitle = 'Environmental Reports - ' . $report_types[$filter_type];
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-bar"></i> Environmental Reports</h2>
        <div class="action-buttons">
            <a href="environmental_monitoring.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Monitoring
            </a>
            <button class="btn btn-success" onclick="printReport()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button class="btn btn-info" onclick="exportToCSV()">
                <i class="fas fa-file-csv"></i> Export to CSV
            </button>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> Report Filters</h5>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="data_type" class="form-label">Data Type</label>
                    <select class="form-select" id="data_type" name="data_type">
                        <?php foreach ($report_types as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($filter_type == $key) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="field" class="form-label">Field</label>
                    <select class="form-select" id="field" name="field">
                        <option value="">All Fields</option>
                        <?php foreach ($fields as $field): ?>
                        <option value="<?php echo $field['id']; ?>" <?php echo ($filter_field == $field['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($field['field_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $filter_date_from; ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $filter_date_to; ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                    <a href="environmental_reports.php" class="btn btn-secondary">Reset Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Stats Cards -->
    <div class="row mb-4">
        <?php foreach ($summary_stats as $stat): ?>
        <div class="col-md-4 mb-3">
            <div class="card <?php echo $stat['bg_class']; ?> text-white">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($stat['title']); ?></h5>
                    <p class="card-text display-6"><?php echo htmlspecialchars($stat['value']); ?></p>
                    <p class="card-text small">Period: <?php echo date('M d, Y', strtotime($filter_date_from)); ?> - <?php echo date('M d, Y', strtotime($filter_date_to)); ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Trend Chart -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>
                <i class="fas fa-chart-line"></i> 
                <?php 
                    if ($filter_type == 'soil') echo 'Soil pH Trends';
                    elseif ($filter_type == 'moisture') echo 'Soil Moisture Trends';
                    elseif ($filter_type == 'temperature') echo 'Temperature Trends';
                    elseif ($filter_type == 'humidity') echo 'Humidity Trends';
                ?>
            </h5>
        </div>
        <div class="card-body">
            <canvas id="trend-chart" style="height: 400px; width: 100%;"></canvas>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-table"></i> Environmental Data</h5>
        </div>
        <div class="card-body">
            <?php if (empty($data)): ?>
            <div class="alert alert-info">
                No data available for the selected criteria. Try adjusting your filters.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Date</th>
                            <?php if ($filter_type == 'soil'): ?>
                                <th>pH Level</th>
                                <th>Moisture (%)</th>
                                <th>Temperature (°C)</th>
                                <th>Nitrogen</th>
                                <th>Phosphorus</th>
                                <th>Potassium</th>
                            <?php elseif ($filter_type == 'moisture'): ?>
                                <th>Moisture (%)</th>
                                <th>Reading Depth</th>
                            <?php elseif ($filter_type == 'temperature' || $filter_type == 'humidity'): ?>
                                <th>Temperature (°C)</th>
                                <th>Humidity (%)</th>
                                <th>Conditions</th>
                                <th>Precipitation (mm)</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['field_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                            
                            <?php if ($filter_type == 'soil'): ?>
                                <td>
                                    <?php 
                                        $ph = $row['ph_level'];
                                        $ph_class = '';
                                        if ($ph < 6.0) $ph_class = 'text-danger';
                                        elseif ($ph > 7.5) $ph_class = 'text-warning';
                                        elseif ($ph >= 6.0 && $ph <= 7.0) $ph_class = 'text-success';
                                        echo '<span class="' . $ph_class . '">' . $ph . '</span>';
                                    ?>
                                </td>
                                <td><?php echo isset($row['moisture_percentage']) ? $row['moisture_percentage'] . '%' : 'N/A'; ?></td>
                                <td><?php echo isset($row['temperature']) ? $row['temperature'] . '°C' : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($row['nitrogen_level']); ?></td>
                                <td><?php echo htmlspecialchars($row['phosphorus_level']); ?></td>
                                <td><?php echo htmlspecialchars($row['potassium_level']); ?></td>
                            <?php elseif ($filter_type == 'moisture'): ?>
                                <td>
                                    <?php 
                                        $moisture = $row['moisture_percentage'];
                                        $moisture_class = '';
                                        if ($moisture < 30) $moisture_class = 'text-danger';
                                        elseif ($moisture > 70) $moisture_class = 'text-primary';
                                        echo '<span class="' . $moisture_class . '">' . $moisture . '%</span>';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['reading_depth']); ?></td>
                            <?php elseif ($filter_type == 'temperature' || $filter_type == 'humidity'): ?>
                                <td><?php echo isset($row['temperature']) ? $row['temperature'] . '°C' : 'N/A'; ?></td>
                                <td><?php echo isset($row['humidity']) ? $row['humidity'] . '%' : 'N/A'; ?></td>
                                <td><?php echo isset($row['conditions']) ? htmlspecialchars($row['conditions']) : 'N/A'; ?></td>
                                <td><?php echo isset($row['precipitation']) ? $row['precipitation'] . ' mm' : 'N/A'; ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Analysis and Recommendations -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-lightbulb"></i> Analysis & Recommendations</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($data)): ?>
                <?php if ($filter_type == 'soil'): ?>
                    <?php if ($low_ph_fields > 0): ?>
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Low pH Detected</h6>
                        <p>Some fields have pH levels below the optimal range (below 6.0). Consider applying lime to increase soil pH for better nutrient availability.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php 
                    // Check if average pH is a number before comparing
                    $avg_ph_value = is_numeric(str_replace(',', '.', $avg_ph)) ? floatval(str_replace(',', '.', $avg_ph)) : null;
                    if ($avg_ph_value !== null && $avg_ph_value > 7.5): 
                    ?>
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>High pH Detected</h6>
                        <p>The average soil pH is above the optimal range (above 7.5). Consider applying sulfur or acidifying amendments to lower soil pH for better nutrient availability.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Check if fields have optimal pH
                    if ($low_ph_fields == 0 && ($avg_ph_value === null || ($avg_ph_value >= 6.0 && $avg_ph_value <= 7.5))): 
                    ?>
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle me-2"></i>Optimal Soil pH</h6>
                        <p>Your soil pH levels are within the optimal range. Continue your current soil management practices.</p>
                    </div>
                    <?php endif; ?>
                    
                <?php elseif ($filter_type == 'moisture'): ?>
                    <?php if ($low_moisture_fields > 0): ?>
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-tint-slash me-2"></i>Low Soil Moisture</h6>
                        <p>Some fields have moisture levels below optimal levels (below 30%). Consider scheduling irrigation or checking irrigation systems.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php 
                    // Check if average moisture is a number before comparing
                    $avg_moisture_value = is_numeric(str_replace(['%', ','], ['', '.'], $avg_moisture)) ? floatval(str_replace(['%', ','], ['', '.'], $avg_moisture)) : null;
                    if ($avg_moisture_value !== null && $avg_moisture_value > 70): 
                    ?>
                    <div class="alert alert-info">
                        <h6><i class="fas fa-tint me-2"></i>High Soil Moisture</h6>
                        <p>The average soil moisture is above optimal levels (above 70%). Consider reducing irrigation to prevent waterlogging and potential root diseases.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Check if fields have optimal moisture
                    if ($low_moisture_fields == 0 && ($avg_moisture_value === null || ($avg_moisture_value >= 30 && $avg_moisture_value <= 70))): 
                    ?>
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle me-2"></i>Optimal Soil Moisture</h6>
                        <p>Your soil moisture levels are within the optimal range. Continue your current irrigation practices.</p>
                    </div>
                    <?php endif; ?>
                    
                <?php elseif ($filter_type == 'temperature'): ?>
                    <?php 
                    // Check temperature extremes
                    $avg_temp_value = is_numeric(str_replace(['°C', ','], ['', '.'], $avg_temp)) ? floatval(str_replace(['°C', ','], ['', '.'], $avg_temp)) : null;
                    $max_temp_value = is_numeric(str_replace(['°C', ','], ['', '.'], $max_temp)) ? floatval(str_replace(['°C', ','], ['', '.'], $max_temp)) : null;
                    
                    if ($max_temp_value !== null && $max_temp_value > 32): 
                    ?>
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-thermometer-full me-2"></i>High Temperature Alert</h6>
                        <p>Maximum temperatures have exceeded 32°C during the selected period. Consider providing shade or additional irrigation to protect sensitive crops from heat stress.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    $min_temp_value = is_numeric(str_replace(['°C', ','], ['', '.'], $min_temp)) ? floatval(str_replace(['°C', ','], ['', '.'], $min_temp)) : null;
                    if ($min_temp_value !== null && $min_temp_value < 10): 
                    ?>
                    <div class="alert alert-info">
                        <h6><i class="fas fa-thermometer-empty me-2"></i>Low Temperature Alert</h6>
                        <p>Minimum temperatures have dropped below 10°C during the selected period. Be aware of potential frost or cold damage to sensitive crops.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Check if temperature is optimal
                    if (($max_temp_value === null || $max_temp_value <= 32) && 
                        ($min_temp_value === null || $min_temp_value >= 10)): 
                    ?>
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle me-2"></i>Optimal Temperature Range</h6>
                        <p>Temperature readings are within optimal growing ranges for most crops.</p>
                    </div>
                    <?php endif; ?>
                    
                <?php elseif ($filter_type == 'humidity'): ?>
                    <?php 
                    // Check humidity extremes
                    $avg_humidity_value = is_numeric(str_replace(['%', ','], ['', '.'], $avg_humidity)) ? floatval(str_replace(['%', ','], ['', '.'], $avg_humidity)) : null;
                    $max_humidity_value = is_numeric(str_replace(['%', ','], ['', '.'], $max_humidity)) ? floatval(str_replace(['%', ','], ['', '.'], $max_humidity)) : null;
                    
                    if ($avg_humidity_value !== null && $avg_humidity_value > 80): 
                    ?>
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-tint me-2"></i>High Humidity Alert</h6>
                        <p>Average humidity levels are above 80%. Monitor crops for fungal diseases and consider improving air circulation or using fungicides if necessary.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    $min_humidity_value = is_numeric(str_replace(['%', ','], ['', '.'], $min_humidity)) ? floatval(str_replace(['%', ','], ['', '.'], $min_humidity)) : null;
                    if ($min_humidity_value !== null && $min_humidity_value < 30): 
                    ?>
                    <div class="alert alert-info">
                        <h6><i class="fas fa-tint-slash me-2"></i>Low Humidity Alert</h6>
                        <p>Minimum humidity levels have dropped below 30%. Monitor crops for water stress and consider adjusting irrigation schedules during these periods.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Check if humidity is optimal
                    if (($avg_humidity_value === null || $avg_humidity_value <= 80) && 
                        ($min_humidity_value === null || $min_humidity_value >= 30)): 
                    ?>
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle me-2"></i>Optimal Humidity Range</h6>
                        <p>Humidity levels are within acceptable ranges for most crops.</p>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- General Recommendations -->
                <div class="mt-4">
                    <h6><i class="fas fa-clipboard-list me-2"></i>General Recommendations</h6>
                    <ul class="list-group">
                        <li class="list-group-item">Continue regular environmental monitoring to track trends over time.</li>
                        <li class="list-group-item">Consider installing automated sensors for more frequent data collection.</li>
                        <li class="list-group-item">Compare environmental data with crop yield to optimize growing conditions.</li>
                        <li class="list-group-item">Document any interventions (irrigation, soil amendments) to assess their effectiveness.</li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No data available to generate recommendations. Try adjusting your filter criteria.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .card-text.display-6 {
        font-size: 2.5rem;
    }
    @media print {
        .action-buttons, .filter-section, footer, .navbar {
            display: none !important;
        }
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
        }
        .card-header {
            background-color: #f8f9fa !important;
            color: #333 !important;
        }
        .bg-primary, .bg-success, .bg-info, .bg-warning, .bg-danger {
            background-color: #f8f9fa !important;
            color: #333 !important;
        }
        .card-text.display-6 {
            color: #333 !important;
        }
        body {
            font-size: 12px !important;
        }
        h2, h5, h6 {
            color: #333 !important;
        }
    }
</style>

<!-- Add Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get chart data from PHP
    const dates = <?php echo $dates_json; ?>;
    const datasets = <?php echo $datasets_json; ?>;
    
    // Prepare chart
    const canvas = document.getElementById('trend-chart');
    if (!canvas) {
        console.error("Canvas element not found!");
        return;
    }
    
    // Create the chart with explicit height and width
    canvas.height = 400;
    canvas.style.height = '400px';
    canvas.style.width = '100%';
    
    const ctx = canvas.getContext('2d');
    
    // Chart title and Y-axis label based on data type
    let chartTitle = '';
    let yAxisLabel = '';
    
    switch ('<?php echo $filter_type; ?>') {
        case 'soil':
            chartTitle = 'Soil pH Trends';
            yAxisLabel = 'pH Level';
            break;
        case 'moisture':
            chartTitle = 'Soil Moisture Trends';
            yAxisLabel = 'Moisture (%)';
            break;
        case 'temperature':
            chartTitle = 'Temperature Trends';
            yAxisLabel = 'Temperature (°C)';
            break;
        case 'humidity':
            chartTitle = 'Humidity Trends';
            yAxisLabel = 'Humidity (%)';
            break;
    }
    
    // Create chart
    try {
        if (dates.length > 0 && datasets.length > 0) {
            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: chartTitle,
                            font: {
                                size: 16
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            title: {
                                display: true,
                                text: yAxisLabel
                            },
                            ticks: {
                                callback: function(value) {
                                    if ('<?php echo $filter_type; ?>' === 'moisture' || '<?php echo $filter_type; ?>' === 'humidity') {
                                        return value + '%';
                                    } else if ('<?php echo $filter_type; ?>' === 'temperature') {
                                        return value + '°C';
                                    }
                                    return value;
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });
        } else {
            // Display message if no data
            canvas.height = 200;
            ctx.font = '16px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('No data available for the selected period', canvas.width / 2, 100);
        }
    } catch (error) {
        console.error("Error creating chart:", error);
    }
});

// Print function
function printReport() {
    window.print();
}

// Export to CSV
function exportToCSV() {
    // Get table
    const table = document.querySelector('.table');
    if (!table) {
        alert('No data available to export');
        return;
    }
    
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Add header row
    const headers = [];
    for (let i = 0; i < table.rows[0].cells.length; i++) {
        headers.push(table.rows[0].cells[i].textContent.trim());
    }
    csvContent += headers.join(",") + "\r\n";
    
    // Add data rows
    for (let i = 1; i < table.rows.length; i++) {
        const row = table.rows[i];
        const rowData = [];
        
        for (let j = 0; j < row.cells.length; j++) {
            // Clean the cell data (remove commas that would break CSV format)
            let cellData = row.cells[j].textContent.trim().replace(/,/g, ' ');
            rowData.push(cellData);
        }
        
        csvContent += rowData.join(",") + "\r\n";
    }
    
    // Create download link
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    
    // Set filename based on report type
    let reportType = '';
    switch ('<?php echo $filter_type; ?>') {
        case 'soil':
            reportType = 'SoilTests';
            break;
        case 'moisture':
            reportType = 'SoilMoisture';
            break;
        case 'temperature':
            reportType = 'Temperature';
            break;
        case 'humidity':
            reportType = 'Humidity';
            break;
    }
    
    const dateRange = '<?php echo date('Ymd', strtotime($filter_date_from)) . '_to_' . date('Ymd', strtotime($filter_date_to)); ?>';
    link.setAttribute("download", `Environmental_${reportType}_Report_${dateRange}.csv`);
    
    // Trigger download
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include 'includes/footer.php'; ?>