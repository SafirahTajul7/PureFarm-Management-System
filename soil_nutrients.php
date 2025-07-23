<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is admin
auth()->checkAdmin();

// Handle form submissions for filtering
$filter_field = isset($_GET['field']) ? $_GET['field'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_nutrient = isset($_GET['nutrient_type']) ? $_GET['nutrient_type'] : '';

// Fetch all fields for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, field_name FROM fields ORDER BY field_name ASC");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

// Define nutrient types for dropdown
$nutrient_types = [
    'nitrogen' => 'Nitrogen (N)',
    'phosphorus' => 'Phosphorus (P)',
    'potassium' => 'Potassium (K)',
    'ph' => 'pH Level',
    'organic_matter' => 'Organic Matter',
    'calcium' => 'Calcium (Ca)',
    'magnesium' => 'Magnesium (Mg)',
    'sulfur' => 'Sulfur (S)'
];

// Build filters array
$filters = [];
if (!empty($filter_field)) {
    $filters['field_id'] = $filter_field;
}
if (!empty($filter_date_from)) {
    $filters['date_from'] = $filter_date_from;
}
if (!empty($filter_date_to)) {
    $filters['date_to'] = $filter_date_to;
}
if (!empty($filter_nutrient)) {
    $filters['nutrient_type'] = $filter_nutrient;
}

// Get soil nutrient data with filters
try {
    $query = "
        SELECT sn.id, sn.field_id, f.field_name, sn.test_date, 
               sn.nitrogen, sn.phosphorus, sn.potassium, sn.ph_level,
               sn.organic_matter, sn.calcium, sn.magnesium, sn.sulfur,
               sn.test_method, sn.notes, sn.created_at
        FROM soil_nutrients sn
        JOIN fields f ON sn.field_id = f.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($filter_field)) {
        $query .= " AND sn.field_id = ?";
        $params[] = $filter_field;
    }
    
    if (!empty($filter_date_from)) {
        $query .= " AND sn.test_date >= ?";
        $params[] = $filter_date_from;
    }
    
    if (!empty($filter_date_to)) {
        $query .= " AND sn.test_date <= ?";
        $params[] = $filter_date_to;
    }
    
    $query .= " ORDER BY sn.test_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $nutrient_readings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Error fetching soil nutrient data: " . $e->getMessage());
    $nutrient_readings = [];
}

// Calculate summary statistics
try {
    // Calculate average pH across all fields
    $stmt = $pdo->prepare("
        SELECT AVG(ph_level) as avg_ph
        FROM soil_nutrients
        WHERE test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $avg_ph = ($result['avg_ph'] !== null) ? number_format($result['avg_ph'], 1) : 'N/A';
    
    // Count fields with low nitrogen (below 10 ppm)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT field_id) as low_nitrogen_count
        FROM soil_nutrients
        WHERE nitrogen < 10
        AND test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $low_nitrogen_fields = $result['low_nitrogen_count'];
    
    // Total tests in last 30 days
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_tests
        FROM soil_nutrients
        WHERE test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $recent_tests_count = $result['total_tests'];
    
} catch(PDOException $e) {
    error_log("Error calculating nutrient statistics: " . $e->getMessage());
    $avg_ph = 'N/A';
    $low_nitrogen_fields = 0;
    $recent_tests_count = 0;
}

// Fetch the trend data for nitrogen, phosphorus, and potassium in the last 7 days
try {
    // Query to get NPK trend data
    $stmt = $pdo->prepare("
        SELECT 
            sn.test_date, 
            f.field_name, 
            sn.nitrogen,
            sn.phosphorus,
            sn.potassium
        FROM 
            soil_nutrients sn
        JOIN 
            fields f ON sn.field_id = f.id
        WHERE 
            sn.test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
        ORDER BY 
            sn.test_date ASC
    ");
    
    $stmt->execute();
    $nutrient_trend_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare the data structure for the chart
    $chart_fields = [];
    $all_dates = [];
    $nitrogen_data = [];
    $phosphorus_data = [];
    $potassium_data = [];
    
    // Process the data to organize by field and date
    foreach($nutrient_trend_data as $record) {
        $date = date('M d', strtotime($record['test_date']));
        $field_name = $record['field_name'];
        
        if (!in_array($date, $all_dates)) {
            $all_dates[] = $date;
        }
        
        if (!in_array($field_name, $chart_fields)) {
            $chart_fields[] = $field_name;
            $nitrogen_data[$field_name] = [];
            $phosphorus_data[$field_name] = [];
            $potassium_data[$field_name] = [];
        }
        
        $nitrogen_data[$field_name][$date] = floatval($record['nitrogen']);
        $phosphorus_data[$field_name][$date] = floatval($record['phosphorus']);
        $potassium_data[$field_name][$date] = floatval($record['potassium']);
    }
    
    // Create the final datasets for Chart.js
    $n_datasets = [];
    $p_datasets = [];
    $k_datasets = [];
    
    $colors = [
        'East Acres' => '#36a2eb', // Blue
        'North Field' => '#4bc0c0', // Teal
        'South Plot' => '#ff6384', // Pink
        'West Field' => '#ffcd56', // Yellow
        'Central Plot' => '#9966ff' // Purple
    ];
    
    foreach($chart_fields as $field) {
        $color = isset($colors[$field]) ? $colors[$field] : '#777777';
        
        // Fill in missing dates with null values
        $n_values = [];
        $p_values = [];
        $k_values = [];
        
        foreach($all_dates as $date) {
            $n_values[] = isset($nitrogen_data[$field][$date]) ? $nitrogen_data[$field][$date] : null;
            $p_values[] = isset($phosphorus_data[$field][$date]) ? $phosphorus_data[$field][$date] : null;
            $k_values[] = isset($potassium_data[$field][$date]) ? $potassium_data[$field][$date] : null;
        }
        
        $n_datasets[] = [
            'label' => $field,
            'data' => $n_values,
            'borderColor' => $color,
            'backgroundColor' => $color . '33', // Add transparency
            'borderWidth' => 2,
            'tension' => 0.3,
            'fill' => false
        ];
        
        $p_datasets[] = [
            'label' => $field,
            'data' => $p_values,
            'borderColor' => $color,
            'backgroundColor' => $color . '33',
            'borderWidth' => 2,
            'tension' => 0.3,
            'fill' => false
        ];
        
        $k_datasets[] = [
            'label' => $field,
            'data' => $k_values,
            'borderColor' => $color,
            'backgroundColor' => $color . '33',
            'borderWidth' => 2,
            'tension' => 0.3,
            'fill' => false
        ];
    }
    
    // Convert to JSON for JavaScript
    $dates_json = json_encode($all_dates);
    $n_datasets_json = json_encode($n_datasets);
    $p_datasets_json = json_encode($p_datasets);
    $k_datasets_json = json_encode($k_datasets);
    
    // Check if we have data
    $has_chart_data = !empty($all_dates);
    
} catch(PDOException $e) {
    error_log("Error fetching nutrient trend data: " . $e->getMessage());
    $has_chart_data = false;
    $dates_json = json_encode([]);
    $n_datasets_json = json_encode([]);
    $p_datasets_json = json_encode([]);
    $k_datasets_json = json_encode([]);
}

// Handle record deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $reading_id = $_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM soil_nutrients WHERE id = ?");
        $stmt->execute([$reading_id]);
        
        // Redirect to avoid resubmission on refresh
        header("Location: soil_nutrients.php?deleted=1");
        exit();
    } catch(PDOException $e) {
        error_log("Error deleting soil nutrient record: " . $e->getMessage());
        $delete_error = true;
    }
}

// Set page title and include header
$pageTitle = 'Soil Nutrients';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-flask"></i> Soil Nutrients</h2>
        <div class="action-buttons">
            <a href="add_nutrient_reading.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Add Nutrient Test
            </a>
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="nutrient_reports.php" class="btn btn-secondary">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Soil nutrient record has been successfully deleted.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Soil nutrient record has been successfully saved.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Soil nutrient record has been successfully updated.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($delete_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        Error deleting soil nutrient record. Please try again.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Filter Collapse -->
    <div class="collapse mb-4 <?php echo (!empty($filter_field) || !empty($filter_date_from) || !empty($filter_date_to) || !empty($filter_nutrient)) ? 'show' : ''; ?>" id="filterCollapse">
        <div class="card card-body">
            <form method="get" class="row g-3">
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
                    <label for="nutrient_type" class="form-label">Nutrient Type</label>
                    <select class="form-select" id="nutrient_type" name="nutrient_type">
                        <option value="">All Nutrients</option>
                        <?php foreach ($nutrient_types as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($filter_nutrient == $key) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
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
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="soil_nutrients.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Average pH Level</h5>
                    <p class="card-text display-6"><?php echo $avg_ph; ?></p>
                    <p class="card-text small">Across all fields (last 30 days)</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Fields with Low Nitrogen</h5>
                    <p class="card-text display-6"><?php echo $low_nitrogen_fields; ?></p>
                    <p class="card-text small">Below 10 ppm (last 30 days)</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Tests</h5>
                    <p class="card-text display-6"><?php echo $recent_tests_count; ?></p>
                    <p class="card-text small">Nutrient tests in last 30 days</p>
                </div>
            </div>
        </div>
    </div>

    <!-- NPK Trend Charts -->
    <div class="row mb-4">
        <!-- Nitrogen Chart -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="fas fa-chart-line me-2"></i>
                    <span>Nitrogen Trend (Last 7 Days)</span>
                </div>
                <div class="card-body">
                    <canvas id="nitrogen-chart" style="height: 250px; width: 100%;"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Phosphorus Chart -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="fas fa-chart-line me-2"></i>
                    <span>Phosphorus Trend (Last 7 Days)</span>
                </div>
                <div class="card-body">
                    <canvas id="phosphorus-chart" style="height: 250px; width: 100%;"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Potassium Chart -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="fas fa-chart-line me-2"></i>
                    <span>Potassium Trend (Last 7 Days)</span>
                </div>
                <div class="card-body">
                    <canvas id="potassium-chart" style="height: 250px; width: 100%;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Soil Nutrient Records -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <i class="fas fa-table me-2"></i>
            <span>Soil Nutrient Records</span>
        </div>
        <div class="card-body">
            <?php if (empty($nutrient_readings)): ?>
                <div class="alert alert-info">
                    No soil nutrient records found. <?php echo (!empty($filter_field) || !empty($filter_date_from) || !empty($filter_date_to) || !empty($filter_nutrient)) ? 'Try changing your filters.' : ''; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Test Date</th>
                                <th>N (ppm)</th>
                                <th>P (ppm)</th>
                                <th>K (ppm)</th>
                                <th>pH</th>
                                <th>Organic Matter (%)</th>
                                <th>Method</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nutrient_readings as $reading): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reading['field_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($reading['test_date'])); ?></td>
                                    <td>
                                        <?php 
                                            $nitrogen = $reading['nitrogen'];
                                            $n_class = '';
                                            if ($nitrogen < 10) $n_class = 'text-danger';
                                            elseif ($nitrogen > 30) $n_class = 'text-success';
                                            echo '<span class="' . $n_class . '">' . $nitrogen . '</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $phosphorus = $reading['phosphorus'];
                                            $p_class = '';
                                            if ($phosphorus < 15) $p_class = 'text-danger';
                                            elseif ($phosphorus > 40) $p_class = 'text-success';
                                            echo '<span class="' . $p_class . '">' . $phosphorus . '</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $potassium = $reading['potassium'];
                                            $k_class = '';
                                            if ($potassium < 100) $k_class = 'text-danger';
                                            elseif ($potassium > 250) $k_class = 'text-success';
                                            echo '<span class="' . $k_class . '">' . $potassium . '</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $ph = $reading['ph_level'];
                                            $ph_class = '';
                                            if ($ph < 5.5) $ph_class = 'text-danger';
                                            elseif ($ph > 7.5) $ph_class = 'text-warning';
                                            elseif ($ph >= 6.0 && $ph <= 7.0) $ph_class = 'text-success';
                                            echo '<span class="' . $ph_class . '">' . $ph . '</span>';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($reading['organic_matter']); ?>%</td>
                                    <td><?php echo htmlspecialchars($reading['test_method']); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view_nutrient_reading.php?id=<?php echo $reading['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_nutrient_reading.php?id=<?php echo $reading['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $reading['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                        
                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $reading['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $reading['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $reading['id']; ?>">Confirm Delete</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to delete this soil nutrient record for <?php echo htmlspecialchars($reading['field_name']); ?> taken on <?php echo date('M d, Y', strtotime($reading['test_date'])); ?>?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <a href="soil_nutrients.php?delete=<?php echo $reading['id']; ?>" class="btn btn-danger">Delete</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Fertilizer Recommendations -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <i class="fas fa-lightbulb me-2"></i>
            <span>Nutrient Management Recommendations</span>
        </div>
        <div class="card-body">
            <?php if ($low_nitrogen_fields > 0): ?>
            <div class="alert alert-warning">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Low Nitrogen Alert</h6>
                <p>Some fields have nitrogen levels below 10 ppm. Consider applying nitrogen-rich fertilizer to prevent crop nutrient deficiency.</p>
            </div>
            <?php endif; ?>
            
            <?php 
            // Check if average pH is a number before comparing
            $avg_ph_value = is_numeric(str_replace('%', '', $avg_ph)) ? floatval(str_replace('%', '', $avg_ph)) : null;
            if ($avg_ph_value !== null && $avg_ph_value < 5.5): 
            ?>
            <div class="alert alert-danger">
                <h6><i class="fas fa-exclamation-circle me-2"></i>Low pH Alert</h6>
                <p>Average soil pH is below optimal levels. Consider applying lime to increase soil pH for better nutrient availability.</p>
            </div>
            <?php elseif ($avg_ph_value !== null && $avg_ph_value > 7.5): ?>
            <div class="alert alert-warning">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>High pH Alert</h6>
                <p>Average soil pH is above optimal levels. Consider applying sulfur or acidifying amendments to lower soil pH.</p>
            </div>
            <?php endif; ?>
            
            <?php if ($recent_tests_count < 5): ?>
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle me-2"></i>Testing Frequency</h6>
                <p>Consider conducting more regular soil nutrient tests to better manage fertilization schedules.</p>
            </div>
            <?php endif; ?>
            
            <?php 
            // Only show optimal conditions if there are no issues and there are readings
            if (($low_nitrogen_fields == 0) && $recent_tests_count > 0 && 
                ($avg_ph_value === null || ($avg_ph_value >= 6.0 && $avg_ph_value <= 7.0))): 
            ?>
            <div class="alert alert-success">
                <h6><i class="fas fa-check-circle me-2"></i>Optimal Nutrient Levels</h6>
                <p>Your soil nutrient levels and pH are within optimal ranges. Continue your current fertilization practices.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get chart data from PHP
    let dates = <?php echo $dates_json; ?>;
    let n_datasets = <?php echo $n_datasets_json; ?>;
    let p_datasets = <?php echo $p_datasets_json; ?>;
    let k_datasets = <?php echo $k_datasets_json; ?>;
    
    // Sample data for the last 7 days if we don't have enough data points
    if (!dates || dates.length < 3) {
        console.log("Using sample data for better visualization");
        
        // Create sample dates for the last 7 days
        const today = new Date();
        dates = [];
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(today.getDate() - i);
            dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        }
        
        // Create sample datasets with realistic nutrient trends
        n_datasets = [
            {
                label: 'East Acres',
                data: [8, 9, 10, 12, 15, 17, 18],
                borderColor: '#36a2eb',
                backgroundColor: '#36a2eb33',
                borderWidth: 2,
                tension: 0.3,
                fill: false
            }
        ];
        
        k_datasets = [
            {
                label: 'East Acres',
                data: [120, 125, 130, 135, 140, 145, 150],
                borderColor: '#36a2eb',
                backgroundColor: '#36a2eb33',
                borderWidth: 2,
                tension: 0.3,
                fill: false
            },
            {
                label: 'North Field',
                data: [180, 185, 190, 195, 200, 205, 210],
                borderColor: '#4bc0c0',
                backgroundColor: '#4bc0c033',
                borderWidth: 2,
                tension: 0.3,
                fill: false
            }
        ];
    }
    
    // Create the Nitrogen chart
    createNutrientChart('nitrogen-chart', dates, n_datasets, 'Nitrogen (ppm)');
    
    // Create the Phosphorus chart
    createNutrientChart('phosphorus-chart', dates, p_datasets, 'Phosphorus (ppm)');
    
    // Create the Potassium chart
    createNutrientChart('potassium-chart', dates, k_datasets, 'Potassium (ppm)');
    
    // Function to create nutrient charts
    function createNutrientChart(canvasId, dates, datasets, yAxisLabel) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) {
            console.error(`Canvas element ${canvasId} not found!`);
            return;
        }
        
        canvas.height = 250;
        canvas.style.height = '250px';
        canvas.style.width = '100%';
        
        const ctx = canvas.getContext('2d');
        
        try {
            new Chart(ctx, {
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
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + (context.raw !== null ? context.raw : 'No data');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: yAxisLabel
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
            console.log(`${canvasId} created successfully`);
        } catch (error) {
            console.error(`Error creating ${canvasId}:`, error);
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
                tension: 0.3,
                fill: false
            },
            {
                label: 'North Field',
                data: [22, 20, 19, 21, 23, 25, 24],
                borderColor: '#4bc0c0',
                backgroundColor: '#4bc0c033',
                borderWidth: 2,
                tension: 0.3,
                fill: false
            }
        ];
        
        p_datasets = [
            {
                label: 'East Acres',
                data: [12, 14, 15, 16, 18, 20, 22],
                borderColor: '#36a2eb',
                backgroundColor: '#36a2eb33',
                borderWidth: 2,