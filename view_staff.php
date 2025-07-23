<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Check if staff ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: staff_directory.php');
    exit;
}

$staff_id = $_GET['id'];

// Get staff details
try {
    $stmt = $pdo->prepare("
        SELECT s.*, r.role_name 
        FROM staff s
        LEFT JOIN roles r ON s.role_id = r.id
        WHERE s.id = :id
    ");
    $stmt->execute([':id' => $staff_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        // Staff not found, redirect to directory
        header('Location: staff_directory.php');
        exit;
    }
} catch(PDOException $e) {
    error_log("Error fetching staff details: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while retrieving staff details";
    header('Location: staff_directory.php');
    exit;
}

// Get activity logs
try {
    $stmt = $pdo->prepare("
        SELECT * FROM activity_logs
        WHERE entity_type = 'staff' AND entity_id = :staff_id
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([':staff_id' => $staff_id]);
    $activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching activity logs: " . $e->getMessage());
    $activity_logs = [];
}

$pageTitle = 'Staff Profile';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-user"></i> Staff Profile</h2>
        <div class="action-buttons">
            <a href="edit_staff.php?id=<?php echo $staff_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
            <a href="staff_directory.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Staff Directory
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Staff Profile -->
        <div class="col-md-4">
            <div class="card profile-card">
                <div class="card-header d-flex justify-content-between">
                    <h3>Staff Information</h3>
                    <span class="badge <?php echo $staff['status'] == 'active' ? 'badge-success' : ($staff['status'] == 'on-leave' ? 'badge-warning' : 'badge-danger'); ?>">
                        <?php echo isset($staff['status']) ? ucfirst($staff['status']) : 'Active'; ?>
                    </span>
                </div>
                
                <div class="card-body text-center">
                    <?php if (!empty($staff['profile_image'])): ?>
                        <img src="uploads/staff/<?php echo htmlspecialchars($staff['profile_image']); ?>" alt="Profile" class="profile-image">
                    <?php else: ?>
                        <div class="profile-icon-large">
                            <?php echo strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h4 class="mt-3 mb-0"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($staff['role_name'] ?? 'No Role Assigned'); ?></p>
                    <p class="staff-id">ID: <?php echo htmlspecialchars($staff['staff_id']); ?></p>
                </div>
                
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <i class="fas fa-envelope"></i> Email
                        <span class="float-right"><?php echo htmlspecialchars($staff['email']); ?></span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-phone"></i> Phone
                        <span class="float-right"><?php echo htmlspecialchars($staff['phone']); ?></span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-calendar-alt"></i> Hire Date
                        <span class="float-right"><?php echo date('M d, Y', strtotime($staff['hire_date'])); ?></span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-map-marker-alt"></i> Address
                        <span class="float-right"><?php echo !empty($staff['address']) ? htmlspecialchars($staff['address']) : 'Not provided'; ?></span>
                    </li>
                    <li class="list-group-item">
                        <i class="fas fa-ambulance"></i> Emergency Contact
                        <span class="float-right"><?php echo !empty($staff['emergency_contact']) ? htmlspecialchars($staff['emergency_contact']) : 'Not provided'; ?></span>
                    </li>
                </ul>
                
                <?php if (!empty($staff['notes'])): ?>
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Notes</h6>
                    <p class="card-text"><?php echo nl2br(htmlspecialchars($staff['notes'])); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="card-footer text-center">
                    <small class="text-muted">Last Updated: <?php echo date('M d, Y H:i', strtotime($staff['updated_at'] ?? date('Y-m-d H:i:s'))); ?></small>
                </div>
            </div>
        </div>
        
        <!-- Task and Performance Information -->
        <div class="col-md-8">
            <!-- Activity Log -->
            <div class="card">
                <div class="card-header">
                    <h3>Activity Log</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($activity_logs)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No activity logs available for this staff member.
                        </div>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($activity_logs as $log): ?>
                                <?php
                                $icon_class = 'fa-info-circle';
                                $color_class = 'timeline-info';
                                
                                if (isset($log['action'])) {
                                    switch ($log['action']) {
                                        case 'create':
                                            $icon_class = 'fa-plus-circle';
                                            $color_class = 'timeline-success';
                                            break;
                                        case 'update':
                                            $icon_class = 'fa-edit';
                                            $color_class = 'timeline-primary';
                                            break;
                                        case 'delete':
                                            $icon_class = 'fa-trash';
                                            $color_class = 'timeline-danger';
                                            break;
                                    }
                                }
                                
                                $details = isset($log['details']) ? json_decode($log['details'], true) : [];
                                ?>
                                <div class="timeline-item <?php echo $color_class; ?>">
                                    <div class="timeline-icon">
                                        <i class="fas <?php echo $icon_class; ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h4><?php echo isset($log['action']) ? ucfirst($log['action']) : 'Unknown'; ?> Action</h4>
                                        <p>
                                            <?php 
                                            if (isset($log['action'])) {
                                                if ($log['action'] == 'create') {
                                                    echo 'Staff profile created';
                                                } elseif ($log['action'] == 'update') {
                                                    if (isset($details['changed_status'])) {
                                                        echo 'Status changed to "' . ucfirst($details['changed_status']) . '"';
                                                    } else {
                                                        echo 'Profile information updated';
                                                    }
                                                }
                                            } else {
                                                echo 'Activity recorded';
                                            }
                                            ?>
                                        </p>
                                        <span class="timestamp"><?php echo isset($log['created_at']) ? date('M d, Y H:i', strtotime($log['created_at'])) : date('M d, Y H:i'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Profile styles */
    .profile-image {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        border: 5px solid white;
    }
    
    .profile-icon-large {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        background-color: #3498db;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        font-weight: bold;
        margin: 0 auto;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .staff-id {
        display: inline-block;
        padding: 3px 10px;
        background-color: #f8f9fa;
        border-radius: 15px;
        font-size: 0.9rem;
        margin-top: 10px;
    }
    
    .profile-card {
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        margin-bottom: 30px;
    }
    
    /* Activity Timeline */
    .activity-timeline {
        position: relative;
        padding-left: 50px;
    }
    
    .activity-timeline::before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: 20px;
        width: 2px;
        background-color: #e0e0e0;
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 25px;
    }
    
    .timeline-icon {
        position: absolute;
        left: -50px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        text-align: center;
        line-height: 40px;
        color: white;
        background-color: #3498db;
        z-index: 1;
    }
    
    .timeline-content {
        padding-left: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .timeline-content h4 {
        margin-bottom: 5px;
        border-bottom: none;
        color: #333;
        font-size: 1.1rem;
    }
    
    .timestamp {
        display: block;
        font-size: 0.8rem;
        color: #7f8c8d;
    }
    
    .timeline-success .timeline-icon {
        background-color: #2ecc71;
    }
    
    .timeline-primary .timeline-icon {
        background-color: #3498db;
    }
    
    .timeline-danger .timeline-icon {
        background-color: #e74c3c;
    }
    
    .timeline-warning .timeline-icon {
        background-color: #f39c12;
    }
    
    /* Card styles */
    .card {
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        margin-bottom: 30px;
    }
    
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #eee;
    }
    
    .card-header h3 {
        margin-bottom: 0;
        color: #2c3e50;
        font-size: 1.25rem;
    }
    
    /* Badge styles */
    .badge {
        padding: 5px 10px;
        border-radius: 15px;
    }
    
    .badge-success {
        background-color: #2ecc71;
    }
    
    .badge-warning {
        background-color: #f39c12;
    }
    
    .badge-danger {
        background-color: #e74c3c;
    }
    
    .badge-primary {
        background-color: #3498db;
    }
    
    .badge-info {
        background-color: #17a2b8;
    }
    
    /* Page header button styles */
    .action-buttons .btn {
        margin-left: 5px;
    }
    
    /* Alert styling */
    .alert-info {
        background-color: #d1ecf1;
        border-color: #bee5eb;
        color: #0c5460;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .row {
            flex-direction: column;
        }
        
        .col-md-4, .col-md-8 {
            width: 100%;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>