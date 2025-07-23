<?php
require_once 'includes/auth.php';
auth()->checkSupervisor(); // SUPERVISOR ACCESS ONLY

require_once 'includes/db.php';

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$severity_filter = $_GET['severity'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build the query
$supervisor_id = $_SESSION['user_id'];
$where_conditions = ["ei.supervisor_id = ?"];
$params = [$supervisor_id];

if (!empty($status_filter)) {
    $where_conditions[] = "ei.status = ?";
    $params[] = $status_filter;
}

if (!empty($severity_filter)) {
    $where_conditions[] = "ei.severity = ?";
    $params[] = $severity_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "ei.issue_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "ei.issue_date <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $where_conditions[] = "(ei.issue_type LIKE ? OR ei.description LIKE ? OR f.field_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get paginated results
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    // Count total records
    $count_sql = "
        SELECT COUNT(*) as total
        FROM environmental_issues ei
        LEFT JOIN fields f ON ei.field_id = f.id
        WHERE $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $per_page);

    // Get the records
    $sql = "
        SELECT 
            ei.*,
            f.field_name,
            f.location as field_location,
            s.first_name,
            s.last_name
        FROM environmental_issues ei
        LEFT JOIN fields f ON ei.field_id = f.id
        LEFT JOIN staff s ON ei.supervisor_id = s.id
        WHERE $where_clause
        ORDER BY ei.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get statistics
    $stats_sql = "
        SELECT 
            COUNT(*) as total_issues,
            SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) as open_issues,
            SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_issues,
            SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved_issues,
            SUM(CASE WHEN severity = 'Critical' THEN 1 ELSE 0 END) as critical_issues,
            SUM(CASE WHEN severity = 'High' THEN 1 ELSE 0 END) as high_issues
        FROM environmental_issues ei
        WHERE ei.supervisor_id = ?
    ";
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute([$supervisor_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $error_message = "Error fetching environmental issues: " . $e->getMessage();
    $issues = [];
    $stats = ['total_issues' => 0, 'open_issues' => 0, 'in_progress_issues' => 0, 'resolved_issues' => 0, 'critical_issues' => 0, 'high_issues' => 0];
    $total_pages = 0;
}

$pageTitle = 'My Environmental Issue Reports';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-clipboard-list"></i> My Environmental Issue Reports</h2>
        <div class="action-buttons">
            <button class="btn btn-danger" onclick="location.href='environmental_issues.php'">
                <i class="fas fa-plus"></i> Report New Issue
            </button>
            <button class="btn btn-info" onclick="exportReports()">
                <i class="fas fa-download"></i> Export Reports
            </button>
            <button class="btn btn-secondary" onclick="location.href='supervisor_environmental.php'">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <!-- Statistics Dashboard -->
    <div class="stats-dashboard">
        <div class="row">
            <div class="col-md-2">
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $stats['total_issues']; ?></div>
                        <div class="stat-label">Total Issues</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card open">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $stats['open_issues']; ?></div>
                        <div class="stat-label">Open</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card progress">
                    <div class="stat-icon">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $stats['in_progress_issues']; ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card resolved">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $stats['resolved_issues']; ?></div>
                        <div class="stat-label">Resolved</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card critical">
                    <div class="stat-icon">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $stats['critical_issues']; ?></div>
                        <div class="stat-label">Critical</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card high">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo $stats['high_issues']; ?></div>
                        <div class="stat-label">High Priority</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-container">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> Filter & Search</h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">
                    <i class="fas fa-times"></i> Clear Filters
                </button>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-form">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="Open" <?php echo $status_filter === 'Open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="In Progress" <?php echo $status_filter === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Resolved" <?php echo $status_filter === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="Closed" <?php echo $status_filter === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="severity">Severity</label>
                                <select name="severity" id="severity" class="form-control">
                                    <option value="">All Severity</option>
                                    <option value="Critical" <?php echo $severity_filter === 'Critical' ? 'selected' : ''; ?>>Critical</option>
                                    <option value="High" <?php echo $severity_filter === 'High' ? 'selected' : ''; ?>>High</option>
                                    <option value="Medium" <?php echo $severity_filter === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="Low" <?php echo $severity_filter === 'Low' ? 'selected' : ''; ?>>Low</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="date_from">Date From</label>
                                <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label for="date_to">Date To</label>
                                <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="search">Search</label>
                                <input type="text" name="search" id="search" class="form-control" 
                                       placeholder="Search by issue type, description, field..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Issues List -->
    <div class="issues-container">
        <?php if (empty($issues)): ?>
        <div class="no-data">
            <div class="no-data-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="no-data-text">
                <h3>No Environmental Issues Found</h3>
                <p>
                    <?php if (!empty($search) || !empty($status_filter) || !empty($severity_filter)): ?>
                        No issues match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                        You haven't reported any environmental issues yet.
                    <?php endif; ?>
                </p>
                <a href="report_environmental_issues.php" class="btn btn-danger">
                    <i class="fas fa-plus"></i> Report Your First Issue
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-list"></i> Environmental Issues 
                    <span class="badge badge-secondary"><?php echo $total_records; ?> total</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Issue #</th>
                                <th>Type & Severity</th>
                                <th>Field Location</th>
                                <th>Date Reported</th>
                                <th>Status</th>
                                <th>Impact</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($issues as $issue): ?>
                            <tr class="issue-row" data-issue-id="<?php echo $issue['id']; ?>">
                                <td>
                                    <div class="issue-id">
                                        <strong>#<?php echo str_pad($issue['id'], 4, '0', STR_PAD_LEFT); ?></strong>
                                        <div class="issue-time">
                                            <?php echo date('H:i', strtotime($issue['issue_time'])); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="issue-type-severity">
                                        <div class="issue-type">
                                            <i class="fas fa-tag"></i>
                                            <?php echo htmlspecialchars($issue['issue_type']); ?>
                                        </div>
                                        <div class="issue-severity">
                                            <span class="severity-badge severity-<?php echo strtolower($issue['severity']); ?>">
                                                <?php echo $issue['severity']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="field-info">
                                        <div class="field-name">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($issue['field_name']); ?>
                                        </div>
                                        <?php if ($issue['field_location']): ?>
                                        <div class="field-location">
                                            <?php echo htmlspecialchars($issue['field_location']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($issue['affected_area']): ?>
                                        <div class="affected-area">
                                            <small>Area: <?php echo htmlspecialchars($issue['affected_area']); ?></small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="date-info">
                                        <div class="issue-date">
                                            <?php echo date('M d, Y', strtotime($issue['issue_date'])); ?>
                                        </div>
                                        <div class="created-date">
                                            <small class="text-muted">
                                                Reported: <?php echo date('M d, H:i', strtotime($issue['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="status-info">
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $issue['status'])); ?>">
                                            <?php echo $issue['status']; ?>
                                        </span>
                                        <?php if ($issue['admin_notification']): ?>
                                        <div class="notification-indicator">
                                            <i class="fas fa-bell text-info" title="Admin Notified"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="impact-info">
                                        <?php if ($issue['estimated_impact']): ?>
                                        <div class="impact-preview" data-toggle="tooltip" 
                                             title="<?php echo htmlspecialchars($issue['estimated_impact']); ?>">
                                            <?php echo htmlspecialchars(substr($issue['estimated_impact'], 0, 50)); ?>
                                            <?php echo strlen($issue['estimated_impact']) > 50 ? '...' : ''; ?>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewIssueDetails(<?php echo $issue['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($issue['status'] === 'Open' || $issue['status'] === 'In Progress'): ?>
                                        <button class="btn btn-sm btn-outline-warning" onclick="updateIssue(<?php echo $issue['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                    type="button" data-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="#" onclick="printIssue(<?php echo $issue['id']; ?>)">
                                                    <i class="fas fa-print"></i> Print Report
                                                </a>
                                                <a class="dropdown-item" href="#" onclick="duplicateIssue(<?php echo $issue['id']; ?>)">
                                                    <i class="fas fa-copy"></i> Duplicate Issue
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="#" onclick="shareIssue(<?php echo $issue['id']; ?>)">
                                                    <i class="fas fa-share"></i> Share Report
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <nav aria-label="Environmental Issues Pagination">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Issue Details Modal -->
<div class="modal fade" id="issueDetailsModal" tabindex="-1" role="dialog" aria-labelledby="issueDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="issueDetailsModalLabel">
                    <i class="fas fa-clipboard-list"></i> Environmental Issue Details
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="issueDetailsContent">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printCurrentIssue()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentIssueId = null;

// View issue details
function viewIssueDetails(issueId) {
    currentIssueId = issueId;
    
    // Show loading state
    document.getElementById('issueDetailsContent').innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-3">Loading issue details...</p>
        </div>
    `;
    
    $('#issueDetailsModal').modal('show');
    
    // Fetch issue details via AJAX
    fetch(`get_environmental_issue_details.php?id=${issueId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayIssueDetails(data.issue);
            } else {
                document.getElementById('issueDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Error loading issue details: ${data.error}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('issueDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Error loading issue details. Please try again.
                </div>
            `;
        });
}

// Display issue details in modal
function displayIssueDetails(issue) {
    const content = `
        <div class="issue-details">
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Issue ID:</label>
                        <span class="detail-value">#${String(issue.id).padStart(4, '0')}</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Status:</label>
                        <span class="status-badge status-${issue.status.toLowerCase().replace(' ', '-')}">${issue.status}</span>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Issue Type:</label>
                        <span class="detail-value">${issue.issue_type}</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Severity:</label>
                        <span class="severity-badge severity-${issue.severity.toLowerCase()}">${issue.severity}</span>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Field:</label>
                        <span class="detail-value">${issue.field_name || 'N/A'}</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Affected Area:</label>
                        <span class="detail-value">${issue.affected_area || 'Not specified'}</span>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Issue Date & Time:</label>
                        <span class="detail-value">${formatDateTime(issue.issue_date, issue.issue_time)}</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Weather Conditions:</label>
                        <span class="detail-value">${issue.weather_conditions || 'Not recorded'}</span>
                    </div>
                </div>
            </div>

            <div class="detail-group">
                <label>Description:</label>
                <div class="detail-description">${issue.description}</div>
            </div>

            ${issue.estimated_impact ? `
            <div class="detail-group">
                <label>Estimated Impact:</label>
                <div class="detail-description">${issue.estimated_impact}</div>
            </div>
            ` : ''}

            ${issue.immediate_action ? `
            <div class="detail-group">
                <label>Immediate Actions Taken:</label>
                <div class="detail-description">${issue.immediate_action}</div>
            </div>
            ` : ''}

            ${issue.photos ? `
            <div class="detail-group">
                <label>Photo References:</label>
                <div class="detail-description">${issue.photos}</div>
            </div>
            ` : ''}

            <div class="row">
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Reported On:</label>
                        <span class="detail-value">${formatDateTime(issue.created_at)}</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-group">
                        <label>Admin Notification:</label>
                        <span class="detail-value">
                            ${issue.admin_notification ? 
                                '<i class="fas fa-check text-success"></i> Sent' : 
                                '<i class="fas fa-times text-muted"></i> Not sent'
                            }
                        </span>
                    </div>
                </div>
            </div>

            ${issue.resolution_notes ? `
            <div class="detail-group">
                <label>Resolution Notes:</label>
                <div class="detail-description">${issue.resolution_notes}</div>
            </div>
            ` : ''}

            ${issue.resolved_at ? `
            <div class="detail-group">
                <label>Resolved On:</label>
                <span class="detail-value">${formatDateTime(issue.resolved_at)}</span>
            </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('issueDetailsContent').innerHTML = content;
}

// Format date and time
function formatDateTime(date, time = null) {
    const d = new Date(date);
    if (time) {
        const [hours, minutes] = time.split(':');
        d.setHours(hours, minutes);
    }
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return d.toLocaleDateString('en-US', options);
}

// Print current issue
function printCurrentIssue() {
    if (currentIssueId) {
        printIssue(currentIssueId);
    }
}

// Print issue report
function printIssue(issueId) {
    window.open(`print_environmental_issue.php?id=${issueId}`, '_blank');
}

// Update issue
function updateIssue(issueId) {
    window.location.href = `update_environmental_issue.php?id=${issueId}`;
}

// Duplicate issue
function duplicateIssue(issueId) {
    if (confirm('This will create a new issue report based on this issue. Continue?')) {
        window.location.href = `report_environmental_issues.php?duplicate=${issueId}`;
    }
}

// Share issue
function shareIssue(issueId) {
    const url = `${window.location.origin}/view_environmental_issue.php?id=${issueId}`;
    
    if (navigator.share) {
        navigator.share({
            title: `Environmental Issue #${String(issueId).padStart(4, '0')}`,
            text: 'Environmental Issue Report',
            url: url
        });
    } else {
        // Fallback - copy to clipboard
        navigator.clipboard.writeText(url).then(() => {
            showNotification('Issue link copied to clipboard', 'success');
        });
    }
}

// Export reports
function exportReports() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = `export_environmental_issues.php?${params.toString()}`;
}

// Clear filters
function clearFilters() {
    window.location.href = window.location.pathname;
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification`;
    notification.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideInRight 0.3s ease-out;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $().tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    }
    
    // Auto-refresh for critical issues
    if (document.querySelector('.severity-critical')) {
        setInterval(() => {
            // Subtle indicator that page might need refresh
            const criticalRows = document.querySelectorAll('.severity-critical');
            criticalRows.forEach(row => {
                row.style.animation = 'pulse 2s infinite';
            });
        }, 30000);
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + N for new issue
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'report_environmental_issues.php';
    }
    
    // Ctrl/Cmd + F for focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('search').focus();
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        $('.modal').modal('hide');
    }
});

console.log('Environmental Issues view initialized successfully');
</script>

<style>
.main-content {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 1px solid #dee2e6;
}

.page-header h2 {
    color: #333;
    margin: 0;
    font-size: 24px;
    font-weight: 600;
}

.page-header h2 i {
    color: #dc3545;
    margin-right: 10px;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

/* Statistics Dashboard */
.stats-dashboard {
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-left: 4px solid;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.stat-card.total { border-left-color: #6c757d; }
.stat-card.open { border-left-color: #ffc107; }
.stat-card.progress { border-left-color: #17a2b8; }
.stat-card.resolved { border-left-color: #28a745; }
.stat-card.critical { border-left-color: #dc3545; }
.stat-card.high { border-left-color: #fd7e14; }

.stat-card {
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    font-size: 24px;
    opacity: 0.8;
}

.stat-card.total .stat-icon { color: #6c757d; }
.stat-card.open .stat-icon { color: #ffc107; }
.stat-card.progress .stat-icon { color: #17a2b8; }
.stat-card.resolved .stat-icon { color: #28a745; }
.stat-card.critical .stat-icon { color: #dc3545; }
.stat-card.high .stat-icon { color: #fd7e14; }

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 12px;
    font-weight: 500;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Filters */
.filters-container {
    margin-bottom: 30px;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h5 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.card-header h5 i {
    color: #6c757d;
    margin-right: 8px;
}

.filter-form .form-group {
    margin-bottom: 0;
}

.filter-form .form-group label {
    font-weight: 500;
    color: #555;
    margin-bottom: 5px;
    font-size: 14px;
}

/* Issues Container */
.issues-container {
    margin-bottom: 30px;
}

.no-data {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.no-data-icon {
    font-size: 64px;
    color: #dee2e6;
    margin-bottom: 20px;
}

.no-data-text h3 {
    color: #333;
    margin-bottom: 15px;
}

.no-data-text p {
    color: #6c757d;
    margin-bottom: 25px;
}

/* Table Styles */
.table {
    margin-bottom: 0;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #555;
    font-size: 14px;
    padding: 15px 12px;
}

.table td {
    padding: 15px 12px;
    vertical-align: middle;
    border-top: 1px solid #dee2e6;
}

.issue-row {
    transition: all 0.3s ease;
}

.issue-row:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.issue-id {
    display: flex;
    flex-direction: column;
}

.issue-id strong {
    color: #333;
    font-size: 14px;
}

.issue-time {
    font-size: 12px;
    color: #6c757d;
}

.issue-type-severity {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.issue-type {
    display: flex;
    align-items: center;
    color: #333;
    font-weight: 500;
}

.issue-type i {
    margin-right: 5px;
    color: #6c757d;
    font-size: 12px;
}

.severity-badge {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    color: white;
    display: inline-block;
}

.severity-critical { background-color: #dc3545; }
.severity-high { background-color: #fd7e14; }
.severity-medium { background-color: #ffc107; color: #333; }
.severity-low { background-color: #17a2b8; }

.field-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.field-name {
    display: flex;
    align-items: center;
    color: #333;
    font-weight: 500;
}

.field-name i {
    margin-right: 5px;
    color: #28a745;
    font-size: 12px;
}

.field-location,
.affected-area {
    font-size: 12px;
    color: #6c757d;
}

.date-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.issue-date {
    color: #333;
    font-weight: 500;
}

.created-date {
    font-size: 12px;
}

.status-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
    text-align: center;
}

.status-open { background-color: #fff3cd; color: #856404; }
.status-in-progress { background-color: #d1ecf1; color: #0c5460; }
.status-resolved { background-color: #d4edda; color: #155724; }
.status-closed { background-color: #e2e3e5; color: #383d41; }

.notification-indicator i {
    font-size: 12px;
}

.impact-info {
    max-width: 200px;
}

.impact-preview {
    font-size: 13px;
    color: #555;
    line-height: 1.4;
    cursor: help;
}

.action-buttons {
    display: flex;
    gap: 5px;
    align-items: center;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

/* Pagination */
.pagination-container {
    margin-top: 30px;
    text-align: center;
}

.pagination .page-link {
    color: #333;
    border: 1px solid #dee2e6;
    padding: 8px 12px;
}

.pagination .page-item.active .page-link {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
}

.pagination .page-link:hover {
    background-color: #f8f9fa;
    border-color: #dc3545;
    color: #dc3545;
}

/* Modal Styles */
.modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.modal-title i {
    color: #dc3545;
    margin-right: 8px;
}

.issue-details {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.detail-group {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f1f3f4;
}

.detail-group:last-child {
    border-bottom: none;
}

.detail-group label {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    display: block;
    font-size: 14px;
}

.detail-value {
    color: #555;
    font-size: 14px;
}

.detail-description {
    color: #555;
    font-size: 14px;
    line-height: 1.6;
    background: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
    border-left: 3px solid #dc3545;
    white-space: pre-wrap;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .stat-card {
        padding: 15px;
    }
    
    .stat-number {
        font-size: 20px;
    }
    
    .stat-icon {
        font-size: 20px;
    }
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .action-buttons {
        width: 100%;
        justify-content: flex-end;
    }
    
    .stats-dashboard .row {
        gap: 15px;
    }
    
    .stat-card {
        margin-bottom: 15px;
    }
    
    .filters-container .card-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .filter-form .row {
        gap: 15px;
    }
    
    .table-responsive {
        font-size: 14px;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 5px;
    }
    
    .btn-sm {
        padding: 4px 8px;
        font-size: 11px;
    }
}

@media (max-width: 576px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .filter-form .col-md-1,
    .filter-form .col-md-2,
    .filter-form .col-md-3 {
        margin-bottom: 15px;
    }
    
    .pagination .page-link {
        padding: 6px 10px;
        font-size: 12px;
    }
}

/* Animation */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

/* Print styles */
@media print {
    .page-header .action-buttons,
    .filters-container,
    .pagination-container,
    .action-buttons {
        display: none;
    }
    
    .table {
        font-size: 12px;
    }
    
    .stat-card {
        break-inside: avoid;
    }
}
</style>

<?php include 'includes/footer.php'; ?>