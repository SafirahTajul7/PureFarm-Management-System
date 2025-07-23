<?php
require_once 'includes/auth.php';
auth()->checkAdmin(); // Only allow admin access

require_once 'includes/db.php';


try {
    // Check for last_checkup column
    $stmt = $pdo->query("SHOW COLUMNS FROM equipment LIKE 'last_checkup'");
    $lastCheckupExists = $stmt->rowCount() > 0;
    
    // Check for next_checkup column
    $stmt = $pdo->query("SHOW COLUMNS FROM equipment LIKE 'next_checkup'");
    $nextCheckupExists = $stmt->rowCount() > 0;
    
    // Check for updated_at column
    $stmt = $pdo->query("SHOW COLUMNS FROM equipment LIKE 'updated_at'");
    $updatedAtExists = $stmt->rowCount() > 0;
    
    // Add missing columns if needed
    $alterQueries = [];
    
    if (!$lastCheckupExists) {
        $alterQueries[] = "ADD COLUMN last_checkup DATE DEFAULT NULL";
    }
    
    if (!$nextCheckupExists) {
        $alterQueries[] = "ADD COLUMN next_checkup DATE DEFAULT NULL";
    }
    
    if (!$updatedAtExists) {
        $alterQueries[] = "ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP";
    }
    
    if (!empty($alterQueries)) {
        $alterQuery = "ALTER TABLE equipment " . implode(", ", $alterQueries);
        $pdo->exec($alterQuery);
        $successMsg = "Database structure updated successfully. Missing columns have been added.";
    }
} catch(PDOException $e) {
    error_log("Error checking or adding columns to equipment table: " . $e->getMessage());
    $errorMsg = "Failed to update database structure: " . $e->getMessage();
}

// Initialize variables
$errorMsg = '';
$successMsg = '';
$equipment = [];
$checkupRecords = [];

// Check if the equipment_checkups table exists, if not create it
try {
    $tableExists = false;
    $stmt = $pdo->query("SHOW TABLES LIKE 'equipment_checkups'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        // Create the table
        $pdo->exec("
            CREATE TABLE `equipment_checkups` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `equipment_id` int(11) NOT NULL,
                `checkup_date` date NOT NULL,
                `condition_status` enum('Good','Fair','Poor','Broken') NOT NULL,
                `technician` varchar(100) DEFAULT NULL,
                `notes` text DEFAULT NULL,
                `repair_cost` decimal(10,2) DEFAULT NULL,
                `next_checkup_date` date DEFAULT NULL,
                `created_by` int(11) NOT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `equipment_id` (`equipment_id`),
                KEY `created_by` (`created_by`),
                CONSTRAINT `equipment_checkups_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`),
                CONSTRAINT `equipment_checkups_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        $successMsg = "Asset checkup system has been initialized. You can now start recording equipment inspections.";
    }
} catch(PDOException $e) {
    error_log("Error checking/creating equipment_checkups table: " . $e->getMessage());
    $errorMsg = "Failed to initialize asset checkup system. Please contact the administrator.";
}

// Fetch all equipment for dropdown
try {
    $stmt = $pdo->query("SELECT id, name, status FROM equipment ORDER BY name ASC");
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching equipment: " . $e->getMessage());
    $errorMsg = "Failed to load equipment list. Please try again later.";
}

// Handle adding a new checkup record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_checkup'])) {
    $equipmentId = isset($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
    $checkupDate = isset($_POST['checkup_date']) ? $_POST['checkup_date'] : date('Y-m-d');
    $conditionStatus = isset($_POST['condition_status']) ? $_POST['condition_status'] : '';
    $technician = isset($_POST['technician']) ? trim($_POST['technician']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $repairCost = isset($_POST['repair_cost']) ? (float)$_POST['repair_cost'] : 0;
    $nextCheckupDate = !empty($_POST['next_checkup_date']) ? $_POST['next_checkup_date'] : null;
    
    // Basic validation
    if ($equipmentId <= 0) {
        $errorMsg = "Please select a valid equipment.";
    } elseif (empty($conditionStatus)) {
        $errorMsg = "Please select a condition status.";
    } else {
        try {
            // Add checkup record
            $stmt = $pdo->prepare("
                INSERT INTO equipment_checkups (
                    equipment_id, checkup_date, condition_status, technician, 
                    notes, repair_cost, next_checkup_date, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $equipmentId, 
                $checkupDate, 
                $conditionStatus, 
                $technician, 
                $notes, 
                $repairCost,
                $nextCheckupDate,
                $_SESSION['user_id']
            ]);
            
            // Update equipment status based on condition
            $status = 'active';
            if ($conditionStatus === 'Broken') {
                $status = 'maintenance';
            } elseif ($conditionStatus === 'Poor') {
                $status = 'needs_attention';
            }
            
            $stmt = $pdo->prepare("
                UPDATE equipment 
                SET status = ?, last_checkup = ?, next_checkup = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $checkupDate, $nextCheckupDate, $equipmentId]);
            
            $successMsg = "Asset checkup record added successfully.";
        } catch (Exception $e) {
            error_log("Error adding checkup record: " . $e->getMessage());
            $errorMsg = "Failed to add checkup record: " . $e->getMessage();
        }
    }
}

// Handle deletion of checkup record
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $checkupId = (int)$_GET['id'];
    
    try {
        // Get checkup record details before deletion
        $stmt = $pdo->prepare("
            SELECT c.equipment_id, e.name as equipment_name
            FROM equipment_checkups c
            JOIN equipment e ON c.equipment_id = e.id
            WHERE c.id = ?
        ");
        $stmt->execute([$checkupId]);
        $checkupRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$checkupRecord) {
            throw new Exception("Checkup record not found");
        }
        
        // Delete the checkup record
        $stmt = $pdo->prepare("DELETE FROM equipment_checkups WHERE id = ?");
        $stmt->execute([$checkupId]);
        
        // Update last checkup date based on latest checkup
        $stmt = $pdo->prepare("
            SELECT MAX(checkup_date) as last_checkup, MAX(next_checkup_date) as next_checkup
            FROM equipment_checkups
            WHERE equipment_id = ?
        ");
        $stmt->execute([$checkupRecord['equipment_id']]);
        $dates = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update equipment with latest dates
        $stmt = $pdo->prepare("
            UPDATE equipment 
            SET last_checkup = ?, next_checkup = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$dates['last_checkup'], $dates['next_checkup'], $checkupRecord['equipment_id']]);
        
        $successMsg = "Checkup record for {$checkupRecord['equipment_name']} has been deleted.";
        
    } catch (Exception $e) {
        error_log("Error deleting checkup record: " . $e->getMessage());
        $errorMsg = "Failed to delete checkup record: " . $e->getMessage();
    }
}

// Process search/filter parameters
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-90 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$equipmentFilter = isset($_GET['equipment_filter']) ? (int)$_GET['equipment_filter'] : 0;
$conditionFilter = isset($_GET['condition_filter']) ? $_GET['condition_filter'] : '';

// Fetch checkup records - only if equipment_checkups table exists
$checkupRecords = [];

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'equipment_checkups'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        // Prepare base query
        $query = "
            SELECT c.id, c.checkup_date, c.condition_status, c.technician, 
                   c.notes, c.repair_cost, c.next_checkup_date, c.created_at,
                   e.name as equipment_name, e.id as equipment_id,
                   u.username as created_by_name
            FROM equipment_checkups c
            JOIN equipment e ON c.equipment_id = e.id
            LEFT JOIN users u ON c.created_by = u.id
            WHERE c.checkup_date BETWEEN ? AND ?
        ";

        // Add search and filter conditions
        $params = [$startDate, $endDate];

        if (!empty($searchTerm)) {
            $query .= " AND (e.name LIKE ? OR c.technician LIKE ? OR c.notes LIKE ?)";
            $searchParam = "%{$searchTerm}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        if ($equipmentFilter > 0) {
            $query .= " AND c.equipment_id = ?";
            $params[] = $equipmentFilter;
        }
        
        if (!empty($conditionFilter)) {
            $query .= " AND c.condition_status = ?";
            $params[] = $conditionFilter;
        }

        // Add order by
        $query .= " ORDER BY c.checkup_date DESC, c.created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $checkupRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching checkup records: " . $e->getMessage());
    $errorMsg = "Failed to load checkup records: " . $e->getMessage();
}

// Get maintenance cost summary
$maintenanceCosts = [];
try {
    if ($tableExists) {
        $query = "
            SELECT e.name, SUM(c.repair_cost) as total_cost, 
                   COUNT(c.id) as checkup_count
            FROM equipment_checkups c
            JOIN equipment e ON c.equipment_id = e.id
            WHERE c.checkup_date BETWEEN ? AND ?
        ";
        
        $params = [$startDate, $endDate];
        
        if ($equipmentFilter > 0) {
            $query .= " AND c.equipment_id = ?";
            $params[] = $equipmentFilter;
        }
        
        $query .= " GROUP BY e.id ORDER BY total_cost DESC LIMIT 5";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $maintenanceCosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error calculating maintenance costs: " . $e->getMessage());
}

// Get equipment requiring maintenance
$maintenanceNeeded = [];
try {
    $stmt = $pdo->query("
        SELECT e.id, e.name, MAX(c.checkup_date) as last_checkup, 
               c.condition_status
        FROM equipment e
        LEFT JOIN equipment_checkups c ON e.id = c.equipment_id
        WHERE e.status IN ('maintenance', 'needs_attention')
        GROUP BY e.id
        ORDER BY last_checkup ASC
        LIMIT 5
    ");
    $maintenanceNeeded = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching maintenance needed: " . $e->getMessage());
}

$pageTitle = 'Asset Checkup';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-tools"></i> Asset Checkup</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='inventory.php'">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </button>
            <button class="btn btn-primary" data-toggle="modal" data-target="#addCheckupModal">
                <i class="fas fa-plus"></i> Record Checkup
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

    <!-- Search and Filter Section -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Search & Filter</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row">
                <div class="col-md-2 form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Search equipment, technician..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
                <div class="col-md-2 form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                </div>
                <div class="col-md-2 form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                </div>
                <div class="col-md-2 form-group">
                    <label for="equipment_filter">Equipment</label>
                    <select id="equipment_filter" name="equipment_filter" class="form-control">
                        <option value="0">All Equipment</option>
                        <?php foreach ($equipment as $item): ?>
                            <option value="<?php echo $item['id']; ?>" <?php echo ($equipmentFilter == $item['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($item['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label for="condition_filter">Condition</label>
                    <select id="condition_filter" name="condition_filter" class="form-control">
                        <option value="">All Conditions</option>
                        <option value="Good" <?php echo ($conditionFilter === 'Good') ? 'selected' : ''; ?>>Good</option>
                        <option value="Fair" <?php echo ($conditionFilter === 'Fair') ? 'selected' : ''; ?>>Fair</option>
                        <option value="Poor" <?php echo ($conditionFilter === 'Poor') ? 'selected' : ''; ?>>Poor</option>
                        <option value="Broken" <?php echo ($conditionFilter === 'Broken') ? 'selected' : ''; ?>>Broken</option>
                    </select>
                </div>
                <div class="col-md-2 form-group d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="asset_checkup.php" class="btn btn-outline-secondary">
                        <i class="fas fa-sync-alt"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Asset Statistics -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Maintenance Summary</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Date Range</h6>
                        <p><?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?></p>
                    </div>
                    <div class="mb-3">
                        <h6>Total Checkups</h6>
                        <p><?php echo count($checkupRecords); ?></p>
                    </div>
                    <div class="mb-3">
                        <h6>Maintenance Costs</h6>
                        <?php if (count($maintenanceCosts) > 0): ?>
                            <ul class="list-group">
                                <?php foreach ($maintenanceCosts as $cost): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($cost['name']); ?>
                                        <span class="badge badge-primary badge-pill">
                                            $<?php echo number_format($cost['total_cost'], 2); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>No maintenance costs recorded</p>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <h6>Equipment Needing Attention</h6>
                        <?php if (count($maintenanceNeeded) > 0): ?>
                            <ul class="list-group">
                                <?php foreach ($maintenanceNeeded as $item): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                        <span class="badge <?php echo ($item['condition_status'] === 'Broken') ? 'badge-danger' : 'badge-warning'; ?> badge-pill">
                                            <?php echo $item['condition_status'] ?? 'Unknown'; ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>No equipment needs maintenance</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Checkup Records Table -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Checkup Records</h5>
                </div>
                <div class="card-body">
                    <?php if (count($checkupRecords) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Equipment</th>
                                        <th>Condition</th>
                                        <th>Technician</th>
                                        <th>Repair Cost</th>
                                        <th>Next Checkup</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($checkupRecords as $record): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d', strtotime($record['checkup_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($record['equipment_name']); ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                        switch($record['condition_status']) {
                                                            case 'Good': echo 'badge-success'; break;
                                                            case 'Fair': echo 'badge-info'; break;
                                                            case 'Poor': echo 'badge-warning'; break;
                                                            case 'Broken': echo 'badge-danger'; break;
                                                            default: echo 'badge-secondary';
                                                        }
                                                    ?>">
                                                    <?php echo $record['condition_status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($record['technician']); ?></td>
                                            <td>$<?php echo number_format($record['repair_cost'], 2); ?></td>
                                            <td>
                                                <?php 
                                                    if (!empty($record['next_checkup_date'])) {
                                                        $nextDate = new DateTime($record['next_checkup_date']);
                                                        $today = new DateTime();
                                                        $interval = $today->diff($nextDate);
                                                        $daysRemaining = $nextDate > $today ? $interval->days : -$interval->days;
                                                        
                                                        $dateClass = '';
                                                        if ($daysRemaining < 0) {
                                                            $dateClass = 'text-danger';
                                                        } elseif ($daysRemaining <= 30) {
                                                            $dateClass = 'text-warning';
                                                        }
                                                        
                                                        echo '<span class="'.$dateClass.'">' . date('Y-m-d', strtotime($record['next_checkup_date'])) . '</span>';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-info view-record" 
                                                        data-toggle="modal" data-target="#viewCheckupModal"
                                                        data-id="<?php echo $record['id']; ?>"
                                                        data-equipment="<?php echo htmlspecialchars($record['equipment_name']); ?>"
                                                        data-date="<?php echo $record['checkup_date']; ?>"
                                                        data-condition="<?php echo $record['condition_status']; ?>"
                                                        data-technician="<?php echo htmlspecialchars($record['technician']); ?>"
                                                        data-notes="<?php echo htmlspecialchars($record['notes']); ?>"
                                                        data-cost="<?php echo $record['repair_cost']; ?>"
                                                        data-next="<?php echo !empty($record['next_checkup_date']) ? $record['next_checkup_date'] : ''; ?>"
                                                        data-created="<?php echo date('Y-m-d H:i', strtotime($record['created_at'])); ?>"
                                                        data-createdby="<?php echo htmlspecialchars($record['created_by_name']); ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="asset_checkup.php?action=delete&id=<?php echo $record['id']; ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Are you sure you want to delete this checkup record?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No checkup records found for the selected criteria.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Checkup Modal -->
<div class="modal fade" id="addCheckupModal" tabindex="-1" role="dialog" aria-labelledby="addCheckupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addCheckupModalLabel">Record Equipment Checkup</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="equipment_id">Equipment</label>
                                <select class="form-control" id="equipment_id" name="equipment_id" required>
                                    <option value="">Select Equipment</option>
                                    <?php foreach ($equipment as $item): ?>
                                        <option value="<?php echo $item['id']; ?>">
                                            <?php echo htmlspecialchars($item['name']); ?> 
                                            (<?php echo ucfirst($item['status']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="checkup_date">Checkup Date</label>
                                <input type="date" class="form-control" id="checkup_date" name="checkup_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="condition_status">Condition</label>
                                <select class="form-control" id="condition_status" name="condition_status" required>
                                    <option value="">Select Condition</option>
                                    <option value="Good">Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Poor">Poor</option>
                                    <option value="Broken">Broken</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="technician">Technician</label>
                                <input type="text" class="form-control" id="technician" name="technician" placeholder="Name of technician">
                            </div>
                            <div class="form-group">
                                <label for="repair_cost">Repair Cost (RM)</label>
                                <input type="number" class="form-control" id="repair_cost" name="repair_cost" step="0.01" min="0" value="0">
                            </div>
                            <div class="form-group">
                                <label for="next_checkup_date">Next Checkup Date</label>
                                <input type="date" class="form-control" id="next_checkup_date" name="next_checkup_date" value="<?php echo date('Y-m-d', strtotime('+90 days')); ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Additional notes about the checkup"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_checkup" class="btn btn-primary">Record Checkup</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Checkup Modal -->
<div class="modal fade" id="viewCheckupModal" tabindex="-1" role="dialog" aria-labelledby="viewCheckupModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewCheckupModalLabel">Checkup Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Equipment:</strong> <span id="view-equipment"></span></p>
                        <p><strong>Checkup Date:</strong> <span id="view-date"></span></p>
                        <p><strong>Condition:</strong> <span id="view-condition"></span></p>
                        <p><strong>Technician:</strong> <span id="view-technician"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Repair Cost:</strong> $<span id="view-cost"></span></p>
                        <p><strong>Next Checkup:</strong> <span id="view-next"></span></p>
                        <p><strong>Added By:</strong> <span id="view-createdby"></span></p>
                        <p><strong>Added On:</strong> <span id="view-created"></span></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <p><strong>Notes:</strong></p>
                        <div class="p-2 bg-light rounded" id="view-notes"></div>
                    </div>
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
        // Set up view modal
        $('.view-record').click(function() {
            $('#view-equipment').text($(this).data('equipment'));
            $('#view-date').text($(this).data('date'));
            
            // Set condition with appropriate badge
            var condition = $(this).data('condition');
            var badgeClass = 'badge ';
            
            switch(condition) {
                case 'Good':
                    badgeClass += 'badge-success';
                    break;
                case 'Fair':
                    badgeClass += 'badge-info';
                    break;
                case 'Poor':
                    badgeClass += 'badge-warning';
                    break;
                case 'Broken':
                    badgeClass += 'badge-danger';
                    break;
                default:
                    badgeClass += 'badge-secondary';
            }
            
            $('#view-condition').html('<span class="' + badgeClass + '">' + condition + '</span>');
            $('#view-technician').text($(this).data('technician'));
            $('#view-cost').text(parseFloat($(this).data('cost')).toFixed(2));
            $('#view-next').text($(this).data('next') || 'Not scheduled');
            $('#view-notes').text($(this).data('notes'));
            $('#view-created').text($(this).data('created'));
            $('#view-createdby').text($(this).data('createdby'));
        });
        
        // Update next checkup date based on condition
        $('#condition_status').change(function() {
            var condition = $(this).val();
            var nextDate = new Date();
            
            // Set different intervals based on condition
            switch(condition) {
                case 'Good':
                    // If condition is good, schedule next checkup in 180 days
                    nextDate.setDate(nextDate.getDate() + 180);
                    break;
                case 'Fair':
                    // If condition is fair, schedule next checkup in 90 days
                    nextDate.setDate(nextDate.getDate() + 90);
                    break;
                case 'Poor':
                    // If condition is poor, schedule next checkup in 30 days
                    nextDate.setDate(nextDate.getDate() + 30);
                    break;
                case 'Broken':
                    // If broken, schedule checkup after estimated repair time (14 days)
                    nextDate.setDate(nextDate.getDate() + 14);
                    break;
                default:
                    // Default to 90 days
                    nextDate.setDate(nextDate.getDate() + 90);
            }
            
            // Format date as YYYY-MM-DD for input
            var month = (nextDate.getMonth() + 1).toString().padStart(2, '0');
            var day = nextDate.getDate().toString().padStart(2, '0');
            var formattedDate = nextDate.getFullYear() + '-' + month + '-' + day;
            
            $('#next_checkup_date').val(formattedDate);
        });
    });
</script>

<style>
    .table th {
        background-color: #f8f9fa;
    }
    
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    .page-header {
        margin-bottom: 20px;
    }
    
    .list-group-item {
        padding: 0.5rem 1rem;
    }
    
    .badge-success {
        background-color: #28a745;
    }
    
    .badge-info {
        background-color: #17a2b8;
    }
    
    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }
    
    .badge-danger {
        background-color: #dc3545;
    }
</style>

<?php include 'includes/footer.php'; ?>