
<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Fetch pesticide data
try {
    // Get pesticide types directly from pesticide_types table
    $stmt = $pdo->prepare("
        SELECT id, name, type, active_ingredients, safe_handling, withholding_period
        FROM pesticide_types
        ORDER BY type, name
    ");
    
    $stmt->execute();
    $pesticide_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all pesticide applications with crop information
    $stmt = $pdo->prepare("
        SELECT p.*, c.crop_name, f.field_name, pt.name as pesticide_name, pt.type as pesticide_type
        FROM pesticide_applications p
        JOIN crops c ON p.crop_id = c.id
        JOIN fields f ON c.field_id = f.id
        JOIN pesticide_types pt ON p.pesticide_type_id = pt.id
        ORDER BY p.application_date DESC
    ");
    $stmt->execute();
    $pesticide_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active crop issues
    $stmt = $pdo->prepare("
        SELECT ci.*, c.crop_name, f.field_name
        FROM crop_issues ci
        JOIN crops c ON ci.crop_id = c.id
        JOIN fields f ON c.field_id = f.id
        WHERE (ci.resolved = 0 OR ci.resolved IS NULL)
        ORDER BY ci.date_identified DESC
    ");
    $stmt->execute();
    $active_issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Error fetching pesticide data: " . $e->getMessage());
    // Set default values in case of error
    $pesticide_applications = [];
    $active_issues = [];
    $pesticide_types = [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_application') {
                // Insert new pesticide application
                $stmt = $pdo->prepare("
                    INSERT INTO pesticide_applications 
                    (crop_id, pesticide_type_id, application_date, quantity_used, application_method, target_pest, notes, weather_conditions, operator_name) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['crop_id'],
                    $_POST['pesticide_type_id'],
                    $_POST['application_date'],
                    $_POST['quantity_used'],
                    $_POST['application_method'],
                    $_POST['target_pest'],
                    $_POST['notes'],
                    $_POST['weather_conditions'],
                    $_POST['operator_name']
                ]);
                
                // If this is connected to a crop issue, update its status
                if (!empty($_POST['issue_id'])) {
                    $stmt = $pdo->prepare("
                        UPDATE crop_issues 
                        SET treatment_applied = 1,
                            resolved = 1,
                            resolution_date = ?,
                            notes = CONCAT(IFNULL(notes, ''), '\n', ?)
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['application_date'],
                        'Applied ' . $_POST['quantity_used'] . ' of pesticide using ' . $_POST['application_method'] . '. ' . $_POST['notes'],
                        $_POST['issue_id']
                    ]);
                }
                
                $success_message = "Pesticide application recorded successfully!";
                
            } elseif ($_POST['action'] === 'report_issue') {
                // Insert new crop issue
                $stmt = $pdo->prepare("
                    INSERT INTO crop_issues 
                    (crop_id, issue_type, description, date_identified, severity, affected_area, treatment_applied, resolved, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?)
                ");
                $stmt->execute([
                    $_POST['crop_id'],
                    $_POST['issue_type'],
                    $_POST['issue_name'],
                    $_POST['date_identified'],
                    $_POST['severity'],
                    $_POST['affected_area'],
                    $_POST['notes']
                ]);
                
                $success_message = "Pest/disease issue reported successfully!";
            }
        } catch(PDOException $e) {
            error_log("Error processing pesticide form: " . $e->getMessage());
            $error_message = "An error occurred while processing your request: " . $e->getMessage();
        }
        
        // Refresh data after form submission
        header("Location: pesticide_management.php?success=true");
        exit;
    }
}

// Get list of crops for dropdown
try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.crop_name, f.field_name 
        FROM crops c
        JOIN fields f ON c.field_id = f.id
        WHERE c.status = 'active'
        ORDER BY f.field_name, c.crop_name
    ");
    $stmt->execute();
    $crops = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching crops for dropdown: " . $e->getMessage());
    $crops = [];
}

// Count applications by type
$herbicide_count = 0;
$insecticide_count = 0;
$fungicide_count = 0;

foreach ($pesticide_applications as $app) {
    $type = strtolower($app['pesticide_type']);
    if (strpos($type, 'herbicide') !== false) {
        $herbicide_count++;
    } elseif (strpos($type, 'insecticide') !== false) {
        $insecticide_count++;
    } elseif (strpos($type, 'fungicide') !== false) {
        $fungicide_count++;
    }
}

$pageTitle = 'Pesticide Management';
include 'includes/header.php';
?>

<div class="main-content">    <div class="page-header">
        <h2><i class="fas fa-spray-can"></i> Pesticide Management</h2>
        <div class="action-buttons">
            <button type="button" class="btn btn-primary" id="recordApplicationBtn">
                <i class="fas fa-plus"></i> Record Application
            </button>
            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#reportIssueModal">
                <i class="fas fa-bug"></i> Report Pest/Disease
            </button>
            <button class="btn btn-secondary" onclick="location.href='crop_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Crop Management
            </button>
        </div>
    </div>

    <?php if (isset($success_message) || isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo isset($success_message) ? $success_message : "Operation completed successfully!"; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="icon-box bg-danger">
                            <i class="fas fa-bug"></i>
                        </div>
                        <h5 class="card-title ms-3">Active Issues</h5>
                    </div>
                    <h3 class="card-text"><?php echo count($active_issues); ?></h3>
                    <p class="text-muted">Requiring treatment</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="icon-box bg-success">
                            <i class="fas fa-leaf"></i>
                        </div>
                        <h5 class="card-title ms-3">Herbicides</h5>
                    </div>
                    <h3 class="card-text"><?php echo $herbicide_count; ?></h3>
                    <p class="text-muted">Applications this season</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="icon-box bg-info">
                            <i class="fas fa-spider"></i>
                        </div>
                        <h5 class="card-title ms-3">Insecticides</h5>
                    </div>
                    <h3 class="card-text"><?php echo $insecticide_count; ?></h3>
                    <p class="text-muted">Applications this season</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="icon-box bg-warning">
                            <i class="fas fa-seedling"></i>
                        </div>
                        <h5 class="card-title ms-3">Fungicides</h5>
                    </div>
                    <h3 class="card-text"><?php echo $fungicide_count; ?></h3>
                    <p class="text-muted">Applications this season</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Pest/Disease Issues -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-exclamation-triangle text-danger"></i> Active Pest & Disease Issues</h5>
            <a href="pest_disease_monitoring.php" class="btn btn-sm btn-outline-primary">View All Issues</a>
        </div>

        <div class="card-body">
            <?php if (empty($active_issues)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> No active pest or disease issues reported. Keep monitoring your crops!
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Crop</th>
                                <th>Issue Type</th>
                                <th>Description</th>
                                <th>Severity</th>
                                <th>Identified</th>
                                <th>Affected Area</th>
                                <th>Treatment Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_issues as $issue): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($issue['field_name']); ?></td>
                                    <td><?php echo htmlspecialchars($issue['crop_name']); ?></td>
                                    <td>
                                        <?php if ($issue['issue_type'] === 'pest'): ?>
                                            <span class="badge bg-danger">Pest</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Disease</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($issue['description']); ?></td>
                                    <td>
                                        <?php 
                                        switch ($issue['severity']) {
                                            case 'low':
                                                echo '<span class="badge bg-success">Low</span>';
                                                break;
                                            case 'medium':
                                                echo '<span class="badge bg-warning">Medium</span>';
                                                break;
                                            case 'high':
                                                echo '<span class="badge bg-danger">High</span>';
                                                break;
                                            default:
                                                echo '<span class="badge bg-secondary">Unknown</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($issue['date_identified']); ?></td>
                                    <td><?php echo htmlspecialchars($issue['affected_area']); ?></td>
                                    <td>
                                        <?php if (!empty($issue['treatment_applied'])): ?>
                                            <span class="badge bg-success">Treated</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (empty($issue['treatment_applied'])): ?>
                                            <button class="btn btn-sm btn-primary treat-issue" data-bs-toggle="modal" data-bs-target="#addApplicationModal"
                                                    data-issue-id="<?php echo $issue['id']; ?>"
                                                    data-crop-id="<?php echo $issue['crop_id']; ?>"
                                                    data-crop-name="<?php echo htmlspecialchars($issue['crop_name']); ?>"
                                                    data-field-name="<?php echo htmlspecialchars($issue['field_name']); ?>"
                                                    data-issue-name="<?php echo htmlspecialchars($issue['description']); ?>"
                                                    data-issue-type="<?php echo htmlspecialchars($issue['issue_type']); ?>">
                                                <i class="fas fa-syringe"></i> Treat
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pesticide Application History -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-history text-primary"></i> Pesticide Application History</h5>
            
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Field</th>
                            <th>Crop</th>
                            <th>Pesticide</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Target</th>
                            <th>Method</th>
                            <th>Operator</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pesticide_applications)): ?>
                            <tr>
                                <td colspan="10" class="text-center">No pesticide applications recorded</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pesticide_applications as $app): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($app['application_date']); ?></td>
                                    <td><?php echo htmlspecialchars($app['field_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['crop_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['pesticide_name']); ?></td>
                                    <td>
                                        <?php 
                                        $type = strtolower($app['pesticide_type']);
                                        if (strpos($type, 'herbicide') !== false): ?>
                                            <span class="badge bg-success">Herbicide</span>
                                        <?php elseif (strpos($type, 'insecticide') !== false): ?>
                                            <span class="badge bg-info">Insecticide</span>
                                        <?php elseif (strpos($type, 'fungicide') !== false): ?>
                                            <span class="badge bg-warning">Fungicide</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($app['pesticide_type']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($app['quantity_used']); ?></td>
                                    <td><?php echo htmlspecialchars($app['target_pest']); ?></td>
                                    <td><?php echo htmlspecialchars($app['application_method']); ?></td>
                                    <td><?php echo htmlspecialchars($app['operator_name']); ?></td>
                                    <td>
                                    <button class="btn btn-sm btn-info view-application" 
                                            data-application-id="<?php echo $app['id']; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Safe Handling Guidelines -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-shield-alt text-success"></i> Safe Handling Guidelines</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="guideline-card">
                        <div class="guideline-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h4>Personal Protective Equipment (PPE)</h4>
                        <ul>
                            <li>Always wear appropriate PPE: gloves, masks, eye protection, and coveralls.</li>
                            <li>Inspect PPE for damage before each use.</li>
                            <li>Wash PPE separately from regular laundry.</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="guideline-card">
                        <div class="guideline-icon">
                            <i class="fas fa-wind"></i>
                        </div>
                        <h4>Weather Considerations</h4>
                        <ul>
                            <li>Avoid application during windy conditions (wind speed > 10 mph).</li>
                            <li>Do not apply before expected rainfall.</li>
                            <li>Apply early morning or late evening to reduce drift and evaporation.</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="guideline-card">
                        <div class="guideline-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <h4>Withholding Periods</h4>
                        <ul>
                            <li>Strictly observe withholding periods before harvest.</li>
                            <li>Keep records of application dates to ensure compliance.</li>
                            <li>Different crops may have different withholding requirements.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Record Pesticide Application Modal -->
<div class="modal fade" id="addApplicationModal" tabindex="-1" aria-labelledby="addApplicationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addApplicationModalLabel"><i class="fas fa-spray-can"></i> Record Pesticide Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="pesticide_management.php" method="POST">
                <input type="hidden" name="action" value="add_application">
                <input type="hidden" name="issue_id" id="issue_id" value="">
                <div class="modal-body">
                    <div class="row mb-3" id="issue-info-row" style="display: none;">
                        <div class="col-md-12">
                            <div class="alert alert-warning">
                                <strong>Treating Issue:</strong> <span id="issue-info"></span>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="crop_id" class="form-label">Crop</label>
                            <select class="form-select" id="crop_id" name="crop_id" required>
                                <option value="">Select Crop</option>
                                <?php foreach ($crops as $crop): ?>
                                    <option value="<?php echo $crop['id']; ?>">
                                        <?php echo htmlspecialchars($crop['field_name'] . ' - ' . $crop['crop_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="pesticide_type_id" class="form-label">Pesticide Type</label>
                            <select class="form-select" id="pesticide_type_id" name="pesticide_type_id" required>
                                <option value="">Select Pesticide</option>
                                <?php foreach ($pesticide_types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                            data-type="<?php echo htmlspecialchars($type['type']); ?>"
                                            data-safe-handling="<?php echo htmlspecialchars($type['safe_handling']); ?>"
                                            data-withholding-period="<?php echo htmlspecialchars($type['withholding_period']); ?>"
                                            data-active-ingredients="<?php echo htmlspecialchars($type['active_ingredients']); ?>">
                                        <?php echo htmlspecialchars($type['name'] . ' (' . $type['type'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="application_date" class="form-label">Application Date</label>
                            <input type="date" class="form-control" id="application_date" name="application_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="quantity_used" class="form-label">Quantity Used</label>
                            <input type="text" class="form-control" id="quantity_used" name="quantity_used" 
                                   placeholder="e.g., '2 L/acre'" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="application_method" class="form-label">Application Method</label>
                            <select class="form-select" id="application_method" name="application_method" required>
                                <option value="">Select Method</option>
                                <option value="Spraying">Spraying</option>
                                <option value="Dusting">Dusting</option>
                                <option value="Soil Incorporation">Soil Incorporation</option>
                                <option value="Seed Treatment">Seed Treatment</option>
                                <option value="Spot Treatment">Spot Treatment</option>
                                <option value="Broadcast">Broadcast</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="target_pest" class="form-label">Target Pest/Disease</label>
                            <input type="text" class="form-control" id="target_pest" name="target_pest" 
                                   placeholder="e.g., 'Aphids', 'Powdery Mildew'" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="weather_conditions" class="form-label">Weather Conditions</label>
                            <input type="text" class="form-control" id="weather_conditions" name="weather_conditions" 
                                   placeholder="e.g., 'Clear, 25°C, light breeze'">
                        </div>
                        <div class="col-md-6">
                            <label for="operator_name" class="form-label">Operator Name</label>
                            <input type="text" class="form-control" id="operator_name" name="operator_name" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                     placeholder="Additional information about the application"></textarea>
                        </div>
                    </div>
                    <div class="row mb-3" id="safety-info" style="display: none;">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <strong>Safety Information:</strong>
                                <p id="safe-handling"></p>
                                <p><strong>Withholding Period:</strong> <span id="withholding-period"></span></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Report Pest/Disease Issue Modal -->
<div class="modal fade" id="reportIssueModal" tabindex="-1" aria-labelledby="reportIssueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportIssueModalLabel"><i class="fas fa-bug"></i> Report Pest/Disease Issue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="pesticide_management.php" method="POST">
                <input type="hidden" name="action" value="report_issue">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="crop_id_issue" class="form-label">Crop</label>
                            <select class="form-select" id="crop_id_issue" name="crop_id" required>
                                <option value="">Select Crop</option>
                                <?php foreach ($crops as $crop): ?>
                                    <option value="<?php echo $crop['id']; ?>">
                                        <?php echo htmlspecialchars($crop['field_name'] . ' - ' . $crop['crop_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="issue_type" class="form-label">Issue Type</label>
                            <select class="form-select" id="issue_type" name="issue_type" required>
                                <option value="">Select Type</option>
                                <option value="pest">Pest</option>
                                <option value="disease">Disease</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="issue_name" class="form-label">Issue Name/Description</label>
                            <input type="text" class="form-control" id="issue_name" name="issue_name" 
                                   placeholder="e.g., 'Aphids', 'Powdery Mildew'" required>
                        </div>
                        <div class="col-md-6">
                            <label for="severity" class="form-label">Severity</label>
                            <select class="form-select" id="severity" name="severity" required>
                                <option value="">Select Severity</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_identified" class="form-label">Date Identified</label>
                            <input type="date" class="form-control" id="date_identified" name="date_identified" required>
                        </div>
                        <div class="col-md-6">
                            <label for="affected_area" class="form-label">Affected Area</label>
                            <input type="text" class="form-control" id="affected_area" name="affected_area" 
                                   placeholder="e.g., '30% of field', '5 acres'"  required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="notes_issue" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes_issue" name="notes" rows="3" 
                                     placeholder="Any other relevant information"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Report Issue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 6. Create Safety Guide Modal -->
<div class="modal fade" id="safetyGuideModal" tabindex="-1" aria-labelledby="safetyGuideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="safetyGuideModalLabel"><i class="fas fa-shield-alt"></i> Complete Pesticide Safety Guide</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Safety First:</strong> Always prioritize safety when handling pesticides. Proper precautions protect you, others, and the environment.
                </div>
                
                <ul class="nav nav-tabs" id="safetyTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="ppe-tab" data-bs-toggle="tab" data-bs-target="#ppe" type="button" role="tab" aria-controls="ppe" aria-selected="true">
                            <i class="fas fa-user-shield"></i> PPE
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="application-tab" data-bs-toggle="tab" data-bs-target="#application" type="button" role="tab" aria-controls="application" aria-selected="false">
                            <i class="fas fa-spray-can"></i> Application
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="storage-tab" data-bs-toggle="tab" data-bs-target="#storage" type="button" role="tab" aria-controls="storage" aria-selected="false">
                            <i class="fas fa-warehouse"></i> Storage
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="disposal-tab" data-bs-toggle="tab" data-bs-target="#disposal" type="button" role="tab" aria-controls="disposal" aria-selected="false">
                            <i class="fas fa-trash-alt"></i> Disposal
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="emergency-tab" data-bs-toggle="tab" data-bs-target="#emergency" type="button" role="tab" aria-controls="emergency" aria-selected="false">
                            <i class="fas fa-ambulance"></i> Emergency
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content pt-3" id="safetyTabContent">
                    <div class="tab-pane fade show active" id="ppe" role="tabpanel" aria-labelledby="ppe-tab">
                        <h5>Personal Protective Equipment (PPE)</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Required PPE:</h6>
                                        <ul>
                                            <li><strong>Gloves:</strong> Chemical-resistant gloves (nitrile or neoprene)</li>
                                            <li><strong>Eye Protection:</strong> Safety glasses, goggles, or face shield</li>
                                            <li><strong>Respiratory Protection:</strong> Respirator with appropriate cartridges</li>
                                            <li><strong>Body Protection:</strong> Long-sleeved shirt, long pants, and coveralls</li>
                                            <li><strong>Footwear:</strong> Chemical-resistant boots or shoe covers</li>
                                            <li><strong>Head Protection:</strong> Hat or hood for overhead applications</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">PPE Best Practices:</h6>
                                        <ul>
                                            <li>Inspect all PPE before each use for tears, holes, or other damage</li>
                                            <li>Replace damaged items immediately; do not use compromised PPE</li>
                                            <li>Wear PPE throughout the entire application process</li>
                                            <li>Remove PPE immediately after application is complete</li>
                                            <li>Wash hands thoroughly after removing PPE</li>
                                            <li>Clean reusable PPE according to manufacturer instructions</li>
                                            <li>Store clean PPE away from pesticide storage areas</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="application" role="tabpanel" aria-labelledby="application-tab">
                        <h5>Application Guidelines</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Weather Considerations:</h6>
                                        <ul>
                                            <li><strong>Wind:</strong> Avoid application when wind speeds exceed 10 mph</li>
                                            <li><strong>Rain:</strong> Do not apply before expected rainfall</li>
                                            <li><strong>Temperature:</strong> Check label for temperature restrictions</li>
                                            <li><strong>Time of Day:</strong> Apply early morning or late evening to reduce drift and evaporation</li>
                                            <li><strong>Humidity:</strong> Low humidity can increase drift potential</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Application Equipment:</h6>
                                        <ul>
                                            <li>Calibrate equipment before each use</li>
                                            <li>Check for leaks or damaged parts</li>
                                            <li>Use appropriate nozzles for the target and conditions</li>
                                            <li>Clean equipment thoroughly after use</li>
                                            <li>Never blow or suck on nozzles to unclog them</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Application Best Practices:</h6>
                                        <ul>
                                            <li>Read and follow the pesticide label instructions carefully</li>
                                            <li>Calculate application rates precisely</li>
                                            <li>Mix only the amount needed for immediate use</li>
                                            <li>Keep people and pets away from treated areas for the recommended period</li>
                                            <li>Establish buffer zones near sensitive areas (water sources, beehives, etc.)</li>
                                            <li>Avoid spray drift to non-target areas</li>
                                            <li>Document all applications in a spray record</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Withholding Periods:</h6>
                                        <ul>
                                            <li>Strictly observe pre-harvest intervals (PHIs) before harvesting crops</li>
                                            <li>Keep records of application dates to ensure compliance</li>
                                            <li>Note that different crops may have different withholding requirements</li>
                                            <li>Consider re-entry intervals (REIs) before allowing workers into treated areas</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="storage" role="tabpanel" aria-labelledby="storage-tab">
                        <h5>Storage Guidelines</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Storage Facility Requirements:</h6>
                                        <ul>
                                            <li>Store pesticides in a dedicated, lockable storage area</li>
                                            <li>Storage area should be well-ventilated and dry</li>
                                            <li>Keep storage area away from food, feed, and water sources</li>
                                            <li>Floor should be impermeable to contain spills</li>
                                            <li>Post warning signs clearly marking pesticide storage</li>
                                            <li>Keep fire extinguisher suitable for chemical fires nearby</li>
                                            <li>Keep emergency contact numbers visible</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Storage Best Practices:</h6>
                                        <ul>
                                            <li>Store pesticides in original containers with labels intact</li>
                                            <li>Never store pesticides in food or drink containers</li>
                                            <li>Keep containers tightly closed and off the floor</li>
                                            <li>Segregate pesticides by type (herbicides, insecticides, fungicides)</li>
                                            <li>Keep powders above liquids to prevent contamination from leaks</li>
                                            <li>Rotate stock: use older products first (FIFO - First In, First Out)</li>
                                            <li>Regularly inspect containers for leaks or damage</li>
                                            <li>Maintain an updated inventory of stored pesticides</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="disposal" role="tabpanel" aria-labelledby="disposal-tab">
                        <h5>Disposal Guidelines</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Container Disposal:</h6>
                                        <ul>
                                            <li>Triple rinse empty containers before disposal</li>
                                            <li>Puncture containers to prevent reuse</li>
                                            <li>Follow local regulations for pesticide container disposal</li>
                                            <li>Never burn empty pesticide containers</li>
                                            <li>Use container recycling programs where available</li>
                                            <li>Keep disposal records for audit purposes</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Disposing of Excess Pesticides:</h6>
                                        <ul>
                                            <li>Mix only the amount needed to avoid excess</li>
                                            <li>Apply diluted rinse water to label-approved sites</li>
                                            <li>Never pour pesticides down drains or waterways</li>
                                            <li>Contact local authorities for hazardous waste disposal options</li>
                                            <li>Expired pesticides must be disposed of as hazardous waste</li>
                                            <li>Keep detailed records of all pesticide disposal activities</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="emergency" role="tabpanel" aria-labelledby="emergency-tab">
                        <h5>Emergency Response</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Spill Response:</h6>
                                        <ul>
                                            <li>Keep a spill kit readily available</li>
                                            <li>Control the spill: stop the source if possible</li>
                                            <li>Contain the spill using absorbent materials</li>
                                            <li>Keep people and animals away from the area</li>
                                            <li>Collect and dispose of contaminated materials properly</li>
                                            <li>For large spills, contact emergency services</li>
                                            <li>Report significant spills to relevant authorities</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">First Aid for Pesticide Exposure:</h6>
                                        <ul>
                                            <li><strong>Skin Contact:</strong> Remove contaminated clothing and wash with soap and water for 15-20 minutes</li>
                                            <li><strong>Eye Contact:</strong> Flush eyes with clean water for 15-20 minutes</li>
                                            <li><strong>Inhalation:</strong> Move to fresh air and loosen tight clothing</li>
                                            <li><strong>Ingestion:</strong> Call poison control immediately</li>
                                            <li>Seek medical attention promptly</li>
                                            <li>Bring the pesticide label to medical personnel</li>
                                            <li>Have emergency contact numbers readily available</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="card mb-3">
                                    <div class="card-body bg-info text-white">
                                        <h6 class="card-title">Emergency Contacts:</h6>
                                        <ul>
                                            <li><strong>Poison Control:</strong> [National Poison Control Center Number]</li>
                                            <li><strong>Emergency Services:</strong> 911 or local emergency number</li>
                                            <li><strong>Farm Manager:</strong> [Phone Number]</li>
                                            <li><strong>Local Hospital:</strong> [Phone Number & Address]</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> <strong>Remember:</strong> Always consult the specific pesticide label for product-specific safety instructions. The label is the law!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Guide
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Application Details Modal -->
<div class="modal fade" id="viewApplicationModal" tabindex="-1" aria-labelledby="viewApplicationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewApplicationModalLabel"><i class="fas fa-eye"></i> Pesticide Application Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="application-details-content">
                <!-- Content will be loaded dynamically -->
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Pesticide Management specific styles */
.icon-box {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-box i {
    font-size: 24px;
    color: white;
}

.bg-danger {
    background-color: #e74c3c !important;
}

.bg-success {
    background-color: #2ecc71 !important;
}

.bg-info {
    background-color: #3498db !important;
}

.bg-warning {
    background-color: #f39c12 !important;
}

.guideline-card {
    padding: 20px;
    border-radius: 5px;
    background-color: #f8f9fa;
    height: 100%;
    margin-bottom: 15px;
    border-left: 4px solid #2ecc71;
}

.guideline-icon {
    text-align: center;
    margin-bottom: 15px;
}

.guideline-icon i {
    font-size: 32px;
    color: #2ecc71;
}

.guideline-card h4 {
    font-size: 18px;
    margin-bottom: 15px;
    color: #2ecc71;
}

.guideline-card ul {
    padding-left: 20px;
}

.guideline-card li {
    margin-bottom: 8px;
}

.treat-issue {
    white-space: nowrap;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default dates
    const today = new Date();
    const formattedToday = today.toISOString().split('T')[0];
    
    const applicationDateField = document.getElementById('application_date');
    const dateIdentifiedField = document.getElementById('date_identified');
    
    if (applicationDateField) {
        applicationDateField.value = formattedToday;
    }
    if (dateIdentifiedField) {
        dateIdentifiedField.value = formattedToday;
    }
    
    // Handle pesticide type selection to show safety info
    const pesticideSelect = document.getElementById('pesticide_type_id');
    if (pesticideSelect) {
        pesticideSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const safetyInfo = document.getElementById('safety-info');
            
            if (this.value) {
                safetyInfo.style.display = 'block';
                
                // Use data attributes from the selected option
                const safeHandling = selectedOption.getAttribute('data-safe-handling');
                const withholdingPeriod = selectedOption.getAttribute('data-withholding-period');
                const activeIngredients = selectedOption.getAttribute('data-active-ingredients');
                
                document.getElementById('safe-handling').textContent = safeHandling || 'No specific handling information available.';
                document.getElementById('withholding-period').textContent = withholdingPeriod || 'Not specified';
            } else {
                safetyInfo.style.display = 'none';
            }
        });
    }

    // Handle view application details
    const viewButtons = document.querySelectorAll('.view-application');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const applicationId = this.getAttribute('data-application-id');
            const modal = new bootstrap.Modal(document.getElementById('viewApplicationModal'));
            
            // Show modal with loading spinner
            modal.show();
            
            // Fetch application details
            fetch(`get_pesticide_application.php?id=${applicationId}`)
                .then(response => response.json())
                .then(data => {
                    // Populate modal with data
                    const content = document.getElementById('application-details-content');
                    content.innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Date:</strong> ${data.application_date}</p>
                                <p><strong>Field:</strong> ${data.field_name}</p>
                                <p><strong>Crop:</strong> ${data.crop_name}</p>
                                <p><strong>Pesticide:</strong> ${data.pesticide_name}</p>
                                <p><strong>Type:</strong> ${data.pesticide_type}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Quantity:</strong> ${data.quantity_used}</p>
                                <p><strong>Target:</strong> ${data.target_pest}</p>
                                <p><strong>Method:</strong> ${data.application_method}</p>
                                <p><strong>Operator:</strong> ${data.operator_name}</p>
                                <p><strong>Weather:</strong> ${data.weather_conditions || 'Not specified'}</p>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <h6>Notes:</h6>
                                <p>${data.notes || 'No notes available'}</p>
                            </div>
                        </div>
                    `;
                })
                .catch(error => {
                    const content = document.getElementById('application-details-content');
                    content.innerHTML = `
                        <div class="alert alert-danger">
                            Error loading application details. Please try again.
                        </div>
                    `;
                    console.error('Error fetching application details:', error);
                });
        });
    });
    
    // Handle treating an issue (pre-filling the application form)
    const treatIssueButtons = document.querySelectorAll('.treat-issue');
    treatIssueButtons.forEach(button => {
        button.addEventListener('click', function() {
            const issueId = this.getAttribute('data-issue-id');
            const cropId = this.getAttribute('data-crop-id');
            const cropName = this.getAttribute('data-crop-name');
            const fieldName = this.getAttribute('data-field-name');
            const issueName = this.getAttribute('data-issue-name');
            const issueType = this.getAttribute('data-issue-type');
            
            // Clear any previous modal state
            const modal = new bootstrap.Modal(document.getElementById('addApplicationModal'));
            
            // Set hidden field and show issue info
            document.getElementById('issue_id').value = issueId;
            document.getElementById('issue-info-row').style.display = 'block';
            document.getElementById('issue-info').textContent = 
                `${issueName} (${issueType}) on ${fieldName} - ${cropName}`;
            
            // Pre-select the crop
            document.getElementById('crop_id').value = cropId;
            
            // Pre-fill the target pest field
            document.getElementById('target_pest').value = issueName;
            
            // Pre-select the appropriate pesticide type based on issue type
            const pesticideSelect = document.getElementById('pesticide_type_id');
            const options = pesticideSelect.options;
            
            for (let i = 0; i < options.length; i++) {
                const optionType = options[i].getAttribute('data-type');
                if (optionType) {
                    if ((issueType === 'pest' && optionType.toLowerCase().includes('insecticide')) ||
                        (issueType === 'disease' && optionType.toLowerCase().includes('fungicide'))) {
                        pesticideSelect.selectedIndex = i;
                        // Trigger the change event to show safety info
                        const event = new Event('change');
                        pesticideSelect.dispatchEvent(event);
                        break;
                    }
                }
            }
            
            // Show the modal
            modal.show();
        });
    });

    // Handle Record Application button
    const recordBtn = document.getElementById('recordApplicationBtn');
    if(recordBtn) {
        recordBtn.addEventListener('click', function() {
            // Clear the form for new application
            const form = document.querySelector('#addApplicationModal form');
            if (form) {
                form.reset();
                // Reset hidden fields and hide issue info
                document.getElementById('issue_id').value = '';
                document.getElementById('issue-info-row').style.display = 'none';
                document.getElementById('safety-info').style.display = 'none';
                // Set today's date
                document.getElementById('application_date').value = formattedToday;
            }
            
            const modalElement = document.getElementById('addApplicationModal');
            if(modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            } else {
                console.error('Modal element not found: addApplicationModal');
            }
        });
    }

    // Handle Report Pest/Disease button
    const reportIssueBtn = document.querySelector('[data-bs-target="#reportIssueModal"]');
    if (reportIssueBtn) {
        reportIssueBtn.addEventListener('click', function() {
            const modalElement = document.getElementById('reportIssueModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            } else {
                console.error('Modal element not found: reportIssueModal');
            }
        });
    }

    // Alternative method: Handle all buttons with data-bs-toggle attribute
    const modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]');
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const targetModal = this.getAttribute('data-bs-target');
            if (targetModal) {
                const modalElement = document.querySelector(targetModal);
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                }
            }
        });
    });

    // Form Validation for Pesticide Application
    const applicationForm = document.querySelector('#addApplicationModal form');
    if (applicationForm) {
        applicationForm.addEventListener('submit', function(event) {
            // Validate crop selection
            const cropSelect = document.getElementById('crop_id');
            if (!cropSelect.value) {
                event.preventDefault();
                alert('Please select a crop.');
                cropSelect.focus();
                return;
            }

            // Validate pesticide type
            const pesticideSelect = document.getElementById('pesticide_type_id');
            if (!pesticideSelect.value) {
                event.preventDefault();
                alert('Please select a pesticide type.');
                pesticideSelect.focus();
                return;
            }

            // Validate application date
            const applicationDate = document.getElementById('application_date');
            if (!applicationDate.value) {
                event.preventDefault();
                alert('Please enter an application date.');
                applicationDate.focus();
                return;
            }

            // Validate quantity used with basic pattern
            const quantityUsed = document.getElementById('quantity_used');
            const quantityPattern = /^\d+(\.\d+)?\s*(L|kg|ml|g)\/?(acre|hectare)?$/i;
            if (!quantityUsed.value || !quantityPattern.test(quantityUsed.value)) {
                event.preventDefault();
                alert('Please enter a valid quantity (e.g., "2 L/acre", "1.5 kg").');
                quantityUsed.focus();
                return;
            }

            // Validate application method
            const applicationMethod = document.getElementById('application_method');
            if (!applicationMethod.value) {
                event.preventDefault();
                alert('Please select an application method.');
                applicationMethod.focus();
                return;
            }

            // Validate target pest
            const targetPest = document.getElementById('target_pest');
            if (!targetPest.value || targetPest.value.length < 2) {
                event.preventDefault();
                alert('Please enter a valid target pest or disease.');
                targetPest.focus();
                return;
            }

            // Validate operator name
            const operatorName = document.getElementById('operator_name');
            if (!operatorName.value || operatorName.value.length < 2) {
                event.preventDefault();
                alert('Please enter a valid operator name.');
                operatorName.focus();
                return;
            }
        });
    }

    // Form Validation for Report Issue
    const reportIssueForm = document.querySelector('#reportIssueModal form');
    if (reportIssueForm) {
        reportIssueForm.addEventListener('submit', function(event) {
            // Validate crop selection
            const cropSelect = document.getElementById('crop_id_issue');
            if (!cropSelect.value) {
                event.preventDefault();
                alert('Please select a crop.');
                cropSelect.focus();
                return;
            }

            // Validate issue type
            const issueType = document.getElementById('issue_type');
            if (!issueType.value) {
                event.preventDefault();
                alert('Please select an issue type.');
                issueType.focus();
                return;
            }

            // Validate issue name
            const issueName = document.getElementById('issue_name');
            if (!issueName.value || issueName.value.length < 2) {
                event.preventDefault();
                alert('Please enter a valid issue name.');
                issueName.focus();
                return;
            }

            // Validate severity
            const severity = document.getElementById('severity');
            if (!severity.value) {
                event.preventDefault();
                alert('Please select a severity level.');
                severity.focus();
                return;
            }

            // Validate identified date
            const dateIdentified = document.getElementById('date_identified');
            if (!dateIdentified.value) {
                event.preventDefault();
                alert('Please enter the date when the issue was identified.');
                dateIdentified.focus();
                return;
            }

            // Validate affected area
            const affectedArea = document.getElementById('affected_area');
            if (!affectedArea.value || affectedArea.value.length < 2) {
                event.preventDefault();
                alert('Please enter the affected area.');
                affectedArea.focus();
                return;
            }
        });
    }
    
    // Logging code to check if Bootstrap is available
    console.log('Bootstrap available:', typeof bootstrap !== 'undefined');
    console.log('Modal elements found:', document.querySelectorAll('.modal').length);
});
</script>

<?php include 'includes/footer.php'; ?>