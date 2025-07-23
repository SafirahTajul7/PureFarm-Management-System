<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Fetch irrigation data
try {
    // Get all irrigation records with crop information
    $stmt = $pdo->prepare("
        SELECT i.*, c.crop_name, f.field_name
        FROM irrigation_schedules i
        JOIN crops c ON i.crop_id = c.id
        JOIN fields f ON c.field_id = f.id
        ORDER BY i.next_irrigation_date ASC
    ");
    $stmt->execute();
    $irrigation_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get water usage statistics
    $stmt = $pdo->prepare("
        SELECT SUM(amount_used) as total_usage 
        FROM irrigation_logs 
        WHERE irrigation_date BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) AND CURRENT_DATE
    ");
    $stmt->execute();
    $current_month_usage = $stmt->fetch(PDO::FETCH_ASSOC)['total_usage'] ?? 0;
    
    $stmt = $pdo->prepare("
        SELECT SUM(amount_used) as total_usage 
        FROM irrigation_logs 
        WHERE irrigation_date BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL 60 DAY) AND DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $previous_month_usage = $stmt->fetch(PDO::FETCH_ASSOC)['total_usage'] ?? 0;
    
    // Calculate percentage change
    $percent_change = 0;
    if ($previous_month_usage > 0) {
        $percent_change = (($current_month_usage - $previous_month_usage) / $previous_month_usage) * 100;
    }
    
} catch(PDOException $e) {
    error_log("Error fetching irrigation data: " . $e->getMessage());
    // Set default values in case of error
    $irrigation_records = [];
    $current_month_usage = 0;
    $previous_month_usage = 0;
    $percent_change = 0;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<pre>';
    print_r($_POST);
    echo '</pre>';
    // Don't exit so the rest of the page still loads
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_schedule') {
                // Insert new irrigation schedule
                $stmt = $pdo->prepare("
                    INSERT INTO irrigation_schedules 
                    (crop_id, schedule_description, water_amount, last_irrigation_date, next_irrigation_date) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['crop_id'],
                    $_POST['schedule_description'],
                    $_POST['water_amount'],
                    $_POST['last_irrigation_date'],
                    $_POST['next_irrigation_date']
                ]);
                
                $success_message = "Irrigation schedule added successfully!";
                
            } elseif ($_POST['action'] === 'log_irrigation') {
                // Insert irrigation log
                $stmt = $pdo->prepare("
                    INSERT INTO irrigation_logs 
                    (schedule_id, irrigation_date, amount_used, notes) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['schedule_id'],
                    $_POST['irrigation_date'],
                    $_POST['amount_used'],
                    $_POST['notes']
                ]);
                
                // Update the last_irrigation_date in schedules table
                $stmt = $pdo->prepare("
                    UPDATE irrigation_schedules 
                    SET last_irrigation_date = ?, 
                        next_irrigation_date = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['irrigation_date'],
                    $_POST['next_irrigation_date'],
                    $_POST['schedule_id']
                ]);
                
                $success_message = "Irrigation logged successfully!";

            } elseif ($_POST['action'] === 'edit_schedule') {
                // Update existing irrigation schedule
                $stmt = $pdo->prepare("
                    UPDATE irrigation_schedules 
                    SET crop_id = ?, 
                        schedule_description = ?, 
                        water_amount = ?, 
                        last_irrigation_date = ?, 
                        next_irrigation_date = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['crop_id'],
                    $_POST['schedule_description'],
                    $_POST['water_amount'],
                    $_POST['last_irrigation_date'],
                    $_POST['next_irrigation_date'],
                    $_POST['schedule_id']
                ]);
                
                $success_message = "Irrigation schedule updated successfully!";
            }

        } catch(PDOException $e) {
            error_log("Error processing irrigation form: " . $e->getMessage());
            $error_message = "An error occurred while processing your request.";
        }
        
        // Refresh data after form submission
        header("Location: irrigation_management.php");
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

$pageTitle = 'Irrigation Management';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-tint"></i> Irrigation Management</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" id="addIrrigationBtn" onclick="openAddIrrigationModal()">
                <i class="fas fa-plus"></i> Add Irrigation Schedule
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

    <!-- Water Usage Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-chart-bar text-primary"></i> Water Usage Statistics</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stat-card">
                                <h3>Current Month</h3>
                                <p class="stat-value"><?php echo number_format($current_month_usage); ?> liters</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-card">
                                <h3>Change</h3>
                                <p class="stat-value <?php echo $percent_change < 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo number_format(abs($percent_change), 1); ?>% 
                                    <?php echo $percent_change < 0 ? 'decrease' : 'increase'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- UPDATED: Weather Impact Card -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-cloud-sun text-warning"></i> Weather Impact</h5>
                    <p>Current weather conditions may affect your irrigation schedule. Expected rainfall in the next 48 hours.</p>
                    <div class="weather-info">
                        <div class="weather-stat">
                            <i class="fas fa-temperature-high text-warning"></i>
                            <span>Loading...</span>
                        </div>
                        <div class="weather-stat">
                            <i class="fas fa-tint text-primary"></i>
                            <span>Loading...</span>
                        </div>
                        <div class="weather-stat">
                            <i class="fas fa-cloud-rain text-info"></i>
                            <span>Loading...</span>
                        </div>
                    </div>
                    <div class="text-end mt-2">
                        <small class="text-muted weather-timestamp">Weather updates automatically</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Irrigation Schedules Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-calendar-alt"></i> Irrigation Schedules</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Crop</th>
                            <th>Schedule</th>
                            <th>Water Amount</th>
                            <th>Last Irrigation</th>
                            <th>Next Irrigation</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($irrigation_records)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No irrigation schedules found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($irrigation_records as $record): ?>
                                <?php 
                                $next_date = new DateTime($record['next_irrigation_date']);
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
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['field_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['crop_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['schedule_description']); ?></td>
                                    <td><?php echo htmlspecialchars($record['water_amount']); ?> liters</td>
                                    <td><?php echo htmlspecialchars($record['last_irrigation_date']); ?></td>
                                    <td><?php echo htmlspecialchars($record['next_irrigation_date']); ?></td>
                                    <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary log-irrigation-btn" 
                                                data-id="<?php echo $record['id']; ?>" 
                                                data-crop="<?php echo htmlspecialchars($record['crop_name']); ?>"
                                                data-field="<?php echo htmlspecialchars($record['field_name']); ?>"
                                                data-schedule="<?php echo htmlspecialchars($record['schedule_description']); ?>"
                                                data-amount="<?php echo htmlspecialchars($record['water_amount']); ?>">
                                            <i class="fas fa-clipboard-list"></i> Log
                                        </button>
                                        <button class="btn btn-sm btn-info edit-irrigation-btn" 
                                                data-id="<?php echo $record['id']; ?>" 
                                                data-crop-id="<?php echo $record['crop_id']; ?>"
                                                data-description="<?php echo htmlspecialchars($record['schedule_description']); ?>"
                                                data-amount="<?php echo htmlspecialchars($record['water_amount']); ?>"
                                                data-last-date="<?php echo htmlspecialchars($record['last_irrigation_date']); ?>"
                                                data-next-date="<?php echo htmlspecialchars($record['next_irrigation_date']); ?>">
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

    <!-- Efficiency Tips Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-lightbulb text-warning"></i> Irrigation Efficiency Tips</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="tip-card">
                        <i class="fas fa-clock text-primary"></i>
                        <h4>Optimal Timing</h4>
                        <p>Irrigate during early morning or evening to minimize evaporation and maximize water absorption.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="tip-card">
                        <i class="fas fa-seedling text-success"></i>
                        <h4>Growth-Stage Watering</h4>
                        <p>Adjust water amounts based on crop growth stages. Newly planted crops need frequent, light watering.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="tip-card">
                        <i class="fas fa-cloud-rain text-info"></i>
                        <h4>Weather Adaptation</h4>
                        <p>Skip scheduled irrigation if there has been or will be significant rainfall in a 48-hour period.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Irrigation Schedule Modal -->
<div class="modal fade" id="addIrrigationModal" tabindex="-1" aria-labelledby="addIrrigationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addIrrigationModalLabel"><i class="fas fa-plus-circle"></i> Add Irrigation Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="irrigation_management.php" method="POST">
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
                            <label for="water_amount" class="form-label">Water Amount (liters)</label>
                            <input type="number" class="form-control" id="water_amount" name="water_amount" min="1" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="schedule_description" class="form-label">Schedule Description</label>
                            <input type="text" class="form-control" id="schedule_description" name="schedule_description" 
                                   placeholder="e.g., 'Every Monday and Thursday', 'Daily at 6am'" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="last_irrigation_date" class="form-label">Last Irrigation Date</label>
                            <input type="date" class="form-control" id="last_irrigation_date" name="last_irrigation_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="next_irrigation_date" class="form-label">Next Irrigation Date</label>
                            <input type="date" class="form-control" id="next_irrigation_date" name="next_irrigation_date" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Reset</button>
                    <button type="submit" class="btn btn-primary">Save Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Log Irrigation Modal -->
<div class="modal fade" id="logIrrigationModal" tabindex="-1" aria-labelledby="logIrrigationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logIrrigationModalLabel"><i class="fas fa-clipboard-list"></i> Log Irrigation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="irrigation_management.php" method="POST">
                <input type="hidden" name="action" value="log_irrigation">
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
                                <label class="form-label">Schedule:</label>
                                <p class="form-control-static" id="log_schedule"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Recommended Amount:</label>
                                <p class="form-control-static" id="log_recommended_amount"></p>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="irrigation_date" class="form-label">Irrigation Date</label>
                            <input type="date" class="form-control" id="irrigation_date" name="irrigation_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="amount_used" class="form-label">Amount Used (liters)</label>
                            <input type="number" class="form-control" id="amount_used" name="amount_used" min="1" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="next_irrigation_date" class="form-label">Next Irrigation Date</label>
                            <input type="date" class="form-control" id="next_irrigation_date" name="next_irrigation_date" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Reset</button>
                    <button type="submit" class="btn btn-primary">Save Log</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Irrigation Schedule Modal -->
<div class="modal fade" id="editIrrigationModal" tabindex="-1" aria-labelledby="editIrrigationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editIrrigationModalLabel"><i class="fas fa-edit"></i> Edit Irrigation Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="irrigation_management.php" method="POST">
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
                            <label for="edit_water_amount" class="form-label">Water Amount (liters)</label>
                            <input type="number" class="form-control" id="edit_water_amount" name="water_amount" min="1" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_schedule_description" class="form-label">Schedule Description</label>
                            <input type="text" class="form-control" id="edit_schedule_description" name="schedule_description" 
                                   placeholder="e.g., 'Every Monday and Thursday', 'Daily at 6am'" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_last_irrigation_date" class="form-label">Last Irrigation Date</label>
                            <input type="date" class="form-control" id="edit_last_irrigation_date" name="last_irrigation_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_next_irrigation_date" class="form-label">Next Irrigation Date</label>
                            <input type="date" class="form-control" id="edit_next_irrigation_date" name="next_irrigation_date" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Reset</button>
                    <button type="submit" class="btn btn-primary">Update Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Irrigation Management specific styles */
.weather-info {
    display: flex;
    justify-content: space-between;
    margin-top: 15px;
}

.weather-stat {
    text-align: center;
    padding: 10px;
    border-radius: 5px;
    background-color: #f8f9fa;
    min-width: 100px;
    transition: all 0.3s ease;
}

.weather-stat:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.weather-stat i {
    display: block;
    font-size: 24px;
    margin-bottom: 5px;
    color: #3498db;
}

.weather-stat span {
    display: block;
    font-weight: 500;
}

.weather-timestamp {
    font-style: italic;
    font-size: 12px;
}

/* Animation for loading state */
.loading-weather span {
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { opacity: 0.6; }
    50% { opacity: 1; }
    100% { opacity: 0.6; }
}

.stat-card {
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 5px;
    margin-bottom: 10px;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    margin: 0;
}

.tip-card {
    padding: 15px;
    border-radius: 5px;
    background-color: #f8f9fa;
    height: 100%;
    margin-bottom: 15px;
}

.tip-card i {
    font-size: 28px;
    margin-bottom: 10px;
}

.tip-card h4 {
    margin-bottom: 10px;
    font-size: 18px;
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
</style>

<script>
// Function to open the Add Irrigation modal
function openAddIrrigationModal() {
    // Make sure we're using the Bootstrap Modal instance correctly
    const addModal = new bootstrap.Modal(document.getElementById('addIrrigationModal'));
    addModal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    // Fix for the Cancel button - ensure proper modal initialization
    document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(button => {
        button.addEventListener('click', function() {
            const modalId = this.closest('.modal').id;
            const modalInstance = bootstrap.Modal.getInstance(document.getElementById(modalId));
            if (modalInstance) {
                modalInstance.hide();
            }
        });
    });

     // Add click event listeners to all modal close and cancel buttons
     const closeButtons = document.querySelectorAll('[data-bs-dismiss="modal"], .btn-secondary');
    
        closeButtons.forEach(button => {
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

        // Optional: Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    const bootstrapModal = bootstrap.Modal.getInstance(modal);
                    if (bootstrapModal) {
                        bootstrapModal.hide();
                    } else {
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
    
    
    // Make sure Bootstrap is loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded! Please check your includes.');
        alert('Error: Bootstrap JavaScript is not loaded. Please check the console for more information.');
        return;
    }
    
    // Set default dates for the add irrigation form
    const today = new Date();
    const formattedToday = today.toISOString().split('T')[0];
    
    // Set default date for last irrigation to today
    document.getElementById('last_irrigation_date').value = formattedToday;
    
    // Set default date for next irrigation to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const formattedTomorrow = tomorrow.toISOString().split('T')[0];
    document.getElementById('next_irrigation_date').value = formattedTomorrow;
    
    // Initialize all Log buttons
    document.querySelectorAll('.log-irrigation-btn').forEach(button => {
        button.addEventListener('click', function() {
            const scheduleId = this.getAttribute('data-id');
            const crop = this.getAttribute('data-crop');
            const field = this.getAttribute('data-field');
            const schedule = this.getAttribute('data-schedule');
            const amount = this.getAttribute('data-amount');
        
            
            // Update modal fields
            document.getElementById('log_schedule_id').value = scheduleId;
            document.getElementById('log_crop').textContent = crop;
            document.getElementById('log_field').textContent = field;
            document.getElementById('log_schedule').textContent = schedule;
            document.getElementById('log_recommended_amount').textContent = amount + ' liters';
            
            // Set default values for the form
            document.getElementById('irrigation_date').value = formattedToday;
            document.getElementById('amount_used').value = amount.split(' ')[0]; // Extract number from "X liters"
            
            // Calculate next irrigation date based on schedule description
            // This is just a simple example - in a real app, you'd need more complex logic
            const nextDate = new Date();
            if (schedule.toLowerCase().includes('daily')) {
                nextDate.setDate(nextDate.getDate() + 1);
            } else if (schedule.toLowerCase().includes('weekly')) {
                nextDate.setDate(nextDate.getDate() + 7);
            } else {
                nextDate.setDate(nextDate.getDate() + 3); // Default to 3 days
            }
            
            

            const formattedNextDate = nextDate.toISOString().split('T')[0];
            document.getElementById('next_irrigation_date').value = formattedNextDate;
            
            // Show the modal
            const logModal = new bootstrap.Modal(document.getElementById('logIrrigationModal'));
            logModal.show();

        });
    });

    document.querySelectorAll('.edit-irrigation-btn').forEach(button => {
        button.addEventListener('click', function() {
            const scheduleId = this.getAttribute('data-id');
            const cropId = this.getAttribute('data-crop-id');
            const description = this.getAttribute('data-description');
            const amount = this.getAttribute('data-amount');
            const lastDate = this.getAttribute('data-last-date');
            const nextDate = this.getAttribute('data-next-date');
            
            // Update edit modal fields
            document.getElementById('edit_schedule_id').value = scheduleId;
            document.getElementById('edit_crop_id').value = cropId;
            document.getElementById('edit_schedule_description').value = description;
            
            // Parse water amount to remove "liters" text
            const waterAmount = amount.split(' ')[0];
            document.getElementById('edit_water_amount').value = waterAmount;
            
            document.getElementById('edit_last_irrigation_date').value = lastDate;
            document.getElementById('edit_next_irrigation_date').value = nextDate;
            
            // Show ONLY the edit modal
            const editModal = new bootstrap.Modal(document.getElementById('editIrrigationModal'));
            editModal.show();
        });
    });
    
    // Get user's location and update weather
    getWeatherForLocation();
});

// Function to get weather for location
function getWeatherForLocation() {
    // Try to get user's current location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            // Success callback
            function(position) {
                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;
                
                // In a real app, you would make an API call here
                // Since we don't have an API key, we'll simulate realistic weather data
                updateWeatherDisplay(generateRealisticWeather(latitude, longitude));
                
                // Optionally, reverse geocode to get location name
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}`)
                    .then(response => response.json())
                    .then(data => {
                        const location = data.address.city || data.address.town || data.address.village || data.address.county;
                        if (location) {
                            document.querySelector('.card-title').innerHTML = 
                                `<i class="fas fa-cloud-sun text-warning"></i> Weather Impact - ${location}`;
                        }
                    })
                    .catch(err => console.log('Error getting location name:', err));
            },
            // Error callback
            function(error) {
                console.log('Geolocation error:', error);
                // Fall back to simulated data if geolocation fails
                updateWeatherDisplay(generateRealisticWeather());
            }
        );
    } else {
        // If geolocation not supported, use fallback data
        updateWeatherDisplay(generateRealisticWeather());
    }
}

// Function to generate realistic weather data based on location and current season
function generateRealisticWeather(latitude = null, longitude = null) {
    // Get current date to determine season
    const date = new Date();
    const month = date.getMonth(); // 0-11

    // Determine hemisphere and season based on latitude
    let isNorthernHemisphere = true;
    if (latitude !== null) {
        isNorthernHemisphere = latitude > 0;
    }

    // Define seasons (roughly)
    // Northern Hemisphere: Winter (Dec-Feb), Spring (Mar-May), Summer (Jun-Aug), Fall (Sep-Nov)
    // Southern Hemisphere: Summer (Dec-Feb), Fall (Mar-May), Winter (Jun-Aug), Spring (Sep-Nov)
    let season;
    if (isNorthernHemisphere) {
        if (month >= 11 || month <= 1) season = 'winter';
        else if (month >= 2 && month <= 4) season = 'spring';
        else if (month >= 5 && month <= 7) season = 'summer';
        else season = 'fall';
    } else {
        if (month >= 11 || month <= 1) season = 'summer';
        else if (month >= 2 && month <= 4) season = 'fall';
        else if (month >= 5 && month <= 7) season = 'winter';
        else season = 'spring';
    }

    // Generate realistic temperature based on season
    let temp, humidity, rainChance;
    
    // Add some randomness to make it look real
    const randomFactor = Math.random() * 5;
    
    switch (season) {
        case 'winter':
            temp = Math.round(5 + randomFactor);
            humidity = Math.round(65 + randomFactor);
            rainChance = Math.round(40 + randomFactor * 3);
            break;
        case 'spring':
            temp = Math.round(15 + randomFactor);
            humidity = Math.round(60 + randomFactor);
            rainChance = Math.round(50 + randomFactor * 3);
            break;
        case 'summer':
            temp = Math.round(25 + randomFactor);
            humidity = Math.round(50 + randomFactor);
            rainChance = Math.round(30 + randomFactor * 3);
            break;
        case 'fall':
            temp = Math.round(18 + randomFactor);
            humidity = Math.round(55 + randomFactor);
            rainChance = Math.round(45 + randomFactor * 3);
            break;
    }
    
    // Add some daily variation
    const hourOfDay = date.getHours();
    if (hourOfDay > 12 && hourOfDay < 18) {
        temp += 2; // Warmer in the afternoon
        humidity -= 5; // Less humidity
    } else if (hourOfDay < 6 || hourOfDay > 18) {
        temp -= 2; // Cooler at night
        humidity += 5; // More humidity
    }

    // Cap values to realistic ranges
    temp = Math.max(0, Math.min(40, temp));
    humidity = Math.max(30, Math.min(95, humidity));
    rainChance = Math.max(5, Math.min(95, rainChance));
    
    // Generate appropriate weather message
    let weatherMessage = "";
    if (rainChance > 60) {
        weatherMessage = `High chance of rainfall in the next 48 hours. Consider delaying irrigation.`;
    } else if (rainChance > 30) {
        weatherMessage = `Moderate chance of precipitation soon. Monitor forecasts before irrigating.`;
    } else if (temp > 30) {
        weatherMessage = `High temperatures may increase water requirements for crops.`;
    } else if (humidity < 40) {
        weatherMessage = `Low humidity increases evaporation rates. Consider increasing water amounts.`;
    } else {
        weatherMessage = `Current weather conditions are favorable for scheduled irrigation.`;
    }
    
    return {
        temperature: temp,
        humidity: humidity,
        rainChance: rainChance,
        message: weatherMessage,
        season: season
    };
}

// Function to update the weather display
function updateWeatherDisplay(weatherData) {
    // Update temperature
    document.querySelector('.weather-stat:nth-child(1) span').textContent = `${weatherData.temperature}°C`;
    
    // Update humidity
    document.querySelector('.weather-stat:nth-child(2) span').textContent = `${weatherData.humidity}% Humidity`;
    
    // Update rain chance
    document.querySelector('.weather-stat:nth-child(3) span').textContent = `${weatherData.rainChance}% Chance of Rain`;
    
    // Update weather message
    document.querySelector('.card-body p').textContent = weatherData.message;
    
    // Optionally update icons based on conditions
    updateWeatherIcons(weatherData);
}

// Function to update weather icons based on conditions
function updateWeatherIcons(weatherData) {
    let tempIcon = document.querySelector('.weather-stat:nth-child(1) i');
    let humidityIcon = document.querySelector('.weather-stat:nth-child(2) i');
    let rainIcon = document.querySelector('.weather-stat:nth-child(3) i');
    
    // Temperature icon
    if (weatherData.temperature > 30) {
        tempIcon.className = 'fas fa-temperature-high text-danger';
    } else if (weatherData.temperature < 10) {
        tempIcon.className = 'fas fa-temperature-low text-info';
    } else {
        tempIcon.className = 'fas fa-temperature-high text-warning';
    }
    
    // Humidity icon remains the same
    humidityIcon.className = 'fas fa-tint text-primary';
    
    // Rain icon
    if (weatherData.rainChance > 60) {
        rainIcon.className = 'fas fa-cloud-showers-heavy text-primary';
    } else if (weatherData.rainChance > 30) {
        rainIcon.className = 'fas fa-cloud-rain text-info';
    } else {
        rainIcon.className = 'fas fa-cloud text-secondary';
    }
}
</script>

<?php include 'includes/footer.php'; ?>