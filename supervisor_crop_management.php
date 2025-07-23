<?php
require_once 'includes/auth.php';
auth()->checkSupervisor(); // Ensure only supervisors can access

require_once 'includes/db.php';

// Initialize error reporting for debugging during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle form submissions for various crop management functions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SUPERVISOR FUNCTIONALITY
    
    // 1. Record Planting Details
    if (isset($_POST['record_planting'])) {
        try {
            // Record planting details (FR 3.1: Input planting dates)
            $stmt = $pdo->prepare("INSERT INTO planting_records (crop_id, planting_date, seed_quantity, field_location, fertilizer_applied, weather_conditions, notes) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['crop_id'],
                $_POST['planting_date'],
                $_POST['seed_quantity'],
                $_POST['field_location'],
                $_POST['fertilizer_applied'] ?? '',
                $_POST['weather_conditions'] ?? '',
                $_POST['notes'] ?? ''
            ]);
            
            // Update crop status to 'active' if it's the first planting
            $updateStmt = $pdo->prepare("UPDATE crops SET status = 'active', next_action = 'Planting recorded', next_action_date = CURRENT_DATE() WHERE id = ?");
            $updateStmt->execute([$_POST['crop_id']]);
            
            $_SESSION['success'] = "Planting details recorded successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error recording planting details: " . $e->getMessage();
        }
        header('Location: supervisor_crop_management.php');
        exit();
    }
    
    // 2. Update Growth Stage
    if (isset($_POST['update_growth_stage'])) {
        try {
            // Update crop growth stage (FR 3.3: Track growth milestones)
            $stmt = $pdo->prepare("UPDATE crops SET growth_stage = ?, next_action = ?, next_action_date = CURRENT_DATE() WHERE id = ?");
            $stmt->execute([
                $_POST['growth_stage'],
                'Growth stage updated to ' . $_POST['growth_stage'],
                $_POST['crop_id']
            ]);
                
            // Try to record growth milestone if table exists
            try {
                $milestoneStmt = $pdo->prepare("INSERT INTO growth_milestones (crop_id, stage, date_reached, notes) VALUES (?, ?, ?, ?)");
                $milestoneStmt->execute([
                    $_POST['crop_id'],
                    $_POST['growth_stage'],
                    $_POST['date_reached'],
                    $_POST['notes'] ?? ''
                ]);
            } catch(PDOException $e) {
                // Ignore if growth_milestones table doesn't exist
                error_log("Growth milestones table may not exist: " . $e->getMessage());
            }
            
            $_SESSION['success'] = "Growth stage updated successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error updating growth stage: " . $e->getMessage();
        }
        header('Location: supervisor_crop_management.php');
        exit();
    }
    
    // 3. Report Pest/Disease Issues
    if (isset($_POST['report_issue'])) {
        try {
            // Insert pest/disease issue (FR 5.1: Report pest and disease incidents)
            $stmt = $pdo->prepare("INSERT INTO crop_issues (crop_id, issue_type, description, severity, date_identified, affected_area, notes, resolved, treatment_applied) 
                                 VALUES (?, ?, ?, ?, CURRENT_DATE(), ?, ?, 0, 0)");
            $stmt->execute([
                $_POST['crop_id'],
                $_POST['issue_type'],
                $_POST['description'],
                $_POST['severity'],
                $_POST['affected_area'],
                $_POST['notes'] ?? ''
            ]);
            
            // Try to record activity if table exists
            try {
                $activityStmt = $pdo->prepare("INSERT INTO crop_activities (crop_id, activity_type, activity_date, description, performed_by) 
                                              VALUES (?, 'issue', CURRENT_DATE(), ?, ?)");
                $activityStmt->execute([
                    $_POST['crop_id'],
                    'Reported ' . $_POST['issue_type'] . ' issue: ' . $_POST['description'],
                    $_SESSION['user_id']
                ]);
            } catch(PDOException $e) {
                // Ignore if crop_activities table doesn't exist
                error_log("Crop activities table may not exist: " . $e->getMessage());
            }
            
            $_SESSION['success'] = "Issue reported successfully.";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error reporting issue: " . $e->getMessage();
        }
        header('Location: supervisor_crop_management.php');
        exit();
    }
    
    // 4. Apply Treatment for Pests/Diseases - FIXED VERSION
    // 4. Apply Treatment for Pests/Diseases - FIXED VERSION
    if (isset($_POST['apply_treatment'])) {
        $transactionStarted = false;
        try {
            // Start transaction to ensure data consistency
            $pdo->beginTransaction();
            $transactionStarted = true;
            
            // Check if treatments table exists first
            $checkTable = $pdo->query("SHOW TABLES LIKE 'treatments'");
            if ($checkTable->rowCount() == 0) {
                // Create treatments table if it doesn't exist
                $createTableSQL = "
                    CREATE TABLE treatments (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        crop_id INT NOT NULL,
                        issue_id INT DEFAULT NULL,
                        treatment_type VARCHAR(255) NOT NULL,
                        application_date DATE NOT NULL,
                        quantity_used VARCHAR(100) NOT NULL,
                        application_method VARCHAR(100) NOT NULL,
                        notes TEXT,
                        applied_by INT DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE,
                        FOREIGN KEY (issue_id) REFERENCES crop_issues(id) ON DELETE SET NULL
                    )
                ";
                $pdo->exec($createTableSQL);
            }
            
            // Insert treatment application
            $stmt = $pdo->prepare("
                INSERT INTO treatments (crop_id, issue_id, treatment_type, application_date, quantity_used, application_method, notes, applied_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['crop_id'],
                $_POST['issue_id'] ?? null,
                $_POST['treatment_type'],
                $_POST['application_date'],
                $_POST['quantity_used'],
                $_POST['application_method'],
                $_POST['notes'] ?? '',
                $_SESSION['user_id']
            ]);
            
            // FIXED: Update crop_issues table - ensure columns exist first
            if (!empty($_POST['issue_id'])) {
                // Check if required columns exist in crop_issues table
                $checkColumns = $pdo->query("SHOW COLUMNS FROM crop_issues LIKE 'treatment_applied'");
                if ($checkColumns->rowCount() == 0) {
                    // Add missing columns
                    $pdo->exec("ALTER TABLE crop_issues ADD COLUMN treatment_applied TINYINT(1) DEFAULT 0");
                }
                
                $checkColumns = $pdo->query("SHOW COLUMNS FROM crop_issues LIKE 'treatment_date'");
                if ($checkColumns->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE crop_issues ADD COLUMN treatment_date DATE DEFAULT NULL");
                }
                
                // Now update the issue with treatment information
                $updateIssueStmt = $pdo->prepare("
                    UPDATE crop_issues 
                    SET treatment_applied = 1,
                        treatment_date = ?,
                        notes = CONCAT(IFNULL(notes, ''), '\n', 'Treatment Applied: ', ?, ' on ', ?, '. Quantity: ', ?, '. Method: ', ?, '. Notes: ', IFNULL(?, ''))
                    WHERE id = ?
                ");
                $updateIssueStmt->execute([
                    $_POST['application_date'],
                    $_POST['treatment_type'],
                    $_POST['application_date'],
                    $_POST['quantity_used'],
                    $_POST['application_method'],
                    $_POST['notes'] ?? '',
                    $_POST['issue_id']
                ]);
                
                // Verify the update was successful
                $verifyStmt = $pdo->prepare("SELECT treatment_applied FROM crop_issues WHERE id = ?");
                $verifyStmt->execute([$_POST['issue_id']]);
                $result = $verifyStmt->fetch();
                
                if ($result && $result['treatment_applied'] == 1) {
                    error_log("Treatment successfully applied to issue ID: " . $_POST['issue_id']);
                } else {
                    error_log("Failed to update treatment status for issue ID: " . $_POST['issue_id']);
                    throw new Exception("Failed to update treatment status");
                }
            }
            
            // Commit transaction
            if ($transactionStarted) {
                $pdo->commit();
                $transactionStarted = false;
            }
            
            $_SESSION['success'] = "Treatment applied successfully! Issue status has been updated.";
            
        } catch(PDOException $e) {
            // Rollback transaction on error (only if transaction was started)
            if ($transactionStarted) {
                try {
                    $pdo->rollback();
                } catch(PDOException $rollbackError) {
                    error_log("Rollback failed: " . $rollbackError->getMessage());
                }
            }
            $_SESSION['error'] = "Error applying treatment: " . $e->getMessage();
            error_log("Treatment application error: " . $e->getMessage());
        } catch(Exception $e) {
            // Handle other exceptions
            if ($transactionStarted) {
                try {
                    $pdo->rollback();
                } catch(PDOException $rollbackError) {
                    error_log("Rollback failed: " . $rollbackError->getMessage());
                }
            }
            $_SESSION['error'] = "Error applying treatment: " . $e->getMessage();
            error_log("Treatment application error: " . $e->getMessage());
        }
        header('Location: supervisor_crop_management.php');
        exit();
    }
    
    // 5. Record Harvest
    if (isset($_POST['record_harvest'])) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS harvest_records (
                id INT AUTO_INCREMENT PRIMARY KEY,
                crop_id INT NOT NULL,
                harvest_date DATE NOT NULL,
                quantity_harvested VARCHAR(100) NOT NULL,
                quality_grade ENUM('excellent', 'good', 'fair', 'poor') NOT NULL,
                notes TEXT,
                recorded_by INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $stmt = $pdo->prepare("
                INSERT INTO harvest_records (crop_id, harvest_date, quantity_harvested, quality_grade, notes, recorded_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $_POST['crop_id'],
                $_POST['harvest_date'],
                $_POST['quantity_harvested'],
                $_POST['quality_grade'],
                $_POST['notes'] ?? '',
                $_SESSION['user_id']
            ]);
            
            // Update crop status to 'harvested'
            $updateStmt = $pdo->prepare("UPDATE crops SET status = 'harvested', next_action = 'Harvested', next_action_date = CURRENT_DATE() WHERE id = ?");
            $updateStmt->execute([$_POST['crop_id']]);
            
            $_SESSION['success'] = "Harvest recorded successfully!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error recording harvest: " . $e->getMessage();
        }
        header('Location: supervisor_crop_management.php');
        exit();
    }
    
    // 6. Record Environmental Data
    if (isset($_POST['record_environmental_data'])) {
        try {
            // Insert environmental data
            $stmt = $pdo->prepare("
                INSERT INTO environmental_data (field_id, recorded_date, temperature, humidity, soil_moisture, rainfall, wind_speed, notes, recorded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['field_id'],
                $_POST['recorded_date'],
                $_POST['temperature'],
                $_POST['humidity'],
                $_POST['soil_moisture'],
                $_POST['rainfall'],
                $_POST['wind_speed'],
                $_POST['notes'] ?? '',
                $_SESSION['user_id']
            ]);
            
            $_SESSION['success'] = "Environmental data recorded successfully!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error recording environmental data: " . $e->getMessage();
        }
        header('Location: supervisor_crop_management.php');
        exit();
    }

    // 7. LOG IRRIGATION (NEW FEATURE)
    if (isset($_POST['log_irrigation_supervisor'])) {
        try {
            // Insert irrigation log
            $stmt = $pdo->prepare("
                INSERT INTO irrigation_logs 
                (schedule_id, irrigation_date, amount_used, notes) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['schedule_id'],
                $_POST['irrigation_date'],
                $_POST['amount_used'],
                $_POST['notes'] ?? ''
            ]);
            
            // Update the last_irrigation_date in schedules table
            $stmt = $pdo->prepare("
                UPDATE irrigation_schedules 
                SET last_irrigation_date = ?, 
                    next_irrigation_date = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['irrigation_date'],
                $_POST['next_irrigation_date'],
                $_POST['schedule_id']
            ]);
            
            $_SESSION['success'] = "Irrigation logged successfully!";
            error_log("Irrigation logged by supervisor for schedule ID: " . $_POST['schedule_id']);
            
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error logging irrigation: " . $e->getMessage();
            error_log("Error logging irrigation: " . $e->getMessage());
        }
        header('Location: supervisor_crop_management.php');
        exit();
    }
}

// Initialize arrays for dashboard data
$crops = [];
$active_crops_count = 0;
$needs_attention = 0;
$pest_disease_issues = 0;
$upcoming_harvests = 0;
$recent_environmental_data = [];
$active_issues = [];

try {
    // Get all crops for this supervisor
    $stmt = $pdo->prepare("
        SELECT c.*, f.field_name, f.location
        FROM crops c
        LEFT JOIN fields f ON c.field_id = f.id
        ORDER BY c.planting_date DESC
    ");
    $stmt->execute();
    $crops = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total active crops
    $active_crops_count = count(array_filter($crops, function($crop) {
        return $crop['status'] === 'active';
    }));
    
    // Count crops needing attention - simplified query
    try {
        $needs_attention = $pdo->query("
            SELECT COUNT(*) FROM crops 
            WHERE status = 'active'
        ")->fetchColumn();
    } catch(PDOException $e) {
        $needs_attention = 0;
    }
    
    // Count pest/disease issues - FIXED: More robust query
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM crop_issues ci
            JOIN crops c ON ci.crop_id = c.id
            WHERE (ci.resolved = 0 OR ci.resolved IS NULL) 
            AND (ci.treatment_applied = 0 OR ci.treatment_applied IS NULL)
            AND ci.issue_type IN ('pest', 'disease')
        ");
        $stmt->execute();
        $pest_disease_issues = $stmt->fetchColumn();
    } catch(PDOException $e) {
        $pest_disease_issues = 0;
        error_log("Error counting pest/disease issues: " . $e->getMessage());
    }
    
    // Count upcoming harvests
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM crops 
            WHERE expected_harvest_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
            AND status = 'active'
        ");
        $stmt->execute();
        $upcoming_harvests = $stmt->fetchColumn();
    } catch(PDOException $e) {
        $upcoming_harvests = 0;
    }
    
    // Try to get recent environmental data - use weather_data if available
    try {
        $envStmt = $pdo->prepare("
            SELECT wd.*, 'Weather Station' as field_name
            FROM weather_data wd
            ORDER BY wd.recorded_date DESC
            LIMIT 5
        ");
        $envStmt->execute();
        $recent_environmental_data = $envStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // If weather_data table doesn't exist, just set empty array
        $recent_environmental_data = [];
    }
    
    // Get active pest/disease issues - FIXED: Better query with proper status checking
    try {
        $issueStmt = $pdo->prepare("
            SELECT ci.*, c.crop_name, IFNULL(f.field_name, 'Unknown Field') as field_name
            FROM crop_issues ci
            JOIN crops c ON ci.crop_id = c.id
            LEFT JOIN fields f ON c.field_id = f.id
            WHERE (ci.resolved = 0 OR ci.resolved IS NULL)
            ORDER BY ci.severity DESC, ci.date_identified DESC
            LIMIT 5
        ");
        $issueStmt->execute();
        $active_issues = $issueStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Log the number of active issues found
        error_log("Found " . count($active_issues) . " active issues");
        
    } catch(PDOException $e) {
        $active_issues = [];
        error_log("Error fetching active issues: " . $e->getMessage());
    }

    // Get all fields for dropdown
    try {
        $fieldsStmt = $pdo->query("SELECT id, field_name, location, area FROM fields ORDER BY field_name");
        $available_fields = $fieldsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $available_fields = [];
    }
    
} catch(PDOException $e) {
    error_log("Error fetching crop data for supervisor: " . $e->getMessage());
    $_SESSION['error'] = "Error loading crop management data: " . $e->getMessage();
}

// IRRIGATION DATA - NEW SECTION
try {
    // Get recent irrigation logs
    $irrigationStmt = $pdo->prepare("
        SELECT 
            il.*, 
            c.crop_name, 
            IFNULL(f.field_name, 'Unknown Field') as field_name,
            isch.schedule_description,
            isch.water_amount as scheduled_amount
        FROM irrigation_logs il
        JOIN irrigation_schedules isch ON il.schedule_id = isch.id
        JOIN crops c ON isch.crop_id = c.id
        LEFT JOIN fields f ON c.field_id = f.id
        ORDER BY il.irrigation_date DESC
        LIMIT 5
    ");
    $irrigationStmt->execute();
    $recent_irrigation_logs = $irrigationStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get irrigation schedules for this supervisor's crops
    $irrigationSchedulesStmt = $pdo->prepare("
        SELECT 
            isch.*, 
            c.crop_name, 
            f.field_name,
            CASE 
                WHEN isch.next_irrigation_date <= CURRENT_DATE THEN 'due'
                WHEN isch.next_irrigation_date <= DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY) THEN 'due_soon'
                ELSE 'scheduled'
            END as status
        FROM irrigation_schedules isch
        JOIN crops c ON isch.crop_id = c.id
        LEFT JOIN fields f ON c.field_id = f.id
        WHERE c.status = 'active'
        ORDER BY isch.next_irrigation_date ASC
        LIMIT 5
    ");
    $irrigationSchedulesStmt->execute();
    $irrigation_schedules = $irrigationSchedulesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log the count of records found
    error_log("Found " . count($recent_irrigation_logs) . " irrigation log records");
    error_log("Found " . count($irrigation_schedules) . " irrigation schedule records");
    
} catch(PDOException $e) {
    error_log("Error fetching irrigation data: " . $e->getMessage());
    $recent_irrigation_logs = [];
    $irrigation_schedules = [];
}

// Get current date and time information
$currentDate = date('M d, Y');
$currentTime = date('g:i A');
$currentDateTime = date('M d, Y g:i A (T)');

// Calculate forecast hours
$forecastHours = [];
$currentHour = (int)date('H');
for ($i = 0; $i < 7; $i++) {
    $hour = ($currentHour + $i) % 24;
    $forecastHours[] = sprintf('%02d:00', $hour);
}

// Calculate expected rain date (3 days from now)
$expectedRainDate = date('M d, Y', strtotime('+3 days'));

$pageTitle = 'Crop Management';
include 'includes/header.php';
?>

<div class="main-content">
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h2><i class="fas fa-seedling"></i> Crop Management</h2>
        <div class="action-buttons">
        <button class="btn btn-primary" data-toggle="modal" data-target="#recordPlantingModal">
            <i class="fas fa-seedling"></i> Record Planting
        </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon bg-blue">
                <i class="fas fa-seedling"></i>
            </div>
            <div class="summary-details">
                <h3>Active Crops</h3>
                <p class="summary-count"><?php echo $active_crops_count; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-orange">
                <i class="fas fa-exclamation"></i>
            </div>
            <div class="summary-details">
                <h3>Needs Attention</h3>
                <p class="summary-count"><?php echo $needs_attention; ?></p>
                <span class="summary-subtitle">Next 7 days</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-red">
                <i class="fas fa-bug"></i>
            </div>
            <div class="summary-details">
                <h3>Pest/Disease Issues</h3>
                <p class="summary-count"><?php echo $pest_disease_issues; ?></p>
                <span class="summary-subtitle">Active cases</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-green">
                <i class="fas fa-tractor"></i>
            </div>
            <div class="summary-details">
                <h3>Upcoming Harvests</h3>
                <p class="summary-count"><?php echo $upcoming_harvests; ?></p>
                <span class="summary-subtitle">Next 30 days</span>
            </div>
        </div>
    </div>

    <!-- Active Issues Section -->
    <div class="content-card mt-4">
        <div class="content-card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Active Issues</h3>
            <div class="card-actions">
                <button class="btn btn-sm btn-outline-primary" onclick="showAllIssues()">
                    <i class="fas fa-list"></i> View All Issues
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($active_issues)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <p>No active issues at the moment.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Crop</th>
                                <th>Field</th>
                                <th>Issue Type</th>
                                <th>Description</th>
                                <th>Severity</th>
                                <th>Identified</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_issues as $issue): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($issue['crop_name']); ?></td>
                                    <td><?php echo htmlspecialchars($issue['field_name']); ?></td>
                                    <td>
                                        <?php if ($issue['issue_type'] === 'pest'): ?>
                                            <span class="badge bg-danger">Pest</span>
                                        <?php elseif ($issue['issue_type'] === 'disease'): ?>
                                            <span class="badge bg-warning">Disease</span>
                                        <?php else: ?>
                                            <span class="badge bg-info"><?php echo ucfirst(htmlspecialchars($issue['issue_type'])); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($issue['description']); ?></td>
                                    <td>
                                        <?php 
                                        switch($issue['severity']) {
                                            case 'high':
                                                echo '<span class="priority-badge priority-high">High</span>';
                                                break;
                                            case 'medium':
                                                echo '<span class="priority-badge priority-medium">Medium</span>';
                                                break;
                                            default:
                                                echo '<span class="priority-badge priority-low">Low</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($issue['date_identified'])); ?></td>
                                    <td>
                                        <?php if ($issue['treatment_applied'] == 1): ?>
                                            <span class="status-badge status-in-progress">Treated</span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <?php if ($issue['treatment_applied'] != 1): ?>
                                            <button class="btn-icon" data-toggle="modal" data-target="#applyTreatmentModal" 
                                                    onclick="setupTreatment(<?php echo $issue['crop_id']; ?>, <?php echo $issue['id']; ?>, '<?php echo htmlspecialchars($issue['issue_type']); ?>')"
                                                    title="Apply Treatment">
                                                <i class="fas fa-prescription-bottle"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-icon" onclick="viewIssueDetails(<?php echo $issue['id']; ?>)" title="View Details">
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

    <!-- Weather & Environmental Conditions -->
    <div class="content-card mt-4">
        <div class="content-card-header">
            <h3><i class="fas fa-cloud-sun"></i> Weather & Environmental Conditions</h3>
            <div class="card-actions">
                <button class="btn btn-sm btn-outline-primary refresh-weather" onclick="refreshWeather()">
                    <i class="fas fa-sync-alt"></i> Refresh Data
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Current Weather Overview -->
            <div class="weather-container">
                <div class="current-weather">
                    <div class="weather-main">
                        <div class="temperature">
                            <span class="temp-value">25</span>
                            <span class="temp-unit">°C</span>
                        </div>
                        <div class="weather-icon">
                            <i class="fas fa-cloud-sun"></i>
                            <div class="condition">Partly Cloudy</div>
                        </div>
                    </div>
                    <div class="weather-details">
                        <div class="detail-item">
                            <i class="fas fa-tint"></i>
                            <span>Humidity: 65%</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-wind"></i>
                            <span>Wind: 12 km/h NE</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-compress-alt"></i>
                            <span>Pressure: 1012 hPa</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-water"></i>
                            <span>Precipitation: 0 mm</span>
                        </div>
                    </div>
                    <div class="last-updated">
                        <i class="far fa-clock"></i>
                        <span>Last Updated: <?php echo $currentDateTime; ?></span>
                    </div>
                </div>
            </div>

            <!-- Weather Alerts -->
            <div class="weather-alerts">
                <div class="alert alert-warning">
                    <div class="alert-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="alert-content">
                        <h4>Heavy Rain Expected</h4>
                        <p>Heavy rainfall expected in 3 days. Consider adjusting irrigation schedules.</p>
                        <span class="alert-date">Expected: <?php echo $expectedRainDate; ?></span>
                    </div>
                </div>
            </div>

            <!-- Hourly Forecast -->
            <div class="forecast-section">
                <h4>Hourly Forecast</h4>
                <div class="hourly-forecast">
                    <div class="forecast-item">
                        <div class="forecast-time"><?php echo $forecastHours[0]; ?></div>
                        <div class="forecast-icon"><i class="fas fa-sun"></i></div>
                        <div class="forecast-temp">25°C</div>
                        <div class="forecast-condition">Sunny</div>
                    </div>
                    <div class="forecast-item">
                        <div class="forecast-time"><?php echo $forecastHours[1]; ?></div>
                        <div class="forecast-icon"><i class="fas fa-sun"></i></div>
                        <div class="forecast-temp">26°C</div>
                        <div class="forecast-condition">Sunny</div>
                    </div>
                    <div class="forecast-item">
                        <div class="forecast-time"><?php echo $forecastHours[2]; ?></div>
                        <div class="forecast-icon"><i class="fas fa-cloud-sun"></i></div>
                        <div class="forecast-temp">26°C</div>
                        <div class="forecast-condition">Partly Cloudy</div>
                    </div>
                    <div class="forecast-item">
                        <div class="forecast-time"><?php echo $forecastHours[3]; ?></div>
                        <div class="forecast-icon"><i class="fas fa-cloud-sun"></i></div>
                        <div class="forecast-temp">25°C</div>
                        <div class="forecast-condition">Partly Cloudy</div>
                    </div>
                    <div class="forecast-item">
                        <div class="forecast-time"><?php echo $forecastHours[4]; ?></div>
                        <div class="forecast-icon"><i class="fas fa-cloud"></i></div>
                        <div class="forecast-temp">24°C</div>
                        <div class="forecast-condition">Cloudy</div>
                    </div>
                    <div class="forecast-item">
                        <div class="forecast-time"><?php echo $forecastHours[5]; ?></div>
                        <div class="forecast-icon"><i class="fas fa-cloud"></i></div>
                        <div class="forecast-temp">23°C</div>
                        <div class="forecast-condition">Cloudy</div>
                    </div>
                    <div class="forecast-item">
                        <div class="forecast-time"><?php echo $forecastHours[6]; ?></div>
                        <div class="forecast-icon"><i class="fas fa-moon"></i></div>
                        <div class="forecast-temp">22°C</div>
                        <div class="forecast-condition">Clear</div>
                    </div>
                </div>
            </div>

            <!-- Field Conditions -->
            <div class="field-conditions-section">
                <h4>Field Conditions</h4>
                <div class="field-conditions-grid">
                    <div class="field-condition-card">
                        <h5>Main Field</h5>
                        <div class="field-condition-icon"><i class="fas fa-cloud"></i></div>
                        <div class="field-condition-temp">24°C</div>
                        <div class="field-condition-details">
                            <div><i class="fas fa-tint"></i> 63%</div>
                            <div><i class="fas fa-wind"></i> 11.1 km/h</div>
                        </div>
                    </div>
                    <div class="field-condition-card">
                        <h5>North Field</h5>
                        <div class="field-condition-icon"><i class="fas fa-cloud"></i></div>
                        <div class="field-condition-temp">23°C</div>
                        <div class="field-condition-details">
                            <div><i class="fas fa-tint"></i> 65%</div>
                            <div><i class="fas fa-wind"></i> 14.7 km/h</div>
                        </div>
                    </div>
                    <div class="field-condition-card">
                        <h5>South Field</h5>
                        <div class="field-condition-icon"><i class="fas fa-cloud"></i></div>
                        <div class="field-condition-temp">23°C</div>
                        <div class="field-condition-details">
                            <div><i class="fas fa-tint"></i> 66%</div>
                            <div><i class="fas fa-wind"></i> 14.2 km/h</div>
                        </div>
                    </div>
                    <div class="field-condition-card">
                        <h5>East Field</h5>
                        <div class="field-condition-icon"><i class="fas fa-cloud"></i></div>
                        <div class="field-condition-temp">25°C</div>
                        <div class="field-condition-details">
                            <div><i class="fas fa-tint"></i> 71%</div>
                            <div><i class="fas fa-wind"></i> 13.3 km/h</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Irrigation Logs - NEW SECTION -->
    <div class="content-card mt-4">
        <div class="content-card-header">
            <h3><i class="fas fa-tint"></i> Recent Irrigation Logs</h3>
            <div class="card-actions">
                <button class="btn btn-sm btn-outline-primary" onclick="showAllIrrigationLogs()">
                    <i class="fas fa-list"></i> View All Logs
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($recent_irrigation_logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-tint fa-3x text-info mb-3"></i>
                    <p>No irrigation logs recorded yet.</p>
                    <button class="btn btn-info mt-2" data-bs-toggle="modal" data-bs-target="#logIrrigationModal">
                        Log First Irrigation
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Crop</th>
                                <th>Field</th>
                                <th>Irrigation Date</th>
                                <th>Amount Used</th>
                                <th>Scheduled Amount</th>
                                <th>Schedule</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_irrigation_logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['crop_name']); ?></td>
                                    <td><?php echo htmlspecialchars($log['field_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($log['irrigation_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($log['amount_used']); ?> L</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($log['scheduled_amount']); ?> L</span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['schedule_description']); ?></td>
                                    <td><?php echo htmlspecialchars($log['notes'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Irrigation Schedules - NEW SECTION -->
    <div class="content-card mt-4">
        <div class="content-card-header">
            <h3><i class="fas fa-calendar-check"></i> Upcoming Irrigation Schedules</h3>
            <div class="card-actions">
                <button class="btn btn-sm btn-outline-success" onclick="showAllSchedules()">
                    <i class="fas fa-calendar-alt"></i> View All Schedules
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($irrigation_schedules)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times fa-3x text-warning mb-3"></i>
                    <p>No irrigation schedules found.</p>
                    <small class="text-muted">Contact admin to set up irrigation schedules.</small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Crop</th>
                                <th>Field</th>
                                <th>Schedule</th>
                                <th>Water Amount</th>
                                <th>Next Irrigation</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($irrigation_schedules as $schedule): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($schedule['crop_name']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['field_name']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['schedule_description']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['water_amount']); ?> L</td>
                                    <td><?php echo date('M d, Y', strtotime($schedule['next_irrigation_date'])); ?></td>
                                    <td>
                                        <?php 
                                        switch($schedule['status']) {
                                            case 'due':
                                                echo '<span class="status-badge status-pending">Due Now</span>';
                                                break;
                                            case 'due_soon':
                                                echo '<span class="status-badge status-in-progress">Due Soon</span>';
                                                break;
                                            default:
                                                echo '<span class="status-badge status-active">Scheduled</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="actions">
                                        <button class="btn-icon btn-icon-success" 
                                                data-toggle="modal" 
                                                data-target="#logIrrigationModal"
                                                onclick="setupIrrigationLog(<?php echo $schedule['id']; ?>, '<?php echo htmlspecialchars($schedule['crop_name']); ?>', '<?php echo htmlspecialchars($schedule['field_name']); ?>', '<?php echo htmlspecialchars($schedule['schedule_description']); ?>', <?php echo $schedule['water_amount']; ?>)"
                                                title="Log Irrigation">
                                            <i class="fas fa-tint"></i>
                                        </button>
                                        <button class="btn-icon" onclick="viewScheduleDetails(<?php echo $schedule['id']; ?>)" title="View Details">
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

    <!-- Crops Table -->
    <div class="content-card mt-4">
        <div class="content-card-header">
            <h3><i class="fas fa-seedling"></i> Crop Management</h3>
            <div class="card-actions">
                <div class="search-bar">
                    <input type="text" id="cropSearch" onkeyup="searchTable('cropsTable', 'cropSearch')" placeholder="Search crops...">
                    <i class="fas fa-search"></i>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table" id="cropsTable">
                <thead>
                    <tr>
                        <th>Crop Name</th>
                        <th>Variety</th>
                        <th>Field Location</th>
                        <th>Planting Date</th>
                        <th>Expected Harvest</th>
                        <th>Growth Stage</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($crops)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No crops found. Contact admin to set up crops.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($crops as $crop): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($crop['crop_name']); ?></td>
                                <td><?php echo htmlspecialchars($crop['variety']); ?></td>
                                <td><?php echo htmlspecialchars($crop['field_name'] ?? 'Not assigned'); ?></td>
                                <td><?php echo !empty($crop['planting_date']) ? date('M d, Y', strtotime($crop['planting_date'])) : 'Not set'; ?></td>
                                <td>
                                    <?php 
                                    if(!empty($crop['expected_harvest_date'])) {
                                        echo date('M d, Y', strtotime($crop['expected_harvest_date']));
                                        
                                        $today = new DateTime();
                                        $harvest = new DateTime($crop['expected_harvest_date']);
                                        $interval = $today->diff($harvest);
                                        $days_until = $interval->format('%R%a');
                                        
                                        if($days_until > 0 && $days_until <= 7) {
                                            echo ' <span class="badge bg-success">Soon</span>';
                                        } elseif($days_until <= 0) {
                                            echo ' <span class="badge bg-danger">Overdue</span>';
                                        }
                                    } else {
                                        echo 'Not set';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $stage = $crop['growth_stage'] ?? 'not set';
                                    switch(strtolower($stage)) {
                                        case 'seedling':
                                            echo '<span class="stage-badge stage-seedling">Seedling</span>';
                                            break;
                                        case 'vegetative':
                                            echo '<span class="stage-badge stage-vegetative">Vegetative</span>';
                                            break;
                                        case 'flowering':
                                            echo '<span class="stage-badge stage-flowering">Flowering</span>';
                                            break;
                                        case 'fruiting':
                                            echo '<span class="stage-badge stage-fruiting">Fruiting</span>';
                                            break;
                                        case 'mature':
                                            echo '<span class="stage-badge stage-mature">Mature</span>';
                                            break;
                                        default:
                                            echo '<span class="stage-badge stage-none">' . ucfirst($stage) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $status = $crop['status'] ?? 'unknown';
                                    switch(strtolower($status)) {
                                        case 'active':
                                            echo '<span class="status-badge status-active">Active</span>';
                                            break;
                                        case 'harvested':
                                            echo '<span class="status-badge status-harvested">Harvested</span>';
                                            break;
                                        case 'failed':
                                            echo '<span class="status-badge status-failed">Failed</span>';
                                            break;
                                        case 'planned':
                                            echo '<span class="status-badge status-planned">Planned</span>';
                                            break;
                                        default:
                                            echo '<span class="status-badge">' . ucfirst($status) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td class="actions">
                                    <button class="btn-icon" data-toggle="modal" data-target="#updateGrowthStageModal" 
                                            onclick="setupGrowthStageUpdate(<?php echo $crop['id']; ?>, '<?php echo htmlspecialchars($crop['crop_name']); ?>', '<?php echo $crop['growth_stage']; ?>')" 
                                            title="Update Growth Stage">
                                        <i class="fas fa-seedling"></i>
                                    </button>
                                    <button class="btn-icon" onclick="viewCropDetails(<?php echo $crop['id']; ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon" data-toggle="modal" data-target="#reportIssueModal" 
                                            onclick="setupIssueReport(<?php echo $crop['id']; ?>, '<?php echo htmlspecialchars($crop['crop_name']); ?>')"
                                            title="Report Issue">
                                        <i class="fas fa-bug"></i>
                                    </button>
                                    <?php if($crop['status'] == 'active'): ?>
                                        <button class="btn-icon btn-icon-success" data-toggle="modal" data-target="#recordHarvestModal"
                                                onclick="setupHarvest(<?php echo $crop['id']; ?>, '<?php echo htmlspecialchars($crop['crop_name']); ?>')" 
                                                title="Record Harvest">
                                            <i class="fas fa-tractor"></i>
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

<!-- Modal: Record Planting -->
<div class="modal fade" id="recordPlantingModal" tabindex="-1" aria-labelledby="recordPlantingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="recordPlantingModalLabel"><i class="fas fa-seedling"></i> Record Planting Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="record_planting" value="1">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="crop_id" class="form-label">Crop</label>
                            <select class="form-select" id="crop_id" name="crop_id" required>
                                <option value="">Select a crop</option>
                                <?php foreach ($crops as $crop): ?>
                                    <option value="<?php echo $crop['id']; ?>">
                                        <?php echo htmlspecialchars($crop['crop_name'] . ' (' . ($crop['field_name'] ?? 'Unknown Field') . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="planting_date" class="form-label">Planting Date</label>
                            <input type="date" class="form-control" id="planting_date" name="planting_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="seed_quantity" class="form-label">Seed Quantity</label>
                            <input type="text" class="form-control" id="seed_quantity" name="seed_quantity" required>
                        </div>
                        <div class="col-md-6">
                            <label for="field_location" class="form-label">Field Location</label>
                            <input type="text" class="form-control" id="field_location" name="field_location" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="fertilizer_applied" class="form-label">Fertilizer Applied</label>
                            <input type="text" class="form-control" id="fertilizer_applied" name="fertilizer_applied">
                        </div>
                        <div class="col-md-6">
                            <label for="weather_conditions" class="form-label">Weather Conditions</label>
                            <input type="text" class="form-control" id="weather_conditions" name="weather_conditions">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes_planting" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes_planting" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Planting</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Log Irrigation - NEW MODAL -->
<div class="modal fade" id="logIrrigationModal" tabindex="-1" aria-labelledby="logIrrigationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logIrrigationModalLabel"><i class="fas fa-tint"></i> Log Irrigation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="log_irrigation_supervisor" value="1">
                <input type="hidden" name="schedule_id" id="irrigation_schedule_id">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Crop:</label>
                                <p class="form-control-static" id="irrigation_crop_name"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Field:</label>
                                <p class="form-control-static" id="irrigation_field_name"></p>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Schedule:</label>
                                <p class="form-control-static" id="irrigation_schedule_desc"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Recommended Amount:</label>
                                <p class="form-control-static" id="irrigation_recommended_amount"></p>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="irrigation_date" class="form-label">Irrigation Date</label>
                            <input type="date" class="form-control" id="irrigation_date" name="irrigation_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="amount_used" class="form-label">Amount Used (Liters)</label>
                            <input type="number" step="0.1" class="form-control" id="amount_used" name="amount_used" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="next_irrigation_date" class="form-label">Next Irrigation Date</label>
                            <input type="date" class="form-control" id="next_irrigation_date" name="next_irrigation_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="irrigation_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="irrigation_notes" name="notes" rows="3" placeholder="Optional notes about this irrigation session"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Log Irrigation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Report Issue -->
<div class="modal fade" id="reportIssueModal" tabindex="-1" aria-labelledby="reportIssueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportIssueModalLabel"><i class="fas fa-bug"></i> Report Issue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="report_issue" value="1">
                <input type="hidden" name="crop_id" id="issue_crop_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Crop:</label>
                        <p class="form-control-static" id="issue_crop_name"></p>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="issue_type" class="form-label">Issue Type</label>
                            <select class="form-select" id="issue_type" name="issue_type" required>
                                <option value="">Select issue type</option>
                                <option value="pest">Pest</option>
                                <option value="disease">Disease</option>
                                <option value="nutrient">Nutrient Deficiency</option>
                                <option value="water">Water Stress</option>
                                <option value="weather">Weather Damage</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="severity" class="form-label">Severity</label>
                            <select class="form-select" id="severity" name="severity" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="affected_area" class="form-label">Affected Area</label>
                            <input type="text" class="form-control" id="affected_area" name="affected_area" placeholder="e.g., '50 sq meters', '10% of field'">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes_issue" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes_issue" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Report Issue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Update Growth Stage -->
<div class="modal fade" id="updateGrowthStageModal" tabindex="-1" aria-labelledby="updateGrowthStageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateGrowthStageModalLabel"><i class="fas fa-seedling"></i> Update Growth Stage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="update_growth_stage" value="1">
                <input type="hidden" name="crop_id" id="growth_stage_crop_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Crop:</label>
                        <p class="form-control-static" id="growth_stage_crop_name"></p>
                    </div>
                    <div class="mb-3">
                        <label for="growth_stage" class="form-label">Growth Stage</label>
                        <select class="form-select" id="growth_stage" name="growth_stage" required>
                            <option value="">Select growth stage</option>
                            <option value="seedling">Seedling</option>
                            <option value="vegetative">Vegetative</option>
                            <option value="flowering">Flowering</option>
                            <option value="fruiting">Fruiting</option>
                            <option value="mature">Mature</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="date_reached" class="form-label">Date Reached</label>
                        <input type="date" class="form-control" id="date_reached" name="date_reached" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="notes_growth" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes_growth" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Stage</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- All Irrigation Logs Modal - NEW -->
<div class="modal fade" id="allIrrigationLogsModal" tabindex="-1" aria-labelledby="allIrrigationLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allIrrigationLogsModalLabel"><i class="fas fa-list"></i> All Irrigation Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="allIrrigationLogsContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading irrigation logs...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- All Irrigation Schedules Modal - NEW -->
<div class="modal fade" id="allSchedulesModal" tabindex="-1" aria-labelledby="allSchedulesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allSchedulesModalLabel"><i class="fas fa-calendar-alt"></i> All Irrigation Schedules</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="allSchedulesContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading irrigation schedules...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Other existing modals... -->
<!-- Modal: View Crop Details -->
<div class="modal fade" id="viewCropDetailsModal" tabindex="-1" aria-labelledby="viewCropDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewCropDetailsModalLabel"><i class="fas fa-seedling"></i> Crop Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="cropDetailsContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading crop details...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: View Issue Details -->
<div class="modal fade" id="viewIssueDetailsModal" tabindex="-1" aria-labelledby="viewIssueDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewIssueDetailsModalLabel"><i class="fas fa-bug"></i> Issue Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="issueDetailsContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading issue details...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: All Issues -->
<div class="modal fade" id="allIssuesModal" tabindex="-1" aria-labelledby="allIssuesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allIssuesModalLabel"><i class="fas fa-list"></i> All Crop Issues</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="allIssuesContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading all issues...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Apply Treatment -->
<div class="modal fade" id="applyTreatmentModal" tabindex="-1" aria-labelledby="applyTreatmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="applyTreatmentModalLabel"><i class="fas fa-prescription-bottle"></i> Apply Treatment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="apply_treatment" value="1">
                <input type="hidden" name="crop_id" id="treatment_crop_id">
                <input type="hidden" name="issue_id" id="treatment_issue_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Issue Type:</label>
                        <p class="form-control-static" id="treatment_issue_type"></p>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="treatment_type" class="form-label">Treatment Type</label>
                            <input type="text" class="form-control" id="treatment_type" name="treatment_type" required>
                        </div>
                        <div class="col-md-6">
                            <label for="application_date" class="form-label">Application Date</label>
                            <input type="date" class="form-control" id="application_date" name="application_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="quantity_used" class="form-label">Quantity Used</label>
                            <input type="text" class="form-control" id="quantity_used" name="quantity_used" required>
                        </div>
                        <div class="col-md-6">
                            <label for="application_method" class="form-label">Application Method</label>
                            <select class="form-select" id="application_method" name="application_method" required>
                                <option value="">Select method</option>
                                <option value="spray">Spray</option>
                                <option value="dust">Dust</option>
                                <option value="drench">Drench</option>
                                <option value="injection">Injection</option>
                                <option value="broadcast">Broadcast</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes_treatment" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes_treatment" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Apply Treatment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Record Harvest -->
<div class="modal fade" id="recordHarvestModal" tabindex="-1" aria-labelledby="recordHarvestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="recordHarvestModalLabel"><i class="fas fa-tractor"></i> Record Harvest</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="record_harvest" value="1">
                <input type="hidden" name="crop_id" id="harvest_crop_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Crop:</label>
                        <p class="form-control-static" id="harvest_crop_name"></p>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="harvest_date" class="form-label">Harvest Date</label>
                            <input type="date" class="form-control" id="harvest_date" name="harvest_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="quantity_harvested" class="form-label">Quantity Harvested</label>
                            <input type="text" class="form-control" id="quantity_harvested" name="quantity_harvested" placeholder="e.g., 500 kg" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="quality_grade" class="form-label">Quality Grade</label>
                            <select class="form-select" id="quality_grade" name="quality_grade" required>
                                <option value="">Select quality</option>
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="harvest_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="harvest_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Harvest</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Record Environmental Data -->
<div class="modal fade" id="recordEnvironmentalModal" tabindex="-1" aria-labelledby="recordEnvironmentalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="recordEnvironmentalModalLabel"><i class="fas fa-cloud-sun"></i> Record Environmental Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="record_environmental_data" value="1">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="field_id" class="form-label">Field</label>
                            <select class="form-select" id="field_id" name="field_id" required>
                                <option value="">Select a field</option>
                                <?php foreach ($available_fields as $field): ?>
                                    <option value="<?php echo $field['id']; ?>">
                                        <?php echo htmlspecialchars($field['field_name'] . ' (' . $field['location'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="recorded_date" class="form-label">Date Recorded</label>
                            <input type="date" class="form-control" id="recorded_date" name="recorded_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="temperature" class="form-label">Temperature (°C)</label>
                            <input type="number" step="0.1" class="form-control" id="temperature" name="temperature" required>
                        </div>
                        <div class="col-md-4">
                            <label for="humidity" class="form-label">Humidity (%)</label>
                            <input type="number" step="0.1" class="form-control" id="humidity" name="humidity" required>
                        </div>
                        <div class="col-md-4">
                            <label for="soil_moisture" class="form-label">Soil Moisture (%)</label>
                            <input type="number" step="0.1" class="form-control" id="soil_moisture" name="soil_moisture" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="rainfall" class="form-label">Rainfall (mm)</label>
                            <input type="number" step="0.1" class="form-control" id="rainfall" name="rainfall" required>
                        </div>
                        <div class="col-md-6">
                            <label for="wind_speed" class="form-label">Wind Speed (km/h)</label>
                            <input type="number" step="0.1" class="form-control" id="wind_speed" name="wind_speed" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="notes_environmental" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes_environmental" name="notes" rows="3" placeholder="Optional notes about environmental conditions"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Data</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
// Wait for document to be ready
$(document).ready(function() {
    console.log('Page loaded with Bootstrap 4');
    
    // Test if Bootstrap 4 is loaded
    if (typeof $.fn.modal === 'undefined') {
        console.error('Bootstrap JavaScript is not loaded!');
        return;
    }
    
    console.log('Bootstrap 4 modals are ready');
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert-dismissible').alert('close');
    }, 5000);
});

// Function to search tables
function searchTable(tableId, inputId) {
    const input = document.getElementById(inputId);
    const filter = input.value.toUpperCase();
    const table = document.getElementById(tableId);
    const tr = table.getElementsByTagName("tr");
    
    for (let i = 1; i < tr.length; i++) {
        let found = false;
        const td = tr[i].getElementsByTagName("td");
        
        for (let j = 0; j < 3; j++) {
            if (td[j]) {
                const txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        tr[i].style.display = found ? "" : "none";
    }
}

// Bootstrap 4 modal functions - Fixed for Bootstrap 4
function setupGrowthStageUpdate(cropId, cropName, currentStage) {
    document.getElementById('growth_stage_crop_id').value = cropId;
    document.getElementById('growth_stage_crop_name').textContent = cropName;
    
    const growthStageSelect = document.getElementById('growth_stage');
    const currentStageValue = currentStage || '';
    
    // Reset select first
    growthStageSelect.selectedIndex = 0;
    
    // Find and select the current stage
    for (let i = 0; i < growthStageSelect.options.length; i++) {
        if (growthStageSelect.options[i].value === currentStageValue) {
            growthStageSelect.selectedIndex = i;
            break;
        }
    }
}

function setupIssueReport(cropId, cropName) {
    document.getElementById('issue_crop_id').value = cropId;
    document.getElementById('issue_crop_name').textContent = cropName;
    
    // Clear form
    document.getElementById('issue_type').value = '';
    document.getElementById('description').value = '';
    document.getElementById('severity').value = 'medium';
    document.getElementById('affected_area').value = '';
    document.getElementById('notes_issue').value = '';
}

function setupHarvest(cropId, cropName) {
    document.getElementById('harvest_crop_id').value = cropId;
    document.getElementById('harvest_crop_name').textContent = cropName;
    
    // Clear form
    document.getElementById('harvest_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('quantity_harvested').value = '';
    document.getElementById('quality_grade').value = '';
    document.getElementById('harvest_notes').value = '';
}

function setupTreatment(cropId, issueId, issueType) {
    document.getElementById('treatment_crop_id').value = cropId;
    document.getElementById('treatment_issue_id').value = issueId;
    document.getElementById('treatment_issue_type').textContent = issueType;
    
    // Suggest treatment type based on issue type
    const treatmentTypeInput = document.getElementById('treatment_type');
    switch(issueType) {
        case 'pest':
            treatmentTypeInput.value = 'Insecticide';
            break;
        case 'disease':
            treatmentTypeInput.value = 'Fungicide';
            break;
        case 'nutrient':
            treatmentTypeInput.value = 'Fertilizer';
            break;
        case 'water':
            treatmentTypeInput.value = 'Irrigation';
            break;
        default:
            treatmentTypeInput.value = '';
    }
    
    // Clear other fields
    document.getElementById('application_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('quantity_used').value = '';
    document.getElementById('application_method').value = '';
    document.getElementById('notes_treatment').value = '';
}

// Function to refresh the page after successful treatment application
$(document).ready(function() {
    // Check for success message and refresh active issues if treatment was applied
    <?php if(isset($_SESSION['success']) && strpos($_SESSION['success'], 'Treatment applied') !== false): ?>
        // Refresh the active issues section after treatment
        setTimeout(function() {
            location.reload();
        }, 2000);
    <?php endif; ?>
});

function setupIrrigationLog(scheduleId, cropName, fieldName, scheduleDesc, waterAmount) {
    document.getElementById('irrigation_schedule_id').value = scheduleId;
    document.getElementById('irrigation_crop_name').textContent = cropName;
    document.getElementById('irrigation_field_name').textContent = fieldName;
    document.getElementById('irrigation_schedule_desc').textContent = scheduleDesc;
    document.getElementById('irrigation_recommended_amount').textContent = waterAmount + ' L';
    
    // Set default amount used to the scheduled amount
    document.getElementById('amount_used').value = waterAmount;
    
    // Calculate next irrigation date based on schedule description
    const nextDate = new Date();
    const today = new Date();
    
    if (scheduleDesc.toLowerCase().includes('daily')) {
        nextDate.setDate(today.getDate() + 1);
    } else if (scheduleDesc.toLowerCase().includes('weekly')) {
        nextDate.setDate(today.getDate() + 7);
    } else if (scheduleDesc.toLowerCase().includes('monday') && scheduleDesc.toLowerCase().includes('thursday')) {
        // For "Every Monday and Thursday" type schedules
        const dayOfWeek = today.getDay(); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
        if (dayOfWeek <= 1) { // Sunday or Monday
            nextDate.setDate(today.getDate() + (4 - dayOfWeek)); // Next Thursday
        } else if (dayOfWeek <= 4) { // Tuesday to Thursday
            nextDate.setDate(today.getDate() + (8 - dayOfWeek)); // Next Monday
        } else { // Friday or Saturday
            nextDate.setDate(today.getDate() + (8 - dayOfWeek)); // Next Monday
        }
    } else {
        // Default to 3 days
        nextDate.setDate(today.getDate() + 3);
    }
    
    const formattedNextDate = nextDate.toISOString().split('T')[0];
    document.getElementById('next_irrigation_date').value = formattedNextDate;
}

// Bootstrap 4 modal opening functions - Fixed
function showAllIrrigationLogs() {
    $('#allIrrigationLogsModal').modal('show');
    loadAllIrrigationLogs();
}

function showAllSchedules() {
    $('#allSchedulesModal').modal('show');
    loadAllSchedules();
}

function viewCropDetails(cropId) {
    $('#viewCropDetailsModal').modal('show');
    loadCropDetails(cropId);
}

function viewIssueDetails(issueId) {
    $('#viewIssueDetailsModal').modal('show');
    loadIssueDetails(issueId);
}

function showAllIssues() {
    $('#allIssuesModal').modal('show');
    loadAllIssues();
}

function viewScheduleDetails(scheduleId) {
    // Simple alert for now - you can enhance this
    alert('Schedule details for ID: ' + scheduleId + '\nThis feature can be expanded to show more detailed information.');
}

// Load functions for modals
function loadAllIrrigationLogs() {
    const contentDiv = document.getElementById('allIrrigationLogsContent');
    
    contentDiv.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Loading irrigation logs...</p>
        </div>
    `;
    
    // Simulate loading all irrigation logs
    setTimeout(function() {
        const irrigationLogs = <?php echo json_encode($recent_irrigation_logs ?? []); ?>;
        
        if (irrigationLogs.length === 0) {
            contentDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-tint fa-3x text-info mb-3"></i>
                    <p>No irrigation logs found.</p>
                    <button class="btn btn-info mt-2" onclick="closeModalAndOpen('allIrrigationLogsModal', 'logIrrigationModal')">
                        Log First Irrigation
                    </button>
                </div>
            `;
        } else {
            let tableHTML = `
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Crop</th>
                                <th>Field</th>
                                <th>Date</th>
                                <th>Amount Used</th>
                                <th>Scheduled</th>
                                <th>Schedule</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            irrigationLogs.forEach(function(log) {
                tableHTML += `
                    <tr>
                        <td>${log.crop_name}</td>
                        <td>${log.field_name}</td>
                        <td>${new Date(log.irrigation_date).toLocaleDateString()}</td>
                        <td><span class="badge badge-info">${log.amount_used} L</span></td>
                        <td><span class="badge badge-secondary">${log.scheduled_amount} L</span></td>
                        <td>${log.schedule_description}</td>
                        <td>${log.notes || '-'}</td>
                    </tr>
                `;
            });
            
            tableHTML += `
                        </tbody>
                    </table>
                </div>
            `;
            
            contentDiv.innerHTML = tableHTML;
        }
    }, 500);
}

function loadAllSchedules() {
    const contentDiv = document.getElementById('allSchedulesContent');
    
    contentDiv.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Loading irrigation schedules...</p>
        </div>
    `;
    
    // Simulate loading all irrigation schedules
    setTimeout(function() {
        const irrigationSchedules = <?php echo json_encode($irrigation_schedules ?? []); ?>;
        
        if (irrigationSchedules.length === 0) {
            contentDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-calendar-times fa-3x text-warning mb-3"></i>
                    <p>No irrigation schedules found.</p>
                    <small class="text-muted">Contact admin to set up irrigation schedules.</small>
                </div>
            `;
        } else {
            let tableHTML = `
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Crop</th>
                                <th>Field</th>
                                <th>Schedule</th>
                                <th>Water Amount</th>
                                <th>Last Irrigation</th>
                                <th>Next Irrigation</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            irrigationSchedules.forEach(function(schedule) {
                let statusBadge = '';
                switch(schedule.status) {
                    case 'due':
                        statusBadge = '<span class="status-badge status-pending">Due Now</span>';
                        break;
                    case 'due_soon':
                        statusBadge = '<span class="status-badge status-in-progress">Due Soon</span>';
                        break;
                    default:
                        statusBadge = '<span class="status-badge status-active">Scheduled</span>';
                }
                
                tableHTML += `
                    <tr>
                        <td>${schedule.crop_name}</td>
                        <td>${schedule.field_name}</td>
                        <td>${schedule.schedule_description}</td>
                        <td>${schedule.water_amount} L</td>
                        <td>${schedule.last_irrigation_date ? new Date(schedule.last_irrigation_date).toLocaleDateString() : 'Not yet'}</td>
                        <td>${new Date(schedule.next_irrigation_date).toLocaleDateString()}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-info" onclick="closeModalAndOpen('allSchedulesModal', 'logIrrigationModal', function() { setupIrrigationLog(${schedule.id}, '${schedule.crop_name.replace(/'/g, "\\'")}', '${schedule.field_name.replace(/'/g, "\\'")}', '${schedule.schedule_description.replace(/'/g, "\\'")}', ${schedule.water_amount}); })">
                                <i class="fas fa-tint"></i> Log
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tableHTML += `
                        </tbody>
                    </table>
                </div>
            `;
            
            contentDiv.innerHTML = tableHTML;
        }
    }, 500);
}

function loadCropDetails(cropId) {
    const contentDiv = document.getElementById('cropDetailsContent');
    
    // Show loading state
    contentDiv.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Loading crop details...</p>
        </div>
    `;
    
    // Simulate loading crop details
    setTimeout(function() {
        // Find crop data from PHP array
        const crops = <?php echo json_encode($crops); ?>;
        const crop = crops.find(function(c) { return c.id == cropId; });
        
        if (crop) {
            contentDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Basic Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Crop Name:</strong></td><td>${crop.crop_name}</td></tr>
                            <tr><td><strong>Variety:</strong></td><td>${crop.variety || 'Not specified'}</td></tr>
                            <tr><td><strong>Field:</strong></td><td>${crop.field_name || 'Not assigned'}</td></tr>
                            <tr><td><strong>Location:</strong></td><td>${crop.location || 'Not specified'}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Dates & Timeline</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Planting Date:</strong></td><td>${crop.planting_date ? new Date(crop.planting_date).toLocaleDateString() : 'Not set'}</td></tr>
                            <tr><td><strong>Expected Harvest:</strong></td><td>${crop.expected_harvest_date ? new Date(crop.expected_harvest_date).toLocaleDateString() : 'Not set'}</td></tr>
                            <tr><td><strong>Last Activity:</strong></td><td>${crop.last_activity ? new Date(crop.last_activity).toLocaleDateString() : 'Not recorded'}</td></tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6>Current Status</h6>
                        <p><strong>Growth Stage:</strong> 
                            <span class="stage-badge stage-${crop.growth_stage || 'none'}">${crop.growth_stage || 'Not set'}</span>
                        </p>
                        <p><strong>Status:</strong> 
                            <span class="status-badge status-${crop.status || 'unknown'}">${crop.status || 'Unknown'}</span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Additional Information</h6>
                        <p><strong>Notes:</strong></p>
                        <p class="text-muted">${crop.notes || 'No notes available'}</p>
                    </div>
                </div>
            `;
        } else {
            contentDiv.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Crop details not found.
                </div>
            `;
        }
    }, 800);
}

function loadIssueDetails(issueId) {
    const contentDiv = document.getElementById('issueDetailsContent');
    
    // Show loading state
    contentDiv.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Loading issue details...</p>
        </div>
    `;
    
    // Simulate loading issue details
    setTimeout(function() {
        const activeIssues = <?php echo json_encode($active_issues); ?>;
        const issue = activeIssues.find(function(i) { return i.id == issueId; });
        
        if (issue) {
            const severityClass = issue.severity === 'high' ? 'danger' : 
                                issue.severity === 'medium' ? 'warning' : 'info';
            
            contentDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Issue Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Crop:</strong></td><td>${issue.crop_name}</td></tr>
                            <tr><td><strong>Field:</strong></td><td>${issue.field_name}</td></tr>
                            <tr><td><strong>Issue Type:</strong></td><td>
                                <span class="badge badge-${issue.issue_type === 'pest' ? 'danger' : 'warning'}">${issue.issue_type}</span>
                            </td></tr>
                            <tr><td><strong>Severity:</strong></td><td>
                                <span class="badge badge-${severityClass}">${issue.severity}</span>
                            </td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Timeline</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Identified:</strong></td><td>${new Date(issue.date_identified).toLocaleDateString()}</td></tr>
                            <tr><td><strong>Treatment Applied:</strong></td><td>${issue.treatment_applied == 1 ? 'Yes' : 'No'}</td></tr>
                            ${issue.treatment_date ? `<tr><td><strong>Treatment Date:</strong></td><td>${new Date(issue.treatment_date).toLocaleDateString()}</td></tr>` : ''}
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Description</h6>
                        <p>${issue.description}</p>
                        
                        ${issue.affected_area ? `
                            <h6>Affected Area</h6>
                            <p>${issue.affected_area}</p>
                        ` : ''}
                        
                        ${issue.notes ? `
                            <h6>Notes</h6>
                            <p class="text-muted">${issue.notes}</p>
                        ` : ''}
                    </div>
                </div>
            `;
        } else {
            contentDiv.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Issue details not found.
                </div>
            `;
        }
    }, 800);
}

function loadAllIssues() {
    const contentDiv = document.getElementById('allIssuesContent');
    
    contentDiv.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2">Loading all issues...</p>
        </div>
    `;
    
    // Simulate loading all issues
    setTimeout(function() {
        const activeIssues = <?php echo json_encode($active_issues); ?>;
        
        if (activeIssues.length === 0) {
            contentDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <p>No active issues found.</p>
                    <button class="btn btn-warning mt-2" onclick="closeModalAndOpen('allIssuesModal', 'reportIssueModal')">
                        Report New Issue
                    </button>
                </div>
            `;
        } else {
            let tableHTML = `
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Crop</th>
                                <th>Field</th>
                                <th>Issue Type</th>
                                <th>Description</th>
                                <th>Severity</th>
                                <th>Identified</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            activeIssues.forEach(function(issue) {
                const severityBadge = issue.severity === 'high' ? 'priority-high' : 
                                    issue.severity === 'medium' ? 'priority-medium' : 'priority-low';
                
                const issueTypeBadge = issue.issue_type === 'pest' ? 'badge-danger' : 
                                     issue.issue_type === 'disease' ? 'badge-warning' : 'badge-info';
                
                const statusBadge = issue.treatment_applied == 1 ? 'status-in-progress' : 'status-pending';
                
                tableHTML += `
                    <tr>
                        <td>${issue.crop_name}</td>
                        <td>${issue.field_name}</td>
                        <td><span class="badge ${issueTypeBadge}">${issue.issue_type}</span></td>
                        <td>${issue.description}</td>
                        <td><span class="priority-badge ${severityBadge}">${issue.severity}</span></td>
                        <td>${new Date(issue.date_identified).toLocaleDateString()}</td>
                        <td><span class="status-badge ${statusBadge}">${issue.treatment_applied ? 'Treated' : 'Pending'}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary mr-1" onclick="viewIssueDetails(${issue.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="closeModalAndOpen('allIssuesModal', 'applyTreatmentModal', function() { setupTreatment(${issue.crop_id}, ${issue.id}, '${issue.issue_type}'); })">
                                <i class="fas fa-prescription-bottle"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tableHTML += `
                        </tbody>
                    </table>
                </div>
            `;
            
            contentDiv.innerHTML = tableHTML;
        }
    }, 500);
}

// Utility function to close one modal and open another - Fixed for Bootstrap 4
function closeModalAndOpen(currentModalId, newModalId, callback) {
    callback = callback || null;
    
    // Close current modal using Bootstrap 4 syntax
    $('#' + currentModalId).modal('hide');
    
    // Wait for close animation to complete
    $('#' + currentModalId).on('hidden.bs.modal', function() {
        // Remove the event listener to prevent multiple bindings
        $(this).off('hidden.bs.modal');
        
        // Execute callback if provided
        if (callback && typeof callback === 'function') {
            callback();
        }
        
        // Open new modal
        $('#' + newModalId).modal('show');
    });
}

// Weather refresh function
function refreshWeather() {
    const button = document.querySelector('.refresh-weather');
    if (!button) return;
    
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    button.disabled = true;
    
    setTimeout(function() {
        const now = new Date();
        const options = { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            hour12: true
        };
        const formattedDate = now.toLocaleDateString('en-US', options);
        
        const lastUpdatedSpan = document.querySelector('.last-updated span');
        if (lastUpdatedSpan) {
            lastUpdatedSpan.textContent = 'Last Updated: ' + formattedDate;
        }
        
        button.innerHTML = originalContent;
        button.disabled = false;
        
        // Show success message using Bootstrap 4
        const alertHTML = `
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                Weather data refreshed successfully!
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
        const weatherContainer = document.querySelector('.weather-container');
        if (weatherContainer) {
            weatherContainer.insertAdjacentHTML('afterend', alertHTML);
        }
        
        setTimeout(function() {
            $('.alert-success').alert('close');
        }, 3000);
    }, 1500);
}

// Auto-refresh data every 5 minutes
setInterval(function() {
    // Uncomment if you want auto-refresh
    // location.reload();
}, 300000); // 5 minutes

// Function to show success message
function showSuccessMessage(message) {
    const alertHTML = `
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i>${message}
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `;
    
    const container = document.querySelector('.main-content');
    if (container) {
        container.insertAdjacentHTML('afterbegin', alertHTML);
        
        // Auto dismiss after 3 seconds
        setTimeout(function() {
            $('.alert-success').alert('close');
        }, 3000);
    }
}

// Function to show error message
function showErrorMessage(message) {
    const alertHTML = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i>${message}
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `;
    
    const container = document.querySelector('.main-content');
    if (container) {
        container.insertAdjacentHTML('afterbegin', alertHTML);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            $('.alert-danger').alert('close');
        }, 5000);
    }
}

// Console log for debugging
console.log('🌾 PureFarm Supervisor Crop Management System initialized successfully!');
</script>

<style>
/* Summary Cards */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.summary-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 15px;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 12px rgba(0,0,0,0.15);
}

.summary-icon {
    width: 45px;
    height: 45px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.summary-icon i {
    font-size: 20px;
    color: white;
}

.bg-blue { background: #3498db; }
.bg-orange { background: #f39c12; }
.bg-red { background: #e74c3c; }
.bg-green { background: #2ecc71; }

.summary-details h3 {
    font-size: 14px;
    margin: 0 0 5px 0;
    color: #555;
}

.summary-count {
    font-size: 24px;
    font-weight: bold;
    margin: 0;
    line-height: 1.2;
}

.summary-subtitle {
    font-size: 12px;
    color: #888;
    display: block;
    margin-top: 2px;
}

/* Content Card Styles */
.content-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 20px;
}

.content-card-header {
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.content-card-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.content-card-header h3 i {
    margin-right: 10px;
    color: #3498db;
}

.card-actions {
    display: flex;
    align-items: center;
}

.search-bar {
    position: relative;
    margin-right: 15px;
}

.search-bar input {
    padding: 8px 30px 8px 10px;
    border-radius: 4px;
    border: 1px solid #ddd;
    font-size: 14px;
    width: 200px;
}

.search-bar i {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #aaa;
}

/* Table Styles */
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background-color: #f8f9fa;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
    color: #444;
    border-bottom: 2px solid #ddd;
}

.data-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

.data-table tbody tr:hover {
    background-color: #f9f9f9;
}

/* Status and Priority Badges */
.stage-badge, .status-badge, .priority-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.stage-seedling { background-color: #e8f5e9; color: #2e7d32; }
.stage-vegetative { background-color: #e3f2fd; color: #1565c0; }
.stage-flowering { background-color: #fff3e0; color: #e65100; }
.stage-fruiting { background-color: #ffebee; color: #c62828; }
.stage-mature { background-color: #f3e5f5; color: #7b1fa2; }
.stage-none { background-color: #f5f5f5; color: #757575; }

.status-active { background-color: #e8f5e9; color: #2e7d32; }
.status-harvested { background-color: #e3f2fd; color: #1565c0; }
.status-failed { background-color: #ffebee; color: #c62828; }
.status-planned { background-color: #f5f5f5; color: #757575; }
.status-completed { background-color: #e8f5e9; color: #2e7d32; }
.status-in-progress { background-color: #fff3e0; color: #e65100; }
.status-pending { background-color: #f5f5f5; color: #757575; }

.priority-high { background-color: #ffebee; color: #c62828; }
.priority-medium { background-color: #fff3e0; color: #e65100; }
.priority-low { background-color: #e8f5e9; color: #2e7d32; }

/* Action Buttons */
.actions {
    white-space: nowrap;
}

.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    background-color: #f8f9fa;
    border: 1px solid #ddd;
    color: #555;
    margin-right: 5px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-icon:hover {
    background-color: #e9ecef;
    transform: translateY(-1px);
}

.btn-icon i {
    font-size: 14px;
}

.btn-icon-success {
    background-color: #e8f5e9;
    color: #2e7d32;
    border-color: #c8e6c9;
}

.btn-icon-success:hover {
    background-color: #c8e6c9;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #777;
}

.empty-state i {
    color: #ccc;
    margin-bottom: 10px;
}

/* Badge styling */
.badge {
    padding: 0.35em 0.65em;
    font-size: 0.75em;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
}

.bg-danger {
    background-color: #dc3545;
    color: white;
}

.bg-warning {
    background-color: #ffc107;
    color: #212529;
}

.bg-info {
    background-color: #0dcaf0;
    color: #212529;
}

.bg-success {
    background-color: #198754;
    color: white;
}

/* Button styling */
.btn {
    display: inline-block;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    text-align: center;
    text-decoration: none;
    vertical-align: middle;
    cursor: pointer;
    user-select: none;
    background-color: transparent;
    border: 1px solid transparent;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    border-radius: 0.25rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.btn-primary {
    color: #fff;
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.btn-primary:hover {
    color: #fff;
    background-color: #0b5ed7;
    border-color: #0a58ca;
}

.btn-secondary {
    color: #fff;
    background-color: #6c757d;
    border-color: #6c757d;
}

.btn-secondary:hover {
    color: #fff;
    background-color: #5c636a;
    border-color: #565e64;
}

.btn-success {
    color: #fff;
    background-color: #198754;
    border-color: #198754;
}

.btn-success:hover {
    color: #fff;
    background-color: #157347;
    border-color: #146c43;
}

.btn-info {
    color: #000;
    background-color: #0dcaf0;
    border-color: #0dcaf0;
}

.btn-info:hover {
    color: #000;
    background-color: #31d2f2;
    border-color: #25cff2;
}

.btn-warning {
    color: #000;
    background-color: #ffc107;
    border-color: #ffc107;
}

.btn-warning:hover {
    color: #000;
    background-color: #ffca2c;
    border-color: #ffc720;
}

.btn-outline-primary {
    color: #0d6efd;
    border-color: #0d6efd;
}

.btn-outline-primary:hover {
    color: #fff;
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.btn-close { ... }
.visually-hidden { ... }

/* NEW - Keep these Bootstrap 4 compatible styles: */
.close {
    padding: 0;
    background-color: transparent;
    border: 0;
    appearance: none;
}

.sr-only {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}

/* Update margin utilities */
.mr-2 { margin-right: 0.5rem !important; }
.ml-2 { margin-left: 0.5rem !important; }
.mt-2 { margin-top: 0.5rem !important; }
.mb-2 { margin-bottom: 0.5rem !important; }

/* Update badge styles for Bootstrap 4 */
.badge-danger {
    background-color: #dc3545;
    color: white;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.badge-info {
    background-color: #17a2b8;
    color: white;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-outline-success {
    color: #198754;
    border-color: #198754;
}

.btn-outline-success:hover {
    color: #fff;
    background-color: #198754;
    border-color: #198754;
}

.btn-outline-info {
    color: #0dcaf0;
    border-color: #0dcaf0;
}

.btn-outline-info:hover {
    color: #000;
    background-color: #0dcaf0;
    border-color: #0dcaf0;
}

/* Form styling */
.form-label {
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.form-control {
    display: block;
    width: 100%;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #ced4da;
    appearance: none;
    border-radius: 0.25rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus {
    color: #212529;
    background-color: #fff;
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.form-control.is-invalid {
    border-color: #dc3545;
    padding-right: calc(1.5em + 0.75rem);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 4.6 1.4 1.4m0-1.4-1.4 1.4'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.form-select {
    display: block;
    width: 100%;
    padding: 0.375rem 2.25rem 0.375rem 0.75rem;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px 12px;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    appearance: none;
}

.form-select:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Weather Styling */
.weather-container {
    margin-bottom: 20px;
}

.current-weather {
    background: linear-gradient(135deg, #3498db, #1e6091);
    color: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.weather-main {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.temperature {
    display: flex;
    align-items: baseline;
}

.temp-value {
    font-size: 3rem;
    font-weight: bold;
    line-height: 1;
}

.temp-unit {
    font-size: 1.5rem;
    margin-left: 5px;
}

.weather-icon {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.weather-icon i {
    font-size: 2.5rem;
    margin-bottom: 5px;
}

.condition {
    font-size: 1.2rem;
}

.weather-details {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 15px;
}

.detail-item {
    display: flex;
    align-items: center;
    flex: 1 1 40%;
}

.detail-item i {
    margin-right: 8px;
    width: 20px;
    text-align: center;
}

.last-updated {
    font-size: 0.9rem;
    opacity: 0.8;
    display: flex;
    align-items: center;
}

.last-updated i {
    margin-right: 5px;
}

/* Weather Alerts */
.weather-alerts {
    margin-bottom: 20px;
}

.weather-alerts .alert {
    display: flex;
    align-items: flex-start;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 10px;
}

.alert-icon {
    margin-right: 15px;
    font-size: 1.5rem;
    color: #856404;
}

.alert-content h4 {
    margin: 0 0 5px 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.alert-content p {
    margin: 0 0 5px 0;
}

.alert-date {
    font-size: 0.9rem;
    opacity: 0.8;
    display: block;
    margin-top: 5px;
}

/* Hourly Forecast */
.forecast-section {
    margin-bottom: 20px;
}

.forecast-section h4 {
    margin-bottom: 15px;
    font-size: 1.2rem;
    font-weight: 600;
}

.hourly-forecast {
    display: flex;
    overflow-x: auto;
    gap: 15px;
    padding-bottom: 10px;
}

.forecast-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 80px;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.forecast-time {
    font-size: 0.9rem;
    margin-bottom: 8px;
    font-weight: 500;
}

.forecast-icon {
    font-size: 1.5rem;
    margin-bottom: 8px;
    color: #3498db;
}

.forecast-temp {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 5px;
}

.forecast-condition {
    font-size: 0.8rem;
    color: #777;
    text-align: center;
}

/* Field Conditions */
.field-conditions-section {
    margin-bottom: 20px;
}

.field-conditions-section h4 {
    margin-bottom: 15px;
    font-size: 1.2rem;
    font-weight: 600;
}

.field-conditions-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.field-condition-card {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    text-align: center;
}

.field-condition-card h5 {
    margin: 0 0 10px 0;
    font-size: 1rem;
    font-weight: 600;
}

.field-condition-icon {
    font-size: 1.8rem;
    margin-bottom: 10px;
    color: #3498db;
}

.field-condition-temp {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 10px;
}

.field-condition-details {
    display: flex;
    justify-content: space-around;
    font-size: 0.9rem;
    color: #666;
}

.field-condition-details div {
    display: flex;
    align-items: center;
}

.field-condition-details i {
    margin-right: 5px;
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #eee;
}

.page-header h2 {
    margin: 0;
    color: #333;
    display: flex;
    align-items: center;
}

.page-header h2 i {
    margin-right: 10px;
    color: #3498db;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

.action-buttons .btn {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Spinner */
.spinner-border {
    display: inline-block;
    width: 2rem;
    height: 2rem;
    vertical-align: text-bottom;
    border: 0.25em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border 0.75s linear infinite;
}

@keyframes spinner-border {
    to { transform: rotate(360deg); }
}

/* Alert styling */
.alert {
    position: relative;
    padding: 1rem 1rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
}

.alert-success {
    color: #0f5132;
    background-color: #d1e7dd;
    border-color: #badbcc;
}

.alert-danger {
    color: #842029;
    background-color: #f8d7da;
    border-color: #f5c2c7;
}

.alert-warning {
    color: #664d03;
    background-color: #fff3cd;
    border-color: #ffecb5;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .field-conditions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .action-buttons {
        flex-wrap: wrap;
    }
}

@media (max-width: 768px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .card-actions {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .search-bar {
        margin-right: 0;
        margin-bottom: 10px;
        width: 100%;
    }
    
    .search-bar input {
        width: 100%;
    }
    
    .field-conditions-grid {
        grid-template-columns: 1fr;
    }
    
    .weather-details {
        flex-direction: column;
        gap: 10px;
    }
    
    .detail-item {
        flex: 1 1 100%;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .action-buttons {
        width: 100%;
        justify-content: flex-start;
    }
}

/* Utility classes */
.mt-2 { margin-top: 0.5rem !important; }
.mt-3 { margin-top: 1rem !important; }
.mt-4 { margin-top: 1.5rem !important; }
.mb-3 { margin-bottom: 1rem !important; }
.me-2 { margin-right: 0.5rem !important; }
.text-center { text-align: center !important; }
.w-100 { width: 100% !important; }
.visually-hidden { position: absolute !important; width: 1px !important; height: 1px !important; padding: 0 !important; margin: -1px !important; overflow: hidden !important; clip: rect(0, 0, 0, 0) !important; white-space: nowrap !important; border: 0 !important; }
</style>

<?php include 'includes/footer.php'; ?>