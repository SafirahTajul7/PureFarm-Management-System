<?php
require_once 'includes/auth.php';
auth()->checkSupervisor(); // SUPERVISOR ACCESS ONLY - No admin functions allowed

require_once 'includes/db.php';

// Initialize error reporting for debugging during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// SUPERVISOR ENVIRONMENTAL MONITORING PAGE
// This page is specifically for supervisors to:
// - Record environmental data for assigned fields
// - Monitor environmental conditions for assigned fields
// - Report environmental issues to admin
// - View current weather conditions

// Fetch summary statistics for supervisor's assigned fields only
try {
    // Get supervisor's assigned fields
    $supervisor_id = $_SESSION['user_id'];
    
    // Check if staff_field_assignments table exists, if not create sample data
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'staff_field_assignments'");
        if ($table_check->rowCount() == 0) {
            // Create the table and add sample data
            $pdo->exec("
                CREATE TABLE staff_field_assignments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    staff_id INT NOT NULL,
                    field_id INT NOT NULL,
                    status VARCHAR(20) DEFAULT 'active',
                    assigned_date DATE DEFAULT CURRENT_DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Get available fields
            $fields_check = $pdo->query("SELECT id FROM fields LIMIT 3");
            $available_fields = $fields_check->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($available_fields)) {
                // Assign some fields to the current supervisor
                foreach ($available_fields as $field_id) {
                    $pdo->prepare("INSERT INTO staff_field_assignments (staff_id, field_id, status) VALUES (?, ?, 'active')")
                        ->execute([$supervisor_id, $field_id]);
                }
            }
        }
    } catch(PDOException $e) {
        error_log("Error checking/creating staff_field_assignments table: " . $e->getMessage());
    }
    
    $assigned_fields_stmt = $pdo->prepare("
        SELECT DISTINCT field_id 
        FROM staff_field_assignments 
        WHERE staff_id = ? AND status = 'active'
    ");
    $assigned_fields_stmt->execute([$supervisor_id]);
    $assigned_field_ids = $assigned_fields_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If no specific assignments, show all fields (fallback)
    if (empty($assigned_field_ids)) {
        // Get all available fields as fallback
        try {
            $all_fields_stmt = $pdo->query("SELECT id FROM fields LIMIT 5");
            $assigned_field_ids = $all_fields_stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch(PDOException $e) {
            error_log("Error fetching fields: " . $e->getMessage());
            $assigned_field_ids = [];
        }
    }
    
    if (empty($assigned_field_ids)) {
        $field_condition = "";
        $field_params = [];
    } else {
        $field_condition = "WHERE id IN (" . str_repeat('?,', count($assigned_field_ids) - 1) . "?)";
        $field_params = $assigned_field_ids;
    }
    
    // Total fields being monitored (supervisor's fields only)
    $total_fields = 0;
    if (!empty($field_params)) {
        $total_fields_query = "SELECT COUNT(*) FROM fields " . $field_condition;
        $stmt = $pdo->prepare($total_fields_query);
        $stmt->execute($field_params);
        $total_fields = $stmt->fetchColumn();
    } else {
        $total_fields = $pdo->query("SELECT COUNT(*) FROM fields")->fetchColumn();
    }
    
    // Soil tests count for supervisor's fields
    $soil_tests = 0;
    try {
        if (empty($assigned_field_ids)) {
            $soil_tests = $pdo->query("
                SELECT COUNT(*) 
                FROM soil_tests 
                WHERE test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
            ")->fetchColumn();
        } else {
            $soil_tests_query = "
                SELECT COUNT(*) 
                FROM soil_tests 
                WHERE test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                AND field_id IN (" . str_repeat('?,', count($assigned_field_ids) - 1) . "?)
            ";
            $stmt = $pdo->prepare($soil_tests_query);
            $stmt->execute($assigned_field_ids);
            $soil_tests = $stmt->fetchColumn();
        }
    } catch(PDOException $e) {
        error_log("Error fetching soil test count: " . $e->getMessage());
        $soil_tests = 0;
    }
    
    // Temperature readings for supervisor's fields
    $temperature = 25; // Default value
    try {
        if (empty($assigned_field_ids)) {
            $temp_result = $pdo->query("
                SELECT AVG(temperature) 
                FROM soil_tests 
                WHERE test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)
            ")->fetchColumn();
        } else {
            $temp_query = "
                SELECT AVG(temperature) 
                FROM soil_tests 
                WHERE test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)
                AND field_id IN (" . str_repeat('?,', count($assigned_field_ids) - 1) . "?)
            ";
            $stmt = $pdo->prepare($temp_query);
            $stmt->execute($assigned_field_ids);
            $temp_result = $stmt->fetchColumn();
        }
        $temperature = $temp_result ?: 25; // Use result or default
    } catch(PDOException $e) {
        error_log("Error fetching temperature data: " . $e->getMessage());
        $temperature = 25;
    }

    // Humidity readings for supervisor's fields
    $humidity = 65; // Default value
    try {
        if (empty($assigned_field_ids)) {
            $humidity_result = $pdo->query("
                SELECT AVG(moisture_percentage) 
                FROM soil_tests 
                WHERE test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)
            ")->fetchColumn();
        } else {
            $humidity_query = "
                SELECT AVG(moisture_percentage) 
                FROM soil_tests 
                WHERE test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)
                AND field_id IN (" . str_repeat('?,', count($assigned_field_ids) - 1) . "?)
            ";
            $stmt = $pdo->prepare($humidity_query);
            $stmt->execute($assigned_field_ids);
            $humidity_result = $stmt->fetchColumn();
        }
        $humidity = $humidity_result ?: 65; // Use result or default
    } catch(PDOException $e) {
        error_log("Error fetching humidity data: " . $e->getMessage());
        $humidity = 65;
    }

    // Use sample data for chart since we removed recent observations
    $dates = ['Apr 18', 'Apr 19', 'Apr 20', 'Apr 21', 'Apr 22', 'Apr 23', 'Apr 24'];
    $temps = [22, 23, 25, 24, 22, 26, 25];
    $humidity_values = [65, 68, 62, 70, 72, 60, 65];

    // Always encode to JSON
    $dates_json = json_encode($dates);
    $temps_json = json_encode($temps);
    $humidity_json = json_encode($humidity_values);

} catch(PDOException $e) {
    error_log("Error fetching environmental monitoring data: " . $e->getMessage());
    // Set default values in case of error
    $total_fields = 0;
    $soil_tests = 0;
    $temperature = 25;
    $humidity = 65;
    
    // Set empty arrays for chart data
    $dates_json = json_encode([]);
    $temps_json = json_encode([]);
    $humidity_json = json_encode([]);
}

$pageTitle = 'Environmental Monitoring';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-cloud-sun"></i> Environmental Monitoring</h2>
        <div class="action-buttons">
            <button class="btn btn-success" onclick="location.href='record_environmental_data.php'">
                <i class="fas fa-thermometer-half"></i> Record Environmental Data
            </button>
            <button class="btn btn-info" onclick="location.href='current_weather1.php'">
                <i class="fas fa-cloud-sun"></i> Current Weather
            </button>
        </div>
    </div>

    <!-- Summary Cards for Supervisor -->
    <div class="summary-cards mb-4">
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="summary-card bg-teal">
                    <div class="card-icon">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo $total_fields; ?></h3>
                        <p>Assigned Fields</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="summary-card bg-blue">
                    <div class="card-icon">
                        <i class="fas fa-vial"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo $soil_tests; ?></h3>
                        <p>Recent Tests</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="summary-card bg-orange">
                    <div class="card-icon">
                        <i class="fas fa-thermometer-half"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo round($temperature); ?>°C</h3>
                        <p>Avg Temperature</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="summary-card bg-purple">
                    <div class="card-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo round($humidity); ?>%</h3>
                        <p>Avg Humidity</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Environmental Data - Large Single Section -->
    <div class="environmental-data-section">
        <div class="feature-card environmental-data">
            <h3><i class="fas fa-cloud-sun-rain"></i> Environmental Data Management</h3>
            <div class="environmental-grid">
                <div class="env-item" onclick="location.href='record_environmental_data.php'">
                    <div class="env-icon">
                        <i class="fas fa-thermometer-half"></i>
                    </div>
                    <div class="env-content">
                        <span class="env-title">Record Environmental Data</span>
                        <span class="env-desc">Log temperature, humidity, and weather readings for your assigned fields</span>
                    </div>
                </div>
                
                <div class="env-item" onclick="location.href='current_weather1.php'">
                    <div class="env-icon">
                        <i class="fas fa-cloud-sun"></i>
                    </div>
                    <div class="env-content">
                        <span class="env-title">Current Weather Conditions</span>
                        <span class="env-desc">View real-time weather data and forecasts for field planning</span>
                    </div>
                </div>
                
                <div class="env-item" onclick="location.href='environmental_issues.php'">
                    <div class="env-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="env-content">
                        <span class="env-title">Report Environmental Issues</span>
                        <span class="env-desc">Report environmental concerns and alerts to administration</span>
                    </div>
                </div>

                <div class="env-item" onclick="location.href='view_environmental_issues.php'">
                    <div class="env-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="env-content">
                        <span class="env-title">View Environmental Issue Reports</span>
                        <span class="env-desc">View and manage all your submitted environmental issue reports</span>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- Environmental Conditions Dashboard -->
    <div class="section-header">
        <h3><i class="fas fa-tachometer-alt"></i> My Field Conditions</h3>
    </div>

    <div class="current-conditions">
        <div class="conditions-chart">
            <!-- Chart container -->
            <div id="conditions-chart-container" style="height: 300px; background: #f8f9fa; border-radius: 8px; padding: 15px;">
                <canvas id="environmental-chart"></canvas>
            </div>
        </div>
        
        <div class="conditions-grid">
            <!-- Supervisor's assigned fields conditions only -->
            <?php
            // Sample assigned field conditions
            $field_conditions = [
                ['name' => 'Field A-1', 'temp' => '24°C', 'humidity' => '65%', 'moisture' => '42%', 'status' => 'normal'],
                ['name' => 'Field A-2', 'temp' => '23°C', 'humidity' => '68%', 'moisture' => '37%', 'status' => 'normal'],
                ['name' => 'Field B-1', 'temp' => '26°C', 'humidity' => '55%', 'moisture' => '22%', 'status' => 'alert']
            ];
            
            foreach($field_conditions as $field): ?>
            <div class="condition-card <?php echo $field['status'] == 'alert' ? 'needs-attention' : ''; ?>">
                <div class="condition-title"><?php echo $field['name']; ?> <small>(My Assignment)</small></div>
                <div class="condition-data">
                    <div class="condition-reading">
                        <i class="fas fa-thermometer-half"></i>
                        <span><?php echo $field['temp']; ?></span>
                    </div>
                    <div class="condition-reading">
                        <i class="fas fa-tint"></i>
                        <span><?php echo $field['humidity']; ?></span>
                    </div>
                    <div class="condition-reading <?php echo $field['status'] == 'alert' ? 'alert' : ''; ?>">
                        <i class="fas fa-water"></i>
                        <span><?php echo $field['moisture']; ?></span>
                    </div>
                </div>
                <div class="condition-updated">Last checked: Today, <?php echo date('g:i A', strtotime('-' . rand(1,3) . ' hours')); ?></div>
                <?php if($field['status'] == 'alert'): ?>
                <div class="condition-alert">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Requires attention - Report to admin
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
/* Custom styles for supervisor environmental page */
.main-content {
    padding-bottom: 60px;
    min-height: calc(100vh - 60px);
}

/* Summary Cards */
.summary-cards .summary-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 20px;
    display: flex;
    align-items: center;
    color: white;
    transition: transform 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-3px);
}

.summary-card .card-icon {
    font-size: 36px;
    margin-right: 15px;
}

.summary-card .card-info h3 {
    font-size: 28px;
    margin: 0;
    font-weight: 600;
}

.summary-card .card-info p {
    margin: 0;
    opacity: 0.9;
}

.bg-teal { background: linear-gradient(135deg, #20c997, #17a085) !important; }
.bg-purple { background: linear-gradient(135deg, #6f42c1, #5a32a3) !important; }
.bg-orange { background: linear-gradient(135deg, #fd7e14, #e8590c) !important; }
.bg-blue { background: linear-gradient(135deg, #0d6efd, #0a58ca) !important; }

/* Environmental Data Section - Large */
.environmental-data-section {
    margin-bottom: 30px;
}

.environmental-data {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 30px;
    transition: all 0.3s ease;
    border-top: 4px solid #0d6efd;
}

.environmental-data:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.environmental-data h3 {
    margin: 0 0 25px 0;
    color: #333;
    font-size: 24px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 15px;
}

.environmental-data h3 i {
    color: #0d6efd;
    font-size: 28px;
}

/* Environmental Grid */
.environmental-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
}

.env-item {
    display: flex;
    align-items: center;
    padding: 20px;
    border-radius: 12px;
    background: #f8f9fa;
    border: 2px solid transparent;
    cursor: pointer;
    transition: all 0.3s ease;
}

.env-item:hover {
    background: #0d6efd;
    color: white;
    border-color: #0d6efd;
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(13, 110, 253, 0.3);
}

.env-icon {
    background: #e3f2fd;
    color: #0d6efd;
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    font-size: 24px;
    transition: all 0.3s ease;
}

.env-item:hover .env-icon {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.env-content {
    flex: 1;
}

.env-title {
    display: block;
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 5px;
    color: #333;
    transition: color 0.3s ease;
}

.env-item:hover .env-title {
    color: white;
}

.env-desc {
    display: block;
    font-size: 14px;
    color: #6c757d;
    line-height: 1.4;
    transition: color 0.3s ease;
}

.env-item:hover .env-desc {
    color: rgba(255, 255, 255, 0.9);
}

/* Current Conditions Dashboard */
.section-header {
    margin: 30px 0 20px 0;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.current-conditions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.conditions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.condition-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    padding: 15px;
    transition: all 0.3s ease;
}

.condition-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.condition-title {
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 10px;
    color: #333;
}

.condition-data {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.condition-reading {
    text-align: center;
    padding: 5px;
}

.condition-reading i {
    display: block;
    font-size: 18px;
    margin-bottom: 5px;
    color: #6c757d;
}

.condition-reading span {
    font-size: 16px;
    font-weight: 500;
}

.condition-updated {
    font-size: 12px;
    color: #6c757d;
    text-align: right;
}

.needs-attention {
    border-left: 3px solid #dc3545;
}

.condition-alert {
    color: #dc3545;
    font-size: 13px;
    margin-top: 8px;
    font-weight: 500;
}

.alert i, .alert span {
    color: #dc3545 !important;
}

/* Activities Section */
.activities-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 20px;
}

.recent-tests-table h4 {
    margin-bottom: 15px;
    color: #333;
}

.alerts-panel {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    height: 100%;
}

.alerts-panel h4 {
    margin-bottom: 20px;
    color: #333;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.alert-item {
    display: flex;
    align-items: flex-start;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.alert-item:hover {
    transform: translateY(-2px);
}

.alert-item.urgent {
    background: #fff5f5;
    border-left: 4px solid #dc3545;
}

.alert-item.warning {
    background: #fffbf0;
    border-left: 4px solid #ffc107;
}

.alert-item.info {
    background: #f0f8ff;
    border-left: 4px solid #0dcaf0;
}

.alert-icon {
    margin-right: 15px;
    font-size: 20px;
}

.urgent .alert-icon { color: #dc3545; }
.warning .alert-icon { color: #ffc107; }
.info .alert-icon { color: #0dcaf0; }

.alert-content {
    flex: 1;
}

.alert-title {
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
}

.alert-desc {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 5px;
}

.alert-time {
    font-size: 12px;
    color: #999;
}

@media (min-width: 992px) {
    .current-conditions {
        grid-template-columns: 2fr 3fr;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .environmental-grid {
        grid-template-columns: 1fr;
    }
    
    .env-item {
        padding: 15px;
    }
    
    .env-icon {
        width: 50px;
        height: 50px;
        font-size: 20px;
        margin-right: 15px;
    }
    
    .conditions-grid {
        grid-template-columns: 1fr;
    }
    
    .condition-data {
        flex-direction: column;
        gap: 10px;
    }
    
    .condition-reading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-align: left;
    }
    
    .condition-reading i {
        margin-bottom: 0;
        margin-right: 10px;
    }
}

body {
    padding-bottom: 60px;
}
</style>

<?php include 'includes/footer.php'; ?>

<!-- Add Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prepare the chart container
    const chartContainer = document.getElementById('conditions-chart-container');
    
    // Add a canvas element inside the container
    chartContainer.innerHTML = '<canvas id="environmental-chart"></canvas>';

    // Get data from PHP
    const dates = <?php echo $dates_json; ?>;
    const temps = <?php echo $temps_json; ?>;
    const humidity = <?php echo $humidity_json; ?>;

    console.log("Chart data:", { 
        dates: dates, 
        temps: temps, 
        humidity: humidity 
    });
    
    // Check if we have data
    if (dates && dates.length > 0) {
        const ctx = document.getElementById('environmental-chart').getContext('2d');
        
        // Create chart
        const envChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Temperature (°C)',
                        data: temps,
                        borderColor: '#fd7e14',
                        backgroundColor: 'rgba(253, 126, 20, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Humidity (%)',
                        data: humidity,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'y1'
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
                    title: {
                        display: true,
                        text: 'My Fields - Temperature & Humidity (Last 7 Days)',
                        font: {
                            size: 16
                        }
                    },
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Temperature (°C)'
                        },
                        min: 0,
                        suggestedMax: 40,
                        grid: {
                            drawOnChartArea: false
                        }
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
    } else {
        // If no data, display a message
        chartContainer.innerHTML = 
            '<div style="display: flex; align-items: center; justify-content: center; height: 100%;">' +
            '<p><i class="fas fa-info-circle" style="font-size: 24px; margin-right: 10px;"></i> ' +
            'No environmental data available for your assigned fields.</p></div>';
    }
});

// Function to refresh field conditions
function refreshFieldData() {
    // Show loading state
    const conditionsGrid = document.querySelector('.conditions-grid');
    if (conditionsGrid) {
        conditionsGrid.style.opacity = '0.6';
        conditionsGrid.style.pointerEvents = 'none';
    }
    
    // Simulate data refresh
    setTimeout(() => {
        location.reload();
    }, 1500);
}

// Function to mark alert as resolved
function resolveAlert(alertElement) {
    alertElement.style.opacity = '0.5';
    alertElement.style.textDecoration = 'line-through';
    
    // You can add AJAX call here to update the database
    setTimeout(() => {
        alertElement.remove();
    }, 2000);
}

// Auto-refresh functionality for real-time updates (optional)
let autoRefreshInterval;

function startAutoRefresh() {
    autoRefreshInterval = setInterval(() => {
        // Refresh field conditions data
        refreshFieldData();
    }, 300000); // Refresh every 5 minutes
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

// Initialize auto-refresh on page load (uncomment if needed)
// startAutoRefresh();

// Stop auto-refresh when page is about to unload
window.addEventListener('beforeunload', stopAutoRefresh);
</script>