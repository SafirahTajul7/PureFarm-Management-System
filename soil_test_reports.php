<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Define default filter variables first to avoid undefined variable warnings
$filter_field = isset($_GET['field']) ? $_GET['field'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '2024-01-01';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '2025-12-31';

error_log("Starting soil test reports page...");

// Test database connection directly
try {
    $check = $pdo->query("SELECT 1")->fetchColumn();
    error_log("Database connection confirmed working");
} catch(PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
}

// Check dates being used
error_log("Date range: $filter_date_from to $filter_date_to");

// Debug function to help identify query issues
function debug_query($query, $params) {
    error_log("Query: $query | Params: " . print_r($params, true));
}

// Check test data directly
try {
    // Check soil_tests data
    $stmt = $pdo->query("SELECT COUNT(*) FROM soil_tests");
    $test_count = $stmt->fetchColumn();
    error_log("Total soil tests found: $test_count");
    
    if ($test_count > 0) {
        // Sample data to see actual content
        $stmt = $pdo->query("SELECT * FROM soil_tests LIMIT 3");
        error_log("Sample soil test data: " . json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)));
    }
    
    // Check field mapping directly
    $stmt = $pdo->query("
        SELECT st.id, st.field_id, f.id AS actual_field_id, f.field_name 
        FROM soil_tests st
        LEFT JOIN fields f ON st.field_id = f.id
        LIMIT 5
    ");
    $mapping = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Field mapping check: " . json_encode($mapping));
    
} catch(PDOException $e) {
    error_log("Error checking test data: " . $e->getMessage());
}

// Fetch all fields for the filter dropdown
try {
    $stmt = $pdo->prepare("SELECT id, field_name FROM fields ORDER BY field_name ASC");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Fields found: " . count($fields));
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

// IMPORTANT: Verify fields and soil tests exist
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fields");
    $stmt->execute();
    $field_count = $stmt->fetchColumn();
    error_log("Total fields in database: $field_count");
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM soil_tests");
    $stmt->execute();
    $test_count = $stmt->fetchColumn();
    error_log("Total soil tests in database: $test_count");
} catch(PDOException $e) {
    error_log("Error checking data: " . $e->getMessage());
}

// Function to fetch average pH trend over time
function getPHTrend($pdo, $field_id, $date_from, $date_to) {
    // Convert dates to ensure proper format
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    $query = "
        SELECT 
            DATE_FORMAT(test_date, '%Y-%m') as month,
            AVG(ph_level) as avg_ph
        FROM 
            soil_tests
        WHERE 
            test_date BETWEEN ? AND ?
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
        error_log("pH trend data points found: " . count($result));
        return $result;
    } catch(PDOException $e) {
        error_log("Error fetching pH trend: " . $e->getMessage());
        return [];
    }
}

// Function to fetch nutrient level distribution
function getNutrientDistribution($pdo, $nutrient, $field_id, $date_from, $date_to) {
    // Convert dates to ensure proper format
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    $query = "
        SELECT 
            {$nutrient}_level as level,
            COUNT(*) as count
        FROM 
            soil_tests
        WHERE 
            test_date BETWEEN ? AND ?
            AND {$nutrient}_level IS NOT NULL
    ";
    
    $params = [$date_from, $date_to];
    
    if (!empty($field_id)) {
        $query .= " AND field_id = ?";
        $params[] = $field_id;
    }
    
    $query .= " GROUP BY {$nutrient}_level";
    
    debug_query($query, $params);
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("$nutrient distribution data points found: " . count($result));
        return $result;
    } catch(PDOException $e) {
        error_log("Error fetching nutrient distribution: " . $e->getMessage());
        return [];
    }
}

// Function to fetch field comparison data
function getFieldComparison($pdo, $date_from, $date_to) {
    // Convert dates to ensure proper format
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    try {
        // First, check if the fields and soil_tests are properly linked
        $stmt = $pdo->query("
            SELECT st.field_id, f.id, f.field_name 
            FROM soil_tests st
            LEFT JOIN fields f ON st.field_id = f.id
            LIMIT 1
        ");
        $check = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Join check: " . json_encode($check));
        
        // If there's a mismatch, we need to use a different approach
        if ($check && ($check['field_id'] != $check['id'] || $check['field_name'] === null)) {
            error_log("Field ID mismatch detected. Using alternate query approach.");
            
            // Get all field IDs from soil_tests
            $stmt = $pdo->query("SELECT DISTINCT field_id FROM soil_tests");
            $field_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get all fields
            $stmt = $pdo->query("SELECT id, field_name FROM fields");
            $fields = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $results = [];
            
            // For each field ID in soil_tests
            foreach ($field_ids as $field_id) {
                // Find a matching field name or use a placeholder
                $field_name = isset($fields[$field_id]) ? $fields[$field_id] : "Field $field_id";
                
                // Get stats for this field
                $stmt = $pdo->prepare("
                    SELECT 
                        ROUND(AVG(ph_level), 1) as avg_ph,
                        COUNT(CASE WHEN nitrogen_level = 'High' THEN 1 END) as high_n,
                        COUNT(CASE WHEN nitrogen_level = 'Medium' THEN 1 END) as medium_n,
                        COUNT(CASE WHEN nitrogen_level = 'Low' THEN 1 END) as low_n,
                        COUNT(CASE WHEN phosphorus_level = 'High' THEN 1 END) as high_p,
                        COUNT(CASE WHEN phosphorus_level = 'Medium' THEN 1 END) as medium_p,
                        COUNT(CASE WHEN phosphorus_level = 'Low' THEN 1 END) as low_p,
                        COUNT(CASE WHEN potassium_level = 'High' THEN 1 END) as high_k,
                        COUNT(CASE WHEN potassium_level = 'Medium' THEN 1 END) as medium_k,
                        COUNT(CASE WHEN potassium_level = 'Low' THEN 1 END) as low_k,
                        COUNT(*) as test_count
                    FROM 
                        soil_tests
                    WHERE 
                        field_id = ? 
                        AND test_date BETWEEN ? AND ?
                ");
                $stmt->execute([$field_id, $date_from, $date_to]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($data && $data['test_count'] > 0) {
                    $data['field_name'] = $field_name;
                    $results[] = $data;
                }
            }
            
            return $results;
        }
        
        // Original query if no mismatch detected
        $stmt = $pdo->prepare("
            SELECT 
                f.field_name,
                ROUND(AVG(st.ph_level), 1) as avg_ph,
                COUNT(CASE WHEN st.nitrogen_level = 'High' THEN 1 END) as high_n,
                COUNT(CASE WHEN st.nitrogen_level = 'Medium' THEN 1 END) as medium_n,
                COUNT(CASE WHEN st.nitrogen_level = 'Low' THEN 1 END) as low_n,
                COUNT(CASE WHEN st.phosphorus_level = 'High' THEN 1 END) as high_p,
                COUNT(CASE WHEN st.phosphorus_level = 'Medium' THEN 1 END) as medium_p,
                COUNT(CASE WHEN st.phosphorus_level = 'Low' THEN 1 END) as low_p,
                COUNT(CASE WHEN st.potassium_level = 'High' THEN 1 END) as high_k,
                COUNT(CASE WHEN st.potassium_level = 'Medium' THEN 1 END) as medium_k,
                COUNT(CASE WHEN st.potassium_level = 'Low' THEN 1 END) as low_k,
                COUNT(*) as test_count
            FROM 
                soil_tests st
            JOIN 
                fields f ON st.field_id = f.id
            WHERE 
                st.test_date BETWEEN ? AND ?
            GROUP BY 
                f.field_name
            HAVING 
                test_count > 0
            ORDER BY 
                f.field_name
        ");
        $stmt->execute([$date_from, $date_to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching field comparison: " . $e->getMessage());
        return [];
    }
}

// Function to fetch summary statistics
function getSummaryStats($pdo, $field_id, $date_from, $date_to) {
    // Convert dates to ensure proper format
    $date_from = date('Y-m-d', strtotime($date_from));
    $date_to = date('Y-m-d', strtotime($date_to));
    
    try {
        $query = "
            SELECT 
                ROUND(AVG(ph_level), 1) as avg_ph,
                MIN(ph_level) as min_ph,
                MAX(ph_level) as max_ph,
                COUNT(*) as test_count,
                COUNT(CASE WHEN ph_level < 6.0 THEN 1 END) as low_ph_count,
                COUNT(CASE WHEN ph_level > 7.5 THEN 1 END) as high_ph_count,
                COUNT(DISTINCT field_id) as field_count
            FROM 
                soil_tests
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
        error_log("Error fetching summary stats: " . $e->getMessage());
        return [
            'avg_ph' => 'N/A',
            'min_ph' => 'N/A',
            'max_ph' => 'N/A',
            'test_count' => 0,
            'low_ph_count' => 0,
            'high_ph_count' => 0,
            'field_count' => 0
        ];
    }
}

// Insert test data if none exists (for demo purposes)
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM soil_tests");
    $stmt->execute();
    $test_count = $stmt->fetchColumn();
    
    if ($test_count == 0 && count($fields) > 0) {
        error_log("No soil tests found. Inserting sample data for demonstration.");
        
        // Sample test dates
        $test_dates = [
            '2024-04-20',
            '2024-04-15',
            '2024-03-10',
            '2024-02-20',
            '2024-01-25'
        ];
        
        // Sample nutrient levels
        $levels = ['Low', 'Medium', 'High'];
        
        // Insert sample data for each field
        foreach ($fields as $field) {
            foreach ($test_dates as $date) {
                $ph = round(rand(55, 80) / 10, 1); // pH between 5.5 and 8.0
                $moisture = rand(20, 40);
                $temp = rand(15, 25);
                $organic = round(rand(25, 50) / 10, 1);
                $n_level = $levels[array_rand($levels)];
                $p_level = $levels[array_rand($levels)];
                $k_level = $levels[array_rand($levels)];
                
                $stmt = $pdo->prepare("
                    INSERT INTO soil_tests 
                    (field_id, test_date, ph_level, moisture_percentage, temperature, 
                    nitrogen_level, phosphorus_level, potassium_level, organic_matter, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $field['id'], 
                    $date, 
                    $ph, 
                    $moisture, 
                    $temp,
                    $n_level,
                    $p_level,
                    $k_level,
                    $organic,
                    'Sample test data'
                ]);
            }
        }
        
        error_log("Sample soil test data inserted successfully.");
    }
} catch(PDOException $e) {
    error_log("Error checking/inserting test data: " . $e->getMessage());
}

// Fetch data for reports
$ph_trend = getPHTrend($pdo, $filter_field, $filter_date_from, $filter_date_to);
$nitrogen_distribution = getNutrientDistribution($pdo, 'nitrogen', $filter_field, $filter_date_from, $filter_date_to);
$phosphorus_distribution = getNutrientDistribution($pdo, 'phosphorus', $filter_field, $filter_date_from, $filter_date_to);
$potassium_distribution = getNutrientDistribution($pdo, 'potassium', $filter_field, $filter_date_from, $filter_date_to);
$field_comparison = getFieldComparison($pdo, $filter_date_from, $filter_date_to);
$summary_stats = getSummaryStats($pdo, $filter_field, $filter_date_from, $filter_date_to);

// Prepare data for chart.js
$ph_trend_labels = [];
$ph_trend_data = [];

foreach ($ph_trend as $point) {
    $ph_trend_labels[] = date('M Y', strtotime($point['month'] . '-01'));
    $ph_trend_data[] = $point['avg_ph'];
}

// Prepare nutrient distribution data
function prepareNutrientData($distribution) {
    $result = [
        'Low' => 0,
        'Medium' => 0,
        'High' => 0
    ];
    
    foreach ($distribution as $level) {
        if (isset($result[$level['level']])) {
            $result[$level['level']] = intval($level['count']);
        }
    }
    
    return $result;
}

$nitrogen_data = prepareNutrientData($nitrogen_distribution);
$phosphorus_data = prepareNutrientData($phosphorus_distribution);
$potassium_data = prepareNutrientData($potassium_distribution);

$pageTitle = 'Soil Test Reports';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-bar"></i> Soil Test Reports</h2>
        <div class="action-buttons">
            <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="soil_tests.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Soil Tests
            </a>
        </div>
    </div>

    <!-- Filter Collapse -->
    <div class="collapse mb-4 show" id="filterCollapse">
        <div class="card card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
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
                <div class="col-md-4">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $filter_date_from; ?>">
                </div>
                <div class="col-md-4">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $filter_date_to; ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="soil_test_reports.php" class="btn btn-secondary">Reset Filters</a>
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
                Displaying soil test data for <strong><?php echo htmlspecialchars($field_name); ?></strong> 
            <?php else: ?>
                Displaying soil test data for <strong>all fields</strong> 
            <?php endif; ?>
            from <strong><?php echo date('M d, Y', strtotime($filter_date_from)); ?></strong> 
            to <strong><?php echo date('M d, Y', strtotime($filter_date_to)); ?></strong>.
            <br>
            Total tests: <strong><?php echo $summary_stats['test_count']; ?></strong>, 
            Average pH: <strong><?php echo $summary_stats['avg_ph']; ?></strong>, 
            Fields with low pH: <strong><?php echo $summary_stats['low_ph_count']; ?></strong>
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
            <div class="card <?php echo ($summary_stats['low_ph_count'] > 0) ? 'bg-warning' : 'bg-light'; ?>">
                <div class="card-body">
                    <h5 class="card-title">Fields with Low pH</h5>
                    <p class="card-text display-6"><?php echo $summary_stats['low_ph_count']; ?></p>
                    <p class="card-text small">pH below 6.0</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- pH Trend Chart -->
        <div class="col-md-8">
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

        <!-- Nutrient Distribution -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie me-2"></i>Nutrient Distribution</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($nitrogen_distribution) && empty($phosphorus_distribution) && empty($potassium_distribution)): ?>
                        <div class="alert alert-info">
                            No nutrient data available for the selected period.
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <h6>Nitrogen Levels</h6>
                            <canvas id="nitrogenChart" height="100"></canvas>
                        </div>
                        <div class="mb-3">
                            <h6>Phosphorus Levels</h6>
                            <canvas id="phosphorusChart" height="100"></canvas>
                        </div>
                        <div>
                            <h6>Potassium Levels</h6>
                            <canvas id="potassiumChart" height="100"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Field Comparison Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-table me-2"></i>Field Comparison</h5>
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
                                <th>Avg pH</th>
                                <th colspan="3" class="text-center">Nitrogen</th>
                                <th colspan="3" class="text-center">Phosphorus</th>
                                <th colspan="3" class="text-center">Potassium</th>
                            </tr>
                            <tr>
                                <th></th>
                                <th></th>
                                <th></th>
                                <th class="text-center bg-success text-white">High</th>
                                <th class="text-center bg-warning">Medium</th>
                                <th class="text-center bg-danger text-white">Low</th>
                                <th class="text-center bg-success text-white">High</th>
                                <th class="text-center bg-warning">Medium</th>
                                <th class="text-center bg-danger text-white">Low</th>
                                <th class="text-center bg-success text-white">High</th>
                                <th class="text-center bg-warning">Medium</th>
                                <th class="text-center bg-danger text-white">Low</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($field_comparison as $field): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($field['field_name']); ?></td>
                                    <td><?php echo $field['test_count']; ?></td>
                                    <td>
                                        <?php // Enhanced pH Trend Chart Configuration
                                            $ph_class = '';
                                            if ($field['avg_ph'] < 6.0) $ph_class = 'text-danger';
                                            elseif ($field['avg_ph'] > 7.5) $ph_class = 'text-warning';
                                            echo '<span class="' . $ph_class . '">' . $field['avg_ph'] . '</span>';
                                        ?>
                                    </td>
                                    <td class="text-center"><?php echo $field['high_n']; ?></td>
                                    <td class="text-center"><?php echo $field['medium_n']; ?></td>
                                    <td class="text-center"><?php echo $field['low_n']; ?></td>
                                    <td class="text-center"><?php echo $field['high_p']; ?></td>
                                    <td class="text-center"><?php echo $field['medium_p']; ?></td>
                                    <td class="text-center"><?php echo $field['low_p']; ?></td>
                                    <td class="text-center"><?php echo $field['high_k']; ?></td>
                                    <td class="text-center"><?php echo $field['medium_k']; ?></td>
                                    <td class="text-center"><?php echo $field['low_k']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
    
    <!-- Debug Data Section (Admin Only) - Styled to be less intrusive -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">
                <button class="btn btn-link text-white collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#debugData" aria-expanded="false" aria-controls="debugData">
                    <i class="fas fa-bug me-2"></i>Debug Data (Admin Only)
                </button>
            </h5>
        </div>
        <div id="debugData" class="collapse">
            <div class="card-body">
                <h6>Raw Soil Test Data (First 5 records)</h6>
                <!-- PHP code to display raw test data here -->
                
                <h6 class="mt-4">Data Queries Used</h6>
                <div class="alert alert-info">
                    <p><strong>Date Range:</strong> <?php echo $filter_date_from; ?> to <?php echo $filter_date_to; ?></p>
                    <p><strong>Field Filter:</strong> <?php echo !empty($filter_field) ? $filter_field : 'All Fields'; ?></p>
                    <p><strong>pH Trend Points:</strong> <?php echo count($ph_trend); ?></p>
                    <p><strong>Comparison Rows:</strong> <?php echo count($field_comparison); ?></p>
                </div>
            </div>
        </div>
    </div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Use this specific version of jsPDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/1.5.3/jspdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<!-- Add this script to verify libraries are loaded -->
<script>
  // Check if libraries are loaded correctly when page is ready
  document.addEventListener('DOMContentLoaded', function() {
    console.log('Chart.js loaded:', typeof Chart !== 'undefined');
    console.log('jsPDF loaded:', typeof jsPDF !== 'undefined');
    console.log('html2canvas loaded:', typeof html2canvas !== 'undefined');
    
    if (typeof jsPDF === 'undefined') {
      console.error('jsPDF library not loaded! PDF export will not work.');
    }
    
    if (typeof html2canvas === 'undefined') {
      console.error('html2canvas library not loaded! PDF export will not work.');
    }
  });
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // pH Trend Chart
    <?php if (!empty($ph_trend)): ?>
    var phTrendCtx = document.getElementById('phTrendChart').getContext('2d');
    var phTrendChart = new Chart(phTrendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($ph_trend_labels); ?>,
            datasets: [{
                label: 'Average pH',
                data: <?php echo json_encode($ph_trend_data); ?>,
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

    // Nutrient Distribution Charts
    <?php if (!empty($nitrogen_distribution)): ?>
    var nitrogenCtx = document.getElementById('nitrogenChart').getContext('2d');
    var nitrogenChart = new Chart(nitrogenCtx, {
        type: 'bar',
        data: {
            labels: ['Low', 'Medium', 'High'],
            datasets: [{
                data: [
                    <?php echo $nitrogen_data['Low']; ?>, 
                    <?php echo $nitrogen_data['Medium']; ?>, 
                    <?php echo $nitrogen_data['High']; ?>
                ],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(255, 205, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)'
                ],
                borderColor: [
                    'rgb(255, 99, 132)',
                    'rgb(255, 205, 86)',
                    'rgb(75, 192, 192)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    <?php endif; ?>

    <?php if (!empty($phosphorus_distribution)): ?>
    var phosphorusCtx = document.getElementById('phosphorusChart').getContext('2d');
    var phosphorusChart = new Chart(phosphorusCtx, {
        type: 'bar',
        data: {
            labels: ['Low', 'Medium', 'High'],
            datasets: [{
                data: [
                    <?php echo $phosphorus_data['Low']; ?>, 
                    <?php echo $phosphorus_data['Medium']; ?>, 
                    <?php echo $phosphorus_data['High']; ?>
                ],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(255, 205, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)'
                ],
                borderColor: [
                    'rgb(255, 99, 132)',
                    'rgb(255, 205, 86)',
                    'rgb(75, 192, 192)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    <?php endif; ?>

    <?php if (!empty($potassium_distribution)): ?>
    var potassiumCtx = document.getElementById('potassiumChart').getContext('2d');
    var potassiumChart = new Chart(potassiumCtx, {
        type: 'bar',
        data: {
            labels: ['Low', 'Medium', 'High'],
            datasets: [{
                data: [
                    <?php echo $potassium_data['Low']; ?>, 
                    <?php echo $potassium_data['Medium']; ?>, 
                    <?php echo $potassium_data['High']; ?>
                ],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(255, 205, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)'
                ],
                borderColor: [
                    'rgb(255, 99, 132)',
                    'rgb(255, 205, 86)',
                    'rgb(75, 192, 192)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
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

    // Export buttons functionality
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
            const fileName = 'soil_test_report_' + dateFrom + '_to_' + dateTo + '.pdf';
            
            // Create directly with the global jsPDF class
            const doc = new jsPDF();
            
            // Main content to capture - this avoids any complex section by section approach
            const content = document.querySelector('.main-content');
            
            // Temporarily hide elements we don't want in the PDF
            const elementsToHide = [
                ...document.querySelectorAll('.action-buttons'),
                ...document.querySelectorAll('#filterCollapse'),
                ...document.querySelectorAll('#debugData'),
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
        csvContent += "Field,Tests,Avg pH,Nitrogen High,Nitrogen Medium,Nitrogen Low,Phosphorus High,Phosphorus Medium,Phosphorus Low,Potassium High,Potassium Medium,Potassium Low\n";
        
        // Get field comparison data from the table
        const table = document.querySelector('table');
        if (table) {
            const rows = table.querySelectorAll('tbody tr');
            if (rows.length > 0) {
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 12) {
                        for (let i = 0; i < cells.length; i++) {
                            // Add cell text and comma
                            csvContent += cells[i].textContent.trim().replace(/,/g, ';') + (i < cells.length - 1 ? ',' : '\n');
                        }
                    }
                });
            } else {
                // If no data, add a row indicating no data
                csvContent += "No data available for the selected period,,,,,,,,,,,\n";
            }
        } else {
            // If no table found, add a row indicating no data
            csvContent += "No data available for the selected period,,,,,,,,,,,\n";
        }
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "soil_test_report_" + new Date().toISOString().slice(0, 10) + ".csv");
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
    #debugData {
        display: none !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>