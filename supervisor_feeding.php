<?php
require_once 'includes/auth.php';
auth()->checkSupervisor(); // Ensure only supervisors can access
require_once 'includes/db.php';

// Handle form submissions for add and update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_schedule'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO feeding_schedules (animal_id, food_type, quantity, frequency, special_diet, notes) 
                                 VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['animal_id'],
                $_POST['food_type'],
                $_POST['quantity'],
                $_POST['frequency'],
                $_POST['special_diet'],
                $_POST['notes']
            ]);
            
            $_SESSION['success'] = "Feeding schedule added successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error adding feeding schedule: " . $e->getMessage();
        }
        header('Location: supervisor_feeding.php');
        exit();
    }
    
    if (isset($_POST['update_schedule'])) {
        try {
            $stmt = $pdo->prepare("UPDATE feeding_schedules 
                                 SET food_type = ?, quantity = ?, 
                                     frequency = ?, special_diet = ?, notes = ?
                                 WHERE id = ?");
            $stmt->execute([
                $_POST['food_type'],
                $_POST['quantity'],
                $_POST['frequency'],
                $_POST['special_diet'],
                $_POST['notes'],
                $_POST['schedule_id']
            ]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = "Feeding schedule updated successfully.";
            } else {
                $_SESSION['error'] = "Failed to update feeding schedule.";
            }
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error updating feeding schedule: " . $e->getMessage();
        }
        header('Location: supervisor_feeding.php');
        exit();
    }

    // Handle delete AJAX request
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        header('Content-Type: application/json');
        try {
            $stmt = $pdo->prepare("DELETE FROM feeding_schedules WHERE id = ?");
            $result = $stmt->execute([$_POST['schedule_id']]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete schedule or it doesn\'t exist']);
            }
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
}

// Fetch animals for dropdown - all animals available
try {
    $stmt = $pdo->query("SELECT id, species, breed FROM animals ORDER BY species, id");
    $animals = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Error fetching animals: " . $e->getMessage();
    $animals = [];
}

// Fetch existing feeding schedules - all schedules
try {
    $stmt = $pdo->query("
        SELECT fs.id, fs.animal_id, fs.food_type, fs.quantity, fs.frequency, 
               fs.special_diet, fs.notes, fs.created_at,
               a.species, a.breed
        FROM feeding_schedules fs 
        JOIN animals a ON fs.animal_id = a.id
        ORDER BY fs.created_at DESC
    ");
    $schedules = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Error fetching feeding schedules: " . $e->getMessage();
    $schedules = [];
}

// Get feeding analytics data (supervisor can view feeding-related analytics)
try {
    // Get feeding frequency distribution
    $stmt = $pdo->query("SELECT 
        frequency,
        COUNT(*) as count
    FROM feeding_schedules
    GROUP BY frequency");
    $feedingFrequency = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Get food types usage
    $stmt = $pdo->query("SELECT 
        food_type,
        COUNT(*) as usage_count
    FROM feeding_schedules
    GROUP BY food_type
    ORDER BY usage_count DESC
    LIMIT 10");
    $foodTypes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Get animals with special diets
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_schedules,
        SUM(CASE WHEN special_diet IS NOT NULL AND special_diet != '' THEN 1 ELSE 0 END) as special_diet_count
    FROM feeding_schedules");
    $dietStats = $stmt->fetch() ?: ['total_schedules' => 0, 'special_diet_count' => 0];

    // Get feeding schedules by species
    $stmt = $pdo->query("SELECT 
        a.species,
        COUNT(fs.id) as schedule_count
    FROM animals a
    LEFT JOIN feeding_schedules fs ON a.id = fs.animal_id
    GROUP BY a.species
    ORDER BY schedule_count DESC");
    $speciesFeeding = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch(PDOException $e) {
    $feedingFrequency = [];
    $foodTypes = [];
    $dietStats = ['total_schedules' => 0, 'special_diet_count' => 0];
    $speciesFeeding = [];
}

$pageTitle = 'Feeding & Nutrition Management';
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h2>
            <i class="fas fa-utensils mr-2"></i>
            Feeding & Nutrition Management
        </h2>
        <button class="btn btn-primary" id="addScheduleBtn">
            <i class="fas fa-plus"></i> Add New Schedule
        </button>
    </div>

    <?php include 'includes/messages.php'; ?>
    
    <!-- Feeding Analytics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="analytics-card">
                <div class="card-icon feeding">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="card-content">
                    <h4>Total Schedules</h4>
                    <div class="stat-number"><?php echo $dietStats['total_schedules']; ?></div>
                    <div class="stat-description">Active feeding schedules</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="analytics-card">
                <div class="card-icon animals">
                    <i class="fas fa-paw"></i>
                </div>
                <div class="card-content">
                    <h4>Special Diets</h4>
                    <div class="stat-number"><?php echo $dietStats['special_diet_count']; ?></div>
                    <div class="stat-description">Animals with special diets</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="analytics-card">
                <div class="card-icon food-types">
                    <i class="fas fa-leaf"></i>
                </div>
                <div class="card-content">
                    <h4>Food Types</h4>
                    <div class="stat-number"><?php echo count($foodTypes); ?></div>
                    <div class="stat-description">Different food types used</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="analytics-card">
                <div class="card-icon species">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="card-content">
                    <h4>Species Coverage</h4>
                    <div class="stat-number"><?php echo count($speciesFeeding); ?></div>
                    <div class="stat-description">Species with feeding plans</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feeding Analytics Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Feeding Frequency Distribution</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="frequencyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Food Types Usage</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="foodTypesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Species Feeding Summary -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-paw"></i> Feeding Coverage by Species</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Species</th>
                            <th>Active Schedules</th>
                            <th>Coverage Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($speciesFeeding as $species): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars(ucfirst($species['species'])); ?></strong></td>
                                <td><?php echo $species['schedule_count']; ?></td>
                                <td>
                                    <?php 
                                    $scheduleCount = $species['schedule_count'];
                                    $badgeClass = $scheduleCount > 0 ? 'bg-success' : 'bg-warning';
                                    $status = $scheduleCount > 0 ? 'Covered' : 'Needs Schedule';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($speciesFeeding)): ?>
                            <tr>
                                <td colspan="3" class="text-center">No animals found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Feeding Schedules Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-alt"></i> Feeding Schedules</h3>
            <div class="card-actions">
                <div class="search-bar">
                    <input type="text" id="scheduleSearch" placeholder="Search schedules...">
                    <i class="fas fa-search"></i>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="schedulesTable">
                    <thead>
                        <tr>
                            <th>Animal ID</th>
                            <th>Species/Breed</th>
                            <th>Food Type</th>
                            <th>Quantity</th>
                            <th>Frequency</th>
                            <th>Special Diet</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($schedule['animal_id']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['species'] . '/' . $schedule['breed']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['food_type']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['quantity']); ?></td>
                            <td>
                                <?php 
                                switch($schedule['frequency']) {
                                    case 'daily':
                                        echo '<span class="badge bg-primary">Daily</span>';
                                        break;
                                    case 'twice_daily':
                                        echo '<span class="badge bg-info">Twice Daily</span>';
                                        break;
                                    case 'weekly':
                                        echo '<span class="badge bg-secondary">Weekly</span>';
                                        break;
                                    default:
                                        echo htmlspecialchars($schedule['frequency']);
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($schedule['special_diet'] ?: 'None'); ?></td>
                            <td><?php echo htmlspecialchars($schedule['notes'] ?: 'None'); ?></td>
                            <td>
                                <button class="btn-icon edit-schedule" 
                                        data-id="<?php echo $schedule['id']; ?>"
                                        data-animal-id="<?php echo $schedule['animal_id']; ?>"
                                        data-food-type="<?php echo htmlspecialchars($schedule['food_type']); ?>"
                                        data-quantity="<?php echo htmlspecialchars($schedule['quantity']); ?>"
                                        data-frequency="<?php echo htmlspecialchars($schedule['frequency']); ?>"
                                        data-special-diet="<?php echo htmlspecialchars($schedule['special_diet']); ?>"
                                        data-notes="<?php echo htmlspecialchars($schedule['notes']); ?>"
                                        title="Edit Schedule">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon delete-schedule" 
                                        data-id="<?php echo $schedule['id']; ?>"
                                        data-animal-id="<?php echo $schedule['animal_id']; ?>"
                                        title="Delete Schedule">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($schedules)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No feeding schedules found. Create your first schedule!</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Feed Consumption Card -->
    <div class="card mt-4">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Feed Consumption Analysis</h3>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Feed consumption analysis based on current feeding schedules and requirements.
            </div>
            
            <div class="feed-chart-container">
                <canvas id="feedConsumptionChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Add Feeding Schedule Modal -->
<div class="modal fade" id="addFeedingModal" tabindex="-1" aria-labelledby="addFeedingModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFeedingModalLabel">Add Feeding Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="addScheduleForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Animal <span class="text-danger">*</span></label>
                        <select name="animal_id" class="form-select" required>
                            <option value="">Select an animal</option>
                            <?php foreach ($animals as $animal): ?>
                                <option value="<?php echo $animal['id']; ?>">
                                    ID: <?php echo $animal['id'] . ' - ' . $animal['species'] . ' (' . $animal['breed'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Food Type <span class="text-danger">*</span></label>
                        <input type="text" name="food_type" class="form-control" placeholder="e.g., Hay, Grain, Pellets" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="text" name="quantity" class="form-control" placeholder="e.g., 2 kg, 500g, 1 cup" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Frequency <span class="text-danger">*</span></label>
                        <select name="frequency" class="form-select" required>
                            <option value="">Select frequency</option>
                            <option value="daily">Daily</option>
                            <option value="twice_daily">Twice Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Special Diet</label>
                        <textarea name="special_diet" class="form-control" rows="2" placeholder="Any special dietary requirements..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes or instructions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_schedule" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Feeding Schedule Modal -->
<div class="modal fade" id="editFeedingModal" tabindex="-1" aria-labelledby="editFeedingModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFeedingModalLabel">Edit Feeding Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editScheduleForm">
                <input type="hidden" name="schedule_id" id="edit_schedule_id">
                <input type="hidden" name="animal_id" id="edit_animal_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Animal ID</label>
                        <input type="text" id="edit_animal_id_display" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Food Type <span class="text-danger">*</span></label>
                        <input type="text" name="food_type" id="edit_food_type" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="text" name="quantity" id="edit_quantity" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Frequency <span class="text-danger">*</span></label>
                        <select name="frequency" id="edit_frequency" class="form-select" required>
                            <option value="daily">Daily</option>
                            <option value="twice_daily">Twice Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Special Diet</label>
                        <textarea name="special_diet" id="edit_special_diet" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_schedule" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning!</strong> Are you sure you want to delete this feeding schedule?
                </div>
                <p>This action cannot be undone. The feeding schedule will be permanently removed from the system.</p>
                <input type="hidden" id="delete_schedule_id">
                <input type="hidden" id="delete_animal_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">
                    <i class="fas fa-trash"></i> Delete Schedule
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded - Initializing event handlers');
    
    // Add New Schedule Button Click Handler
    const addScheduleBtn = document.getElementById('addScheduleBtn');
    if (addScheduleBtn) {
        addScheduleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Add Schedule button clicked');
            
            // Clear the form first
            const form = document.getElementById('addScheduleForm');
            if (form) {
                form.reset();
            }
            
            // Show the modal
            const addModal = new bootstrap.Modal(document.getElementById('addFeedingModal'));
            addModal.show();
        });
        console.log('Add Schedule button event listener attached');
    } else {
        console.error('Add Schedule button not found');
    }

    // Edit Schedule Button Click Handler using Event Delegation
    document.addEventListener('click', function(e) {
        const editButton = e.target.closest('.edit-schedule');
        if (editButton) {
            e.preventDefault();
            console.log('Edit button clicked');
            
            const data = editButton.dataset;
            console.log('Edit data:', data);
            
            // Populate edit modal with data
            document.getElementById('edit_schedule_id').value = data.id;
            document.getElementById('edit_animal_id').value = data.animalId;
            document.getElementById('edit_animal_id_display').value = 'Animal ID: ' + data.animalId;
            document.getElementById('edit_food_type').value = data.foodType;
            document.getElementById('edit_quantity').value = data.quantity;
            document.getElementById('edit_frequency').value = data.frequency;
            document.getElementById('edit_special_diet').value = data.specialDiet || '';
            document.getElementById('edit_notes').value = data.notes || '';
            
            // Show the modal
            const editModal = new bootstrap.Modal(document.getElementById('editFeedingModal'));
            editModal.show();
        }
    });

    // Delete Schedule Button Click Handler using Event Delegation
    document.addEventListener('click', function(e) {
        // Check if the clicked element or its parent is a delete button
        let deleteButton = null;
        if (e.target.classList.contains('delete-schedule')) {
            deleteButton = e.target;
        } else if (e.target.closest('.delete-schedule')) {
            deleteButton = e.target.closest('.delete-schedule');
        }
        
        if (deleteButton) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Delete button clicked');
            
            const scheduleId = deleteButton.getAttribute('data-id');
            const animalId = deleteButton.getAttribute('data-animal-id');
            
            console.log('Delete data:', {scheduleId, animalId});
            console.log('Delete button element:', deleteButton);
            
            // Store reference to the button and row for later use
            window.currentDeleteButton = deleteButton;
            window.currentDeleteRow = deleteButton.closest('tr');
            
            document.getElementById('delete_schedule_id').value = scheduleId;
            document.getElementById('delete_animal_id').value = animalId;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            deleteModal.show();
        }
    });

    // Delete Confirmation Handler
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Confirm delete clicked');
            
            const scheduleId = document.getElementById('delete_schedule_id').value;
            const animalId = document.getElementById('delete_animal_id').value;
            
            console.log('Deleting schedule:', {scheduleId, animalId});
            
            if (!scheduleId) {
                console.error('No schedule ID found');
                showAlert('error', 'Error: No schedule ID found');
                return;
            }
            
            // Get the row reference from stored global variable
            const tableRow = window.currentDeleteRow;
            console.log('Table row to delete:', tableRow);
            
            // Show loading state
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            this.disabled = true;
            
            // Create form data for POST request
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('schedule_id', scheduleId);
            
            // Send AJAX request
            fetch('supervisor_feeding.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Delete response status:', response.status);
                
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                
                // Get the response text first
                return response.text();
            })
            .then(responseText => {
                console.log('Raw response text:', responseText);
                
                // Try to parse as JSON
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (e) {
                    console.error('Failed to parse JSON. Response was:', responseText);
                    throw new Error('Server returned invalid JSON response');
                }
                
                console.log('Parsed response data:', data);
                
                // Reset button state
                this.innerHTML = originalText;
                this.disabled = false;
                
                // Close the modal
                const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
                if (deleteModal) {
                    deleteModal.hide();
                }
                
                if (data.success) {
                    console.log('Delete successful, removing row');
                    
                    // Remove the row from the table with animation
                    if (tableRow) {
                        console.log('Animating row removal');
                        tableRow.style.transition = 'opacity 0.3s ease';
                        tableRow.style.opacity = '0';
                        
                        setTimeout(() => {
                            console.log('Removing row from DOM');
                            tableRow.remove();
                            
                            // Check if table is empty
                            const tbody = document.querySelector('#schedulesTable tbody');
                            if (tbody && tbody.children.length === 0) {
                                console.log('Table is now empty, showing empty message');
                                tbody.innerHTML = '<tr><td colspan="8" class="text-center">No feeding schedules found. Create your first schedule!</td></tr>';
                            }
                        }, 300);
                    } else {
                        console.log('Could not find table row, reloading page');
                        // If we can't find the row, just reload the page
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    }
                    
                    // Show success message
                    showAlert('success', data.message || 'Schedule deleted successfully');
                } else {
                    console.error('Delete failed:', data.error);
                    // Show error message
                    showAlert('error', data.error || 'Failed to delete schedule');
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                
                // Reset button state
                this.innerHTML = originalText;
                this.disabled = false;
                
                // Close modal
                const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
                if (deleteModal) {
                    deleteModal.hide();
                }
                
                showAlert('error', 'An error occurred while processing your request: ' + error.message);
            });
        });
    }

    // Search functionality
    const searchInput = document.getElementById('scheduleSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            searchTable('schedulesTable', 'scheduleSearch');
        });
    }

    function searchTable(tableId, inputId) {
        const input = document.getElementById(inputId);
        const filter = input.value.toUpperCase();
        const table = document.getElementById(tableId);
        const tr = table.getElementsByTagName("tr");
        
        for (let i = 1; i < tr.length; i++) {
            let found = false;
            const td = tr[i].getElementsByTagName("td");
            
            for (let j = 0; j < td.length - 1; j++) { // Skip the last column (actions)
                if (td[j]) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
            }
            
            tr[i].style.display = found ? "" : "none";
        }
    }

    // Helper function to show alerts
    function showAlert(type, message) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="fas ${iconClass}"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        const pageHeader = document.querySelector('.page-header');
        pageHeader.insertAdjacentHTML('afterend', alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.remove();
            }
        }, 5000);
    }

    // Initialize feeding analytics charts
    const frequencyCtx = document.getElementById('frequencyChart');
    const foodTypesCtx = document.getElementById('foodTypesChart');
    const feedConsumptionCtx = document.getElementById('feedConsumptionChart');

    // Feeding Frequency Distribution Chart
    if (frequencyCtx) {
        const frequencyData = <?php echo json_encode($feedingFrequency); ?>;
        
        new Chart(frequencyCtx, {
            type: 'doughnut',
            data: {
                labels: frequencyData.map(item => {
                    switch(item.frequency) {
                        case 'daily': return 'Daily';
                        case 'twice_daily': return 'Twice Daily';
                        case 'weekly': return 'Weekly';
                        default: return item.frequency;
                    }
                }),
                datasets: [{
                    data: frequencyData.map(item => item.count),
                    backgroundColor: [
                        '#007bff',
                        '#28a745', 
                        '#ffc107',
                        '#dc3545',
                        '#6f42c1'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                let value = context.raw || 0;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = total > 0 ? ((value * 100) / total).toFixed(1) : 0;
                                return `${label}${value} schedules (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
    }

    // Food Types Usage Chart
    if (foodTypesCtx) {
        const foodTypesData = <?php echo json_encode($foodTypes); ?>;
        
        new Chart(foodTypesCtx, {
            type: 'bar',
            data: {
                labels: foodTypesData.map(item => item.food_type),
                datasets: [{
                    label: 'Usage Count',
                    data: foodTypesData.map(item => item.usage_count),
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0
                        }
                    }
                }
            }
        });
    }

    // Feed Consumption Chart
    if (feedConsumptionCtx) {
        const speciesData = <?php echo json_encode($speciesFeeding); ?>;
        
        new Chart(feedConsumptionCtx, {
            type: 'bar',
            data: {
                labels: speciesData.map(item => item.species.charAt(0).toUpperCase() + item.species.slice(1)),
                datasets: [{
                    label: 'Active Feeding Schedules',
                    data: speciesData.map(item => item.schedule_count),
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(153, 102, 255, 0.5)',
                        'rgba(255, 159, 64, 0.5)',
                        'rgba(255, 99, 132, 0.5)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(255, 99, 132, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.raw} feeding schedules`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            maxTicksLimit: 6
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0
                        }
                    }
                },
                layout: {
                    padding: 10
                }
            }
        });
    }

    console.log('All event handlers initialized successfully');
});
</script>

<style>
.main-content {
    padding: 20px;
}

.page-header {
    margin-bottom: 20px;
    align-items: center;
}

.page-header h2 {
    display: flex;
    align-items: center;
    margin: 0;
}

.page-header h2 i {
    margin-right: 10px;
}

.card {
    margin-bottom: 20px;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    border: none;
    border-radius: 0.5rem;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,0.125);
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
}

.card-header h3 {
    margin: 0;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
}

.card-header h3 i {
    margin-right: 0.5rem;
    color: #6c757d;
}

.card-body {
    padding: 1.25rem;
}

.table {
    width: 100%;
    margin-bottom: 1rem;
    color: #212529;
    vertical-align: middle;
    border-color: #dee2e6;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.table th, .table td {
    padding: 0.75rem;
    vertical-align: middle;
}

.badge {
    display: inline-block;
    padding: 0.25em 0.4em;
    font-size: 75%;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
}

.bg-primary {
    background-color: #0d6efd !important;
    color: #fff;
}

.bg-info {
    background-color: #0dcaf0 !important;
    color: #000;
}

.bg-secondary {
    background-color: #6c757d !important;
    color: #fff;
}

.bg-success {
    background-color: #198754 !important;
    color: #fff;
}

.bg-warning {
    background-color: #ffc107 !important;
    color: #000;
}

.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #212529;
    margin-right: 5px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-icon:hover {
    background-color: #e9ecef;
    transform: translateY(-1px);
}

.btn-icon i {
    font-size: 14px;
}

.btn-primary {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: #fff;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    border: 1px solid transparent;
    font-weight: 400;
    line-height: 1.5;
    text-align: center;
    text-decoration: none;
    vertical-align: middle;
    cursor: pointer;
    transition: all 0.15s ease-in-out;
}

.btn-primary:hover {
    background-color: #0b5ed7;
    border-color: #0a58ca;
}

.btn-secondary {
    background-color: #6c757d;
    border-color: #6c757d;
    color: #fff;
}

.btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
    color: #fff;
}

.btn-danger:hover {
    background-color: #c82333;
    border-color: #bd2130;
}

/* Analytics Cards */
.analytics-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    height: 120px;
    margin-bottom: 20px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.analytics-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    font-size: 24px;
    color: white;
}

.card-icon.feeding {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.card-icon.animals {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.card-icon.food-types {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.card-icon.species {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.card-content h4 {
    margin: 0 0 10px 0;
    color: #4a5568;
    font-size: 16px;
    font-weight: 600;
}

.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #2d3748;
    margin-bottom: 5px;
}

.stat-description {
    color: #718096;
    font-size: 14px;
}

.row {
    margin-left: -15px;
    margin-right: -15px;
}

.col-md-3, .col-md-4, .col-md-6 {
    padding-left: 15px;
    padding-right: 15px;
}

.search-bar {
    position: relative;
}

.search-bar input {
    padding: 0.375rem 2rem 0.375rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    width: 250px;
}

.search-bar i {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

/* Chart containers */
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

.feed-chart-container {
    position: relative;
    height: 400px;
    width: 100%;
}

/* Form styles */
.form-label {
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.form-control, .form-select {
    display: block;
    width: 100%;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    background-image: none;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus, .form-select:focus {
    color: #212529;
    background-color: #fff;
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.text-danger {
    color: #dc3545;
}

/* Alert styles */
.alert {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.375rem;
}

.alert-success {
    color: #0f5132;
    background-color: #d1eddd;
    border-color: #badbcc;
}

.alert-danger {
    color: #842029;
    background-color: #f8d7da;
    border-color: #f5c2c7;
}

.alert-warning {
    color: #664d03;
    background-color: #fff3cd;
    border-color: #ffecb5;
}

.alert-info {
    color: #055160;
    background-color: #cff4fc;
    border-color: #b6effb;
}

.alert-dismissible {
    padding-right: 3rem;
}

.alert i {
    margin-right: 0.5rem;
}

/* Responsive design */
@media (max-width: 992px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-header button {
        margin-top: 10px;
    }
    
    .search-bar input {
        width: 100%;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .card-actions {
        margin-top: 10px;
        width: 100%;
    }
}

@media (max-width: 768px) {
    .table-responsive {
        overflow-x: auto;
    }
    
    .btn-icon {
        width: 28px;
        height: 28px;
        margin-right: 3px;
    }
    
    .btn-icon i {
        font-size: 12px;
    }
    
    .analytics-card {
        height: auto;
        flex-direction: column;
        text-align: center;
        padding: 15px;
    }
    
    .card-icon {
        margin-right: 0;
        margin-bottom: 15px;
    }
}

/* Animation for smooth transitions */
.btn, .btn-icon {
    transition: all 0.15s ease-in-out;
}

.table tbody tr {
    transition: background-color 0.15s ease-in-out;
}

.table tbody tr:hover {
    background-color: rgba(0,0,0,0.075);
}

/* Loading states */
.btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}
</style>

<?php include 'includes/footer.php'; ?>