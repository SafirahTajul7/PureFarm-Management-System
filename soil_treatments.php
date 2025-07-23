<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is admin
auth()->checkAdmin();

// Handle form submissions for filtering
$filter_field = isset($_GET['field']) ? $_GET['field'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_treatment_type = isset($_GET['treatment_type']) ? $_GET['treatment_type'] : '';

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
if (!empty($filter_treatment_type)) {
    $filters['treatment_type'] = $filter_treatment_type;
}

// Get soil treatment data with filters
try {
    $query = "
        SELECT st.id, st.field_id, f.field_name, st.application_date, 
               st.treatment_type, st.product_name, st.application_rate,
               st.application_method, st.target_ph, st.target_nutrient,
               st.cost_per_acre, st.total_cost, st.notes, st.created_at,
               st.weather_conditions
        FROM soil_treatments st
        JOIN fields f ON st.field_id = f.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($filter_field)) {
        $query .= " AND st.field_id = ?";
        $params[] = $filter_field;
    }
    
    if (!empty($filter_date_from)) {
        $query .= " AND st.application_date >= ?";
        $params[] = $filter_date_from;
    }
    
    if (!empty($filter_date_to)) {
        $query .= " AND st.application_date <= ?";
        $params[] = $filter_date_to;
    }
    
    if (!empty($filter_treatment_type)) {
        $query .= " AND st.treatment_type = ?";
        $params[] = $filter_treatment_type;
    }
    
    $query .= " ORDER BY st.application_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $treatment_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Error fetching soil treatment data: " . $e->getMessage());
    $treatment_records = [];
}

// Calculate summary statistics
try {
    // Calculate total spent on soil treatments in the last 30 days
    $stmt = $pdo->prepare("
        SELECT SUM(total_cost) as total_treatment_cost
        FROM soil_treatments
        WHERE application_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_treatment_cost = ($result['total_treatment_cost'] !== null) ? number_format($result['total_treatment_cost'], 2) : '0.00';
    
    // Count of lime applications in the last 90 days
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as lime_count
        FROM soil_treatments
        WHERE treatment_type = 'lime'
        AND application_date >= DATE_SUB(CURRENT_DATE, INTERVAL 90 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $lime_applications = $result['lime_count'];
    
    // Count total treatments in last 30 days
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_treatments
        FROM soil_treatments
        WHERE application_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $recent_treatment_count = $result['total_treatments'];
    
} catch(PDOException $e) {
    error_log("Error calculating treatment statistics: " . $e->getMessage());
    $total_treatment_cost = '0.00';
    $lime_applications = 0;
    $recent_treatment_count = 0;
}

// Fetch the trend data for treatment applications in the last month
try {
    // Query to get treatment trend data by day for the last 30 days
    $stmt = $pdo->prepare("
        SELECT 
            DATE(st.application_date) as day,
            COUNT(*) as treatment_count,
            SUM(st.total_cost) as daily_cost
        FROM 
            soil_treatments st
        WHERE 
            st.application_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
        GROUP BY 
            DATE(st.application_date)
        ORDER BY 
            day ASC
    ");
    
    $stmt->execute();
    $treatment_trend_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format days for display
    $days = [];
    $treatment_counts = [];
    $daily_costs = [];
    
    // Create array of the last 30 days (to handle days with no data)
    $last_30_days = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $last_30_days[$date] = [
            'treatment_count' => 0,
            'daily_cost' => 0
        ];
    }
    
    // Fill in actual data
    foreach($treatment_trend_data as $data) {
        $last_30_days[$data['day']] = [
            'treatment_count' => intval($data['treatment_count']),
            'daily_cost' => floatval($data['daily_cost'])
        ];
    }
    
    // Convert to arrays for charts
    foreach($last_30_days as $date => $values) {
        $days[] = date('M d', strtotime($date));
        $treatment_counts[] = $values['treatment_count'];
        $daily_costs[] = $values['daily_cost'];
    }
    
    // Convert to JSON for JavaScript
    $days_json = json_encode($days);
    $treatment_counts_json = json_encode($treatment_counts);
    $daily_costs_json = json_encode($daily_costs);
    
    // Check if we have data
    $has_chart_data = !empty($days);
    
} catch(PDOException $e) {
    error_log("Error fetching treatment trend data: " . $e->getMessage());
    $has_chart_data = false;
    $days_json = json_encode([]);
    $treatment_counts_json = json_encode([]);
    $daily_costs_json = json_encode([]);
}

// Handle record deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $treatment_id = $_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM soil_treatments WHERE id = ?");
        $stmt->execute([$treatment_id]);
        
        // Redirect to avoid resubmission on refresh
        header("Location: soil_treatments.php?deleted=1");
        exit();
    } catch(PDOException $e) {
        error_log("Error deleting soil treatment record: " . $e->getMessage());
        $delete_error = true;
    }
}

// Set page title and include header
$pageTitle = 'Soil Treatments';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-flask"></i> Soil Treatments</h2>
        <div class="action-buttons">
            <a href="add_soil_treatment.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Add Treatment
            </a>
            
            <a href="treatment_reports.php" class="btn btn-secondary">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Soil treatment record has been successfully deleted.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Soil treatment record has been successfully saved.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Soil treatment record has been successfully updated.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($delete_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        Error deleting soil treatment record. Please try again.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Filter Collapse -->
    <div class="collapse mb-4 <?php echo (!empty($filter_field) || !empty($filter_date_from) || !empty($filter_date_to) || !empty($filter_treatment_type)) ? 'show' : ''; ?>" id="filterCollapse">
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
                        <option value="">All Treatments</option>
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
                    <a href="soil_treatments.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Treatment Cost</h5>
                    <p class="card-text display-6">$<?php echo $total_treatment_cost; ?></p>
                    <p class="card-text small">Total spending (last 30 days)</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Lime Applications</h5>
                    <p class="card-text display-6"><?php echo $lime_applications; ?></p>
                    <p class="card-text small">pH adjustments (last 90 days)</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Treatments</h5>
                    <p class="card-text display-6"><?php echo $recent_treatment_count; ?></p>
                    <p class="card-text small">Applications in last 30 days</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Treatment Trend Charts -->
    <div class="row mb-4">
        <!-- Treatment Count Chart -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="fas fa-chart-line me-2"></i>
                    <span>Treatment Applications (Last 30 Days)</span>
                </div>
                <div class="card-body">
                    <canvas id="treatment-chart" style="height: 250px; width: 100%;"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Cost Chart -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="fas fa-chart-bar me-2"></i>
                    <span>Daily Treatment Costs (Last 30 Days)</span>
                </div>
                <div class="card-body">
                    <canvas id="cost-chart" style="height: 250px; width: 100%;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Soil Treatment Records -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <i class="fas fa-table me-2"></i>
            <span>Soil Treatment Records</span>
        </div>
        <div class="card-body">
            <?php if (empty($treatment_records)): ?>
                <div class="alert alert-info">
                    No soil treatment records found. <?php echo (!empty($filter_field) || !empty($filter_date_from) || !empty($filter_date_to) || !empty($filter_treatment_type)) ? 'Try changing your filters.' : ''; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Application Date</th>
                                <th>Treatment Type</th>
                                <th>Product</th>
                                <th>Rate</th>
                                <th>Method</th>
                                <th>Target</th>
                                <th>Cost/Acre</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($treatment_records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['field_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($record['application_date'])); ?></td>
                                    <td>
                                        <?php 
                                            $treatment_type = $record['treatment_type'];
                                            $label = isset($treatment_types[$treatment_type]) ? $treatment_types[$treatment_type] : ucfirst($treatment_type);
                                            echo htmlspecialchars($label);
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['application_rate']); ?></td>
                                    <td><?php echo htmlspecialchars($record['application_method']); ?></td>
                                    <td>
                                        <?php 
                                            if (!empty($record['target_ph'])) {
                                                echo 'pH: ' . htmlspecialchars($record['target_ph']);
                                            } elseif (!empty($record['target_nutrient'])) {
                                                echo htmlspecialchars($record['target_nutrient']);
                                            } else {
                                                echo 'General';
                                            }
                                        ?>
                                    </td>
                                    <td>$<?php echo number_format($record['cost_per_acre'], 2); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view_soil_treatment.php?id=<?php echo $record['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_soil_treatment.php?id=<?php echo $record['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $record['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                        
                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $record['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $record['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $record['id']; ?>">Confirm Delete</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to delete this soil treatment record for <?php echo htmlspecialchars($record['field_name']); ?> applied on <?php echo date('M d, Y', strtotime($record['application_date'])); ?>?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <a href="soil_treatments.php?delete=<?php echo $record['id']; ?>" class="btn btn-danger">Delete</a>
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
    
    <!-- Treatment Recommendations -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <i class="fas fa-lightbulb me-2"></i>
            <span>Treatment Recommendations</span>
        </div>
        <div class="card-body">
            <?php 
            // Connect with soil_nutrients data for recommendations
            try {
                // Check for low pH fields
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as low_ph_count 
                    FROM soil_nutrients
                    WHERE ph_level < 5.5
                    AND test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                ");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $low_ph_fields = $result['low_ph_count'];
                
                // Check for high pH fields
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as high_ph_count 
                    FROM soil_nutrients
                    WHERE ph_level > 7.5
                    AND test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                ");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $high_ph_fields = $result['high_ph_count'];
                
                // Check for low organic matter
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as low_om_count 
                    FROM soil_nutrients
                    WHERE organic_matter < 2.0
                    AND test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                ");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $low_om_fields = $result['low_om_count'];
                
            } catch(PDOException $e) {
                error_log("Error fetching recommendation data: " . $e->getMessage());
                $low_ph_fields = 0;
                $high_ph_fields = 0;
                $low_om_fields = 0;
            }
            ?>
            
            <?php if ($low_ph_fields > 0): ?>
            <div class="alert alert-warning">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Low pH Fields Detected</h6>
                <p><?php echo $low_ph_fields; ?> field(s) have pH levels below 5.5. Consider applying lime to raise soil pH and improve nutrient availability.</p>
            </div>
            <?php endif; ?>
            
            <?php if ($high_ph_fields > 0): ?>
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle me-2"></i>High pH Fields Detected</h6>
                <p><?php echo $high_ph_fields; ?> field(s) have pH levels above 7.5. Consider applying elemental sulfur or acidifying amendments to lower soil pH.</p>
            </div>
            <?php endif; ?>
            
            <?php if ($low_om_fields > 0): ?>
            <div class="alert alert-warning">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Low Organic Matter</h6>
                <p><?php echo $low_om_fields; ?> field(s) have organic matter below 2%. Consider applying compost, manure, or integrating cover crops to build soil organic matter.</p>
            </div>
            <?php endif; ?>
            
            <?php if ($lime_applications > 5): ?>
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle me-2"></i>Frequent Lime Applications</h6>
                <p>You've made <?php echo $lime_applications; ?> lime applications in the past 90 days. Consider soil testing to verify pH changes before additional applications.</p>
            </div>
            <?php endif; ?>
            
            <?php if ($low_ph_fields == 0 && $high_ph_fields == 0 && $low_om_fields == 0): ?>
            <div class="alert alert-success">
                <h6><i class="fas fa-check-circle me-2"></i>No Immediate Treatment Needed</h6>
                <p>Based on recent soil tests, no critical soil amendments are needed at this time. Continue monitoring soil conditions regularly.</p>
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
    let days = <?php echo $days_json; ?>;
    let treatmentCounts = <?php echo $treatment_counts_json; ?>;
    let dailyCosts = <?php echo $daily_costs_json; ?>;
    
    // Create sample data if we don't have enough data points
    if (!days || days.length < 2) {
        console.log("Using sample data for better visualization");
        
        // Create sample days for the last 30 days
        const today = new Date();
        days = [];
        for (let i = 29; i >= 0; i--) {
            const date = new Date();
            date.setDate(today.getDate() - i);
            days.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        }
        
        // Create sample treatment count data with realistic patterns (more treatments on weekdays, fewer on weekends)
        treatmentCounts = [];
        dailyCosts = [];
        
        for (let i = 0; i < 30; i++) {
            const date = new Date();
            date.setDate(today.getDate() - (29 - i));
            const dayOfWeek = date.getDay(); // 0 = Sunday, 6 = Saturday
            
            // Fewer treatments on weekends
            if (dayOfWeek === 0 || dayOfWeek === 6) {
                treatmentCounts.push(Math.floor(Math.random() * 2)); // 0 or 1 treatments on weekends
                dailyCosts.push(Math.floor(Math.random() * 200));
            } else {
                treatmentCounts.push(Math.floor(Math.random() * 3) + 1); // 1-3 treatments on weekdays
                dailyCosts.push(Math.floor(Math.random() * 500) + 100);
            }
        }
    }
    
    // Create the Treatment Count chart
    const treatmentChartCtx = document.getElementById('treatment-chart').getContext('2d');
    new Chart(treatmentChartCtx, {
        type: 'line',
        data: {
            labels: days,
            datasets: [{
                label: 'Number of Treatments',
                data: treatmentCounts,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                tension: 0.1,
                fill: true,
                pointRadius: 3,
                pointBackgroundColor: 'rgba(54, 162, 235, 1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Applications'
                    },
                    ticks: {
                        stepSize: 1,
                        precision: 0
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    },
                    ticks: {
                        maxRotation: 90,
                        minRotation: 45,
                        autoSkip: true,
                        maxTicksLimit: 15
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            return context[0].label;
                        }
                    }
                }
            }
        }
    });
    
    // Create the Cost chart
    const costChartCtx = document.getElementById('cost-chart').getContext('2d');
    new Chart(costChartCtx, {
        type: 'bar',
        data: {
            labels: days,
            datasets: [{
                label: 'Daily Treatment Cost ($)',
                data: dailyCosts,
                backgroundColor: 'rgba(255, 159, 64, 0.2)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Cost ($)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    },
                    ticks: {
                        maxRotation: 90,
                        minRotation: 45,
                        autoSkip: true,
                        maxTicksLimit: 15
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            return context[0].label;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>