<?php
// supervisor_animal_details.php
session_start();
require_once 'includes/auth.php';
auth()->checkSupervisor();
require_once 'includes/db.php';

// Check if animal ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Animal ID is required.";
    header('Location: supervisor_animal_management.php');
    exit();
}

$animal_id = $_GET['id'];

try {
    // Fetch animal details
    $stmt = $pdo->prepare("
        SELECT a.*
        FROM animals a
        WHERE a.id = ?
    ");
    $stmt->execute([$animal_id]);
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$animal) {
        $_SESSION['error'] = "Animal not found.";
        header('Location: supervisor_animal_management.php');
        exit();
    }
    
    // Fetch health records
    $stmt = $pdo->prepare("
        SELECT *
        FROM health_records
        WHERE animal_id = ?
        ORDER BY date DESC
    ");
    $stmt->execute([$animal_id]);
    $health_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch feeding schedules
    $stmt = $pdo->prepare("
        SELECT *
        FROM feeding_schedules
        WHERE animal_id = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$animal_id]);
    $feeding_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch vaccinations
    $stmt = $pdo->prepare("
        SELECT *
        FROM vaccinations
        WHERE animal_id = ?
        ORDER BY date DESC
    ");
    $stmt->execute([$animal_id]);
    $vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch weight records
    $stmt = $pdo->prepare("
        SELECT *
        FROM weight_records
        WHERE animal_id = ?
        ORDER BY date DESC
    ");
    $stmt->execute([$animal_id]);
    $weight_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $_SESSION['error'] = "Error fetching animal details: " . $e->getMessage();
    header('Location: supervisor_animal_management.php');
    exit();
}

$pageTitle = 'Animal Details - ' . $animal['animal_id'];
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2>
            <i class="fas fa-paw"></i> Animal Details: <?php echo htmlspecialchars($animal['animal_id']); ?>
        </h2>
        <div class="action-buttons">
            <button class="btn btn-primary update-health" data-id="<?php echo $animal['id']; ?>" 
                    data-animal-id="<?php echo htmlspecialchars($animal['animal_id']); ?>"
                    data-species="<?php echo htmlspecialchars($animal['species']); ?>"
                    data-breed="<?php echo htmlspecialchars($animal['breed']); ?>"
                    data-status="<?php echo htmlspecialchars($animal['health_status']); ?>">
                <i class="fas fa-edit"></i> Update Health
            </button>
            <button class="btn btn-info" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    </div>

    <!-- Animal Basic Info -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th width="40%">Animal ID:</th>
                            <td><?php echo htmlspecialchars($animal['animal_id']); ?></td>
                        </tr>
                        <tr>
                            <th>Species:</th>
                            <td><?php echo htmlspecialchars($animal['species']); ?></td>
                        </tr>
                        <tr>
                            <th>Breed:</th>
                            <td><?php echo htmlspecialchars($animal['breed']); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth:</th>
                            <td><?php echo date('M d, Y', strtotime($animal['date_of_birth'])); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th width="40%">Gender:</th>
                            <td><?php echo htmlspecialchars($animal['gender']); ?></td>
                        </tr>
                        <tr>
                            <th>Health Status:</th>
                            <td>
                                <?php 
                                $statusClass = '';
                                switch(strtolower($animal['health_status'])) {
                                    case 'healthy': $statusClass = 'badge-success'; break;
                                    case 'sick': $statusClass = 'badge-danger'; break;
                                    case 'injured': $statusClass = 'badge-warning'; break;
                                    case 'quarantine': $statusClass = 'badge-info'; break;
                                    default: $statusClass = 'badge-secondary';
                                }
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($animal['health_status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Source:</th>
                            <td><?php echo htmlspecialchars($animal['source'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <th>Last Vaccination:</th>
                            <td><?php echo !empty($animal['last_vaccination_date']) ? date('M d, Y', strtotime($animal['last_vaccination_date'])) : 'Never'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Health Records -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-heartbeat"></i> Health Records</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($health_records)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Condition</th>
                                <th>Treatment</th>
                                <th>Veterinarian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($health_records as $record): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                <td>
                                    <?php 
                                    $conditionClass = '';
                                    switch(strtolower($record['condition'])) {
                                        case 'good':
                                        case 'excellent':
                                        case 'healthy': $conditionClass = 'badge-success'; break;
                                        case 'sick':
                                        case 'fever': $conditionClass = 'badge-danger'; break;
                                        default: $conditionClass = 'badge-info';
                                    }
                                    ?>
                                    <span class="badge <?php echo $conditionClass; ?>">
                                        <?php echo htmlspecialchars($record['condition']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($record['treatment'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($record['vet_name'] ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">No health records found for this animal.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Vaccinations -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-syringe"></i> Vaccination Records</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($vaccinations)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Next Due</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vaccinations as $vacc): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($vacc['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($vacc['type']); ?></td>
                                        <td>
                                            <?php 
                                            echo date('M d, Y', strtotime($vacc['next_due'])); 
                                            
                                            $dueDate = new DateTime($vacc['next_due']);
                                            $today = new DateTime();
                                            $interval = $today->diff($dueDate);
                                            $daysRemaining = $interval->days;
                                            $pastDue = $today > $dueDate;
                                            
                                            if ($pastDue) {
                                                echo ' <span class="badge badge-danger">Overdue</span>';
                                            } elseif ($daysRemaining <= 14) {
                                                echo ' <span class="badge badge-warning">' . $daysRemaining . ' days left</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">No vaccination records found for this animal.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Weight Records -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-weight"></i> Weight Records</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($weight_records)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Weight (kg)</th>
                                        <?php if (isset($weight_records[0]['measured_by'])): ?>
                                        <th>Measured By</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($weight_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['weight']); ?> kg</td>
                                        <?php if (isset($record['measured_by'])): ?>
                                        <td><?php echo htmlspecialchars($record['measured_by']); ?></td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">No weight records found for this animal.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Feeding Schedules -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-utensils"></i> Feeding Schedules</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($feeding_schedules)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Food Type</th>
                                <th>Quantity</th>
                                <th>Frequency</th>
                                <th>Special Diet</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeding_schedules as $schedule): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($schedule['food_type'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($schedule['quantity'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($schedule['frequency'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($schedule['special_diet'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($schedule['notes'] ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">No feeding schedules found for this animal.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Update Health Modal (same as in supervisor_animal_management.php) -->
<div class="modal fade" id="updateHealthModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Health Status</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form method="POST" action="supervisor_animal_management.php">
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

<script>
$(document).ready(function() {
    // Handle update health button click
    $('.update-health').click(function() {
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
});
</script>

<style>
.badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 8px;
    border: none;
    margin-bottom: 20px;
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

.table th {
    background-color: #f8f9fa;
}

.btn-primary {
    background-color: #4CAF50;
    border-color: #4CAF50;
}

.btn-primary:hover {
    background-color: #45a049;
    border-color: #45a049;
}

/* Print styling */
@media print {
    .main-content {
        margin-left: 0 !important;
    }
    
    .action-buttons, .sidebar, header {
        display: none !important;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #ddd;
        break-inside: avoid;
    }
    
    body {
        background-color: white;
    }
}
</style>

<?php include 'includes/footer.php'; ?>