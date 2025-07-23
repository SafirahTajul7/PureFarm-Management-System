<?php
require_once 'includes/auth.php';
auth()->checkSupervisor();

require_once 'includes/db.php';

// Get current date and week
$current_date = new DateTime();
$selected_date = isset($_GET['date']) ? new DateTime($_GET['date']) : $current_date;

// Calculate week start (Monday)
$week_start = clone $selected_date;
$week_start->modify('monday this week');

// Fetch team members
try {
    $stmt = $pdo->query("
        SELECT id, CONCAT(first_name, ' ', last_name) as full_name, 
               role_id, status, profile_image
        FROM staff 
        WHERE status = 'active'
        ORDER BY first_name, last_name
    ");
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching team members: " . $e->getMessage());
    $team_members = [];
}

// Fetch tasks for the current week
try {
    $week_end = clone $week_start;
    $week_end->modify('+6 days');
    
    $stmt = $pdo->prepare("
        SELECT t.*, CONCAT(s.first_name, ' ', s.last_name) as staff_name,
               s.id as staff_id
        FROM staff_tasks t
        JOIN staff s ON t.staff_id = s.id
        WHERE t.due_date BETWEEN ? AND ?
        AND s.status = 'active'
        ORDER BY t.due_date, t.priority DESC
    ");
    $stmt->execute([$week_start->format('Y-m-d'), $week_end->format('Y-m-d')]);
    $week_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching week tasks: " . $e->getMessage());
    $week_tasks = [];
}

// Organize tasks by day and staff
$schedule = [];
for ($i = 0; $i < 7; $i++) {
    $day = clone $week_start;
    $day->modify("+$i days");
    $date_key = $day->format('Y-m-d');
    $schedule[$date_key] = [];
    
    foreach ($team_members as $member) {
        $schedule[$date_key][$member['id']] = [];
    }
}

// Populate schedule with tasks
foreach ($week_tasks as $task) {
    $task_date = $task['due_date'];
    if (isset($schedule[$task_date][$task['staff_id']])) {
        $schedule[$task_date][$task['staff_id']][] = $task;
    }
}

// Calculate team statistics
$total_tasks_week = count($week_tasks);
$completed_tasks_week = count(array_filter($week_tasks, function($task) { 
    return $task['status'] === 'completed'; 
}));
$pending_tasks_week = count(array_filter($week_tasks, function($task) { 
    return $task['status'] === 'pending'; 
}));
$overdue_tasks_week = count(array_filter($week_tasks, function($task) { 
    return $task['status'] !== 'completed' && $task['status'] !== 'cancelled' && $task['due_date'] < date('Y-m-d'); 
}));

$pageTitle = 'Team Schedule';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-calendar-alt"></i> Team Schedule</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='staff_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Staff Management
            </button>
        </div>
    </div>

    <!-- Week Statistics -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon bg-blue">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="summary-details">
                <h3>Total Tasks This Week</h3>
                <p class="summary-count"><?php echo $total_tasks_week; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="summary-details">
                <h3>Completed</h3>
                <p class="summary-count"><?php echo $completed_tasks_week; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-orange">
                <i class="fas fa-clock"></i>
            </div>
            <div class="summary-details">
                <h3>Pending</h3>
                <p class="summary-count"><?php echo $pending_tasks_week; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-red">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="summary-details">
                <h3>Overdue</h3>
                <p class="summary-count"><?php echo $overdue_tasks_week; ?></p>
            </div>
        </div>
    </div>

    <!-- Week Navigation -->
    <div class="week-navigation">
        <button class="btn btn-outline-primary" onclick="navigateWeek(-1)">
            <i class="fas fa-chevron-left"></i> Previous Week
        </button>
        
        <div class="week-info">
            <h4><?php echo $week_start->format('M d') . ' - ' . $week_end->format('M d, Y'); ?></h4>
            <p class="text-muted">Week <?php echo $week_start->format('W'); ?> of <?php echo $week_start->format('Y'); ?></p>
        </div>
        
        <button class="btn btn-outline-primary" onclick="navigateWeek(1)">
            Next Week <i class="fas fa-chevron-right"></i>
        </button>
        
        <button class="btn btn-primary" onclick="goToCurrentWeek()">
            <i class="fas fa-calendar-day"></i> Current Week
        </button>
    </div>

    <!-- Schedule Grid -->
    <div class="schedule-container">
        <div class="schedule-grid">
            <!-- Header Row -->
            <div class="schedule-header">
                <div class="staff-column-header">Team Members</div>
                <?php for ($i = 0; $i < 7; $i++): ?>
                    <?php 
                    $day = clone $week_start;
                    $day->modify("+$i days");
                    $is_today = $day->format('Y-m-d') === $current_date->format('Y-m-d');
                    $is_weekend = in_array($day->format('N'), [6, 7]); // Saturday, Sunday
                    ?>
                    <div class="day-header <?php echo $is_today ? 'today' : ''; ?> <?php echo $is_weekend ? 'weekend' : ''; ?>">
                        <div class="day-name"><?php echo $day->format('D'); ?></div>
                        <div class="day-date"><?php echo $day->format('M d'); ?></div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Staff Rows -->
            <?php foreach ($team_members as $member): ?>
                <div class="staff-row">
                    <div class="staff-info">
                        <div class="staff-avatar">
                            <?php if (!empty($member['profile_image'])): ?>
                                <img src="uploads/staff/<?php echo htmlspecialchars($member['profile_image']); ?>" alt="Profile">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?php echo strtoupper(substr($member['full_name'], 0, 2)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="staff-details">
                            <div class="staff-name"><?php echo htmlspecialchars($member['full_name']); ?></div>
                        </div>
                    </div>

                    <!-- Daily Task Cells -->
                    <?php for ($i = 0; $i < 7; $i++): ?>
                        <?php 
                        $day = clone $week_start;
                        $day->modify("+$i days");
                        $date_key = $day->format('Y-m-d');
                        $day_tasks = $schedule[$date_key][$member['id']] ?? [];
                        $is_today = $day->format('Y-m-d') === $current_date->format('Y-m-d');
                        $is_weekend = in_array($day->format('N'), [6, 7]);
                        ?>
                        <div class="task-cell <?php echo $is_today ? 'today' : ''; ?> <?php echo $is_weekend ? 'weekend' : ''; ?>" 
                             data-date="<?php echo $date_key; ?>" 
                             data-staff="<?php echo $member['id']; ?>">
                            
                            <?php if (!empty($day_tasks)): ?>
                                <?php foreach ($day_tasks as $task): ?>
                                    <?php 
                                    $priority_class = 'priority-' . $task['priority'];
                                    $status_class = 'status-' . str_replace('_', '-', $task['status']);
                                    
                                    $is_overdue = $task['status'] !== 'completed' && 
                                                 $task['status'] !== 'cancelled' && 
                                                 $task['due_date'] < date('Y-m-d');
                                    ?>
                                    <div class="task-item <?php echo $priority_class; ?> <?php echo $status_class; ?> <?php echo $is_overdue ? 'overdue' : ''; ?>"
                                         data-task-id="<?php echo $task['id']; ?>"
                                         title="<?php echo htmlspecialchars($task['task_description'] ?: $task['task_title']); ?>">
                                        
                                        <div class="task-title">
                                            <?php echo htmlspecialchars($task['task_title']); ?>
                                        </div>
                                        
                                        <div class="task-meta">
                                            <span class="priority-indicator <?php echo $priority_class; ?>">
                                                <?php echo strtoupper(substr($task['priority'], 0, 1)); ?>
                                            </span>
                                            <span class="status-indicator <?php echo $status_class; ?>">
                                                <?php 
                                                $status_icons = [
                                                    'pending' => 'clock',
                                                    'in_progress' => 'spinner',
                                                    'completed' => 'check',
                                                    'cancelled' => 'times'
                                                ];
                                                $icon = $status_icons[$task['status']] ?? 'question';
                                                ?>
                                                <i class="fas fa-<?php echo $icon; ?>"></i>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-tasks">
                                    <span class="text-muted">No tasks</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Legend -->
    <div class="schedule-legend">
        <h6>Legend:</h6>
        <div class="legend-items">
            <div class="legend-item">
                <div class="legend-color priority-high"></div>
                <span>High Priority</span>
            </div>
            <div class="legend-item">
                <div class="legend-color priority-medium"></div>
                <span>Medium Priority</span>
            </div>
            <div class="legend-item">
                <div class="legend-color priority-low"></div>
                <span>Low Priority</span>
            </div>
            <div class="legend-item">
                <div class="legend-color status-completed"></div>
                <span>Completed</span>
            </div>
            <div class="legend-item">
                <div class="legend-color overdue"></div>
                <span>Overdue</span>
            </div>
        </div>
    </div>

    <!-- Task Detail Modal -->
    <div class="modal fade" id="taskDetailModal" tabindex="-1" aria-labelledby="taskDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskDetailModalLabel">
                        <i class="fas fa-tasks"></i> Task Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 id="taskTitle"></h4>
                            <p id="taskDescription" class="text-muted"></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <span id="taskPriority" class="badge"></span>
                            <span id="taskStatus" class="badge"></span>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Assigned To:</strong> <span id="taskAssignee"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Due Date:</strong> <span id="taskDueDate"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Team Schedule Styles */
.week-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
    padding: 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.week-info {
    text-align: center;
    margin: 0 20px;
}

.week-info h4 {
    margin: 0;
    color: #2c3e50;
}

.week-info p {
    margin: 5px 0 0 0;
}

.schedule-container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 20px;
}

.schedule-grid {
    display: grid;
    grid-template-columns: 200px repeat(7, 1fr);
    min-height: 500px;
}

.schedule-header {
    display: contents;
}

.staff-column-header {
    background: #2c3e50;
    color: white;
    padding: 15px;
    font-weight: 600;
    display: flex;
    align-items: center;
    border-right: 1px solid #34495e;
}

.day-header {
    background: #34495e;
    color: white;
    padding: 15px 10px;
    text-align: center;
    border-right: 1px solid #2c3e50;
}

.day-header.today {
    background: #3498db;
}

.day-header.weekend {
    background: #7f8c8d;
}

.day-name {
    font-weight: 600;
    font-size: 14px;
}

.day-date {
    font-size: 12px;
    margin-top: 2px;
    opacity: 0.9;
}

.staff-row {
    display: contents;
}

.staff-info {
    background: #f8f9fa;
    padding: 15px;
    border-right: 1px solid #dee2e6;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    align-items: center;
    gap: 10px;
}

.staff-avatar {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
}

.staff-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #3498db;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 12px;
}

.staff-name {
    font-weight: 500;
    color: #2c3e50;
    font-size: 14px;
}

.task-cell {
    padding: 8px;
    border-right: 1px solid #dee2e6;
    border-bottom: 1px solid #dee2e6;
    min-height: 80px;
    background: #fafafa;
    position: relative;
}

.task-cell.today {
    background: #e3f2fd;
}

.task-cell.weekend {
    background: #f5f5f5;
}

.task-item {
    background: white;
    border-radius: 6px;
    padding: 6px 8px;
    margin-bottom: 4px;
    border-left: 3px solid #ddd;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 12px;
}

.task-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.task-item:last-child {
    margin-bottom: 0;
}

/* Priority colors */
.task-item.priority-high {
    border-left-color: #e74c3c;
}

.task-item.priority-medium {
    border-left-color: #f39c12;
}

.task-item.priority-low {
    border-left-color: #2ecc71;
}

/* Status styles */
.task-item.status-completed {
    opacity: 0.7;
    background: #f8f9fa;
}

.task-item.status-completed .task-title {
    text-decoration: line-through;
}

.task-item.overdue {
    background: #ffebee;
    border-left-color: #f44336;
}

.task-title {
    font-weight: 500;
    margin-bottom: 4px;
    line-height: 1.2;
    color: #2c3e50;
}

.task-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.priority-indicator {
    background: #ddd;
    color: white;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: bold;
}

.priority-indicator.priority-high {
    background: #e74c3c;
}

.priority-indicator.priority-medium {
    background: #f39c12;
}

.priority-indicator.priority-low {
    background: #2ecc71;
}

.status-indicator {
    color: #7f8c8d;
    font-size: 10px;
}

.status-indicator.status-completed {
    color: #2ecc71;
}

.status-indicator.status-in-progress {
    color: #3498db;
}

.status-indicator.status-pending {
    color: #f39c12;
}

.no-tasks {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    font-size: 11px;
}

/* Legend */
.schedule-legend {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 15px;
    margin-bottom: 20px;
}

.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 10px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 3px;
}

.legend-color.priority-high {
    background: #e74c3c;
}

.legend-color.priority-medium {
    background: #f39c12;
}

.legend-color.priority-low {
    background: #2ecc71;
}

.legend-color.status-completed {
    background: #95a5a6;
}

.legend-color.overdue {
    background: #f44336;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .schedule-grid {
        grid-template-columns: 150px repeat(7, 1fr);
    }
    
    .staff-info {
        padding: 10px;
    }
    
    .staff-avatar {
        width: 30px;
        height: 30px;
    }
    
    .avatar-placeholder {
        width: 30px;
        height: 30px;
        font-size: 10px;
    }
}

@media (max-width: 768px) {
    .week-navigation {
        flex-direction: column;
        gap: 15px;
    }
    
    .schedule-grid {
        grid-template-columns: 120px repeat(7, 80px);
        overflow-x: auto;
    }
    
    .staff-name {
        font-size: 12px;
    }
    
    .task-item {
        font-size: 10px;
        padding: 4px 6px;
    }
    
    .legend-items {
        justify-content: center;
    }
}

.main-content {
    padding-bottom: 60px;
    min-height: calc(100vh - 60px);
}

body {
    padding-bottom: 60px;
}
</style>

<script>
// Global variables
let currentWeekStart = new Date('<?php echo $week_start->format('Y-m-d'); ?>');

// Navigation functions
function navigateWeek(direction) {
    const newDate = new Date(currentWeekStart);
    newDate.setDate(newDate.getDate() + (direction * 7));
    
    const year = newDate.getFullYear();
    const month = String(newDate.getMonth() + 1).padStart(2, '0');
    const day = String(newDate.getDate()).padStart(2, '0');
    
    window.location.href = `team_schedule.php?date=${year}-${month}-${day}`;
}

function goToCurrentWeek() {
    window.location.href = 'team_schedule.php';
}

// Task detail modal functionality
document.addEventListener('DOMContentLoaded', function() {
    const taskItems = document.querySelectorAll('.task-item');
    const taskDetailModal = new bootstrap.Modal(document.getElementById('taskDetailModal'));
    
    // Sample task data - in real implementation, this would come from PHP
    const taskData = {
        <?php foreach ($week_tasks as $task): ?>
        '<?php echo $task['id']; ?>': {
            title: '<?php echo addslashes($task['task_title']); ?>',
            description: '<?php echo addslashes($task['task_description'] ?: 'No description provided'); ?>',
            priority: '<?php echo $task['priority']; ?>',
            status: '<?php echo $task['status']; ?>',
            assignee: '<?php echo addslashes($task['staff_name']); ?>',
            dueDate: '<?php echo date('F d, Y', strtotime($task['due_date'])); ?>'
        },
        <?php endforeach; ?>
    };
    
    taskItems.forEach(item => {
        item.addEventListener('click', function() {
            const taskId = this.getAttribute('data-task-id');
            const task = taskData[taskId];
            
            if (task) {
                // Update modal content
                document.getElementById('taskTitle').textContent = task.title;
                document.getElementById('taskDescription').textContent = task.description;
                document.getElementById('taskAssignee').textContent = task.assignee;
                document.getElementById('taskDueDate').textContent = task.dueDate;
                
                // Update priority badge
                const priorityBadge = document.getElementById('taskPriority');
                priorityBadge.textContent = task.priority.charAt(0).toUpperCase() + task.priority.slice(1) + ' Priority';
                priorityBadge.className = 'badge';
                
                if (task.priority === 'high') {
                    priorityBadge.classList.add('bg-danger');
                } else if (task.priority === 'medium') {
                    priorityBadge.classList.add('bg-warning');
                } else {
                    priorityBadge.classList.add('bg-success');
                }
                
                // Update status badge
                const statusBadge = document.getElementById('taskStatus');
                let statusText = task.status.replace('_', ' ');
                statusText = statusText.charAt(0).toUpperCase() + statusText.slice(1);
                statusBadge.textContent = statusText;
                statusBadge.className = 'badge';
                
                if (task.status === 'completed') {
                    statusBadge.classList.add('bg-success');
                } else if (task.status === 'in_progress') {
                    statusBadge.classList.add('bg-primary');
                } else if (task.status === 'pending') {
                    statusBadge.classList.add('bg-warning');
                } else {
                    statusBadge.classList.add('bg-secondary');
                }
                
                // Show modal
                taskDetailModal.show();
            }
        });
    });
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') {
            navigateWeek(-1);
        } else if (e.key === 'ArrowRight') {
            navigateWeek(1);
        } else if (e.key === 'Home') {
            e.preventDefault();
            goToCurrentWeek();
        }
    });
    
    // Add tooltips to task items
    taskItems.forEach(item => {
        const title = item.getAttribute('title');
        if (title) {
            item.setAttribute('data-bs-toggle', 'tooltip');
            item.setAttribute('data-bs-placement', 'top');
        }
    });
    
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Highlight current time
    const now = new Date();
    const currentHour = now.getHours();
    
    // Add visual indicator for current time if viewing current week
    const today = new Date();
    const weekStart = new Date(currentWeekStart);
    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekEnd.getDate() + 6);
    
    if (today >= weekStart && today <= weekEnd) {
        const todayCells = document.querySelectorAll('.task-cell.today');
        todayCells.forEach(cell => {
            cell.style.borderTop = '3px solid #3498db';
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>