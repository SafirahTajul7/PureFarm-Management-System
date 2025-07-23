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

// Fetch all fields for dropdown
try {
    $fieldStmt = $pdo->query("SELECT id, field_name, location FROM fields ORDER BY field_name");
    $fields = $fieldStmt->fetchAll();
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            UPDATE crops SET
                crop_name = :crop_name,
                variety = :variety,
                field_id = :field_id,
                planting_date = :planting_date,
                expected_harvest_date = :expected_harvest_date,
                growth_stage = :growth_stage,
                status = :status,
                next_action = :next_action,
                next_action_date = :next_action_date,
                notes = :notes
            WHERE id = :id
        ");
        
        $stmt->execute([
            'crop_name' => $_POST['crop_name'],
            'variety' => $_POST['variety'],
            'field_id' => $_POST['field_id'],
            'planting_date' => $_POST['planting_date'],
            'expected_harvest_date' => $_POST['expected_harvest_date'] ?: null,
            'growth_stage' => $_POST['growth_stage'],
            'status' => $_POST['status'],
            'next_action' => $_POST['next_action'] ?: null,
            'next_action_date' => $_POST['next_action_date'] ?: null,
            'notes' => $_POST['notes'] ?: null,
            'id' => $crop_id
        ]);
        
        $_SESSION['success_message'] = 'Crop updated successfully!';
        header('Location: crop_details.php?id=' . $crop_id);
        exit;
        
    } catch(PDOException $e) {
        error_log("Error updating crop: " . $e->getMessage());
        $_SESSION['error_message'] = 'Error updating crop: ' . $e->getMessage();
    }
}

// Fetch crop details
try {
    $stmt = $pdo->prepare("SELECT * FROM crops WHERE id = :id");
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

$pageTitle = 'Edit Crop: ' . $crop['crop_name'];
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-edit"></i> Edit Crop: <?php echo htmlspecialchars($crop['crop_name']); ?></h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='crop_details.php?id=<?php echo $crop_id; ?>'">
                <i class="fas fa-arrow-left"></i> Back to Crop Details
            </button>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-header">
            <h3>Edit Crop Details</h3>
        </div>
        <div class="content-card-body">
            <form method="POST" action="" class="form-grid">
                <div class="form-group span-2">
                    <label for="crop_name">Crop Name*</label>
                    <input type="text" id="crop_name" name="crop_name" required value="<?php echo htmlspecialchars($crop['crop_name']); ?>">
                </div>
                
                <div class="form-group span-2">
                    <label for="variety">Variety</label>
                    <input type="text" id="variety" name="variety" value="<?php echo htmlspecialchars($crop['variety']); ?>">
                </div>
                
                <div class="form-group span-2">
                    <label for="field_id">Field Location*</label>
                    <select id="field_id" name="field_id" required>
                        <option value="">Select a field</option>
                        <?php foreach ($fields as $field): ?>
                            <option value="<?php echo $field['id']; ?>" <?php if ($field['id'] == $crop['field_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($field['field_name']) . ' (' . htmlspecialchars($field['location']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="planting_date">Planting Date*</label>
                    <input type="date" id="planting_date" name="planting_date" required value="<?php echo $crop['planting_date']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="expected_harvest_date">Expected Harvest Date</label>
                    <input type="date" id="expected_harvest_date" name="expected_harvest_date" value="<?php echo $crop['expected_harvest_date']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="growth_stage">Growth Stage*</label>
                    <select id="growth_stage" name="growth_stage" required>
                        <option value="seedling" <?php if ($crop['growth_stage'] == 'seedling') echo 'selected'; ?>>Seedling</option>
                        <option value="vegetative" <?php if ($crop['growth_stage'] == 'vegetative') echo 'selected'; ?>>Vegetative</option>
                        <option value="flowering" <?php if ($crop['growth_stage'] == 'flowering') echo 'selected'; ?>>Flowering</option>
                        <option value="fruiting" <?php if ($crop['growth_stage'] == 'fruiting') echo 'selected'; ?>>Fruiting</option>
                        <option value="mature" <?php if ($crop['growth_stage'] == 'mature') echo 'selected'; ?>>Mature</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">Status*</label>
                    <select id="status" name="status" required>
                        <option value="active" <?php if ($crop['status'] == 'active') echo 'selected'; ?>>Active</option>
                        <option value="harvested" <?php if ($crop['status'] == 'harvested') echo 'selected'; ?>>Harvested</option>
                        <option value="failed" <?php if ($crop['status'] == 'failed') echo 'selected'; ?>>Failed</option>
                    </select>
                </div>
                
                <div class="form-group span-2">
                    <label for="next_action">Next Action</label>
                    <input type="text" id="next_action" name="next_action" value="<?php echo htmlspecialchars($crop['next_action']); ?>">
                </div>
                
                <div class="form-group span-2">
                    <label for="next_action_date">Next Action Date</label>
                    <input type="date" id="next_action_date" name="next_action_date" value="<?php echo $crop['next_action_date']; ?>">
                </div>
                
                <div class="form-group span-4">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="4"><?php echo htmlspecialchars($crop['notes']); ?></textarea>
                </div>
                
                <div class="form-actions span-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Crop
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="location.href='crop_details.php?id=<?php echo $crop_id; ?>'">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.form-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.span-2 {
    grid-column: span 2;
}

.form-group.span-4 {
    grid-column: span 4;
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

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-start;
    margin-top: 10px;
}
</style>

<?php include 'includes/footer.php'; ?>