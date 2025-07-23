<?php
// housing_location.php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

// Define valid locations for each species
$locationMapping = array(
    'Cattle' => array('Barn A', 'Barn B', 'Pasture 1', 'Pasture 2'),
    'Goat' => array('Goat Shed 1', 'Goat Shed 2', 'Grazing Area'),
    'Buffalo' => array('Buffalo Barn', 'Water Buffalo Area', 'Grazing Field'),
    'Chicken' => array('Chicken Coop A', 'Chicken Coop B', 'Free Range Area'),
    'Duck' => array('Duck Pond', 'Duck House', 'Waterfowl Area'),
    'Rabbit' => array('Rabbit Hutch 1', 'Rabbit Hutch 2', 'Indoor Rabbit Area')
);

// Debug output
echo "<!-- Debug: Available Animals -->";
$debug_animals = $pdo->query("SELECT id, species, breed FROM animals")->fetchAll();
foreach ($debug_animals as $animal) {
    echo "<!-- Animal: ID=" . $animal['id'] . ", Species=" . $animal['species'] . ", Breed=" . $animal['breed'] . " -->";
}

// Handle form submission for adding/updating housing assignments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_assignment'])) {
        try {
            // Get the animal's species to validate location
            $stmt = $pdo->prepare("SELECT species FROM animals WHERE id = ?");
            $stmt->execute([$_POST['animal_id']]);
            $animal = $stmt->fetch();
            
            // Only validate if strict validation is required
            // For now, we'll skip the validation since we're using a static dropdown
            /*
            if (!isset($locationMapping[$animal['species']]) || 
                !in_array($_POST['location'], $locationMapping[$animal['species']])) {
                throw new Exception("Invalid location for this animal species.");
            }
            */

            $stmt = $pdo->prepare("INSERT INTO housing_assignments (animal_id, location, assigned_date, status, notes) 
                                 VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['animal_id'],
                $_POST['location'],
                $_POST['assigned_date'],
                $_POST['status'],
                $_POST['notes']
            ]);
            $_SESSION['success'] = "Housing assignment added successfully.";
        } catch(Exception $e) {
            $_SESSION['error'] = "Error adding housing assignment: " . $e->getMessage();
        }
    }

    if (isset($_POST['edit_assignment'])) {
        try {
            $stmt = $pdo->prepare("UPDATE housing_assignments 
                                 SET location = ?, assigned_date = ?, status = ?, notes = ? 
                                 WHERE id = ?");
            $stmt->execute([
                $_POST['location'],
                $_POST['assigned_date'],
                $_POST['status'],
                $_POST['notes'],
                $_POST['assignment_id']
            ]);
            $_SESSION['success'] = "Housing assignment updated successfully.";
        } catch(Exception $e) {
            $_SESSION['error'] = "Error updating housing assignment: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_assignment'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM housing_assignments WHERE id = ?");
            $stmt->execute([$_POST['assignment_id']]);
            $_SESSION['success'] = "Housing assignment deleted successfully.";
        } catch(Exception $e) {
            $_SESSION['error'] = "Error deleting housing assignment: " . $e->getMessage();
        }
    }
}

// Fetch existing housing assignments
try {
    $assignments = $pdo->query("
        SELECT ha.*, a.species, a.breed 
        FROM housing_assignments ha 
        JOIN animals a ON ha.animal_id = a.id
        ORDER BY ha.assigned_date DESC
    ")->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Error fetching housing assignments: " . $e->getMessage();
    $assignments = [];
}

$pageTitle = 'Housing & Location';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-home"></i> Housing & Location Management</h2>
        <div class="action-buttons">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addHousingModal">
                <i class="fas fa-plus"></i> Add New Assignment
            </button>
            <button class="btn btn-primary" onclick="location.href='animal_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Animal Management
            </button>
        </div>
    </div>

    <?php include 'includes/messages.php'; ?>

    <!-- Housing Assignments Table -->
    <div class="card">
        <div class="card-header">
            <h3>Housing Assignments</h3>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Animal ID</th>
                        <th>Species/Breed</th>
                        <th>Location</th>
                        <th>Assigned Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($assignment['animal_id']); ?></td>
                        <td><?php echo htmlspecialchars($assignment['species'] . '/' . $assignment['breed']); ?></td>
                        <td><?php echo htmlspecialchars($assignment['location']); ?></td>
                        <td><?php echo htmlspecialchars($assignment['assigned_date']); ?></td>
                        <td><?php echo htmlspecialchars($assignment['status']); ?></td>
                        <td><?php echo htmlspecialchars($assignment['notes']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary edit-assignment" data-id="<?php echo $assignment['id']; ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-assignment" data-id="<?php echo $assignment['id']; ?>">
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

<!-- Add Housing Assignment Modal -->
<div class="modal fade" id="addHousingModal" tabindex="-1" role="dialog" aria-labelledby="addHousingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addHousingModalLabel">Add Housing Assignment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="housingForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="animal_id">Animal ID</label>
                        <select name="animal_id" id="animal_id" class="form-control" required>
                            <option value="">Select Animal</option>
                            <?php
                            // Modified query to exclude animals that already have active housing assignments
                            $animals = $pdo->query("
                                SELECT a.id, a.species, a.breed 
                                FROM animals a 
                                WHERE NOT EXISTS (
                                    SELECT 1 
                                    FROM housing_assignments ha 
                                    WHERE ha.animal_id = a.id 
                                    AND ha.status = 'active'
                                )
                                ORDER BY a.id
                            ")->fetchAll();

                            foreach ($animals as $animal) {
                                echo "<option value='" . htmlspecialchars($animal['id']) . "'>" .
                                    htmlspecialchars($animal['id'] . " - " . $animal['species'] . " (" . $animal['breed'] . ")") .
                                    "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="location">Location</label>
                        <select name="location" id="location" class="form-control" required>
                            <option value="">Select Location</option>
                            <!-- Barn Areas -->
                            <option value="Barn A">Barn A</option>
                            <option value="Barn B">Barn B</option>
                            <option value="Buffalo Barn">Buffalo Barn</option>
                            
                            <!-- Pastures and Grazing Areas -->
                            <option value="Pasture 1">Pasture 1</option>
                            <option value="Pasture 2">Pasture 2</option>
                            <option value="Grazing Area">Grazing Area</option>
                            <option value="Grazing Field">Grazing Field</option>
                            
                            <!-- Sheds and Coops -->
                            <option value="Goat Shed 1">Goat Shed 1</option>
                            <option value="Goat Shed 2">Goat Shed 2</option>
                            <option value="Chicken Coop A">Chicken Coop A</option>
                            <option value="Chicken Coop B">Chicken Coop B</option>
                            
                            <!-- Water Areas -->
                            <option value="Duck Pond">Duck Pond</option>
                            <option value="Duck House">Duck House</option>
                            <option value="Waterfowl Area">Waterfowl Area</option>
                            <option value="Water Buffalo Area">Water Buffalo Area</option>
                            
                            <!-- Small Animal Areas -->
                            <option value="Rabbit Hutch 1">Rabbit Hutch 1</option>
                            <option value="Rabbit Hutch 2">Rabbit Hutch 2</option>
                            <option value="Indoor Rabbit Area">Indoor Rabbit Area</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="assigned_date">Assigned Date</label>
                        <input type="date" name="assigned_date" id="assigned_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="add_assignment" class="btn btn-primary">Add Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Housing Assignment Modal -->
<div class="modal fade" id="editHousingModal" tabindex="-1" role="dialog" aria-labelledby="editHousingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editHousingModalLabel">Edit Housing Assignment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="editHousingForm">
                <div class="modal-body">
                    <input type="hidden" name="assignment_id" id="edit_assignment_id">
                    <div class="form-group">
                        <label for="edit_animal_id">Animal ID</label>
                        <input type="text" id="edit_animal_id" class="form-control" readonly>
                    </div>

                    <div class="form-group">
                        <label for="edit_location">Location</label>
                        <select name="location" id="edit_location" class="form-control" required>
                            <option value="">Select Location</option>
                            <!-- Barn Areas -->
                            <option value="Barn A">Barn A</option>
                            <option value="Barn B">Barn B</option>
                            <option value="Buffalo Barn">Buffalo Barn</option>
                            <!-- Pastures and Grazing Areas -->
                            <option value="Pasture 1">Pasture 1</option>
                            <option value="Pasture 2">Pasture 2</option>
                            <option value="Grazing Area">Grazing Area</option>
                            <option value="Grazing Field">Grazing Field</option>
                            <!-- Sheds and Coops -->
                            <option value="Goat Shed 1">Goat Shed 1</option>
                            <option value="Goat Shed 2">Goat Shed 2</option>
                            <option value="Chicken Coop A">Chicken Coop A</option>
                            <option value="Chicken Coop B">Chicken Coop B</option>
                            <!-- Water Areas -->
                            <option value="Duck Pond">Duck Pond</option>
                            <option value="Duck House">Duck House</option>
                            <option value="Waterfowl Area">Waterfowl Area</option>
                            <option value="Water Buffalo Area">Water Buffalo Area</option>
                            <!-- Small Animal Areas -->
                            <option value="Rabbit Hutch 1">Rabbit Hutch 1</option>
                            <option value="Rabbit Hutch 2">Rabbit Hutch 2</option>
                            <option value="Indoor Rabbit Area">Indoor Rabbit Area</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_assigned_date">Assigned Date</label>
                        <input type="date" name="assigned_date" id="edit_assigned_date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_notes">Notes</label>
                        <textarea name="notes" id="edit_notes" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="edit_assignment" class="btn btn-primary">Update Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteHousingModal" tabindex="-1" role="dialog" aria-labelledby="deleteHousingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteHousingModalLabel">Delete Housing Assignment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="assignment_id" id="delete_assignment_id">
                    <p>Are you sure you want to delete this housing assignment?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_assignment" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Set today's date as default for assigned_date
    const today = new Date().toISOString().split('T')[0];
    $('#assigned_date').val(today);
});


// Edit button click handler
$('.edit-assignment').click(function() {
    const assignmentId = $(this).data('id');
    const row = $(this).closest('tr');
    
    // Fill the edit modal with data from the table row
    $('#edit_assignment_id').val(assignmentId);
    $('#edit_animal_id').val(row.find('td:eq(0)').text());
    $('#edit_location').val(row.find('td:eq(2)').text());
    $('#edit_assigned_date').val(row.find('td:eq(3)').text());
    $('#edit_status').val(row.find('td:eq(4)').text().toLowerCase());
    $('#edit_notes').val(row.find('td:eq(5)').text());
    
    // Show the edit modal
    $('#editHousingModal').modal('show');
});

// Delete button click handler
$('.delete-assignment').click(function() {
    const assignmentId = $(this).data('id');
    $('#delete_assignment_id').val(assignmentId);
    $('#deleteHousingModal').modal('show');
});

</script>
<?php include 'includes/footer.php'; ?>