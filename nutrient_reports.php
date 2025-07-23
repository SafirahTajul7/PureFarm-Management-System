<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Define default filter variables
$filter_field = isset($_GET['field']) ? $_GET['field'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '2024-01-01';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '2025-12-31';
$filter_nutrient = isset($_GET['nutrient_type']) ? $_GET['nutrient_type'] : '';

error_log("Starting soil nutrient reports page...");

// Test database connection directly
try {
    $check = $pdo->query("SELECT 1")->fetchColumn();
    error_log("Database connection confirmed working");
} catch(PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
}

// Debug function to help identify query issues
function debug_query($query, $params) {
    error_log("Query: $query | Params: " . print_r($params, true));
}

// Fetch all fields for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, field_name FROM fields ORDER BY field_name ASC");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Fields found: " . count($fields));
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

// Define nutrient types for dropdown (same as soil_nutrients.php)
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

// Function to fetch nutrient trend over time
function getNutrientTrend($pdo, $nutrient_column, $field_id, $date_from, $date_to) {
    // Convert dates to ensure proper format
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    $query = "
        SELECT 
            DATE_FORMAT(test_date, '%Y-%m') as month,
            AVG({$nutrient_column}) as avg_value
        FROM 
            soil_nutrients
        WHERE 
            test_date BETWEEN ? AND ?
            AND {$nutrient_column} IS NOT NULL
    ";
    
    $params = [$date_from, $date_to];
    
    if (!empty($field_id)) {
        $query .= " AND field_id = ?";
        $params[] = $field_id;
    }
    
    $query .= " GROUP BY DATE_FORMAT(test_date, '%Y-%m') ORDER BY month ASC";
    
    debug_query($query, $params);
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Nutrient trend data points found: " . count($result));
        return $result;
    } catch(PDOException $e) {
        error_log("Error fetching nutrient trend: " . $e->getMessage());
        return [];
    }
}

// Function to get field comparison data for nutrients
function getFieldNutrientComparison($pdo, $date_from, $date_to) {
    // Convert dates to ensure proper format
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                f.field_name,
                ROUND(AVG(sn.nitrogen), 1) as avg_nitrogen,
                ROUND(AVG(sn.phosphorus), 1) as avg_phosphorus,
                ROUND(AVG(sn.potassium), 1) as avg_potassium,
                ROUND(AVG(sn.ph_level), 1) as avg_ph,
                ROUND(AVG(sn.organic_matter), 1) as avg_organic_matter,
                COUNT(*) as test_count
            FROM 
                soil_nutrients sn
            JOIN 
                fields f ON sn.field_id = f.id
            WHERE 
                sn.test_date BETWEEN ? AND ?
            GROUP BY 
                f.field_name
            HAVING 
                test_count > 0
            ORDER BY 
                f.field_name
        ");
        $stmt->execute([$date_from, $date_to]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Field comparison data points found: " . count($result));
        return $result;
    } catch(PDOException $e) {
        error_log("Error fetching field nutrient comparison: " . $e->getMessage());
        return [];
    }
}

// Function to fetch summary statistics
function getNutrientSummaryStats($pdo, $field_id, $date_from, $date_to) {
    // Convert dates to ensure proper format
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    try {
        $query = "
            SELECT 
                ROUND(AVG(nitrogen), 1) as avg_nitrogen,
                ROUND(AVG(phosphorus), 1) as avg_phosphorus,
                ROUND(AVG(potassium), 1) as avg_potassium,
                ROUND(AVG(ph_level), 1) as avg_ph,
                ROUND(AVG(organic_matter), 1) as avg_organic_matter,
                COUNT(*) as test_count,
                COUNT(CASE WHEN nitrogen < 10 THEN 1 END) as low_nitrogen_count,
                COUNT(CASE WHEN ph_level < 6.0 THEN 1 END) as low_ph_count,
                COUNT(DISTINCT field_id) as field_count
            FROM 
                soil_nutrients
            WHERE 
                test_date BETWEEN ? AND ?
        ";
        
        $params = [$date_from, $date_to];
        
        if (!empty($field_id)) {
            $query .= " AND field_id = ?";
            $params[] = $field_id;
        }
        
        error_log("Summary stats query: $query with params: " . json_encode($params));
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Summary stats result: " . json_encode($result));
        return $result;
    } catch(PDOException $e) {
        error_log("Error fetching nutrient summary stats: " . $e->getMessage());
        return [
            'avg_nitrogen' => 'N/A',
            'avg_phosphorus' => 'N/A',
            'avg_potassium' => 'N/A',
            'avg_ph' => 'N/A',
            'avg_organic_matter' => 'N/A',
            'test_count' => 0,
            'low_nitrogen_count' => 0,
            'low_ph_count' => 0,
            'field_count' => 0
        ];
    }
}

// Fetch data for reports based on selected nutrient or all nutrients
$summary_stats = getNutrientSummaryStats($pdo, $filter_field, $filter_date_from, $filter_date_to);
$field_comparison = getFieldNutrientComparison($pdo, $filter_date_from, $filter_date_to);

// Fetch trends for NPK and pH
$nitrogen_trend = getNutrientTrend($pdo, 'nitrogen', $filter_field, $filter_date_from, $filter_date_to);
$phosphorus_trend = getNutrientTrend($pdo, 'phosphorus', $filter_field, $filter_date_from, $filter_date_to);
$potassium_trend = getNutrientTrend($pdo, 'potassium', $filter_field, $filter_date_from, $filter_date_to);
$ph_trend = getNutrientTrend($pdo, 'ph_level', $filter_field, $filter_date_from, $filter_date_to);

// Prepare data for chart.js
function prepareChartData($trend_data) {
    $labels = [];
    $values = [];
    
    foreach ($trend_data as $point) {
        $labels[] = date('M Y', strtotime($point['month'] . '-01'));
        $values[] = $point['avg_value'];
    }
    
    return [
        'labels' => $labels,
        'values' => $values
    ];
}

$nitrogen_chart = prepareChartData($nitrogen_trend);
$phosphorus_chart = prepareChartData($phosphorus_trend);
$potassium_chart = prepareChartData($potassium_trend);
$ph_chart = prepareChartData($ph_trend);

$pageTitle = 'Soil Nutrient Reports';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-bar"></i> Soil Nutrient Reports</h2>
        <div class="action-buttons">
            <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="soil_nutrients.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Soil Nutrients
            </a>
        </div>
    </div>

    <!-- Filter Collapse -->
    <div class="collapse mb-4 show" id="filterCollapse">
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
                    <a href="nutrient_reports.php" class="btn btn-secondary">Reset Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Report summary -->
    <div class="alert alert-info">
        <h5 class="alert-heading"><i class="fas fa-info-circle"></i> Report Summary</h5>
        <p>
            <?php if (!empty($filter_field)): ?>
                <?php 
                    $field_name = '';
                    foreach ($fields as $field) {
                        if ($field['id'] == $filter_field) {
                            $field_name = $field['field_name'];
                            break;
                        }
                    }
                ?>
                Displaying soil nutrient data for <strong><?php echo htmlspecialchars($field_name); ?></strong> 
            <?php else: ?>
                Displaying soil nutrient data for <strong>all fields</strong> 
            <?php endif; ?>
            from <strong><?php echo date('M d, Y', strtotime($filter_date_from)); ?></strong> 
            to <strong><?php echo date('M d, Y', strtotime($filter_date_to)); ?></strong>.
            <br>
            Total tests: <strong><?php echo $summary_stats['test_count']; ?></strong>, 
            Average pH: <strong><?php echo $summary_stats['avg_ph']; ?></strong>, 
            Fields with low nitrogen: <strong><?php echo $summary_stats['low_nitrogen_count']; ?></strong>
        </p>
    </div>

    <!-- Summary Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Tests</h5>
                    <p class="card-text display-6"><?php echo $summary_stats['test_count']; ?></p>
                    <p class="card-text small">Tests conducted in selected period</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Average pH</h5>
                    <p class="card-text display-6"><?php echo $summary_stats['avg_ph']; ?></p>
                    <p class="card-text small">Optimal range: 6.0-7.5</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Fields Tested</h5>
                    <p class="card-text display-6"><?php echo $summary_stats['field_count']; ?></p>
                    <p class="card-text small">Number of unique fields</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card <?php echo ($summary_stats['low_nitrogen_count'] > 0) ? 'bg-warning' : 'bg-light'; ?>">
                <div class="card-body">
                    <h5 class="card-title">Fields with Low N</h5>
                    <p class="card-text display-6"><?php echo $summary_stats['low_nitrogen_count']; ?></p>
                    <p class="card-text small">Nitrogen below 10 ppm</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- NPK Trend Charts -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line me-2"></i>NPK Trend Analysis</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($nitrogen_trend) && empty($phosphorus_trend) && empty($potassium_trend)): ?>
                        <div class="alert alert-info">
                            No nutrient trend data available for the selected period.
                        </div>
                    <?php else: ?>
                        <canvas id="npkTrendChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- pH Trend Chart -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line me-2"></i>pH Level Trend</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($ph_trend)): ?>
                        <div class="alert alert-info">
                            No pH trend data available for the selected period.
                        </div>
                    <?php else: ?>
                        <canvas id="phTrendChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Field Comparison Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-table me-2"></i>Field Nutrient Comparison</h5>
        </div>
        <div class="card-body">
            <?php if (empty($field_comparison)): ?>
                <div class="alert alert-info">
                    No field comparison data available for the selected period.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Tests</th>
                                <th>Avg N (ppm)</th>
                                <th>Avg P (ppm)</th>
                                <th>Avg K (ppm)</th>
                                <th>Avg pH</th>
                                <th>Avg Organic Matter (%)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($field_comparison as $field): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($field['field_name']); ?></td>
                                    <td><?php echo $field['test_count']; ?></td>
                                    <td>
                                        <?php 
                                            $n_class = '';
                                            if ($field['avg_nitrogen'] < 10) $n_class = 'text-danger';
                                            elseif ($field['avg_nitrogen'] > 30) $n_class = 'text-success';
                                            echo '<span class="' . $n_class . '">' . $field['avg_nitrogen'] . '</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $p_class = '';
                                            if ($field['avg_phosphorus'] < 15) $p_class = 'text-danger';
                                            elseif ($field['avg_phosphorus'] > 40) $p_class = 'text-success';
                                            echo '<span class="' . $p_class . '">' . $field['avg_phosphorus'] . '</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $k_class = '';
                                            if ($field['avg_potassium'] < 100) $k_class = 'text-danger';
                                            elseif ($field['avg_potassium'] > 250) $k_class = 'text-success';
                                            echo '<span class="' . $k_class . '">' . $field['avg_potassium'] . '</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $ph_class = '';
                                            if ($field['avg_ph'] < 5.5) $ph_class = 'text-danger';
                                            elseif ($field['avg_ph'] > 7.5) $ph_class = 'text-warning';
                                            elseif ($field['avg_ph'] >= 6.0 && $field['avg_ph'] <= 7.0) $ph_class = 'text-success';
                                            echo '<span class="' . $ph_class . '">' . $field['avg_ph'] . '</span>';
                                        ?>
                                    </td>
                                    <td><?php echo $field['avg_organic_matter']; ?>%</td>
                                    <td>
                                        <?php 
                                            $status = 'Good';
                                            $status_class = 'badge bg-success';
                                            
                                            if ($field['avg_nitrogen'] < 10 || $field['avg_phosphorus'] < 15 || $field['avg_potassium'] < 100) {
                                                $status = 'Low Nutrients';
                                                $status_class = 'badge bg-danger';
                                            } else if ($field['avg_ph'] < 5.5 || $field['avg_ph'] > 7.5) {
                                                $status = 'pH Issue';
                                                $status_class = 'badge bg-warning text-dark';
                                            }
                                            
                                            echo '<span class="' . $status_class . '">' . $status . '</span>';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recommendations Based on Data -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-lightbulb me-2"></i>Nutrient Management Recommendations</h5>
        </div>
        <div class="card-body">
            <?php if ($summary_stats['low_nitrogen_count'] > 0): ?>
            <div class="alert alert-warning">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Low Nitrogen Alert</h6>
                <p>Some fields have nitrogen levels below 10 ppm. Consider applying nitrogen-rich fertilizer to prevent crop nutrient deficiency.</p>
            </div>
            <?php endif; ?>
            
            <?php 
            // Check if average pH is a number before comparing
            $avg_ph_value = is_numeric(str_replace(['N/A', '%'], '', $summary_stats['avg_ph'])) ? floatval(str_replace(['N/A', '%'], '', $summary_stats['avg_ph'])) : null;
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
            
            <?php 
            // Only show optimal conditions if there are no issues and there are readings
            if ($summary_stats['test_count'] > 0 && $summary_stats['low_nitrogen_count'] == 0 && 
                ($avg_ph_value === null || ($avg_ph_value >= 6.0 && $avg_ph_value <= 7.0))): 
            ?>
            <div class="alert alert-success">
                <h6><i class="fas fa-check-circle me-2"></i>Optimal Nutrient Levels</h6>
                <p>Your soil nutrient levels and pH are within optimal ranges. Continue your current fertilization practices.</p>
            </div>
            <?php endif; ?>

            <!-- Additional Recommendations -->
            <div class="mt-4">
                <h6><i class="fas fa-clipboard-list me-2"></i>Detailed Recommendations</h6>
                <ul class="list-group">
                    <li class="list-group-item">
                        <strong>Nitrogen Management:</strong> 
                        <?php if ($summary_stats['avg_nitrogen'] < 10): ?>
                            Apply nitrogen-rich fertilizers such as urea, ammonium nitrate, or organic alternatives like blood meal.
                        <?php elseif ($summary_stats['avg_nitrogen'] > 30): ?>
                            Nitrogen levels are high. Consider planting nitrogen-demanding crops or reducing nitrogen applications.
                        <?php else: ?>
                            Nitrogen levels are adequate. Maintain current practices.
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item">
                        <strong>Phosphorus Management:</strong>
                        <?php if ($summary_stats['avg_phosphorus'] < 15): ?>
                            Apply phosphorus-rich fertilizers such as rock phosphate, bone meal, or triple superphosphate.
                        <?php elseif ($summary_stats['avg_phosphorus'] > 40): ?>
                            Phosphorus levels are high. Avoid additional phosphorus applications to prevent runoff and water quality issues.
                        <?php else: ?>
                            Phosphorus levels are adequate. Maintain current practices.
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item">
                        <strong>Potassium Management:</strong>
                        <?php if ($summary_stats['avg_potassium'] < 100): ?>
                            Apply potassium-rich fertilizers such as potassium chloride, potassium sulfate, or organic sources like wood ash.
                        <?php elseif ($summary_stats['avg_potassium'] > 250): ?>
                            Potassium levels are high. Reduce potassium applications and consider crops that use high amounts of potassium.
                        <?php else: ?>
                            Potassium levels are adequate. Maintain current practices.
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item">
                        <strong>Soil Testing Frequency:</strong> Continue regular soil testing to monitor nutrient changes over time and adjust fertilization practices accordingly.
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Export Options -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-file-export me-2"></i>Export Options</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <div class="d-grid">
                        <button class="btn btn-primary" id="exportPDF">
                            <i class="fas fa-file-pdf me-2"></i>Export as PDF
                        </button>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="d-grid">
                        <button class="btn btn-success" id="exportExcel">
                            <i class="fas fa-file-excel me-2"></i>Export as Excel
                        </button>
                    </div>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="d-grid">
                        <button class="btn btn-secondary" id="printReport">
                            <i class="fas fa-print me-2"></i>Print Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Use this specific version of jsPDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/1.5.3/jspdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // NPK Trend Chart
    <?php if (!empty($nitrogen_trend) || !empty($phosphorus_trend) || !empty($potassium_trend)): ?>
    var npkCtx = document.getElementById('npkTrendChart').getContext('2d');
    var npkChart = new Chart(npkCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($nitrogen_chart['labels'] ?: $phosphorus_chart['labels'] ?: $potassium_chart['labels']); ?>,
            datasets: [
                {
                    label: 'Nitrogen (ppm)',
                    data: <?php echo json_encode($potassium_chart['values']); ?>,
                    backgroundColor: 'rgba(255, 206, 86, 0.2)',
                    borderColor: 'rgba(255, 206, 86, 1)',
                    borderWidth: 2,
                    tension: 0.1
                }
            ]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Value (ppm)'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // pH Trend Chart
    <?php if (!empty($ph_trend)): ?>
    var phTrendCtx = document.getElementById('phTrendChart').getContext('2d');
    var phTrendChart = new Chart(phTrendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($ph_chart['labels']); ?>,
            datasets: [{
                label: 'Average pH',
                data: <?php echo json_encode($ph_chart['values']); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                tension: 0.1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: false,
                    min: 5,
                    max: 8,
                    title: {
                        display: true,
                        text: 'pH Level'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'pH: ' + context.parsed.y;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // Handle date range validation
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    if (dateFrom && dateTo) {
        dateFrom.addEventListener('change', function() {
            if (dateTo.value && this.value > dateTo.value) {
                dateTo.value = this.value;
            }
        });
        
        dateTo.addEventListener('change', function() {
            if (dateFrom.value && this.value < dateFrom.value) {
                dateFrom.value = this.value;
            }
        });
    }

    // Export PDF functionality
    document.getElementById('exportPDF').addEventListener('click', function() {
        // Create loading message
        const loadingMsg = document.createElement('div');
        loadingMsg.className = 'alert alert-info position-fixed top-50 start-50 translate-middle';
        loadingMsg.innerHTML = '<div class="spinner-border spinner-border-sm me-2" role="status"></div>Generating PDF...';
        loadingMsg.style.zIndex = '9999';
        document.body.appendChild(loadingMsg);
        
        // Simple cleanup function
        function cleanup() {
            if (document.body.contains(loadingMsg)) {
                document.body.removeChild(loadingMsg);
            }
        }

        try {
            // Get report title and date range for the filename
            const dateFrom = document.getElementById('date_from').value || '2024-01-01';
            const dateTo = document.getElementById('date_to').value || '2025-12-31';
            const fileName = 'soil_nutrient_report_' + dateFrom + '_to_' + dateTo + '.pdf';
            
            // Create directly with the global jsPDF class
            const doc = new jsPDF();
            
            // Main content to capture
            const content = document.querySelector('.main-content');
            
            // Temporarily hide elements we don't want in the PDF
            const elementsToHide = [
                ...document.querySelectorAll('.action-buttons'),
                ...document.querySelectorAll('#filterCollapse'),
                ...document.querySelectorAll('.btn-link')
            ];
            
            // Store original display states
            const originalDisplays = elementsToHide.map(el => el.style.display);
            
            // Hide elements
            elementsToHide.forEach(el => {
                el.style.display = 'none';
            });
            
            // Use html2canvas directly with a promise
            html2canvas(content, {
                scale: 1.5, // Better quality
                useCORS: true,
                allowTaint: true,
                logging: false
            }).then(function(canvas) {
                try {
                    // Calculate dimensions to fit on A4
                    const imgWidth = 190; // mm, slightly smaller than A4 width
                    const pageHeight = 287; // mm, slightly smaller than A4 height
                    const imgHeight = canvas.height * imgWidth / canvas.width;
                    
                    // Get image data
                    const imgData = canvas.toDataURL('image/jpeg', 0.8); // Use JPEG for smaller file size
                    
                    // Add to PDF - first page
                    doc.addImage(imgData, 'JPEG', 10, 10, imgWidth, imgHeight);
                    
                    // Add additional pages if content is too tall
                    if (imgHeight > pageHeight) {
                        let heightLeft = imgHeight;
                        let position = 0;
                        
                        // Remove content from first page that will be on other pages
                        doc.addImage(imgData, 'JPEG', 10, 10, imgWidth, pageHeight);
                        heightLeft -= pageHeight;
                        position = -pageHeight;
                        
                        // Add new pages as needed
                        while (heightLeft > 0) {
                            position = position - pageHeight;
                            doc.addPage();
                            doc.addImage(imgData, 'JPEG', 10, position, imgWidth, imgHeight);
                            heightLeft -= pageHeight;
                        }
                    }
                    
                    // Save the PDF
                    doc.save(fileName);
                    
                    // Restore hidden elements to their original state
                    elementsToHide.forEach((el, index) => {
                        el.style.display = originalDisplays[index];
                    });
                    
                    // Remove loading message
                    cleanup();
                } catch (error) {
                    console.error('Error generating PDF:', error);
                    alert('Error generating PDF: ' + error.message);
                    
                    // Restore hidden elements
                    elementsToHide.forEach((el, index) => {
                        el.style.display = originalDisplays[index];
                    });
                    
                    cleanup();
                }
            }).catch(function(error) {
                console.error('Error capturing content:', error);
                alert('Error capturing content: ' + error.message);
                
                // Restore hidden elements
                elementsToHide.forEach((el, index) => {
                    el.style.display = originalDisplays[index];
                });
                
                cleanup();
            });
        } catch (error) {
            console.error('Overall error:', error);
            alert('Failed to create PDF: ' + error.message);
            cleanup();
        }
    });

    // Export to Excel functionality
    document.getElementById('exportExcel').addEventListener('click', function() {
        // Create a CSV content
        let csvContent = "data:text/csv;charset=utf-8,";
        
        // Add headers
        csvContent += "Field,Tests,Avg Nitrogen,Avg Phosphorus,Avg Potassium,Avg pH,Avg Organic Matter,Status\n";
        
        // Get field comparison data from the table
        const table = document.querySelector('table');
        if (table) {
            const rows = table.querySelectorAll('tbody tr');
            if (rows.length > 0) {
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 8) {
                        for (let i = 0; i < cells.length; i++) {
                            // Add cell text and comma
                            csvContent += cells[i].textContent.trim().replace(/,/g, ';') + (i < cells.length - 1 ? ',' : '\n');
                        }
                    }
                });
            } else {
                // If no data, add a row indicating no data
                csvContent += "No data available for the selected period,,,,,,\n";
            }
        } else {
            // If no table found, add a row indicating no data
            csvContent += "No data available for the selected period,,,,,,\n";
        }
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "soil_nutrient_report_" + new Date().toISOString().slice(0, 10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    // Print Report functionality
    document.getElementById('printReport').addEventListener('click', function() {
        window.print();
    });
});
</script>

<!-- Print styles -->
<style>
@media print {
    .no-print, .action-buttons, #filterCollapse, footer, .btn, .card-header button {
        display: none !important;
    }
    .card {
        border: 1px solid #ddd !important;
        break-inside: avoid;
    }
    .main-content {
        width: 100%;
        margin: 0;
        padding: 0;
    }
    body {
        background-color: white !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>_