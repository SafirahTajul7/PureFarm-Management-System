<?php
session_start();
require_once 'includes/auth.php';
auth()->checkSupervisor();  // Ensure only supervisors can access
require_once 'includes/db.php';

// Handle form submissions for tasks like updating animal records, health status, etc.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_health'])) {
        try {
            // Update animal health status
            $stmt = $pdo->prepare("UPDATE animals SET health_status = ? WHERE id = ?");
            $stmt->execute([$_POST['health_status'], $_POST['animal_id']]);
            
            // Add a health record entry
            $stmt = $pdo->prepare("INSERT INTO health_records (animal_id, date, `condition`, treatment, vet_name) 
                                VALUES (?, CURRENT_DATE(), ?, ?, ?)");
            $stmt->execute([
                $_POST['animal_id'],
                $_POST['health_status'],
                $_POST['treatment'] ?? '',
                $_POST['vet_name'] ?? ''
            ]);
            
            $_SESSION['success'] = "Animal health status updated successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error updating animal health status: " . $e->getMessage();
        }
        header('Location: supervisor_animal_management.php');
        exit();
    }
    
    if (isset($_POST['update_vaccination_status'])) {
        try {
            $stmt = $pdo->prepare("UPDATE animals SET vaccination_status = ? WHERE id = ?");
            $stmt->execute([$_POST['vaccination_status'], $_POST['animal_id']]);
            
            $_SESSION['success'] = "Animal vaccination status updated successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error updating vaccination status: " . $e->getMessage();
        }
        header('Location: supervisor_animal_management.php');
        exit();
    }
    
    if (isset($_POST['add_feeding_record'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO feeding_schedules (animal_id, food_type, quantity, frequency, special_diet, notes) 
                                 VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['animal_id'],
                $_POST['food_type'],
                $_POST['quantity'],
                $_POST['frequency'],
                $_POST['special_diet'] ?? '',
                $_POST['notes'] ?? ''
            ]);
            $_SESSION['success'] = "Feeding schedule added successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error adding feeding schedule: " . $e->getMessage();
        }
        header('Location: supervisor_animal_management.php');
        exit();
    }
    
    if (isset($_POST['report_incident'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO incidents (type, description, date_reported, status, severity, reported_by, affected_area, resolution_notes) 
                                 VALUES (?, ?, CURRENT_DATE(), 'open', ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['type'],
                $_POST['description'],
                $_POST['severity'],
                $_SESSION['user_name'] ?? 'Supervisor',
                $_POST['affected_area'],
                $_POST['resolution_notes'] ?? ''
            ]);
            $_SESSION['success'] = "Incident reported successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error reporting incident: " . $e->getMessage();
        }
        header('Location: supervisor_animal_management.php');
        exit();
    }

    if (isset($_POST['resolve_incident'])) {
        try {
            $stmt = $pdo->prepare("UPDATE incidents SET status = 'resolved', resolution_notes = ? WHERE id = ?");
            $stmt->execute([$_POST['resolution_notes'], $_POST['incident_id']]);
            $_SESSION['success'] = "Incident marked as resolved successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error resolving incident: " . $e->getMessage();
        }
        header('Location: supervisor_animal_management.php');
        exit();
    }
}

// Initialize arrays for dashboard data
$animals = [];
$health_status = [];
$animals_by_status = [];
$upcoming_vaccinations = [];
$feeding_schedules = [];
$recent_incidents = [];
$vaccination_overview = [];

try {
    // Get all animals with basic information including vaccination data
    $animals = $pdo->query("
        SELECT a.*, 
               COUNT(DISTINCT hr.id) as health_record_count,
               COUNT(DISTINCT fs.id) as feeding_schedule_count,
               COUNT(DISTINCT v.id) as vaccination_count,
               MAX(v.date) as last_vaccination_date,
               GROUP_CONCAT(DISTINCT CONCAT(v.type, ' (', v.administered_by, ')') SEPARATOR ', ') as vaccination_history
        FROM animals a
        LEFT JOIN health_records hr ON a.id = hr.animal_id
        LEFT JOIN feeding_schedules fs ON a.id = fs.animal_id
        LEFT JOIN vaccinations v ON a.id = v.animal_id
        GROUP BY a.id
        ORDER BY a.species, a.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Health Overview - Count animals by health status
    $health_status_query = $pdo->query("
        SELECT health_status, COUNT(*) as count
        FROM animals
        GROUP BY health_status
    ");
    $health_status = $health_status_query->fetchAll(PDO::FETCH_KEY_PAIR);

    // Vaccination Overview - Count animals by vaccination status
    $vaccination_status_query = $pdo->query("
        SELECT vaccination_status, COUNT(*) as count
        FROM animals
        GROUP BY vaccination_status
    ");
    $vaccination_overview = $vaccination_status_query->fetchAll(PDO::FETCH_KEY_PAIR);

    // Group animals by health status using the existing $animals data
    foreach ($animals as $animal) {
        $status = $animal['health_status'];
        if (!isset($animals_by_status[$status])) {
            $animals_by_status[$status] = [];
        }
        $animals_by_status[$status][] = $animal;
    }

    // Upcoming vaccinations within next 14 days
    $upcoming_vaccinations = $pdo->query("
        SELECT a.id, a.animal_id as animal_code, a.species, a.breed, v.date, v.next_due, v.type as vaccine_type, v.administered_by
        FROM vaccinations v
        JOIN animals a ON v.animal_id = a.id
        WHERE v.next_due <= DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY)
        ORDER BY v.next_due ASC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Recent feeding schedules
    $feeding_schedules = $pdo->query("
        SELECT fs.*, a.animal_id as animal_code, a.species, a.breed
        FROM feeding_schedules fs
        JOIN animals a ON fs.animal_id = a.id
        ORDER BY fs.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent incidents
    $recent_incidents = $pdo->query("
        SELECT *
        FROM incidents
        WHERE status = 'open'
        ORDER BY date_reported DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error fetching supervisor animal management data: " . $e->getMessage());
    $_SESSION['error'] = "Error loading animal management data";
}

$pageTitle = 'Animal Management - Supervisor';
include 'includes/header.php';
?>

<div class="main-content">
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h2><i class="fas fa-paw"></i> Animal Management</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" data-toggle="modal" data-target="#reportIncidentModal">
                <i class="fas fa-exclamation-triangle"></i> Report Incident
            </button>
        </div>
    </div>

    <!-- Enhanced Health Status Overview -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-heartbeat"></i> Health Status Overview</h3>
            <small class="text-muted">Click on any status card to view animals in that category</small>
        </div>
        <div class="card-body">
            <div class="row">
                <?php 
                $statusColors = [
                    'Healthy' => 'success',
                    'Sick' => 'danger',
                    'Injured' => 'warning',
                    'Quarantine' => 'info',
                    'Treatment' => 'primary'
                ];
                
                $statusIcons = [
                    'Healthy' => 'fa-heart',
                    'Sick' => 'fa-thermometer-three-quarters',
                    'Injured' => 'fa-band-aid',
                    'Quarantine' => 'fa-shield-alt',
                    'Treatment' => 'fa-stethoscope'
                ];
                
                foreach ($health_status as $status => $count): 
                    $color = $statusColors[$status] ?? 'secondary';
                    $icon = $statusIcons[$status] ?? 'fa-question';
                    $statusId = strtolower(str_replace(' ', '_', $status));
                ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="health-stat-card bg-light-<?php echo $color; ?> clickable-card" 
                             data-toggle="collapse" 
                             data-target="#collapse-<?php echo $statusId; ?>" 
                             aria-expanded="false">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4><i class="fas <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($status); ?></h4>
                                    <p class="count"><?php echo $count; ?></p>
                                </div>
                                <i class="fas fa-chevron-down toggle-icon"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Collapsible sections for each health status -->
            <?php foreach ($health_status as $status => $count): 
                if ($count == 0) continue; // Skip if no animals in this status
                $color = $statusColors[$status] ?? 'secondary';
                $statusId = strtolower(str_replace(' ', '_', $status));
            ?>
                <div class="collapse mt-3" id="collapse-<?php echo $statusId; ?>">
                    <div class="card border-<?php echo $color; ?>">
                        <div class="card-header bg-light-<?php echo $color; ?>">
                            <h5 class="mb-0">
                                <strong><?php echo htmlspecialchars($status); ?> Animals (<?php echo $count; ?>)</strong>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($animals_by_status[$status]) && !empty($animals_by_status[$status])): ?>
                                <div class="row">
                                    <?php foreach ($animals_by_status[$status] as $animal): ?>
                                        <div class="col-md-4 col-sm-6 mb-3">
                                            <div class="animal-card border-<?php echo $color; ?>">
                                                <div class="animal-header">
                                                    <strong><?php echo htmlspecialchars($animal['animal_id']); ?></strong>
                                                    <span class="badge badge-<?php echo $color; ?> badge-sm">
                                                        <?php echo htmlspecialchars($animal['health_status']); ?>
                                                    </span>
                                                </div>
                                                <div class="animal-details">
                                                    <p class="mb-1">
                                                        <i class="fas fa-paw text-muted"></i> 
                                                        <strong><?php echo htmlspecialchars($animal['species']); ?></strong>
                                                    </p>
                                                    <p class="mb-1">
                                                        <i class="fas fa-dna text-muted"></i> 
                                                        <?php echo htmlspecialchars($animal['breed']); ?>
                                                    </p>
                                                    <?php if (!empty($animal['location'])): ?>
                                                    <p class="mb-1">
                                                        <i class="fas fa-map-marker-alt text-muted"></i> 
                                                        <?php echo htmlspecialchars($animal['location']); ?>
                                                    </p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($animal['date_of_birth'])): ?>
                                                    <p class="mb-1">
                                                        <i class="fas fa-birthday-cake text-muted"></i> 
                                                        <?php 
                                                        $birthDate = new DateTime($animal['date_of_birth']);
                                                        $today = new DateTime();
                                                        $age = $today->diff($birthDate);
                                                        echo $birthDate->format('M d, Y') . ' (' . $age->y . 'y ' . $age->m . 'm)';
                                                        ?>
                                                    </p>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Vaccination Status Display -->
                                                    <?php
                                                    $vaccinationColors = [
                                                        'vaccinated' => 'success',
                                                        'not_vaccinated' => 'danger',
                                                        'partially_vaccinated' => 'warning',
                                                        'overdue' => 'danger'
                                                    ];
                                                    $vaccinationColor = $vaccinationColors[$animal['vaccination_status']] ?? 'secondary';
                                                    ?>
                                                    <p class="mb-1">
                                                        <i class="fas fa-syringe text-muted"></i> 
                                                        <span class="badge badge-<?php echo $vaccinationColor; ?> badge-sm">
                                                            <?php echo ucfirst(str_replace('_', ' ', $animal['vaccination_status'] ?? 'not_vaccinated')); ?>
                                                        </span>
                                                    </p>
                                                    
                                                    <!-- Records count information -->
                                                    <div class="mt-2">
                                                        <span class="badge badge-light">
                                                            <i class="fas fa-heartbeat"></i> <?php echo $animal['health_record_count']; ?> records
                                                        </span>
                                                        <span class="badge badge-light">
                                                            <i class="fas fa-utensils"></i> <?php echo $animal['feeding_schedule_count']; ?> feeding
                                                        </span>
                                                        <span class="badge badge-light">
                                                            <i class="fas fa-syringe"></i> <?php echo $animal['vaccination_count']; ?> vaccines
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">No animals found with <?php echo strtolower($status); ?> status.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Vaccination Status Overview -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-syringe"></i> Vaccination Status Overview</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <?php 
                $vaccinationColors = [
                    'vaccinated' => 'success',
                    'not_vaccinated' => 'danger',
                    'partially_vaccinated' => 'warning',
                    'overdue' => 'danger'
                ];
                
                $vaccinationIcons = [
                    'vaccinated' => 'fa-check-circle',
                    'not_vaccinated' => 'fa-times-circle',
                    'partially_vaccinated' => 'fa-exclamation-circle',
                    'overdue' => 'fa-clock'
                ];
                
                foreach ($vaccination_overview as $status => $count): 
                    $color = $vaccinationColors[$status] ?? 'secondary';
                    $icon = $vaccinationIcons[$status] ?? 'fa-question';
                ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="health-stat-card bg-light-<?php echo $color; ?>"> 
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4><i class="fas <?php echo $icon; ?>"></i> <?php echo ucfirst(str_replace('_', ' ', $status)); ?></h4>
                                    <p class="count"><?php echo $count; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Upcoming Vaccinations -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-syringe"></i> Upcoming Vaccinations</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($upcoming_vaccinations)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Animal ID</th>
                                        <th>Species</th>
                                        <th>Vaccine Type</th>
                                        <th>Due Date</th>
                                        <th>Administered By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_vaccinations as $vacc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($vacc['animal_code']); ?></td>
                                        <td><?php echo htmlspecialchars($vacc['species']); ?></td>
                                        <td><?php echo htmlspecialchars($vacc['vaccine_type']); ?></td>
                                        <td>
                                            <?php 
                                            $dueDate = new DateTime($vacc['next_due']);
                                            $today = new DateTime();
                                            $interval = $today->diff($dueDate);
                                            $daysRemaining = $interval->days;
                                            $pastDue = $today > $dueDate;
                                            
                                            $badgeClass = $pastDue ? 'badge-danger' : ($daysRemaining <= 7 ? 'badge-warning' : 'badge-info');
                                            echo date('M d, Y', strtotime($vacc['next_due']));
                                            echo ' <span class="badge ' . $badgeClass . '">';
                                            echo $pastDue ? 'Overdue' : ($daysRemaining . ' days left');
                                            echo '</span>';
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($vacc['administered_by']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">No upcoming vaccinations in the next 14 days.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Enhanced Recent Incidents - NOW SIDE BY SIDE -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-circle"></i> Recent Incidents</h3>
                    <small class="text-muted">View details and manage incident resolution</small>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_incidents)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Date</th>
                                        <th>Severity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_incidents as $incident): 
                                        $severityClass = '';
                                        switch(strtolower($incident['severity'])) {
                                            case 'high': $severityClass = 'badge-danger'; break;
                                            case 'medium': $severityClass = 'badge-warning'; break;
                                            default: $severityClass = 'badge-info';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-<?php echo $incident['type'] == 'illness' ? 'thermometer-three-quarters' : ($incident['type'] == 'injury' ? 'band-aid' : ($incident['type'] == 'equipment' ? 'tools' : 'exclamation-triangle')); ?>"></i>
                                            <span class="d-none d-md-inline"><?php echo ucfirst(htmlspecialchars($incident['type'])); ?></span>
                                        </td>
                                        <td>
                                            <span class="incident-description" data-full-description="<?php echo htmlspecialchars($incident['description']); ?>">
                                                <?php echo htmlspecialchars(substr($incident['description'], 0, 30)) . (strlen($incident['description']) > 30 ? '...' : ''); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d', strtotime($incident['date_reported'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $severityClass; ?> badge-sm">
                                                <?php echo strtoupper(substr($incident['severity'], 0, 1)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm" role="group">
                                                <button class="btn btn-sm btn-info view-incident" 
                                                        data-id="<?php echo $incident['id']; ?>"
                                                        data-type="<?php echo htmlspecialchars($incident['type']); ?>"
                                                        data-description="<?php echo htmlspecialchars($incident['description']); ?>"
                                                        data-severity="<?php echo htmlspecialchars($incident['severity']); ?>"
                                                        data-area="<?php echo htmlspecialchars($incident['affected_area']); ?>"
                                                        data-reporter="<?php echo htmlspecialchars($incident['reported_by']); ?>"
                                                        data-date="<?php echo date('M d, Y', strtotime($incident['date_reported'])); ?>"
                                                        data-notes="<?php echo htmlspecialchars($incident['resolution_notes'] ?? ''); ?>"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($incident['status'] == 'open'): ?>
                                                <button class="btn btn-sm btn-success resolve-incident" 
                                                        data-id="<?php echo $incident['id']; ?>"
                                                        data-type="<?php echo htmlspecialchars($incident['type']); ?>"
                                                        data-description="<?php echo htmlspecialchars($incident['description']); ?>"
                                                        title="Mark as Resolved">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <?php else: ?>
                                                <span class="btn btn-sm btn-secondary disabled">
                                                    <i class="fas fa-check-circle"></i>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                            <p class="text-muted mb-0">No open incidents</p>
                            <small class="text-muted">All clear!</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Incident Details Section (Initially Hidden) - FULL WIDTH BELOW THE ROW -->
    <div id="incident-details-section" class="mb-4" style="display: none;">
        <div class="card border-info">
            <div class="card-header bg-light-info">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle"></i> Incident Details
                    <button type="button" class="close float-right" id="close-incident-details">
                        <span>&times;</span>
                    </button>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="detail-item">
                            <strong>Incident ID:</strong>
                            <span id="detail-id"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Type:</strong>
                            <span id="detail-type"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Severity:</strong>
                            <span id="detail-severity"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Affected Area:</strong>
                            <span id="detail-area"></span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="detail-item">
                            <strong>Reported By:</strong>
                            <span id="detail-reporter"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Date Reported:</strong>
                            <span id="detail-date"></span>
                        </div>
                        <div class="detail-item">
                            <strong>Status:</strong>
                            <span id="detail-status"></span>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="detail-item">
                            <strong>Description:</strong>
                            <div id="detail-description" class="mt-2 p-3 bg-light border-left border-info"></div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3" id="resolution-notes-section" style="display: none;">
                    <div class="col-12">
                        <div class="detail-item">
                            <strong>Resolution Notes:</strong>
                            <div id="detail-resolution-notes" class="mt-2 p-3 bg-light border-left border-success"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feeding & Nutrition Plan -->
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h3><i class="fas fa-utensils"></i> Feeding & Nutrition</h3>
                <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#addFeedingModal">
                    <i class="fas fa-plus"></i> Add Feeding Plan
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($feeding_schedules)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Animal ID</th>
                                <th>Species</th>
                                <th>Food Type</th>
                                <th>Quantity</th>
                                <th>Frequency</th>
                                <th>Special Diet</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeding_schedules as $schedule): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($schedule['animal_code']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['species']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['food_type']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['frequency']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['special_diet']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary edit-feeding" data-id="<?php echo $schedule['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">No feeding schedules have been set up yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Animal List & Health Status -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Animal Records & Management</h3>
            <small class="text-muted">Use this table to update health status and vaccination status</small>
        </div>
        <div class="card-body">
            <?php if (!empty($animals)): ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="animalsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Species</th>
                                <th>Breed</th>
                                <th>Health Status</th>
                                <th>Vaccination Status</th>
                                <th>Last Vaccination</th>
                                <th>Records</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($animals as $animal): 
                                $statusClass = '';
                                switch(strtolower($animal['health_status'])) {
                                    case 'healthy': $statusClass = 'badge-success'; break;
                                    case 'sick': $statusClass = 'badge-danger'; break;
                                    case 'injured': $statusClass = 'badge-warning'; break;
                                    case 'quarantine': $statusClass = 'badge-info'; break;
                                    default: $statusClass = 'badge-secondary';
                                }
                                
                                $vaccinationColors = [
                                    'vaccinated' => 'success',
                                    'not_vaccinated' => 'danger',
                                    'partially_vaccinated' => 'warning',
                                    'overdue' => 'danger'
                                ];
                                $vaccinationColor = $vaccinationColors[$animal['vaccination_status']] ?? 'secondary';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($animal['animal_id']); ?></td>
                                <td><?php echo htmlspecialchars($animal['species']); ?></td>
                                <td><?php echo htmlspecialchars($animal['breed']); ?></td>
                                <td>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($animal['health_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $vaccinationColor; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $animal['vaccination_status'] ?? 'not_vaccinated')); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($animal['last_vaccination_date'])): ?>
                                        <?php echo date('M d, Y', strtotime($animal['last_vaccination_date'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-primary">
                                        <i class="fas fa-heartbeat"></i> <?php echo $animal['health_record_count']; ?>
                                    </span>
                                    <span class="badge badge-success">
                                        <i class="fas fa-utensils"></i> <?php echo $animal['feeding_schedule_count']; ?>
                                    </span>
                                    <span class="badge badge-info">
                                        <i class="fas fa-syringe"></i> <?php echo $animal['vaccination_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-primary update-health" data-id="<?php echo $animal['id']; ?>" 
                                                data-animal-id="<?php echo htmlspecialchars($animal['animal_id']); ?>"
                                                data-species="<?php echo htmlspecialchars($animal['species']); ?>"
                                                data-breed="<?php echo htmlspecialchars($animal['breed']); ?>"
                                                data-status="<?php echo htmlspecialchars($animal['health_status']); ?>"
                                                title="Update Health">
                                            <i class="fas fa-heartbeat"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning update-vaccination" data-id="<?php echo $animal['id']; ?>" 
                                                data-animal-id="<?php echo htmlspecialchars($animal['animal_id']); ?>"
                                                data-vaccination-status="<?php echo htmlspecialchars($animal['vaccination_status'] ?? 'not_vaccinated'); ?>"
                                                data-vaccination-history="<?php echo htmlspecialchars($animal['vaccination_history'] ?? 'No vaccination history'); ?>"
                                                title="Update Vaccination">
                                            <i class="fas fa-syringe"></i>
                                        </button>
                                        <a href="supervisor_animal_details.php?id=<?php echo $animal['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">No animals found in the database.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Update Health Status Modal -->
<div class="modal fade" id="updateHealthModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Health Status</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="animal_id" id="update_animal_id">
                    
                    <div class="form-group">
                        <label>Animal ID:</label>
                        <input type="text" id="display_animal_id" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Species/Breed:</label>
                        <input type="text" id="display_species_breed" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Current Health Status:</label>
                        <input type="text" id="current_status" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>New Health Status:</label>
                        <select name="health_status" class="form-control" required>
                            <option value="">Select Status</option>
                            <option value="Healthy">Healthy</option>
                            <option value="Sick">Sick</option>
                            <option value="Injured">Injured</option>
                            <option value="Quarantine">Quarantine</option>
                            <option value="Treatment">Treatment</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Treatment (if applicable):</label>
                        <input type="text" name="treatment" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Veterinarian:</label>
                        <select name="vet_name" class="form-control">
                            <option value="">Select Veterinarian</option>
                            <option value="Dr. Sarah Lee">Dr. Sarah Lee</option>
                            <option value="Dr. Ahmad Kamal">Dr. Ahmad Kamal</option>
                            <option value="Dr. Ahmed Khan">Dr. Ahmed Khan</option>
                            <option value="Self-Treatment">Self-Treatment</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_health" class="btn btn-primary">Update Health</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Vaccination Status Modal -->
<div class="modal fade" id="updateVaccinationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Vaccination Status</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="animal_id" id="vaccination_animal_id">
                    
                    <div class="form-group">
                        <label>Animal ID:</label>
                        <input type="text" id="vaccination_display_animal_id" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Current Vaccination Status:</label>
                        <input type="text" id="current_vaccination_status" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Vaccination History:</label>
                        <textarea id="vaccination_history_display" class="form-control" rows="3" readonly></textarea>
                        <small class="text-muted">This shows all vaccines administered by veterinarians</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Update Vaccination Status:</label>
                        <select name="vaccination_status" class="form-control" required>
                            <option value="">Select Status</option>
                            <option value="vaccinated">Vaccinated (Up to date)</option>
                            <option value="not_vaccinated">Not Vaccinated</option>
                            <option value="partially_vaccinated">Partially Vaccinated</option>
                            <option value="overdue">Overdue for Vaccination</option>
                        </select>
                        <small class="text-muted">Use this to manually update vaccination status based on your assessment</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Vaccination records are managed by administrators. This status update is for tracking and monitoring purposes only.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_vaccination_status" class="btn btn-warning">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Feeding Schedule Modal -->
<div class="modal fade" id="addFeedingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Feeding Schedule</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Animal:</label>
                        <select name="animal_id" class="form-control" required>
                            <option value="">Select Animal</option>
                            <?php foreach ($animals as $animal): ?>
                                <option value="<?php echo $animal['id']; ?>">
                                    <?php echo htmlspecialchars($animal['animal_id'] . ' - ' . $animal['species'] . ' (' . $animal['breed'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Food Type:</label>
                        <input type="text" name="food_type" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity:</label>
                        <input type="text" name="quantity" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Frequency:</label>
                        <select name="frequency" class="form-control" required>
                            <option value="daily">Daily</option>
                            <option value="twice_daily">Twice Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Special Diet (if applicable):</label>
                        <textarea name="special_diet" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes:</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_feeding_record" class="btn btn-primary">Add Feeding Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Feeding Modal -->
<div class="modal fade" id="editFeedingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Feeding Schedule</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST" action="update_feeding.php">
                <div class="modal-body">
                    <input type="hidden" name="feeding_id" id="edit_feeding_id">
                    
                    <div class="form-group">
                        <label>Animal ID:</label>
                        <input type="text" id="edit_feeding_animal_code" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Food Type:</label>
                        <input type="text" name="food_type" id="edit_food_type" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity:</label>
                        <input type="text" name="quantity" id="edit_quantity" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Frequency:</label>
                        <select name="frequency" id="edit_frequency" class="form-control" required>
                            <option value="daily">Daily</option>
                            <option value="twice_daily">Twice Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Special Diet (if applicable):</label>
                        <textarea name="special_diet" id="edit_special_diet" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes:</label>
                        <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_feeding" class="btn btn-primary">Update Feeding</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Report Incident Modal -->
<div class="modal fade" id="reportIncidentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Report Incident</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Incident Type:</label>
                        <select name="type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="illness">Animal Illness</option>
                            <option value="injury">Animal Injury</option>
                            <option value="equipment">Equipment Failure</option>
                            <option value="facility">Facility Issue</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Description:</label>
                        <textarea name="description" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Severity:</label>
                        <select name="severity" class="form-control" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Affected Area:</label>
                        <input type="text" name="affected_area" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Resolution Notes (if any):</label>
                        <textarea name="resolution_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="report_incident" class="btn btn-primary">Report Incident</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Resolve Incident Modal -->
<div class="modal fade" id="resolveIncidentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle text-success"></i> Resolve Incident
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="incident_id" id="resolve_incident_id">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Incident Details:</strong>
                        <div class="mt-2">
                            <strong>Type:</strong> <span id="resolve_incident_type"></span><br>
                            <strong>Description:</strong> <span id="resolve_incident_description"></span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Resolution Notes: <span class="text-danger">*</span></label>
                        <textarea name="resolution_notes" class="form-control" rows="4" required 
                                placeholder="Please describe how this incident was resolved, what actions were taken, and any follow-up measures..."></textarea>
                        <small class="form-text text-muted">
                            Provide detailed information about the resolution for future reference and reporting.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="confirm_resolution" required>
                            <label class="custom-control-label" for="confirm_resolution">
                                I confirm that this incident has been fully resolved and no further action is required.
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="resolve_incident" class="btn btn-success">
                        <i class="fas fa-check"></i> Mark as Resolved
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Enhanced styling for clickable health status cards */
.clickable-card {
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.clickable-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.toggle-icon {
    transition: transform 0.3s ease;
    color: #6c757d;
}

.clickable-card[aria-expanded="true"] .toggle-icon {
    transform: rotate(180deg);
}

/* Animal card styling */
.animal-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    background: white;
    transition: all 0.2s ease;
    height: 100%;
}

.animal-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.animal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
}

.animal-header strong {
    font-size: 1.1rem;
    color: #333;
}

.animal-details {
    margin-bottom: 15px;
}

.animal-details p {
    font-size: 0.85rem;
    color: #666;
    display: flex;
    align-items: center;
    gap: 8px;
}

.animal-details i {
    width: 14px;
    text-align: center;
}

.animal-actions {
    display: flex;
    gap: 5px;
    justify-content: space-between;
}

.animal-actions .btn {
    flex: 1;
    font-size: 0.8rem;
    padding: 5px 8px;
}

/* Badge styling */
.badge-sm {
    font-size: 0.7rem;
    padding: 3px 8px;
}

/* Status-specific background colors for collapsed sections */
.bg-light-success {
    background-color: rgba(40, 167, 69, 0.1);
    border-left: 4px solid #28a745;
}
.bg-light-danger {
    background-color: rgba(220, 53, 69, 0.1);
    border-left: 4px solid #dc3545;
}
.bg-light-warning {
    background-color: rgba(255, 193, 7, 0.1);
    border-left: 4px solid #ffc107;
}
.bg-light-info {
    background-color: rgba(23, 162, 184, 0.1);
    border-left: 4px solid #17a2b8;
}
.bg-light-primary {
    background-color: rgba(0, 123, 255, 0.1);
    border-left: 4px solid #007bff;
}
.bg-light-secondary {
    background-color: rgba(108, 117, 125, 0.1);
    border-left: 4px solid #6c757d;
}

.health-stat-card {
    padding: 15px;
    border-radius: 8px;
    transition: transform 0.2s;
}

.health-stat-card h4 {
    margin-bottom: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.health-stat-card .count {
    font-size: 2rem;
    font-weight: bold;
    margin: 0;
}

.badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Button group styling */
.btn-group .btn {
    margin-right: 0;
    border-radius: 0;
}

.btn-group .btn:first-child {
    border-top-left-radius: 0.25rem;
    border-bottom-left-radius: 0.25rem;
}

.btn-group .btn:last-child {
    border-top-right-radius: 0.25rem;
    border-bottom-right-radius: 0.25rem;
}

/* Enhanced incident section styling */
.incident-description {
    cursor: pointer;
}

.incident-description:hover {
    text-decoration: underline;
    color: #007bff;
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

#incident-details-section {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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

/* Button group enhancements */
.btn-group .btn, .btn-group-vertical .btn {
    font-size: 0.8rem;
    padding: 5px 10px;
}

/* Status badge improvements */
.badge {
    font-size: 0.75rem;
    padding: 4px 8px;
}

/* Table improvements */
.table td {
    vertical-align: middle;
}

/* Modal improvements */
.modal-header .text-success {
    color: #28a745 !important;
}

.custom-control-label {
    font-size: 0.9rem;
    color: #495057;
}

.btn-primary {
    background-color: #4CAF50;
    border-color: #4CAF50;
}

.btn-primary:hover {
    background-color: #45a049;
    border-color: #45a049;
}

.d-flex {
    display: flex;
}

.justify-content-between {
    justify-content: space-between;
}

.align-items-center {
    align-items: center;
}

/* Animation for collapse */
.collapsing {
    transition: height 0.35s ease;
}

.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 8px;
    border: none;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #eee;
    padding: 15px 20px;
}

.card-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.1rem;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .animal-card {
        margin-bottom: 15px;
    }
    
    .animal-actions {
        flex-direction: column;
    }
    
    .animal-actions .btn {
        margin-bottom: 5px;
    }
    
    .health-stat-card .count {
        font-size: 1.5rem;
    }
    
    .card-header {
        flex-direction: column;
    }
    
    .card-header h3 {
        margin-bottom: 10px;
    }
    
    .btn-group, .btn-group-vertical {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-group .btn, .btn-group-vertical .btn {
        border-radius: 0.25rem !important;
        margin-bottom: 2px;
        padding: 3px 6px;
        font-size: 0.75rem;
    }
    
    .badge-sm {
        font-size: 0.6rem;
        padding: 2px 4px;
    }
    
    .table-sm td {
        padding: 0.3rem;
        font-size: 0.8rem;
    }
    
    .detail-item {
        flex-direction: column;
    }
    
    .detail-item strong {
        min-width: auto;
        margin-bottom: 5px;
    }
}
</style>

<script>
$(document).ready(function() {
    // Initialize DataTable on animals table
    $('#animalsTable').DataTable({
        "pageLength": 10,
        "order": [[0, "asc"]],
        "responsive": true
    });
    
    // Store which sections were open before form submission
    function saveCollapsedState() {
        const openSections = [];
        $('.collapse.show').each(function() {
            openSections.push(this.id);
        });
        localStorage.setItem('openHealthSections', JSON.stringify(openSections));
    }
    
    // Restore collapsed state after page load
    function restoreCollapsedState() {
        const openSections = JSON.parse(localStorage.getItem('openHealthSections') || '[]');
        openSections.forEach(function(sectionId) {
            $('#' + sectionId).collapse('show');
            // Update the toggle icon
            $('[data-target="#' + sectionId + '"] .toggle-icon')
                .removeClass('fa-chevron-down')
                .addClass('fa-chevron-up');
        });
    }
    
    // Restore state on page load
    restoreCollapsedState();
    
    // Save state before form submission
    $('form').on('submit', function() {
        saveCollapsedState();
    });
    
    // Handle the collapse toggle for health status cards
    $('.clickable-card').on('click', function() {
        const target = $(this).data('target');
        const icon = $(this).find('.toggle-icon');
        
        // Toggle the collapse
        $(target).collapse('toggle');
    });
    
    // Update toggle icons when collapse sections change
    $('.collapse').on('shown.bs.collapse', function() {
        const cardId = $(this).attr('id');
        $('[data-target="#' + cardId + '"] .toggle-icon')
            .removeClass('fa-chevron-down')
            .addClass('fa-chevron-up');
    });
    
    $('.collapse').on('hidden.bs.collapse', function() {
        const cardId = $(this).attr('id');
        $('[data-target="#' + cardId + '"] .toggle-icon')
            .removeClass('fa-chevron-up')
            .addClass('fa-chevron-down');
    });
    
    // Handle update health button click from anywhere (main table or collapsed sections)
    $(document).on('click', '.update-health', function(e) {
        e.stopPropagation(); // Prevent triggering the card collapse
        
        const animalId = $(this).data('id');
        const animalCode = $(this).data('animal-id');
        const species = $(this).data('species');
        const breed = $(this).data('breed');
        const status = $(this).data('status');
        
        $('#update_animal_id').val(animalId);
        $('#display_animal_id').val(animalCode);
        $('#display_species_breed').val(species + ' / ' + breed);
        $('#current_status').val(status);
        
        $('#updateHealthModal').modal('show');
    });
    
    // Handle update vaccination button click
    $(document).on('click', '.update-vaccination', function(e) {
        e.stopPropagation(); // Prevent triggering the card collapse
        
        const animalId = $(this).data('id');
        const animalCode = $(this).data('animal-id');
        const vaccinationStatus = $(this).data('vaccination-status');
        const vaccinationHistory = $(this).data('vaccination-history');
        
        $('#vaccination_animal_id').val(animalId);
        $('#vaccination_display_animal_id').val(animalCode);
        $('#current_vaccination_status').val(vaccinationStatus.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()));
        $('#vaccination_history_display').val(vaccinationHistory || 'No vaccination history available');
        
        // Set current vaccination status as selected
        $('select[name="vaccination_status"]').val(vaccinationStatus);
        
        $('#updateVaccinationModal').modal('show');
    });
    
    // Handle edit feeding button
    $('.edit-feeding').click(function() {
        const id = $(this).data('id');
        
        // Get feeding data using AJAX
        $.ajax({
            url: 'get_feeding_data.php',
            type: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function(data) {
                $('#edit_feeding_id').val(data.id);
                $('#edit_feeding_animal_code').val(data.animal_code);
                $('#edit_food_type').val(data.food_type);
                $('#edit_quantity').val(data.quantity);
                $('#edit_frequency').val(data.frequency);
                $('#edit_special_diet').val(data.special_diet);
                $('#edit_notes').val(data.notes);
                
                $('#editFeedingModal').modal('show');
            },
            error: function() {
                alert('Error fetching feeding data. Please try again.');
            }
        });
    });
    
    // Add smooth scrolling when collapse sections open
    $('.collapse').on('shown.bs.collapse', function() {
        $('html, body').animate({
            scrollTop: $(this).offset().top - 100
        }, 500);
    });
    
    // Clear saved state when user manually closes all sections
    $('.collapse').on('hidden.bs.collapse', function() {
        if ($('.collapse.show').length === 0) {
            localStorage.removeItem('openHealthSections');
        }
    });

    // Handle view incident details
    $('.view-incident').click(function() {
        const id = $(this).data('id');
        const type = $(this).data('type');
        const description = $(this).data('description');
        const severity = $(this).data('severity');
        const area = $(this).data('area');
        const reporter = $(this).data('reporter');
        const date = $(this).data('date');
        const notes = $(this).data('notes');
        
        // Populate incident details
        $('#detail-id').text('#' + id);
        $('#detail-type').html('<i class="fas fa-' + getIncidentIcon(type) + '"></i> ' + type.charAt(0).toUpperCase() + type.slice(1));
        $('#detail-severity').html('<span class="badge badge-' + getSeverityClass(severity) + '">' + severity.toUpperCase() + '</span>');
        $('#detail-area').text(area);
        $('#detail-reporter').text(reporter);
        $('#detail-date').text(date);
        $('#detail-status').html('<span class="badge badge-warning">Open</span>');
        $('#detail-description').text(description);
        
        // Show/hide resolution notes
        if (notes && notes.trim() !== '') {
            $('#detail-resolution-notes').text(notes);
            $('#resolution-notes-section').show();
        } else {
            $('#resolution-notes-section').hide();
        }
        
        // Show the details section
        $('#incident-details-section').slideDown();
        
        // Scroll to details section
        $('html, body').animate({
            scrollTop: $('#incident-details-section').offset().top - 100
        }, 500);
    });
    
    // Handle resolve incident
    $('.resolve-incident').click(function() {
        const id = $(this).data('id');
        const type = $(this).data('type');
        const description = $(this).data('description');
        
        $('#resolve_incident_id').val(id);
        $('#resolve_incident_type').text(type.charAt(0).toUpperCase() + type.slice(1));
        $('#resolve_incident_description').text(description);
        
        $('#resolveIncidentModal').modal('show');
    });
    
    // Handle close incident details
    $('#close-incident-details').click(function() {
        $('#incident-details-section').slideUp();
    });
    
    // Helper functions
    function getIncidentIcon(type) {
        switch(type) {
            case 'illness': return 'thermometer-three-quarters';
            case 'injury': return 'band-aid';
            case 'equipment': return 'tools';
            case 'facility': return 'building';
            default: return 'exclamation-triangle';
        }
    }
    
    function getSeverityClass(severity) {
        switch(severity.toLowerCase()) {
            case 'high': return 'danger';
            case 'medium': return 'warning';
            default: return 'info';
        }
    }
    
    // Auto-hide success/error messages after 5 seconds
    setTimeout(function() {
        $('.alert-dismissible').fadeOut();
    }, 5000);
});
</script>

<?php include 'includes/footer.php'; ?>