<?php
// Save this as pest_disease_monitoring.php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Fetch all crop issues (both active and resolved)
try {
    $stmt = $pdo->prepare("
        SELECT ci.*, c.crop_name, f.field_name
        FROM crop_issues ci
        JOIN crops c ON ci.crop_id = c.id
        JOIN fields f ON c.field_id = f.id
        ORDER BY ci.date_identified DESC
    ");
    $stmt->execute();
    $all_issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Error fetching crop issues: " . $e->getMessage());
    $all_issues = [];
}

$pageTitle = 'Pest & Disease Monitoring';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-bug"></i> Pest & Disease Monitoring</h2>
        <div class="action-buttons">
        
            <button class="btn btn-secondary" onclick="location.href='pesticide_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Pesticide Management
            </button>
        </div>
    </div>

    <!-- All Pest/Disease Issues -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> All Pest & Disease Issues</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="issuesTable">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Crop</th>
                            <th>Issue Type</th>
                            <th>Description</th>
                            <th>Severity</th>
                            <th>Identified</th>
                            <th>Affected Area</th>
                            <th>Status</th>
                            <th>Resolution Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_issues)): ?>
                            <tr>
                                <td colspan="10" class="text-center">No pest or disease issues recorded</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_issues as $issue): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($issue['field_name']); ?></td>
                                    <td><?php echo htmlspecialchars($issue['crop_name']); ?></td>
                                    <td>
                                        <?php if ($issue['issue_type'] === 'pest'): ?>
                                            <span class="badge bg-danger">Pest</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Disease</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($issue['description']); ?></td>
                                    <td>
                                        <?php 
                                        switch ($issue['severity']) {
                                            case 'low':
                                                echo '<span class="badge bg-success">Low</span>';
                                                break;
                                            case 'medium':
                                                echo '<span class="badge bg-warning">Medium</span>';
                                                break;
                                            case 'high':
                                                echo '<span class="badge bg-danger">High</span>';
                                                break;
                                            default:
                                                echo '<span class="badge bg-secondary">Unknown</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($issue['date_identified']); ?></td>
                                    <td><?php echo htmlspecialchars($issue['affected_area']); ?></td>
                                    <td>
                                        <?php if (!empty($issue['resolved'])): ?>
                                            <span class="badge bg-success">Resolved</span>
                                        <?php elseif (!empty($issue['treatment_applied'])): ?>
                                            <span class="badge bg-primary">Treated</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo !empty($issue['resolution_date']) ? htmlspecialchars($issue['resolution_date']) : 'N/A'; ?></td>
                                    <td>
                                        <?php if (empty($issue['treatment_applied']) && empty($issue['resolved'])): ?>
                                            <button class="btn btn-sm btn-primary treat-issue" onclick="location.href='pesticide_management.php?treat_issue=<?php echo $issue['id']; ?>'">
                                                <i class="fas fa-syringe"></i> Treat
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-info view-issue" data-bs-toggle="modal" data-bs-target="#viewIssueModal" 
                                                    data-issue-id="<?php echo $issue['id']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        <?php endif; ?>
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

<!-- Include the Report Issue Modal -->
<!-- You can copy the reportIssueModal from pesticide_management.php -->

<!-- View Issue Modal -->
<div class="modal fade" id="viewIssueModal" tabindex="-1" aria-labelledby="viewIssueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewIssueModalLabel"><i class="fas fa-bug"></i> Issue Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="issueDetails">
                    <!-- Details will be loaded here via JavaScript -->
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize DataTable for better searching and pagination
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#issuesTable').DataTable({
            responsive: true,
            order: [[5, 'desc']], // Order by identification date, newest first
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
        });
    }
    
    // Handle view-issue button clicks
    const viewIssueButtons = document.querySelectorAll('.view-issue');
    viewIssueButtons.forEach(button => {
        button.addEventListener('click', function() {
            const issueId = this.getAttribute('data-issue-id');
            // Fetch issue details via AJAX and populate the modal
            fetch(`get_issue_details.php?id=${issueId}`)
                .then(response => response.json())
                .then(data => {
                    // Create and populate the details
                    let detailsHtml = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Basic Information</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th>Field:</th>
                                        <td>${data.field_name}</td>
                                    </tr>
                                    <tr>
                                        <th>Crop:</th>
                                        <td>${data.crop_name}</td>
                                    </tr>
                                    <tr>
                                        <th>Issue Type:</th>
                                        <td>${data.issue_type === 'pest' ? '<span class="badge bg-danger">Pest</span>' : '<span class="badge bg-warning">Disease</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th>Description:</th>
                                        <td>${data.description}</td>
                                    </tr>
                                    <tr>
                                        <th>Severity:</th>
                                        <td>${getSeverityBadge(data.severity)}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Status Information</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th>Identified:</th>
                                        <td>${data.date_identified}</td>
                                    </tr>
                                    <tr>
                                        <th>Affected Area:</th>
                                        <td>${data.affected_area}</td>
                                    </tr>
                                    <tr>
                                        <th>Treatment Applied:</th>
                                        <td>${data.treatment_applied ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning">No</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>${data.resolved ? '<span class="badge bg-success">Resolved</span>' : '<span class="badge bg-warning">Active</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th>Resolution Date:</th>
                                        <td>${data.resolution_date || 'N/A'}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Notes</h6>
                                <div class="border p-3 rounded bg-light">${data.notes || 'No notes available'}</div>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('issueDetails').innerHTML = detailsHtml;
                })
                .catch(error => {
                    document.getElementById('issueDetails').innerHTML = `<div class="alert alert-danger">Error loading issue details: ${error.message}</div>`;
                });
        });
    });
    
    function getSeverityBadge(severity) {
        switch(severity) {
            case 'low':
                return '<span class="badge bg-success">Low</span>';
            case 'medium':
                return '<span class="badge bg-warning">Medium</span>';
            case 'high':
                return '<span class="badge bg-danger">High</span>';
            default:
                return '<span class="badge bg-secondary">Unknown</span>';
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>