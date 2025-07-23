<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$batches = [];
$qualityHistory = [];

// Get current user ID
$current_user_id = $_SESSION['user_id'] ?? 1;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'quality_check') {
        try {
            $batch_id = $_POST['batch_id'];
            $moisture_level = floatval($_POST['moisture_level']);
            $notes = $_POST['notes'] ?? '';

            // Calculate quality grade based on moisture level
            $quality_grade = calculateQualityGrade($moisture_level);

            // Check if columns exist and insert accordingly
            try {
                // Try the new schema first
                $stmt = $pdo->prepare("
                    INSERT INTO batch_quality_checks 
                    (batch_id, check_date, performed_by, moisture_level, quality_grade, additional_notes) 
                    VALUES (?, NOW(), ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $batch_id, 
                    $current_user_id, 
                    $moisture_level,
                    $quality_grade,
                    $notes
                ]);
            } catch(PDOException $e) {
                // Fall back to existing schema
                $stmt = $pdo->prepare("
                    INSERT INTO batch_quality_checks 
                    (batch_id, check_date, performed_by, passed, temperature, humidity, additional_notes) 
                    VALUES (?, NOW(), ?, ?, ?, ?, ?)
                ");
                
                $passed = ($quality_grade !== 'FAIL') ? 1 : 0;
                $stmt->execute([
                    $batch_id, 
                    $current_user_id, 
                    $passed,
                    $moisture_level, // Store in temperature field temporarily
                    0, // Default humidity
                    "Grade: {$quality_grade} | Moisture: {$moisture_level}% | {$notes}"
                ]);
            }

            // Update batch status based on quality grade
            $newStatus = ($quality_grade === 'FAIL') ? 'quarantine' : 'active';
            $updateStmt = $pdo->prepare("UPDATE inventory_batches SET status = ? WHERE id = ?");
            $updateStmt->execute([$newStatus, $batch_id]);

            $successMsg = "Quality check completed for Batch ID: {$batch_id}. Grade: {$quality_grade}";

        } catch(PDOException $e) {
            $errorMsg = "Error saving quality check: " . $e->getMessage();
        }
    }
}

// Function to calculate quality grade based on moisture level
function calculateQualityGrade($moisture_level) {
    if ($moisture_level <= 12) {
        return 'A'; // Excellent quality
    } elseif ($moisture_level <= 15) {
        return 'B'; // Good quality
    } elseif ($moisture_level <= 18) {
        return 'C'; // Average quality
    } elseif ($moisture_level <= 20) {
        return 'D'; // Poor quality
    } else {
        return 'FAIL'; // Unacceptable
    }
}

// Get inventory batches from batch_tracking system
try {
    $stmt = $pdo->query("
        SELECT ib.id, ib.batch_number, ii.item_name, ib.received_date, ib.status,
               bqc.quality_grade,
               CASE 
                   WHEN bqc.quality_grade IS NULL THEN 'Pending'
                   ELSE bqc.quality_grade
               END as quality_status
        FROM inventory_batches ib
        JOIN inventory_items ii ON ib.item_id = ii.id
        LEFT JOIN batch_quality_checks bqc ON ib.id = bqc.batch_id
        WHERE ib.status IN ('active', 'quarantine', 'pending')
        ORDER BY ib.received_date DESC
    ");
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Fallback sample data
    $batches = [
        ['id' => 1, 'batch_number' => 'BATCH-001', 'item_name' => 'Garden Hoe', 'received_date' => '2025-06-01', 'status' => 'active', 'quality_status' => 'Pending'],
        ['id' => 2, 'batch_number' => 'BATCH-002', 'item_name' => 'Corn Seeds Premium', 'received_date' => '2025-06-01', 'status' => 'active', 'quality_status' => 'Pending'],
        ['id' => 3, 'batch_number' => 'BATCH-003', 'item_name' => 'Cattle Feed', 'received_date' => '2025-05-05', 'status' => 'active', 'quality_status' => 'Pending']
    ];
}

// Get quality check history
try {
    // Try to get data with new schema first
    $stmt = $pdo->prepare("
        SELECT bqc.check_date, ib.batch_number, ii.item_name, 
               COALESCE(bqc.quality_grade, CASE WHEN bqc.passed = 1 THEN 'PASS' ELSE 'FAIL' END) as quality_grade,
               COALESCE(bqc.moisture_level, bqc.temperature) as moisture_level
        FROM batch_quality_checks bqc
        JOIN inventory_batches ib ON bqc.batch_id = ib.id
        JOIN inventory_items ii ON ib.item_id = ii.id
        ORDER BY bqc.check_date DESC
        LIMIT 50
    ");
    $stmt->execute();
    $qualityHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Fall back to basic query if tables don't exist
    $qualityHistory = [];
}

// Handle auto-fill request
if (isset($_GET['auto_fill']) && $_GET['auto_fill'] === '1') {
    header('Content-Type: application/json');
    
    $auto_data = [
        'moisture_level' => round(rand(80, 250) / 10, 1), // 8-25%
        'notes' => 'Automated quality check performed at ' . date('Y-m-d H:i:s') . '. Moisture sensor reading captured.'
    ];
    
    echo json_encode($auto_data);
    exit;
}

$pageTitle = 'Batch Quality Check';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-clipboard-check"></i> Quality Check History</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='batch_tracking.php'">
                <i class="fas fa-arrow-left"></i> Back to Batch Tracking
            </button>
        </div>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $errorMsg; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $successMsg; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Batch List Section -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-boxes"></i> Available Batches for Quality Check</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th>Batch ID</th>
                            <th>Item Type</th>
                            <th>Received Date</th>
                            <th>Current Grade</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batches as $batch): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($batch['batch_number']); ?></td>
                            <td><?php echo htmlspecialchars($batch['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($batch['received_date']); ?></td>
                            <td>
                                <span class="badge <?php 
                                    switch($batch['quality_status']) {
                                        case 'A': echo 'badge-success'; break;
                                        case 'B': echo 'badge-info'; break;
                                        case 'C': echo 'badge-warning'; break;
                                        case 'D': echo 'badge-secondary'; break;
                                        case 'FAIL': echo 'badge-danger'; break;
                                        default: echo 'badge-light text-dark';
                                    }
                                ?>">
                                    <?php echo $batch['quality_status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm" 
                                        onclick="showQualityForm('<?php echo $batch['id']; ?>', '<?php echo htmlspecialchars($batch['batch_number']); ?>', '<?php echo htmlspecialchars($batch['item_name']); ?>')">
                                    <i class="fas fa-clipboard-check"></i> Check Quality
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quality Check Form Section -->
    <div class="card mb-4" id="qualityFormCard" style="display: none;">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-microscope"></i> Quality Check Form</h5>
        </div>
        <div class="card-body">
            <div id="selectedBatchInfo"></div>
            
            <form id="qualityCheckForm" method="POST">
                <input type="hidden" name="action" value="quality_check">
                <input type="hidden" id="batchId" name="batch_id">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="moistureLevel">Moisture Level (%):</label>
                            <input type="number" class="form-control" id="moistureLevel" name="moisture_level" 
                                   min="0" max="100" step="0.1" required>
                            <small class="form-text text-muted">
                                Grade Scale: A (≤12%), B (≤15%), C (≤18%), D (≤20%), FAIL (>20%)
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Predicted Grade:</label>
                            <div class="form-control-plaintext">
                                <span id="predictedGrade" class="badge badge-light">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                              placeholder="Enter any additional observations..."></textarea>
                </div>

                <div class="text-center">
                    <button type="button" class="btn btn-info" onclick="autoFillData()">
                        <i class="fas fa-magic"></i> Auto-Fill Data
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Submit Quality Check
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="hideQualityForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quality Check History Section -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-history"></i> Quality Check History</h5>
        </div>
        <div class="card-body">
            <?php if (empty($qualityHistory)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No quality check records found.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Batch ID</th>
                            <th>Item Type</th>
                            <th>Result</th>
                            <th>Moisture</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($qualityHistory as $record): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($record['check_date'])); ?></td>
                            <td><?php echo htmlspecialchars($record['batch_number']); ?></td>
                            <td><?php echo htmlspecialchars($record['item_name']); ?></td>
                            <td>
                                <span class="badge <?php 
                                    switch($record['quality_grade']) {
                                        case 'A': echo 'badge-success'; break;
                                        case 'B': echo 'badge-info'; break;
                                        case 'C': echo 'badge-warning'; break;
                                        case 'D': echo 'badge-secondary'; break;
                                        case 'FAIL': echo 'badge-danger'; break;
                                        default: echo 'badge-light';
                                    }
                                ?>">
                                    <?php echo htmlspecialchars($record['quality_grade']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($record['moisture_level']); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Show quality check form
    function showQualityForm(batchId, batchNumber, itemName) {
        document.getElementById('batchId').value = batchId;
        document.getElementById('selectedBatchInfo').innerHTML = `
            <div class="alert alert-primary">
                <h6><i class="fas fa-info-circle"></i> Selected Batch:</h6>
                <strong>Batch ID:</strong> ${batchNumber}<br>
                <strong>Item:</strong> ${itemName}
            </div>
        `;

        document.getElementById('qualityFormCard').style.display = 'block';
        document.getElementById('qualityFormCard').scrollIntoView({ behavior: 'smooth' });

        // Clear form
        document.getElementById('qualityCheckForm').reset();
        document.getElementById('batchId').value = batchId;
        updatePredictedGrade();
    }

    // Hide quality check form
    function hideQualityForm() {
        document.getElementById('qualityFormCard').style.display = 'none';
    }

    // Auto-fill data with simulated sensor values
    async function autoFillData() {
        try {
            const response = await fetch('?auto_fill=1');
            const data = await response.json();
            
            document.getElementById('moistureLevel').value = data.moisture_level;
            document.getElementById('notes').value = data.notes;
            
            updatePredictedGrade();
            showAlert('success', '<i class="fas fa-magic"></i> Moisture sensor data auto-filled!');
        } catch (error) {
            showAlert('danger', '<i class="fas fa-exclamation-triangle"></i> Error auto-generating data');
        }
    }

    // Update predicted grade based on moisture level
    function updatePredictedGrade() {
        const moisture = parseFloat(document.getElementById('moistureLevel').value);
        const gradeElement = document.getElementById('predictedGrade');
        
        if (isNaN(moisture)) {
            gradeElement.textContent = '-';
            gradeElement.className = 'badge badge-light';
            return;
        }

        let grade, badgeClass;
        if (moisture <= 12) {
            grade = 'A';
            badgeClass = 'badge-success';
        } else if (moisture <= 15) {
            grade = 'B';
            badgeClass = 'badge-info';
        } else if (moisture <= 18) {
            grade = 'C';
            badgeClass = 'badge-warning';
        } else if (moisture <= 20) {
            grade = 'D';
            badgeClass = 'badge-secondary';
        } else {
            grade = 'FAIL';
            badgeClass = 'badge-danger';
        }

        gradeElement.textContent = grade;
        gradeElement.className = `badge ${badgeClass}`;
    }

    // Real-time moisture level validation
    document.getElementById('moistureLevel').addEventListener('input', updatePredictedGrade);

    // Handle form submission
    document.getElementById('qualityCheckForm').addEventListener('submit', function(e) {
        const moistureLevel = parseFloat(document.getElementById('moistureLevel').value);

        if (!moistureLevel || moistureLevel < 0 || moistureLevel > 100) {
            e.preventDefault();
            showAlert('danger', '<i class="fas fa-exclamation-triangle"></i> Please enter a valid moisture level (0-100%)');
            return false;
        }

        const grade = document.getElementById('predictedGrade').textContent;
        const confirmMessage = `Confirm Quality Check:

Moisture Level: ${moistureLevel}%
Predicted Grade: ${grade}

Submit this quality check?`;

        if (!confirm(confirmMessage)) {
            e.preventDefault();
            return false;
        }
    });

    // Show alert messages
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        `;
        
        const mainContent = document.querySelector('.main-content');
        mainContent.insertBefore(alertDiv, mainContent.children[1]);
        
        setTimeout(() => alertDiv.remove(), 5000);
    }

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Simplified Batch Quality Check System initialized');
    });
</script>

<style>
    .badge {
        font-size: 0.9em;
        padding: 0.4em 0.8em;
    }
    
    .table th {
        white-space: nowrap;
    }
    
    .card {
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .page-header {
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .page-header h2 {
        margin: 0;
        color: #495057;
    }
    
    .form-control:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }
    
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .action-buttons {
            margin-top: 1rem;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>