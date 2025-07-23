<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Fetch task statistics
try {
    // Total pending tasks
    $total_pending = $pdo->query("SELECT COUNT(*) FROM staff_tasks WHERE status = 'pending'")->fetchColumn();
    
    // Tasks due today
    $due_today = $pdo->query("
        SELECT COUNT(*) 
        FROM staff_tasks 
        WHERE status = 'pending' 
        AND due_date = CURRENT_DATE
    ")->fetchColumn();
    
    // Overdue tasks
    $overdue_tasks = $pdo->query("
        SELECT COUNT(*) 
        FROM staff_tasks 
        WHERE status = 'pending' 
        AND due_date < CURRENT_DATE
    ")->fetchColumn();

} catch(PDOException $e) {
    error_log("Error fetching task statistics: " . $e->getMessage());
    // Set default values in case of error
    $total_pending = 0;
    $due_today = 0;
    $overdue_tasks = 0;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_task') {
                // Insert new task
                $stmt = $pdo->prepare("
                    INSERT INTO staff_tasks 
                    (staff_id, task_title, task_description, priority, status, due_date, assigned_date) 
                    VALUES (?, ?, ?, ?, 'pending', ?, CURRENT_DATE)
                ");
                $stmt->execute([
                    $_POST['staff_id'],
                    $_POST['task_title'],
                    $_POST['task_description'],
                    $_POST['priority'],
                    $_POST['due_date']
                ]);
                
                $success_message = "Task assigned successfully!";
                
            } elseif ($_POST['action'] === 'update_task') {
                // Update existing task
                $stmt = $pdo->prepare("
                    UPDATE staff_tasks 
                    SET staff_id = ?, 
                        task_title = ?, 
                        task_description = ?, 
                        priority = ?, 
                        status = ?, 
                        due_date = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['staff_id'],
                    $_POST['task_title'],
                    $_POST['task_description'],
                    $_POST['priority'],
                    $_POST['status'],
                    $_POST['due_date'],
                    $_POST['task_id']
                ]);
                
                $success_message = "Task updated successfully!";
            } elseif ($_POST['action'] === 'delete_task') {
                // Delete existing task
                $stmt = $pdo->prepare("DELETE FROM staff_tasks WHERE id = ?");
                $stmt->execute([$_POST['task_id']]);
                
                $success_message = "Task deleted successfully!";
            }
            
            // Refresh page after form submission
            header("Location: task_assignment.php");
            exit;
            
        } catch(PDOException $e) {
            error_log("Error processing task form: " . $e->getMessage());
            $error_message = "An error occurred while processing your request.";
        }
    }
}

// Fetch all staff for dropdown - IMPROVED QUERY
try {
    // First, check the structure of the staff table
    $checkStmt = $pdo->query("SHOW COLUMNS FROM staff");
    $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Determine if we have role_id or role column
    $roleColumn = in_array('role_id', $columns) ? 'role_id' : (in_array('role', $columns) ? 'role' : null);
    
    if ($roleColumn) {
        // If we have a role column, include it in the query
        $stmt = $pdo->prepare("
            SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as full_name, 
                  " . ($roleColumn === 'role_id' ? "r.role_name as role" : "s.role") . "
            FROM staff s
            " . ($roleColumn === 'role_id' ? "LEFT JOIN roles r ON s.role_id = r.id" : "") . "
            WHERE (s.status = 'active' OR s.status IS NULL)
            ORDER BY s.first_name, s.last_name
        ");
    } else {
        // If no role column exists, just get name information
        $stmt = $pdo->prepare("
            SELECT id, CONCAT(first_name, ' ', last_name) as full_name
            FROM staff
            WHERE (status = 'active' OR status IS NULL)
            ORDER BY first_name, last_name
        ");
    }
    
    $stmt->execute();
    $staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no staff members are found, log this
    if (empty($staff_members)) {
        error_log("No active staff members found in the database. Check if the staff table has any records.");
    }
    
} catch(PDOException $e) {
    error_log("Error fetching staff for dropdown: " . $e->getMessage());
    $staff_members = [];
}

// Fetch recent tasks (limited to 10) with staff role information
try {
    // First, check the structure of the staff table
    if (!isset($columns)) {
        $checkStmt = $pdo->query("SHOW COLUMNS FROM staff");
        $columns = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Determine if we have role_id or role column
    $roleColumn = in_array('role_id', $columns) ? 'role_id' : (in_array('role', $columns) ? 'role' : null);
    
    if ($roleColumn === 'role_id') {
        // If using role_id with roles table
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   CONCAT(s.first_name, ' ', s.last_name) as staff_name,
                   r.role_name as staff_role
            FROM staff_tasks t
            JOIN staff s ON t.staff_id = s.id
            LEFT JOIN roles r ON s.role_id = r.id
            ORDER BY t.assigned_date DESC, t.priority DESC
            LIMIT 10
        ");
    } elseif ($roleColumn === 'role') {
        // If role is stored directly in staff table
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   CONCAT(s.first_name, ' ', s.last_name) as staff_name,
                   s.role as staff_role
            FROM staff_tasks t
            JOIN staff s ON t.staff_id = s.id
            ORDER BY t.assigned_date DESC, t.priority DESC
            LIMIT 10
        ");
    } else {
        // Fallback if no role column exists
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   CONCAT(s.first_name, ' ', s.last_name) as staff_name,
                   'Staff' as staff_role
            FROM staff_tasks t
            JOIN staff s ON t.staff_id = s.id
            ORDER BY t.assigned_date DESC, t.priority DESC
            LIMIT 10
        ");
    }
    
    $stmt->execute();
    $recent_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching recent tasks: " . $e->getMessage());
    $recent_tasks = [];
}

$pageTitle = 'Task Assignment';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-tasks"></i> Task Assignment</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" id="addTaskBtn">
                <i class="fas fa-plus"></i> Assign New Task
            </button>
            <button class="btn btn-secondary" onclick="location.href='staff_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Staff Management
            </button>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Task Statistics Cards -->    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon bg-blue">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="summary-details">
                <h3>Total Pending Tasks</h3>
                <p class="summary-count"><?php echo $total_pending; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-orange">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="summary-details">
                <h3>Due Today</h3>
                <p class="summary-count"><?php echo $due_today; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-red">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="summary-details">
                <h3>Overdue Tasks</h3>
                <p class="summary-count"><?php echo $overdue_tasks; ?></p>
            </div>
        </div>
    </div>

    <!-- Recent Tasks Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-history"></i> Recent Tasks</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Assigned To <small>(with Role)</small></th>
                            <th>Priority</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_tasks)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No tasks found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_tasks as $task): ?>
                                <?php 
                                // Determine priority class
                                $priority_class = "";
                                switch ($task['priority']) {
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
                                switch ($task['status']) {
                                    case 'pending':
                                        $status_class = "warning";
                                        break;
                                    case 'in_progress':  // Changed to match dropdown value
                                    case 'in progress':  // Keep backward compatibility
                                        $status_class = "primary";
                                        break;
                                    case 'completed':
                                        $status_class = "success";
                                        break;
                                    case 'cancelled':
                                        $status_class = "secondary";
                                        break;
                                }
                                
                                // Check if task is overdue
                                $is_overdue = false;
                                if ($task['status'] !== 'completed' && $task['status'] !== 'cancelled') {
                                    $due_date = new DateTime($task['due_date']);
                                    $today = new DateTime();
                                    if ($due_date < $today) {
                                        $is_overdue = true;
                                    }
                                }
                                ?>                                <tr class="<?php echo $is_overdue ? 'table-danger' : ''; ?>">
                                    <td><?php echo htmlspecialchars($task['task_title']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($task['staff_name']); ?></div>
                                        <small class="text-muted"><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($task['staff_role'] ?? 'Staff'); ?></small>
                                    </td>
                                    <td><span class="badge bg-<?php echo $priority_class; ?>"><?php echo ucfirst($task['priority']); ?></span></td>
                                    <td><?php echo htmlspecialchars($task['due_date']); ?></td>
                                    <td><span class="badge bg-<?php echo $status_class; ?>">
                                        <?php 
                                        // Format the status for display
                                        $display_status = $task['status'];
                                        if ($display_status == 'in_progress' || $display_status == 'in progress') {
                                            echo 'In Progress';
                                        } else {
                                            echo ucfirst($display_status);
                                        }
                                        ?>
                                    </span></td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-task-btn" 
                                                data-id="<?php echo $task['id']; ?>"
                                                data-staff="<?php echo $task['staff_id']; ?>"
                                                data-title="<?php echo htmlspecialchars($task['task_title']); ?>"
                                                data-description="<?php echo htmlspecialchars($task['task_description']); ?>"
                                                data-priority="<?php echo $task['priority']; ?>"
                                                data-status="<?php echo $task['status']; ?>"
                                                data-due-date="<?php echo $task['due_date']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-task-btn"
                                                data-id="<?php echo $task['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($task['task_title']); ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Removing "View All Tasks" link as it's not needed -->
        </div>
    </div>

    <!-- Task Management Tips Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-lightbulb text-warning"></i> Task Management Tips</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="tip-card">
                        <i class="fas fa-sort-amount-up text-primary"></i>
                        <h4>Prioritize Effectively</h4>
                        <p>Assign high priority to tasks that are time-sensitive or impact critical farm operations like irrigation or animal health checks.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="tip-card">
                        <i class="fas fa-users text-success"></i>
                        <h4>Match Skills to Tasks</h4>
                        <p>Assign tasks based on staff skills and experience to maximize efficiency and task completion quality.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="tip-card">
                        <i class="fas fa-balance-scale text-info"></i>
                        <h4>Balance Workloads</h4>
                        <p>Distribute tasks evenly among staff to prevent burnout and ensure consistent productivity across the farm.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTaskModalLabel"><i class="fas fa-plus-circle"></i> Assign New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="task_assignment.php" method="POST">
                <input type="hidden" name="action" value="add_task">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="staff_id" class="form-label">Assign To</label>
                            <select class="form-select" id="staff_id" name="staff_id" required>
                                <option value="">Select Staff Member</option>
                                <?php if (!empty($staff_members)): ?>
                                    <?php foreach ($staff_members as $staff): ?>
                                        <option value="<?php echo $staff['id']; ?>">
                                            <?php echo htmlspecialchars($staff['full_name'] . ' (' . $staff['role'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No staff members available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="task_title" class="form-label">Task Title</label>
                            <input type="text" class="form-control" id="task_title" name="task_title" placeholder="Enter task title" required>
                        </div>
                        <div class="col-md-4">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-select" id="priority" name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="task_description" class="form-label">Task Description</label>
                            <textarea class="form-control" id="task_description" name="task_description" rows="4" placeholder="Enter detailed task description"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTaskModalLabel"><i class="fas fa-edit"></i> Edit Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="task_assignment.php" method="POST">
                <input type="hidden" name="action" value="update_task">
                <input type="hidden" name="task_id" id="edit_task_id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_staff_id" class="form-label">Assigned To</label>
                            <select class="form-select" id="edit_staff_id" name="staff_id" required>
                                <option value="">Select Staff Member</option>
                                <?php if (!empty($staff_members)): ?>
                                    <?php foreach ($staff_members as $staff): ?>
                                        <option value="<?php echo $staff['id']; ?>">
                                            <?php echo htmlspecialchars($staff['full_name'] . ' (' . $staff['role'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No staff members available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="edit_due_date" name="due_date" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_task_title" class="form-label">Task Title</label>
                            <input type="text" class="form-control" id="edit_task_title" name="task_title" required>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_priority" class="form-label">Priority</label>
                            <select class="form-select" id="edit_priority" name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_task_description" class="form-label">Task Description</label>
                            <textarea class="form-control" id="edit_task_description" name="task_description" rows="4"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Task Confirmation Modal -->
<div class="modal fade" id="deleteTaskModal" tabindex="-1" aria-labelledby="deleteTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteTaskModalLabel"><i class="fas fa-trash-alt"></i> Delete Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="task_assignment.php" method="POST">
                <input type="hidden" name="action" value="delete_task">
                <input type="hidden" name="task_id" id="delete_task_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the task: <strong id="delete_task_title"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Task Modal (kept for reference but not used in updated UI) -->
<div class="modal fade" id="viewTaskModal" tabindex="-1" aria-labelledby="viewTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTaskModalLabel"><i class="fas fa-clipboard-list"></i> Task Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h4 id="view_task_title" class="mb-3">Task Title</h4>
                        <p class="mb-1"><strong>Assigned To:</strong> <span id="view_staff_name"></span></p>
                        <p class="mb-1"><strong>Assigned Date:</strong> <span id="view_assigned_date"></span></p>
                        <p class="mb-1"><strong>Due Date:</strong> <span id="view_due_date"></span></p>
                        <p class="mb-1"><strong>Completion Date:</strong> <span id="view_completion_date">-</span></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="status-badge mb-2">
                            <span class="badge bg-primary" id="view_status_badge">Status</span>
                        </div>
                        <div class="priority-badge">
                            <span class="badge bg-warning" id="view_priority_badge">Priority</span>
                        </div>
                    </div>
                </div>
                <div class="task-description mb-4">
                    <h5>Description</h5>
                    <p id="view_task_description" class="p-3 bg-light rounded">Task description goes here.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="edit_from_view_btn">Edit Task</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Task Assignment specific styles */
.status-badge .badge, .priority-badge .badge {
    font-size: 1rem;
    padding: 8px 12px;
}

/* Staff info in Assigned To column */
td small.text-muted {
    font-size: 0.85rem;
    display: block;
    margin-top: 2px;
}

.task-description {
    border-top: 1px solid #dee2e6;
    padding-top: 15px;
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default date for the add task form
    const today = new Date();
    const formattedToday = today.toISOString().split('T')[0];
    
    // Set default date for due date to today
    document.getElementById('due_date').value = formattedToday;
    
    // Handle Add Task button click
    document.getElementById('addTaskBtn').addEventListener('click', function() {
        const addModal = new bootstrap.Modal(document.getElementById('addTaskModal'));
        addModal.show();
    });
    
    // Edit Task button event listeners
    document.querySelectorAll('.edit-task-btn').forEach(button => {
        button.addEventListener('click', function() {
            const taskId = this.getAttribute('data-id');
            const staffId = this.getAttribute('data-staff');
            const title = this.getAttribute('data-title');
            const description = this.getAttribute('data-description');
            const priority = this.getAttribute('data-priority');
            const status = this.getAttribute('data-status');
            const dueDate = this.getAttribute('data-due-date');
            
            // Update edit modal fields
            document.getElementById('edit_task_id').value = taskId;
            document.getElementById('edit_staff_id').value = staffId;
            document.getElementById('edit_task_title').value = title;
            document.getElementById('edit_task_description').value = description;
            document.getElementById('edit_priority').value = priority;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_due_date').value = dueDate;
            
            // Show the edit modal
            const editModal = new bootstrap.Modal(document.getElementById('editTaskModal'));
            editModal.show();
        });
    });
    
    // Delete Task button event listeners
    document.querySelectorAll('.delete-task-btn').forEach(button => {
        button.addEventListener('click', function() {
            const taskId = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            
            // Update delete modal fields
            document.getElementById('delete_task_id').value = taskId;
            document.getElementById('delete_task_title').textContent = title;
            
            // Show the delete confirmation modal
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteTaskModal'));
            deleteModal.show();
        });
    });
    
    // Edit from View button event listener (kept for reference but not used in updated UI)
    if (document.getElementById('edit_from_view_btn')) {
        document.getElementById('edit_from_view_btn').addEventListener('click', function() {
            const taskId = this.getAttribute('data-task-id');
            
            // First hide the view modal
            const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewTaskModal'));
            viewModal.hide();
            
            // Find and trigger the edit button for this task
            const editButton = document.querySelector(`.edit-task-btn[data-id="${taskId}"]`);
            if (editButton) {
                // Slight delay to ensure first modal is hidden
                setTimeout(() => {
                    editButton.click();
                }, 500);
            }
        });
    }
    
    // Debug staff dropdown
    const staffDropdown = document.getElementById('staff_id');
    if (staffDropdown) {
        console.log('Staff dropdown found, options count: ' + staffDropdown.options.length);
    } else {
        console.log('Staff dropdown not found!');
    }
    
    // Fix for the Cancel button - ensure proper modal initialization
    document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(button => {
        button.addEventListener('click', function() {
            const modalId = this.closest('.modal').id;
            const modalInstance = bootstrap.Modal.getInstance(document.getElementById(modalId));
            if (modalInstance) {
                modalInstance.hide();
            }
        });
    });
});
</script>

<footer class="footer">
    <div class="container">
        <p>&copy; 2025 PureFarm Management System. All rights reserved. <span class="float-end">Version 1.0</span></p>
    </div>
</footer>

<?php include 'includes/footer.php'; ?>