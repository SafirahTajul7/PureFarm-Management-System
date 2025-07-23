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
        SELECT c.*, f.field_name, f.location, f.soil_type, f.area
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
    
    // Fetch crop activities
    $activityStmt = $pdo->prepare("
        SELECT * FROM crop_activities 
        WHERE crop_id = :crop_id 
        ORDER BY activity_date DESC, created_at DESC
    ");
    $activityStmt->execute(['crop_id' => $crop_id]);
    $activities = $activityStmt->fetchAll();
    
    // Fetch crop issues
    $issueStmt = $pdo->prepare("
        SELECT * FROM crop_issues 
        WHERE crop_id = :crop_id 
        ORDER BY date_identified DESC
    ");
    $issueStmt->execute(['crop_id' => $crop_id]);
    $issues = $issueStmt->fetchAll();
    
} catch(PDOException $e) {
    error_log("Error fetching crop details: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error retrieving crop information.';
    header('Location: crop_list.php');
    exit;
}

$pageTitle = 'Crop Details: ' . $crop['crop_name'];
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-seedling"></i> Crop Details: <?php echo htmlspecialchars($crop['crop_name']); ?></h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='edit_crop.php?id=<?php echo $crop_id; ?>'">
                <i class="fas fa-edit"></i> Edit Crop
            </button>
            <button class="btn btn-primary" onclick="location.href='record_crop_activity.php?id=<?php echo $crop_id; ?>'">
                <i class="fas fa-clipboard-list"></i> Record Activity
            </button>
            <?php if ($crop['status'] === 'active'): ?>
            <button class="btn btn-success" onclick="location.href='record_harvest.php?id=<?php echo $crop_id; ?>'">
                <i class="fas fa-tractor"></i> Record Harvest
            </button>
            <?php endif; ?>
            <button class="btn btn-warning" onclick="location.href='report_issue.php?type=crop&id=<?php echo $crop_id; ?>'">
                <i class="fas fa-exclamation-circle"></i> Report Issue
            </button>
            <button class="btn btn-secondary" onclick="location.href='crop_list.php'">
                <i class="fas fa-arrow-left"></i> Back to List
            </button>
        </div>
    </div>

    <div class="content-grid">
        <!-- Crop Information Card -->
        <div class="content-card">
            <div class="content-card-header">
                <h3><i class="fas fa-info-circle"></i> Crop Information</h3>
            </div>
            <div class="content-card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Crop Name:</label>
                        <span><?php echo htmlspecialchars($crop['crop_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Variety:</label>
                        <span><?php echo htmlspecialchars($crop['variety'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Status:</label>
                        <span class="status-badge status-<?php echo strtolower($crop['status']); ?>">
                            <?php echo ucfirst(htmlspecialchars($crop['status'])); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Growth Stage:</label>
                        <span><?php echo ucfirst(htmlspecialchars($crop['growth_stage'])); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Field:</label>
                        <span><?php echo htmlspecialchars($crop['field_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Location:</label>
                        <span><?php echo htmlspecialchars($crop['location']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Field Area:</label>
                        <span><?php echo htmlspecialchars($crop['area']); ?> acres</span>
                    </div>
                    <div class="info-item">
                        <label>Soil Type:</label>
                        <span><?php echo htmlspecialchars($crop['soil_type'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Planting Date:</label>
                        <span><?php echo date('F d, Y', strtotime($crop['planting_date'])); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Expected Harvest:</label>
                        <span><?php echo $crop['expected_harvest_date'] ? date('F d, Y', strtotime($crop['expected_harvest_date'])) : 'N/A'; ?></span>
                    </div>
                    <?php if ($crop['actual_harvest_date']): ?>
                    <div class="info-item">
                        <label>Actual Harvest:</label>
                        <span><?php echo date('F d, Y', strtotime($crop['actual_harvest_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <label>Next Action:</label>
                        <span><?php echo htmlspecialchars($crop['next_action'] ?? 'None planned'); ?></span>
                    </div>
                    <?php if ($crop['next_action_date']): ?>
                    <div class="info-item">
                        <label>Next Action Date:</label>
                        <span><?php echo date('F d, Y', strtotime($crop['next_action_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item span-2">
                        <label>Notes:</label>
                        <span><?php echo nl2br(htmlspecialchars($crop['notes'] ?? 'No notes available.')); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Crop Activities Card -->
        <div class="content-card">
            <div class="content-card-header">
                <h3><i class="fas fa-clipboard-list"></i> Activities</h3>
                <button class="btn btn-sm btn-primary" onclick="location.href='record_crop_activity.php?id=<?php echo $crop_id; ?>'">
                    <i class="fas fa-plus"></i> Add Activity
                </button>
            </div>
            <div class="content-card-body">
                <?php if (empty($activities)): ?>
                    <p class="text-center">No activities recorded yet.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($activities as $activity): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">
                                    <?php echo date('M d, Y', strtotime($activity['activity_date'])); ?>
                                </div>
                                <div class="timeline-content">
                                    <h4><?php echo ucfirst(htmlspecialchars($activity['activity_type'])); ?></h4>
                                    <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                    <?php if ($activity['quantity']): ?>
                                    <p><strong>Quantity:</strong> <?php echo htmlspecialchars($activity['quantity'] . ' ' . $activity['unit']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($activity['notes']): ?>
                                    <p><strong>Notes:</strong> <?php echo htmlspecialchars($activity['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reported Issues Card -->
        <div class="content-card">
            <div class="content-card-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Issues</h3>
                <button class="btn btn-sm btn-warning" onclick="location.href='report_issue.php?type=crop&id=<?php echo $crop_id; ?>'">
                    <i class="fas fa-plus"></i> Report Issue
                </button>
            </div>
            <div class="content-card-body">
                <?php if (empty($issues)): ?>
                    <p class="text-center">No issues reported yet.</p>
                <?php else: ?>
                    <div class="issue-list">
                        <?php foreach ($issues as $issue): ?>
                            <div class="issue-item severity-<?php echo htmlspecialchars($issue['severity']); ?>">
                                <div class="issue-header">
                                    <h4><?php echo ucfirst(htmlspecialchars($issue['issue_type'])); ?> Issue</h4>
                                    <span class="issue-date">Reported: <?php echo date('M d, Y', strtotime($issue['date_identified'])); ?></span>
                                    <span class="issue-status <?php echo $issue['resolved'] ? 'resolved' : 'active'; ?>">
                                        <?php echo $issue['resolved'] ? 'Resolved' : 'Active'; ?>
                                    </span>
                                </div>
                                <div class="issue-body">
                                    <p><strong>Description:</strong> <?php echo htmlspecialchars($issue['description']); ?></p>
                                    <p><strong>Severity:</strong> <?php echo ucfirst(htmlspecialchars($issue['severity'])); ?></p>
                                    <?php if ($issue['treatment_applied']): ?>
                                    <p><strong>Treatment Applied:</strong> <?php echo htmlspecialchars($issue['treatment_applied']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($issue['resolved']): ?>
                                    <p><strong>Resolved On:</strong> <?php echo date('M d, Y', strtotime($issue['resolution_date'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="issue-actions">
                                    <?php if (!$issue['resolved']): ?>
                                    <a href="resolve_issue.php?id=<?php echo $issue['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Mark Resolved
                                    </a>
                                    <?php endif; ?>
                                    <a href="edit_issue.php?id=<?php echo $issue['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.content-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-item.span-2 {
    grid-column: span 2;
}

.info-item label {
    font-weight: 600;
    margin-bottom: 5px;
    color: #666;
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 4px;
    font-weight: 600;
    text-align: center;
}

.status-active {
    background-color: #d4edda;
    color: #155724;
}

.status-harvested {
    background-color: #d1ecf1;
    color: #0c5460;
}

.status-failed {
    background-color: #f8d7da;
    color: #721c24;
}

.timeline {
    position: relative;
    max-height: 500px;
    overflow-y: auto;
    padding-right: 10px;
}

.timeline-item {
    position: relative;
    padding-left: 30px;
    margin-bottom: 20px;
    border-left: 2px solid #ddd;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-date {
    position: absolute;
    left: -70px;
    top: 0;
    width: 65px;
    text-align: right;
    font-weight: 600;
    font-size: 0.85rem;
    color: #666;
}

.timeline-content {
    background-color: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.timeline-content h4 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #333;
}

.issue-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
    max-height: 500px;
    overflow-y: auto;
    padding-right: 10px;
}

.issue-item {
    border-left: 4px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.issue-item.severity-low {
    border-left-color: #ffc107;
}

.issue-item.severity-medium {
    border-left-color: #fd7e14;
}

.issue-item.severity-high {
    border-left-color: #dc3545;
}

.issue-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
}

.issue-header h4 {
    margin: 0;
    flex-grow: 1;
}

.issue-date {
    font-size: 0.85rem;
    color: #666;
    margin-right: 15px;
}

.issue-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 0.85rem;
    font-weight: 600;
}

.issue-status.active {
    background-color: #f8d7da;
    color: #721c24;
}

.issue-status.resolved {
    background-color: #d4edda;
    color: #155724;
}

.issue-body {
    padding: 15px;
    background-color: #fff;
}

.issue-body p {
    margin: 0 0 10px;
}

.issue-body p:last-child {
    margin-bottom: 0;
}

.issue-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 10px 15px;
    background-color: #f8f9fa;
    border-top: 1px solid #eee;
}

@media (min-width: 992px) {
    .content-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .content-grid > .content-card:first-child {
        grid-column: span 2;
    }
}
</style>

<?php include 'includes/footer.php'; ?>