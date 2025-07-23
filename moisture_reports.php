<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is admin
auth()->checkAdmin();

// Handle form submissions for filtering
$filter_field = isset($_GET['field']) ? $_GET['field'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'moisture_trend';

// Fetch all fields for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, field_name FROM fields ORDER BY field_name ASC");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

// Function to get moisture data for reports
function getMoistureData($pdo, $filter_field, $filter_date_from, $filter_date_to) {
    $query = "
        SELECT 
            sm.id, 
            sm.field_id, 
            f.field_name, 
            sm.reading_date, 
            sm.moisture_percentage, 
            sm.reading_depth, 
            sm.reading_method, 
            sm.notes
        FROM 
            soil_moisture sm
        JOIN 
            fields f ON sm.field_id = f.id
        WHERE 
            sm.reading_date BETWEEN ? AND ?
    ";
    
    $params = [$filter_date_from, $filter_date_to];
    
    if (!empty($filter_field)) {
        $query .= " AND sm.field_id = ?";
        $params[] = $filter_field;
    }
    
    $query .= " ORDER BY sm.reading_date ASC, f.field_name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get moisture data based on filters
try {
    $moisture_data = getMoistureData($pdo, $filter_field, $filter_date_from, $filter_date_to);
    
    // Prepare data for charts
    $chart_fields = [];
    $all_dates = [];
    $field_data = [];
    
    // Process data for charts
    foreach($moisture_data as $record) {
        $date = date('Y-m-d', strtotime($record['reading_date']));
        $field_name = $record['field_name'];
        $moisture = floatval($record['moisture_percentage']);
        
        if (!in_array($date, $all_dates)) {
            $all_dates[] = $date;
        }
        
        if (!in_array($field_name, $chart_fields)) {
            $chart_fields[] = $field_name;
            $field_data[$field_name] = [];
        }
        
        $field_data[$field_name][$date] = $moisture;
    }
    
    // Sort dates chronologically
    sort($all_dates);
    
    // Create formatted dates for display
    $display_dates = array_map(function($date) {
        return date('M d', strtotime($date));
    }, $all_dates);
    
    // Fill in missing dates with null values for each field
    foreach($chart_fields as $field) {
        $field_values = [];
        foreach($all_dates as $date) {
            $field_values[] = isset($field_data[$field][$date]) ? $field_data[$field][$date] : null;
        }
        $field_data[$field] = $field_values;
    }
    
    // Calculate statistics
    $stats = [];
    foreach($chart_fields as $field) {
        $field_values = array_filter($field_data[$field], function($value) {
            return $value !== null;
        });
        
        $stats[$field] = [
            'avg' => !empty($field_values) ? round(array_sum($field_values) / count($field_values), 1) : 'N/A',
            'min' => !empty($field_values) ? round(min($field_values), 1) : 'N/A',
            'max' => !empty($field_values) ? round(max($field_values), 1) : 'N/A',
            'latest' => !empty($field_values) ? round(end($field_values), 1) : 'N/A',
            'count' => count($field_values)
        ];
    }
    
    // Convert to JSON for JavaScript
    $dates_json = json_encode($display_dates);
    $all_dates_json = json_encode($all_dates);
    $chart_fields_json = json_encode($chart_fields);
    
    // Create datasets for charts
    $colors = [
        'East Acres' => '#36a2eb', // Blue
        'North Field' => '#4bc0c0', // Teal
        'South Plot' => '#ff6384', // Pink
        'West Field' => '#ffcd56', // Yellow
        'Central Plot' => '#9966ff' // Purple
    ];
    
    $line_datasets = [];
    foreach($chart_fields as $field) {
        $color = isset($colors[$field]) ? $colors[$field] : '#777777';
        
        $line_datasets[] = [
            'label' => $field,
            'data' => $field_data[$field],
            'borderColor' => $color,
            'backgroundColor' => $color . '33', // Add transparency
            'borderWidth' => 2,
            'tension' => 0.3,
            'fill' => false
        ];
    }
    
    $line_datasets_json = json_encode($line_datasets);
    
    // Create data for bar chart (average by field)
    $bar_data = [];
    foreach($stats as $field => $field_stats) {
        $bar_data[] = [
            'field' => $field,
            'avg' => is_numeric($field_stats['avg']) ? $field_stats['avg'] : 0
        ];
    }
    
    $bar_data_json = json_encode($bar_data);
    
    // Has data flag
    $has_chart_data = !empty($all_dates);
    
} catch(PDOException $e) {
    error_log("Error processing moisture data: " . $e->getMessage());
    $has_chart_data = false;
    $dates_json = json_encode([]);
    $line_datasets_json = json_encode([]);
    $bar_data_json = json_encode([]);
}

// Set page title and include header
$pageTitle = 'Moisture Reports';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-chart-bar"></i> Soil Moisture Reports</h2>
        <div class="action-buttons">
            <a href="soil_moisture.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Moisture
            </a>
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                <i class="fas fa-filter"></i> Report Options
            </button>
            <?php if ($has_chart_data): ?>
            <div class="dropdown d-inline-block">
                <button class="btn btn-success dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-download"></i> Export
                </button>
                <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                    <li><a class="dropdown-item" href="#" id="exportPDF"><i class="fas fa-file-pdf"></i> Export as PDF</a></li>
                    <li><a class="dropdown-item" href="#" id="exportCSV"><i class="fas fa-file-csv"></i> Export as CSV</a></li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Options -->
    <div class="collapse mb-4 <?php echo ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['report_type'])) ? 'show' : ''; ?>" id="filterCollapse">
        <div class="card card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="report_type" class="form-label">Report Type</label>
                    <select class="form-select" id="report_type" name="report_type">
                        <option value="moisture_trend" <?php echo ($report_type == 'moisture_trend') ? 'selected' : ''; ?>>Moisture Trend</option>
                        <option value="field_comparison" <?php echo ($report_type == 'field_comparison') ? 'selected' : ''; ?>>Field Comparison</option>
                        <option value="monthly_summary" <?php echo ($report_type == 'monthly_summary') ? 'selected' : ''; ?>>Monthly Summary</option>
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
                    <a href="moisture_reports.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['report_type'])): ?>
        <!-- Report Content Based on Type -->
        <?php if ($report_type == 'moisture_trend'): ?>
            <!-- Moisture Trend Report -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Moisture Trend Report</h4>
                    <p class="mb-0 text-muted">
                        <?php echo date('F d, Y', strtotime($filter_date_from)); ?> to 
                        <?php echo date('F d, Y', strtotime($filter_date_to)); ?>
                        <?php echo (!empty($filter_field)) ? ' for ' . htmlspecialchars($fields[array_search($filter_field, array_column($fields, 'id'))]['field_name']) : ' for All Fields'; ?>
                    </p>
                </div>
                <div class="card-body">
                    <?php if ($has_chart_data): ?>
                        <div class="moisture-trend-chart-container mb-4">
                            <canvas id="moistureTrendChart" style="height: 400px;"></canvas>
                        </div>
                        
                        <div class="table-responsive mt-4">
                            <table class="table table-bordered table-striped">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Field</th>
                                        <th>Average Moisture</th>
                                        <th>Minimum</th>
                                        <th>Maximum</th>
                                        <th>Latest Reading</th>
                                        <th>Number of Readings</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($stats as $field => $field_stats): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($field); ?></td>
                                        <td><?php echo is_numeric($field_stats['avg']) ? $field_stats['avg'] . '%' : $field_stats['avg']; ?></td>
                                        <td><?php echo is_numeric($field_stats['min']) ? $field_stats['min'] . '%' : $field_stats['min']; ?></td>
                                        <td><?php echo is_numeric($field_stats['max']) ? $field_stats['max'] . '%' : $field_stats['max']; ?></td>
                                        <td><?php echo is_numeric($field_stats['latest']) ? $field_stats['latest'] . '%' : $field_stats['latest']; ?></td>
                                        <td><?php echo $field_stats['count']; ?></td>
                                        <td>
                                            <?php 
                                                if (is_numeric($field_stats['latest'])) {
                                                    if ($field_stats['latest'] < 30) {
                                                        echo '<span class="badge bg-danger">Low</span>';
                                                    } elseif ($field_stats['latest'] > 70) {
                                                        echo '<span class="badge bg-primary">High</span>';
                                                    } else {
                                                        echo '<span class="badge bg-success">Optimal</span>';
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-secondary">Unknown</span>';
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No moisture data available for the selected date range and field. Please adjust your filters.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($report_type == 'field_comparison'): ?>
            <!-- Field Comparison Report -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Field Comparison Report</h4>
                    <p class="mb-0 text-muted">
                        <?php echo date('F d, Y', strtotime($filter_date_from)); ?> to 
                        <?php echo date('F d, Y', strtotime($filter_date_to)); ?>
                    </p>
                </div>
                <div class="card-body">
                    <?php if ($has_chart_data): ?>
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="fieldComparisonChart" style="height: 400px;"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">Observations</div>
                                    <div class="card-body">
                                        <h5>Field Comparison Analysis</h5>
                                        <ul class="list-group list-group-flush mb-3">
                                            <?php foreach($stats as $field => $field_stats): ?>
                                                <?php if (is_numeric($field_stats['avg'])): ?>
                                                    <li class="list-group-item">
                                                        <strong><?php echo htmlspecialchars($field); ?>:</strong> 
                                                        <?php 
                                                            if ($field_stats['avg'] < 30) {
                                                                echo 'Below optimal range. Consider irrigation.';
                                                            } elseif ($field_stats['avg'] > 70) {
                                                                echo 'Above optimal range. Consider drainage.';
                                                            } else {
                                                                echo 'Within optimal moisture range.';
                                                            }
                                                        ?>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                        
                                        <h5>Recommendations</h5>
                                        <div class="alert alert-info">
                                            <?php
                                                $low_fields = array_filter($stats, function($s) {
                                                    return is_numeric($s['avg']) && $s['avg'] < 30;
                                                });
                                                
                                                $high_fields = array_filter($stats, function($s) {
                                                    return is_numeric($s['avg']) && $s['avg'] > 70;
                                                });
                                                
                                                if (count($low_fields) > 0) {
                                                    echo '<p><i class="fas fa-exclamation-triangle"></i> Fields requiring irrigation: ' . 
                                                        implode(', ', array_keys($low_fields)) . '.</p>';
                                                }
                                                
                                                if (count($high_fields) > 0) {
                                                    echo '<p><i class="fas fa-tint-slash"></i> Fields with excess moisture: ' . 
                                                        implode(', ', array_keys($high_fields)) . '.</p>';
                                                }
                                                
                                                if (count($low_fields) == 0 && count($high_fields) == 0) {
                                                    echo '<p><i class="fas fa-check-circle"></i> All fields are within optimal moisture ranges.</p>';
                                                }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No moisture data available for the selected date range. Please adjust your filters.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($report_type == 'monthly_summary'): ?>
            <!-- Monthly Summary Report -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Monthly Summary Report</h4>
                    <p class="mb-0 text-muted">
                        <?php echo date('F Y', strtotime($filter_date_from)); ?> to 
                        <?php echo date('F Y', strtotime($filter_date_to)); ?>
                        <?php echo (!empty($filter_field)) ? ' for ' . htmlspecialchars($fields[array_search($filter_field, array_column($fields, 'id'))]['field_name']) : ' for All Fields'; ?>
                    </p>
                </div>
                <div class="card-body">
                    <?php if ($has_chart_data): ?>
                        <!-- Group data by month -->
                        <?php
                            $monthly_data = [];
                            foreach($moisture_data as $record) {
                                $month = date('Y-m', strtotime($record['reading_date']));
                                $field_name = $record['field_name'];
                                
                                if (!isset($monthly_data[$month][$field_name])) {
                                    $monthly_data[$month][$field_name] = [
                                        'readings' => [],
                                        'count' => 0
                                    ];
                                }
                                
                                $monthly_data[$month][$field_name]['readings'][] = $record['moisture_percentage'];
                                $monthly_data[$month][$field_name]['count']++;
                            }
                            
                            // Calculate monthly averages
                            $monthly_avg = [];
                            foreach($monthly_data as $month => $fields) {
                                $monthly_avg[$month] = [];
                                foreach($fields as $field => $data) {
                                    $monthly_avg[$month][$field] = round(array_sum($data['readings']) / count($data['readings']), 1);
                                }
                            }
                        ?>
                        
                        <div class="moisture-monthly-chart-container mb-4">
                            <canvas id="moistureMonthlyChart" style="height: 400px;"></canvas>
                        </div>
                        
                        <div class="table-responsive mt-4">
                            <table class="table table-bordered table-striped">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Month</th>
                                        <?php foreach($chart_fields as $field): ?>
                                            <th><?php echo htmlspecialchars($field); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($monthly_avg as $month => $fields): ?>
                                    <tr>
                                        <td><?php echo date('F Y', strtotime($month . '-01')); ?></td>
                                        <?php foreach($chart_fields as $field): ?>
                                            <td>
                                                <?php 
                                                    if (isset($fields[$field])) {
                                                        echo $fields[$field] . '%';
                                                        
                                                        if ($fields[$field] < 30) {
                                                            echo ' <span class="badge bg-danger">Low</span>';
                                                        } elseif ($fields[$field] > 70) {
                                                            echo ' <span class="badge bg-primary">High</span>';
                                                        } else {
                                                            echo ' <span class="badge bg-success">Optimal</span>';
                                                        }
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No moisture data available for the selected date range and field. Please adjust your filters.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    
    <?php else: ?>
        <!-- Report Type Selection -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card report-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title"><i class="fas fa-chart-line mb-3 fa-3x text-primary"></i></h5>
                        <h6 class="card-subtitle mb-2">Moisture Trend Report</h6>
                        <p class="card-text">Analyze moisture trends over time for selected fields. View historical patterns and identify moisture issues.</p>
                        <a href="moisture_reports.php?report_type=moisture_trend&date_from=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-primary mt-3">Generate Report</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card report-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title"><i class="fas fa-chart-bar mb-3 fa-3x text-success"></i></h5>
                        <h6 class="card-subtitle mb-2">Field Comparison Report</h6>
                        <p class="card-text">Compare moisture levels across different fields. Identify which fields need irrigation or have excess moisture.</p>
                        <a href="moisture_reports.php?report_type=field_comparison&date_from=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-success mt-3">Generate Report</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card report-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title"><i class="fas fa-calendar-alt mb-3 fa-3x text-info"></i></h5>
                        <h6 class="card-subtitle mb-2">Monthly Summary Report</h6>
                        <p class="card-text">Get monthly averages of moisture levels. Track seasonal patterns and plan irrigation schedules.</p>
                        <a href="moisture_reports.php?report_type=monthly_summary&date_from=<?php echo date('Y-m-d', strtotime('-6 months')); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-info mt-3">Generate Report</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.report-card {
    transition: transform 0.3s ease-in-out;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.report-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
}
</style>

<!-- Add Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Add jsPDF for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Date range validation for filters
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

    <?php if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['report_type']) && $has_chart_data): ?>
        <?php if ($report_type == 'moisture_trend'): ?>
            // Moisture Trend Chart
            const trendChart = document.getElementById('moistureTrendChart');
            if (trendChart) {
                const ctx = trendChart.getContext('2d');
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo $dates_json; ?>,
                        datasets: <?php echo $line_datasets_json; ?>
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Soil Moisture Trend',
                                font: {
                                    size: 16
                                }
                            },
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + 
                                            (context.raw !== null ? context.raw + '%' : 'No data');
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Soil Moisture (%)'
                                },
                                min: 0,
                                max: 100
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
            }
        <?php elseif ($report_type == 'field_comparison'): ?>
            // Field Comparison Chart
            const comparisonChart = document.getElementById('fieldComparisonChart');
            if (comparisonChart) {
                const ctx = comparisonChart.getContext('2d');
                
                // Create data for bar chart
                const barLabels = <?php echo json_encode(array_keys($stats)); ?>;
                const barData = barLabels.map(field => {
                    return <?php echo json_encode(array_combine(array_keys($stats), array_column($stats, 'avg'))); ?>[field] || 0;
                });
                
                // Define colors for each field
                const barColors = barLabels.map(field => {
                    const colors = <?php echo json_encode($colors); ?>;
                    return colors[field] || '#777777';
                });
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: barLabels,
                        datasets: [{
                            label: 'Average Moisture (%)',
                            data: barData,
                            backgroundColor: barColors,
                            borderColor: barColors.map(color => color.replace('33', '')),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Average Moisture by Field',
                                font: {
                                    size: 16
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Average Moisture: ' + context.raw + '%';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Soil Moisture (%)'
                                },
                                min: 0,
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Field'
                                }
                            }
                        }
                    }
                });
            }
            <?php elseif ($report_type == 'monthly_summary'): ?>
            // Monthly Summary Chart
            const monthlyChart = document.getElementById('moistureMonthlyChart');
            if (monthlyChart) {
                const ctx = monthlyChart.getContext('2d');
                
                // Prepare monthly data for chart
                const months = Object.keys(<?php echo json_encode($monthly_avg); ?>).map(month => {
                    return month.replace('-', ' ');
                });
                
                const datasets = [];
                <?php foreach($chart_fields as $field): ?>
                const <?php echo str_replace(' ', '_', $field); ?>_data = [];
                
                months.forEach(month => {
                    const monthData = <?php echo json_encode($monthly_avg); ?>[month.replace(' ', '-')];
                    if (monthData && monthData['<?php echo $field; ?>']) {
                        <?php echo str_replace(' ', '_', $field); ?>_data.push(monthData['<?php echo $field; ?>']);
                    } else {
                        <?php echo str_replace(' ', '_', $field); ?>_data.push(null);
                    }
                });
                
                datasets.push({
                    label: '<?php echo $field; ?>',
                    data: <?php echo str_replace(' ', '_', $field); ?>_data,
                    backgroundColor: '<?php echo isset($colors[$field]) ? $colors[$field] : '#777777'; ?>33',
                    borderColor: '<?php echo isset($colors[$field]) ? $colors[$field] : '#777777'; ?>',
                    borderWidth: 2
                });
                <?php endforeach; ?>
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: months.map(month => {
                            const [year, monthNum] = month.split(' ');
                            const date = new Date(year, monthNum - 1, 1);
                            return date.toLocaleString('default', { month: 'long', year: 'numeric' });
                        }),
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Monthly Average Soil Moisture',
                                font: {
                                    size: 16
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + 
                                            (context.raw !== null ? context.raw + '%' : 'No data');
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Average Soil Moisture (%)'
                                },
                                min: 0,
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
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
            }
        <?php endif; ?>
        
        // Export functionality
        const exportPDF = document.getElementById('exportPDF');
        const exportCSV = document.getElementById('exportCSV');
        
        if (exportPDF) {
            exportPDF.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Initialize jsPDF
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                
                // Add title
                doc.setFontSize(16);
                doc.text('Soil Moisture Report', 14, 15);
                
                // Add subtitle
                doc.setFontSize(12);
                doc.text('<?php echo $report_type == "moisture_trend" ? "Moisture Trend Report" : ($report_type == "field_comparison" ? "Field Comparison Report" : "Monthly Summary Report"); ?>', 14, 22);
                doc.text('<?php echo date('F d, Y', strtotime($filter_date_from)); ?> to <?php echo date('F d, Y', strtotime($filter_date_to)); ?>', 14, 29);
                
                // Add statistics table
                const tableData = [];
                tableData.push([
                    'Field', 
                    'Average Moisture', 
                    'Minimum', 
                    'Maximum', 
                    'Latest Reading', 
                    'Status'
                ]);
                
                <?php foreach($stats as $field => $field_stats): ?>
                tableData.push([
                    '<?php echo $field; ?>', 
                    '<?php echo is_numeric($field_stats['avg']) ? $field_stats['avg'] . '%' : $field_stats['avg']; ?>', 
                    '<?php echo is_numeric($field_stats['min']) ? $field_stats['min'] . '%' : $field_stats['min']; ?>', 
                    '<?php echo is_numeric($field_stats['max']) ? $field_stats['max'] . '%' : $field_stats['max']; ?>', 
                    '<?php echo is_numeric($field_stats['latest']) ? $field_stats['latest'] . '%' : $field_stats['latest']; ?>', 
                    '<?php 
                        if (is_numeric($field_stats['latest'])) {
                            if ($field_stats['latest'] < 30) {
                                echo 'Low';
                            } elseif ($field_stats['latest'] > 70) {
                                echo 'High';
                            } else {
                                echo 'Optimal';
                            }
                        } else {
                            echo 'Unknown';
                        }
                    ?>'
                ]);
                <?php endforeach; ?>
                
                doc.autoTable({
                    head: [tableData[0]],
                    body: tableData.slice(1),
                    startY: 40,
                    theme: 'grid',
                    styles: {
                        fontSize: 10
                    },
                    headStyles: {
                        fillColor: [66, 139, 202]
                    }
                });
                
                // Add recommendations
                const yPos = doc.lastAutoTable.finalY + 15;
                doc.setFontSize(14);
                doc.text('Recommendations', 14, yPos);
                
                doc.setFontSize(11);
                
                <?php
                    $low_fields = array_filter($stats, function($s) {
                        return is_numeric($s['avg']) && $s['avg'] < 30;
                    });
                    
                    $high_fields = array_filter($stats, function($s) {
                        return is_numeric($s['avg']) && $s['avg'] > 70;
                    });
                ?>
                
                <?php if (count($low_fields) > 0): ?>
                doc.text('Fields requiring irrigation: <?php echo implode(', ', array_keys($low_fields)); ?>', 14, yPos + 10);
                <?php endif; ?>
                
                <?php if (count($high_fields) > 0): ?>
                doc.text('Fields with excess moisture: <?php echo implode(', ', array_keys($high_fields)); ?>', 14, yPos + <?php echo count($low_fields) > 0 ? '20' : '10'; ?>);
                <?php endif; ?>
                
                <?php if (count($low_fields) == 0 && count($high_fields) == 0): ?>
                doc.text('All fields are within optimal moisture ranges.', 14, yPos + 10);
                <?php endif; ?>
                
                // Add footer
                const pageCount = doc.internal.getNumberOfPages();
                for(let i = 1; i <= pageCount; i++) {
                    doc.setPage(i);
                    doc.setFontSize(10);
                    doc.text('PureFarm Management System - Generated on <?php echo date('F d, Y'); ?>', 14, doc.internal.pageSize.height - 10);
                    doc.text('Page ' + i + ' of ' + pageCount, doc.internal.pageSize.width - 30, doc.internal.pageSize.height - 10);
                }
                
                // Save PDF
                doc.save('moisture-report-<?php echo $report_type; ?>-<?php echo date('Y-m-d'); ?>.pdf');
            });
        }
        
        if (exportCSV) {
            exportCSV.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Prepare CSV content
                let csvContent = 'Field,Date,Moisture (%),Depth,Method,Notes\n';
                
                <?php foreach($moisture_data as $record): ?>
                csvContent += '<?php echo addslashes($record['field_name']); ?>,' +
                              '<?php echo date('Y-m-d', strtotime($record['reading_date'])); ?>,' +
                              '<?php echo $record['moisture_percentage']; ?>,' +
                              '<?php echo addslashes($record['reading_depth']); ?>,' +
                              '<?php echo addslashes($record['reading_method']); ?>,' +
                              '<?php echo addslashes(str_replace(array("\r", "\n"), ' ', $record['notes'])); ?>\n';
                <?php endforeach; ?>
                
                // Create download link
                const encodedUri = encodeURI('data:text/csv;charset=utf-8,' + csvContent);
                const link = document.createElement('a');
                link.setAttribute('href', encodedUri);
                link.setAttribute('download', 'moisture-data-<?php echo date('Y-m-d'); ?>.csv');
                document.body.appendChild(link);
                
                // Trigger download and clean up
                link.click();
                document.body.removeChild(link);
            });
        }
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>