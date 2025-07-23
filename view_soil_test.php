<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/soil_test_manager.php';

// Check if user is admin
auth()->checkAdmin();

// Create soil test manager
$soilTestManager = new SoilTestManager($pdo);

// Get soil test ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if ID is valid
if ($id <= 0) {
    header('Location: soil_tests.php');
    exit;
}

// Fetch soil test data
$soil_test = $soilTestManager->getSoilTest($id);

// If no record found, redirect back to soil_tests
if (!$soil_test) {
    header('Location: soil_tests.php?error=not_found');
    exit;
}

// Get soil health status
$soil_health = $soilTestManager->getSoilHealthStatus($soil_test);

// Get recommendations based on soil test results
$recommendations = $soilTestManager->getRecommendations($soil_test);

$pageTitle = 'View Soil Test';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-flask"></i> Soil Test Details</h2>
        <div class="action-buttons">
            <a href="soil_tests.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Soil Tests
            </a>
            <a href="edit_soil_test.php?id=<?php echo $id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Soil Test Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Test Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Field:</strong> <?php echo htmlspecialchars($soil_test['field_name']); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($soil_test['location']); ?></p>
                            <p><strong>Test Date:</strong> <?php echo date('F d, Y', strtotime($soil_test['test_date'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <div class="soil-health-indicator text-center p-3 border rounded bg-light mb-3">
                                <h6>Overall Soil Health</h6>
                                <div class="mt-2">
                                    <span class="badge bg-<?php echo $soil_health['class']; ?> p-2 fs-6">
                                        <i class="fas fa-<?php echo $soil_health['icon']; ?> me-1"></i> 
                                        <?php echo $soil_health['status']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Soil Parameters Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar me-2"></i>Soil Parameters</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">pH Level:</th>
                                    <td>
                                        <?php 
                                            $ph = $soil_test['ph_level'];
                                            $ph_class = '';
                                            if ($ph < 6.0) $ph_class = 'text-danger';
                                            elseif ($ph > 7.5) $ph_class = 'text-warning';
                                            else $ph_class = 'text-success';
                                            echo "<span class=\"$ph_class fw-bold\">$ph</span>";
                                        ?>
                                        <div class="progress mt-1" style="height: 5px;">
                                            <div class="progress-bar bg-<?php echo $ph_class ? str_replace('text-', '', $ph_class) : 'secondary'; ?>" 
                                                role="progressbar" 
                                                style="width: <?php echo min(($ph / 14) * 100, 100); ?>%" 
                                                aria-valuenow="<?php echo $ph; ?>" 
                                                aria-valuemin="0" 
                                                aria-valuemax="14">
                                            </div>
                                        </div>
                                        <small class="text-muted">Optimal Range: 6.0-7.5</small>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Moisture:</th>
                                    <td>
                                        <?php echo $soil_test['moisture_percentage'] ? $soil_test['moisture_percentage'] . '%' : 'Not tested'; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Temperature:</th>
                                    <td>
                                        <?php echo $soil_test['temperature'] ? $soil_test['temperature'] . '°C' : 'Not tested'; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Organic Matter:</th>
                                    <td>
                                        <?php echo $soil_test['organic_matter'] ? $soil_test['organic_matter'] . '%' : 'Not tested'; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="40%">Nitrogen:</th>
                                    <td>
                                        <?php 
                                            switch($soil_test['nitrogen_level']) {
                                                case 'Low': echo '<span class="badge bg-danger">Low</span>'; break;
                                                case 'Medium': echo '<span class="badge bg-warning text-dark">Medium</span>'; break;
                                                case 'High': echo '<span class="badge bg-success">High</span>'; break;
                                                default: echo 'Not tested';
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Phosphorus:</th>
                                    <td>
                                        <?php 
                                            switch($soil_test['phosphorus_level']) {
                                                case 'Low': echo '<span class="badge bg-danger">Low</span>'; break;
                                                case 'Medium': echo '<span class="badge bg-warning text-dark">Medium</span>'; break;
                                                case 'High': echo '<span class="badge bg-success">High</span>'; break;
                                                default: echo 'Not tested';
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Potassium:</th>
                                    <td>
                                        <?php 
                                            switch($soil_test['potassium_level']) {
                                                case 'Low': echo '<span class="badge bg-danger">Low</span>'; break;
                                                case 'Medium': echo '<span class="badge bg-warning text-dark">Medium</span>'; break;
                                                case 'High': echo '<span class="badge bg-success">High</span>'; break;
                                                default: echo 'Not tested';
                                            }
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($soil_test['notes'])): ?>
            <!-- Notes Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-sticky-note me-2"></i>Notes</h5>
                </div>
                <div class="card-body">
                    <p><?php echo nl2br(htmlspecialchars($soil_test['notes'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <!-- Recommendations Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-lightbulb me-2"></i>Recommendations</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recommendations)): ?>
                        <div class="alert alert-info">
                            No specific recommendations available.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recommendations as $recommendation): ?>
                        <div class="alert alert-<?php echo $recommendation['type']; ?> mb-3">
                            <h6><i class="fas fa-<?php echo $recommendation['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i><?php echo $recommendation['title']; ?></h6>
                            <p class="mb-0"><?php echo $recommendation['description']; ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-tools me-2"></i>Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit_soil_test.php?id=<?php echo $id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Edit This Test
                        </a>
                        <a href="add_soil_test.php" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Add New Test
                        </a>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#printModal" class="btn btn-outline-secondary">
                            <i class="fas fa-print me-2"></i>Print Report
                        </a>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#deleteModal" class="btn btn-outline-danger">
                            <i class="fas fa-trash me-2"></i>Delete Test
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Modal -->
<div class="modal fade" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="printModalLabel">Print Soil Test Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>What would you like to include in the printed report?</p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="print_basic_info" checked>
                    <label class="form-check-label" for="print_basic_info">
                        Basic Information
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="print_soil_params" checked>
                    <label class="form-check-label" for="print_soil_params">
                        Soil Parameters
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="print_recommendations" checked>
                    <label class="form-check-label" for="print_recommendations">
                        Recommendations
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="" id="print_notes">
                    <label class="form-check-label" for="print_notes">
                        Notes
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="window.print();">Print Report</button>
            </div>
        </div>
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
                Are you sure you want to delete this soil test record for <?php echo htmlspecialchars($soil_test['field_name']); ?> taken on <?php echo date('M d, Y', strtotime($soil_test['test_date'])); ?>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="soil_tests.php?delete=<?php echo $id; ?>" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<style>
    .main-content {
        padding-bottom: 60px;
    }
    
    .card {
        margin-top: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .card-body {
        padding: 25px;
    }
    
    .table th {
        font-weight: 600;
        color: #495057;
    }
    
    .soil-health-indicator {
        background-color: #f8f9fa;
        border-color: #dee2e6;
    }
    
    @media print {
        .action-buttons, 
        .btn, 
        .modal,
        .footer {
            display: none !important;
        }
        
        .card {
            border: 1px solid #ddd;
            box-shadow: none;
            margin-bottom: 15px;
        }
        
        .container {
            width: 100%;
            max-width: 100%;
        }
        
        body {
            font-size: 12pt;
        }
        
        h2 {
            font-size: 16pt;
        }
        
        h5 {
            font-size: 14pt;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configure print options
    const printButton = document.querySelector('#printModal .btn-primary');
    if (printButton) {
        printButton.addEventListener('click', function() {
            const includeBasicInfo = document.getElementById('print_basic_info').checked;
            const includeSoilParams = document.getElementById('print_soil_params').checked;
            const includeRecommendations = document.getElementById('print_recommendations').checked;
            const includeNotes = document.getElementById('print_notes').checked;
            
            // You can extend this to actually hide elements before printing
            // This is a simplified approach - in a real implementation you'd
            // want to add print-specific CSS classes to control visibility
            
            window.print();
            
            // Hide the modal after printing
            const printModal = bootstrap.Modal.getInstance(document.getElementById('printModal'));
            if (printModal) {
                printModal.hide();
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>