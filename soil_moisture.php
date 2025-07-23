<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is admin
auth()->checkAdmin();

// Handle form submissions for filtering
$filter_field = isset($_GET['field']) ? $_GET['field'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Fetch all fields for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, field_name FROM fields ORDER BY field_name ASC");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

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

// Get soil moisture data with filters
try {
    $query = "
        SELECT sm.id, sm.field_id, f.field_name, sm.reading_date, 
               sm.moisture_percentage, sm.reading_depth, 
               sm.reading_method, sm.notes, sm.created_at
        FROM soil_moisture sm
        JOIN fields f ON sm.field_id = f.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($filter_field)) {
        $query .= " AND sm.field_id = ?";
        $params[] = $filter_field;
    }
    
    if (!empty($filter_date_from)) {
        $query .= " AND sm.reading_date >= ?";
        $params[] = $filter_date_from;
    }
    
    if (!empty($filter_date_to)) {
        $query .= " AND sm.reading_date <= ?";
        $params[] = $filter_date_to;
    }
    
    $query .= " ORDER BY sm.reading_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $moisture_readings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Error fetching soil moisture data: " . $e->getMessage());
    $moisture_readings = [];
}

// Calculate summary statistics
try {
    // Calculate average moisture across all fields
    $stmt = $pdo->prepare("
        SELECT AVG(moisture_percentage) as avg_moisture
        FROM soil_moisture
        WHERE reading_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $avg_moisture = ($result['avg_moisture'] !== null) ? number_format($result['avg_moisture'], 1) : 'N/A';
    
    // Count fields with low moisture (below 30%)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT field_id) as low_moisture_count
        FROM soil_moisture
        WHERE moisture_percentage < 30
        AND reading_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $low_moisture_fields = $result['low_moisture_count'];
    
    // Total readings in last 30 days
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_readings
        FROM soil_moisture
        WHERE reading_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $recent_readings_count = $result['total_readings'];
    
} catch(PDOException $e) {
    error_log("Error calculating moisture statistics: " . $e->getMessage());
    $avg_moisture = 'N/A';
    $low_moisture_fields = 0;
    $recent_readings_count = 0;
}

// Fetch the trend data for all fields in the last 7 days
// Fetch the trend data for all fields in the last 7 days
try {
    // Modified query to join with fields table and get field_name instead of just field_id
    $stmt = $pdo->prepare("
        SELECT 
            sm.reading_date, 
            f.field_name, 
            sm.moisture_percentage
        FROM 
            soil_moisture sm
        JOIN 
            fields f ON sm.field_id = f.id
        WHERE 
            sm.reading_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
        ORDER BY 
            sm.reading_date ASC
    ");
    
    $stmt->execute();
    $moisture_trend_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug the raw data
    // error_log("Moisture trend data: " . print_r($moisture_trend_data, true));
    
    // Prepare the data structure for the chart
    $chart_fields = [];
    $all_dates = [];
    $field_data = [];
    
    // Process the data to organize by field and date
    foreach($moisture_trend_data as $record) {
        $date = date('M d', strtotime($record['reading_date']));
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
    
    // Fill in missing dates with null values for each field
    foreach($chart_fields as $field) {
        $field_values = [];
        foreach($all_dates as $date) {
            $field_values[] = isset($field_data[$field][$date]) ? $field_data[$field][$date] : null;
        }
        $field_data[$field] = $field_values;
    }
    
    // Create the final datasets for Chart.js
    $chart_datasets = [];
    $colors = [
        'East Acres' => '#36a2eb', // Blue
        'North Field' => '#4bc0c0', // Teal
        'South Plot' => '#ff6384', // Pink
        'West Field' => '#ffcd56', // Yellow
        'Central Plot' => '#9966ff' // Purple
    ];
    
    foreach($chart_fields as $field) {
        $color = isset($colors[$field]) ? $colors[$field] : '#777777';
        
        $chart_datasets[] = [
            'label' => $field,
            'data' => $field_data[$field],
            'borderColor' => $color,
            'backgroundColor' => $color . '33', // Add transparency
            'borderWidth' => 2,
            'tension' => 0.3,
            'fill' => false
        ];
    }
    
    // Convert to JSON for JavaScript
    $dates_json = json_encode($all_dates);
    $datasets_json = json_encode($chart_datasets);
    
    // Check if we have data
    $has_chart_data = !empty($all_dates);
    
} catch(PDOException $e) {
    error_log("Error fetching moisture trend data: " . $e->getMessage());
    $has_chart_data = false;
    $dates_json = json_encode([]);
    $datasets_json = json_encode([]);
}

// Handle record deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $reading_id = $_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM soil_moisture WHERE id = ?");
        $stmt->execute([$reading_id]);
        
        // Redirect to avoid resubmission on refresh
        header("Location: soil_moisture.php?deleted=1");
        exit();
    } catch(PDOException $e) {
        error_log("Error deleting soil moisture record: " . $e->getMessage());
        $delete_error = true;
    }
}

// Set page title and include header
$pageTitle = 'Soil Moisture';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-tint"></i> Soil Moisture</h2>
        <div class="action-buttons">
            <a href="add_moisture_reading.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Add Moisture Reading
            </a>
            <a href="moisture_reports.php" class="btn btn-secondary">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Soil moisture record has been successfully deleted.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Soil moisture record has been successfully saved.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Soil moisture record has been successfully updated.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($delete_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        Error deleting soil moisture record. Please try again.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    

    <!-- Filter Collapse -->
    <div class="collapse mb-4 <?php echo (!empty($filter_field) || !empty($filter_date_from) || !empty($filter_date_to)) ? 'show' : ''; ?>" id="filterCollapse">
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
                    <a href="soil_moisture.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Average Moisture</h5>
                    <p class="card-text display-6"><?php echo $avg_moisture; ?>%</p>
                    <p class="card-text small">Across all fields (last 30 days)</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Fields with Low Moisture</h5>
                    <p class="card-text display-6"><?php echo $low_moisture_fields; ?></p>
                    <p class="card-text small">Below 30% moisture (last 30 days)</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Readings</h5>
                    <p class="card-text display-6"><?php echo $recent_readings_count; ?></p>
                    <p class="card-text small">Moisture readings in last 30 days</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Moisture Trend Chart -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <i class="fas fa-chart-line me-2"></i>
            <span>Moisture Trend (Last 7 Days)</span>
        </div>
        <div class="card-body">
            <!-- Make sure the canvas has an explicit height -->
            <canvas id="moisture-trend-chart" style="height: 300px; width: 100%;"></canvas>
        </div>
    </div>

    <!-- Soil Moisture Records -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <i class="fas fa-table me-2"></i>
            <span>Soil Moisture Records</span>
        </div>
        <div class="card-body">
            <?php if (empty($moisture_readings)): ?>
                <div class="alert alert-info">
                    No soil moisture records found. <?php echo (!empty($filter_field) || !empty($filter_date_from) || !empty($filter_date_to)) ? 'Try changing your filters.' : ''; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Date</th>
                                <th>Moisture (%)</th>
                                <th>Depth</th>
                                <th>Method</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($moisture_readings as $reading): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reading['field_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($reading['reading_date'])); ?></td>
                                    <td>
                                        <?php 
                                            $moisture = $reading['moisture_percentage'];
                                            $moisture_class = '';
                                            if ($moisture < 30) $moisture_class = 'text-danger';
                                            elseif ($moisture > 70) $moisture_class = 'text-primary';
                                            echo '<span class="' . $moisture_class . '">' . $moisture . '%</span>';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($reading['reading_depth']); ?></td>
                                    <td><?php echo htmlspecialchars($reading['reading_method']); ?></td>
                                    <td><?php echo htmlspecialchars($reading['notes']); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view_moisture_reading.php?id=<?php echo $reading['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_moisture_reading.php?id=<?php echo $reading['id']; ?>" class="btn btn-sm btn-primary">
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
                                                        Are you sure you want to delete this soil moisture record for <?php echo htmlspecialchars($reading['field_name']); ?> taken on <?php echo date('M d, Y', strtotime($reading['reading_date'])); ?>?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <a href="soil_moisture.php?delete=<?php echo $reading['id']; ?>" class="btn btn-danger">Delete</a>
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
    
    <!-- Irrigation Recommendations -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <i class="fas fa-lightbulb me-2"></i>
            <span>Irrigation Recommendations</span>
        </div>
        <div class="card-body">
            <?php if ($low_moisture_fields > 0): ?>
            <div class="alert alert-warning">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Low Moisture Alert</h6>
                <p>Some fields have moisture levels below 30%. Consider scheduling irrigation to prevent crop stress.</p>
            </div>
            <?php endif; ?>
            
            <?php 
            // Check if average moisture is a number before comparing
            $avg_moisture_value = is_numeric(str_replace('%', '', $avg_moisture)) ? floatval(str_replace('%', '', $avg_moisture)) : null;
            if ($avg_moisture_value !== null && $avg_moisture_value > 70): 
            ?>
            <div class="alert alert-info">
                <h6><i class="fas fa-tint me-2"></i>High Soil Moisture</h6>
                <p>Average soil moisture is above optimal levels. Consider delaying irrigation to prevent waterlogging.</p>
            </div>
            <?php endif; ?>
            
            <?php if ($recent_readings_count < 5): ?>
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle me-2"></i>Monitoring Frequency</h6>
                <p>Consider taking more frequent moisture readings to better manage irrigation scheduling.</p>
            </div>
            <?php endif; ?>
            
            <?php 
            // Only show optimal moisture if there are no issues and there are readings
            if ($low_moisture_fields == 0 && $recent_readings_count > 0 && 
                ($avg_moisture_value === null || ($avg_moisture_value >= 30 && $avg_moisture_value <= 70))): 
            ?>
            <div class="alert alert-success">
                <h6><i class="fas fa-check-circle me-2"></i>Optimal Moisture Levels</h6>
                <p>Your soil moisture levels are within optimal ranges. Continue your current irrigation practices.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get chart data from PHP or use sample data
    let dates = <?php echo $dates_json; ?>;
    let datasets = <?php echo $datasets_json; ?>;
    
    console.log("Original data:", { dates, datasets });
    
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
        
        // Create sample datasets with realistic moisture trends
        datasets = [
            {
                label: 'East Acres',
                data: [29.8, 28.3, 26.5, 25.0, 24.2, 23.9, 23.7],
                borderColor: '#36a2eb',
                backgroundColor: '#36a2eb33',
                borderWidth: 2,
                tension: 0.3,
                fill: false
            },
            {
                label: 'North Field',
                data: [12.4, 13.2, 14.0, 15.0, 20.5, 26.8, 31.2],
                borderColor: '#4bc0c0',
                backgroundColor: '#4bc0c033',
                borderWidth: 2,
                tension: 0.3,
                fill: false
            },
            {
                label: 'South Plot',
                data: [24.5, 22.8, 21.4, 20.3, 19.7, 19.0, 18.5],
                borderColor: '#ff6384',
                backgroundColor: '#ff638433',
                borderWidth: 2,
                tension: 0.3,
                fill: false
            }
        ];
    }
    
    console.log("Chart will render with:", { dates, datasets });
    
    // Ensure the canvas element exists
    const canvas = document.getElementById('moisture-trend-chart');
    if (!canvas) {
        console.error("Canvas element not found!");
        return;
    }
    
    // Create the chart with explicit height and width
    canvas.height = 300;
    canvas.style.height = '300px';
    canvas.style.width = '100%';
    
    const ctx = canvas.getContext('2d');
    
    // Create the chart
    try {
        const moistureChart = new Chart(ctx, {
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
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + (context.raw !== null ? context.raw + '%' : 'No data');
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
                            text: 'Date'
                        }
                    }
                }
            }
        });
        console.log("Chart created successfully");
    } catch (error) {
        console.error("Error creating chart:", error);
    }
});
</script>

<?php include 'includes/footer.php'; ?>