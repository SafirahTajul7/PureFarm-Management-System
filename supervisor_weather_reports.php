<?php
require_once 'includes/auth.php';
auth()->checkSupervisor(); // SUPERVISOR ACCESS ONLY

require_once 'includes/db.php';

$supervisor_id = $_SESSION['user_id'];

// Handle filter parameters
$field_filter = isset($_GET['field']) ? $_GET['field'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Get supervisor's assigned fields
try {
    $fields_stmt = $pdo->prepare("
        SELECT DISTINCT f.id, f.field_name, f.location 
        FROM fields f
        LEFT JOIN staff_field_assignments sfa ON f.id = sfa.field_id 
        WHERE sfa.staff_id = ? AND sfa.status = 'active'
        OR NOT EXISTS (
            SELECT 1 FROM staff_field_assignments 
            WHERE staff_id = ? AND status = 'active'
        )
        ORDER BY f.field_name
    ");
    $fields_stmt->execute([$supervisor_id, $supervisor_id]);
    $assigned_fields = $fields_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($assigned_fields)) {
        $fields_stmt = $pdo->query("SELECT id, field_name, location FROM fields ORDER BY field_name");
        $assigned_fields = $fields_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $assigned_fields = [];
}

// Get field observations with filters
try {
    $where_conditions = ["fo.supervisor_id = ?"];
    $params = [$supervisor_id];
    
    if ($field_filter) {
        $where_conditions[] = "fo.field_id = ?";
        $params[] = $field_filter;
    }
    
    if ($date_from) {
        $where_conditions[] = "fo.observation_date >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = "fo.observation_date <= ?";
        $params[] = $date_to;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $observations_stmt = $pdo->prepare("
        SELECT 
            fo.*,
            f.field_name,
            f.location
        FROM field_observations fo
        LEFT JOIN fields f ON fo.field_id = f.id
        WHERE {$where_clause}
        ORDER BY fo.observation_date DESC, fo.created_at DESC
        LIMIT 50
    ");
    $observations_stmt->execute($params);
    $observations = $observations_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $observations = [];
}

// Get summary statistics
try {
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_observations,
            AVG(temperature) as avg_temp,
            AVG(humidity) as avg_humidity,
            AVG(soil_moisture) as avg_moisture,
            COUNT(DISTINCT field_id) as fields_monitored
        FROM field_observations 
        WHERE supervisor_id = ? 
        AND observation_date >= ? 
        AND observation_date <= ?
    ");
    $stats_stmt->execute([$supervisor_id, $date_from, $date_to]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $stats = [
        'total_observations' => 0,
        'avg_temp' => 0,
        'avg_humidity' => 0,
        'avg_moisture' => 0,
        'fields_monitored' => 0
    ];
}

$pageTitle = 'My Field Reports';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-line"></i> My Field Reports</h2>
        <div class="action-buttons">
            <button class="btn btn-success" onclick="location.href='record_field_observation.php'">
                <i class="fas fa-plus"></i> New Observation
            </button>
            <button class="btn btn-secondary" onclick="location.href='supervisor_environmental.php'">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="summary-cards mb-4">
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="summary-card bg-teal">
                    <div class="card-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo $stats['total_observations']; ?></h3>
                        <p>Total Observations</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="summary-card bg-orange">
                    <div class="card-icon">
                        <i class="fas fa-thermometer-half"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo $stats['avg_temp'] ? round($stats['avg_temp'], 1) . '°C' : 'N/A'; ?></h3>
                        <p>Avg Temperature</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="summary-card bg-blue">
                    <div class="card-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo $stats['avg_humidity'] ? round($stats['avg_humidity'], 1) . '%' : 'N/A'; ?></h3>
                        <p>Avg Humidity</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="summary-card bg-purple">
                    <div class="card-icon">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo $stats['fields_monitored']; ?></h3>
                        <p>Fields Monitored</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" class="filter-form">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="field"><i class="fas fa-map-marker-alt"></i> Field</label>
                        <select name="field" id="field" class="form-control">
                            <option value="">All Fields</option>
                            <?php foreach($assigned_fields as $field): ?>
                            <option value="<?php echo $field['id']; ?>" 
                                    <?php echo ($field_filter == $field['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($field['field_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="date_from"><i class="fas fa-calendar"></i> From Date</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="date_to"><i class="fas fa-calendar"></i> To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Observations Table -->
    <div class="observations-section">
        <div class="section-header">
            <h3><i class="fas fa-table"></i> Field Observations</h3>
            <div class="export-buttons">
                <button class="btn btn-info btn-sm" onclick="exportToPDF()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button class="btn btn-success btn-sm" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
            </div>
        </div>

        <?php if (empty($observations)): ?>
        <div class="no-data">
            <i class="fas fa-clipboard-list"></i>
            <h4>No Field Observations Found</h4>
            <p>You haven't recorded any field observations yet, or no observations match your current filters.</p>
            <button class="btn btn-success" onclick="location.href='record_field_observation.php'">
                <i class="fas fa-plus"></i> Record Your First Observation
            </button>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped observations-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Field</th>
                        <th>Temperature</th>
                        <th>Humidity</th>
                        <th>Soil Moisture</th>
                        <th>Weather</th>
                        <th>Crop Stage</th>
                        <th>Irrigation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($observations as $obs): ?>
                    <tr>
                        <td>
                            <strong><?php echo date('M d, Y', strtotime($obs['observation_date'])); ?></strong>
                            <br><small class="text-muted"><?php echo date('g:i A', strtotime($obs['created_at'])); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($obs['field_name']); ?></strong>
                            <?php if($obs['location']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($obs['location']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($obs['temperature']): ?>
                            <span class="metric-value temp"><?php echo $obs['temperature']; ?>°C</span>
                            <?php else: ?>
                            <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($obs['humidity']): ?>
                            <span class="metric-value humidity"><?php echo $obs['humidity']; ?>%</span>
                            <?php else: ?>
                            <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($obs['soil_moisture']): ?>
                            <span class="metric-value moisture <?php echo ($obs['soil_moisture'] < 30) ? 'low' : (($obs['soil_moisture'] > 70) ? 'high' : 'normal'); ?>">
                                <?php echo $obs['soil_moisture']; ?>%
                            </span>
                            <?php else: ?>
                            <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($obs['weather_conditions']): ?>
                            <span class="badge weather-badge weather-<?php echo strtolower(str_replace(' ', '-', $obs['weather_conditions'])); ?>">
                                <?php echo htmlspecialchars($obs['weather_conditions']); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($obs['crop_stage']): ?>
                            <span class="badge stage-badge">
                                <?php echo htmlspecialchars($obs['crop_stage']); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($obs['irrigation_status']): ?>
                            <span class="badge irrigation-badge irrigation-<?php echo strtolower(str_replace(' ', '-', $obs['irrigation_status'])); ?>">
                                <?php echo htmlspecialchars($obs['irrigation_status']); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-info" onclick="viewObservation(<?php echo $obs['id']; ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="editObservation(<?php echo $obs['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteObservation(<?php echo $obs['id']; ?>)" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Observation Details Modal -->
<div class="modal fade" id="observationModal" tabindex="-1" role="dialog" aria-labelledby="observationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="observationModalLabel">
                    <i class="fas fa-clipboard-list"></i> Field Observation Details
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="observationDetails">
                <!-- Content will be loaded here via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="editObservationBtn">
                    <i class="fas fa-edit"></i> Edit
                </button>
            </div>
        </div>
    </div>
</div>

<style>
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

/* Filters Section */
.filters-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
}

.filter-form .form-group label {
    font-weight: 500;
    color: #555;
    margin-bottom: 5px;
}

.filter-form .form-group label i {
    color: #6c757d;
    margin-right: 5px;
}

.filter-buttons {
    display: flex;
    gap: 10px;
    margin-top: 8px;
}

.filter-buttons .btn {
    flex: 1;
}

/* Observations Section */
.observations-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 30px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

.section-header h3 {
    margin: 0;
    color: #333;
}

.export-buttons {
    display: flex;
    gap: 10px;
}

/* Observations Table */
.observations-table {
    margin-bottom: 0;
}

.observations-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #333;
    border-top: none;
    padding: 15px 10px;
}

.observations-table td {
    padding: 15px 10px;
    vertical-align: middle;
}

.metric-value {
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
}

.metric-value.temp {
    background-color: #fff3cd;
    color: #856404;
}

.metric-value.humidity {
    background-color: #d1ecf1;
    color: #0c5460;
}

.metric-value.moisture.low {
    background-color: #f8d7da;
    color: #721c24;
}

.metric-value.moisture.normal {
    background-color: #d4edda;
    color: #155724;
}

.metric-value.moisture.high {
    background-color: #d1ecf1;
    color: #0c5460;
}

/* Badges */
.weather-badge {
    font-size: 11px;
    padding: 4px 8px;
}

.weather-sunny { background-color: #ffc107; color: #212529; }
.weather-partly-cloudy { background-color: #6c757d; color: white; }
.weather-cloudy { background-color: #495057; color: white; }
.weather-light-rain { background-color: #17a2b8; color: white; }
.weather-heavy-rain { background-color: #007bff; color: white; }
.weather-windy { background-color: #6f42c1; color: white; }
.weather-hot-&-dry { background-color: #fd7e14; color: white; }

.stage-badge {
    background-color: #28a745;
    color: white;
    font-size: 11px;
    padding: 4px 8px;
}

.irrigation-badge {
    font-size: 11px;
    padding: 4px 8px;
}

.irrigation-not-required { background-color: #28a745; color: white; }
.irrigation-required-soon { background-color: #ffc107; color: #212529; }
.irrigation-required-today { background-color: #fd7e14; color: white; }
.irrigation-urgent { background-color: #dc3545; color: white; }
.irrigation-recently-irrigated { background-color: #17a2b8; color: white; }

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
}

.action-buttons .btn {
    padding: 4px 8px;
    font-size: 12px;
}

/* No Data State */
.no-data {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.no-data i {
    font-size: 48px;
    margin-bottom: 20px;
    color: #dee2e6;
}

.no-data h4 {
    margin-bottom: 10px;
    color: #495057;
}

.no-data p {
    margin-bottom: 30px;
    font-size: 16px;
}

/* Modal Styles */
.modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.modal-body {
    padding: 20px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .filter-buttons {
        flex-direction: column;
    }
    
    .export-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 2px;
    }
    
    .observations-table {
        font-size: 14px;
    }
    
    .observations-table th,
    .observations-table td {
        padding: 8px 5px;
    }
}

/* Print Styles */
@media print {
    .page-header,
    .filters-section,
    .export-buttons,
    .action-buttons {
        display: none !important;
    }
    
    .observations-section {
        box-shadow: none;
        border: 1px solid #000;
    }
}
</style>

<?php include 'includes/footer.php'; ?>

<script>
// View observation details
function viewObservation(id) {
    // Load observation details via AJAX
    fetch('ajax/get_observation_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('observationDetails').innerHTML = data.html;
                document.getElementById('editObservationBtn').onclick = () => editObservation(id);
                $('#observationModal').modal('show');
            } else {
                alert('Error loading observation details: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading observation details');
        });
}

// Edit observation
function editObservation(id) {
    window.location.href = 'edit_field_observation.php?id=' + id;
}

// Delete observation
function deleteObservation(id) {
    if (confirm('Are you sure you want to delete this observation? This action cannot be undone.')) {
        fetch('ajax/delete_observation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Observation deleted successfully');
                location.reload();
            } else {
                alert('Error deleting observation: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting observation');
        });
    }
}

// Clear filters
function clearFilters() {
    window.location.href = 'supervisor_weather_reports.php';
}

// Export functions
function exportToPDF() {
    window.print();
}

function exportToExcel() {
    // Create a simple CSV export
    const table = document.querySelector('.observations-table');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('th, td');
        const rowData = [];
        cols.forEach((col, index) => {
            if (index < cols.length - 1) { // Skip actions column
                rowData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
            }
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'field-observations-' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Auto-refresh every 5 minutes for real-time updates
setInterval(() => {
    // Only refresh if user hasn't interacted recently
    if (document.hidden === false && 
        (Date.now() - window.lastUserActivity) > 300000) { // 5 minutes
        location.reload();
    }
}, 300000);

// Track user activity
window.lastUserActivity = Date.now();
document.addEventListener('click', () => {
    window.lastUserActivity = Date.now();
});
document.addEventListener('keypress', () => {
    window.lastUserActivity = Date.now();
});
</script>