<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Update existing database records to use consistent format (run once, then can be removed)
try {
    $update_status = $pdo->prepare("UPDATE staff_tasks SET status = 'in_progress' WHERE status = 'in progress'");
    $update_status->execute();
    error_log("Updated status values from 'in progress' to 'in_progress'");
} catch(PDOException $e) {
    error_log("Error updating status values: " . $e->getMessage());
}

// Fetch summary statistics
try {
    // Total tasks
    $total_tasks = $pdo->query("SELECT COUNT(*) FROM staff_tasks")->fetchColumn();
    
    // Completed tasks
    $completed_tasks = $pdo->query("
        SELECT COUNT(*) 
        FROM staff_tasks 
        WHERE status = 'completed'
    ")->fetchColumn();
    
    // Tasks in progress
    $in_progress_tasks = $pdo->query("
        SELECT COUNT(*) 
        FROM staff_tasks 
        WHERE status = 'in_progress'
    ")->fetchColumn();
    
    // Tasks by priority
    $high_priority_tasks = $pdo->query("
        SELECT COUNT(*) 
        FROM staff_tasks 
        WHERE priority = 'high' AND status != 'completed' AND status != 'cancelled'
    ")->fetchColumn();

} catch(PDOException $e) {
    error_log("Error fetching summary data: " . $e->getMessage());
    // Set default values in case of error
    $total_tasks = 0;
    $completed_tasks = 0;
    $in_progress_tasks = 0;
    $high_priority_tasks = 0;
}

// Calculate completion percentage
$completion_percentage = ($total_tasks > 0) ? round(($completed_tasks / $total_tasks) * 100) : 0;

// Fetch staff with assigned tasks
try {
    $stmt = $pdo->prepare("
        SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as staff_name, 
            COUNT(t.id) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM staff s
        JOIN staff_tasks t ON s.id = t.staff_id
        GROUP BY s.id
        ORDER BY completed_tasks DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_performers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching top performers: " . $e->getMessage());
    $top_performers = [];
}

// Fetch task completion by category (using staff roles as a proxy for task categories)
try {
    $stmt = $pdo->prepare("
        SELECT r.role_name, 
            COUNT(t.id) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM staff_tasks t
        JOIN staff s ON t.staff_id = s.id
        JOIN roles r ON s.role_id = r.id
        GROUP BY r.role_name
        ORDER BY total_tasks DESC
    ");
    $stmt->execute();
    $task_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching task categories: " . $e->getMessage());
    $task_categories = [];
}

// Fetch recent task updates
try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.task_title, t.status, t.completion_date, 
            CONCAT(s.first_name, ' ', s.last_name) as staff_name, 
            t.priority, t.last_updated
        FROM staff_tasks t
        JOIN staff s ON t.staff_id = s.id
        ORDER BY 
            CASE 
                WHEN t.completion_date IS NOT NULL THEN t.completion_date 
                ELSE t.last_updated 
            END DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching recent updates: " . $e->getMessage());
    $recent_updates = [];
}

$pageTitle = 'Task Progress Tracking';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-line"></i> Task Progress Tracking</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='task_assignment.php'">
                <i class="fas fa-tasks"></i> Assign Tasks
            </button>
            <button class="btn btn-secondary" onclick="location.href='staff_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Staff Management
            </button>
        </div>
    </div>

    <!-- Progress Overview Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon bg-blue">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="summary-details">
                <h3>Total Tasks</h3>
                <p class="summary-count"><?php echo $total_tasks; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="summary-details">
                <h3>Completed Tasks</h3>
                <p class="summary-count"><?php echo $completed_tasks; ?></p>
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: <?php echo $completion_percentage; ?>%"></div>
                </div>
                <span class="progress-text"><?php echo $completion_percentage; ?>% completion rate</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-orange">
                <i class="fas fa-spinner"></i>
            </div>
            <div class="summary-details">
                <h3>In Progress</h3>
                <p class="summary-count"><?php echo $in_progress_tasks; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-red">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="summary-details">
                <h3>High Priority</h3>
                <p class="summary-count"><?php echo $high_priority_tasks; ?></p>
                <span class="summary-subtitle">Pending or in progress</span>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Top Performers Card -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-trophy text-warning"></i> Top Performers</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Assigned Tasks</th>
                                    <th>Completion Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_performers)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">No task data available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($top_performers as $performer): ?>
                                        <?php 
                                        $completion_rate = ($performer['total_tasks'] > 0) ? 
                                            round(($performer['completed_tasks'] / $performer['total_tasks']) * 100) : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="performer-info">
                                                    <span class="performer-name"><?php echo htmlspecialchars($performer['staff_name']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo $performer['total_tasks']; ?></td>
                                            <td>
                                                <div class="progress-bar-small-container">
                                                    <div class="progress-bar-small" style="width: <?php echo $completion_rate; ?>%"></div>
                                                </div>
                                                <span class="progress-text-small"><?php echo $completion_rate; ?>%</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tasks by Category Card -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-tags text-primary"></i> Tasks by Category</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Total Tasks</th>
                                    <th>Completion Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($task_categories)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">No category data available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($task_categories as $category): ?>
                                        <?php 
                                        $category_completion = ($category['total_tasks'] > 0) ? 
                                            round(($category['completed_tasks'] / $category['total_tasks']) * 100) : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($category['role_name']); ?></td>
                                            <td><?php echo $category['total_tasks']; ?></td>
                                            <td>
                                                <div class="progress-bar-small-container">
                                                    <div class="progress-bar-small" style="width: <?php echo $category_completion; ?>%"></div>
                                                </div>
                                                <span class="progress-text-small"><?php echo $category_completion; ?>%</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Task Updates Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-history"></i> Recent Task Updates</h5>
        </div>
        <div class="card-body">
            <div class="task-table-container">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Assigned To</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_updates)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No recent updates found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_updates as $update): ?>
                                <?php 
                                // Determine priority class
                                $priority_class = "";
                                switch ($update['priority']) {
                                    case 'high':
                                        $priority_class = "danger";
                                        break;
                                    case 'medium':
                                        $priority_class = "warning";
                                        break;
                                    case 'low':
                                        $priority_class = "success";
                                        break;
                                }
                                
                                // Determine status class
                                $status_class = "";
                                switch ($update['status']) {
                                    case 'pending':
                                        $status_class = "warning";
                                        break;
                                    case 'in_progress':
                                        $status_class = "primary";
                                        break;
                                    case 'completed':
                                        $status_class = "success";
                                        break;
                                    case 'cancelled':
                                        $status_class = "secondary";
                                        break;
                                }
                                
                                // Format date
                                $update_date = isset($update['completion_date']) && $update['completion_date'] ? 
                                    $update['completion_date'] : ($update['last_updated'] ?? 'Unknown');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($update['task_title']); ?></td>
                                    <td><?php echo htmlspecialchars($update['staff_name']); ?></td>
                                    <td><span class="badge bg-<?php echo $priority_class; ?>"><?php echo ucfirst($update['priority']); ?></span></td>
                                    <td><span class="badge bg-<?php echo $status_class; ?>">
                                        <?php 
                                        // Format the status for display
                                        $display_status = $update['status'];
                                        if ($display_status == 'in_progress' || $display_status == 'in progress') {
                                            echo 'In Progress';
                                        } else if ($display_status == 'pending') {
                                            echo 'Pending';
                                        } else if ($display_status == 'cancelled') {
                                            echo 'Cancelled';
                                        } else if ($display_status == 'completed') {
                                            echo 'Completed';
                                        } else {
                                            echo ucfirst($display_status);
                                        }
                                        ?>
                                    </span></td>
                                    <td><?php echo htmlspecialchars($update_date); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-3">
                <button class="btn btn-outline-primary btn-sm toggle-table-height">
                    <i class="fas fa-chevron-down"></i> View More
                </button>
            </div>
        </div>
    </div>

    <!-- Progress Tracking Tips Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-lightbulb text-warning"></i> Progress Tracking Tips</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="tip-card">
                        <i class="fas fa-chart-line text-primary"></i>
                        <h4>Monitor Regularly</h4>
                        <p>Check task progress daily to identify bottlenecks early and ensure timely completion of critical farm operations.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="tip-card">
                        <i class="fas fa-comments text-success"></i>
                        <h4>Provide Feedback</h4>
                        <p>Regular feedback helps staff understand performance expectations and improves future task completion rates.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="tip-card">
                        <i class="fas fa-trophy text-info"></i>
                        <h4>Recognize Achievement</h4>
                        <p>Acknowledge high performers to boost morale and encourage continued productivity across the farm team.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Task Progress specific styles */
.progress-bar-container {
    width: 100%;
    background-color: #f0f0f0;
    height: 8px;
    border-radius: 4px;
    margin-top: 10px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background-color: #2ecc71;
    transition: width 0.5s ease-in-out;
}

.progress-text {
    font-size: 0.8rem;
    color: #7f8c8d;
    margin-top: 4px;
    display: inline-block;
}

.progress-bar-small-container {
    width: 80%;
    background-color: #f0f0f0;
    height: 6px;
    border-radius: 3px;
    display: inline-block;
    margin-right: 10px;
    vertical-align: middle;
}

.progress-bar-small {
    height: 100%;
    background-color: #3498db;
    border-radius: 3px;
}

.progress-text-small {
    font-size: 0.75rem;
    color: #7f8c8d;
    vertical-align: middle;
}

.performer-info {
    display: flex;
    align-items: center;
}

.performer-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    margin-right: 10px;
    object-fit: cover;
}

.performer-name {
    font-weight: 500;
}

.tip-card {
    padding: 15px;
    border-radius: 5px;
    background-color: #f8f9fa;
    height: 100%;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.tip-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.tip-card i {
    font-size: 28px;
    margin-bottom: 10px;
}

.tip-card h4 {
    margin-bottom: 10px;
    font-size: 18px;
}

.badge.bg-danger {
    background-color: #e74c3c !important;
}

.badge.bg-warning {
    background-color: #f39c12 !important;
}

.badge.bg-success {
    background-color: #2ecc71 !important;
}

.badge.bg-primary {
    background-color: #3498db !important;
}

.badge.bg-secondary {
    background-color: #95a5a6 !important;
}

.main-content {
    padding-bottom: 60px; /* Add space for footer */
    min-height: calc(100vh - 60px); /* Ensure content takes up full height minus footer */
}

footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    padding: 15px 0;
    background-color: #f8f9fa;
    text-align: center;
    border-top: 1px solid #dee2e6;
    height: 50px;
}

/* Additional spacing for the features grid */
.features-grid {
    margin-bottom: 70px; /* Add extra space before footer */
}

/* Prevent content from being hidden behind footer */
body {
    padding-bottom: 60px;
} 

/* Task table container for improved scrolling */
.task-table-container {
    max-height: 400px;
    overflow-y: auto;
    position: relative;
}

.task-table-container thead th {
    position: sticky;
    top: 0;
    background-color: #fff;
    z-index: 1;
    box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
}

/* Additional styles for table scrolling */
.task-table-container {
    max-height: 400px;
    overflow-y: auto;
    position: relative;
    transition: max-height 0.5s ease;
}

.task-table-container table {
    position: relative;
}

.task-table-container thead th {
    position: sticky;
    top: 0;
    background-color: white;
    z-index: 1;
    box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
}

/* Custom scrollbar */
.task-table-container::-webkit-scrollbar {
    width: 8px;
}

.task-table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.task-table-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.task-table-container::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

/* Button transition */
.toggle-table-height {
    transition: all 0.3s ease;
}

.toggle-table-height:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle View All Tasks button
    const viewAllTasksBtn = document.getElementById('view-all-tasks');
    const taskTableContainer = document.querySelector('.table-responsive');
    
    let isExpanded = false;
    
    viewAllTasksBtn.addEventListener('click', function() {
        if (!isExpanded) {
            // Expand the table
            taskTableContainer.style.maxHeight = '800px'; // Increased height
            viewAllTasksBtn.textContent = 'Show Less';
            isExpanded = true;
        } else {
            // Collapse back to default
            taskTableContainer.style.maxHeight = '400px';
            viewAllTasksBtn.textContent = 'View All Tasks';
            isExpanded = false;
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    // Handle toggling table height
    const toggleBtn = document.querySelector('.toggle-table-height');
    const taskTableContainer = document.querySelector('.task-table-container');
    
    let isExpanded = false;
    
    toggleBtn.addEventListener('click', function() {
        if (!isExpanded) {
            // Expand the table
            taskTableContainer.style.maxHeight = '800px';
            toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i> Show Less';
            isExpanded = true;
        } else {
            // Collapse back to default
            taskTableContainer.style.maxHeight = '400px';
            toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i> View More';
            isExpanded = false;
        }
        
        // Smooth scroll to the table
        taskTableContainer.scrollIntoView({behavior: 'smooth'});
    });
});
</script>

<footer class="footer">
    <div class="container">
        <p>&copy; 2025 PureFarm Management System. All rights reserved. <span class="float-end">Version 1.0</span></p>
    </div>
</footer>

<?php include 'includes/footer.php'; ?>