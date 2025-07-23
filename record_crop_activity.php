<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Get crop ID from URL
$crop_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$crop_id) {
    $_SESSION['error_message'] = 'Invalid crop ID.';
    header('Location: crop_list.php');
    exit;
}

// Fetch crop details
try {
    $stmt = $pdo->prepare("
        SELECT c.*, f.field_name, f.location
        FROM crops c
        LEFT JOIN fields f ON c.field_id = f.id
        WHERE c.id = :id
    ");
    $stmt->execute(['id' => $crop_id]);
    $crop = $stmt->fetch();
    
    if (!$crop) {
        $_SESSION['error_message'] = 'Crop not found.';
        header('Location: crop_list.php');
        exit;
    }
    
} catch(PDOException $e) {
    error_log("Error fetching crop details: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error retrieving crop information.';
    header('Location: crop_list.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO crop_activities (
                crop_id, activity_type, activity_date, description, quantity, unit, performed_by, notes
            ) VALUES (
                :crop_id, :activity_type, :activity_date, :description, :quantity, :unit, :performed_by, :notes
            )
        ");
        
        $stmt->execute([
            'crop_id' => $crop_id,
            'activity_type' => $_POST['activity_type'],
            'activity_date' => $_POST['activity_date'],
            'description' => $_POST['description'],
            'quantity' => $_POST['quantity'] ?: null,
            'unit' => $_POST['unit'] ?: null,
            'performed_by' => $_SESSION['user_id'] ?? null,
            'notes' => $_POST['notes'] ?: null
        ]);
        
        // Update crop details if needed
        if (isset($_POST['update_crop']) && $_POST['update_crop'] == 'yes') {
            // Determine if growth stage should be updated based on activity
            $growth_stage = $crop['growth_stage'];
            if ($_POST['activity_type'] == 'planting') {
                $growth_stage = 'seedling';
            } elseif ($_POST['activity_type'] == 'fertilization' && $growth_stage == 'seedling') {
                $growth_stage = 'vegetative';
            }
            
            $updateStmt = $pdo->prepare("
                UPDATE crops SET
                    growth_stage = :growth_stage,
                    next_action = :next_action,
                    next_action_date = :next_action_date
                WHERE id = :id
            ");
            
            $updateStmt->execute([
                'growth_stage' => $growth_stage,
                'next_action' => $_POST['next_action'] ?: $crop['next_action'],
                'next_action_date' => $_POST['next_action_date'] ?: $crop['next_action_date'],
                'id' => $crop_id
            ]);
        }
        
        $_SESSION['success_message'] = 'Activity recorded successfully!';
        header('Location: crop_details.php?id=' . $crop_id);
        exit;
        
    } catch(PDOException $e) {
        error_log("Error recording activity: " . $e->getMessage());
        $_SESSION['error_message'] = 'Error recording activity: ' . $e->getMessage();
    }
}

$pageTitle = 'Record Crop Activity: ' . $crop['crop_name'];
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-clipboard-list"></i> Record Activity: <?php echo htmlspecialchars($crop['crop_name']); ?></h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='crop_details.php?id=<?php echo $crop_id; ?>'">
                <i class="fas fa-arrow-left"></i> Back to Crop Details
            </button>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-header">
            <h3>Activity Details</h3>
        </div>
        <div class="content-card-body">
            <form method="POST" action="" class="form-grid">
                <div class="form-group span-2">
                    <label for="activity_type">Activity Type*</label>
                    <select id="activity_type" name="activity_type" required onchange="updateFields()">
                        <option value="">Select activity type</option>
                        <option value="planting">Planting</option>
                        <option value="irrigation">Irrigation</option>
                        <option value="fertilization">Fertilization</option>
                        <option value="pesticide">Pesticide Application</option>
                        <option value="weeding">Weeding</option>
                        <option value="harvest">Partial Harvest</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group span-2">
                    <label for="activity_date">Activity Date*</label>
                    <input type="date" id="activity_date" name="activity_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group span-4">
                    <label for="description">Description*</label>
                    <input type="text" id="description" name="description" required placeholder="E.g., Applied nitrogen fertilizer, Irrigated field, etc.">
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" id="quantity" name="quantity" step="0.01" placeholder="E.g., 50">
                </div>
                
                <div class="form-group">
                    <label for="unit">Unit</label>
                    <select id="unit" name="unit">
                        <option value="">Select unit</option>
                        <option value="kg">Kilograms (kg)</option>
                        <option value="g">Grams (g)</option>
                        <option value="L">Liters (L)</option>
                        <option value="mL">Milliliters (mL)</option>
                        <option value="gal">Gallons (gal)</option>
                        <option value="lb">Pounds (lb)</option>
                        <option value="hours">Hours</option>
                        <option value="mins">Minutes</option>
                    </select>
                </div>
                
                <div class="form-group span-4">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Any additional information about this activity"></textarea>
                </div>
                
                <div class="form-divider span-4">
                    <h4>Update Crop Information</h4>
                </div>
                
                <div class="form-group span-4">
                    <label class="checkbox-container">
                        <input type="checkbox" id="update_crop" name="update_crop" value="yes" checked>
                        <span class="checkmark"></span>
                        Update crop information based on this activity
                    </label>
                </div>
                
                <div id="update_fields" class="span-4">
                    <div class="form-group span-2">
                        <label for="next_action">Next Action</label>
                        <input type="text" id="next_action" name="next_action" placeholder="E.g., Apply fertilizer, Check for pests, etc.">
                    </div>
                    
                    <div class="form-group span-2">
                        <label for="next_action_date">Next Action Date</label>
                        <input type="date" id="next_action_date" name="next_action_date">
                    </div>
                </div>
                
                <div class="form-actions span-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Record Activity
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateFields() {
    const activityType = document.getElementById('activity_type').value;
    const descriptionField = document.getElementById('description');
    const quantityField = document.getElementById('quantity');
    const unitField = document.getElementById('unit');
    const nextActionField = document.getElementById('next_action');
    const nextActionDateField = document.getElementById('next_action_date');
    
    // Reset fields
    descriptionField.value = '';
    
    // Set default values based on activity type
    switch(activityType) {
        case 'planting':
            descriptionField.value = 'Planted <?php echo htmlspecialchars($crop['crop_name']); ?>';
            nextActionField.value = 'Check germination';
            // Set next action date to 7 days from now
            const germinationDate = new Date();
            germinationDate.setDate(germinationDate.getDate() + 7);
            nextActionDateField.valueAsDate = germinationDate;
            break;
        case 'irrigation':
            descriptionField.value = 'Irrigated <?php echo htmlspecialchars($crop['crop_name']); ?>';
            quantityField.value = '';
            unitField.value = 'L';
            nextActionField.value = 'Check soil moisture';
            // Set next action date to 3 days from now
            const moistureCheckDate = new Date();
            moistureCheckDate.setDate(moistureCheckDate.getDate() + 3);
            nextActionDateField.valueAsDate = moistureCheckDate;
            break;
        case 'fertilization':
            descriptionField.value = 'Applied fertilizer to <?php echo htmlspecialchars($crop['crop_name']); ?>';
            unitField.value = 'kg';
            nextActionField.value = 'Check crop response';
            // Set next action date to 7 days from now
            const responseDate = new Date();
            responseDate.setDate(responseDate.getDate() + 7);
            nextActionDateField.valueAsDate = responseDate;
            break;
        case 'pesticide':
            descriptionField.value = 'Applied pesticide to <?php echo htmlspecialchars($crop['crop_name']); ?>';
            unitField.value = 'L';
            nextActionField.value = 'Check pest control effectiveness';
            // Set next action date to 5 days from now
            const pestCheckDate = new Date();
            pestCheckDate.setDate(pestCheckDate.getDate() + 5);
            nextActionDateField.valueAsDate = pestCheckDate;
            break;
        case 'weeding':
            descriptionField.value = 'Removed weeds from <?php echo htmlspecialchars($crop['crop_name']); ?> field';
            quantityField.value = '';
            unitField.value = 'hours';
            nextActionField.value = 'Check for new weed growth';
            // Set next action date to 14 days from now
            const weedCheckDate = new Date();
            weedCheckDate.setDate(weedCheckDate.getDate() + 14);
            nextActionDateField.valueAsDate = weedCheckDate;
            break;
        case 'harvest':
            descriptionField.value = 'Partially harvested <?php echo htmlspecialchars($crop['crop_name']); ?>';
            unitField.value = 'kg';
            nextActionField.value = 'Complete harvest';
            // Set next action date to 7 days from now
            const harvestDate = new Date();
            harvestDate.setDate(harvestDate.getDate() + 7);
            nextActionDateField.valueAsDate = harvestDate;
            break;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Set default value for activity date to today
    document.getElementById('activity_date').valueAsDate = new Date();
    
    // Toggle update fields visibility
    document.getElementById('update_crop').addEventListener('change', function() {
        document.getElementById('update_fields').style.display = this.checked ? 'grid' : 'none';
    });
});
</script>

<style>
.form-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.span-2 {
    grid-column: span 2;
}

.span-4 {
    grid-column: span 4;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 5px;
    font-weight: 600;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.form-group textarea {
    resize: vertical;
}

.form-divider {
    margin-top: 20px;
    margin-bottom: 10px;
    border-top: 1px solid #eee;
    padding-top: 15px;
}

.form-divider h4 {
    margin: 0;
    color: #555;
}

#update_fields {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-start;
    margin-top: 20px;
}

/* Checkbox styling */
.checkbox-container {
    display: block;
    position: relative;
    padding-left: 35px;
    margin-bottom: 12px;
    cursor: pointer;
    font-size: 16px;
    user-select: none;
}

.checkbox-container input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

.checkmark {
    position: absolute;
    top: 0;
    left: 0;
    height: 20px;
    width: 20px;
    background-color: #eee;
    border-radius: 4px;
}

.checkbox-container:hover input ~ .checkmark {
    background-color: #ccc;
}

.checkbox-container input:checked ~ .checkmark {
    background-color: #4CAF50;
}

.checkmark:after {
    content: "";
    position: absolute;
    display: none;
}

.checkbox-container input:checked ~ .checkmark:after {
    display: block;
}

.checkbox-container .checkmark:after {
    left: 7px;
    top: 3px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
</style>

<?php include 'includes/footer.php'; ?>