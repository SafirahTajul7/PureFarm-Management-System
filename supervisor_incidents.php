<?php
// supervisor_incidents_health.php
require_once 'includes/auth.php';
auth()->checkSupervisor(); // Assuming you have a supervisor auth check
require_once 'includes/db.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle incident reporting
    if (isset($_POST['report_incident'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO incidents (type, description, date_reported, status, severity, reported_by, affected_area, resolution_notes) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['type'],
                $_POST['description'],
                $_POST['date_reported'],
                'open',
                $_POST['severity'],
                $_SESSION['user_name'] ?? 'Supervisor', // Use logged-in supervisor name
                $_POST['affected_area'],
                $_POST['resolution_notes'] ?? ''
            ]);
            $_SESSION['success'] = "Incident reported successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error reporting incident: " . $e->getMessage();
        }
    }
    
    // Handle animal health update
    if (isset($_POST['update_health'])) {
        try {
            $stmt = $pdo->prepare("UPDATE animals SET health_status = ?, last_health_check = ?, health_notes = ? WHERE id = ?");
            $stmt->execute([
                $_POST['health_status'],
                $_POST['health_check_date'],
                $_POST['health_notes'],
                $_POST['animal_id']
            ]);
            $_SESSION['success'] = "Animal health status updated successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error updating animal health: " . $e->getMessage();
        }
    }
}

// Fetch recent incidents reported by supervisors
try {
    $incidents = $pdo->query("
        SELECT * FROM incidents 
        WHERE reported_by LIKE '%Supervisor%' OR reported_by = '" . ($_SESSION['user_name'] ?? '') . "'
        ORDER BY date_reported DESC 
        LIMIT 10
    ")->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Error fetching incidents: " . $e->getMessage();
    $incidents = [];
}

// Fetch animals for health monitoring
try {
    $animals = $pdo->query("
        SELECT id, animal_id, species, breed, health_status, last_health_check, health_notes
        FROM animals 
        ORDER BY last_health_check ASC
    ")->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Error fetching animals: " . $e->getMessage();
    $animals = [];
}

// Get animals that need health checks (more than 30 days)
try {
    $health_alerts = $pdo->query("
        SELECT id, animal_id, species, breed, last_health_check
        FROM animals 
        WHERE last_health_check < DATE_SUB(NOW(), INTERVAL 30 DAY) 
        OR last_health_check IS NULL
        ORDER BY last_health_check ASC
    ")->fetchAll();
} catch(PDOException $e) {
    $health_alerts = [];
}

$pageTitle = 'Incidents & Health Management';
include 'includes/header.php';

?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-heartbeat"></i> Incidents & Health Management</h2>
        <div class="action-buttons">
            <button class="btn btn-danger" data-toggle="modal" data-target="#reportIncidentModal">
                <i class="fas fa-exclamation-triangle"></i> Report Incident
            </button>
            <button class="btn btn-success" data-toggle="modal" data-target="#updateHealthModal">
                <i class="fas fa-heartbeat"></i> Update Animal Health
            </button>
        </div>
    </div>

    <?php include 'includes/messages.php'; ?>

    <!-- Health Alerts -->
    <?php if (!empty($health_alerts)): ?>
    <div class="alert alert-warning">
        <h5><i class="fas fa-exclamation-triangle"></i> Health Check Alerts</h5>
        <p>The following animals need health checkups:</p>
        <ul class="mb-0">
            <?php foreach ($health_alerts as $alert): ?>
            <li>
                <strong><?php echo htmlspecialchars($alert['animal_id']); ?></strong> 
                (<?php echo htmlspecialchars($alert['species']); ?>) - 
                Last check: <?php echo $alert['last_health_check'] ? htmlspecialchars($alert['last_health_check']) : 'Never'; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Recent Incidents -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Recent Incidents</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($incidents)): ?>
                        <p class="text-muted">No incidents reported yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Severity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($incidents as $incident): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($incident['type']); ?></td>
                                        <td><?php echo date('M j', strtotime($incident['date_reported'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $incident['status'] === 'open' ? 'danger' : 'success'; ?>">
                                                <?php echo htmlspecialchars($incident['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $incident['severity'] === 'high' ? 'danger' : 
                                                    ($incident['severity'] === 'medium' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo htmlspecialchars($incident['severity']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info view-incident" 
                                                    data-id="<?php echo $incident['id']; ?>"
                                                    data-toggle="tooltip" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Animal Health Status -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Animal Health Overview</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($animals)): ?>
                        <p class="text-muted">No animals registered yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Animal ID</th>
                                        <th>Species</th>
                                        <th>Health Status</th>
                                        <th>Last Check</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($animals, 0, 8) as $animal): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($animal['animal_id']); ?></td>
                                        <td><?php echo htmlspecialchars($animal['species']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $animal['health_status'] === 'healthy' ? 'success' : 
                                                    ($animal['health_status'] === 'sick' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo htmlspecialchars($animal['health_status'] ?? 'unknown'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $animal['last_health_check'] ? date('M j', strtotime($animal['last_health_check'])) : 'Never'; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary update-health" 
                                                    data-id="<?php echo $animal['id']; ?>"
                                                    data-animal-id="<?php echo $animal['animal_id']; ?>"
                                                    data-toggle="tooltip" title="Update Health">
                                                <i class="fas fa-heartbeat"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Incident Modal -->
<div class="modal fade" id="reportIncidentModal" tabindex="-1">
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
                            <option value="">Select incident type...</option>
                            <option value="illness">Animal Illness</option>
                            <option value="injury">Animal Injury</option>
                            <option value="equipment">Equipment Failure</option>
                            <option value="facility">Facility Issue</option>
                            <option value="pest">Pest/Disease Issue</option>
                            <option value="weather">Weather Related</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" required rows="3" 
                                  placeholder="Provide detailed description of the incident..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Date Reported</label>
                        <input type="date" name="date_reported" class="form-control" required 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Severity Level</label>
                        <select name="severity" class="form-control" required>
                            <option value="">Select severity...</option>
                            <option value="low">Low - Minor issue, no immediate action required</option>
                            <option value="medium">Medium - Requires attention within 24 hours</option>
                            <option value="high">High - Immediate action required</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Affected Area/Location</label>
                        <input type="text" name="affected_area" class="form-control" required 
                               placeholder="e.g., Barn 3, Field A, Equipment Shed">
                    </div>
                    <div class="form-group">
                        <label>Initial Response/Notes</label>
                        <textarea name="resolution_notes" class="form-control" rows="2" 
                                  placeholder="Any immediate actions taken or observations..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="report_incident" class="btn btn-danger">Report Incident</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Animal Health Modal -->
<div class="modal fade" id="updateHealthModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Animal Health</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select Animal</label>
                        <select name="animal_id" class="form-control" required>
                            <option value="">Choose animal...</option>
                            <?php foreach ($animals as $animal): ?>
                            <option value="<?php echo $animal['id']; ?>">
                                <?php echo htmlspecialchars($animal['animal_id'] . ' - ' . $animal['species'] . ' (' . $animal['breed'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Health Status</label>
                        <select name="health_status" class="form-control" required>
                            <option value="">Select status...</option>
                            <option value="healthy">Healthy</option>
                            <option value="sick">Sick</option>
                            <option value="injured">Injured</option>
                            <option value="recovering">Recovering</option>
                            <option value="quarantine">Quarantine</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Health Check Date</label>
                        <input type="date" name="health_check_date" class="form-control" required 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Health Notes</label>
                        <textarea name="health_notes" class="form-control" rows="3" 
                                  placeholder="Symptoms, treatment given, observations, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_health" class="btn btn-success">Update Health Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Incident Details Modal -->
<div class="modal fade" id="viewIncidentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Incident Details</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-sm-4"><strong>Type:</strong></div>
                    <div class="col-sm-8" id="view_type"></div>
                </div>
                <div class="row mt-2">
                    <div class="col-sm-4"><strong>Date Reported:</strong></div>
                    <div class="col-sm-8" id="view_date"></div>
                </div>
                <div class="row mt-2">
                    <div class="col-sm-4"><strong>Status:</strong></div>
                    <div class="col-sm-8" id="view_status"></div>
                </div>
                <div class="row mt-2">
                    <div class="col-sm-4"><strong>Severity:</strong></div>
                    <div class="col-sm-8" id="view_severity"></div>
                </div>
                <div class="row mt-2">
                    <div class="col-sm-4"><strong>Affected Area:</strong></div>
                    <div class="col-sm-8" id="view_area"></div>
                </div>
                <div class="row mt-2">
                    <div class="col-sm-4"><strong>Description:</strong></div>
                    <div class="col-sm-8" id="view_description"></div>
                </div>
                <div class="row mt-2">
                    <div class="col-sm-4"><strong>Resolution Notes:</strong></div>
                    <div class="col-sm-8" id="view_resolution"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Handle view incident
    $('.view-incident').click(function() {
        const id = $(this).data('id');
        
        $.get('ajax/get_incident.php', { id: id }, function(response) {
            if (response.success) {
                const incident = response.data;
                
                $('#view_type').text(incident.type);
                $('#view_date').text(incident.date_reported);
                $('#view_status').html('<span class="badge badge-' + 
                    (incident.status === 'open' ? 'danger' : 'success') + '">' + 
                    incident.status + '</span>');
                $('#view_severity').html('<span class="badge badge-' + 
                    (incident.severity === 'high' ? 'danger' : 
                    (incident.severity === 'medium' ? 'warning' : 'info')) + '">' + 
                    incident.severity + '</span>');
                $('#view_area').text(incident.affected_area);
                $('#view_description').text(incident.description);
                $('#view_resolution').text(incident.resolution_notes || 'No notes available');
                
                $('#viewIncidentModal').modal('show');
            } else {
                alert('Error fetching incident details.');
            }
        }).fail(function() {
            alert('Error connecting to server.');
        });
    });
    
    // Handle update health button
    $('.update-health').click(function() {
        const animalId = $(this).data('id');
        const animalName = $(this).data('animal-id');
        
        // Pre-select the animal in the modal
        $('select[name="animal_id"]').val(animalId);
        $('#updateHealthModal').modal('show');
    });
});
</script>

<style>
.action-buttons {
    display: flex;
    gap: 10px;
}

.action-buttons .btn {
    display: flex;
    align-items: center;
    gap: 5px;
}

.card {
    border: none;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
}

.table-responsive {
    max-height: 400px;
    overflow-y: auto;
}

.badge {
    font-size: 0.75em;
}

.alert ul {
    margin-bottom: 0;
}

.modal-body .row {
    margin-bottom: 5px;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .action-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .action-buttons .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php include 'includes/footer.php'; ?>