<?php
require_once 'includes/auth.php';
auth()->checkAdmin(); // Based on FR requirements, admin should be able to track growth

require_once 'includes/db.php';

// Initialize variables
$successMessage = '';
$errorMessage = '';
$crops = [];

// Process form submission for updating growth stage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_stage') {
        try {
            // Update crop growth stage
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
                'date_reached' => $_POST['date_reached'] ?? date('Y-m-d'),
                'notes' => $_POST['notes'] ?? null
            ]);
            
            $successMessage = 'Growth stage updated successfully!';
            
        } catch(PDOException $e) {
            $errorMessage = 'Error updating growth stage: ' . $e->getMessage();
            error_log("Error updating growth stage: " . $e->getMessage());
        }
    }
}

// Fetch all active crops with their current growth information
try {
    $stmt = $pdo->prepare("
        SELECT c.*, f.field_name, f.location
        FROM crops c
        LEFT JOIN fields f ON c.field_id = f.id
        WHERE c.status = 'active'
        ORDER BY c.crop_name ASC
    ");
    $stmt->execute();
    $crops = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $errorMessage = 'Error fetching crop data: ' . $e->getMessage();
    error_log("Error fetching crop data: " . $e->getMessage());
}

// Fetch growth milestones for each crop
$milestones = [];
try {
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
}

$pageTitle = 'Growth Tracking';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-leaf"></i> Growth Tracking</h2>
        <div class="action-buttons">
            <a href="crop_management.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Crop Management
            </a>
        </div>
    </div>

    <?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $successMessage; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $errorMessage; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Growth Tracking Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0"><i class="fas fa-seedling me-2"></i>Crop Growth Stages</h3>
            <div class="search-container">
                <input type="text" id="cropSearch" class="form-control" onkeyup="searchCrops()" placeholder="Search crops...">
                <i class="fas fa-search search-icon"></i>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover" id="growthTable">
                    <thead class="table-light">
                        <tr>
                            <th>Crop Name</th>
                            <th>Variety</th>
                            <th>Field Location</th>
                            <th>Planting Date</th>
                            <th>Current Growth Stage</th>
                            <th>Last Updated</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($crops)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="empty-state">
                                        <i class="fas fa-seedling fa-3x text-muted mb-3"></i>
                                        <p>No active crops found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($crops as $crop): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($crop['crop_name']); ?></td>
                                    <td><?php echo htmlspecialchars($crop['variety']); ?></td>
                                    <td><?php echo htmlspecialchars($crop['field_name'] . ' (' . $crop['location'] . ')'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($crop['planting_date'])); ?></td>
                                    <td>
                                        <span class="stage-badge stage-<?php echo htmlspecialchars($crop['growth_stage']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($crop['growth_stage'] ?? 'Not set')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($crop['updated_at']) ? date('M d, Y', strtotime($crop['updated_at'])) : 'N/A'; ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-success" 
                                            onclick="updateGrowthStage(<?php echo $crop['id']; ?>, '<?php echo htmlspecialchars($crop['crop_name']); ?>', '<?php echo htmlspecialchars($crop['growth_stage'] ?? ''); ?>')">
                                            Update Stage
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info" 
                                            onclick="viewGrowthHistory(<?php echo $crop['id']; ?>, '<?php echo htmlspecialchars($crop['crop_name']); ?>')">
                                            View History
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Growth Stages Information -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Growth Stages Information</h3>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <!-- Seedling Stage -->
                <div class="col-md-6 col-lg-4">
                    <div class="info-card">
                        <div class="info-icon seedling">
                            <i class="fas fa-seedling"></i>
                        </div>
                        <div class="info-content">
                            <h4>Seedling</h4>
                            <p>Initial growth stage after germination with first true leaves developing.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Vegetative Stage -->
                <div class="col-md-6 col-lg-4">
                    <div class="info-card">
                        <div class="info-icon vegetative">
                            <i class="fas fa-leaf"></i>
                        </div>
                        <div class="info-content">
                            <h4>Vegetative</h4>
                            <p>Focus on leaf and stem growth, building energy reserves.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Flowering Stage -->
                <div class="col-md-6 col-lg-4">
                    <div class="info-card">
                        <div class="info-icon flowering">
                            <i class="fas fa-spa"></i>
                        </div>
                        <div class="info-content">
                            <h4>Flowering</h4>
                            <p>Plant produces flowers, critical for pollination.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Fruiting Stage -->
                <div class="col-md-6 col-lg-4">
                    <div class="info-card">
                        <div class="info-icon fruiting">
                            <i class="fas fa-apple-alt"></i>
                        </div>
                        <div class="info-content">
                            <h4>Fruiting</h4>
                            <p>Development of fruits or seeds.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Mature Stage -->
                <div class="col-md-6 col-lg-4">
                    <div class="info-card">
                        <div class="info-icon mature">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="info-content">
                            <h4>Mature</h4>
                            <p>Fruits or seeds fully developed and ready for harvest.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Growth Stage Modal -->
<div class="modal fade" id="updateGrowthModal" tabindex="-1" aria-labelledby="updateGrowthLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateGrowthLabel">Update Growth Stage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_stage">
                    <input type="hidden" name="crop_id" id="crop_id">
                    
                    <div class="mb-3">
                        <label for="crop_name" class="form-label">Crop:</label>
                        <input type="text" id="crop_name" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="current_stage" class="form-label">Current Stage:</label>
                        <input type="text" id="current_stage" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="growth_stage" class="form-label">New Growth Stage:</label>
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
                        <label for="date_reached" class="form-label">Date Reached:</label>
                        <input type="date" id="date_reached" name="date_reached" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes:</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Enter any observations or notes about this growth stage"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Growth Stage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Growth History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="historyModalLabel">Growth History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 id="history_crop_name" class="mb-3"></h6>
                <div id="milestone_timeline" class="timeline">
                    <!-- Will be populated with JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Search functionality
function searchCrops() {
    var input = document.getElementById("cropSearch");
    var filter = input.value.toUpperCase();
    var table = document.getElementById("growthTable");
    var tr = table.getElementsByTagName("tr");

    for (var i = 1; i < tr.length; i++) {
        var tdName = tr[i].getElementsByTagName("td")[0];
        var tdVariety = tr[i].getElementsByTagName("td")[1];
        var tdField = tr[i].getElementsByTagName("td")[2];
        
        if (tdName || tdVariety || tdField) {
            var nameValue = tdName.textContent || tdName.innerText;
            var varietyValue = tdVariety.textContent || tdVariety.innerText;
            var fieldValue = tdField.textContent || tdField.innerText;
            
            if (
                nameValue.toUpperCase().indexOf(filter) > -1 || 
                varietyValue.toUpperCase().indexOf(filter) > -1 || 
                fieldValue.toUpperCase().indexOf(filter) > -1
            ) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}

// Update Growth Stage modal
function updateGrowthStage(cropId, cropName, currentStage) {
    document.getElementById('crop_id').value = cropId;
    document.getElementById('crop_name').value = cropName;
    document.getElementById('current_stage').value = currentStage ? currentStage.charAt(0).toUpperCase() + currentStage.slice(1) : 'Not set';
    
    // If there's a current stage, suggest the next logical stage
    var growthStageSelect = document.getElementById('growth_stage');
    growthStageSelect.value = '';
    
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
    }
    
    var modal = new bootstrap.Modal(document.getElementById('updateGrowthModal'));
    modal.show();
}

// Improved View Growth History function to handle errors better
function viewGrowthHistory(cropId, cropName) {
    document.getElementById('history_crop_name').textContent = cropName;
    var timelineContainer = document.getElementById('milestone_timeline');
    timelineContainer.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i><p>Loading growth history...</p></div>';
    
    // Show the modal while loading data
    var modal = new bootstrap.Modal(document.getElementById('historyModal'));
    modal.show();
    
    // Fetch the milestone data for this crop using Ajax
    fetch('get_milestones.php?crop_id=' + cropId)
        .then(response => {
            // Check if the response is ok (status in the range 200-299)
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text(); // Get response as text instead of JSON
        })
        .then(data => {
            // Log the raw response for debugging
            console.log('Raw response:', data);
            
            try {
                // Try to parse the response as JSON
                const milestones = JSON.parse(data);
                
                if (Array.isArray(milestones) && milestones.length > 0) {
                    timelineContainer.innerHTML = ''; // Clear loading indicator
                    
                    milestones.forEach(milestone => {
                        var milestoneElement = document.createElement('div');
                        milestoneElement.className = 'timeline-item';
                        
                        var stageIconClass = '';
                        switch(milestone.stage) {
                            case 'seedling': stageIconClass = 'fas fa-seedling'; break;
                            case 'vegetative': stageIconClass = 'fas fa-leaf'; break;
                            case 'flowering': stageIconClass = 'fas fa-spa'; break;
                            case 'fruiting': stageIconClass = 'fas fa-apple-alt'; break;
                            case 'mature': stageIconClass = 'fas fa-check-circle'; break;
                            default: stageIconClass = 'fas fa-circle';
                        }
                        
                        var formattedDate = new Date(milestone.date_reached).toLocaleDateString();
                        
                        milestoneElement.innerHTML = `
                            <div class="timeline-icon ${milestone.stage}">
                                <i class="${stageIconClass}"></i>
                            </div>
                            <div class="timeline-content">
                                <h6>${milestone.stage.charAt(0).toUpperCase() + milestone.stage.slice(1)} Stage</h6>
                                <p class="text-muted"><i class="fas fa-calendar-alt me-2"></i>${formattedDate}</p>
                                ${milestone.notes ? `<p class="mt-2">${milestone.notes}</p>` : ''}
                            </div>
                        `;
                        
                        timelineContainer.appendChild(milestoneElement);
                    });
                } else if (Array.isArray(milestones) && milestones.length === 0) {
                    timelineContainer.innerHTML = '<div class="text-center py-4"><i class="fas fa-info-circle fa-2x text-muted mb-3"></i><p>No growth history recorded for this crop.</p></div>';
                } else if (milestones.error) {
                    // Handle API error
                    timelineContainer.innerHTML = `<div class="text-center py-4"><i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i><p>Error: ${milestones.error}</p></div>`;
                } else {
                    // Unexpected response format
                    timelineContainer.innerHTML = '<div class="text-center py-4"><i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i><p>Unexpected response format. Please try again.</p></div>';
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                timelineContainer.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i>
                        <p>Error parsing response data. Please try again.</p>
                        <div class="mt-3 text-start">
                            <p class="text-muted small">Technical details (for admin):</p>
                            <pre class="small bg-light p-2" style="max-height: 200px; overflow: auto;">${parseError.message}</pre>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            timelineContainer.innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i>
                    <p>Error loading growth history: ${error.message}</p>
                    <button class="btn btn-sm btn-outline-primary mt-3" onclick="viewGrowthHistory(${cropId}, '${cropName}')">
                        <i class="fas fa-sync"></i> Try Again
                    </button>
                </div>
            `;
        });
}

// Initialize Bootstrap tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>

<style>
/* Basic styling for the page */
.main-content {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.search-container {
    position: relative;
    width: 250px;
}

.search-icon {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

/* Stage badges */
.stage-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: white;
}

.stage-seedling {
    background-color: #8bc34a;
}

.stage-vegetative {
    background-color: #4caf50;
}

.stage-flowering {
    background-color: #9c27b0;
}

.stage-fruiting {
    background-color: #ff9800;
}

.stage-mature {
    background-color: #3f51b5;
}

/* Info cards */
.info-card {
    display: flex;
    align-items: center;
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    height: 100%;
}

.info-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.info-icon i {
    color: white;
    font-size: 1.5rem;
}

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

.info-content h4 {
    margin: 0 0 5px 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.info-content p {
    margin: 0;
    font-size: 0.9rem;
    color: #6c757d;
}

/* Timeline for growth history */
.timeline {
    position: relative;
    padding: 20px 0;
    margin-left: 40px;
}

.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-icon {
    position: absolute;
    left: -50px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    z-index: 1;
}

.timeline-content {
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
    margin-left: 20px;
}

/* Empty state styling */
.empty-state {
    text-align: center;
    padding: 30px 0;
}

.empty-state i {
    color: #ccc;
    margin-bottom: 15px;
}
</style>

<?php include 'includes/footer.php'; ?>