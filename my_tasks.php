<?php
require_once 'includes/auth.php';
auth()->checkSupervisor();

require_once 'includes/db.php';

$success_message = '';
$error_message = '';

// Handle task status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $task_id = $_POST['task_id'];
        $new_status = $_POST['status'];
        
        try {
            // Update task status
            $update_stmt = $pdo->prepare("
                UPDATE staff_tasks 
                SET status = ?, 
                    completion_date = CASE WHEN ? = 'completed' THEN CURRENT_DATE ELSE completion_date END,
                    last_updated = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $update_stmt->execute([$new_status, $new_status, $task_id]);
            
            $success_message = "Task status updated successfully!";
        } catch(PDOException $e) {
            error_log("Error updating task status: " . $e->getMessage());
            $error_message = "An error occurred while updating the task.";
        }
    }
}

// Get filter parameter
$filter = $_GET['filter'] ?? 'all';

// Fetch all tasks with staff information and role details
try {
    $sql = "
        SELECT t.*, 
               CONCAT(s.first_name, ' ', s.last_name) as staff_name,
               s.staff_id as staff_code,
               CASE 
                   WHEN r.role_name IS NOT NULL THEN r.role_name
                   WHEN s.role_id = 1 THEN 'Farm Manager'
                   WHEN s.role_id = 2 THEN 'Supervisor'
                   WHEN s.role_id = 3 THEN 'Field Worker'
                   WHEN s.role_id = 4 THEN 'Crop Specialist'
                   WHEN s.role_id = 5 THEN 'Equipment Operator'
                   WHEN s.role_id = 6 THEN 'Veterinarian'
                   WHEN s.role_id = 7 THEN 'Administrative Staff'
                   ELSE 'Staff Member'
               END as role_name,
               DATE_FORMAT(t.assigned_date, '%d %b %Y') as formatted_assigned_date,
               DATE_FORMAT(t.due_date, '%d %b %Y') as formatted_due_date,
               DATE_FORMAT(t.completion_date, '%d %b %Y') as formatted_completion_date,
               DATEDIFF(t.due_date, CURRENT_DATE) as days_until_due,
               CASE 
                   WHEN t.status = 'completed' OR t.status = 'cancelled' THEN 0
                   WHEN DATEDIFF(t.due_date, CURRENT_DATE) < 0 THEN 1
                   ELSE 0
               END as is_overdue
        FROM staff_tasks t
        INNER JOIN staff s ON t.staff_id = s.id
        LEFT JOIN roles r ON s.role_id = r.id
        ORDER BY 
            CASE 
                WHEN t.status = 'pending' THEN 1
                WHEN t.status = 'in_progress' THEN 2
                WHEN t.status = 'completed' THEN 3
                WHEN t.status = 'cancelled' THEN 4
                ELSE 5
            END,
            t.priority DESC,
            t.due_date ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $all_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug information
    error_log("Found " . count($all_tasks) . " total tasks");
    
} catch(PDOException $e) {
    error_log("Error fetching tasks: " . $e->getMessage());
    $all_tasks = [];
    $error_message = "Error loading tasks. Please try again later.";
}

// Calculate task statistics
$total_tasks = count($all_tasks);
$pending_tasks = count(array_filter($all_tasks, function($task) { return $task['status'] === 'pending'; }));
$in_progress_tasks = count(array_filter($all_tasks, function($task) { return $task['status'] === 'in_progress'; }));
$completed_tasks = count(array_filter($all_tasks, function($task) { return $task['status'] === 'completed'; }));
$overdue_tasks = count(array_filter($all_tasks, function($task) { return $task['is_overdue'] == 1; }));

$pageTitle = 'All Staff Tasks';
include 'includes/header.php';
?>

<div class="main-content">    <div class="page-header">
        <h2><i class="fas fa-clipboard-list"></i> All Staff Tasks</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='supervisor_staff_tasks.php'">
                <i class="fas fa-users"></i> Staff Tasks Overview
            </button>
            <button class="btn btn-secondary" onclick="location.href='supervisordashboard.php'">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </button>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Task Statistics Cards -->
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
            <div class="summary-icon bg-orange">
                <i class="fas fa-clock"></i>
            </div>
            <div class="summary-details">
                <h3>Pending</h3>
                <p class="summary-count"><?php echo $pending_tasks; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-primary">
                <i class="fas fa-play-circle"></i>
            </div>
            <div class="summary-details">
                <h3>In Progress</h3>
                <p class="summary-count"><?php echo $in_progress_tasks; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="summary-details">
                <h3>Completed</h3>
                <p class="summary-count"><?php echo $completed_tasks; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-red">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="summary-details">
                <h3>Overdue</h3>
                <p class="summary-count"><?php echo $overdue_tasks; ?></p>
            </div>
        </div>
    </div>

    <!-- Task Filter Buttons -->
    <div class="filter-panel">
        <div class="filter-buttons">
            <button class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>" 
                    onclick="filterTasks('all')" data-filter="all">
                <i class="fas fa-list"></i> All Tasks (<?php echo $total_tasks; ?>)
            </button>
            <button class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>" 
                    onclick="filterTasks('pending')" data-filter="pending">
                <i class="fas fa-clock"></i> Pending (<?php echo $pending_tasks; ?>)
            </button>
            <button class="filter-btn <?php echo $filter === 'in_progress' ? 'active' : ''; ?>" 
                    onclick="filterTasks('in_progress')" data-filter="in_progress">
                <i class="fas fa-play-circle"></i> In Progress (<?php echo $in_progress_tasks; ?>)
            </button>
            <button class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>" 
                    onclick="filterTasks('completed')" data-filter="completed">
                <i class="fas fa-check-circle"></i> Completed (<?php echo $completed_tasks; ?>)
            </button>
            <button class="filter-btn <?php echo $filter === 'overdue' ? 'active' : ''; ?>" 
                    onclick="filterTasks('overdue')" data-filter="overdue">
                <i class="fas fa-exclamation-triangle"></i> Overdue (<?php echo $overdue_tasks; ?>)
            </button>
        </div>
    </div>

    <!-- Tasks Table -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-table"></i> Staff Task Assignment Overview</h5>
        </div>
        <div class="card-body">
            <?php if (empty($all_tasks)): ?>
                <div class="empty-state text-center py-5">
                    <div class="empty-state-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h4>No Tasks Found</h4>
                    <p>No tasks have been assigned to staff members yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="tasksTable">                        <thead class="table-dark">
                            <tr>
                                <th>Staff Member</th>
                                <th>Role</th>
                                <th>Task Title</th>
                                <th>Description</th>
                                <th>Priority</th>
                                <th>Assigned Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_tasks as $task): ?>
                                <?php 
                                // Determine priority class and color
                                $priority_class = '';
                                $priority_color = '';
                                switch ($task['priority']) {
                                    case 'high':
                                        $priority_class = 'danger';
                                        $priority_color = 'text-danger';
                                        break;
                                    case 'medium':
                                        $priority_class = 'warning';
                                        $priority_color = 'text-warning';
                                        break;
                                    case 'low':
                                        $priority_class = 'success';
                                        $priority_color = 'text-success';
                                        break;
                                }
                                
                                // Determine status class and color
                                $status_class = '';
                                switch ($task['status']) {
                                    case 'pending':
                                        $status_class = 'warning';
                                        break;
                                    case 'in_progress':
                                        $status_class = 'primary';
                                        break;
                                    case 'completed':
                                        $status_class = 'success';
                                        break;
                                    case 'cancelled':
                                        $status_class = 'secondary';
                                        break;
                                }
                                
                                // Determine row class for overdue tasks
                                $row_class = '';
                                if ($task['is_overdue'] == 1) {
                                    $row_class = 'table-danger';
                                }
                                ?>
                                
                                <tr class="task-row <?php echo $row_class; ?>" 
                                    data-status="<?php echo $task['status']; ?>" 
                                    data-overdue="<?php echo $task['is_overdue']; ?>">
                                    <td>
                                        <div class="staff-info">
                                            <strong><?php echo htmlspecialchars($task['staff_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($task['staff_code']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($task['role_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($task['task_title']); ?></strong>
                                        <?php if ($task['is_overdue'] == 1): ?>
                                            <br><small class="text-danger">
                                                <i class="fas fa-exclamation-triangle"></i> 
                                                <?php echo abs($task['days_until_due']); ?> days overdue
                                            </small>
                                        <?php elseif ($task['days_until_due'] == 0 && $task['status'] !== 'completed'): ?>
                                            <br><small class="text-warning">
                                                <i class="fas fa-clock"></i> Due today
                                            </small>
                                        <?php elseif ($task['days_until_due'] > 0 && $task['status'] !== 'completed'): ?>
                                            <br><small class="text-info">
                                                <i class="fas fa-calendar"></i> 
                                                <?php echo $task['days_until_due']; ?> days remaining
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="task-description">
                                            <?php if (!empty($task['task_description'])): ?>
                                                <?php echo htmlspecialchars(substr($task['task_description'], 0, 100)); ?>
                                                <?php if (strlen($task['task_description']) > 100): ?>...<?php endif; ?>
                                            <?php else: ?>
                                                <em class="text-muted">No description provided</em>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $priority_class; ?>">
                                            <i class="fas fa-flag"></i> <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $task['formatted_assigned_date']; ?></td>
                                    <td><?php echo $task['formatted_due_date']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php 
                                            switch ($task['status']) {
                                                case 'pending':
                                                    echo '<i class="fas fa-clock"></i> Pending';
                                                    break;
                                                case 'in_progress':
                                                    echo '<i class="fas fa-play"></i> In Progress';
                                                    break;
                                                case 'completed':
                                                    echo '<i class="fas fa-check"></i> Completed';
                                                    break;
                                                case 'cancelled':
                                                    echo '<i class="fas fa-times"></i> Cancelled';
                                                    break;
                                                default:
                                                    echo ucfirst($task['status']);
                                            }
                                            ?>                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>        </div>
    </div>
</div>

<!-- Hidden form for status updates -->
<form id="updateStatusForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="task_id" id="updateTaskId">
    <input type="hidden" name="status" id="updateStatus">
</form>

<style>
/* Summary Grid */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    padding: 25px;
    display: flex;
    align-items: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.summary-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    flex-shrink: 0;
}

.summary-icon i {
    font-size: 24px;
    color: white;
}

.summary-icon.bg-blue { background: linear-gradient(135deg, #3498db, #2980b9); }
.summary-icon.bg-orange { background: linear-gradient(135deg, #f39c12, #e67e22); }
.summary-icon.bg-primary { background: linear-gradient(135deg, #3498db, #2c3e50); }
.summary-icon.bg-green { background: linear-gradient(135deg, #2ecc71, #27ae60); }
.summary-icon.bg-red { background: linear-gradient(135deg, #e74c3c, #c0392b); }

.summary-details h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #666;
    font-weight: 500;
}

.summary-count {
    margin: 0;
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
}

/* Filter Panel */
.filter-panel {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 25px;
}

.filter-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.filter-btn {
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    color: #495057;
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.filter-btn:hover {
    background: #e9ecef;
    transform: translateY(-1px);
}

.filter-btn.active {
    background: #2ecc71;
    border-color: #2ecc71;
    color: white;
    box-shadow: 0 3px 10px rgba(46, 204, 113, 0.3);
}

/* Table Styles */
.table {
    margin-bottom: 0;
}

.table th {
    border-top: none;
    font-weight: 600;
    font-size: 14px;
    padding: 15px 10px;
}

.table td {
    padding: 15px 10px;
    vertical-align: middle;
}

.staff-info strong {
    font-size: 14px;
    color: #2c3e50;
}

.staff-info small {
    font-size: 12px;
}

.task-description {
    font-size: 13px;
    line-height: 1.4;
}

.badge {
    font-size: 11px;
    padding: 6px 10px;
    font-weight: 500;
}

.task-row.table-danger {
    background-color: rgba(220, 53, 69, 0.05) !important;
}

.task-row:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

/* Empty State */
.empty-state-icon {
    font-size: 80px;
    color: #bdc3c7;
    margin-bottom: 20px;
}

.empty-state h4 {
    color: #2c3e50;
    margin-bottom: 10px;
}

.empty-state p {
    color: #7f8c8d;
    margin-bottom: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-buttons {
        justify-content: center;
    }
    
    .filter-btn {
        font-size: 12px;
        padding: 8px 12px;
    }
    
    .table-responsive {
        font-size: 12px;
    }
      .table th,
    .table td {
        padding: 8px 5px;
    }
}

.main-content {
    padding-bottom: 40px;
}
</style>

<script>
// Filter tasks based on status
function filterTasks(filter) {
    const rows = document.querySelectorAll('.task-row');
    const buttons = document.querySelectorAll('.filter-btn');
    
    // Update active button
    buttons.forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-filter="${filter}"]`).classList.add('active');
    
    // Show/hide rows based on filter
    rows.forEach(row => {
        const status = row.getAttribute('data-status');
        const isOverdue = row.getAttribute('data-overdue') === '1';
        
        let shouldShow = false;
        
        switch(filter) {
            case 'all':
                shouldShow = true;
                break;
            case 'pending':
                shouldShow = status === 'pending';
                break;
            case 'in_progress':
                shouldShow = status === 'in_progress';
                break;
            case 'completed':
                shouldShow = status === 'completed';
                break;
            case 'overdue':
                shouldShow = isOverdue && status !== 'completed' && status !== 'cancelled';
                break;
        }
        
        row.style.display = shouldShow ? '' : 'none';
    });
    
    // Update URL without refreshing
    const url = new URL(window.location);
    if (filter === 'all') {
        url.searchParams.delete('filter');
    } else {
        url.searchParams.set('filter', filter);
    }
    window.history.pushState({}, '', url);
}

// Update task status
function updateTaskStatus(taskId, newStatus) {
    if (confirm(`Are you sure you want to update this task status to "${newStatus.replace('_', ' ')}"?`)) {
        document.getElementById('updateTaskId').value = taskId;
        document.getElementById('updateStatus').value = newStatus;
        document.getElementById('updateStatusForm').submit();
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Apply filter from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const filterParam = urlParams.get('filter') || 'all';
    
    if (filterParam !== 'all') {
        filterTasks(filterParam);
    }
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.metaKey) {
            switch(e.key) {
                case '1':
                    e.preventDefault();
                    filterTasks('all');
                    break;
                case '2':
                    e.preventDefault();
                    filterTasks('pending');
                    break;
                case '3':
                    e.preventDefault();
                    filterTasks('in_progress');
                    break;
                case '4':
                    e.preventDefault();
                    filterTasks('completed');
                    break;
                case '5':
                    e.preventDefault();
                    filterTasks('overdue');
                    break;
            }
        }
    });
    
    // Auto-refresh every 2 minutes
    setInterval(function() {
        location.reload();
    }, 120000);
    
    console.log('Staff Tasks Management loaded successfully!');
});
</script>

<?php include 'includes/footer.php'; ?>