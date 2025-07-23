<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Get crop ID and type from URL
$crop_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : '';

if (!$crop_id || $type !== 'crop') {
    $_SESSION['error_message'] = 'Invalid request.';
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
            INSERT INTO crop_issues (
                crop_id, issue_type, description, date_identified, severity, treatment_applied, notes
            ) VALUES (
                :crop_id, :issue_type, :description, :date_identified, :severity, :treatment_applied, :notes
            )
        ");
        
        $stmt->execute([
            'crop_id' => $crop_id,
            'issue_type' => $_POST['issue_type'],
            'description' => $_POST['description'],
            'date_identified' => $_POST['date_identified'],
            'severity' => $_POST['severity'],
            'treatment_applied' => $_POST['treatment_applied'] ?: null,
            'notes' => $_POST['notes'] ?: null
        ]);
        
        $_SESSION['success_message'] = 'Issue reported successfully!';
        header('Location: crop_details.php?id=' . $crop_id);
        exit;
        
    } catch(PDOException $e) {
        error_log("Error reporting issue: " . $e->getMessage());
        $_SESSION['error_message'] = 'Error reporting issue: ' . $e->getMessage();
    }
}

$pageTitle = 'Report Issue: ' . $crop['crop_name'];
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-exclamation-triangle"></i> Report Issue: <?php echo htmlspecialchars($crop['crop_name']); ?></h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='crop_details.php?id=<?php echo $crop_id; ?>'">
                <i class="fas fa-arrow-left"></i> Back to Crop Details
            </button>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-header">
            <h3>Issue Details</h3>
        </div>
        <div class="content-card-body">
            <form method="POST" action="" class="form-grid">
                <div class="form-group span-2">
                    <label for="issue_type">Issue Type*</label>
                    <select id="issue_type" name="issue_type" required onchange="updateDescription()">
                        <option value="">Select issue type</option>
                        <option value="pest">Pest</option>
                        <option value="disease">Disease</option>
                        <option value="nutrient">Nutrient Deficiency</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group span-2">
                    <label for="date_identified">Date Identified*</label>
                    <input type="date" id="date_identified" name="date_identified" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group span-2">
                    <label for="severity">Severity*</label>
                    <select id="severity" name="severity" required>
                        <option value="">Select severity</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                
                <div class="form-group span-4">
                    <label for="description">Description*</label>
                    <textarea id="description" name="description" rows="3" required placeholder="Describe the issue in detail (symptoms, affected area, etc.)"></textarea>
                </div>
                
                <div class="form-group span-4">
                    <label for="treatment_applied">Treatment Applied (if any)</label>
                    <textarea id="treatment_applied" name="treatment_applied" rows="2" placeholder="Describe any treatments already applied"></textarea>
                </div>
                
                <div class="form-group span-4">
                    <label for="notes">Additional Notes</label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Any other relevant information"></textarea>
                </div>

                <div class="form-actions span-4">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-exclamation-circle"></i> Report Issue
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
    // Set default date to today
    document.getElementById('date_identified').valueAsDate = new Date();
});

function updateDescription() {
    const issueType = document.getElementById('issue_type').value;
    const descriptionField = document.getElementById('description');
    
    if (descriptionField.value) {
        // Don't overwrite existing description if user has entered something
        return;
    }
    
    switch(issueType) {
        case 'pest':
            descriptionField.placeholder = "Describe the pest (e.g., aphids, caterpillars), affected parts of the plant, extent of infestation...";
            break;
        case 'disease':
            descriptionField.placeholder = "Describe the symptoms (e.g., leaf spots, wilting), affected parts, spread pattern...";
            break;
        case 'nutrient':
            descriptionField.placeholder = "Describe the deficiency symptoms (e.g., yellowing leaves, stunted growth), affected parts...";
            break;
        case 'other':
            descriptionField.placeholder = "Describe the issue in detail...";
            break;
        default:
            descriptionField.placeholder = "Describe the issue in detail (symptoms, affected area, etc.)";
    }
}
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
</style>

<?php include 'includes/footer.php'; ?>