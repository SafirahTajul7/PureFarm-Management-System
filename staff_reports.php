<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Handle date range filtering
$start_date = date('Y-m-d', strtotime('-30 days')); // Default to last 30 days
$end_date = date('Y-m-d');

if (isset($_GET['filter'])) {
    if ($_GET['filter'] === 'this_week') {
        $start_date = date('Y-m-d', strtotime('monday this week'));
    } elseif ($_GET['filter'] === 'last_week') {
        $start_date = date('Y-m-d', strtotime('monday last week'));
        $end_date = date('Y-m-d', strtotime('sunday last week'));
    } elseif ($_GET['filter'] === 'this_month') {
        $start_date = date('Y-m-d', strtotime('first day of this month'));
    } elseif ($_GET['filter'] === 'last_month') {
        $start_date = date('Y-m-d', strtotime('first day of last month'));
        $end_date = date('Y-m-d', strtotime('last day of last month'));
    } elseif ($_GET['filter'] === 'custom' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $start_date = $_GET['start_date'];
        $end_date = $_GET['end_date'];
    }
}

// Get staff summary statistics for the selected period
try {
    // Total staff in the period
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM staff 
        WHERE hire_date <= ?
    ");
    $stmt->execute([$end_date]);
    $total_staff = $stmt->fetchColumn();
    
    // Staff by status
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM staff
        GROUP BY status
    ");
    $status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Staff by role
    $stmt = $pdo->query("
        SELECT r.role_name, COUNT(s.id) as count
        FROM roles r
        LEFT JOIN staff s ON r.id = s.role_id
        GROUP BY r.role_name
        ORDER BY count DESC
    ");
    $role_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // New hires in the period
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM staff 
        WHERE hire_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $new_hires = $stmt->fetchColumn();
    
    // Departed staff in the period
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM staff 
        WHERE status = 'inactive' 
        AND updated_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $departed_staff = $stmt->fetchColumn();
    
    // Staff on leave
    $on_leave_count = isset($status_counts['on-leave']) ? $status_counts['on-leave'] : 0;
    
    // Active staff
    $active_staff = isset($status_counts['active']) ? $status_counts['active'] : 0;
    
    // Inactive staff
    $inactive_staff = isset($status_counts['inactive']) ? $status_counts['inactive'] : 0;
    
    // Staff retention rate
    $retention_rate = ($total_staff > 0) ? round((($total_staff - $departed_staff) / $total_staff) * 100) : 0;
    
} catch(PDOException $e) {
    error_log("Error fetching staff summary statistics: " . $e->getMessage());
    // Set default values in case of error
    $total_staff = 0;
    $active_staff = 0;
    $inactive_staff = 0;
    $on_leave_count = 0;
    $new_hires = 0;
    $departed_staff = 0;
    $retention_rate = 0;
    $status_counts = [];
    $role_counts = [];
}

// Get staff task performance metrics
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.id, 
            CONCAT(s.first_name, ' ', s.last_name) as staff_name,
            COUNT(t.id) as total_assigned,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN t.status = 'in progress' THEN 1 ELSE 0 END) as in_progress,
            AVG(CASE WHEN t.status = 'completed' AND t.completion_date IS NOT NULL 
                THEN DATEDIFF(t.completion_date, t.assigned_date) 
                ELSE NULL END) as avg_completion_days
        FROM staff s
        LEFT JOIN staff_tasks t ON s.id = t.staff_id AND t.assigned_date BETWEEN ? AND ?
        WHERE s.status = 'active'
        GROUP BY s.id
        ORDER BY total_assigned DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $staff_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching staff performance: " . $e->getMessage());
    $staff_performance = [];
}

// Get staff hiring breakdown by month
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(hire_date, '%Y-%m') as month,
            COUNT(*) as count
        FROM staff
        WHERE hire_date >= DATE_SUB(?, INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(hire_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$end_date]);
    $monthly_hires = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format for chart data
    $chart_labels = [];
    $hire_data = [];
    
    foreach ($monthly_hires as $month) {
        $chart_labels[] = date('M Y', strtotime($month['month'] . '-01'));
        $hire_data[] = $month['count'];
    }
    
} catch(PDOException $e) {
    error_log("Error fetching monthly hire data: " . $e->getMessage());
    $monthly_hires = [];
    $chart_labels = [];
    $hire_data = [];
}

// Get documents expiring soon
try {
    $stmt = $pdo->prepare("
        SELECT 
            d.document_title, 
            d.expiry_date,
            CONCAT(s.first_name, ' ', s.last_name) as staff_name,
            d.document_type
        FROM staff_documents d
        JOIN staff s ON d.staff_id = s.id
        WHERE d.expiry_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY)
        ORDER BY d.expiry_date ASC
    ");
    $stmt->execute();
    $expiring_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching expiring documents: " . $e->getMessage());
    $expiring_documents = [];
}

// Get staff list for detailed report
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            r.role_name,
            (SELECT COUNT(*) FROM staff_tasks WHERE staff_id = s.id AND status = 'completed') as completed_tasks,
            (SELECT COUNT(*) FROM staff_tasks WHERE staff_id = s.id AND status = 'pending') as pending_tasks,
            (SELECT COUNT(*) FROM staff_documents WHERE staff_id = s.id) as document_count
        FROM staff s
        LEFT JOIN roles r ON s.role_id = r.id
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->execute();
    $detailed_staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching detailed staff list: " . $e->getMessage());
    $detailed_staff = [];
}

$pageTitle = 'Staff Reports';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-bar"></i> Staff Reports</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="window.print();">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button class="btn btn-success" id="exportCSVBtn">
                <i class="fas fa-file-csv"></i> Export to CSV
            </button>
            <button class="btn btn-secondary" onclick="location.href='staff_management.php'">
                <i class="fas fa-users"></i> Staff Management
            </button>
        </div>
    </div>
    
    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> Report Filters</h5>
        </div>
        <div class="card-body">
            <form action="staff_reports.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="filter" class="form-label">Predefined Ranges</label>
                    <select class="form-select" id="filter" name="filter" onchange="handleFilterChange(this.value)">
                        <option value="last_30" <?php echo (!isset($_GET['filter']) || $_GET['filter'] === 'last_30') ? 'selected' : ''; ?>>Last 30 days</option>
                        <option value="this_week" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'this_week') ? 'selected' : ''; ?>>This Week</option>
                        <option value="last_week" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'last_week') ? 'selected' : ''; ?>>Last Week</option>
                        <option value="this_month" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'this_month') ? 'selected' : ''; ?>>This Month</option>
                        <option value="last_month" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'last_month') ? 'selected' : ''; ?>>Last Month</option>
                        <option value="custom" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                
                <div class="col-md-3 custom-date-range" style="<?php echo (isset($_GET['filter']) && $_GET['filter'] === 'custom') ? '' : 'display: none;'; ?>">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="col-md-3 custom-date-range" style="<?php echo (isset($_GET['filter']) && $_GET['filter'] === 'custom') ? '' : 'display: none;'; ?>">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report period info -->
    <div class="report-period-info mb-4 text-center">
        <h4>Report Period: <?php echo date('F d, Y', strtotime($start_date)); ?> to <?php echo date('F d, Y', strtotime($end_date)); ?></h4>
    </div>

    <!-- Chart Section -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line"></i> Staff Hiring Trends (Last 12 Months)</h5>
                </div>
                <div class="card-body">
                    <canvas id="staffHiringChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie"></i> Staff Status Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="staffStatusChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performing Staff Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-user-check"></i> Top Performing Staff</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered">                    <thead class="table-light">
                        <tr>
                            <th>Staff Member</th>
                            <th>Total Tasks</th>
                            <th>Completed</th>
                            <th>In Progress</th>
                            <th>Pending</th>
                            <th>Completion Rate</th>
                        </tr>
                    </thead>
                    <tbody>                        <?php if (empty($staff_performance)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No staff performance data available for the selected period</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staff_performance as $staff): ?>
                                <?php 
                                // Only include staff with assigned tasks
                                if ($staff['total_assigned'] == 0) continue;
                                  // Calculate completion rate
                                $staff_completion_rate = ($staff['total_assigned'] > 0) ? 
                                    round(($staff['completed'] / $staff['total_assigned']) * 100) : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($staff['staff_name']); ?></td>
                                    <td><?php echo $staff['total_assigned']; ?></td>
                                    <td><?php echo $staff['completed']; ?></td>
                                    <td><?php echo $staff['in_progress']; ?></td>
                                    <td><?php echo $staff['pending']; ?></td>
                                    <td>
                                        <div class="progress-bar-container" style="width: 100px; display: inline-block; margin-right: 10px;">
                                            <div class="progress-bar" style="width: <?php echo $staff_completion_rate; ?>%;"></div>                                        </div>
                                        <?php echo $staff_completion_rate; ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Documents Expiring Soon -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-exclamation-circle text-warning"></i> Documents Expiring Soon</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Document Title</th>
                            <th>Staff Member</th>
                            <th>Document Type</th>
                            <th>Expiry Date</th>
                            <th>Days Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expiring_documents)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No documents expiring soon</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expiring_documents as $doc): ?>
                                <?php 
                                $today = new DateTime();
                                $expiry = new DateTime($doc['expiry_date']);
                                $days_remaining = $expiry->diff($today)->days;
                                $alert_class = ($days_remaining <= 30) ? 'table-danger' : (($days_remaining <= 60) ? 'table-warning' : '');
                                ?>
                                <tr class="<?php echo $alert_class; ?>">
                                    <td><?php echo htmlspecialchars($doc['document_title']); ?></td>
                                    <td><?php echo htmlspecialchars($doc['staff_name']); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($doc['expiry_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($days_remaining <= 30) ? 'danger' : (($days_remaining <= 60) ? 'warning' : 'info'); ?>">
                                            <?php echo $days_remaining; ?> days
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Detailed Staff Report -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-list-alt"></i> Detailed Staff Report</h5>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="showInactiveStaffSwitch">
                <label class="form-check-label" for="showInactiveStaffSwitch">Show Inactive Staff</label>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="detailedStaffTable">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Contact</th>
                            <th>Hire Date</th>
                            <th>Status</th>
                            <th>Tasks</th>
                            <th>Documents</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($detailed_staff)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No staff data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($detailed_staff as $staff): ?>
                                <?php 
                                // Determine status class
                                $status_class = "";
                                switch ($staff['status']) {
                                    case 'active':
                                        $status_class = "success";
                                        break;
                                    case 'inactive':
                                        $status_class = "danger";
                                        break;
                                    case 'on-leave':
                                        $status_class = "warning";
                                        break;
                                }
                                ?>
                                <tr class="staff-row <?php echo $staff['status'] === 'inactive' ? 'inactive-staff' : ''; ?>" style="<?php echo $staff['status'] === 'inactive' ? 'display: none;' : ''; ?>">
                                    <td><?php echo htmlspecialchars($staff['staff_id']); ?></td>
                                    <td>
                                        <a href="view_staff.php?id=<?php echo $staff['id']; ?>" class="staff-detail-link">
                                            <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($staff['role_name'] ?? 'Not Assigned'); ?></td>
                                    <td>
                                        <div><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($staff['email']); ?></div>
                                        <div><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($staff['phone']); ?></div>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($staff['hire_date'])); ?></td>
                                    <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo ucfirst($staff['status']); ?></span></td>
                                    <td>
                                        <div>Completed: <?php echo $staff['completed_tasks'] ?? 0; ?></div>
                                        <div>Pending: <?php echo $staff['pending_tasks'] ?? 0; ?></div>
                                    </td>
                                    <td><?php echo $staff['document_count'] ?? 0; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Report Insights Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-lightbulb text-warning"></i> Staffing Insights</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="insight-card">
                        <i class="fas fa-chart-line text-primary"></i>
                        <h4>Staffing Trends</h4>
                        <p>
                            <?php if ($total_staff > 0): ?>
                                Your farm currently has <?php echo $total_staff; ?> staff members with a retention rate of <?php echo $retention_rate; ?>%.
                                <?php if ($new_hires > 0): ?>
                                    There have been <?php echo $new_hires; ?> new hires in the selected period.
                                <?php endif; ?>
                                <?php if ($retention_rate >= 90): ?>
                                    Excellent staff retention!
                                <?php elseif ($retention_rate >= 75): ?>
                                    Good staff retention, but there's room for improvement.
                                <?php else: ?>
                                    Staff retention requires attention.
                                <?php endif; ?>
                            <?php else: ?>
                                No staff records available for analysis.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="insight-card">
                        <i class="fas fa-exclamation-circle text-danger"></i>
                        <h4>Areas for Attention</h4>
                        <p>
                            <?php if ($on_leave_count > 0): ?>
                                Currently <?php echo $on_leave_count; ?> staff members are on leave.
                                <?php if ($on_leave_count > $total_staff * 0.1): ?>
                                    This is above 10% of your workforce, consider adjusting workloads accordingly.
                                <?php endif; ?>
                            <?php else: ?>
                                No staff currently on leave.
                            <?php endif; ?>
                            
                            <?php if (count($expiring_documents) > 0): ?>
                                There are <?php echo count($expiring_documents); ?> staff documents expiring soon that require attention.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="insight-card">
                        <i class="fas fa-trophy text-success"></i>
                        <h4>Recommendations</h4>
                        <p>
                            <?php if ($total_staff > 0): ?>
                                <?php if (!empty($staff_performance)): ?>
                                    Consider recognizing top performing staff members who consistently complete tasks efficiently.
                                <?php endif; ?>
                                
                                <?php if ($departed_staff > 0 && $departed_staff > $new_hires): ?>
                                    Staff departures exceed new hires. Consider implementing retention strategies or staff interviews to understand turnover causes.
                                <?php endif; ?>
                            <?php else: ?>
                                No staff data available for recommendations.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Staff Reports specific styles */
.progress-bar-container {
    width: 100px;
    background-color: #f0f0f0;
    height: 12px;
    border-radius: 6px;
    overflow: hidden;
    display: inline-block;
    margin-right: 10px;
    vertical-align: middle;
}

.progress-bar {
    height: 100%;
    background-color: #2ecc71;
    transition: width 0.5s ease-in-out;
}

.insight-card {
    padding: 20px;
    border-radius: 5px;
    background-color: #f8f9fa;
    height: 100%;
    margin-bottom: 15px;
    transition: all 0.3s ease;
    border-left: 4px solid #3498db;
}

.insight-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.insight-card i {
    font-size: 28px;
    margin-bottom: 10px;
}

.insight-card h4 {
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

.report-period-info {
    background-color: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.staff-detail-link {
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
}

.staff-detail-link:hover {
    text-decoration: underline;
}

.main-content {
    padding-bottom: 60px; /* Add space for footer */
    min-height: calc(100vh - 60px); /* Ensure content takes up full height minus footer */
}

/* Print styles */
@media print {
    .action-buttons, .custom-date-range, .form-check, .modal, footer {
        display: none !important;
    }
    
    .card {
        border: 1px solid #dee2e6;
        margin-bottom: 20px;
        break-inside: avoid;
    }
    
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        padding: 10px 15px;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
    }
    
    th, td {
        border: 1px solid #dee2e6;
        padding: 8px;
        text-align: left;
    }
    
    .badge {
        color: #000;
        font-weight: bold;
        background: none !important;
    }
    
    .progress-bar-container {
        display: none;
    }
    
    .summary-grid {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    
    .summary-card {
        width: 22%;
        padding: 15px;
        border: 1px solid #dee2e6;
        break-inside: avoid;
    }
    
    .summary-icon {
        display: none;
    }
    
    /* Hide charts as they don't print well */
    #staffHiringChart, #staffStatusChart, #roleDistributionChart {
        display: none;
    }
    
    /* Hide non-print areas */
    .page-header button, .card-header button, .form-check {
        display: none;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {    // Handle filter change
    window.handleFilterChange = function(value) {
        const customDateFields = document.querySelectorAll('.custom-date-range');
        const filterForm = document.querySelector('form[action="staff_reports.php"]');
        
        if (value === 'custom') {
            customDateFields.forEach(field => {
                field.style.display = 'block';
            });
            // Don't submit the form yet for custom, as we need the date inputs
        } else {
            customDateFields.forEach(field => {
                field.style.display = 'none';
            });
            // Auto-submit the form for predefined date ranges
            filterForm.submit();
        }
    };

    // Toggle inactive staff visibility
    document.getElementById('showInactiveStaffSwitch').addEventListener('change', function() {
        const inactiveStaff = document.querySelectorAll('.inactive-staff');
        
        inactiveStaff.forEach(staff => {
            staff.style.display = this.checked ? 'table-row' : 'none';
        });
    });
    
    // Export to CSV functionality
    document.getElementById('exportCSVBtn').addEventListener('click', function() {
        exportTableToCSV('staff-detailed-report.csv');
    });
    
    function exportTableToCSV(filename) {
        const table = document.getElementById('detailedStaffTable');
        const rows = table.querySelectorAll('tr');
        const csvContent = [];
        
        // Get headers
        const headers = [];
        const headerCells = rows[0].querySelectorAll('th');
        headerCells.forEach(cell => {
            headers.push('"' + cell.textContent.trim() + '"');
        });
        csvContent.push(headers.join(','));
        
        // Get data rows
        for (let i = 1; i < rows.length; i++) {
            // Skip hidden rows
            if (rows[i].style.display === 'none') continue;
            
            const row = rows[i];
            const rowData = [];
            const cells = row.querySelectorAll('td');
            
            cells.forEach(cell => {
                // Get text content only, not HTML
                let content = cell.textContent.trim();
                
                // For cells with badges, get the text directly
                const badge = cell.querySelector('.badge');
                if (badge) {
                    content = badge.textContent.trim();
                }
                
                // Escape any quotes in the content
                content = content.replace(/"/g, '""');
                rowData.push('"' + content + '"');
            });
            
            csvContent.push(rowData.join(','));
        }
        
        // Create CSV file and download
        const csv = csvContent.join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        
        const link = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    // Initialize charts
    if (document.getElementById('staffStatusChart')) {
        const statusCtx = document.getElementById('staffStatusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'On Leave', 'Inactive'],
                datasets: [{
                    data: [
                        <?php echo isset($status_counts['active']) ? $status_counts['active'] : 0; ?>, 
                        <?php echo isset($status_counts['on-leave']) ? $status_counts['on-leave'] : 0; ?>, 
                        <?php echo isset($status_counts['inactive']) ? $status_counts['inactive'] : 0; ?>
                    ],
                    backgroundColor: [
                        '#2ecc71', // green
                        '#f39c12', // orange
                        '#e74c3c'  // red
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    if (document.getElementById('staffHiringChart')) {
        const hiringCtx = document.getElementById('staffHiringChart').getContext('2d');
        const hiringChart = new Chart(hiringCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'New Hires',
                    data: <?php echo json_encode($hire_data); ?>,
                    backgroundColor: '#3498db',
                    borderColor: '#2980b9',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    
    if (document.getElementById('roleDistributionChart')) {
        const roleCtx = document.getElementById('roleDistributionChart').getContext('2d');
        
        // Extract role data from PHP
        const roleLabels = [];
        const roleCounts = [];
        const roleColors = [
            '#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6', 
            '#1abc9c', '#d35400', '#34495e', '#16a085', '#27ae60'
        ];
        
        <?php 
        $colorIndex = 0;
        foreach ($role_counts as $role => $count) {
            echo "roleLabels.push('".addslashes($role)."');\n";
            echo "roleCounts.push(".$count.");\n";
            $colorIndex++;
        }
        ?>
        
        const roleChart = new Chart(roleCtx, {
            type: 'horizontalBar',
            data: {
                labels: roleLabels,
                datasets: [{
                    label: 'Staff Count',
                    data: roleCounts,
                    backgroundColor: roleColors.slice(0, roleLabels.length),
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>