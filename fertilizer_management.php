<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Fetch fertilizer data
try {
    // Get all fertilizer applications with crop information
    $stmt = $pdo->prepare("
        SELECT f.*, c.crop_name, fi.field_name
        FROM fertilizer_schedules f
        JOIN crops c ON f.crop_id = c.id
        JOIN fields fi ON c.field_id = fi.id
        ORDER BY f.next_application_date ASC
    ");
    $stmt->execute();
    $fertilizer_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get fertilizer types for dropdown
    $stmt = $pdo->prepare("
        SELECT id, name, nutrient_composition, recommended_crops, unit_of_measure
        FROM fertilizer_types
        ORDER BY name
    ");
    $stmt->execute();
    $fertilizer_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check if fertilizer_types is empty
    if (empty($fertilizer_types)) {
        error_log("No fertilizer types found in the database");
    } else {
        error_log("Found " . count($fertilizer_types) . " fertilizer types");
    }
    
    // Get soil test results
    $stmt = $pdo->prepare("
        SELECT st.*, f.field_name
        FROM soil_tests st
        JOIN fields f ON st.field_id = f.id
        WHERE test_date = (
            SELECT MAX(test_date) FROM soil_tests WHERE field_id = st.field_id
        )
        ORDER BY test_date DESC
    ");
    $stmt->execute();
    $soil_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Error fetching fertilizer data: " . $e->getMessage());
    // Set default values in case of error
    $fertilizer_schedules = [];
    $fertilizer_types = [];
    $soil_tests = [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_schedule') {
                // Insert new fertilizer schedule
                $stmt = $pdo->prepare("
                    INSERT INTO fertilizer_schedules 
                    (crop_id, fertilizer_type_id, schedule_description, application_rate, last_application_date, next_application_date) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['crop_id'],
                    $_POST['fertilizer_type_id'],
                    $_POST['schedule_description'],
                    $_POST['application_rate'],
                    $_POST['last_application_date'],
                    $_POST['next_application_date']
                ]);
                
                $success_message = "Fertilizer schedule added successfully!";
                
            } elseif ($_POST['action'] === 'log_application') {
                // Insert fertilizer application log
                $stmt = $pdo->prepare("
                    INSERT INTO fertilizer_logs 
                    (schedule_id, application_date, amount_used, application_method, notes) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['schedule_id'],
                    $_POST['application_date'],
                    $_POST['amount_used'],
                    $_POST['application_method'],
                    $_POST['notes']
                ]);
                
                // Update the last_application_date in schedules table
                $stmt = $pdo->prepare("
                    UPDATE fertilizer_schedules 
                    SET last_application_date = ?, 
                        next_application_date = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['application_date'],
                    $_POST['next_application_date'],
                    $_POST['schedule_id']
                ]);
                
                $success_message = "Fertilizer application logged successfully!";

            } elseif ($_POST['action'] === 'edit_schedule') {
                // Update existing fertilizer schedule
                $stmt = $pdo->prepare("
                    UPDATE fertilizer_schedules 
                    SET crop_id = ?, 
                        fertilizer_type_id = ?, 
                        schedule_description = ?, 
                        application_rate = ?, 
                        last_application_date = ?, 
                        next_application_date = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['crop_id'],
                    $_POST['fertilizer_type_id'],
                    $_POST['schedule_description'],
                    $_POST['application_rate'],
                    $_POST['last_application_date'],
                    $_POST['next_application_date'],
                    $_POST['schedule_id']
                ]);
                
                $success_message = "Fertilizer schedule updated successfully!";
            }
        } catch(PDOException $e) {
            error_log("Error processing fertilizer form: " . $e->getMessage());
            $error_message = "An error occurred while processing your request.";
        }
        
        // Refresh data after form submission
        header("Location: fertilizer_management.php");
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

$pageTitle = 'Fertilizer Management';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-flask"></i> Fertilizer Management</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" id="addFertilizerBtn" onclick="openAddFertilizerModal()">
                <i class="fas fa-plus"></i> Add Fertilizer Schedule
            </button>
            <button class="btn btn-secondary" onclick="location.href='crop_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Crop Management
            </button>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Soil Test Results -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-microscope text-primary"></i> Soil Test Results</h5>
        </div>
        <div class="card-body">
            <?php if (empty($soil_tests)): ?>
                <div class="alert alert-info">
                    No recent soil test results available. Consider conducting soil tests to optimize fertilizer application.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($soil_tests as $index => $test): ?>
                        <?php if ($index < 3): // Show only the 3 most recent tests ?>
                            <div class="col-md-4">
                                <div class="soil-test-card">
                                    <h4><?php echo htmlspecialchars($test['field_name']); ?></h4>
                                    <p class="text-muted">Test Date: <?php echo htmlspecialchars($test['test_date']); ?></p>
                                    
                                    <div class="nutrient-level">
                                        <span class="nutrient-name">Nitrogen (N):</span>
                                        <div class="progress">
                                            <?php 
                                            $n_level = $test['nitrogen_level'];
                                            $n_class = 'bg-danger';
                                            $n_text = 'Low';
                                            
                                            if ($n_level > 30 && $n_level <= 60) {
                                                $n_class = 'bg-warning';
                                                $n_text = 'Medium';
                                            } elseif ($n_level > 60) {
                                                $n_class = 'bg-success';
                                                $n_text = 'High';
                                            }
                                            ?>
                                            <div class="progress-bar <?php echo $n_class; ?>" role="progressbar" 
                                                 style="width: <?php echo min(100, $n_level); ?>%" 
                                                 aria-valuenow="<?php echo $n_level; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $n_text; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="nutrient-level">
                                        <span class="nutrient-name">Phosphorus (P):</span>
                                        <div class="progress">
                                            <?php 
                                            $p_level = $test['phosphorus_level'];
                                            $p_class = 'bg-danger';
                                            $p_text = 'Low';
                                            
                                            if ($p_level > 30 && $p_level <= 60) {
                                                $p_class = 'bg-warning';
                                                $p_text = 'Medium';
                                            } elseif ($p_level > 60) {
                                                $p_class = 'bg-success';
                                                $p_text = 'High';
                                            }
                                            ?>
                                            <div class="progress-bar <?php echo $p_class; ?>" role="progressbar" 
                                                 style="width: <?php echo min(100, $p_level); ?>%" 
                                                 aria-valuenow="<?php echo $p_level; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $p_text; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="nutrient-level">
                                        <span class="nutrient-name">Potassium (K):</span>
                                        <div class="progress">
                                            <?php 
                                            $k_level = $test['potassium_level'];
                                            $k_class = 'bg-danger';
                                            $k_text = 'Low';
                                            
                                            if ($k_level > 30 && $k_level <= 60) {
                                                $k_class = 'bg-warning';
                                                $k_text = 'Medium';
                                            } elseif ($k_level > 60) {
                                                $k_class = 'bg-success';
                                                $k_text = 'High';
                                            }
                                            ?>
                                            <div class="progress-bar <?php echo $k_class; ?>" role="progressbar" 
                                                 style="width: <?php echo min(100, $k_level); ?>%" 
                                                 aria-valuenow="<?php echo $k_level; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $k_text; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <span class="badge bg-secondary">pH: <?php echo $test['ph_level']; ?></span>
                                        <span class="badge bg-secondary">Organic Matter: <?php echo $test['organic_matter']; ?>%</span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-end mt-3">
                    <button class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-plus"></i> Record New Soil Test
                    </button>
                    <button class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-history"></i> View All Test History
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Fertilizer Schedules Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-calendar-alt"></i> Fertilizer Application Schedules</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Crop</th>
                            <th>Fertilizer Type</th>
                            <th>Schedule</th>
                            <th>Application Rate</th>
                            <th>Last Application</th>
                            <th>Next Application</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fertilizer_schedules)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No fertilizer schedules found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fertilizer_schedules as $schedule): ?>
                                <?php 
                                $next_date = new DateTime($schedule['next_application_date']);
                                $today = new DateTime();
                                $diff = $today->diff($next_date);
                                $days_diff = $diff->format("%R%a");
                                
                                if ($days_diff <= 0) {
                                    $status_class = "danger";
                                    $status_text = "Due Today";
                                } elseif ($days_diff <= 3) {
                                    $status_class = "warning";
                                    $status_text = "Due in " . $days_diff . " days";
                                } else {
                                    $status_class = "success";
                                    $status_text = "Scheduled";
                                }
                                
                                // Get fertilizer type name
                                $fertilizer_name = "Unknown";
                                foreach ($fertilizer_types as $type) {
                                    if ($type['id'] == $schedule['fertilizer_type_id']) {
                                        $fertilizer_name = $type['name'];
                                        break;
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($schedule['field_name']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['crop_name']); ?></td>
                                    <td><?php echo htmlspecialchars($fertilizer_name); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['schedule_description']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['application_rate']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['last_application_date']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['next_application_date']); ?></td>
                                    <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary log-application-btn" 
                                                data-id="<?php echo $schedule['id']; ?>"
                                                data-crop="<?php echo htmlspecialchars($schedule['crop_name']); ?>"
                                                data-field="<?php echo htmlspecialchars($schedule['field_name']); ?>"
                                                data-fertilizer="<?php echo htmlspecialchars($fertilizer_name); ?>"
                                                data-schedule="<?php echo htmlspecialchars($schedule['schedule_description']); ?>"
                                                data-rate="<?php echo htmlspecialchars($schedule['application_rate']); ?>">
                                            <i class="fas fa-clipboard-list"></i> Log
                                        </button>
                                        <button class="btn btn-sm btn-info"
                                                data-id="<?php echo $schedule['id']; ?>"
                                                data-crop-id="<?php echo $schedule['crop_id']; ?>"
                                                data-fertilizer-id="<?php echo $schedule['fertilizer_type_id']; ?>"
                                                data-rate="<?php echo htmlspecialchars($schedule['application_rate']); ?>"
                                                data-schedule="<?php echo htmlspecialchars($schedule['schedule_description']); ?>"
                                                data-last-date="<?php echo htmlspecialchars($schedule['last_application_date']); ?>"
                                                data-next-date="<?php echo htmlspecialchars($schedule['next_application_date']); ?>">
                                            <i class="fas fa-edit"></i> Edit
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

    <!-- Fertilizer Types and Recommendations -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-info-circle text-primary"></i> Fertilizer Types and Recommendations</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($fertilizer_types as $index => $type): ?>
                    <?php if ($index < 6): // Show only 6 fertilizer types ?>
                        <div class="col-md-4 mb-3">
                            <div class="fertilizer-type-card">
                                <h4><?php echo htmlspecialchars($type['name']); ?></h4>
                                <p class="composition">
                                    <strong>Composition:</strong> <?php echo htmlspecialchars($type['nutrient_composition']); ?>
                                </p>
                                <p>
                                    <strong>Best for:</strong> <?php echo htmlspecialchars($type['recommended_crops']); ?>
                                </p>
                                <p>
                                    <strong>Unit:</strong> <?php echo htmlspecialchars($type['unit_of_measure']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
        </div>
    </div>
</div>

<!-- Add Fertilizer Schedule Modal -->
<div class="modal fade" id="addFertilizerModal" tabindex="-1" aria-labelledby="addFertilizerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFertilizerModalLabel"><i class="fas fa-plus-circle"></i> Add Fertilizer Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="fertilizer_management.php" method="POST">
                <input type="hidden" name="action" value="add_schedule">
                <div class="modal-body">
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
                            <label for="fertilizer_type_id" class="form-label">Fertilizer Type</label>
                            <select class="form-select" id="fertilizer_type_id" name="fertilizer_type_id" required>
                                <option value="">Select Fertilizer</option>
                                <?php foreach ($fertilizer_types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>">
                                        <?php echo htmlspecialchars($type['name'] . ' (' . $type['nutrient_composition'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="application_rate" class="form-label">Application Rate</label>
                            <input type="text" class="form-control" id="application_rate" name="application_rate" 
                                   placeholder="e.g., '5 kg/acre'" required>
                        </div>
                        <div class="col-md-6">
                            <label for="schedule_description" class="form-label">Schedule Description</label>
                            <input type="text" class="form-control" id="schedule_description" name="schedule_description" 
                                   placeholder="e.g., 'Every 2 weeks', 'Monthly'" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="last_application_date" class="form-label">Last Application Date</label>
                            <input type="date" class="form-control" id="last_application_date" name="last_application_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="next_application_date" class="form-label">Next Application Date</label>
                            <input type="date" class="form-control" id="next_application_date" name="next_application_date" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddFertilizerModal()">Reset</button>
                    <button type="submit" class="btn btn-primary">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Log Fertilizer Application Modal -->
<div class="modal fade" id="logApplicationModal" tabindex="-1" aria-labelledby="logApplicationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logApplicationModalLabel"><i class="fas fa-clipboard-list"></i> Log Fertilizer Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="fertilizer_management.php" method="POST">
                <input type="hidden" name="action" value="log_application">
                <input type="hidden" name="schedule_id" id="log_schedule_id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Field:</label>
                                <p class="form-control-static" id="log_field"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Crop:</label>
                                <p class="form-control-static" id="log_crop"></p>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Fertilizer Type:</label>
                                <p class="form-control-static" id="log_fertilizer"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Recommended Rate:</label>
                                <p class="form-control-static" id="log_recommended_rate"></p>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="application_date" class="form-label">Application Date</label>
                            <input type="date" class="form-control" id="application_date" name="application_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="amount_used" class="form-label">Amount Used</label>
                            <input type="text" class="form-control" id="amount_used" name="amount_used" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="application_method" class="form-label">Application Method</label>
                            <select class="form-select" id="application_method" name="application_method" required>
                                <option value="">Select Method</option>
                                <option value="Broadcast">Broadcast</option>
                                <option value="Band">Band</option>
                                <option value="Foliar Spray">Foliar Spray</option>
                                <option value="Drip Irrigation">Through Drip Irrigation</option>
                                <option value="Manual">Manual</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="next_application_date" class="form-label">Next Application Date</label>
                            <input type="date" class="form-control" id="next_application_date" name="next_application_date" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeLogApplicationModal()">Reset</button>
                    <button type="submit" class="btn btn-primary">Save Log</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Fertilizer Schedule Modal -->
<div class="modal fade" id="editFertilizerModal" tabindex="-1" aria-labelledby="editFertilizerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFertilizerModalLabel"><i class="fas fa-edit"></i> Edit Fertilizer Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="fertilizer_management.php" method="POST">
                <input type="hidden" name="action" value="edit_schedule">
                <input type="hidden" name="schedule_id" id="edit_schedule_id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_crop_id" class="form-label">Crop</label>
                            <select class="form-select" id="edit_crop_id" name="crop_id" required>
                                <option value="">Select Crop</option>
                                <?php foreach ($crops as $crop): ?>
                                    <option value="<?php echo $crop['id']; ?>">
                                        <?php echo htmlspecialchars($crop['field_name'] . ' - ' . $crop['crop_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_fertilizer_type_id" class="form-label">Fertilizer Type</label>
                            <select class="form-select" id="edit_fertilizer_type_id" name="fertilizer_type_id" required>
                                <option value="">Select Fertilizer</option>
                                <?php foreach ($fertilizer_types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>">
                                        <?php echo htmlspecialchars($type['name'] . ' (' . $type['nutrient_composition'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_application_rate" class="form-label">Application Rate</label>
                            <input type="text" class="form-control" id="edit_application_rate" name="application_rate" 
                                   placeholder="e.g., '5 kg/acre'" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_schedule_description" class="form-label">Schedule Description</label>
                            <input type="text" class="form-control" id="edit_schedule_description" name="schedule_description" 
                                   placeholder="e.g., 'Every 2 weeks', 'Monthly'" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_last_application_date" class="form-label">Last Application Date</label>
                            <input type="date" class="form-control" id="edit_last_application_date" name="last_application_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_next_application_date" class="form-label">Next Application Date</label>
                            <input type="date" class="form-control" id="edit_next_application_date" name="next_application_date" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="allFertilizerTypesModal" tabindex="-1" aria-labelledby="allFertilizerTypesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allFertilizerTypesModalLabel">All Fertilizer Types</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <?php foreach ($fertilizer_types as $type): ?>
                        <div class="col-md-4 mb-3">
                            <div class="fertilizer-type-card">
                                <h4><?php echo htmlspecialchars($type['name']); ?></h4>
                                <p class="composition">
                                    <strong>Composition:</strong> <?php echo htmlspecialchars($type['nutrient_composition']); ?>
                                </p>
                                <p>
                                    <strong>Best for:</strong> <?php echo htmlspecialchars($type['recommended_crops']); ?>
                                </p>
                                <p>
                                    <strong>Unit:</strong> <?php echo htmlspecialchars($type['unit_of_measure']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Fertilizer Management specific styles */
.fertilizer-type-card {
    padding: 15px;
    border-radius: 5px;
    background-color: #f8f9fa;
    height: 100%;
    border-left: 4px solid #2ecc71;
}

.fertilizer-type-card h4 {
    color: #2ecc71;
    margin-bottom: 10px;
    font-size: 18px;
}

.composition {
    font-style: italic;
}

.soil-test-card {
    padding: 15px;
    border-radius: 5px;
    background-color: #f8f9fa;
    margin-bottom: 15px;
    border-left: 4px solid #3498db;
}

.soil-test-card h4 {
    margin-bottom: 5px;
    color: #3498db;
    font-size: 18px;
}

.nutrient-level {
    margin-bottom: 10px;
}

.nutrient-name {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.badge.bg-danger {
    background-color: #e74c3c !important;
}

.badge.bg-warning {
    background-color: #f39c12 !important;
}

.badge.bg-success {
    background-color: #2ecc71 !important;
}

.badge.bg-secondary {
    background-color: #7f8c8d !important;
    margin-right: 5px;
}

/* Ensure dropdown stays within modal */
.modal-body .form-select {
    width: 100%;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
}

.modal .dropdown-menu {
    max-height: 300px;
    overflow-y: auto;
    width: 100%;
}

/* Optional: Improve dropdown visibility */
.modal .dropdown-item:hover {
    background-color: #f8f9fa;
}
</style>

<script>
// Function to open the Add Fertilizer modal
function openAddFertilizerModal() {
    const addModal = new bootstrap.Modal(document.getElementById('addFertilizerModal'));
    addModal.show();
}

// Function to close the Add Fertilizer modal
function closeAddFertilizerModal() {
    const addModal = bootstrap.Modal.getInstance(document.getElementById('addFertilizerModal'));
    if (addModal) {
        addModal.hide();
    }
    // Reset form
    document.getElementById('addFertilizerModal').querySelector('form').reset();
}

// Function to close the Log Application modal
function closeLogApplicationModal() {
    const logModal = bootstrap.Modal.getInstance(document.getElementById('logApplicationModal'));
    if (logModal) {
        logModal.hide();
    }
    // Reset form
    document.getElementById('logApplicationModal').querySelector('form').reset();
}

// Function to open the Edit Fertilizer modal
function openEditFertilizerModal(scheduleId, cropId, fertilizerTypeId, applicationRate, scheduleDesc, lastDate, nextDate) {
    // Set values in the edit form
    document.getElementById('edit_schedule_id').value = scheduleId;
    
    // Set dropdown values
    const cropSelect = document.getElementById('edit_crop_id');
    const fertilizerSelect = document.getElementById('edit_fertilizer_type_id');
    
    // Set crop dropdown value
    for (let i = 0; i < cropSelect.options.length; i++) {
        if (cropSelect.options[i].value == cropId) {
            cropSelect.selectedIndex = i;
            break;
        }
    }
    
    // Set fertilizer type dropdown value
    for (let i = 0; i < fertilizerSelect.options.length; i++) {
        if (fertilizerSelect.options[i].value == fertilizerTypeId) {
            fertilizerSelect.selectedIndex = i;
            break;
        }
    }
    
    // Set other form fields
    document.getElementById('edit_application_rate').value = applicationRate;
    document.getElementById('edit_schedule_description').value = scheduleDesc;
    document.getElementById('edit_last_application_date').value = lastDate;
    document.getElementById('edit_next_application_date').value = nextDate;
    
    // Show the modal
    const editModal = new bootstrap.Modal(document.getElementById('editFertilizerModal'));
    editModal.show();
}

// Function to close the Edit Fertilizer modal
function closeEditFertilizerModal() {
    const editModal = bootstrap.Modal.getInstance(document.getElementById('editFertilizerModal'));
    if (editModal) {
        editModal.hide();
    }
    // Reset form
    document.getElementById('editFertilizerModal').querySelector('form').reset();
}

document.addEventListener('DOMContentLoaded', function() {
    // Make sure Bootstrap is loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded! Please check your includes.');
        alert('Error: Bootstrap JavaScript is not loaded. Please check the console for more information.');
        return;
    }
    
    // Handle all cancel buttons in modals
    const cancelButtons = document.querySelectorAll('[onclick="closeAddFertilizerModal()"], [onclick="closeLogApplicationModal()"], [onclick="closeEditFertilizerModal()"]');
    
    cancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Find the closest modal
            const modal = this.closest('.modal');
            
            if (modal) {
                // Reset the form inside the modal
                const form = modal.querySelector('form');
                if (form) {
                    form.reset();
                }
                
                // Try to hide the modal using different methods
                const bootstrapModal = bootstrap.Modal.getInstance(modal);
                if (bootstrapModal) {
                    bootstrapModal.hide();
                } else {
                    // Fallback method
                    modal.style.display = 'none';
                }
            }
        });
    });

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                const bootstrapModal = bootstrap.Modal.getInstance(modal);
                if (bootstrapModal) {
                    bootstrapModal.hide();
                } else {
                    // Fallback method
                    modal.style.display = 'none';
                }
                
                // Reset the form
                const form = modal.querySelector('form');
                if (form) {
                    form.reset();
                }
            }
        });
    });

    // Set default dates for the add fertilizer form
    const today = new Date();
    const formattedToday = today.toISOString().split('T')[0];
    
    // Set default date for last application to today
    const lastAppDateInput = document.getElementById('last_application_date');
    if (lastAppDateInput) {
        lastAppDateInput.value = formattedToday;
    }
    
    // Set default date for next application to 2 weeks from now
    const nextDate = new Date();
    nextDate.setDate(nextDate.getDate() + 14);
    const formattedNextDate = nextDate.toISOString().split('T')[0];
    
    const nextAppDateInput = document.getElementById('next_application_date');
    if (nextAppDateInput) {
        nextAppDateInput.value = formattedNextDate;
    }
    
    // Initialize all Log buttons
    document.querySelectorAll('.log-application-btn').forEach(button => {
        button.addEventListener('click', function() {
            const scheduleId = this.getAttribute('data-id');
            const crop = this.getAttribute('data-crop');
            const field = this.getAttribute('data-field');
            const fertilizer = this.getAttribute('data-fertilizer');
            const schedule = this.getAttribute('data-schedule');
            const rate = this.getAttribute('data-rate');
            
            // Update modal fields
            document.getElementById('log_schedule_id').value = scheduleId;
            document.getElementById('log_crop').textContent = crop;
            document.getElementById('log_field').textContent = field;
            document.getElementById('log_fertilizer').textContent = fertilizer;
            document.getElementById('log_recommended_rate').textContent = rate;
            
            // Set default values for the form
            document.getElementById('application_date').value = formattedToday;
            document.getElementById('amount_used').value = rate.split(' ')[0]; // Extract number from rate
            
            // Calculate next application date based on schedule description
            // This is just a simple example - in a real app, you'd need more complex logic
            const nextAppDate = new Date();
            if (schedule.toLowerCase().includes('weekly')) {
                nextAppDate.setDate(nextAppDate.getDate() + 7);
            } else if (schedule.toLowerCase().includes('monthly')) {
                nextAppDate.setMonth(nextAppDate.getMonth() + 1);
            } else if (schedule.toLowerCase().includes('2 weeks')) {
                nextAppDate.setDate(nextAppDate.getDate() + 14);
            } else if (schedule.toLowerCase().includes('3 weeks')) {
                nextAppDate.setDate(nextAppDate.getDate() + 21);
            } else {
                nextAppDate.setDate(nextAppDate.getDate() + 14); // Default to 2 weeks
            }
            
            const formattedNextAppDate = nextAppDate.toISOString().split('T')[0];
            document.getElementById('next_application_date').value = formattedNextAppDate;
            
            // Show the modal
            const logModal = new bootstrap.Modal(document.getElementById('logApplicationModal'));
            logModal.show();
        });
    });

    // Initialize all Edit buttons
    document.querySelectorAll('.btn-info').forEach(button => {
        button.addEventListener('click', function() {
            const scheduleId = this.getAttribute('data-id');
            const cropId = this.getAttribute('data-crop-id');
            const fertilizerTypeId = this.getAttribute('data-fertilizer-id');
            const applicationRate = this.getAttribute('data-rate');
            const scheduleDesc = this.getAttribute('data-schedule');
            const lastDate = this.getAttribute('data-last-date');
            const nextDate = this.getAttribute('data-next-date');
            
            openEditFertilizerModal(scheduleId, cropId, fertilizerTypeId, applicationRate, scheduleDesc, lastDate, nextDate);
        });
    });

    // Fix for "View All Fertilizer Types" button and modal
    const viewAllButton = document.querySelector('.btn-outline-primary');
    if (viewAllButton) {
        viewAllButton.addEventListener('click', function() {
            const allFertilizerTypesModal = new bootstrap.Modal(document.getElementById('allFertilizerTypesModal'));
            allFertilizerTypesModal.show();
        });
    }

    // Fix for the "View All Fertilizer Types" modal close button
    const allFertilizerTypesModal = document.getElementById('allFertilizerTypesModal');
    if (allFertilizerTypesModal) {
        // Get the close button in the modal footer
        const closeButtons = allFertilizerTypesModal.querySelectorAll('.btn-close, .modal-footer .btn-secondary');
            closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                try {
                    // First try Bootstrap's recommended method
                    const modalInstance = bootstrap.Modal.getInstance(allFertilizerTypesModal);
                    if (modalInstance) {
                        modalInstance.hide();
                        return;
                    }
                    
                    // Fallback method
                    const modal = bootstrap.Modal.getOrCreateInstance(allFertilizerTypesModal);
                    modal.hide();
                } catch (error) {
                    console.error('Error closing modal:', error);
                    
                    // Last resort DOM manipulation
                    allFertilizerTypesModal.classList.remove('show');
                    document.body.classList.remove('modal-open');
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.remove();
                }
            });
        });
    }
    
    // Initialize all modals
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modalEl => {
        new bootstrap.Modal(modalEl);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
            