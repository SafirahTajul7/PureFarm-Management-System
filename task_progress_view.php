<?php
require_once 'includes/auth.php';
auth()->checkSupervisor();

require_once 'includes/db.php';

$user_id = auth()->getUserId();

// Fetch personal task statistics
try {
    // Total tasks assigned to me
    $total_tasks = $pdo->prepare("SELECT COUNT(*) FROM staff_tasks WHERE staff_id = ?");
    $total_tasks->execute([$user_id]);
    $total_tasks = $total_tasks->fetchColumn();
    
    // Completed tasks
    $completed_tasks = $pdo->prepare("
        SELECT COUNT(*) 
        FROM staff_tasks 
        WHERE staff_id = ? AND status = 'completed'
    ");
    $completed_tasks->execute([$user_id]);
    $completed_tasks = $completed_tasks->fetchColumn();
    
    // Tasks in progress
    $in_progress_tasks = $pdo->prepare("
        SELECT COUNT(*) 
        FROM staff_tasks 
        WHERE staff_id = ? AND status = 'in_progress'
    ");
    $in_progress_tasks->execute([$user_id]);
    $in_progress_tasks = $in_progress_tasks->fetchColumn();
    
    // Overdue tasks
    $overdue_tasks = $pdo->prepare("
        SELECT COUNT(*) 
        FROM staff_tasks 
        WHERE staff_id = ? AND status != 'completed' AND status != 'cancelled' AND due_date < CURRENT_DATE
    ");
    $overdue_tasks->execute([$user_id]);
    $overdue_tasks = $overdue_tasks->fetchColumn();

} catch(PDOException $e) {
    error_log("Error fetching task statistics: " . $e->getMessage());
    $total_tasks = 0;
    $completed_tasks = 0;
    $in_progress_tasks = 0;
    $overdue_tasks = 0;
}

// Calculate completion percentage
$completion_percentage = ($total_tasks > 0) ? round(($completed_tasks / $total_tasks) * 100) : 0;

// Fetch task completion history (last 30 days)
try {
    $stmt = $pdo->prepare("
        SELECT DATE(completion_date) as completion_date, COUNT(*) as completed_count
        FROM staff_tasks 
        WHERE staff_id = ? 
        AND completion_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
        AND status = 'completed'
        GROUP BY DATE(completion_date)
        ORDER BY completion_date ASC
    ");
    $stmt->execute([$user_id]);
    $completion_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching completion history: " . $e->getMessage());
    $completion_history = [];
}

// Fetch tasks by priority
try {
    $stmt = $pdo->prepare("
        SELECT priority, 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM staff_tasks 
        WHERE staff_id = ?
        GROUP BY priority
        ORDER BY 
            CASE priority 
                WHEN 'high' THEN 1 
                WHEN 'medium' THEN 2 
                WHEN 'low' THEN 3 
                ELSE 4 
            END
    ");
    $stmt->execute([$user_id]);
    $tasks_by_priority = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching tasks by priority: " . $e->getMessage());
    $tasks_by_priority = [];
}

// Fetch recent completed tasks
try {
    $stmt = $pdo->prepare("
        SELECT task_title, completion_date, priority,
               DATE_FORMAT(completion_date, '%M %d, %Y') as formatted_completion_date
        FROM staff_tasks 
        WHERE staff_id = ? AND status = 'completed'
        ORDER BY completion_date DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_completed = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching recent completed tasks: " . $e->getMessage());
    $recent_completed = [];
}

// Fetch upcoming tasks (next 7 days)
try {
    $stmt = $pdo->prepare("
        SELECT task_title, due_date, priority, status,
               DATE_FORMAT(due_date, '%M %d, %Y') as formatted_due_date,
               DATEDIFF(due_date, CURRENT_DATE) as days_until_due
        FROM staff_tasks 
        WHERE staff_id = ? 
        AND status != 'completed' 
        AND status != 'cancelled'
        AND due_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
        ORDER BY due_date ASC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $upcoming_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching upcoming tasks: " . $e->getMessage());
    $upcoming_tasks = [];
}

$pageTitle = 'My Task Progress';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-line"></i> My Task Progress</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='my_tasks.php'">
                <i class="fas fa-tasks"></i> View All Tasks
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
                <h3>Completed</h3>
                <p class="summary-count"><?php echo $completed_tasks; ?></p>
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: <?php echo $completion_percentage; ?>%"></div>
                </div>
                <span class="progress-text"><?php echo $completion_percentage; ?>% completion rate</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-purple">
                <i class="fas fa-spinner"></i>
            </div>
            <div class="summary-details">
                <h3>In Progress</h3>
                <p class="summary-count"><?php echo $in_progress_tasks; ?></p>
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

    <div class="row">
        <!-- Tasks by Priority -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-exclamation-circle text-warning"></i> Tasks by Priority</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($tasks_by_priority)): ?>
                        <p class="text-center text-muted">No task data available</p>
                    <?php else: ?>
                        <?php foreach ($tasks_by_priority as $priority_data): ?>
                            <?php 
                            $completion_rate = ($priority_data['total_tasks'] > 0) ? 
                                round(($priority_data['completed_tasks'] / $priority_data['total_tasks']) * 100) : 0;
                            
                            $priority_colors = [
                                'high' => 'danger',
                                'medium' => 'warning', 
                                'low' => 'success'
                            ];
                            $color = $priority_colors[$priority_data['priority']] ?? 'secondary';
                            ?>
                            <div class="priority-item mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-<?php echo $color; ?> priority-badge">
                                        <?php echo ucfirst($priority_data['priority']); ?> Priority
                                    </span>
                                    <span class="text-muted">
                                        <?php echo $priority_data['completed_tasks']; ?>/<?php echo $priority_data['total_tasks']; ?> completed
                                    </span>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $completion_rate; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $completion_rate; ?>% completion rate</small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Tasks -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-calendar-alt text-primary"></i> Upcoming Tasks (Next 7 Days)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_tasks)): ?>
                        <p class="text-center text-muted">No upcoming tasks in the next 7 days</p>
                    <?php else: ?>
                        <div class="upcoming-tasks-list">
                            <?php foreach ($upcoming_tasks as $task): ?>
                                <?php 
                                $priority_colors = [
                                    'high' => 'danger',
                                    'medium' => 'warning', 
                                    'low' => 'success'
                                ];
                                $priority_color = $priority_colors[$task['priority']] ?? 'secondary';
                                
                                $is_due_today = $task['days_until_due'] == 0;
                                $is_due_soon = $task['days_until_due'] <= 2 && $task['days_until_due'] >= 0;
                                ?>
                                <div class="upcoming-task-item <?php echo $is_due_today ? 'due-today' : ($is_due_soon ? 'due-soon' : ''); ?>">
                                    <div class="task-info">
                                        <h6 class="task-title"><?php echo htmlspecialchars($task['task_title']); ?></h6>
                                        <div class="task-meta">
                                            <span class="badge bg-<?php echo $priority_color; ?> me-2">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                            <small class="text-muted">
                                                Due: <?php echo $task['formatted_due_date']; ?>
                                                <?php if ($is_due_today): ?>
                                                    <span class="due-indicator text-warning">(Today)</span>
                                                <?php elseif ($task['days_until_due'] == 1): ?>
                                                    <span class="due-indicator text-warning">(Tomorrow)</span>
                                                <?php else: ?>
                                                    <span class="due-indicator">(<?php echo $task['days_until_due']; ?> days)</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Completed Tasks -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-history text-success"></i> Recent Completed Tasks</h5>
        </div>
        <div class="card-body">
            <?php if (empty($recent_completed)): ?>
                <p class="text-center text-muted">No completed tasks yet</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Priority</th>
                                <th>Completion Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_completed as $task): ?>
                                <?php 
                                $priority_colors = [
                                    'high' => 'danger',
                                    'medium' => 'warning', 
                                    'low' => 'success'
                                ];
                                $priority_color = $priority_colors[$task['priority']] ?? 'secondary';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($task['task_title']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $priority_color; ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $task['formatted_completion_date']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Performance Tips -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-lightbulb text-warning"></i> Performance Improvement Tips</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="tip-card">
                        <i class="fas fa-target text-primary"></i>
                        <h4>Focus on Priorities</h4>
                        <p>Complete high-priority tasks first to ensure critical farm operations are not delayed.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="tip-card">
                        <i class="fas fa-calendar-check text-success"></i>
                        <h4>Plan Ahead</h4>
                        <p>Review upcoming tasks regularly and plan your schedule to avoid last-minute rushes.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="tip-card">
                        <i class="fas fa-sync text-info"></i>
                        <h4>Update Status</h4>
                        <p>Keep task statuses updated so your supervisor knows your progress and can provide support.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Summary -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-chart-pie text-info"></i> My Performance Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="performance-metric">
                        <h6>Overall Completion Rate</h6>
                        <div class="metric-value"><?php echo $completion_percentage; ?>%</div>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: <?php echo $completion_percentage; ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="performance-metric">
                        <h6>Tasks This Month</h6>
                        <div class="metric-value"><?php echo $total_tasks; ?></div>
                        <small class="text-muted">Total assigned</small>
                    </div>
                </div>
            </div>
            
            <?php if ($completion_percentage >= 80): ?>
                <div class="alert alert-success mt-3">
                    <i class="fas fa-trophy"></i> <strong>Excellent Work!</strong> 
                    You're maintaining a high completion rate. Keep up the great performance!
                </div>
            <?php elseif ($completion_percentage >= 60): ?>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-thumbs-up"></i> <strong>Good Progress!</strong> 
                    You're doing well. Consider focusing on overdue tasks to improve your completion rate.
                </div>
            <?php else: ?>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> <strong>Room for Improvement</strong> 
                    Consider prioritizing pending tasks and reaching out for help if needed.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Progress tracking specific styles */
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
    border-radius: 4px;
}

.progress-bar.bg-danger {
    background-color: #e74c3c !important;
}

.progress-bar.bg-warning {
    background-color: #f39c12 !important;
}

.progress-bar.bg-success {
    background-color: #2ecc71 !important;
}

.progress-text {
    font-size: 0.8rem;
    color: #7f8c8d;
    margin-top: 4px;
    display: inline-block;
}

/* Priority items */
.priority-item {
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #dee2e6;
}

.priority-badge {
    font-size: 0.75rem;
    padding: 5px 10px;
}

/* Upcoming tasks */
.upcoming-tasks-list {
    max-height: 300px;
    overflow-y: auto;
}

.upcoming-task-item {
    padding: 12px;
    border-bottom: 1px solid #eee;
    transition: background-color 0.2s;
}

.upcoming-task-item:hover {
    background-color: #f8f9fa;
}

.upcoming-task-item:last-child {
    border-bottom: none;
}

.upcoming-task-item.due-today {
    background-color: #fff3cd;
    border-left: 4px solid #ffc107;
}

.upcoming-task-item.due-soon {
    background-color: #f8f9fa;
    border-left: 4px solid #17a2b8;
}

.task-title {
    margin-bottom: 5px;
    font-size: 14px;
    font-weight: 600;
}

.task-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
}

.due-indicator {
    font-weight: 500;
}

/* Performance metrics */
.performance-metric {
    text-align: center;
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 15px;
}

.metric-value {
    font-size: 2.5rem;
    font-weight: bold;
    color: #2c3e50;
    margin: 10px 0;
}

/* Tip cards */
.tip-card {
    padding: 15px;
    border-radius: 5px;
    background-color: #f8f9fa;
    height: 100%;
    margin-bottom: 15px;
    transition: all 0.3s ease;
    text-align: center;
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
    color: #2c3e50;
}

.tip-card p {
    color: #666;
    font-size: 14px;
    line-height: 1.5;
}

/* Summary cards */
.summary-icon.bg-purple {
    background-color: #9b59b6;
}

/* Badges */
.badge {
    font-size: 0.75rem;
    padding: 5px 10px;
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

/* Table styling */
.table th {
    border-top: none;
    font-weight: 600;
    color: #2c3e50;
}

.table td {
    vertical-align: middle;
}

/* Alert customization */
.alert {
    border: none;
    border-radius: 8px;
}

.alert i {
    margin-right: 8px;
}

/* Custom scrollbar for upcoming tasks */
.upcoming-tasks-list::-webkit-scrollbar {
    width: 6px;
}

.upcoming-tasks-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.upcoming-tasks-list::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.upcoming-tasks-list::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

/* Responsive design */
@media (max-width: 768px) {
    .metric-value {
        font-size: 2rem;
    }
    
    .tip-card {
        margin-bottom: 20px;
    }
    
    .upcoming-tasks-list {
        max-height: 250px;
    }
    
    .task-meta {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .badge.me-2 {
        margin-bottom: 5px !important;
        margin-right: 0 !important;
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
document.addEventListener('DOMContentLoaded', function() {
    // Animate progress bars on load
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 500);
    });
    
    // Add hover effects to upcoming tasks
    const upcomingTasks = document.querySelectorAll('.upcoming-task-item');
    upcomingTasks.forEach(task => {
        task.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
        });
        
        task.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
    
    // Smooth scroll for any internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>