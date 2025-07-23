<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Define default filter variables first to avoid undefined variable warnings
$filter_field = isset($_GET['field']) ? $_GET['field'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-1 year'));
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$filter_treatment_type = isset($_GET['treatment_type']) ? $_GET['treatment_type'] : '';

error_log("Starting soil treatment reports page...");

// Fetch all fields for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, field_name FROM fields ORDER BY field_name ASC");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

// Define treatment types for dropdown
$treatment_types = [
    'lime' => 'Lime Application',
    'gypsum' => 'Gypsum Application',
    'compost' => 'Compost/Organic Matter',
    'sulfur' => 'Sulfur Amendment',
    'manure' => 'Manure Application',
    'biochar' => 'Biochar',
    'cover_crop' => 'Cover Crop Integration',
    'other' => 'Other Amendment'
];

// Debug function to help identify query issues
function debug_query($query, $params) {
    error_log("Query: $query | Params: " . print_r($params, true));
}

// Function to fetch treatment frequency by type
function getTreatmentTypeDistribution($pdo, $field_id, $date_from, $date_to) {
    // Convert dates to ensure proper format
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    $query = "
        SELECT 
            treatment_type,
            COUNT(*) as count
        FROM 
            soil_treatments
        WHERE 
            application_date BETWEEN ? AND ?
    ";
    
    $params = [$date_from, $date_to];
    
    if (!empty($field_id)) {
        $query .= " AND field_id = ?";
        $params[] = $field_id;
    }
    
    $query .= " GROUP BY treatment_type ORDER BY count DESC";
    
    debug_query($query, $params);
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Treatment type distribution data points found: " . count($result));
        return $result;
    } catch(PDOException $e) {
        error_log("Error fetching treatment type distribution: " . $e->getMessage());
        return [];
    }
}

// Function to fetch treatment cost trend over time
function getTreatmentCostTrend($pdo, $field_id, $treatment_type, $date_from, $date_to) {
    // Convert dates to ensure proper format
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    $query = "
        SELECT 
            DATE_FORMAT(application_date, '%Y-%m') as month,
            SUM(total_cost) as total_cost,
            COUNT(*) as treatment_count
        FROM 
            soil_treatments
        WHERE 
            application_date BETWEEN ? AND ?
    ";
    
    $params = [$date_from, $date_to];
    
    if (!empty($field_id)) {
        $query .= " AND field_id = ?";
        $params[] = $field_id;
    }
    
    if (!empty($treatment_type)) {
        $query .= " AND treatment_type = ?";
        $params[] = $treatment_type;
    }
    
    $query .= " GROUP BY DATE_FORMAT(application_date, '%Y-%m') ORDER BY month ASC";
    
    debug_query($query, $params);
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Treatment cost trend data points found: " . count($result));
        return $result;
    } catch(PDOException $e) {
        error_log("Error fetching treatment cost trend: " . $e->getMessage());
        return [];
    }
}

// Function to fetch field comparison data
function getFieldTreatmentComparison($pdo, $date_from, $date_to, $treatment_type = '') {
    // Convert dates to ensure proper format
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    try {
        $query = "
            SELECT 
                f.field_name,
                COUNT(st.id) as treatment_count,
                SUM(st.total_cost) as total_cost,
                AVG(st.cost_per_acre) as avg_cost_per_acre,
                COUNT(CASE WHEN st.treatment_type = 'lime' THEN 1 END) as lime_count,
                COUNT(CASE WHEN st.treatment_type = 'gypsum' THEN 1 END) as gypsum_count,
                COUNT(CASE WHEN st.treatment_type = 'compost' THEN 1 END) as compost_count,
                COUNT(CASE WHEN st.treatment_type = 'sulfur' THEN 1 END) as sulfur_count,
                COUNT(CASE WHEN st.treatment_type = 'manure' THEN 1 END) as manure_count,
                MAX(st.application_date) as last_treatment
            FROM 
                soil_treatments st
            JOIN 
                fields f ON st.field_id = f.id
            WHERE 
                st.application_date BETWEEN ? AND ?
        ";
        
        $params = [$date_from, $date_to];
        
        if (!empty($treatment_type)) {
            $query .= " AND st.treatment_type = ?";
            $params[] = $treatment_type;
        }
        
        $query .= "
            GROUP BY 
                f.field_name
            ORDER BY 
                treatment_count DESC
        ";
        
        debug_query($query, $params);
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Field comparison rows: " . count($result));
        return $result;
    } catch(PDOException $e) {
        error_log("Error fetching field comparison: " . $e->getMessage());
        return [];
    }
}

// Function to fetch summary statistics
function getSummaryStats($pdo, $field_id, $treatment_type, $date_from, $date_to) {
    // Convert dates to ensure proper format
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    try {
        $query = "
            SELECT 
                COUNT(*) as treatment_count,
                SUM(total_cost) as total_cost,
                AVG(cost_per_acre) as avg_cost_per_acre,
                COUNT(DISTINCT field_id) as field_count,
                COUNT(DISTINCT treatment_type) as treatment_type_count,
                MAX(application_date) as latest_treatment,
                COUNT(CASE WHEN treatment_type = 'lime' THEN 1 END) as lime_count
            FROM 
                soil_treatments
            WHERE 
                application_date BETWEEN ? AND ?
        ";
        
        $params = [$date_from, $date_to];
        
        if (!empty($field_id)) {
            $query .= " AND field_id = ?";
            $params[] = $field_id;
        }
        
        if (!empty($treatment_type)) {
            $query .= " AND treatment_type = ?";
            $params[] = $treatment_type;
        }
        
        debug_query($query, $params);
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Format the money values
        if ($result['total_cost'] !== null) {
            $result['total_cost_formatted'] = number_format($result['total_cost'], 2);
        } else {
            $result['total_cost_formatted'] = '0.00';
        }
        
        if ($result['avg_cost_per_acre'] !== null) {
            $result['avg_cost_per_acre_formatted'] = number_format($result['avg_cost_per_acre'], 2);
        } else {
            $result['avg_cost_per_acre_formatted'] = '0.00';
        }
        
        error_log("Summary stats result: " . json_encode($result));
        return $result;
    } catch(PDOException $e) {
        error_log("Error fetching summary stats: " . $e->getMessage());
        return [
            'treatment_count' => 0,
            'total_cost' => 0,
            'total_cost_formatted' => '0.00',
            'avg_cost_per_acre' => 0,
            'avg_cost_per_acre_formatted' => '0.00',
            'field_count' => 0,
            'treatment_type_count' => 0,
            'latest_treatment' => null,
            'lime_count' => 0
        ];
    }
}

// Function to fetch monthly treatment counts
function getMonthlyTreatmentCounts($pdo, $field_id, $treatment_type, $date_from, $date_to) {
    // Convert dates to ensure proper format
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    $query = "
        SELECT 
            DATE_FORMAT(application_date, '%Y-%m') as month,
            COUNT(*) as count
        FROM 
            soil_treatments
        WHERE 
            application_date BETWEEN ? AND ?
    ";
    
    $params = [$date_from, $date_to];
    
    if (!empty($field_id)) {
        $query .= " AND field_id = ?";
        $params[] = $field_id;
    }
    
    if (!empty($treatment_type)) {
        $query .= " AND treatment_type = ?";
        $params[] = $treatment_type;
    }
    
    $query .= " GROUP BY DATE_FORMAT(application_date, '%Y-%m') ORDER BY month ASC";
    
    debug_query($query, $params);
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Monthly treatment counts data points found: " . count($result));
        return $result;
    } catch(PDOException $e) {
        error_log("Error fetching monthly treatment counts: " . $e->getMessage());
        return [];
    }
}

// Function to get soil pH improvements after lime applications
function getPHImprovements($pdo, $date_from, $date_to) {
    // Convert dates to ensure proper format
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    try {
        $query = "
            SELECT 
                f.field_name,
                st.application_date,
                sn_before.ph_level as ph_before,
                sn_after.ph_level as ph_after,
                (sn_after.ph_level - sn_before.ph_level) as ph_change
            FROM 
                soil_treatments st
            JOIN 
                fields f ON st.field_id = f.id
            JOIN 
                soil_nutrients sn_before ON st.field_id = sn_before.field_id 
                AND sn_before.test_date = (
                    SELECT MAX(test_date) 
                    FROM soil_nutrients 
                    WHERE field_id = st.field_id AND test_date < st.application_date
                )
            JOIN 
                soil_nutrients sn_after ON st.field_id = sn_after.field_id 
                AND sn_after.test_date = (
                    SELECT MIN(test_date) 
                    FROM soil_nutrients 
                    WHERE field_id = st.field_id AND test_date > st.application_date
                )
            WHERE 
                st.treatment_type = 'lime'
                AND st.application_date BETWEEN ? AND ?
            ORDER BY
                ph_change DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$date_from, $date_to]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("pH improvements data points found: " . count($result));
        return $result;
    } catch(PDOException $e) {
        error_log("Error fetching pH improvements: " . $e->getMessage());
        return [];
    }
}

// Fetch data for reports
$treatment_type_distribution = getTreatmentTypeDistribution($pdo, $filter_field, $filter_date_from, $filter_date_to);
$treatment_cost_trend = getTreatmentCostTrend($pdo, $filter_field, $filter_treatment_type, $filter_date_from, $filter_date_to);
$field_comparison = getFieldTreatmentComparison($pdo, $filter_date_from, $filter_date_to, $filter_treatment_type);
$summary_stats = getSummaryStats($pdo, $filter_field, $filter_treatment_type, $filter_date_from, $filter_date_to);
$monthly_treatment_counts = getMonthlyTreatmentCounts($pdo, $filter_field, $filter_treatment_type, $filter_date_from, $filter_date_to);
$ph_improvements = getPHImprovements($pdo, $filter_date_from, $filter_date_to);

// Prepare treatment type distribution data for chart
$treatment_types_labels = [];
$treatment_types_data = [];
$treatment_types_colors = [
    'lime' => 'rgba(75, 192, 192, 0.7)',
    'gypsum' => 'rgba(54, 162, 235, 0.7)',
    'compost' => 'rgba(153, 102, 255, 0.7)',
    'sulfur' => 'rgba(255, 206, 86, 0.7)',
    'manure' => 'rgba(255, 159, 64, 0.7)',
    'biochar' => 'rgba(255, 99, 132, 0.7)',
    'cover_crop' => 'rgba(75, 192, 192, 0.7)',
    'other' => 'rgba(201, 203, 207, 0.7)'
];
$treatment_types_colors_border = [
    'lime' => 'rgb(75, 192, 192)',
    'gypsum' => 'rgb(54, 162, 235)',
    'compost' => 'rgb(153, 102, 255)',
    'sulfur' => 'rgb(255, 206, 86)',
    'manure' => 'rgb(255, 159, 64)',
    'biochar' => 'rgb(255, 99, 132)',
    'cover_crop' => 'rgb(75, 192, 192)',
    'other' => 'rgb(201, 203, 207)'
];
$treatment_colors_array = [];
$treatment_borders_array = [];

foreach ($treatment_type_distribution as $type) {
    $type_name = isset($treatment_types[$type['treatment_type']]) ? $treatment_types[$type['treatment_type']] : ucfirst($type['treatment_type']);
    $treatment_types_labels[] = $type_name;
    $treatment_types_data[] = $type['count'];
    
    // Add colors
    $color = isset($treatment_types_colors[$type['treatment_type']]) ? $treatment_types_colors[$type['treatment_type']] : 'rgba(201, 203, 207, 0.7)';
    $border = isset($treatment_types_colors_border[$type['treatment_type']]) ? $treatment_types_colors_border[$type['treatment_type']] : 'rgb(201, 203, 207)';
    $treatment_colors_array[] = $color;
    $treatment_borders_array[] = $border;
}

// Prepare treatment cost trend data for chart
$cost_trend_labels = [];
$cost_trend_data = [];
$treatment_count_data = [];

foreach ($treatment_cost_trend as $point) {
    $cost_trend_labels[] = date('M Y', strtotime($point['month'] . '-01'));
    $cost_trend_data[] = floatval($point['total_cost']);
    $treatment_count_data[] = intval($point['treatment_count']);
}

// Prepare monthly treatment counts data
$monthly_labels = [];
$monthly_counts = [];

foreach ($monthly_treatment_counts as $month) {
    $monthly_labels[] = date('M Y', strtotime($month['month'] . '-01'));
    $monthly_counts[] = intval($month['count']);
}

$pageTitle = 'Soil Treatment Reports';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-chart-bar"></i> Soil Treatment Reports</h2>
        <div class="action-buttons">
            
            <a href="soil_treatments.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Treatments
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
                    <label for="treatment_type" class="form-label">Treatment Type</label>
                    <select class="form-select" id="treatment_type" name="treatment_type">
                        <option value="">All Treatment Types</option>
                        <?php foreach ($treatment_types as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($filter_treatment_type == $key) ? 'selected' : ''; ?>>
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
                    <a href="treatment_reports.php" class="btn btn-secondary">Reset Filters</a>
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
                Displaying soil treatment data for <strong><?php echo htmlspecialchars($field_name); ?></strong> 
            <?php else: ?>
                Displaying soil treatment data for <strong>all fields</strong> 
            <?php endif; ?>
            
            <?php if (!empty($filter_treatment_type)): ?>
                (treatment type: <strong><?php echo htmlspecialchars($treatment_types[$filter_treatment_type]); ?></strong>)
            <?php endif; ?>
            
            from <strong><?php echo date('M d, Y', strtotime($filter_date_from)); ?></strong> 
            to <strong><?php echo date('M d, Y', strtotime($filter_date_to)); ?></strong>.
            <br>
            Total treatments: <strong><?php echo $summary_stats['treatment_count']; ?></strong>, 
            Total cost: <strong>$<?php echo $summary_stats['total_cost_formatted']; ?></strong>, 
            Fields treated: <strong><?php echo $summary_stats['field_count']; ?></strong>
        </p>
    </div>

    <!-- Summary Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Treatments</h5>
                    <p class="card-text display-6"><?php echo $summary_stats['treatment_count']; ?></p>
                    <p class="card-text small">Treatments applied in selected period</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Cost</h5>
                    <p class="card-text display-6">$<?php echo $summary_stats['total_cost_formatted']; ?></p>
                    <p class="card-text small">Treatment expenses</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Fields Treated</h5>
                    <p class="card-text display-6"><?php echo $summary_stats['field_count']; ?></p>
                    <p class="card-text small">Number of treated fields</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning">
                <div class="card-body">
                    <h5 class="card-title">Avg. Cost Per Acre</h5>
                    <p class="card-text display-6">$<?php echo $summary_stats['avg_cost_per_acre_formatted']; ?></p>
                    <p class="card-text small">Average treatment cost per acre</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Treatment Type Distribution Chart -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie me-2"></i>Treatment Type Distribution</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($treatment_type_distribution)): ?>
                        <div class="alert alert-info">
                            No treatment type data available for the selected period.
                        </div>
                    <?php else: ?>
                        <canvas id="treatmentTypeChart" height="300"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Monthly Treatment Count Chart -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line me-2"></i>Monthly Treatment Count</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($monthly_treatment_counts)): ?>
                        <div class="alert alert-info">
                            No monthly treatment data available for the selected period.
                        </div>
                    <?php else: ?>
                        <canvas id="monthlyTreatmentChart" height="300"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Treatment Cost Trend Chart -->
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line me-2"></i>Treatment Cost Trend</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($treatment_cost_trend)): ?>
                        <div class="alert alert-info">
                            No treatment cost trend data available for the selected period.
                        </div>
                    <?php else: ?>
                        <canvas id="costTrendChart" height="250"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Field Comparison Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-table me-2"></i>Field Treatment Comparison</h5>
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
                                <th>Treatments</th>
                                <th>Total Cost</th>
                                <th>Avg Cost/Acre</th>
                                <th>Lime</th>
                                <th>Gypsum</th>
                                <th>Compost</th>
                                <th>Sulfur</th>
                                <th>Manure</th>
                                <th>Last Treatment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($field_comparison as $field): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($field['field_name']); ?></td>
                                    <td><?php echo $field['treatment_count']; ?></td>
                                    <td>$<?php echo number_format($field['total_cost'], 2); ?></td>
                                    <td>$<?php echo number_format($field['avg_cost_per_acre'], 2); ?></td>
                                    <td><?php echo $field['lime_count']; ?></td>
                                    <td><?php echo $field['gypsum_count']; ?></td>
                                    <td><?php echo $field['compost_count']; ?></td>
                                    <td><?php echo $field['sulfur_count']; ?></td>
                                    <td><?php echo $field['manure_count']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($field['last_treatment'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- pH Improvement Analysis (after lime applications) -->
    <?php if (!empty($ph_improvements)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-flask me-2"></i>pH Improvement Analysis (Lime Applications)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Application Date</th>
                            <th>pH Before</th>
                            <th>pH After</th>
                            <th>pH Change</th>
                            <th>Effectiveness</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ph_improvements as $improvement): ?>
                            <?php 
                                $ph_change = round($improvement['ph_change'], 1);
                                $effectiveness_class = '';
                                $effectiveness_label = '';
                                
                                if ($ph_change > 1.0) {
                                    $effectiveness_class = 'bg-success text-white';
                                    $effectiveness_label = 'Excellent';
                                } elseif ($ph_change > 0.5) {
                                    $effectiveness_class = 'bg-info text-white';
                                    $effectiveness_label = 'Good';
                                } elseif ($ph_change > 0.1) {
                                    $effectiveness_class = 'bg-warning';
                                    $effectiveness_label = 'Moderate';
                                } elseif ($ph_change <= 0) {
                                    $effectiveness_class = 'bg-danger text-white';
                                    $effectiveness_label = 'Ineffective';
                                } else {
                                    $effectiveness_class = 'bg-light';
                                    $effectiveness_label = 'Minimal';
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($improvement['field_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($improvement['application_date'])); ?></td>
                                <td><?php echo $improvement['ph_before']; ?></td>
                                <td><?php echo $improvement['ph_after']; ?></td>
                                <td class="<?php echo ($ph_change > 0) ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo ($ph_change > 0) ? '+' . $ph_change : $ph_change; ?>
                                </td>
                                <td><span class="badge <?php echo $effectiveness_class; ?>"><?php echo $effectiveness_label; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
    // Treatment Type Distribution Chart
    <?php if (!empty($treatment_type_distribution)): ?>
    var typeDistributionCtx = document.getElementById('treatmentTypeChart').getContext('2d');
    var typeDistributionChart = new Chart(typeDistributionCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($treatment_types_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($treatment_types_data); ?>,
                backgroundColor: <?php echo json_encode($treatment_colors_array); ?>,
                borderColor: <?php echo json_encode($treatment_borders_array); ?>,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var label = context.label || '';
                            var value = context.raw || 0;
                            var total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            var percentage = Math.round((value / total) * 100);
                            return label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // Monthly Treatment Count Chart
    <?php if (!empty($monthly_treatment_counts)): ?>
    var monthlyTreatmentCtx = document.getElementById('monthlyTreatmentChart').getContext('2d');
    var monthlyTreatmentChart = new Chart(monthlyTreatmentCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [{
                label: 'Number of Treatments',
                data: <?php echo json_encode($monthly_counts); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgb(54, 162, 235)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    },
                    title: {
                        display: true,
                        text: 'Number of Treatments'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Month'
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // Treatment Cost Trend Chart
    <?php if (!empty($treatment_cost_trend)): ?>
    var costTrendCtx = document.getElementById('costTrendChart').getContext('2d');
    var costTrendChart = new Chart(costTrendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($cost_trend_labels); ?>,
            datasets: [
                {
                    label: 'Total Cost ($)',
                    data: <?php echo json_encode($cost_trend_data); ?>,
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgb(255, 99, 132)',
                    borderWidth: 2,
                    yAxisID: 'y',
                    tension: 0.1
                },
                {
                    label: 'Number of Treatments',
                    data: <?php echo json_encode($treatment_count_data); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgb(54, 162, 235)',
                    borderWidth: 2,
                    yAxisID: 'y1',
                    tension: 0.1
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
            scales: {
                y: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Cost ($)'
                    }
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        precision: 0
                    },
                    title: {
                        display: true,
                        text: 'Number of Treatments'
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

    // Export to PDF functionality
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
            const dateFrom = document.getElementById('date_from').value || '<?php echo $filter_date_from; ?>';
            const dateTo = document.getElementById('date_to').value || '<?php echo $filter_date_to; ?>';
            const fileName = 'soil_treatment_report_' + dateFrom + '_to_' + dateTo + '.pdf';
            
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
        csvContent += "Field,Treatments,Total Cost,Avg Cost/Acre,Lime,Gypsum,Compost,Sulfur,Manure,Last Treatment\n";
        
        // Get field comparison data from the table
        const table = document.querySelector('table');
        if (table) {
            const rows = table.querySelectorAll('tbody tr');
            if (rows.length > 0) {
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 10) {
                        for (let i = 0; i < cells.length; i++) {
                            // Add cell text and comma
                            csvContent += cells[i].textContent.trim().replace(/,/g, ';') + (i < cells.length - 1 ? ',' : '\n');
                        }
                    }
                });
            } else {
                // If no data, add a row indicating no data
                csvContent += "No data available for the selected period,,,,,,,,,,\n";
            }
        } else {
            // If no table found, add a row indicating no data
            csvContent += "No data available for the selected period,,,,,,,,,,\n";
        }
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "soil_treatment_report_" + new Date().toISOString().slice(0, 10) + ".csv");
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

<?php include 'includes/footer.php'; ?>