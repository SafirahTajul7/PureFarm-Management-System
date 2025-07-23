<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/soil_test_manager.php';

// Check if user is admin
auth()->checkAdmin();

// Create soil test manager
$soilTestManager = new SoilTestManager($pdo);

// Handle form submissions for filtering
$filter_field = isset($_GET['field']) ? $_GET['field'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Fetch all fields for the filter dropdown
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

// Get soil tests with filters
$soil_tests = $soilTestManager->getAllSoilTests($filters);

// Calculate summary statistics directly (in case SoilTestManager doesn't have proper implementation)
try {
    // Calculate total tests in last 30 days
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_tests
        FROM soil_tests
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $recent_tests_count = $result['total_tests'];
    
    // Debug
    error_log('Recent tests query result: ' . print_r($result, true));
    
    // Calculate average pH
    $stmt = $pdo->prepare("
        SELECT AVG(ph_level) as avg_ph
        FROM soil_tests
        WHERE ph_level IS NOT NULL
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $avg_ph = ($result['avg_ph'] !== null) ? number_format($result['avg_ph'], 1) : 'N/A';

    // Debug
    error_log('Average pH query result: ' . print_r($result, true));
    
    // Calculate average moisture
    $stmt = $pdo->prepare("
        SELECT AVG(moisture_percentage) as avg_moisture
        FROM soil_tests
        WHERE moisture_percentage IS NOT NULL
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $avg_moisture = ($result['avg_moisture'] !== null) ? number_format($result['avg_moisture'], 1) . '%' : 'N/A';
    // Debug
    error_log('Average moisture query result: ' . print_r($result, true));
    
    // Count fields with low pH
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT field_id) as low_ph_count
        FROM soil_tests
        WHERE ph_level < 6.0
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $low_ph_fields = $result['low_ph_count'];
    
    // Debug
    error_log('Low pH fields query result: ' . print_r($result, true));
    
} catch (PDOException $e) {
    // Handle errors
    error_log("Error calculating statistics: " . $e->getMessage());
    $recent_tests_count = 0;
    $avg_ph = 'N/A';
    $avg_moisture = 'N/A';
    $low_ph_fields = 0;
}

// Handle soil test deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $test_id = $_GET['delete'];
    
    if ($soilTestManager->deleteSoilTest($test_id)) {
        // Redirect to avoid resubmission on refresh
        header("Location: soil_tests.php?deleted=1");
        exit();
    } else {
        $delete_error = true;
    }
}

// Set page title and include header
$pageTitle = 'Soil Tests';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-flask"></i> Soil Tests</h2>
        <div class="action-buttons">
            <a href="add_soil_test.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Soil Test
            </a>
            <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="soil_test_reports.php" class="btn btn-outline-secondary">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Soil test record has been successfully deleted.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Soil test record has been successfully saved.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Soil test record has been successfully updated.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($delete_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        Error deleting soil test record. Please try again.
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
                    <a href="soil_tests.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Recent Tests</h5>
                    <p class="card-text display-6"><?php echo $recent_tests_count; ?></p>
                    <p class="card-text small">Tests in the last 30 days</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Average pH</h5>
                    <p class="card-text display-6"><?php echo $avg_ph; ?></p>
                    <p class="card-text small">Across all fields (last 30 days)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Average Moisture</h5>
                    <p class="card-text display-6"><?php echo $avg_moisture; ?></p>
                    <p class="card-text small">Across all fields (last 30 days)</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card <?php echo ($low_ph_fields > 0) ? 'bg-warning' : 'bg-light'; ?> <?php echo ($low_ph_fields > 0) ? 'text-dark' : ''; ?>">
                <div class="card-body">
                    <h5 class="card-title">Fields with Low pH</h5>
                    <p class="card-text display-6"><?php echo $low_ph_fields; ?></p>
                    <p class="card-text small">pH below 6.0 (last 30 days)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Soil Tests Table -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-table me-2"></i>Soil Test Records</h5>
        </div>
        <div class="card-body">
            <?php if (empty($soil_tests)): ?>
                <div class="alert alert-info">
                    No soil test records found. <?php echo (!empty($filter_field) || !empty($filter_date_from) || !empty($filter_date_to)) ? 'Try changing your filters.' : ''; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Test Date</th>
                                <th>pH Level</th>
                                <th>Moisture</th>
                                <th>Temperature</th>
                                <th>Nitrogen</th>
                                <th>Phosphorus</th>
                                <th>Potassium</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($soil_tests as $test): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($test['field_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($test['test_date'])); ?></td>
                                    <td>
                                        <?php 
                                            $ph = $test['ph_level'];
                                            $ph_class = '';
                                            if ($ph < 6.0) $ph_class = 'text-danger';
                                            elseif ($ph > 7.5) $ph_class = 'text-warning';
                                            echo '<span class="' . $ph_class . '">' . $ph . '</span>';
                                        ?>
                                    </td>
                                    <td><?php echo isset($test['moisture_percentage']) ? $test['moisture_percentage'] . '%' : 'N/A'; ?></td>
                                    <td><?php echo isset($test['temperature']) ? $test['temperature'] . '°C' : 'N/A'; ?></td>
                                    <td>
                                        <?php 
                                            $nitrogen = $test['nitrogen_level'];
                                            if ($nitrogen) {
                                                switch(strtolower($nitrogen)) {
                                                    case 'low': echo '<span class="badge bg-danger">Low</span>'; break;
                                                    case 'medium': echo '<span class="badge bg-warning text-dark">Medium</span>'; break;
                                                    case 'high': echo '<span class="badge bg-success">High</span>'; break;
                                                    default: echo htmlspecialchars($nitrogen);
                                                }
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $phosphorus = $test['phosphorus_level'];
                                            if ($phosphorus) {
                                                switch(strtolower($phosphorus)) {
                                                    case 'low': echo '<span class="badge bg-danger">Low</span>'; break;
                                                    case 'medium': echo '<span class="badge bg-warning text-dark">Medium</span>'; break;
                                                    case 'high': echo '<span class="badge bg-success">High</span>'; break;
                                                    default: echo htmlspecialchars($phosphorus);
                                                }
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $potassium = $test['potassium_level'];
                                            if ($potassium) {
                                                switch(strtolower($potassium)) {
                                                    case 'low': echo '<span class="badge bg-danger">Low</span>'; break;
                                                    case 'medium': echo '<span class="badge bg-warning text-dark">Medium</span>'; break;
                                                    case 'high': echo '<span class="badge bg-success">High</span>'; break;
                                                    default: echo htmlspecialchars($potassium);
                                                }
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view_soil_test.php?id=<?php echo $test['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_soil_test.php?id=<?php echo $test['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $test['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                        
                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $test['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $test['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $test['id']; ?>">Confirm Delete</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to delete this soil test record for <?php echo htmlspecialchars($test['field_name']); ?> taken on <?php echo date('M d, Y', strtotime($test['test_date'])); ?>?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <a href="soil_tests.php?delete=<?php echo $test['id']; ?>" class="btn btn-danger">Delete</a>
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
    
    <!-- Recommendations Section -->
    <div class="card mt-4">
        <div class="card-header">
            <h5><i class="fas fa-lightbulb me-2"></i>Recommendations</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php if ($low_ph_fields > 0): ?>
                <div class="col-md-4">
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Low pH Detected</h6>
                        <p>Some fields have low pH levels (below 6.0). Consider applying lime to raise soil pH for optimal nutrient availability.</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php 
                // Check if average moisture is a number before comparing
                $avg_moisture_value = is_numeric($avg_moisture) ? floatval($avg_moisture) : null;
                if ($avg_moisture_value !== null && $avg_moisture_value < 30): 
                ?>
                <div class="col-md-4">
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-tint-slash me-2"></i>Low Soil Moisture</h6>
                        <p>Average soil moisture is below optimal levels. Consider scheduling irrigation or checking irrigation systems.</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($recent_tests_count < 5): ?>
                <div class="col-md-4">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Testing Frequency</h6>
                        <p>Consider conducting more regular soil tests to better monitor soil health and nutrient levels.</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php 
                // Only show good soil management if there are no issues and there are tests
                if ($low_ph_fields == 0 && $recent_tests_count > 0 && 
                    ($avg_moisture_value === null || $avg_moisture_value >= 30)): 
                ?>
                <div class="col-md-4">
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle me-2"></i>Good Soil Management</h6>
                        <p>Your soil parameters are within optimal ranges. Continue your current management practices.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Custom Scripts for Soil Tests Page -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Enable tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Handle filter date range validation
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
    });
</script>

<?php include 'includes/footer.php'; ?>