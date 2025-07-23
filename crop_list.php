<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Fetch all active crops
try {
    $stmt = $pdo->prepare("
        SELECT c.*, f.field_name, f.location
        FROM crops c
        LEFT JOIN fields f ON c.field_id = f.id
        ORDER BY c.planting_date DESC
    ");
    $stmt->execute();
    $crops = $stmt->fetchAll();
    
    // Count total active crops
    $total_active = $pdo->query("SELECT COUNT(*) FROM crops WHERE status = 'active'")->fetchColumn();
    
    // Count crops needing attention (due for watering, fertilizing, etc.)
    $needs_attention = $pdo->query("
        SELECT COUNT(*) FROM crops 
        WHERE next_action_date <= DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
        AND status = 'active'
    ")->fetchColumn();
    
    // Count pest/disease issues
    $pest_disease = $pdo->query("
        SELECT COUNT(*) FROM crop_issues 
        WHERE resolved = 0 AND issue_type IN ('pest', 'disease')
    ")->fetchColumn();
    
    // Count upcoming harvests (next 30 days)
    $upcoming_harvests = $pdo->query("
        SELECT COUNT(*) FROM crops 
        WHERE expected_harvest_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
        AND status = 'active'
    ")->fetchColumn();
    
} catch(PDOException $e) {
    error_log("Error fetching crop data: " . $e->getMessage());
    // Set default values in case of error
    $crops = [];
    $total_active = 0;
    $needs_attention = 0;
    $pest_disease = 0;
    $upcoming_harvests = 0;
}

$pageTitle = 'Crop List';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-seedling"></i> Crop List</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='add_crop.php'">
                <i class="fas fa-plus"></i> Add New Crop
            </button>
            <button class="btn btn-primary" onclick="location.href='crop_reports.php'">
                <i class="fas fa-chart-bar"></i> View Reports
            </button>
            <button class="btn btn-primary" onclick="location.href='crop_performance.php'">
                <i class="fas fa-chart-line"></i> Performance Analysis
            </button>
            <button class="btn btn-secondary" onclick="location.href='crop_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Crop Management
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon bg-blue">
                <i class="fas fa-seedling"></i>
            </div>
            <div class="summary-details">
                <h3>Active Crops</h3>
                <p class="summary-count"><?php echo $total_active; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-orange">
                <i class="fas fa-exclamation"></i>
            </div>
            <div class="summary-details">
                <h3>Needs Attention</h3>
                <p class="summary-count"><?php echo $needs_attention; ?></p>
                <span class="summary-subtitle">Next 7 days</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-red">
                <i class="fas fa-bug"></i>
            </div>
            <div class="summary-details">
                <h3>Pest/Disease Issues</h3>
                <p class="summary-count"><?php echo $pest_disease; ?></p>
                <span class="summary-subtitle">Active cases</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-green">
                <i class="fas fa-tractor"></i>
            </div>
            <div class="summary-details">
                <h3>Upcoming Harvests</h3>
                <p class="summary-count"><?php echo $upcoming_harvests; ?></p>
                <span class="summary-subtitle">Next 30 days</span>
            </div>
        </div>
    </div>

    <!-- Crop List Table -->
    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-list"></i> All Crops</h3>
            <div class="card-actions">
                <div class="search-bar">
                    <input type="text" id="cropSearch" onkeyup="searchCrops()" placeholder="Search crops...">
                    <i class="fas fa-search"></i>
                </div>
                <div class="filter-dropdown">
                    <select id="statusFilter" onchange="filterCrops()">
                        <option value="all">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="harvested">Harvested</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table" id="cropTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Crop Name</th>
                        <th>Variety</th>
                        <th>Field Location</th>
                        <th>Planting Date</th>
                        <th>Expected Harvest</th>
                        <th>Growth Stage</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($crops)): ?>
                        <tr>
                            <td colspan="9" class="text-center">No crops found. <a href="add_crop.php">Add a crop</a> to get started.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($crops as $crop): ?>
                            <tr data-status="<?php echo htmlspecialchars($crop['status']); ?>">
                                <td><?php echo htmlspecialchars($crop['id']); ?></td>
                                <td><?php echo htmlspecialchars($crop['crop_name']); ?></td>
                                <td><?php echo htmlspecialchars($crop['variety']); ?></td>
                                <td><?php echo htmlspecialchars($crop['field_name'] . ' (' . $crop['location'] . ')'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($crop['planting_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($crop['expected_harvest_date'])); ?></td>
                                <td><?php echo htmlspecialchars($crop['growth_stage']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars(strtolower($crop['status'])); ?>">
                                        <?php echo htmlspecialchars(ucfirst($crop['status'])); ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="crop_details.php?id=<?php echo $crop['id']; ?>" class="btn-icon" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_crop.php?id=<?php echo $crop['id']; ?>" class="btn-icon" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="record_crop_activity.php?id=<?php echo $crop['id']; ?>" class="btn-icon" title="Record Activity">
                                        <i class="fas fa-clipboard-list"></i>
                                    </a>
                                    <?php if ($crop['status'] === 'active'): ?>
                                        <a href="record_harvest.php?id=<?php echo $crop['id']; ?>" class="btn-icon btn-icon-success" title="Record Harvest">
                                            <i class="fas fa-tractor"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($crop['status'] === 'harvested'): ?>
                                        <a href="record_harvest.php?id=<?php echo $crop['id']; ?>" class="btn-icon btn-icon-warning" title="Edit Harvest">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="report_issue.php?type=crop&id=<?php echo $crop['id']; ?>" class="btn-icon" title="Report Issue">
                                        <i class="fas fa-exclamation-circle"></i>
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

<script>
function searchCrops() {
    // Declare variables
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("cropSearch");
    filter = input.value.toUpperCase();
    table = document.getElementById("cropTable");
    tr = table.getElementsByTagName("tr");

    // Loop through all table rows, and hide those who don't match the search query
    for (i = 1; i < tr.length; i++) {
        // Skip header row
        if (tr[i].getElementsByTagName("td").length > 0) {
            var found = false;
            for (var j = 0; j < 3; j++) { // Search in first 3 columns
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

function filterCrops() {
    var select, filter, table, tr, td, i;
    select = document.getElementById("statusFilter");
    filter = select.value;
    table = document.getElementById("cropTable");
    tr = table.getElementsByTagName("tr");

    // Loop through all table rows, and hide those that don't match the selected status
    for (i = 1; i < tr.length; i++) {
        // Skip header row
        if (tr[i].getElementsByTagName("td").length > 0) {
            if (filter === "all") {
                tr[i].style.display = "";
            } else {
                var status = tr[i].getAttribute("data-status");
                if (status === filter) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }
}
</script>

<style>
/* Button icon colors */
.btn-icon-success {
    color: #2ecc71;
}

.btn-icon-warning {
    color: #f39c12;
}
</style>

<?php include 'includes/footer.php'; ?>