<?php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

$pageTitle = 'Productivity Tracking';
include 'includes/header.php';

try {
    // Fetch breeding history
    $stmt = $pdo->query("
        SELECT b.*, a1.species as mother_species, a2.species as father_species
        FROM breeding_history b
        LEFT JOIN animals a1 ON b.animal_id = a1.id
        LEFT JOIN animals a2 ON b.partner_id = a2.id
        ORDER BY b.date DESC
    ");
    $breedingRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get breeding success rate
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN outcome = 'success' THEN 1 ELSE 0 END) as successful
        FROM breeding_history
        WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
    ");
    $breedingStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate success rate
    $successRate = $breedingStats['total'] > 0 
        ? round(($breedingStats['successful'] / $breedingStats['total']) * 100, 1)
        : 0;

} catch(PDOException $e) {
    error_log("Error fetching productivity data: " . $e->getMessage());
    $breedingRecords = [];
    $successRate = 0;
}
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-pie"></i> Productivity Tracking</h2>
    </div>

    <!-- Breeding Success Rate Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Breeding Success Rate (Last 12 Months)</h3>
        </div>
        <div class="card-body">
            <div class="progress-circle">
                <div class="progress-circle-inner">
                    <span class="progress-value"><?php echo $successRate; ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Breeding History Table -->
    <div class="card">
        <div class="card-header">
            <h3>Breeding History</h3>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Mother Species</th>
                        <th>Father Species</th>
                        <th>Outcome</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($breedingRecords as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['date']); ?></td>
                        <td><?php echo htmlspecialchars($record['mother_species']); ?></td>
                        <td><?php echo htmlspecialchars($record['father_species']); ?></td>
                        <td><?php echo htmlspecialchars($record['outcome']); ?></td>
                        <td><?php echo htmlspecialchars($record['notes']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.progress-circle {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: #f0f0f0;
    margin: 0 auto;
    position: relative;
}

.progress-circle-inner {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.progress-value {
    font-size: 2em;
    font-weight: bold;
    color: #333;
}
</style>

<?php include 'includes/footer.php'; ?>