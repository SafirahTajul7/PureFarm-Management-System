<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is logged in and authorized
auth()->checkAdmin();

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: soil_nutrients.php?error=invalid_id");
    exit();
}

$id = intval($_GET['id']);

// Fetch the existing record with field name
try {
    $stmt = $pdo->prepare("
        SELECT sn.*, f.field_name 
        FROM soil_nutrients sn
        JOIN fields f ON sn.field_id = f.id
        WHERE sn.id = ?
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        header("Location: soil_nutrients.php?error=record_not_found");
        exit();
    }
} catch(PDOException $e) {
    error_log("Error fetching soil nutrient record: " . $e->getMessage());
    header("Location: soil_nutrients.php?error=database_error");
    exit();
}

// Fetch next test dates for the field
try {
    $stmt = $pdo->prepare("
        SELECT MIN(test_date) as next_test_date
        FROM soil_nutrients
        WHERE field_id = ? AND test_date > ?
    ");
    $stmt->execute([$record['field_id'], $record['test_date']]);
    $next_test = $stmt->fetch(PDO::FETCH_ASSOC);
    $next_test_date = $next_test['next_test_date'] ? $next_test['next_test_date'] : null;
} catch(PDOException $e) {
    error_log("Error fetching next test date: " . $e->getMessage());
    $next_test_date = null;
}

// Fetch previous test dates for the field
try {
    $stmt = $pdo->prepare("
        SELECT MAX(test_date) as prev_test_date
        FROM soil_nutrients
        WHERE field_id = ? AND test_date < ?
    ");
    $stmt->execute([$record['field_id'], $record['test_date']]);
    $prev_test = $stmt->fetch(PDO::FETCH_ASSOC);
    $prev_test_date = $prev_test['prev_test_date'] ? $prev_test['prev_test_date'] : null;
} catch(PDOException $e) {
    error_log("Error fetching previous test date: " . $e->getMessage());
    $prev_test_date = null;
}

// Get historical data for charts
try {
    // Get the last 5 test results for this field
    $stmt = $pdo->prepare("
        SELECT test_date, nitrogen, phosphorus, potassium, ph_level, organic_matter
        FROM soil_nutrients
        WHERE field_id = ?
        ORDER BY test_date DESC
        LIMIT 5
    ");
    $stmt->execute([$record['field_id']]);
    $historical_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Reverse order to show chronological progression
    $historical_data = array_reverse($historical_data);
    
    // Format data for charts
    $dates = [];
    $n_values = [];
    $p_values = [];
    $k_values = [];
    $ph_values = [];
    $om_values = [];
    
    foreach ($historical_data as $data) {
        $dates[] = date('M d, Y', strtotime($data['test_date']));
        $n_values[] = $data['nitrogen'];
        $p_values[] = $data['phosphorus'];
        $k_values[] = $data['potassium'];
        $ph_values[] = $data['ph_level'];
        $om_values[] = $data['organic_matter'];
    }
    
    $dates_json = json_encode($dates);
    $n_values_json = json_encode($n_values);
    $p_values_json = json_encode($p_values);
    $k_values_json = json_encode($k_values);
    $ph_values_json = json_encode($ph_values);
    $om_values_json = json_encode($om_values);
    
    $has_historical_data = count($historical_data) > 1;
    
} catch(PDOException $e) {
    error_log("Error fetching historical data: " . $e->getMessage());
    $has_historical_data = false;
}

// Set page title and include header
$pageTitle = 'View Nutrient Reading';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h2><i class="fas fa-flask"></i> Soil Nutrient Test Details</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="soil_nutrients.php">Soil Nutrients</a></li>
                    <li class="breadcrumb-item active" aria-current="page">View Reading</li>
                </ol>
            </nav>
        </div>
        <div class="action-buttons">
            <a href="edit_nutrient_reading.php?id=<?php echo $id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="#" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                <i class="fas fa-trash"></i> Delete
            </a>
        </div>
    </div>

    <!-- Test Navigation -->
    <div class="d-flex justify-content-between mb-3">
        <?php if ($prev_test_date): ?>
        <a href="view_nutrient_reading.php?id=<?php echo getTestIdByDate($pdo, $record['field_id'], $prev_test_date); ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Previous Test (<?php echo date('M d, Y', strtotime($prev_test_date)); ?>)
        </a>
        <?php else: ?>
        <button class="btn btn-outline-secondary" disabled>
            <i class="fas fa-arrow-left"></i> Previous Test
        </button>
        <?php endif; ?>
        
        <span class="align-self-center">
            <strong>Test Date:</strong> <?php echo date('F d, Y', strtotime($record['test_date'])); ?>
        </span>
        
        <?php if ($next_test_date): ?>
        <a href="view_nutrient_reading.php?id=<?php echo getTestIdByDate($pdo, $record['field_id'], $next_test_date); ?>" class="btn btn-outline-secondary">
            Next Test (<?php echo date('M d, Y', strtotime($next_test_date)); ?>) <i class="fas fa-arrow-right"></i>
        </a>
        <?php else: ?>
        <button class="btn btn-outline-secondary" disabled>
            Next Test <i class="fas fa-arrow-right"></i>
        </button>
        <?php endif; ?>
    </div>

    <div class="row">
        <!-- Test Details -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-2"></i>Test Information
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <tbody>
                            <tr>
                                <th style="width: 35%">Field:</th>
                                <td><?php echo htmlspecialchars($record['field_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Test Date:</th>
                                <td><?php echo date('F d, Y', strtotime($record['test_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Test Method:</th>
                                <td><?php echo htmlspecialchars($record['test_method']); ?></td>
                            </tr>
                            <tr>
                                <th>Recorded On:</th>
                                <td><?php echo date('F d, Y g:i A', strtotime($record['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Last Updated:</th>
                                <td><?php echo date('F d, Y g:i A', strtotime($record['updated_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Notes:</th>
                                <td>
                                    <?php echo !empty($record['notes']) ? nl2br(htmlspecialchars($record['notes'])) : '<em class="text-muted">No notes provided</em>'; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Nutrient Levels -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-vial me-2"></i>Nutrient Levels
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Macronutrients -->
                            <h6 class="border-bottom pb-2 mb-3">Macronutrients</h6>
                            <div class="nutrient-item mb-3">
                                <div class="d-flex justify-content-between">
                                    <strong>Nitrogen (N):</strong>
                                    <span class="<?php echo getNutrientClass('nitrogen', $record['nitrogen']); ?>">
                                        <?php echo htmlspecialchars($record['nitrogen']); ?> ppm
                                    </span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar <?php echo getNutrientProgressBarClass('nitrogen', $record['nitrogen']); ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo min(($record['nitrogen']/40)*100, 100); ?>%" 
                                         aria-valuenow="<?php echo $record['nitrogen']; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="40"></div>
                                </div>
                            </div>
                            
                            <div class="nutrient-item mb-3">
                                <div class="d-flex justify-content-between">
                                    <strong>Phosphorus (P):</strong>
                                    <span class="<?php echo getNutrientClass('phosphorus', $record['phosphorus']); ?>">
                                        <?php echo htmlspecialchars($record['phosphorus']); ?> ppm
                                    </span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar <?php echo getNutrientProgressBarClass('phosphorus', $record['phosphorus']); ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo min(($record['phosphorus']/50)*100, 100); ?>%" 
                                         aria-valuenow="<?php echo $record['phosphorus']; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="50"></div>
                                </div>
                            </div>
                            
                            <div class="nutrient-item mb-3">
                                <div class="d-flex justify-content-between">
                                    <strong>Potassium (K):</strong>
                                    <span class="<?php echo getNutrientClass('potassium', $record['potassium']); ?>">
                                        <?php echo htmlspecialchars($record['potassium']); ?> ppm
                                    </span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar <?php echo getNutrientProgressBarClass('potassium', $record['potassium']); ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo min(($record['potassium']/300)*100, 100); ?>%" 
                                         aria-valuenow="<?php echo $record['potassium']; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="300"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <!-- pH and Other Metrics -->
                            <h6 class="border-bottom pb-2 mb-3">Soil Properties</h6>
                            <div class="nutrient-item mb-3">
                                <div class="d-flex justify-content-between">
                                    <strong>pH Level:</strong>
                                    <span class="<?php echo getPHClass($record['ph_level']); ?>">
                                        <?php echo htmlspecialchars($record['ph_level']); ?>
                                    </span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar <?php echo getPHProgressBarClass($record['ph_level']); ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo ($record['ph_level']/14)*100; ?>%" 
                                         aria-valuenow="<?php echo $record['ph_level']; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="14"></div>
                                </div>
                            </div>
                            
                            <div class="nutrient-item mb-3">
                                <div class="d-flex justify-content-between">
                                    <strong>Organic Matter:</strong>
                                    <span class="<?php echo getOrganicMatterClass($record['organic_matter']); ?>">
                                        <?php echo htmlspecialchars($record['organic_matter']); ?> %
                                    </span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar <?php echo getOrganicMatterProgressBarClass($record['organic_matter']); ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo min(($record['organic_matter']/10)*100, 100); ?>%" 
                                         aria-valuenow="<?php echo $record['organic_matter']; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="10"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Secondary Nutrients -->
                    <h6 class="border-bottom pb-2 mb-3 mt-4">Secondary Nutrients</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="secondary-nutrient text-center mb-3">
                                <div class="card">
                                    <div class="card-body p-2">
                                        <h6 class="mb-1">Calcium (Ca)</h6>
                                        <span class="fs-5 <?php echo getSecondaryNutrientClass('calcium', $record['calcium']); ?>">
                                            <?php echo !empty($record['calcium']) ? htmlspecialchars($record['calcium']) : 'N/A'; ?> ppm
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="secondary-nutrient text-center mb-3">
                                <div class="card">
                                    <div class="card-body p-2">
                                        <h6 class="mb-1">Magnesium (Mg)</h6>
                                        <span class="fs-5 <?php echo getSecondaryNutrientClass('magnesium', $record['magnesium']); ?>">
                                            <?php echo !empty($record['magnesium']) ? htmlspecialchars($record['magnesium']) : 'N/A'; ?> ppm
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="secondary-nutrient text-center mb-3">
                                <div class="card">
                                    <div class="card-body p-2">
                                        <h6 class="mb-1">Sulfur (S)</h6>
                                        <span class="fs-5 <?php echo getSecondaryNutrientClass('sulfur', $record['sulfur']); ?>">
                                            <?php echo !empty($record['sulfur']) ? htmlspecialchars($record['sulfur']) : 'N/A'; ?> ppm
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Historical Charts -->
    <?php if ($has_historical_data): ?>
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-chart-line me-2"></i>Historical Nutrient Trends
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <canvas id="npk-chart" style="height: 300px;"></canvas>
                </div>
                <div class="col-md-6">
                    <canvas id="ph-om-chart" style="height: 300px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Recommendations -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-lightbulb me-2"></i>Recommendations
        </div>
        <div class="card-body">
            <?php
            $recommendations = getNutrientRecommendations($record);
            if (empty($recommendations)):
            ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>All nutrient levels are within optimal ranges. Continue with current management practices.
                </div>
            <?php else: ?>
                <ul class="list-group">
                <?php foreach ($recommendations as $recommendation): ?>
                    <li class="list-group-item">
                        <div class="d-flex">
                            <div class="me-3">
                                <?php if ($recommendation['type'] == 'danger'): ?>
                                <i class="fas fa-exclamation-circle text-danger fs-5"></i>
                                <?php elseif ($recommendation['type'] == 'warning'): ?>
                                <i class="fas fa-exclamation-triangle text-warning fs-5"></i>
                                <?php else: ?>
                                <i class="fas fa-info-circle text-info fs-5"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($recommendation['title']); ?></h6>
                                <p class="mb-0"><?php echo htmlspecialchars($recommendation['message']); ?></p>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this soil nutrient record for <?php echo htmlspecialchars($record['field_name']); ?> from <?php echo date('M d, Y', strtotime($record['test_date'])); ?>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="soil_nutrients.php?delete=<?php echo $id; ?>" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if ($has_historical_data): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get chart data from PHP
    const dates = <?php echo $dates_json; ?>;
    const nitrogen = <?php echo $n_values_json; ?>;
    const phosphorus = <?php echo $p_values_json; ?>;
    const potassium = <?php echo $k_values_json; ?>;
    const ph = <?php echo $ph_values_json; ?>;
    const organicMatter = <?php echo $om_values_json; ?>;
    
    // NPK Chart
    const npkCtx = document.getElementById('npk-chart').getContext('2d');
    new Chart(npkCtx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [
                {
                    label: 'Nitrogen (N)',
                    data: nitrogen,
                    borderColor: '#36a2eb',
                    backgroundColor: '#36a2eb33',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: false
                },
                {
                    label: 'Phosphorus (P)',
                    data: phosphorus,
                    borderColor: '#ff6384',
                    backgroundColor: '#ff638433',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: false
                },
                {
                    label: 'Potassium (K)',
                    data: potassium,
                    borderColor: '#4bc0c0',
                    backgroundColor: '#4bc0c033',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: false,
                    hidden: true  // Hide by default due to scale difference
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'NPK Nutrient Levels Over Time'
                },
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw + ' ppm';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Level (ppm)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Test Date'
                    }
                }
            }
        }
    });
    
    // pH and Organic Matter Chart
    const phomCtx = document.getElementById('ph-om-chart').getContext('2d');
    new Chart(phomCtx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [
                {
                    label: 'pH Level',
                    data: ph,
                    borderColor: '#ffcd56',
                    backgroundColor: '#ffcd5633',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: false,
                    yAxisID: 'y'
                },
                {
                    label: 'Organic Matter (%)',
                    data: organicMatter,
                    borderColor: '#9966ff',
                    backgroundColor: '#9966ff33',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: false,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'pH and Organic Matter Levels Over Time'
                },
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.dataset.label === 'pH Level') {
                                return context.dataset.label + ': ' + context.raw;
                            } else {
                                return context.dataset.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    min: 0,
                    max: 14,
                    title: {
                        display: true,
                        text: 'pH Level'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    min: 0,
                    max: Math.max(...organicMatter) * 1.2,
                    title: {
                        display: true,
                        text: 'Organic Matter (%)'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Test Date'
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php
// Helper functions for this page

// Get the test ID by field ID and date
function getTestIdByDate($pdo, $field_id, $date) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM soil_nutrients WHERE field_id = ? AND test_date = ? LIMIT 1");
        $stmt->execute([$field_id, $date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : 0;
    } catch(PDOException $e) {
        error_log("Error getting test ID by date: " . $e->getMessage());
        return 0;
    }
}

// Get CSS class for nutrient values
function getNutrientClass($nutrient, $value) {
    switch($nutrient) {
        case 'nitrogen':
            if ($value < 10) return 'text-danger';
            if ($value < 20) return 'text-warning';
            if ($value <= 30) return 'text-success';
            return 'text-primary';
        
        case 'phosphorus':
            if ($value < 15) return 'text-danger';
            if ($value < 30) return 'text-warning';
            if ($value <= 40) return 'text-success';
            return 'text-primary';
            
        case 'potassium':
            if ($value < 100) return 'text-danger';
            if ($value < 150) return 'text-warning';
            if ($value <= 250) return 'text-success';
            return 'text-primary';
            
        default:
            return '';
    }
}

// Get CSS class for progress bar
function getNutrientProgressBarClass($nutrient, $value) {
    switch($nutrient) {
        case 'nitrogen':
            if ($value < 10) return 'bg-danger';
            if ($value < 20) return 'bg-warning';
            if ($value <= 30) return 'bg-success';
            return 'bg-primary';
        
        case 'phosphorus':
            if ($value < 15) return 'bg-danger';
            if ($value < 30) return 'bg-warning';
            if ($value <= 40) return 'bg-success';
            return 'bg-primary';
            
        case 'potassium':
            if ($value < 100) return 'bg-danger';
            if ($value < 150) return 'bg-warning';
            if ($value <= 250) return 'bg-success';
            return 'bg-primary';
            
        default:
            return 'bg-secondary';
    }
}

// Get CSS class for pH value
function getPHClass($value) {
    if ($value < 5.5) return 'text-danger';
    if ($value < 6.0) return 'text-warning';
    if ($value <= 7.0) return 'text-success';
    if ($value <= 7.5) return 'text-warning';
    return 'text-danger';
}

// Get CSS class for pH progress bar
function getPHProgressBarClass($value) {
    if ($value < 5.5) return 'bg-danger';
    if ($value < 6.0) return 'bg-warning';
    if ($value <= 7.0) return 'bg-success';
    if ($value <= 7.5) return 'bg-warning';
    return 'bg-danger';
}

// Get CSS class for organic matter
function getOrganicMatterClass($value) {
    if ($value < 2.0) return 'text-danger';
    if ($value < 3.0) return 'text-warning';
    if ($value <= 5.0) return 'text-success';
    return 'text-primary';
}

// Get CSS class for organic matter progress bar
function getOrganicMatterProgressBarClass($value) {
    if ($value < 2.0) return 'bg-danger';
    if ($value < 3.0) return 'bg-warning';
    if ($value <= 5.0) return 'bg-success';
    return 'bg-primary';
}

// Get CSS class for secondary nutrients
function getSecondaryNutrientClass($nutrient, $value) {
    if (empty($value)) return 'text-muted';
    
    switch($nutrient) {
        case 'calcium':
            if ($value < 1000) return 'text-danger';
            if ($value < 1200) return 'text-warning';
            if ($value <= 1500) return 'text-success';
            return 'text-primary';
            
        case 'magnesium':
            if ($value < 150) return 'text-danger';
            if ($value < 200) return 'text-warning';
            if ($value <= 250) return 'text-success';
            return 'text-primary';
            
        case 'sulfur':
            if ($value < 10) return 'text-danger';
            if ($value < 12) return 'text-warning';
            if ($value <= 16) return 'text-success';
            return 'text-primary';
            
        default:
            return '';
    }
}

// Generate recommendations based on soil test results
function getNutrientRecommendations($record) {
    $recommendations = [];
    
    // Nitrogen recommendations
    if ($record['nitrogen'] < 10) {
        $recommendations[] = [
            'type' => 'danger',
            'title' => 'Critical Nitrogen Deficiency',
            'message' => 'Apply nitrogen-rich fertilizer immediately. Consider applications of urea, ammonium nitrate, or ammonium sulfate.'
        ];
    } elseif ($record['nitrogen'] < 20) {
        $recommendations[] = [
            'type' => 'warning',
            'title' => 'Low Nitrogen Levels',
            'message' => 'Add moderate nitrogen fertilization. Consider slow-release nitrogen sources for sustained nutrient availability.'
        ];
    }
    
    // Phosphorus recommendations
    if ($record['phosphorus'] < 15) {
        $recommendations[] = [
            'type' => 'danger',
            'title' => 'Critical Phosphorus Deficiency',
            'message' => 'Apply phosphate fertilizers such as triple superphosphate or diammonium phosphate to correct the deficiency.'
        ];
    } elseif ($record['phosphorus'] < 30) {
        $recommendations[] = [
            'type' => 'warning',
            'title' => 'Low Phosphorus Levels',
            'message' => 'Consider adding phosphorus-containing fertilizers and incorporating organic matter to improve phosphorus availability.'
        ];
    }
    
    // Potassium recommendations
    if ($record['potassium'] < 100) {
        $recommendations[] = [
            'type' => 'danger',
            'title' => 'Critical Potassium Deficiency',
            'message' => 'Apply potassium fertilizers such as potassium chloride or potassium sulfate to correct the deficiency.'
        ];
    } elseif ($record['potassium'] < 150) {
        $recommendations[] = [
            'type' => 'warning',
            'title' => 'Low Potassium Levels',
            'message' => 'Consider adding potassium-containing fertilizers before planting or during crop growth.'
        ];
    }
    
    // pH recommendations
    if ($record['ph_level'] < 5.5) {
        $recommendations[] = [
            'type' => 'danger',
            'title' => 'Critically Acidic Soil',
            'message' => 'Apply agricultural lime to raise pH levels. The highly acidic conditions limit nutrient availability and can be toxic to plants.'
        ];
    } elseif ($record['ph_level'] < 6.0) {
        $recommendations[] = [
            'type' => 'warning',
            'title' => 'Moderately Acidic Soil',
            'message' => 'Consider applying lime to gradually increase soil pH for better nutrient availability.'
        ];
    } elseif ($record['ph_level'] > 7.5) {
        $recommendations[] = [
            'type' => 'warning',
            'title' => 'Alkaline Soil',
            'message' => 'Consider applications of elemental sulfur, acidic organic matter, or acidifying fertilizers to lower pH over time.'
        ];
    }
    
    // Organic matter recommendations
    if ($record['organic_matter'] < 2.0) {
        $recommendations[] = [
            'type' => 'warning',
            'title' => 'Low Organic Matter',
            'message' => 'Incorporate compost, cover crops, and crop residues to build soil organic matter. This will improve soil structure and nutrient cycling.'
        ];
    }
    
    return $recommendations;
}
?>

<?php include 'includes/footer.php'; ?>