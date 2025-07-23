<?php
session_start();
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
        header('Location: feeding_management.php');
        exit();
    }
    
    if (isset($_POST['update_schedule'])) {
        try {
            $stmt = $pdo->prepare("UPDATE feeding_schedules 
                                 SET animal_id = ?, food_type = ?, quantity = ?, 
                                     frequency = ?, special_diet = ?, notes = ? 
                                 WHERE id = ?");
            $stmt->execute([
                $_POST['animal_id'],
                $_POST['food_type'],
                $_POST['quantity'],
                $_POST['frequency'],
                $_POST['special_diet'],
                $_POST['notes'],
                $_POST['schedule_id']
            ]);
            $_SESSION['success'] = "Feeding schedule updated successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error updating feeding schedule: " . $e->getMessage();
        }
        header('Location: feeding_management.php');
        exit();
    }

    // Handle delete AJAX request
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        header('Content-Type: application/json');
        try {
            $stmt = $pdo->prepare("DELETE FROM feeding_schedules WHERE id = ?");
            $result = $stmt->execute([$_POST['schedule_id']]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete schedule']);
            }
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
}

// Fetch animals for dropdown
try {
    $animals = $pdo->query("SELECT id, species, breed FROM animals")->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Error fetching animals: " . $e->getMessage();
    $animals = [];
}

// Fetch existing feeding schedules
try {
    $schedules = $pdo->query("
        SELECT fs.*, a.species, a.breed 
        FROM feeding_schedules fs 
        JOIN animals a ON fs.animal_id = a.id
        ORDER BY fs.created_at DESC
    ")->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Error fetching feeding schedules: " . $e->getMessage();
    $schedules = [];
}

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h2>
            <i class="fas fa-utensils mr-2"></i>
            Feeding Management
        </h2>
        <button class="btn btn-primary" data-toggle="modal" data-target="#addFeedingModal">
            <i class="fas fa-plus"></i> Add New Schedule
        </button>
            <button class="btn btn-primary" onclick="location.href='animal_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Animal Management
            </button>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Table Content -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
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
                            <td><?php echo htmlspecialchars($schedule['frequency']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['special_diet']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['notes']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-schedule" 
                                        data-id="<?php echo $schedule['id']; ?>"
                                        data-animal-id="<?php echo $schedule['animal_id']; ?>"
                                        data-food-type="<?php echo htmlspecialchars($schedule['food_type']); ?>"
                                        data-quantity="<?php echo htmlspecialchars($schedule['quantity']); ?>"
                                        data-frequency="<?php echo htmlspecialchars($schedule['frequency']); ?>"
                                        data-special-diet="<?php echo htmlspecialchars($schedule['special_diet']); ?>"
                                        data-notes="<?php echo htmlspecialchars($schedule['notes']); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-schedule" 
                                        data-id="<?php echo $schedule['id']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Feeding Schedule Modal -->
<div class="modal fade" id="addFeedingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Feeding Schedule</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Animal ID</label>
                        <select name="animal_id" class="form-control" required>
                            <?php foreach ($animals as $animal): ?>
                                <option value="<?php echo $animal['id']; ?>">
                                    <?php echo $animal['id'] . ' - ' . $animal['species'] . ' (' . $animal['breed'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Food Type</label>
                        <input type="text" name="food_type" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="text" name="quantity" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Frequency</label>
                        <select name="frequency" class="form-control" required>
                            <option value="daily">Daily</option>
                            <option value="twice_daily">Twice Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Special Diet</label>
                        <textarea name="special_diet" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="add_schedule" class="btn btn-primary">Add Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Feeding Schedule Modal -->
<div class="modal fade" id="editFeedingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Feeding Schedule</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="schedule_id" id="edit_schedule_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Animal ID</label>
                        <select name="animal_id" id="edit_animal_id" class="form-control" required>
                            <?php foreach ($animals as $animal): ?>
                                <option value="<?php echo $animal['id']; ?>">
                                    <?php echo $animal['id'] . ' - ' . $animal['species'] . ' (' . $animal['breed'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Food Type</label>
                        <input type="text" name="food_type" id="edit_food_type" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="text" name="quantity" id="edit_quantity" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Frequency</label>
                        <select name="frequency" id="edit_frequency" class="form-control" required>
                            <option value="daily">Daily</option>
                            <option value="twice_daily">Twice Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Special Diet</label>
                        <textarea name="special_diet" id="edit_special_diet" class="form-control"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" id="edit_notes" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="update_schedule" class="btn btn-primary">Update Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add this before closing body tag -->
<script>
$(document).ready(function() {
    // Handle edit button click
    $('.edit-schedule').click(function() {
        const data = $(this).data();
        $('#edit_schedule_id').val(data.id);
        $('#edit_animal_id').val(data.animalId);
        $('#edit_food_type').val(data.foodType);
        $('#edit_quantity').val(data.quantity);
        $('#edit_frequency').val(data.frequency);
        $('#edit_special_diet').val(data.specialDiet);
        $('#edit_notes').val(data.notes);
        $('#editFeedingModal').modal('show');
    });

    // Handle delete button click
    $('.delete-schedule').click(function() {
        const scheduleId = $(this).data('id');
        const row = $(this).closest('tr');
        
        if (confirm('Are you sure you want to delete this feeding schedule?')) {
            $.ajax({
                url: 'feeding_management.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'delete',
                    schedule_id: scheduleId
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the row from the table
                        row.fadeOut(400, function() {
                            $(this).remove();
                        });
                        // Show success message
                        $('<div class="alert alert-success alert-dismissible fade show">' +
                          'Schedule deleted successfully' +
                          '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                          '</div>').insertAfter('.page-header');
                    } else {
                        alert('Error deleting schedule: ' + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Delete error:', error);
                    alert('Error performing delete operation. Please try again.');
                }
            });
        }
    });
});
</script>

<style>
.main-content {
    padding: 20px;
}

.page-header {
    margin-bottom: 20px;
}

.card {
    margin-bottom: 20px;
}

.table th {
    background-color: #f8f9fa;
}

.btn-sm {
    margin: 0 2px;
}

.alert {
    margin-bottom: 20px;
}
</style>

<?php include 'includes/footer.php'; ?>