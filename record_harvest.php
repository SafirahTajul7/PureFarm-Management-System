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
        SELECT c.*, f.field_name, f.location, f.area AS field_area
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
    
    // Check if crop is already harvested and get harvest data if available
    $harvest_exists = false;
    $harvest_data = null;
    
    if ($crop['status'] === 'harvested') {
        $harvest_stmt = $pdo->prepare("SELECT * FROM harvests WHERE crop_id = :crop_id");
        $harvest_stmt->execute(['crop_id' => $crop_id]);
        $harvest_data = $harvest_stmt->fetch();
        
        if ($harvest_data) {
            $harvest_exists = true;
        }
    } else if ($crop['status'] !== 'active') {
        $_SESSION['error_message'] = 'Only active crops can be newly harvested.';
        header('Location: crop_details.php?id=' . $crop_id);
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
        // Begin transaction
        $pdo->beginTransaction();
        
        // Calculate yield amount in consistent units (for performance analysis)
        $yield_amount = floatval($_POST['yield_amount']);
        $yield_unit = $_POST['yield_unit'];
        
        // Convert yield to a quality rating (1-5 scale)
        // This is an example - you might want to adjust this logic based on your needs
        $quality_rating = isset($_POST['quality_rating']) ? floatval($_POST['quality_rating']) : 3.0;
        
        // First check if harvests table exists, create it if not
        $check_table = $pdo->query("SHOW TABLES LIKE 'harvests'");
        if ($check_table->rowCount() == 0) {
            // Table doesn't exist, create it
            $create_table_sql = "
                CREATE TABLE IF NOT EXISTS `harvests` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `crop_id` int(11) NOT NULL,
                    `actual_harvest_date` date NOT NULL,
                    `yield_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `quality_rating` decimal(3,1) NOT NULL DEFAULT 0.0,
                    `notes` text,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `crop_id` (`crop_id`),
                    CONSTRAINT `harvests_ibfk_1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            
            $pdo->exec($create_table_sql);
        }
        
        // Insert or update harvest data for performance analysis
        if ($harvest_exists) {
            // Update existing harvest record
            $harvest_stmt = $pdo->prepare("
                UPDATE harvests SET
                    actual_harvest_date = :harvest_date,
                    yield_amount = :yield_amount,
                    quality_rating = :quality_rating,
                    notes = :notes
                WHERE crop_id = :crop_id
            ");
        } else {
            // Insert new harvest record
            $harvest_stmt = $pdo->prepare("
                INSERT INTO harvests (
                    crop_id, actual_harvest_date, yield_amount, quality_rating, notes
                ) VALUES (
                    :crop_id, :harvest_date, :yield_amount, :quality_rating, :notes
                )
            ");
        }
        
        $harvest_stmt->execute([
            'crop_id' => $crop_id,
            'harvest_date' => $_POST['harvest_date'],
            'yield_amount' => $yield_amount,
            'quality_rating' => $quality_rating,
            'notes' => $_POST['notes'] ?: null
        ]);
        
        // Record harvest activity in crop_activities table
        $activityStmt = $pdo->prepare("
            INSERT INTO crop_activities (
                crop_id, activity_type, activity_date, description, quantity, unit, performed_by, notes
            ) VALUES (
                :crop_id, 'harvest', :activity_date, :description, :quantity, :unit, :performed_by, :notes
            )
        ");
        
        $activityStmt->execute([
            'crop_id' => $crop_id,
            'activity_date' => $_POST['harvest_date'],
            'description' => 'Harvested ' . $crop['crop_name'] . ' (' . $crop['variety'] . ')',
            'quantity' => $_POST['yield_amount'],
            'unit' => $_POST['yield_unit'],
            'performed_by' => $_SESSION['user_id'] ?? null,
            'notes' => $_POST['notes'] ?: null
        ]);
        
        // Update crop status if not already harvested
        if ($crop['status'] !== 'harvested') {
            $updateStmt = $pdo->prepare("
                UPDATE crops SET
                    status = 'harvested',
                    actual_harvest_date = :harvest_date,
                    growth_stage = 'mature',
                    next_action = NULL,
                    next_action_date = NULL
                WHERE id = :id
            ");
            
            $updateStmt->execute([
                'harvest_date' => $_POST['harvest_date'],
                'id' => $crop_id
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = $harvest_exists ? 'Harvest data updated successfully!' : 'Harvest recorded successfully!';
        header('Location: crop_details.php?id=' . $crop_id);
        exit;
        
    } catch(PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error recording harvest: " . $e->getMessage());
        $_SESSION['error_message'] = 'Error recording harvest: ' . $e->getMessage();
    }
}

$pageTitle = 'Record Harvest: ' . $crop['crop_name'];
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-tractor"></i> <?php echo $harvest_exists ? 'Edit Harvest Data' : 'Record Harvest' ?>: <?php echo htmlspecialchars($crop['crop_name']); ?></h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='crop_details.php?id=<?php echo $crop_id; ?>'">
                <i class="fas fa-arrow-left"></i> Back to Crop Details
            </button>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-header">
            <h3>Harvest Details</h3>
        </div>
        <div class="content-card-body">
            <?php if (!$harvest_exists): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This will mark the crop as harvested and record the yield information.
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> You are editing an existing harvest record.
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="form-grid">
                <div class="form-group span-2">
                    <label for="harvest_date">Harvest Date*</label>
                    <input type="date" id="harvest_date" name="harvest_date" required 
                           value="<?php echo $harvest_exists ? $harvest_data['actual_harvest_date'] : date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="yield_amount">Yield Amount*</label>
                    <input type="number" id="yield_amount" name="yield_amount" step="0.01" required 
                           placeholder="E.g., 500" value="<?php echo $harvest_exists ? $harvest_data['yield_amount'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="yield_unit">Unit*</label>
                    <select id="yield_unit" name="yield_unit" required>
                        <option value="">Select unit</option>
                        <option value="kg" <?php echo $harvest_exists && isset($harvest_data['unit']) && $harvest_data['unit'] == 'kg' ? 'selected' : ''; ?>>Kilograms (kg)</option>
                        <option value="g" <?php echo $harvest_exists && isset($harvest_data['unit']) && $harvest_data['unit'] == 'g' ? 'selected' : ''; ?>>Grams (g)</option>
                        <option value="tons" <?php echo $harvest_exists && isset($harvest_data['unit']) && $harvest_data['unit'] == 'tons' ? 'selected' : ''; ?>>Tons</option>
                        <option value="lb" <?php echo $harvest_exists && isset($harvest_data['unit']) && $harvest_data['unit'] == 'lb' ? 'selected' : ''; ?>>Pounds (lb)</option>
                        <option value="bushels" <?php echo $harvest_exists && isset($harvest_data['unit']) && $harvest_data['unit'] == 'bushels' ? 'selected' : ''; ?>>Bushels</option>
                        <option value="pieces" <?php echo $harvest_exists && isset($harvest_data['unit']) && $harvest_data['unit'] == 'pieces' ? 'selected' : ''; ?>>Pieces</option>
                    </select>
                </div>
                
                <div class="form-group span-4">
                    <label for="quality_rating">Quality Rating (1-5)*</label>
                    <div class="rating-input">
                        <input type="range" id="quality_rating" name="quality_rating" min="1" max="5" step="0.5" 
                               value="<?php echo $harvest_exists ? $harvest_data['quality_rating'] : '3'; ?>" 
                               oninput="updateRatingValue(this.value)">
                        <span id="rating_value"><?php echo $harvest_exists ? $harvest_data['quality_rating'] : '3'; ?></span>
                        <div class="star-rating">
                            <i class="far fa-star" data-rating="1"></i>
                            <i class="far fa-star" data-rating="2"></i>
                            <i class="far fa-star" data-rating="3"></i>
                            <i class="far fa-star" data-rating="4"></i>
                            <i class="far fa-star" data-rating="5"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-group span-4">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" 
                              placeholder="Any additional information about the harvest (quality, conditions, etc.)"
                    ><?php echo $harvest_exists ? $harvest_data['notes'] : ''; ?></textarea>
                </div>
                
                <?php if (!$harvest_exists): ?>
                <div class="form-group span-4">
                    <label class="checkbox-container">
                        <input type="checkbox" id="confirm_harvest" required>
                        <span class="checkmark"></span>
                        I confirm that this crop has been fully harvested
                    </label>
                </div>
                <?php endif; ?>
                
                <div class="form-actions span-4">
                    <button type="submit" class="btn btn-success" id="submit_btn" <?php echo (!$harvest_exists) ? 'disabled' : ''; ?>>
                        <i class="fas fa-tractor"></i> <?php echo $harvest_exists ? 'Update Harvest Data' : 'Record Harvest'; ?>
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="location.href='crop_details.php?id=<?php echo $crop_id; ?>'">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!$harvest_exists): ?>
    // Enable/disable submit button based on confirmation checkbox
    document.getElementById('confirm_harvest').addEventListener('change', function() {
        document.getElementById('submit_btn').disabled = !this.checked;
    });
    <?php endif; ?>
    
    // Initialize star rating display
    updateStars(document.getElementById('quality_rating').value);
});

function updateRatingValue(value) {
    document.getElementById('rating_value').textContent = value;
    updateStars(value);
}

function updateStars(value) {
    const stars = document.querySelectorAll('.star-rating i');
    const rating = parseFloat(value);
    
    stars.forEach((star, index) => {
        const starValue = parseInt(star.getAttribute('data-rating'));
        
        if (starValue <= rating) {
            // Full star
            star.className = 'fas fa-star';
        } else if (starValue - 0.5 <= rating) {
            // Half star
            star.className = 'fas fa-star-half-alt';
        } else {
            // Empty star
            star.className = 'far fa-star';
        }
    });
}

// Add click events to stars
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.star-rating i');
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            document.getElementById('quality_rating').value = rating;
            document.getElementById('rating_value').textContent = rating;
            updateStars(rating);
        });
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

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-start;
    margin-top: 20px;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-info {
    color: #31708f;
    background-color: #d9edf7;
    border-color: #bce8f1;
}

.alert-warning {
    color: #8a6d3b;
    background-color: #fcf8e3;
    border-color: #faebcc;
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

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Quality rating styling */
.rating-input {
    display: flex;
    align-items: center;
    gap: 10px;
}

#rating_value {
    width: 30px;
    text-align: center;
    font-weight: bold;
}

.star-rating {
    display: flex;
    gap: 5px;
    color: #f39c12;
    cursor: pointer;
}

.star-rating i {
    font-size: 20px;
}
</style>

<?php include 'includes/footer.php'; ?>