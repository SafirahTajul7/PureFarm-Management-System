<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is admin
auth()->checkAdmin();

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirect to soil_treatments page if no valid ID
    header("Location: soil_treatments.php");
    exit();
}

$treatment_id = (int)$_GET['id'];

// Define treatment types
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

// Application methods
$application_methods = [
    'broadcast' => 'Broadcast Spreading',
    'banded' => 'Banded Application',
    'incorporated' => 'Soil Incorporated',
    'foliar' => 'Foliar Application',
    'drip' => 'Drip Irrigation',
    'injection' => 'Soil Injection',
    'other' => 'Other Method'
];

// Fetch treatment data
try {
    $stmt = $pdo->prepare("
        SELECT st.*, f.field_name 
        FROM soil_treatments st
        JOIN fields f ON st.field_id = f.id
        WHERE st.id = ?
    ");
    $stmt->execute([$treatment_id]);
    $treatment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$treatment) {
        // Redirect if treatment not found
        header("Location: soil_treatments.php");
        exit();
    }
    
} catch(PDOException $e) {
    error_log("Error fetching treatment data: " . $e->getMessage());
    // Redirect with error
    header("Location: soil_treatments.php?error=1");
    exit();
}

// Try to fetch soil test results before and after the treatment
try {
    // Fetch the most recent soil test before the treatment
    $stmt = $pdo->prepare("
        SELECT sn.*
        FROM soil_nutrients sn
        WHERE sn.field_id = ? AND sn.test_date < ?
        ORDER BY sn.test_date DESC
        LIMIT 1
    ");
    $stmt->execute([$treatment['field_id'], $treatment['application_date']]);
    $before_test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch the first soil test after the treatment
    $stmt = $pdo->prepare("
        SELECT sn.*
        FROM soil_nutrients sn
        WHERE sn.field_id = ? AND sn.test_date > ?
        ORDER BY sn.test_date ASC
        LIMIT 1
    ");
    $stmt->execute([$treatment['field_id'], $treatment['application_date']]);
    $after_test = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Error fetching soil test data: " . $e->getMessage());
    $before_test = null;
    $after_test = null;
}

// Set page title and include header
$pageTitle = 'View Soil Treatment';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between">
        <h2><i class="fas fa-flask"></i> View Soil Treatment</h2>
        <div>
            <a href="edit_soil_treatment.php?id=<?php echo $treatment_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="soil_treatments.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <!-- Treatment Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i> Treatment Information
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Field</h5>
                            <p><?php echo htmlspecialchars($treatment['field_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Application Date</h5>
                            <p><?php echo date('F j, Y', strtotime($treatment['application_date'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Treatment Type</h5>
                            <p>
                                <?php 
                                    $treatment_type = $treatment['treatment_type'];
                                    $label = isset($treatment_types[$treatment_type]) ? $treatment_types[$treatment_type] : ucfirst($treatment_type);
                                    echo htmlspecialchars($label);
                                ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h5>Product</h5>
                            <p><?php echo htmlspecialchars($treatment['product_name']); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Application Rate</h5>
                            <p><?php echo htmlspecialchars($treatment['application_rate']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Application Method</h5>
                            <p>
                                <?php 
                                    $method = $treatment['application_method'];
                                    if (empty($method)) {
                                        echo '<em>Not specified</em>';
                                    } else {
                                        $method_label = isset($application_methods[$method]) ? $application_methods[$method] : ucfirst($method);
                                        echo htmlspecialchars($method_label);
                                    }
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Target pH</h5>
                            <p>
                                <?php 
                                    echo !empty($treatment['target_ph']) ? htmlspecialchars($treatment['target_ph']) : '<em>Not specified</em>';
                                ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h5>Target Nutrient</h5>
                            <p>
                                <?php 
                                    echo !empty($treatment['target_nutrient']) ? htmlspecialchars($treatment['target_nutrient']) : '<em>Not specified</em>';
                                ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Cost per Acre</h5>
                            <p>$<?php echo number_format((float)$treatment['cost_per_acre'], 2); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Total Cost</h5>
                            <p>$<?php echo number_format((float)$treatment['total_cost'], 2); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h5>Weather Conditions</h5>
                            <p>
                                <?php 
                                    echo !empty($treatment['weather_conditions']) ? htmlspecialchars($treatment['weather_conditions']) : '<em>Not recorded</em>';
                                ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h5>Date Created</h5>
                            <p><?php echo date('F j, Y g:i a', strtotime($treatment['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <h5>Notes</h5>
                            <p>
                                <?php 
                                    echo !empty($treatment['notes']) ? nl2br(htmlspecialchars($treatment['notes'])) : '<em>No notes recorded</em>';
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Soil Test Comparison -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-line me-1"></i> Before & After Comparison
                </div>
                <div class="card-body">
                    <?php if ($before_test && $after_test): ?>
                        <h5 class="card-title">Soil Test Results</h5>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Nutrient</th>
                                    <th>Before</th>
                                    <th>After</th>
                                    <th>Change</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($treatment['treatment_type'] == 'lime' || $treatment['treatment_type'] == 'sulfur'): ?>
                                <tr>
                                    <td>pH Level</td>
                                    <td><?php echo number_format($before_test['ph_level'], 1); ?></td>
                                    <td><?php echo number_format($after_test['ph_level'], 1); ?></td>
                                    <td>
                                        <?php 
                                            $ph_change = $after_test['ph_level'] - $before_test['ph_level'];
                                            $change_class = $ph_change > 0 ? 'text-success' : ($ph_change < 0 ? 'text-danger' : '');
                                            echo '<span class="' . $change_class . '">' . ($ph_change > 0 ? '+' : '') . number_format($ph_change, 1) . '</span>';
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($treatment['treatment_type'] == 'compost' || $treatment['treatment_type'] == 'manure' || $treatment['treatment_type'] == 'biochar'): ?>
                                <tr>
                                    <td>Organic Matter</td>
                                    <td><?php echo $before_test['organic_matter']; ?>%</td>
                                    <td><?php echo $after_test['organic_matter']; ?>%</td>
                                    <td>
                                        <?php 
                                            $om_change = $after_test['organic_matter'] - $before_test['organic_matter'];
                                            $change_class = $om_change > 0 ? 'text-success' : ($om_change < 0 ? 'text-danger' : '');
                                            echo '<span class="' . $change_class . '">' . ($om_change > 0 ? '+' : '') . number_format($om_change, 1) . '%</span>';
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($treatment['treatment_type'] == 'gypsum' || $treatment['treatment_type'] == 'lime'): ?>
                                <tr>
                                    <td>Calcium (Ca)</td>
                                    <td><?php echo $before_test['calcium']; ?> ppm</td>
                                    <td><?php echo $after_test['calcium']; ?> ppm</td>
                                    <td>
                                        <?php 
                                            $ca_change = $after_test['calcium'] - $before_test['calcium'];
                                            $change_class = $ca_change > 0 ? 'text-success' : ($ca_change < 0 ? 'text-danger' : '');
                                            echo '<span class="' . $change_class . '">' . ($ca_change > 0 ? '+' : '') . $ca_change . ' ppm</span>';
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($treatment['treatment_type'] == 'gypsum' || $treatment['treatment_type'] == 'sulfur'): ?>
                                <tr>
                                    <td>Sulfur (S)</td>
                                    <td><?php echo $before_test['sulfur']; ?> ppm</td>
                                    <td><?php echo $after_test['sulfur']; ?> ppm</td>
                                    <td>
                                        <?php 
                                            $s_change = $after_test['sulfur'] - $before_test['sulfur'];
                                            $change_class = $s_change > 0 ? 'text-success' : ($s_change < 0 ? 'text-danger' : '');
                                            echo '<span class="' . $change_class . '">' . ($s_change > 0 ? '+' : '') . $s_change . ' ppm</span>';
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <!-- Show NPK changes for manure and compost treatments -->
                                <?php if ($treatment['treatment_type'] == 'manure' || $treatment['treatment_type'] == 'compost'): ?>
                                <tr>
                                    <td>Nitrogen (N)</td>
                                    <td><?php echo $before_test['nitrogen']; ?> ppm</td>
                                    <td><?php echo $after_test['nitrogen']; ?> ppm</td>
                                    <td>
                                        <?php 
                                            $n_change = $after_test['nitrogen'] - $before_test['nitrogen'];
                                            $change_class = $n_change > 0 ? 'text-success' : ($n_change < 0 ? 'text-danger' : '');
                                            echo '<span class="' . $change_class . '">' . ($n_change > 0 ? '+' : '') . $n_change . ' ppm</span>';
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Phosphorus (P)</td>
                                    <td><?php echo $before_test['phosphorus']; ?> ppm</td>
                                    <td><?php echo $after_test['phosphorus']; ?> ppm</td>
                                    <td>
                                        <?php 
                                            $p_change = $after_test['phosphorus'] - $before_test['phosphorus'];
                                            $change_class = $p_change > 0 ? 'text-success' : ($p_change < 0 ? 'text-danger' : '');
                                            echo '<span class="' . $change_class . '">' . ($p_change > 0 ? '+' : '') . $p_change . ' ppm</span>';
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Potassium (K)</td>
                                    <td><?php echo $before_test['potassium']; ?> ppm</td>
                                    <td><?php echo $after_test['potassium']; ?> ppm</td>
                                    <td>
                                        <?php 
                                            $k_change = $after_test['potassium'] - $before_test['potassium'];
                                            $change_class = $k_change > 0 ? 'text-success' : ($k_change < 0 ? 'text-danger' : '');
                                            echo '<span class="' . $change_class . '">' . ($k_change > 0 ? '+' : '') . $k_change . ' ppm</span>';
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="small text-muted mt-2">
                            <p>Before test: <?php echo date('M d, Y', strtotime($before_test['test_date'])); ?><br>
                            After test: <?php echo date('M d, Y', strtotime($after_test['test_date'])); ?></p>
                        </div>
                    <?php elseif ($before_test): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Soil test data available before treatment, but no tests found after application.
                        </div>
                    <?php elseif ($after_test): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Soil test data available after treatment, but no tests found before application.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> No soil test data available for this field before or after the treatment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recommendations Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-lightbulb me-1"></i> Recommendations
                </div>
                <div class="card-body">
                    <?php if ($treatment['treatment_type'] == 'lime'): ?>
                        <div class="alert alert-info">
                            <h6>pH Adjustment</h6>
                            <p>Lime applications typically take 3-6 months to fully affect soil pH. Schedule a follow-up soil test in 3 months to monitor changes.</p>
                        </div>
                    <?php elseif ($treatment['treatment_type'] == 'sulfur'): ?>
                        <div class="alert alert-info">
                            <h6>pH Reduction</h6>
                            <p>Elemental sulfur takes time to oxidize and lower pH. Results may take 6-12 months to fully develop. Monitor soil pH regularly.</p>
                        </div>
                    <?php elseif ($treatment['treatment_type'] == 'compost' || $treatment['treatment_type'] == 'manure'): ?>
                        <div class="alert alert-info">
                            <h6>Organic Matter</h6>
                            <p>To maximize benefits, consider implementing minimal tillage practices. The full effect on organic matter content can take multiple seasons.</p>
                        </div>
                    <?php elseif ($treatment['treatment_type'] == 'gypsum'): ?>
                        <div class="alert alert-info">
                            <h6>Soil Structure</h6>
                            <p>Gypsum improves soil structure gradually. Expect improvements in water infiltration and reduced compaction over time.</p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <h6>Monitoring</h6>
                            <p>Schedule regular soil tests to track nutrient levels and observe treatment effectiveness over time.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>