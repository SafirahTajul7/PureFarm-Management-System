<?php
require_once 'includes/auth.php';
auth()->checkSupervisor();

require_once 'includes/db.php';

// Fetch real-time summary statistics from staff table (read-only for supervisor)
try {
    // Total active staff under supervision
    $total_staff = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn();
} catch(PDOException $e) {
    error_log("Error counting active staff: " . $e->getMessage());
    $total_staff = 0;
}

try {
    // My assigned tasks
    $user_id = auth()->getUserId();
    $my_tasks = $pdo->prepare("SELECT COUNT(*) FROM staff_tasks WHERE staff_id = ? AND status = 'pending'");
    $my_tasks->execute([$user_id]);
    $pending_tasks = $my_tasks->fetchColumn();
} catch(PDOException $e) {
    error_log("Error counting my tasks: " . $e->getMessage());
    $pending_tasks = 0;
}

try {
    // Tasks I've completed
    $user_id = auth()->getUserId();
    $completed_tasks_stmt = $pdo->prepare("SELECT COUNT(*) FROM staff_tasks WHERE staff_id = ? AND status = 'completed'");
    $completed_tasks_stmt->execute([$user_id]);
    $completed_tasks = $completed_tasks_stmt->fetchColumn();
} catch(PDOException $e) {
    error_log("Error counting completed tasks: " . $e->getMessage());
    $completed_tasks = 0;
}

try {
    // Team members on duty (if supervisor has team members)
    $team_on_duty = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn();
} catch(PDOException $e) {
    error_log("Error counting team on duty: " . $e->getMessage());
    $team_on_duty = 0;
}

$pageTitle = 'Staff & Task Management';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-users-cog"></i> Staff & Task Management</h2>
        <div class="action-buttons">
            <button class="btn btn-success" onclick="location.href='my_tasks.php'">
                <i class="fas fa-clipboard-list"></i> My Tasks
            </button>
        </div>
    </div>

    <!-- Summary Cards for Supervisor -->
    <div class="summary-cards mb-4">
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="summary-card bg-teal">
                    <div class="card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo isset($total_staff) ? $total_staff : 0; ?></h3>
                        <p>Team Members</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="summary-card bg-blue">
                    <div class="card-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo isset($pending_tasks) ? $pending_tasks : 0; ?></h3>
                        <p>My Pending Tasks</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="summary-card bg-orange">
                    <div class="card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo isset($completed_tasks) ? $completed_tasks : 0; ?></h3>
                        <p>Tasks Completed</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="summary-card bg-purple">
                    <div class="card-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo isset($team_on_duty) ? $team_on_duty : 0; ?></h3>
                        <p>Team On Duty</p>
                    </div>
                </div>
            </div>
        </div>
    </div>    <!-- Combined Tasks & Team Management -->
    <div class="features-grid-1col">
        <!-- Combined Tasks & Team Section - Teal Theme -->
        <div class="feature-card task-team-management">
            <h3><i class="fas fa-clipboard-users"></i> Tasks & Team Management</h3>
            <div class="menu-grid">
                <div class="menu-section">
                    <h4><i class="fas fa-clipboard-check"></i> My Tasks</h4>
                    <ul>
                        <li onclick="location.href='my_tasks.php'">
                            <div class="menu-item">
                                <i class="fas fa-list-ul"></i>
                                <div class="menu-content">
                                    <span class="menu-title">View My Tasks</span>
                                    <span class="menu-desc">View and update my assigned tasks</span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
                
                <div class="menu-section">
                    <h4><i class="fas fa-users"></i> Team Information</h4>
                    <ul>
                        <li onclick="location.href='team_directory.php'">
                            <div class="menu-item">
                                <i class="fas fa-address-book"></i>
                                <div class="menu-content">
                                    <span class="menu-title">Team Directory</span>
                                    <span class="menu-desc">View team member information</span>
                                </div>
                            </div>
                        </li>
                        <li onclick="location.href='team_schedule.php'">
                            <div class="menu-item">
                                <i class="fas fa-calendar-alt"></i>
                                <div class="menu-content">
                                    <span class="menu-title">Team Schedule</span>
                                    <span class="menu-desc">View team work schedules</span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Performance Dashboard -->
    <div class="section-header">
        <h3><i class="fas fa-tachometer-alt"></i> My Task Performance</h3>
    </div>

    <div class="current-conditions">
        <div class="conditions-chart">
            <!-- Chart container -->
            <div id="conditions-chart-container" style="height: 300px; background: #f8f9fa; border-radius: 8px; padding: 15px;">
                <canvas id="task-chart"></canvas>
            </div>
        </div>
        
        <div class="conditions-grid">
            <!-- Task status cards -->
            <div class="condition-card">
                <div class="condition-title">Today's Tasks <small>(My Assignment)</small></div>
                <div class="condition-data">
                    <div class="condition-reading">
                        <i class="fas fa-clipboard-list"></i>
                        <span>5 Total</span>
                    </div>
                    <div class="condition-reading">
                        <i class="fas fa-check-circle"></i>
                        <span>3 Done</span>
                    </div>
                    <div class="condition-reading">
                        <i class="fas fa-clock"></i>
                        <span>2 Pending</span>
                    </div>
                </div>
                <div class="condition-updated">Last updated: Today, <?php echo date('g:i A', strtotime('-1 hour')); ?></div>
            </div>

            <div class="condition-card">
                <div class="condition-title">This Week <small>(Progress)</small></div>
                <div class="condition-data">
                    <div class="condition-reading">
                        <i class="fas fa-tasks"></i>
                        <span>15 Total</span>
                    </div>
                    <div class="condition-reading">
                        <i class="fas fa-chart-line"></i>
                        <span>80%</span>
                    </div>
                    <div class="condition-reading">
                        <i class="fas fa-trophy"></i>
                        <span>Good</span>
                    </div>
                </div>
                <div class="condition-updated">Performance: Above average</div>
            </div>

            <div class="condition-card needs-attention">
                <div class="condition-title">Overdue Tasks <small>(Alert)</small></div>
                <div class="condition-data">
                    <div class="condition-reading">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>1 Task</span>
                    </div>
                    <div class="condition-reading alert">
                        <i class="fas fa-calendar-times"></i>
                        <span>2 Days</span>
                    </div>
                    <div class="condition-reading alert">
                        <i class="fas fa-bell"></i>
                        <span>High</span>
                    </div>
                </div>
                <div class="condition-updated">Due: 2 days ago</div>
                <div class="condition-alert">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Requires immediate attention
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styles for supervisor staff page - using environmental page design */
.main-content {
    padding-bottom: 60px;
    min-height: calc(100vh - 60px);
}

/* Summary Cards */
.summary-cards .summary-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 20px;
    display: flex;
    align-items: center;
    color: white;
    transition: transform 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-3px);
}

.summary-card .card-icon {
    font-size: 36px;
    margin-right: 15px;
}

.summary-card .card-info h3 {
    font-size: 28px;
    margin: 0;
    font-weight: 600;
}

.summary-card .card-info p {
    margin: 0;
    opacity: 0.9;
}

.bg-teal { background: linear-gradient(135deg, #20c997, #17a085) !important; }
.bg-purple { background: linear-gradient(135deg, #6f42c1, #5a32a3) !important; }
.bg-orange { background: linear-gradient(135deg, #fd7e14, #e8590c) !important; }
.bg-blue { background: linear-gradient(135deg, #0d6efd, #0a58ca) !important; }

/* Features Grid - 1 column for combined section */
.features-grid-1col {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

/* Combined Task & Team Card */
.task-team-management { 
    border-top: 4px solid #20c997; 
}

.task-team-management h3 i { 
    color: #20c997; 
}

.task-team-management:hover { 
    box-shadow: 0 6px 12px rgba(32, 201, 151, 0.2); 
}

.task-team-management .menu-item:hover { 
    background: #20c997; 
}

/* Menu Grid for Combined Section */
.menu-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

@media (max-width: 768px) {
    .menu-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

.menu-section h4 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eaeaea;
    color: #333;
}

.menu-section h4 i {
    margin-right: 8px;
    color: #20c997;
}

/* Feature Cards Base Styles */
.feature-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px;
    transition: all 0.3s ease;
}

.feature-card:hover {
    transform: translateY(-3px);
}

.feature-card h3 {
    margin-bottom: 15px;
    font-size: 18px;
    font-weight: 600;
    border-bottom: 1px solid #eaeaea;
    padding-bottom: 10px;
}

.feature-card ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.feature-card li {
    margin-bottom: 10px;
    border-radius: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.feature-card li:hover {
    transform: translateX(5px);
}

.menu-item {
    display: flex;
    align-items: center;
    padding: 12px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.menu-item:hover {
    color: white;
}

.menu-item i {
    font-size: 16px;
    margin-right: 12px;
    width: 20px;
}

.menu-content {
    flex: 1;
}

.menu-title {
    display: block;
    font-weight: 500;
    margin-bottom: 2px;
}

.menu-desc {
    font-size: 12px;
    opacity: 0.8;
}

/* Current Conditions Dashboard */
.section-header {
    margin: 20px 0;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.current-conditions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.conditions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.condition-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    padding: 15px;
    transition: all 0.3s ease;
}

.condition-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.condition-title {
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 10px;
    color: #333;
}

.condition-data {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.condition-reading {
    text-align: center;
    padding: 5px;
}

.condition-reading i {
    display: block;
    font-size: 18px;
    margin-bottom: 5px;
    color: #6c757d;
}

.condition-reading span {
    font-size: 16px;
    font-weight: 500;
}

.condition-updated {
    font-size: 12px;
    color: #6c757d;
    text-align: right;
}

.needs-attention {
    border-left: 3px solid #dc3545;
}

.condition-alert {
    color: #dc3545;
    font-size: 13px;
    margin-top: 8px;
    font-weight: 500;
}

.alert i, .alert span {
    color: #dc3545 !important;
}



/* Table Status Badges */
.badge {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 500;
    border-radius: 0.375rem;
}

.bg-success { background-color: #198754 !important; color: white; }
.bg-primary { background-color: #0d6efd !important; color: white; }
.bg-danger { background-color: #dc3545 !important; color: white; }
.bg-warning { background-color: #ffc107 !important; color: black; }

@media (min-width: 992px) {
    .current-conditions {
        grid-template-columns: 2fr 3fr;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .conditions-grid {
        grid-template-columns: 1fr;
    }
    
    .condition-data {
        flex-direction: column;
        gap: 10px;
    }
    
    .condition-reading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-align: left;
    }
    
    .condition-reading i {
        margin-bottom: 0;
        margin-right: 10px;
    }
}

body {
    padding-bottom: 60px;
}
</style>

<?php include 'includes/footer.php'; ?>

<!-- Add Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prepare the chart container
    const chartContainer = document.getElementById('conditions-chart-container');
    
    // Add a canvas element inside the container
    chartContainer.innerHTML = '<canvas id="task-chart"></canvas>';

    // Sample task completion data for the last 7 days
    const dates = ['Apr 18', 'Apr 19', 'Apr 20', 'Apr 21', 'Apr 22', 'Apr 23', 'Apr 24'];
    const tasksAssigned = [3, 4, 2, 5, 3, 2, 4];
    const tasksCompleted = [3, 3, 2, 4, 3, 2, 3];

    console.log("Chart data:", { 
        dates: dates, 
        tasksAssigned: tasksAssigned, 
        tasksCompleted: tasksCompleted 
    });
    
    const ctx = document.getElementById('task-chart').getContext('2d');
    
    // Create task chart
    const taskChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [
                {
                    label: 'Tasks Assigned',
                    data: tasksAssigned,
                    borderColor: '#fd7e14',
                    backgroundColor: 'rgba(253, 126, 20, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    yAxisID: 'y'
                },
                {
                    label: 'Tasks Completed',
                    data: tasksCompleted,
                    borderColor: '#20c997',
                    backgroundColor: 'rgba(32, 201, 151, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    yAxisID: 'y'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                title: {
                    display: true,
                    text: 'My Task Completion Progress (Last 7 Days)',
                    font: {
                        size: 16
                    }
                },
                legend: {
                    position: 'top',
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Number of Tasks'
                    },
                    min: 0,
                    suggestedMax: 8,
                    grid: {
                        drawOnChartArea: true
                    }
                }
            }
        }
    });
});

// Function to refresh task data
function refreshTaskData() {
    // Show loading state
    const conditionsGrid = document.querySelector('.conditions-grid');
    if (conditionsGrid) {
        conditionsGrid.style.opacity = '0.6';
        conditionsGrid.style.pointerEvents = 'none';
    }
    
    // Simulate data refresh
    setTimeout(() => {
        location.reload();
    }, 1500);
}

// Function to mark alert as resolved
function resolveAlert(alertElement) {
    alertElement.style.opacity = '0.5';
    alertElement.style.textDecoration = 'line-through';
    
    // You can add AJAX call here to update the database
    setTimeout(() => {
        alertElement.remove();
    }, 2000);
}

// Auto-refresh functionality for real-time updates (optional)
let autoRefreshInterval;

function startAutoRefresh() {
    autoRefreshInterval = setInterval(() => {
        // Refresh task data
        refreshTaskData();
    }, 300000); // Refresh every 5 minutes
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

// Initialize auto-refresh on page load (uncomment if needed)
// startAutoRefresh();

// Stop auto-refresh when page is about to unload
window.addEventListener('beforeunload', stopAutoRefresh);
</script>