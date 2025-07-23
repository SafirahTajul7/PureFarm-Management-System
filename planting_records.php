<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Fetch all active crops with their planting details
try {
    $stmt = $pdo->prepare("
        SELECT c.*, f.field_name, f.location
        FROM crops c
        LEFT JOIN fields f ON c.field_id = f.id
        ORDER BY c.planting_date DESC
    ");
    $stmt->execute();
    $crops = $stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log("Error fetching crop data: " . $e->getMessage());
    $crops = [];
}

// Process form submission for updating growth stage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stage') {
    try {
        $stmt = $pdo->prepare("
            UPDATE crops SET
                growth_stage = :growth_stage,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            'growth_stage' => $_POST['growth_stage'],
            'id' => $_POST['crop_id']
        ]);
        
        // Record the growth milestone
        $milestoneStmt = $pdo->prepare("
            INSERT INTO purefarm_growth_milestones (
                crop_id, stage, date_reached, notes
            ) VALUES (
                :crop_id, :stage, :date_reached, :notes
            )
        ");
        
        $milestoneStmt->execute([
            'crop_id' => $_POST['crop_id'],
            'stage' => $_POST['growth_stage'],
            'date_reached' => date('Y-m-d'),
            'notes' => $_POST['notes'] ?: null
        ]);
        
        // Record activity
        $activityStmt = $pdo->prepare("
            INSERT INTO crop_activities (
                crop_id, activity_type, activity_date, description, performed_by
            ) VALUES (
                :crop_id, 'other', :activity_date, :description, :performed_by
            )
        ");
        
        $activityStmt->execute([
            'crop_id' => $_POST['crop_id'],
            'activity_date' => date('Y-m-d'),
            'description' => 'Growth stage updated to ' . $_POST['growth_stage'],
            'performed_by' => $_SESSION['user_id'] ?? null
        ]);
        
        $_SESSION['success_message'] = 'Growth stage updated successfully!';
        header('Location: planting_records.php');
        exit;
        
    } catch(PDOException $e) {
        error_log("Error updating growth stage: " . $e->getMessage());
        $_SESSION['error_message'] = 'Error updating growth stage: ' . $e->getMessage();
    }
}

// Fetch growth milestones for each crop
try {
    $milestones = [];
    
    foreach ($crops as $crop) {
        $milestoneStmt = $pdo->prepare("
            SELECT * FROM purefarm_growth_milestones 
            WHERE crop_id = :crop_id 
            ORDER BY date_reached DESC
        ");
        $milestoneStmt->execute(['crop_id' => $crop['id']]);
        $milestones[$crop['id']] = $milestoneStmt->fetchAll();
    }
    
} catch(PDOException $e) {
    error_log("Error fetching growth milestones: " . $e->getMessage());
    $milestones = [];
}

$pageTitle = 'Planting Records';
include 'includes/header.php';
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <h2><i class="fas fa-seedling"></i> Planting Records</h2>
        <div class="action-buttons">
            <a href="add_crop.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Crop
            </a>
            <a href="crop_management.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Crop Management
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); endif; ?>

    <!-- Planting Records Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0"><i class="fas fa-list me-2"></i>Crop Planting Records</h3>
            <div class="d-flex gap-3">
                <div class="search-container">
                    <input type="text" id="cropSearch" class="form-control" onkeyup="searchCrops()" placeholder="Search crops...">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <div class="filter-container">
                    <select id="stageFilter" class="form-select" onchange="filterByStage()">
                        <option value="all">All Growth Stages</option>
                        <option value="seedling">Seedling</option>
                        <option value="vegetative">Vegetative</option>
                        <option value="flowering">Flowering</option>
                        <option value="fruiting">Fruiting</option>
                        <option value="mature">Mature</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover" id="plantingTable">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Crop Name</th>
                            <th>Variety</th>
                            <th>Field Location</th>
                            <th>Planting Date</th>
                            <th>Expected Harvest</th>
                            <th>Current Growth Stage</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($crops)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-seedling fa-3x text-muted mb-3"></i>
                                        <p>No crops found. <a href="add_crop.php">Add a crop</a> to get started.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($crops as $crop): ?>
                                <tr data-stage="<?php echo htmlspecialchars($crop['growth_stage']); ?>">
                                    <td><?php echo htmlspecialchars($crop['id']); ?></td>
                                    <td><?php echo htmlspecialchars($crop['crop_name']); ?></td>
                                    <td><?php echo htmlspecialchars($crop['variety']); ?></td>
                                    <td><?php echo htmlspecialchars($crop['field_name'] . ' (' . $crop['location'] . ')'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($crop['planting_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($crop['expected_harvest_date'])); ?></td>
                                    <td>
                                        <span class="stage-badge stage-<?php echo htmlspecialchars($crop['growth_stage']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($crop['growth_stage'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars(strtolower($crop['status'])); ?>">
                                            <?php echo htmlspecialchars(ucfirst($crop['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                onclick="viewGrowthMilestones(<?php echo $crop['id']; ?>, '<?php echo htmlspecialchars($crop['crop_name']); ?>')"
                                                title="View Growth Milestones">
                                                <i class="fas fa-history"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                onclick="updateGrowthStage(<?php echo $crop['id']; ?>, '<?php echo htmlspecialchars($crop['crop_name']); ?>', '<?php echo htmlspecialchars($crop['growth_stage']); ?>')"
                                                title="Update Growth Stage">
                                                <i class="fas fa-leaf"></i>
                                            </button>
                                            <a href="crop_details.php?id=<?php echo $crop['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Crop Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Growth Stages Information Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Growth Stages Information</h3>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <!-- Seedling Stage -->
                <div class="col-md-6 col-lg-4">
                    <div class="growth-stage-card">
                        <div class="stage-icon seedling">
                            <i class="fas fa-seedling"></i>
                        </div>
                        <div class="stage-details">
                            <h4>Seedling</h4>
                            <p>Initial growth stage after germination. Plants have their first true leaves and are establishing roots.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Vegetative Stage -->
                <div class="col-md-6 col-lg-4">
                    <div class="growth-stage-card">
                        <div class="stage-icon vegetative">
                            <i class="fas fa-leaf"></i>
                        </div>
                        <div class="stage-details">
                            <h4>Vegetative</h4>
                            <p>Plant is focusing on leaf and stem growth. Building structure and energy reserves for later stages.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Flowering Stage -->
                <div class="col-md-6 col-lg-4">
                    <div class="growth-stage-card">
                        <div class="stage-icon flowering">
                            <i class="fas fa-spa"></i>
                        </div>
                        <div class="stage-details">
                            <h4>Flowering</h4>
                            <p>Plant begins producing flowers. Critical stage for pollination and the start of fruit/seed development.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Fruiting Stage -->
                <div class="col-md-6 col-lg-4">
                    <div class="growth-stage-card">
                        <div class="stage-icon fruiting">
                            <i class="fas fa-apple-alt"></i>
                        </div>
                        <div class="stage-details">
                            <h4>Fruiting</h4>
                            <p>Plant is developing fruits or seeds. Requires adequate nutrients and water for proper development.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Mature Stage -->
                <div class="col-md-6 col-lg-4">
                    <div class="growth-stage-card">
                        <div class="stage-icon mature">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stage-details">
                            <h4>Mature</h4>
                            <p>Fruits or seeds are fully developed and ready for harvest. Plant may begin to dry or show signs of senescence.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Growth Milestones Modal -->
<div class="modal fade" id="growthMilestonesModal" tabindex="-1" aria-labelledby="milestoneModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="milestoneModalLabel">Growth Milestones: <span id="milestone_crop_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="milestone-timeline" id="milestone_timeline">
                    <!-- Milestones will be populated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Growth Stage Modal -->
<div class="modal fade" id="updateGrowthStageModal" tabindex="-1" aria-labelledby="updateGrowthStageLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateGrowthStageLabel">Update Growth Stage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="updateGrowthStageForm">
                    <input type="hidden" name="action" value="update_stage">
                    <input type="hidden" name="crop_id" id="update_crop_id">
                    
                    <div class="mb-3">
                        <label for="update_crop_name" class="form-label">Crop:</label>
                        <input type="text" id="update_crop_name" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="current_stage" class="form-label">Current Stage:</label>
                        <input type="text" id="current_stage" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="growth_stage" class="form-label">New Growth Stage*</label>
                        <select id="growth_stage" name="growth_stage" class="form-select" required>
                            <option value="">Select growth stage</option>
                            <option value="seedling">Seedling</option>
                            <option value="vegetative">Vegetative</option>
                            <option value="flowering">Flowering</option>
                            <option value="fruiting">Fruiting</option>
                            <option value="mature">Mature</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea id="notes" name="notes" rows="3" class="form-control" placeholder="Enter any notes about this growth stage change"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="updateGrowthStageForm" class="btn btn-primary">Update Stage</button>
            </div>
        </div>
    </div>
</div>

<script>
// Search Function
function searchCrops() {
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("cropSearch");
    filter = input.value.toUpperCase();
    table = document.getElementById("plantingTable");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) {
        // Skip header row
        if (tr[i].getElementsByTagName("td").length > 0) {
            var found = false;
            // Search in columns 1-4 (Crop Name, Variety, Field Location)
            for (var j = 1; j < 4; j++) {
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
}

// Filter by Growth Stage Function
function filterByStage() {
    var select, filter, table, tr, i;
    select = document.getElementById("stageFilter");
    filter = select.value;
    table = document.getElementById("plantingTable");
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) {
        // Skip header row
        if (tr[i].getElementsByTagName("td").length > 0) {
            if (filter === "all") {
                tr[i].style.display = "";
            } else {
                var stage = tr[i].getAttribute("data-stage");
                if (stage === filter) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }
}

// Growth Milestones Modal Functions
function viewGrowthMilestones(cropId, cropName) {
    document.getElementById('milestone_crop_name').textContent = cropName;
    
    const timelineContainer = document.getElementById('milestone_timeline');
    timelineContainer.innerHTML = '';
    
    // Get milestones data from PHP
    const milestonesData = <?php echo json_encode($milestones); ?>;
    const cropMilestones = milestonesData[cropId] || [];
    
    if (cropMilestones.length > 0) {
        cropMilestones.forEach(milestone => {
            const milestoneItem = document.createElement('div');
            milestoneItem.className = 'milestone-item';
            
            const milestoneIcon = document.createElement('div');
            milestoneIcon.className = 'milestone-icon stage-' + milestone.stage;
            
            let iconClass;
            switch(milestone.stage) {
                case 'seedling': iconClass = 'fas fa-seedling'; break;
                case 'vegetative': iconClass = 'fas fa-leaf'; break;
                case 'flowering': iconClass = 'fas fa-spa'; break;
                case 'fruiting': iconClass = 'fas fa-apple-alt'; break;
                case 'mature': iconClass = 'fas fa-check-circle'; break;
                default: iconClass = 'fas fa-circle';
            }
            
            milestoneIcon.innerHTML = `<i class="${iconClass}"></i>`;
            milestoneItem.appendChild(milestoneIcon);
            
            const milestoneContent = document.createElement('div');
            milestoneContent.className = 'milestone-content';
            
            const milestoneTitle = document.createElement('h5');
            milestoneTitle.textContent = ucfirst(milestone.stage) + ' Stage Reached';
            milestoneContent.appendChild(milestoneTitle);
            
            const milestoneDate = document.createElement('p');
            milestoneDate.className = 'milestone-date';
            milestoneDate.innerHTML = `<i class="fas fa-calendar-alt me-2"></i>${new Date(milestone.date_reached).toLocaleDateString()}`;
            milestoneContent.appendChild(milestoneDate);
            
            if (milestone.notes) {
                const milestoneNotes = document.createElement('p');
                milestoneNotes.className = 'milestone-notes';
                milestoneNotes.innerHTML = `<i class="fas fa-sticky-note me-2"></i>${milestone.notes}`;
                milestoneContent.appendChild(milestoneNotes);
            }
            
            milestoneItem.appendChild(milestoneContent);
            timelineContainer.appendChild(milestoneItem);
        });
    } else {
        const emptyMessage = document.createElement('div');
        emptyMessage.className = 'empty-state text-center py-4';
        emptyMessage.innerHTML = `
            <i class="fas fa-history fa-3x text-muted mb-3"></i>
            <p class="mb-0">No growth milestones recorded yet.</p>
        `;
        timelineContainer.appendChild(emptyMessage);
    }
    
    var myModal = new bootstrap.Modal(document.getElementById('growthMilestonesModal'));
    myModal.show();
}

// Update Growth Stage Modal Functions
function updateGrowthStage(cropId, cropName, currentStage) {
    document.getElementById('update_crop_id').value = cropId;
    document.getElementById('update_crop_name').value = cropName;
    document.getElementById('current_stage').value = ucfirst(currentStage);
    
    // Set dropdown default based on natural progression
    const growthStageSelect = document.getElementById('growth_stage');
    
    // Clear previous selection
    growthStageSelect.value = '';
    
    // Suggest next stage based on current stage
    switch(currentStage) {
        case 'seedling':
            growthStageSelect.value = 'vegetative';
            break;
        case 'vegetative':
            growthStageSelect.value = 'flowering';
            break;
        case 'flowering':
            growthStageSelect.value = 'fruiting';
            break;
        case 'fruiting':
            growthStageSelect.value = 'mature';
            break;
        case 'mature':
            // Already at final stage, leave default empty
            break;
    }
    
    var myModal = new bootstrap.Modal(document.getElementById('updateGrowthStageModal'));
    myModal.show();
}

// Helper function to capitalize first letter
function ucfirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

// Initialize Bootstrap components
document.addEventListener('DOMContentLoaded', function() {
    // Auto-close alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const alertInstance = new bootstrap.Alert(alert);
            alertInstance.close();
        }, 5000);
    });
});
</script>

<style>
/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.page-header h2 {
    margin: 0;
    font-weight: 600;
    color: #2c3e50;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

/* Card styling */
.card {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: none;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding: 1rem 1.25rem;
}

.card-title {
    font-weight: 600;
    color: #2c3e50;
}

/* Search and filter controls */
.search-container {
    position: relative;
    width: 250px;
}

.search-icon {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    color: #6c757d;
}

.filter-container select {
    min-width: 180px;
}

/* Table styling */
.table {
    margin-bottom: 0;
}

.table thead th {
    font-weight: 600;
    background-color: #f8f9fa;
}

.empty-state {
    padding: 2rem;
    text-align: center;
    color: #6c757d;
}

.empty-state i {
    margin-bottom: 1rem;
}

/* Action buttons in table */
td.text-center .action-buttons {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}

/* Stage and Status badges */
.stage-badge, .status-badge {
    display: inline-block;
    padding: 0.25em 0.6em;
    font-size: 0.85em;
    font-weight: 600;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
}

/* Stage badge colors */
.stage-badge {
    color: white;
}

.stage-badge.stage-seedling {
    background-color: #8bc34a;
}

.stage-badge.stage-vegetative {
    background-color: #4caf50;
}

.stage-badge.stage-flowering {
    background-color: #9c27b0;
}

.stage-badge.stage-fruiting {
    background-color: #ff9800;
}

.stage-badge.stage-mature {
    background-color: #3f51b5;
}

/* Status badge colors */
.status-badge.status-active {
    background-color: #e8f5e9;
    color: #2e7d32;
}

.status-badge.status-harvested {
    background-color: #e3f2fd;
    color: #1565c0;
}

.status-badge.status-failed {
    background-color: #ffebee;
    color: #c62828;
}

/* Growth stage cards */
.growth-stage-card {
    display: flex;
    gap: 1rem;
    padding: 1.25rem;
    background-color: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 1rem;
    height: 100%;
    transition: transform 0.2s, box-shadow 0.2s;
}

.growth-stage-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
}

.stage-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    flex-shrink: 0;
}

.stage-icon i {
    font-size: 1.25rem;
    color: white;
}

.stage-details h4 {
    margin-top: 0;
    margin-bottom: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
}

.stage-details p {
    margin: 0;
    font-size: 0.9rem;
    color: #6c757d;
}

/* Growth Stage Icons */
.seedling {
    background-color: #8bc34a;
}

.vegetative {
    background-color: #4caf50;
}

.flowering {
    background-color: #9c27b0;
}

.fruiting {
    background-color: #ff9800;
}

.mature {
    background-color: #3f51b5;
}

/* Milestone Timeline */
.milestone-timeline {
    position: relative;
    padding: 1.5rem 0 0.5rem 2rem;
    margin-left: 1rem;
}

.milestone-timeline:before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 2px;
    background-color: #e9ecef;
}

.milestone-item {
    position: relative;
    margin-bottom: 1.5rem;
}

.milestone-icon {
    position: absolute;
    left: -2.5rem;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    z-index: 1;
}

.milestone-content {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.milestone-content h5 {
    margin-top: 0;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.milestone-date, .milestone-notes {
    color: #6c757d;
    margin-bottom: 0.5rem;
}

.milestone-notes:last-child {
    margin-bottom: 0;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .card-header .d-flex {
        flex-direction: column;
        width: 100%;
        gap: 0.75rem;
        margin-top: 1rem;
    }
    .search-container,
    .filter-container {
        width: 100%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>