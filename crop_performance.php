<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables with default values to prevent undefined variable warnings
$performance_data = [];
$available_years = [];
$crop_types = [];
$fields = [];
$historical_data = [];
$total_yield = 0;
$total_area = 0;
$quality_sum = 0;
$count = 0;
$average_yield_per_acre = 0;
$average_quality = 0;

// Check if harvests table exists, if not create it
try {
    $check_table = $pdo->query("SHOW TABLES LIKE 'harvests'");
    
    if ($check_table->rowCount() == 0) {
        // Table doesn't exist, create it
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS `harvests` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `crop_id` int(11) NOT NULL,
                `actual_harvest_date` date NOT NULL,
                `yield_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
                `quality_rating` decimal(3,1) NOT NULL DEFAULT 0.0,
                `notes` text,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `crop_id` (`crop_id`),
                CONSTRAINT `harvests_ibfk_1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $pdo->exec($create_table_sql);
        $_SESSION['success_message'] = "Harvests table has been created. Please add harvest records to view performance data.";
    }
} catch(PDOException $e) {
    error_log("Error checking/creating harvests table: " . $e->getMessage());
    $_SESSION['error_message'] = "Database setup error: " . $e->getMessage();
}

// Process filter parameters
$year_filter = isset($_GET['year']) ? $_GET['year'] : date('Y');
$crop_type_filter = isset($_GET['crop_type']) ? $_GET['crop_type'] : '';
$field_filter = isset($_GET['field_id']) ? $_GET['field_id'] : '';

// Fetch crop performance data with filters
try {
    // Get crop types for filter - this should work even without harvests
    $crop_types_stmt = $pdo->query("SELECT DISTINCT crop_name FROM crops ORDER BY crop_name");
    if ($crop_types_stmt) {
        $crop_types = $crop_types_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Get fields for filter - this should work even without harvests
    $fields_stmt = $pdo->query("SELECT id, field_name FROM fields ORDER BY field_name");
    if ($fields_stmt) {
        $fields = $fields_stmt->fetchAll();
    }
    
    // Check if harvests table exists and has data
    $check_harvests = $pdo->query("SHOW TABLES LIKE 'harvests'");
    
    if ($check_harvests->rowCount() > 0) {
        // Table exists, now check if it has any data
        $count_harvests = $pdo->query("SELECT COUNT(*) FROM harvests");
        $has_harvests = ($count_harvests && $count_harvests->fetchColumn() > 0);
        
        if ($has_harvests) {
            // Get available years for filter
            $years_stmt = $pdo->query("SELECT DISTINCT YEAR(actual_harvest_date) AS year FROM harvests ORDER BY year DESC");
            if ($years_stmt) {
                $available_years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        
            // Query for performance data
            $query = "
                SELECT 
                    c.id, 
                    c.crop_name, 
                    c.variety, 
                    f.field_name, 
                    f.area AS field_area,
                    c.planting_date,
                    c.expected_harvest_date,
                    h.actual_harvest_date,
                    h.yield_amount,
                    h.quality_rating,
                    (h.yield_amount / f.area) AS yield_per_acre,
                    h.notes
                FROM 
                    crops c
                JOIN 
                    fields f ON c.field_id = f.id
                LEFT JOIN 
                    harvests h ON c.id = h.crop_id
                WHERE 
                    c.status = 'harvested'
            ";
            
            $params = [];
            
            // Apply filters
            if ($year_filter) {
                $query .= " AND YEAR(h.actual_harvest_date) = ?";
                $params[] = $year_filter;
            }
            
            if ($crop_type_filter) {
                $query .= " AND c.crop_name = ?";
                $params[] = $crop_type_filter;
            }
            
            if ($field_filter) {
                $query .= " AND f.id = ?";
                $params[] = $field_filter;
            }
            
            $query .= " ORDER BY h.actual_harvest_date DESC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $performance_data = $stmt->fetchAll();
            
            // Calculate averages and totals
            $total_yield = 0;
            $total_area = 0;
            $quality_sum = 0;
            $count = count($performance_data);
            
            foreach ($performance_data as $data) {
                if (isset($data['yield_amount']) && is_numeric($data['yield_amount'])) {
                    $total_yield += $data['yield_amount'];
                }
                
                if (isset($data['field_area']) && is_numeric($data['field_area'])) {
                    $total_area += $data['field_area'];
                }
                
                if (isset($data['quality_rating']) && is_numeric($data['quality_rating'])) {
                    $quality_sum += $data['quality_rating'];
                }
            }
            
            $average_yield_per_acre = $total_area > 0 ? $total_yield / $total_area : 0;
            $average_quality = $count > 0 ? $quality_sum / $count : 0;
            
            // Get historical performance data for comparisons
            $historical_query = "
                SELECT 
                    YEAR(h.actual_harvest_date) AS year,
                    c.crop_name,
                    AVG(h.yield_amount / f.area) AS avg_yield_per_acre,
                    AVG(h.quality_rating) AS avg_quality
                FROM 
                    crops c
                JOIN 
                    fields f ON c.field_id = f.id
                JOIN 
                    harvests h ON c.id = h.crop_id
                WHERE 
                    c.status = 'harvested'
                GROUP BY 
                    YEAR(h.actual_harvest_date), c.crop_name
                ORDER BY 
                    year DESC, c.crop_name
            ";
            
            $historical_stmt = $pdo->query($historical_query);
            if ($historical_stmt) {
                $historical_data = $historical_stmt->fetchAll();
            }
        } else {
            $_SESSION['info_message'] = "No harvest records found. Please add harvest records to view performance data.";
        }
    } else {
        $_SESSION['error_message'] = "Harvests table does not exist. Please contact system administrator.";
    }
    
} catch(PDOException $e) {
    error_log("Error fetching performance data: " . $e->getMessage());
    $_SESSION['error_message'] = "Error retrieving performance data: " . $e->getMessage();
}

$pageTitle = 'Crop Performance Analysis';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-pie"></i> Crop Performance Analysis</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button class="btn btn-secondary" onclick="location.href='crop_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Crop Management
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; ?>
            <?php unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
            <?php unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['info_message'])): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> <?php echo $_SESSION['info_message']; ?>
            <?php unset($_SESSION['info_message']); ?>
        </div>
    <?php endif; ?>

    <!-- Filter Controls -->
    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-filter"></i> Filter Options</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="year">Harvest Year:</label>
                        <select id="year" name="year" onchange="this.form.submit()">
                            <option value="">All Years</option>
                            <?php foreach ($available_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($year == $year_filter) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="crop_type">Crop Type:</label>
                        <select id="crop_type" name="crop_type" onchange="this.form.submit()">
                            <option value="">All Crop Types</option>
                            <?php foreach ($crop_types as $crop): ?>
                                <option value="<?php echo $crop; ?>" <?php echo ($crop == $crop_type_filter) ? 'selected' : ''; ?>>
                                    <?php echo $crop; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="field_id">Field:</label>
                        <select id="field_id" name="field_id" onchange="this.form.submit()">
                            <option value="">All Fields</option>
                            <?php foreach ($fields as $field): ?>
                                <option value="<?php echo $field['id']; ?>" <?php echo ($field['id'] == $field_filter) ? 'selected' : ''; ?>>
                                    <?php echo $field['field_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="crop_performance.php" class="btn btn-outline">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Performance Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon bg-green">
                <i class="fas fa-balance-scale"></i>
            </div>
            <div class="summary-details">
                <h3>Total Harvested Crops</h3>
                <p class="summary-count"><?php echo $count; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-blue">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="summary-details">
                <h3>Average Yield/Acre</h3>
                <p class="summary-count"><?php echo number_format($average_yield_per_acre, 2); ?></p>
                <span class="summary-subtitle">units per acre</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-purple">
                <i class="fas fa-star"></i>
            </div>
            <div class="summary-details">
                <h3>Average Quality</h3>
                <p class="summary-count"><?php echo number_format($average_quality, 1); ?>/5</p>
                <span class="summary-subtitle">rating</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-orange">
                <i class="fas fa-tractor"></i>
            </div>
            <div class="summary-details">
                <h3>Total Harvest Area</h3>
                <p class="summary-count"><?php echo number_format($total_area, 1); ?></p>
                <span class="summary-subtitle">acres</span>
            </div>
        </div>
    </div>

    <!-- Performance Analysis Table -->
    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-table"></i> Crop Performance Data</h3>
            <div class="card-actions">
                <div class="search-bar">
                    <input type="text" id="performanceSearch" onkeyup="searchTable()" placeholder="Search...">
                    <i class="fas fa-search"></i>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table" id="performanceTable">
                <thead>
                    <tr>
                        <th>Crop</th>
                        <th>Variety</th>
                        <th>Field</th>
                        <th>Planting Date</th>
                        <th>Harvest Date</th>
                        <th>Yield</th>
                        <th>Yield/Acre</th>
                        <th>Quality</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($performance_data)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No performance data available for the selected filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($performance_data as $data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($data['crop_name']); ?></td>
                                <td><?php echo htmlspecialchars($data['variety']); ?></td>
                                <td><?php echo htmlspecialchars($data['field_name']); ?></td>
                                <td><?php echo !empty($data['planting_date']) ? date('M d, Y', strtotime($data['planting_date'])) : 'N/A'; ?></td>
                                <td><?php echo !empty($data['actual_harvest_date']) ? date('M d, Y', strtotime($data['actual_harvest_date'])) : 'N/A'; ?></td>
                                <td><?php echo isset($data['yield_amount']) ? number_format($data['yield_amount'], 2) : 'N/A'; ?></td>
                                <td><?php echo isset($data['yield_per_acre']) ? number_format($data['yield_per_acre'], 2) : 'N/A'; ?></td>
                                <td>
                                    <div class="quality-stars">
                                        <?php 
                                        $rating = isset($data['quality_rating']) ? round($data['quality_rating']) : 0;
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $rating) {
                                                echo '<i class="fas fa-star"></i>';
                                            } else {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Historical Comparison -->
    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-history"></i> Historical Performance Comparison</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="historicalYieldChart"></canvas>
            </div>
        </div>
    </div>

    <!-- KPI Analysis -->
    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-tachometer-alt"></i> Key Performance Indicators</h3>
        </div>
        <div class="card-body">
            <div class="kpi-grid">
                <div class="kpi-card">
                    <h4>Growth Rate</h4>
                    <div class="kpi-value">
                        <?php 
                        // Calculate days from planting to harvest
                        $avg_growth_days = 0;
                        $growth_count = 0;
                        
                        foreach ($performance_data as $data) {
                            if (!empty($data['planting_date']) && !empty($data['actual_harvest_date'])) {
                                $plant_date = new DateTime($data['planting_date']);
                                $harvest_date = new DateTime($data['actual_harvest_date']);
                                $diff = $plant_date->diff($harvest_date);
                                $avg_growth_days += $diff->days;
                                $growth_count++;
                            }
                        }
                        
                        $avg_growth_days = $growth_count > 0 ? round($avg_growth_days / $growth_count) : 0;
                        echo $avg_growth_days;
                        ?>
                        <span>days</span>
                    </div>
                    <p>Average growth cycle from planting to harvest</p>
                </div>
                
                <div class="kpi-card">
                    <h4>Yield Efficiency</h4>
                    <div class="kpi-value">
                        <?php 
                        // Compare actual yield to expected yield
                        // This would normally be calculated from actual data
                        // For demo purposes, using 85% as a placeholder
                        echo "85%";
                        ?>
                    </div>
                    <p>Actual yield compared to expected yield</p>
                </div>
                
                <div class="kpi-card">
                    <h4>Pest Resistance</h4>
                    <div class="kpi-value">
                        <?php 
                        // Calculate number of crops with pest issues
                        // This would normally be calculated from actual data
                        // For demo purposes, using a placeholder
                        echo "72%";
                        ?>
                    </div>
                    <p>Percentage of crops without pest issues</p>
                </div>
                
                <div class="kpi-card">
                    <h4>Overall Productivity</h4>
                    <div class="kpi-value">
                        <?php 
                        // Calculate productivity score
                        // This would be a composite score based on various factors
                        // For demo purposes, using a placeholder
                        echo "3.8/5";
                        ?>
                    </div>
                    <p>Combined score based on yield, quality, and efficiency</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Search function for performance table
function searchTable() {
    var input, filter, table, tr, td, i, j, txtValue, found;
    input = document.getElementById("performanceSearch");
    filter = input.value.toUpperCase();
    table = document.getElementById("performanceTable");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) {
        found = false;
        td = tr[i].getElementsByTagName("td");
        
        for (j = 0; j < 3; j++) { // Search in first 3 columns only (Crop, Variety, Field)
            if (td[j]) {
                txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        if (found) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}

// Historical Yield Chart
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('historicalYieldChart').getContext('2d');
    
    // Process historical data for chart
    var years = [];
    var cropTypes = [];
    var datasets = [];
    var dataMap = {};
    
    <?php foreach ($historical_data as $data): ?>
        if (!years.includes('<?php echo $data['year']; ?>')) {
            years.push('<?php echo $data['year']; ?>');
        }
        
        if (!cropTypes.includes('<?php echo $data['crop_name']; ?>')) {
            cropTypes.push('<?php echo $data['crop_name']; ?>');
        }
        
        if (!dataMap['<?php echo $data['crop_name']; ?>']) {
            dataMap['<?php echo $data['crop_name']; ?>'] = {};
        }
        
        dataMap['<?php echo $data['crop_name']; ?>']['<?php echo $data['year']; ?>'] = <?php echo $data['avg_yield_per_acre']; ?>;
    <?php endforeach; ?>
    
    // Generate random colors for each crop type
    var colors = [
        '#3498db', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6',
        '#1abc9c', '#f1c40f', '#34495e', '#e67e22', '#16a085'
    ];
    
    // Create datasets for each crop type
    cropTypes.forEach(function(crop, index) {
        var data = [];
        years.forEach(function(year) {
            data.push(dataMap[crop] && dataMap[crop][year] ? dataMap[crop][year] : null);
        });
        
        datasets.push({
            label: crop,
            data: data,
            backgroundColor: colors[index % colors.length],
            borderColor: colors[index % colors.length],
            fill: false,
            tension: 0.1
        });
    });
    
    var chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: years,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Average Yield per Acre'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Year'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Historical Yield Performance by Crop Type',
                    font: {
                        size: 16
                    }
                },
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>

<style>
/* Quality stars styling */
.quality-stars {
    color: #f39c12;
}

/* KPI grid styling */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.kpi-card {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    text-align: center;
}

.kpi-card h4 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 16px;
}

.kpi-value {
    font-size: 32px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 10px;
}

.kpi-value span {
    font-size: 16px;
    color: #7f8c8d;
    margin-left: 5px;
}

.kpi-card p {
    color: #7f8c8d;
    font-size: 14px;
    margin: 0;
}

/* Chart container */
.chart-container {
    width: 100%;
    height: 400px;
    margin: 20px 0;
}

/* Filter form styling */
.filter-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    min-width: 200px;
}

.filter-group label {
    margin-bottom: 5px;
    font-weight: 500;
}

/* Additional colors */
.bg-purple {
    background: #9b59b6 !important;
}

/* Alert styling */
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-info {
    color: #0c5460;
    background-color: #d1ecf1;
    border-color: #bee5eb;
}
</style>

<?php include 'includes/footer.php'; ?>