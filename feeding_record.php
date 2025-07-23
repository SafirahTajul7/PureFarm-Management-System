<?php
require_once 'includes/auth.php';
auth()->checkSupervisor(); // Ensure only supervisors can access
require_once 'includes/db.php';

// Get animal ID from URL
$animal_id = isset($_GET['animal_id']) ? intval($_GET['animal_id']) : 0;

// Validate if the animal exists and the supervisor has access to it
try {
    $stmt = $pdo->prepare("
        SELECT a.*, fs.id as schedule_id, fs.food_type, fs.quantity, fs.frequency, fs.special_diet 
        FROM animals a 
        LEFT JOIN feeding_schedules fs ON a.id = fs.animal_id AND fs.status = 'active'
        WHERE a.id = ? AND (a.assigned_to = ? OR EXISTS (
            SELECT 1 FROM feeding_schedules fs2 
            WHERE fs2.animal_id = a.id AND fs2.created_by = ?
        ))
    ");
    $stmt->execute([$animal_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $animal = $stmt->fetch();
    
    if (!$animal) {
        $_SESSION['error'] = "Animal not found or you don't have permission to access it.";
        header('Location: supervisor_feeding_nutrition.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = "Error retrieving animal information: " . $e->getMessage();
    header('Location: supervisor_feeding_nutrition.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['record_feeding'])) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO feeding_records (animal_id, schedule_id, food_type, quantity_given, feeding_time, notes, recorded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $animal_id,
                $_POST['schedule_id'] ?: null,
                $_POST['food_type'],
                $_POST['quantity_given'],
                $_POST['feeding_time'],
                $_POST['notes'],
                $_SESSION['user_id']
            ]);
            
            // Log the activity
            $activityStmt = $pdo->prepare("
                INSERT INTO activity_log (user_id, activity_type, description, related_id) 
                VALUES (?, 'feeding_record', ?, ?)
            ");
            $activityStmt->execute([
                $_SESSION['user_id'],
                'Recorded feeding for animal ID: ' . $animal_id,
                $animal_id
            ]);
            
            $_SESSION['success'] = "Feeding record added successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error recording feeding: " . $e->getMessage();
        }
        header('Location: feeding_record.php?animal_id=' . $animal_id);
        exit();
    }
}

// Fetch the animal's feeding records
try {
    $stmt = $pdo->prepare("
        SELECT fr.*, u.username as recorded_by_name 
        FROM feeding_records fr
        LEFT JOIN users u ON fr.recorded_by = u.id
        WHERE fr.animal_id = ?
        ORDER BY fr.feeding_time DESC, fr.id DESC
    ");
    $stmt->execute([$animal_id]);
    $feeding_records = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Error retrieving feeding records: " . $e->getMessage();
    $feeding_records = [];
}

// Fetch feeding schedule information for this animal
try {
    $stmt = $pdo->prepare("
        SELECT * FROM feeding_schedules
        WHERE animal_id = ? AND status = 'active'
    ");
    $stmt->execute([$animal_id]);
    $schedule = $stmt->fetch();
} catch(PDOException $e) {
    $_SESSION['error'] = "Error retrieving feeding schedule: " . $e->getMessage();
    $schedule = null;
}

// Calculate statistics
$total_feedings = count($feeding_records);
$today_feedings = 0;
$weekly_feedings = 0;
$today = date('Y-m-d');
$week_ago = date('Y-m-d', strtotime('-7 days'));

foreach ($feeding_records as $record) {
    $record_date = substr($record['feeding_time'], 0, 10); // Get just the date part
    if ($record_date === $today) {
        $today_feedings++;
    }
    if ($record_date >= $week_ago) {
        $weekly_feedings++;
    }
}

// Get scheduled feedings per week
$scheduled_per_week = 0;
if ($schedule) {
    switch ($schedule['frequency']) {
        case 'daily':
            $scheduled_per_week = 7;
            break;
        case 'twice_daily':
            $scheduled_per_week = 14;
            break;
        case 'weekly':
            $scheduled_per_week = 1;
            break;
    }
}

$adherence_rate = $scheduled_per_week > 0 ? round(($weekly_feedings / $scheduled_per_week) * 100) : 0;

$pageTitle = 'Feeding Records';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h2>
            <i class="fas fa-clipboard-list mr-2"></i>
            Feeding Records for <?php echo htmlspecialchars($animal['name'] ?: "Animal #" . $animal['id']); ?>
        </h2>
        <a href="supervisor_feeding_nutrition.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Feeding Management
        </a>
    </div>

    <?php include 'includes/messages.php'; ?>

    <!-- Animal Information Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Animal Information</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>ID:</strong></span>
                            <span><?php echo htmlspecialchars($animal['id']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Name:</strong></span>
                            <span><?php echo htmlspecialchars($animal['name'] ?: 'None'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Species:</strong></span>
                            <span><?php echo htmlspecialchars($animal['species']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Breed:</strong></span>
                            <span><?php echo htmlspecialchars($animal['breed']); ?></span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Food Type:</strong></span>
                            <span><?php echo htmlspecialchars($animal['food_type'] ?: 'Not set'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Quantity:</strong></span>
                            <span><?php echo htmlspecialchars($animal['quantity'] ?: 'Not set'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Frequency:</strong></span>
                            <span><?php echo htmlspecialchars($animal['frequency'] ? ucfirst(str_replace('_', ' ', $animal['frequency'])) : 'Not set'); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Special Diet:</strong></span>
                            <span><?php echo htmlspecialchars($animal['special_diet'] ?: 'None'); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Feeding Statistics Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Feeding Statistics</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-card-icon bg-primary">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-card-info">
                            <h4>Today's Feedings</h4>
                            <p><?php echo $today_feedings; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-card-icon bg-success">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-card-info">
                            <h4>Weekly Feedings</h4>
                            <p><?php echo $weekly_feedings; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-card-icon bg-info">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-card-info">
                            <h4>Schedule Adherence</h4>
                            <p><?php echo $adherence_rate; ?>%</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-card-icon bg-secondary">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-card-info">
                            <h4>Total Records</h4>
                            <p><?php echo $total_feedings; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Record Feeding Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Record Feeding</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="schedule_id" value="<?php echo $animal['schedule_id'] ?? ''; ?>">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Food Type</label>
                            <input type="text" name="food_type" class="form-control" 
                                   value="<?php echo htmlspecialchars($animal['food_type'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Quantity Given</label>
                            <input type="text" name="quantity_given" class="form-control" 
                                   value="<?php echo htmlspecialchars($animal['quantity'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Feeding Time</label>
                            <input type="datetime-local" name="feeding_time" class="form-control" 
                                   value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" 
                              placeholder="Optional notes about this feeding (consumption, behavior, etc.)"></textarea>
                </div>
                <div class="text-end">
                    <button type="submit" name="record_feeding" class="btn btn-primary">
                        <i class="fas fa-save"></i> Record Feeding
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Feeding Records Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Feeding History</h3>
        </div>
        <div class="card-body">
            <?php if (empty($feeding_records)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No feeding records found. Use the form above to record your first feeding.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Food Type</th>
                                <th>Quantity</th>
                                <th>Notes</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeding_records as $record): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($record['feeding_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($record['food_type']); ?></td>
                                    <td><?php echo htmlspecialchars($record['quantity_given']); ?></td>
                                    <td><?php echo htmlspecialchars($record['notes'] ?: 'None'); ?></td>
                                    <td><?php echo htmlspecialchars($record['recorded_by_name'] ?: 'Unknown'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.main-content {
    padding: 20px;
}

.page-header {
    margin-bottom: 20px;
    align-items: center;
}

.page-header h2 {
    display: flex;
    align-items: center;
    margin: 0;
}

.page-header h2 i {
    margin-right: 10px;
}

.card {
    margin-bottom: 20px;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    border: none;
    border-radius: 0.5rem;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0,0,0,0.125);
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
}

.card-header h3 {
    margin: 0;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
}

.card-header h3 i {
    margin-right: 0.5rem;
    color: #6c757d;
}

.card-body {
    padding: 1.25rem;
}

.list-group-item {
    display: flex;
    justify-content: space-between;
}

.stat-card {
    background-color: #ffffff;
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    padding: 1.25rem;
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.stat-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
}

.stat-card-icon i {
    color: #ffffff;
    font-size: 1.5rem;
}

.stat-card-info h4 {
    margin: 0;
    font-size: 0.875rem;
    color: #6c757d;
}

.stat-card-info p {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
}

.bg-primary {
    background-color: #0d6efd;
}

.bg-success {
    background-color: #198754;
}

.bg-info {
    background-color: #0dcaf0;
}

.bg-secondary {
    background-color: #6c757d;
}

@media (max-width: 992px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-header a {
        margin-top: 10px;
    }
}

@media (max-width: 768px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
    
    .stat-card-icon {
        margin-right: 0;
        margin-bottom: 0.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>