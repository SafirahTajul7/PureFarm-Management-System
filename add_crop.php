<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Fetch all fields for dropdown
try {
    $stmt = $pdo->query("SELECT id, field_name, location FROM fields ORDER BY field_name");
    $fields = $stmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO crops (
                crop_name, variety, field_id, planting_date, expected_harvest_date, 
                growth_stage, status, next_action, next_action_date, notes
            ) VALUES (
                :crop_name, :variety, :field_id, :planting_date, :expected_harvest_date, 
                :growth_stage, :status, :next_action, :next_action_date, :notes
            )
        ");
        
        $stmt->execute([
            'crop_name' => $_POST['crop_name'],
            'variety' => $_POST['variety'],
            'field_id' => $_POST['field_id'],
            'planting_date' => $_POST['planting_date'],
            'expected_harvest_date' => $_POST['expected_harvest_date'],
            'growth_stage' => $_POST['growth_stage'],
            'status' => $_POST['status'],
            'next_action' => $_POST['next_action'],
            'next_action_date' => $_POST['next_action_date'],
            'notes' => $_POST['notes']
        ]);
        
        // Get the ID of the newly inserted crop
        $crop_id = $pdo->lastInsertId();
        
        // Record the planting activity
        $activityStmt = $pdo->prepare("
            INSERT INTO crop_activities (
                crop_id, activity_type, activity_date, description, performed_by
            ) VALUES (
                :crop_id, 'planting', :activity_date, :description, :performed_by
            )
        ");
        
        $activityStmt->execute([
            'crop_id' => $crop_id,
            'activity_date' => $_POST['planting_date'],
            'description' => 'Initial planting of ' . $_POST['crop_name'] . ' (' . $_POST['variety'] . ')',
            'performed_by' => $_SESSION['user_id'] ?? null
        ]);
        
        $_SESSION['success_message'] = 'Crop added successfully!';
        header('Location: crop_list.php');
        exit;
        
    } catch(PDOException $e) {
        error_log("Error adding crop: " . $e->getMessage());
        $_SESSION['error_message'] = 'Error adding crop: ' . $e->getMessage();
    }
}

$pageTitle = 'Add New Crop';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-seedling"></i> Add New Crop</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='crop_list.php'">
                <i class="fas fa-arrow-left"></i> Back to Crop List
            </button>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-header">
            <h3>Crop Details</h3>
        </div>
        <div class="content-card-body">
            <form method="POST" action="" class="form-grid">
                <div class="form-group span-2">
                    <label for="crop_name">Crop Name*</label>
                    <input type="text" id="crop_name" name="crop_name" required placeholder="e.g., Corn, Wheat, Tomatoes">
                </div>
                
                <div class="form-group span-2">
                    <label for="variety">Variety</label>
                    <input type="text" id="variety" name="variety" placeholder="e.g., Sweet Corn, Cherry Tomatoes">
                </div>
                
                <div class="form-group span-2">
                    <label for="field_id">Field Location*</label>
                    <select id="field_id" name="field_id" required>
                        <option value="">Select a field</option>
                        <?php foreach ($fields as $field): ?>
                            <option value="<?php echo $field['id']; ?>">
                                <?php echo htmlspecialchars($field['field_name']) . ' (' . htmlspecialchars($field['location']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="planting_date">Planting Date*</label>
                    <input type="date" id="planting_date" name="planting_date" required>
                </div>
                
                <div class="form-group">
                    <label for="expected_harvest_date">Expected Harvest Date</label>
                    <input type="date" id="expected_harvest_date" name="expected_harvest_date">
                </div>
                
                <div class="form-group">
                    <label for="growth_stage">Growth Stage*</label>
                    <select id="growth_stage" name="growth_stage" required>
                        <option value="seedling">Seedling</option>
                        <option value="vegetative">Vegetative</option>
                        <option value="flowering">Flowering</option>
                        <option value="fruiting">Fruiting</option>
                        <option value="mature">Mature</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">Status*</label>
                    <select id="status" name="status" required>
                        <option value="active">Active</option>
                        <option value="harvested">Harvested</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                
                <div class="form-group span-2">
                    <label for="next_action">Next Action</label>
                    <input type="text" id="next_action" name="next_action" placeholder="e.g., Fertilize, Irrigate, Apply Pesticide">
                </div>
                
                <div class="form-group span-2">
                    <label for="next_action_date">Next Action Date</label>
                    <input type="date" id="next_action_date" name="next_action_date">
                </div>
                
                <div class="form-group span-4">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="4" placeholder="Enter any additional notes about this crop"></textarea>
                </div>
                
                <div class="form-actions span-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Crop
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
document.addEventListener('DOMContentLoaded', function() {
    // Set default planting date to today
    document.getElementById('planting_date').valueAsDate = new Date();
    
    // Automatically calculate expected harvest date based on crop selection
    const cropNameInput = document.getElementById('crop_name');
    const expectedHarvestInput = document.getElementById('expected_harvest_date');
    
    cropNameInput.addEventListener('change', function() {
        const cropName = this.value.toLowerCase();
        const plantingDate = new Date(document.getElementById('planting_date').value);
        
        if (!plantingDate) return;
        
        let daysToHarvest = 0;
        
        // Rough estimates for common crops
        if (cropName.includes('corn')) {
            daysToHarvest = 80; // ~80 days for corn
        } else if (cropName.includes('tomato')) {
            daysToHarvest = 70; // ~70 days for tomatoes
        } else if (cropName.includes('wheat')) {
            daysToHarvest = 120; // ~120 days for wheat
        } else if (cropName.includes('potato')) {
            daysToHarvest = 90; // ~90 days for potatoes
        } else if (cropName.includes('lettuce')) {
            daysToHarvest = 45; // ~45 days for lettuce
        } else {
            daysToHarvest = 90; // Default: 90 days
        }
        
        if (daysToHarvest > 0) {
            const harvestDate = new Date(plantingDate);
            harvestDate.setDate(harvestDate.getDate() + daysToHarvest);
            expectedHarvestInput.valueAsDate = harvestDate;
        }
    });
    
    // Update harvest date when planting date changes
    document.getElementById('planting_date').addEventListener('change', function() {
        // Trigger the crop name change event to recalculate
        const event = new Event('change');
        cropNameInput.dispatchEvent(event);
    });
});
</script>

<?php include 'includes/footer.php'; ?>