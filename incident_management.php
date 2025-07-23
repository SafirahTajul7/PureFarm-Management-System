<?php
// incident_management.php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

// Handle form submission for adding/updating incidents
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_incident'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO incidents (type, description, date_reported, status, severity, reported_by, affected_area, resolution_notes) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['type'],
                $_POST['description'],
                $_POST['date_reported'],
                'open',
                $_POST['severity'],
                $_POST['reported_by'],
                $_POST['affected_area'],
                $_POST['resolution_notes']
            ]);
            $_SESSION['success'] = "Incident reported successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error reporting incident: " . $e->getMessage();
        }
    }
}

// Fetch existing incidents
try {
    $incidents = $pdo->query("
        SELECT * FROM incidents 
        ORDER BY date_reported DESC
    ")->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Error fetching incidents: " . $e->getMessage();
    $incidents = [];
}

$pageTitle = 'Incident Management';
include 'includes/header.php';

?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-exclamation-triangle"></i> Incident Management</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" data-toggle="modal" data-target="#addIncidentModal">
                <i class="fas fa-plus"></i> Report New Incident
            </button>
            <button class="btn btn-primary" onclick="location.href='animal_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Animal Management
            </button>
        </div>
    </div>

    <?php include 'includes/messages.php'; ?>

    <!-- Incidents Table -->
    <div class="card">
        <div class="card-header">
            <h3>Reported Incidents</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="incidentsTable">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Date Reported</th>
                            <th>Status</th>
                            <th>Severity</th>
                            <th>Reported By</th>
                            <th>Affected Area</th>
                            <th>Resolution Date</th>
                            <th>Resolution Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incidents as $incident): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($incident['type']); ?></td>
                            <td>
                                <span class="incident-description" title="<?php echo htmlspecialchars($incident['description']); ?>">
                                    <?php echo htmlspecialchars(strlen($incident['description']) > 50 ? substr($incident['description'], 0, 50) . '...' : $incident['description']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($incident['date_reported'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $incident['status'] === 'open' ? 'danger' : 'success'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($incident['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php 
                                    switch($incident['severity']) {
                                        case 'high': echo 'danger'; break;
                                        case 'medium': echo 'warning'; break;
                                        case 'low': echo 'info'; break;
                                        default: echo 'secondary';
                                    }
                                ?>">
                                    <?php echo ucfirst(htmlspecialchars($incident['severity'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($incident['reported_by']); ?></td>
                            <td><?php echo htmlspecialchars($incident['affected_area']); ?></td>
                            <td>
                                <?php if (!empty($incident['resolution_date'])): ?>
                                    <span class="text-success">
                                        <i class="fas fa-calendar-check"></i>
                                        <?php echo date('M d, Y', strtotime($incident['resolution_date'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">
                                        <i class="fas fa-clock"></i>
                                        Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($incident['resolution_notes'])): ?>
                                    <span class="resolution-notes" title="<?php echo htmlspecialchars($incident['resolution_notes']); ?>">
                                        <?php echo htmlspecialchars(strlen($incident['resolution_notes']) > 30 ? substr($incident['resolution_notes'], 0, 30) . '...' : $incident['resolution_notes']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <!-- Edit button -->
                                    <button class="btn btn-sm btn-primary edit-incident" data-id="<?php echo $incident['id']; ?>" title="Edit Incident">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($incident['status'] === 'open'): ?>
                                    <!-- Resolve button -->
                                    <button class="btn btn-sm btn-success resolve-incident" 
                                            data-id="<?php echo $incident['id']; ?>"
                                            title="Mark as Resolved">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <!-- View Details button -->
                                    <button class="btn btn-sm btn-info view-incident" 
                                            data-id="<?php echo $incident['id']; ?>"
                                            data-type="<?php echo htmlspecialchars($incident['type']); ?>"
                                            data-description="<?php echo htmlspecialchars($incident['description']); ?>"
                                            data-severity="<?php echo htmlspecialchars($incident['severity']); ?>"
                                            data-status="<?php echo htmlspecialchars($incident['status']); ?>"
                                            data-reported-by="<?php echo htmlspecialchars($incident['reported_by']); ?>"
                                            data-affected-area="<?php echo htmlspecialchars($incident['affected_area']); ?>"
                                            data-date-reported="<?php echo date('M d, Y', strtotime($incident['date_reported'])); ?>"
                                            data-resolution-date="<?php echo !empty($incident['resolution_date']) ? date('M d, Y', strtotime($incident['resolution_date'])) : ''; ?>"
                                            data-resolution-notes="<?php echo htmlspecialchars($incident['resolution_notes']); ?>"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Incident Modal -->
<div class="modal fade" id="addIncidentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Report New Incident</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Incident Type</label>
                        <select name="type" class="form-control" required>
                            <option value="illness">Animal Illness</option>
                            <option value="injury">Animal Injury</option>
                            <option value="equipment">Equipment Failure</option>
                            <option value="facility">Facility Issue</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" required rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Date Reported</label>
                        <input type="date" name="date_reported" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Severity</label>
                        <select name="severity" class="form-control" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reported By</label>
                        <input type="text" name="reported_by" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Affected Area</label>
                        <input type="text" name="affected_area" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Resolution Notes</label>
                        <textarea name="resolution_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" name="add_incident" class="btn btn-primary">Report Incident</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Incident Modal -->
<div class="modal fade" id="editIncidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Incident</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="editIncidentForm">
                <div class="modal-body">
                    <input type="hidden" name="incident_id" id="edit_incident_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Incident Type</label>
                                <select name="type" id="edit_type" class="form-control" required>
                                    <option value="illness">Animal Illness</option>
                                    <option value="injury">Animal Injury</option>
                                    <option value="equipment">Equipment Failure</option>
                                    <option value="facility">Facility Issue</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date Reported</label>
                                <input type="date" name="date_reported" id="edit_date_reported" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Severity</label>
                                <select name="severity" id="edit_severity" class="form-control" required>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Reported By</label>
                                <input type="text" name="reported_by" id="edit_reported_by" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Affected Area</label>
                                <input type="text" name="affected_area" id="edit_affected_area" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" id="edit_status" class="form-control" required>
                                    <option value="open">Open</option>
                                    <option value="resolved">Resolved</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Resolution Date</label>
                                <input type="date" name="resolution_date" id="edit_resolution_date" class="form-control">
                                <small class="form-text text-muted">Leave blank if incident is not resolved</small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control" required rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Resolution Notes</label>
                        <textarea name="resolution_notes" id="edit_resolution_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Incident Details Modal -->
<div class="modal fade" id="viewIncidentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle"></i> Incident Details
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-item">
                            <strong>Incident Type:</strong>
                            <span id="view_type"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Severity:</strong>
                            <span id="view_severity"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Status:</strong>
                            <span id="view_status"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Reported By:</strong>
                            <span id="view_reported_by"></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-item">
                            <strong>Affected Area:</strong>
                            <span id="view_affected_area"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Date Reported:</strong>
                            <span id="view_date_reported"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Resolution Date:</strong>
                            <span id="view_resolution_date"></span>
                        </div>
                    </div>
                </div>
                <div class="detail-item mt-3">
                    <strong>Description:</strong>
                    <div id="view_description" class="mt-2 p-3 bg-light border-left border-info"></div>
                </div>
                <div class="detail-item mt-3" id="view_resolution_section">
                    <strong>Resolution Notes:</strong>
                    <div id="view_resolution_notes" class="mt-2 p-3 bg-light border-left border-success"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add JavaScript for handling incident resolution -->
<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#incidentsTable').DataTable({
        "pageLength": 10,
        "order": [[2, "desc"]], // Order by date reported descending
        "responsive": true,
        "columnDefs": [
            { "orderable": false, "targets": -1 } // Disable sorting on Actions column
        ]
    });

    // Handle resolve button click
    $('.resolve-incident').click(function() {
        const id = $(this).data('id');
        const $button = $(this);
        const $row = $button.closest('tr');
        const $statusBadge = $row.find('td:eq(3) .badge');
        const $resolutionDateCell = $row.find('td:eq(7)');
        
        if (confirm('Are you sure you want to mark this incident as resolved?')) {
            $.post('ajax/resolve_incident.php', { 
                id: id,
                resolution_date: new Date().toISOString().split('T')[0]
            }, function(response) {
                if (response.success) {
                    // Update status badge
                    $statusBadge.removeClass('badge-danger').addClass('badge-success');
                    $statusBadge.text('Resolved');
                    
                    // Update resolution date
                    const today = new Date().toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    });
                    $resolutionDateCell.html('<span class="text-success"><i class="fas fa-calendar-check"></i> ' + today + '</span>');
                    
                    // Remove resolve button
                    $button.remove();
                    
                    // Show success message
                    alert('Incident marked as resolved successfully!');
                } else {
                    alert('Error resolving incident. Please try again.');
                }
            }, 'json').fail(function() {
                alert('Error connecting to server. Please try again.');
            });
        }
    });

    // Handle edit button click
    $('.edit-incident').click(function() {
        const id = $(this).data('id');
        
        // Fetch incident details
        $.get('ajax/get_incident.php', { id: id }, function(response) {
            if (response.success) {
                const incident = response.data;
                
                // Populate the edit modal with incident data
                $('#edit_incident_id').val(incident.id);
                $('#edit_type').val(incident.type);
                $('#edit_description').val(incident.description);
                $('#edit_date_reported').val(incident.date_reported);
                $('#edit_severity').val(incident.severity);
                $('#edit_reported_by').val(incident.reported_by);
                $('#edit_affected_area').val(incident.affected_area);
                $('#edit_resolution_notes').val(incident.resolution_notes);
                $('#edit_status').val(incident.status);
                $('#edit_resolution_date').val(incident.resolution_date || '');
                
                // Show the modal
                $('#editIncidentModal').modal('show');
            } else {
                alert('Error fetching incident details. Please try again.');
            }
        }, 'json').fail(function() {
            alert('Error connecting to server. Please try again.');
        });
    });

    // Handle view button click
    $('.view-incident').click(function() {
        const type = $(this).data('type');
        const description = $(this).data('description');
        const severity = $(this).data('severity');
        const status = $(this).data('status');
        const reportedBy = $(this).data('reported-by');
        const affectedArea = $(this).data('affected-area');
        const dateReported = $(this).data('date-reported');
        const resolutionDate = $(this).data('resolution-date');
        const resolutionNotes = $(this).data('resolution-notes');
        
        // Populate view modal
        $('#view_type').html('<i class="fas fa-tag"></i> ' + type.charAt(0).toUpperCase() + type.slice(1));
        $('#view_severity').html('<span class="badge badge-' + 
            (severity === 'high' ? 'danger' : (severity === 'medium' ? 'warning' : 'info')) + 
            '">' + severity.toUpperCase() + '</span>');
        $('#view_status').html('<span class="badge badge-' + 
            (status === 'open' ? 'danger' : 'success') + 
            '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>');
        $('#view_reported_by').text(reportedBy);
        $('#view_affected_area').text(affectedArea);
        $('#view_date_reported').text(dateReported);
        $('#view_resolution_date').html(resolutionDate ? 
            '<span class="text-success"><i class="fas fa-calendar-check"></i> ' + resolutionDate + '</span>' : 
            '<span class="text-muted"><i class="fas fa-clock"></i> Pending</span>');
        $('#view_description').text(description);
        
        if (resolutionNotes && resolutionNotes.trim() !== '') {
            $('#view_resolution_notes').text(resolutionNotes);
            $('#view_resolution_section').show();
        } else {
            $('#view_resolution_section').hide();
        }
        
        $('#viewIncidentModal').modal('show');
    });

    // Handle edit form submission
    $('#editIncidentForm').submit(function(e) {
        e.preventDefault();
        
        $.post('ajax/update_incident.php', $(this).serialize(), function(response) {
            if (response.success) {
                // Reload the page to show updated data
                location.reload();
            } else {
                alert('Error updating incident. Please try again.');
            }
        }, 'json').fail(function() {
            alert('Error connecting to server. Please try again.');
        });
    });

    // Handle status change in edit modal
    $('#edit_status').change(function() {
        const status = $(this).val();
        const $resolutionDate = $('#edit_resolution_date');
        
        if (status === 'resolved') {
            if (!$resolutionDate.val()) {
                $resolutionDate.val(new Date().toISOString().split('T')[0]);
            }
            $resolutionDate.prop('required', true);
        } else {
            $resolutionDate.prop('required', false);
        }
    });
});
</script>

<style>
.d-flex {
    display: flex;
}
.btn-group {
    display: inline-flex;
}
.btn-group .btn {
    margin-right: 2px;
}
.btn-group .btn:last-child {
    margin-right: 0;
}
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.2rem;
}
.incident-description, .resolution-notes {
    cursor: help;
}
.detail-item {
    margin-bottom: 15px;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
.detail-item:last-child {
    border-bottom: none;
}
.detail-item strong {
    color: #495057;
    display: inline-block;
    min-width: 120px;
}
.border-left {
    border-left: 4px solid;
}
.border-info {
    border-left-color: #17a2b8 !important;
}
.border-success {
    border-left-color: #28a745 !important;
}
</style>

<?php include 'includes/footer.php'; ?>