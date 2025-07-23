<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Process form submission for adding a new field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO fields (
                field_name, location, area, soil_type, last_crop, notes
            ) VALUES (
                :field_name, :location, :area, :soil_type, :last_crop, :notes
            )
        ");
        
        $stmt->execute([
            'field_name' => $_POST['field_name'],
            'location' => $_POST['location'],
            'area' => $_POST['area'],
            'soil_type' => $_POST['soil_type'],
            'last_crop' => $_POST['last_crop'],
            'notes' => $_POST['notes']
        ]);
        
        $_SESSION['success_message'] = 'Field added successfully!';
        header('Location: field_management.php');
        exit;
        
    } catch(PDOException $e) {
        error_log("Error adding field: " . $e->getMessage());
        $_SESSION['error_message'] = 'Error adding field: ' . $e->getMessage();
    }
}

// Process form submission for editing a field
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $stmt = $pdo->prepare("
            UPDATE fields SET
                field_name = :field_name,
                location = :location,
                area = :area,
                soil_type = :soil_type,
                last_crop = :last_crop,
                notes = :notes
            WHERE id = :id
        ");
        
        $stmt->execute([
            'field_name' => $_POST['field_name'],
            'location' => $_POST['location'],
            'area' => $_POST['area'],
            'soil_type' => $_POST['soil_type'],
            'last_crop' => $_POST['last_crop'],
            'notes' => $_POST['notes'],
            'id' => $_POST['field_id']
        ]);
        
        $_SESSION['success_message'] = 'Field updated successfully!';
        header('Location: field_management.php');
        exit;
        
    } catch(PDOException $e) {
        error_log("Error updating field: " . $e->getMessage());
        $_SESSION['error_message'] = 'Error updating field: ' . $e->getMessage();
    }
}

// Process field deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM fields WHERE id = :id");
        $stmt->execute(['id' => $_POST['field_id']]);
        
        $_SESSION['success_message'] = 'Field deleted successfully!';
        header('Location: field_management.php');
        exit;
        
    } catch(PDOException $e) {
        error_log("Error deleting field: " . $e->getMessage());
        $_SESSION['error_message'] = 'Error deleting field: ' . $e->getMessage();
    }
}

// Fetch all fields
try {
    $stmt = $pdo->query("SELECT * FROM fields ORDER BY field_name");
    $fields = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

$pageTitle = 'Field Management';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-map-marker-alt"></i> Field Management</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="openAddFieldModal()">
                <i class="fas fa-plus"></i> Add New Field
            </button>
            <button class="btn btn-secondary" onclick="location.href='crop_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Crop Management
            </button>
        </div>
    </div>

    <!-- Field List Table -->
    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-list"></i> All Fields</h3>
            <div class="card-actions">
                <div class="search-bar">
                    <input type="text" id="fieldSearch" onkeyup="searchFields()" placeholder="Search fields...">
                    <i class="fas fa-search"></i>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table" id="fieldTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Field Name</th>
                        <th>Location</th>
                        <th>Area (acres)</th>
                        <th>Soil Type</th>
                        <th>Last Crop</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fields)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No fields found. Add a field to get started.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fields as $field): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($field['id']); ?></td>
                                <td><?php echo htmlspecialchars($field['field_name']); ?></td>
                                <td><?php echo htmlspecialchars($field['location']); ?></td>
                                <td><?php echo htmlspecialchars($field['area']); ?></td>
                                <td><?php echo htmlspecialchars($field['soil_type']); ?></td>
                                <td><?php echo htmlspecialchars($field['last_crop']); ?></td>
                                <td class="actions">
                                    <a href="#" onclick="openEditFieldModal(<?php echo htmlspecialchars(json_encode($field)); ?>)" class="btn-icon" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" onclick="confirmDeleteField(<?php echo $field['id']; ?>, '<?php echo htmlspecialchars($field['field_name']); ?>')" class="btn-icon text-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Field Modal -->
<div id="addFieldModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Field</h3>
            <span class="close" onclick="closeAddFieldModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="addFieldForm">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="field_name">Field Name*</label>
                    <input type="text" id="field_name" name="field_name" required placeholder="e.g., North Field, Plot 1">
                </div>
                
                <div class="form-group">
                    <label for="location">Location*</label>
                    <input type="text" id="location" name="location" required placeholder="e.g., North Farm, Section 3">
                </div>
                
                <div class="form-group">
                    <label for="area">Area (acres)*</label>
                    <input type="number" id="area" name="area" step="0.01" required placeholder="e.g., 5.5">
                </div>
                
                <div class="form-group">
                    <label for="soil_type">Soil Type</label>
                    <select id="soil_type" name="soil_type">
                        <option value="">Select soil type</option>
                        <option value="Clay">Clay</option>
                        <option value="Sandy">Sandy</option>
                        <option value="Silty">Silty</option>
                        <option value="Peaty">Peaty</option>
                        <option value="Chalky">Chalky</option>
                        <option value="Loamy">Loamy</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="last_crop">Last Crop</label>
                    <input type="text" id="last_crop" name="last_crop" placeholder="e.g., Corn, Wheat">
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Enter any additional notes about this field"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Field
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddFieldModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Field Modal -->
<div id="editFieldModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Field</h3>
            <span class="close" onclick="closeEditFieldModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="editFieldForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="field_id" id="edit_field_id">
                
                <div class="form-group">
                    <label for="edit_field_name">Field Name*</label>
                    <input type="text" id="edit_field_name" name="field_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_location">Location*</label>
                    <input type="text" id="edit_location" name="location" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_area">Area (acres)*</label>
                    <input type="number" id="edit_area" name="area" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_soil_type">Soil Type</label>
                    <select id="edit_soil_type" name="soil_type">
                        <option value="">Select soil type</option>
                        <option value="Clay">Clay</option>
                        <option value="Sandy">Sandy</option>
                        <option value="Silty">Silty</option>
                        <option value="Peaty">Peaty</option>
                        <option value="Chalky">Chalky</option>
                        <option value="Loamy">Loamy</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_last_crop">Last Crop</label>
                    <input type="text" id="edit_last_crop" name="last_crop">
                </div>
                
                <div class="form-group">
                    <label for="edit_notes">Notes</label>
                    <textarea id="edit_notes" name="notes" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Field
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditFieldModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Field Confirmation Modal -->
<div id="deleteFieldModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Deletion</h3>
            <span class="close" onclick="closeDeleteFieldModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete the field "<span id="deleteFieldName"></span>"?</p>
            <p class="text-warning">This action cannot be undone. Any crops associated with this field will need to be reassigned.</p>
            
            <form method="POST" action="" id="deleteFieldForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="field_id" id="delete_field_id">
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Field
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteFieldModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Add Field Modal Functions
function openAddFieldModal() {
    document.getElementById('addFieldModal').style.display = 'block';
}

function closeAddFieldModal() {
    document.getElementById('addFieldModal').style.display = 'none';
    document.getElementById('addFieldForm').reset();
}

// Edit Field Modal Functions
function openEditFieldModal(field) {
    document.getElementById('edit_field_id').value = field.id;
    document.getElementById('edit_field_name').value = field.field_name;
    document.getElementById('edit_location').value = field.location;
    document.getElementById('edit_area').value = field.area;
    document.getElementById('edit_soil_type').value = field.soil_type;
    document.getElementById('edit_last_crop').value = field.last_crop;
    document.getElementById('edit_notes').value = field.notes;
    
    document.getElementById('editFieldModal').style.display = 'block';
}

function closeEditFieldModal() {
    document.getElementById('editFieldModal').style.display = 'none';
    document.getElementById('editFieldForm').reset();
}

// Delete Field Functions
function confirmDeleteField(fieldId, fieldName) {
    document.getElementById('delete_field_id').value = fieldId;
    document.getElementById('deleteFieldName').textContent = fieldName;
    document.getElementById('deleteFieldModal').style.display = 'block';
}

function closeDeleteFieldModal() {
    document.getElementById('deleteFieldModal').style.display = 'none';
}

// Search Function
function searchFields() {
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("fieldSearch");
    filter = input.value.toUpperCase();
    table = document.getElementById("fieldTable");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) {
        var found = false;
        // Search in first 3 columns (ID, Field Name, Location)
        for (var j = 0; j < 3; j++) {
            td = tr[i].getElementsByTagName("td")[j];
            if (td) {
                txtValue = td.textContent || td.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        if (found) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    if (event.target == document.getElementById('addFieldModal')) {
        closeAddFieldModal();
    }
    if (event.target == document.getElementById('editFieldModal')) {
        closeEditFieldModal();
    }
    if (event.target == document.getElementById('deleteFieldModal')) {
        closeDeleteFieldModal();
    }
}
</script>

<style>
/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    overflow: auto;
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #888;
    border-radius: 5px;
    width: 50%;
    max-width: 600px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #eee;
    border-top-left-radius: 5px;
    border-top-right-radius: 5px;
}

.modal-header h3 {
    margin: 0;
    color: #333;
}

.modal-body {
    padding: 20px;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: #333;
    text-decoration: none;
}

.text-warning {
    color: #f39c12;
}

.text-danger {
    color: #e74c3c;
}
</style>

<?php include 'includes/footer.php'; ?>