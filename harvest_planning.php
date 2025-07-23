<?php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/purefarm_fixes.php';
require_once 'includes/db.php';

// Define $crop_id variable before using it
// Get crop_id from URL parameter or set a default value
$crop_id = isset($_GET['crop_id']) ? intval($_GET['crop_id']) : 0;


// Debug the financial data loading section
try {
    // Find where financial data is being loaded and add proper error handling
    // Example:
    $stmt = $pdo->prepare("
        SELECT c.crop_name, 
               COALESCE(SUM(cr.amount), 0) as revenue, 
               COALESCE(SUM(ce.amount), 0) as expenses
        FROM crops c
        LEFT JOIN crop_revenue cr ON c.id = cr.crop_id
        LEFT JOIN crop_expenses ce ON c.id = ce.crop_id
        WHERE c.id = :crop_id
        GROUP BY c.crop_name
    ");
    
    // Use proper error handling
    $stmt->execute(['crop_id' => $crop_id]);
    $financial_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no data found, initialize with zeros
    if (!$financial_data) {
        $financial_data = [
            'revenue' => 0,
            'expenses' => 0
        ];
    }
    
    // Log for debugging
    error_log("Financial data for crop ID $crop_id: " . json_encode($financial_data));
    
} catch (PDOException $e) {
    // Log the error
    error_log("Error loading financial data for harvest planning: " . $e->getMessage());
    
    // Initialize with default values instead of showing error
    $financial_data = [
        'revenue' => 0,
        'expenses' => 0
    ];
    
    // Still display the error for admin
    if (auth()->isAdmin()) {
        echo '<div class="alert alert-danger">Error loading financial data: ' . $e->getMessage() . '</div>';
    } else {
        echo '<div class="alert alert-danger">Error loading financial data. Please try again.</div>';
    }
}

// Helper function to get role names from role IDs
function getRoleName($roleId) {
    switch($roleId) {
        case 1: return 'Admin';
        case 2: return 'Field Manager';
        case 3: return 'Equipment Operator';
        case 4: return 'Harvest Crew';
        case 5: return 'Support Staff';
        default: return 'Unknown Role';
    }
}

// Initialize variables
$upcoming_harvests = [];
$available_equipment = [];
$assigned_staff = [];
$field_conditions = [];
$weather_forecast = [];
$alerts = [];

// Process filter parameters
$days_filter = isset($_GET['days']) ? intval($_GET['days']) : 30;
$crop_type_filter = isset($_GET['crop_type']) ? $_GET['crop_type'] : '';
$field_filter = isset($_GET['field_id']) ? $_GET['field_id'] : '';

// Get current date for calculations
$current_date = date('Y-m-d');

try {
    // Fetch upcoming harvests with filters
    $query = "
        SELECT 
            c.id,
            c.crop_name,
            c.variety,
            c.growth_stage,
            c.expected_harvest_date,
            c.planting_date,
            c.status,
            f.id AS field_id, 
            f.field_name,
            f.area AS field_area,
            f.location,
            DATEDIFF(c.expected_harvest_date, CURRENT_DATE) AS days_until_harvest
        FROM 
            crops c
        JOIN 
            fields f ON c.field_id = f.id
        WHERE 
            c.status = 'active'
            AND c.expected_harvest_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL ? DAY)
    ";
    
    $params = [$days_filter];
    
    // Apply additional filters
    if ($crop_type_filter) {
        $query .= " AND c.crop_name = ?";
        $params[] = $crop_type_filter;
    }
    
    if ($field_filter) {
        $query .= " AND f.id = ?";
        $params[] = $field_filter;
    }
    
    $query .= " ORDER BY c.expected_harvest_date ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $upcoming_harvests = $stmt->fetchAll();
    
    // Count total upcoming harvests for summary
    $total_upcoming = count($upcoming_harvests);
    
    // Calculate total harvest area
    $total_harvest_area = 0;
    foreach ($upcoming_harvests as $harvest) {
        $total_harvest_area += $harvest['field_area'];
    }
    
    // Get all crop types for filter
    $crop_types_stmt = $pdo->query("SELECT DISTINCT crop_name FROM crops WHERE status = 'active' ORDER BY crop_name");
    $crop_types = $crop_types_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all fields for filter
    $fields_stmt = $pdo->query("SELECT id, field_name FROM fields ORDER BY field_name");
    $fields = $fields_stmt->fetchAll();
    
    // Check if equipment table exists, if not create it
    $check_equipment = $pdo->query("SHOW TABLES LIKE 'equipment'");
    if ($check_equipment->rowCount() == 0) {
        // Create sample equipment data
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `equipment` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                `type` varchar(50) NOT NULL,
                `status` enum('available','in-use','maintenance') NOT NULL DEFAULT 'available',
                `next_available_date` date DEFAULT NULL,
                `notes` text,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        // Insert sample equipment
        $sample_equipment = [
            ['Harvester A', 'harvester', 'available', NULL],
            ['Harvester B', 'harvester', 'maintenance', date('Y-m-d', strtotime('+5 days'))],
            ['Tractor 1', 'tractor', 'available', NULL],
            ['Tractor 2', 'tractor', 'in-use', date('Y-m-d', strtotime('+2 days'))],
            ['Transport Truck', 'transport', 'available', NULL]
        ];
        
        $equipment_insert = $pdo->prepare("
            INSERT INTO `equipment` (name, type, status, next_available_date) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($sample_equipment as $equip) {
            $equipment_insert->execute($equip);
        }
    }
    
    // Check if staff table exists, if not create it
    $check_staff = $pdo->query("SHOW TABLES LIKE 'staff'");
    if ($check_staff->rowCount() == 0) {
        // Create sample staff data
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `staff` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `staff_id` varchar(20) NOT NULL,
                `first_name` varchar(50) NOT NULL,
                `last_name` varchar(50) NOT NULL,
                `email` varchar(100) NOT NULL,
                `phone` varchar(15) DEFAULT NULL,
                `address` text,
                `role_id` int(11) NOT NULL,
                `hire_date` date DEFAULT NULL,
                `emergency_contact` varchar(100) DEFAULT NULL,
                `notes` text,
                `profile_image` varchar(255) DEFAULT NULL,
                `status` enum('active','assigned','off') NOT NULL DEFAULT 'active',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
    
    // Check if harvest_assignments table exists, if not create it
    $check_assignments = $pdo->query("SHOW TABLES LIKE 'harvest_assignments'");
    if ($check_assignments->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `harvest_assignments` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `crop_id` int(11) NOT NULL,
                `equipment_id` int(11) DEFAULT NULL,
                `staff_id` int(11) DEFAULT NULL,
                `planned_date` date NOT NULL,
                `status` enum('scheduled','in-progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
                `notes` text,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `crop_id` (`crop_id`),
                KEY `equipment_id` (`equipment_id`),
                KEY `staff_id` (`staff_id`),
                CONSTRAINT `harvest_assignments_ibfk_1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE,
                CONSTRAINT `harvest_assignments_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
                CONSTRAINT `harvest_assignments_ibfk_3` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
    
    // Get available equipment
    $equipment_stmt = $pdo->query("
        SELECT * FROM equipment 
        WHERE status = 'available' 
        OR (status != 'available' AND next_available_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY))
        ORDER BY status, next_available_date
    ");
    $available_equipment = $equipment_stmt->fetchAll();
    
    // Get staff
    $staff_stmt = $pdo->query("SELECT * FROM staff ORDER BY status, role_id");
    $assigned_staff = $staff_stmt->fetchAll();
    
    // Get existing assignments
    $assignments_stmt = $pdo->prepare("
        SELECT 
            ha.*, 
            c.crop_name, 
            c.expected_harvest_date,
            f.field_name,
            e.name as equipment_name,
            CONCAT(s.first_name, ' ', s.last_name) as staff_name
        FROM 
            harvest_assignments ha
        JOIN 
            crops c ON ha.crop_id = c.id
        JOIN 
            fields f ON c.field_id = f.id
        LEFT JOIN 
            equipment e ON ha.equipment_id = e.id
        LEFT JOIN 
            staff s ON ha.staff_id = s.id
        WHERE 
            ha.planned_date >= ?
        ORDER BY 
            ha.planned_date ASC
    ");

    $assignments_stmt->execute([$current_date]);
    $harvest_assignments = $assignments_stmt->fetchAll();
    
    // Create weather forecast sample data (normally would come from an API)
    $weather_forecast = [
        ['date' => date('Y-m-d', strtotime('+1 day')), 'condition' => 'Sunny', 'temperature' => '75°F', 'precipitation' => '0%', 'wind' => '5 mph'],
        ['date' => date('Y-m-d', strtotime('+2 day')), 'condition' => 'Partly Cloudy', 'temperature' => '72°F', 'precipitation' => '10%', 'wind' => '8 mph'],
        ['date' => date('Y-m-d', strtotime('+3 day')), 'condition' => 'Cloudy', 'temperature' => '68°F', 'precipitation' => '20%', 'wind' => '10 mph'],
        ['date' => date('Y-m-d', strtotime('+4 day')), 'condition' => 'Rain Showers', 'temperature' => '65°F', 'precipitation' => '60%', 'wind' => '15 mph'],
        ['date' => date('Y-m-d', strtotime('+5 day')), 'condition' => 'Thunderstorms', 'temperature' => '62°F', 'precipitation' => '80%', 'wind' => '20 mph'],
        ['date' => date('Y-m-d', strtotime('+6 day')), 'condition' => 'Partly Cloudy', 'temperature' => '70°F', 'precipitation' => '30%', 'wind' => '12 mph'],
        ['date' => date('Y-m-d', strtotime('+7 day')), 'condition' => 'Sunny', 'temperature' => '76°F', 'precipitation' => '5%', 'wind' => '7 mph']
    ];
    
    // Generate alerts based on data
    // Equipment shortage
    if (count($available_equipment) < 2) {
        $alerts[] = [
            'type' => 'warning',
            'message' => 'Limited harvest equipment available. Consider scheduling maintenance after peak harvest times.'
        ];
    }
    
    // Weather warnings
    foreach ($weather_forecast as $forecast) {
        if ($forecast['precipitation'] > '50%') {
            $alerts[] = [
                'type' => 'danger',
                'message' => 'Adverse weather expected on ' . date('M d', strtotime($forecast['date'])) . ': ' . $forecast['condition'] . ' with ' . $forecast['precipitation'] . ' chance of precipitation.'
            ];
            break; // Only show one weather alert
        }
    }
    
    // Staffing alerts
    $available_staff_count = 0;
    foreach ($assigned_staff as $staff) {
        if ($staff['status'] == 'active') {
            $available_staff_count++;
        }
    }
    
    if ($available_staff_count < 3 && count($upcoming_harvests) > 2) {
        $alerts[] = [
            'type' => 'warning',
            'message' => 'Limited harvest staff available for upcoming harvests. Consider reassigning or hiring temporary staff.'
        ];
    }
    
    // First time setup alert
    if (empty($harvest_assignments)) {
        $alerts[] = [
            'type' => 'info',
            'message' => 'No harvest assignments found. Use the form below to schedule your first harvest assignment.'
        ];
    }

} catch(PDOException $e) {
    // Log error and set error message
    error_log("Error in harvest planning: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
    $alerts[] = [
        'type' => 'danger',
        'message' => $error_message
    ];
}

// Check if form was submitted to create or update an assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'create_assignment') {
            // Validate inputs
            $crop_id = isset($_POST['crop_id']) ? intval($_POST['crop_id']) : 0;
            $equipment_id = !empty($_POST['equipment_id']) ? intval($_POST['equipment_id']) : null;
            $staff_id = !empty($_POST['staff_id']) ? intval($_POST['staff_id']) : null;
            $planned_date = !empty($_POST['planned_date']) ? $_POST['planned_date'] : null;
            $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
            
            if (!$crop_id || !$planned_date) {
                throw new Exception("Missing required fields");
            }
            
            // Insert the assignment
            $insert_stmt = $pdo->prepare("
                INSERT INTO harvest_assignments 
                (crop_id, equipment_id, staff_id, planned_date, status, notes) 
                VALUES (?, ?, ?, ?, 'scheduled', ?)
            ");
            
            $insert_stmt->execute([$crop_id, $equipment_id, $staff_id, $planned_date, $notes]);
            
            // Update equipment status if assigned
            if ($equipment_id) {
                $pdo->prepare("UPDATE equipment SET status = 'in-use', next_available_date = ? WHERE id = ?")
                    ->execute([$planned_date, $equipment_id]);
            }
            
            // Update staff status if assigned
            if ($staff_id) {
                $pdo->prepare("UPDATE staff SET status = 'assigned' WHERE id = ?")
                    ->execute([$staff_id]);
            }
            
            $_SESSION['success_message'] = "Harvest assignment created successfully.";
        } elseif ($_POST['action'] === 'update_assignment') {
            // Handle update logic
            $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
            $status = isset($_POST['status']) ? $_POST['status'] : '';
            $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
            
            if (!$assignment_id || !$status) {
                throw new Exception("Missing required fields for update");
            }
            
            $update_stmt = $pdo->prepare("
                UPDATE harvest_assignments 
                SET status = ?, notes = ? 
                WHERE id = ?
            ");
            
            $update_stmt->execute([$status, $notes, $assignment_id]);
            
            $_SESSION['success_message'] = "Harvest assignment updated successfully.";
        } elseif ($_POST['action'] === 'delete_assignment') {
            // Handle delete logic
            $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;
            
            if (!$assignment_id) {
                throw new Exception("Missing assignment ID for deletion");
            }
            
            // First get the equipment and staff to update their status
            $get_assignment = $pdo->prepare("
                SELECT equipment_id, staff_id FROM harvest_assignments WHERE id = ?
            ");
            $get_assignment->execute([$assignment_id]);
            $assignment_data = $get_assignment->fetch();
            
            // Delete the assignment
            $delete_stmt = $pdo->prepare("DELETE FROM harvest_assignments WHERE id = ?");
            $delete_stmt->execute([$assignment_id]);
            
            // Update equipment status
            if ($assignment_data['equipment_id']) {
                $pdo->prepare("UPDATE equipment SET status = 'available', next_available_date = NULL WHERE id = ?")
                    ->execute([$assignment_data['equipment_id']]);
            }
            
            // Update staff status
            if ($assignment_data['staff_id']) {
                $pdo->prepare("UPDATE staff SET status = 'active' WHERE id = ?")
                    ->execute([$assignment_data['staff_id']]);
            }
            
            $_SESSION['success_message'] = "Harvest assignment deleted successfully.";
        }
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $alerts[] = [
            'type' => 'danger',
            'message' => "Error: " . $error_message
        ];
    }
}

$pageTitle = 'Harvest Planning';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-calendar-alt"></i> Harvest Planning</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" data-toggle="modal" data-target="#newAssignmentModal">
                <i class="fas fa-plus"></i> New Harvest Assignment
            </button>
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Schedule
            </button>
            <button class="btn btn-secondary" onclick="location.href='crop_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Crop Management
            </button>
        </div>
    </div>

    <!-- Notifications with close button -->
    <div id="notifications-container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close-alert">&times;</button>
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close-alert">&times;</button>
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php foreach ($alerts as $alert): ?>
            <div class="alert alert-<?php echo $alert['type']; ?> alert-dismissible">
                <button type="button" class="close-alert">&times;</button>
                <i class="fas fa-<?php echo $alert['type'] === 'danger' ? 'exclamation-circle' : ($alert['type'] === 'warning' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
                <?php echo $alert['message']; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon bg-green">
                <i class="fas fa-tractor"></i>
            </div>
            <div class="summary-details">
                <h3>Upcoming Harvests</h3>
                <p class="summary-count"><?php echo $total_upcoming; ?></p>
                <span class="summary-subtitle">Next <?php echo $days_filter; ?> days</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-blue">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="summary-details">
                <h3>Total Harvest Area</h3>
                <p class="summary-count"><?php echo number_format($total_harvest_area, 1); ?></p>
                <span class="summary-subtitle">acres</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-orange">
                <i class="fas fa-tools"></i>
            </div>
            <div class="summary-details">
                <h3>Available Equipment</h3>
                <p class="summary-count"><?php 
                    $count = 0;
                    foreach ($available_equipment as $equip) {
                        if ($equip['status'] === 'available') $count++;
                    }
                    echo $count;
                ?></p>
                <span class="summary-subtitle">ready for use</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-purple">
                <i class="fas fa-users"></i>
            </div>
            <div class="summary-details">
                <h3>Available Staff</h3>
                <p class="summary-count"><?php 
                    $count = 0;
                    foreach ($assigned_staff as $staff) {
                        if ($staff['status'] === 'active') $count++;
                    }
                    echo $count;
                ?></p>
                <span class="summary-subtitle">for harvest duties</span>
            </div>
        </div>
    </div>

    <!-- Filter Options (in one line) -->
    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-filter"></i> Filter Options</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="filter-form">
                <div class="filter-row-inline">
                    <div class="filter-group">
                        <label for="days">Time Frame:</label>
                        <select id="days" name="days" class="form-control">
                            <option value="7" <?php echo ($days_filter == 7) ? 'selected' : ''; ?>>Next 7 Days</option>
                            <option value="14" <?php echo ($days_filter == 14) ? 'selected' : ''; ?>>Next 14 Days</option>
                            <option value="30" <?php echo ($days_filter == 30) ? 'selected' : ''; ?>>Next 30 Days</option>
                            <option value="60" <?php echo ($days_filter == 60) ? 'selected' : ''; ?>>Next 60 Days</option>
                            <option value="90" <?php echo ($days_filter == 90) ? 'selected' : ''; ?>>Next 90 Days</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="crop_type">Crop Type:</label>
                        <select id="crop_type" name="crop_type" class="form-control">
                            <option value="">All Crop Types</option>
                            <?php foreach ($crop_types as $crop): ?>
                                <option value="<?php echo htmlspecialchars($crop); ?>" <?php echo ($crop == $crop_type_filter) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($crop); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="field_id">Field:</label>
                        <select id="field_id" name="field_id" class="form-control">
                            <option value="">All Fields</option>
                            <?php foreach ($fields as $field): ?>
                                <option value="<?php echo $field['id']; ?>" <?php echo ($field['id'] == $field_filter) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($field['field_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group filter-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply
                        </button>
                        <a href="harvest_planning.php" class="btn btn-outline">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Upcoming Harvests -->
    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-calendar-plus"></i> Upcoming Harvests</h3>
            <div class="card-actions">
                <div class="search-bar">
                    <input type="text" id="harvestSearch" onkeyup="searchTable('harvestTable', 'harvestSearch')" placeholder="Search crops...">
                    <i class="fas fa-search"></i>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table" id="harvestTable">
                <thead>
                    <tr>
                        <th>Crop</th>
                        <th>Variety</th>
                        <th>Field</th>
                        <th>Days Until Harvest</th>
                        <th>Expected Harvest Date</th>
                        <th>Growth Stage</th>
                        <th>Area (acres)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcoming_harvests)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No upcoming harvests found for the selected filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($upcoming_harvests as $harvest): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($harvest['crop_name']); ?></td>
                                <td><?php echo htmlspecialchars($harvest['variety']); ?></td>
                                <td><?php echo htmlspecialchars($harvest['field_name'] . ' (' . $harvest['location'] . ')'); ?></td>
                                <td>
                                    <?php 
                                        $days = $harvest['days_until_harvest'];
                                        $class = '';
                                        if ($days <= 7) {
                                            $class = 'text-danger font-weight-bold';
                                        } elseif ($days <= 14) {
                                            $class = 'text-warning';
                                        }
                                        echo '<span class="' . $class . '">' . $days . ' days</span>';
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($harvest['expected_harvest_date'])); ?></td>
                                <td><?php echo htmlspecialchars($harvest['growth_stage']); ?></td>
                                <td><?php echo number_format($harvest['field_area'], 1); ?></td>
                                <td class="actions">
                                    <button class="btn-icon btn-icon-primary" title="Schedule Harvest" 
                                            onclick="prepareAssignment(<?php echo $harvest['id']; ?>, '<?php echo htmlspecialchars($harvest['crop_name'] . ' - ' . $harvest['variety']); ?>', '<?php echo $harvest['expected_harvest_date']; ?>')">
                                        <i class="fas fa-calendar-plus"></i>
                                    </button>
                                    <a href="crop_details.php?id=<?php echo $harvest['id']; ?>" class="btn-icon" title="View Crop Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="record_harvest.php?id=<?php echo $harvest['id']; ?>" class="btn-icon btn-icon-success" title="Record Actual Harvest">
                                        <i class="fas fa-tractor"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Current Harvest Schedule (Colorful Calendar) -->
    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-calendar-check"></i> Harvest Schedule</h3>
        </div>
        <div class="card-body">
            <div id="harvestCalendar"></div>
        </div>
    </div>

    <!-- Scheduled Assignments List -->
    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-list-alt"></i> Scheduled Assignments</h3>
        </div>
        <div class="table-responsive">
            <table class="data-table" id="assignmentTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Crop</th>
                        <th>Field</th>
                        <th>Equipment</th>
                        <th>Staff</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($harvest_assignments)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No scheduled assignments yet. Create your first assignment using the button above.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($harvest_assignments as $assignment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($assignment['planned_date'])); ?></td>
                                <td><?php echo htmlspecialchars($assignment['crop_name']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['field_name']); ?></td>
                                <td><?php echo $assignment['equipment_name'] ? htmlspecialchars($assignment['equipment_name']) : 'Not assigned'; ?></td>
                                <td><?php echo $assignment['staff_name'] ? htmlspecialchars($assignment['staff_name']) : 'Not assigned'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($assignment['status']); ?>">
                                        <?php echo ucfirst($assignment['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($assignment['notes']); ?></td>
                                <td class="actions">
                                    <button class="btn-icon" title="Edit Assignment" onclick="editAssignment(<?php echo $assignment['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon btn-icon-success" title="Mark Complete" onclick="updateAssignmentStatus(<?php echo $assignment['id']; ?>, 'completed')" <?php echo ($assignment['status'] == 'completed' ? 'disabled' : ''); ?>>
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn-icon btn-icon-danger" title="Delete Assignment" onclick="deleteAssignment(<?php echo $assignment['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
        
    <!-- Resource Status (Equipment and Staff in the same line) -->
    <div class="grid-container two-columns">
        <!-- Equipment Status -->
        <div class="content-card grid-item">
            <div class="content-card-header">
                <h3><i class="fas fa-tools"></i> Equipment Status</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Equipment</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_equipment as $equipment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($equipment['name']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($equipment['type'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $equipment['status']; ?>">
                                            <?php echo ucfirst($equipment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                            if ($equipment['status'] == 'available') {
                                                echo 'Now';
                                            } elseif ($equipment['next_available_date']) {
                                                echo date('M d, Y', strtotime($equipment['next_available_date']));
                                            } else {
                                                echo 'Unknown';
                                            }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Staff Availability -->
        <div class="content-card grid-item">
            <div class="content-card-header">
                <h3><i class="fas fa-users"></i> Staff Availability</h3>
            </div>
            <div class="card-body">
                <div class="staff-availability-grid">
                    <?php foreach ($assigned_staff as $staff): ?>
                        <div class="staff-card <?php echo $staff['status'] == 'active' ? 'staff-available' : ($staff['status'] == 'assigned' ? 'staff-assigned' : 'staff-off'); ?>">
                            <div class="staff-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="staff-info">
                                <h4><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h4>
                                <p class="staff-role"><?php echo htmlspecialchars(getRoleName($staff['role_id'])); ?></p>
                                <p class="staff-status">
                                    Status: <span class="status-badge status-<?php echo $staff['status']; ?>"><?php echo ucfirst($staff['status']); ?></span>
                                </p>
                                <?php if (!empty($staff['skills'])): ?>
                                    <p class="staff-skills">
                                        Skills: 
                                        <?php 
                                            $skills = explode(',', $staff['skills']);
                                            foreach ($skills as $index => $skill) {
                                                echo '<span class="skill-badge">' . trim(htmlspecialchars($skill)) . '</span>';
                                            }
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Assignment Modal -->
<div class="modal fade" id="newAssignmentModal" tabindex="-1" role="dialog" aria-labelledby="newAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newAssignmentModalLabel">Schedule New Harvest Assignment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_assignment">
                    
                    <div class="form-group">
                        <label for="crop_id">Select Crop:</label>
                        <select id="crop_id" name="crop_id" class="form-control" required>
                            <option value="">-- Select Crop --</option>
                            <?php foreach ($upcoming_harvests as $harvest): ?>
                                <option value="<?php echo $harvest['id']; ?>" data-harvest-date="<?php echo $harvest['expected_harvest_date']; ?>">
                                    <?php echo htmlspecialchars($harvest['crop_name'] . ' - ' . $harvest['variety'] . ' (' . $harvest['field_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="planned_date">Planned Harvest Date:</label>
                        <input type="date" id="planned_date" name="planned_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="equipment_id">Assign Equipment:</label>
                        <select id="equipment_id" name="equipment_id" class="form-control">
                            <option value="">-- No Equipment --</option>
                            <?php foreach ($available_equipment as $equipment): ?>
                                <option value="<?php echo $equipment['id']; ?>" <?php echo ($equipment['status'] != 'available') ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($equipment['name'] . ' (' . ucfirst($equipment['type']) . ')'); ?>
                                    <?php echo ($equipment['status'] != 'available') ? ' - ' . ucfirst($equipment['status']) : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="staff_id">Assign Primary Staff:</label>
                        <select id="staff_id" name="staff_id" class="form-control">
                            <option value="">-- No Staff Assigned --</option>
                            <?php foreach ($assigned_staff as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>" <?php echo ($staff['status'] != 'active') ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name'] . ' (' . getRoleName($staff['role_id']) . ')'); ?>
                                    <?php echo ($staff['status'] != 'active') ? ' - ' . ucfirst($staff['status']) : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Add any special instructions or notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Harvest</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Assignment Modal -->
<div class="modal fade" id="editAssignmentModal" tabindex="-1" role="dialog" aria-labelledby="editAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAssignmentModalLabel">Edit Harvest Assignment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="" id="editAssignmentForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_assignment">
                    <input type="hidden" name="assignment_id" id="edit_assignment_id">
                    
                    <div class="form-group">
                        <label for="edit_status">Status:</label>
                        <select id="edit_status" name="status" class="form-control" required>
                            <option value="scheduled">Scheduled</option>
                            <option value="in-progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_notes">Notes:</label>
                        <textarea id="edit_notes" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteAssignmentModal" tabindex="-1" role="dialog" aria-labelledby="deleteAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteAssignmentModalLabel">Confirm Deletion</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this harvest assignment? This action cannot be undone.</p>
            </div>
            <form method="POST" action="" id="deleteAssignmentForm">
                <input type="hidden" name="action" value="delete_assignment">
                <input type="hidden" name="assignment_id" id="delete_assignment_id">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Close alert functionality
    document.querySelectorAll('.close-alert').forEach(function(btn) {
        btn.addEventListener('click', function() {
            this.parentElement.style.display = 'none';
        });
    });

    // Initialize FullCalendar with more colorful styles
    var calendarEl = document.getElementById('harvestCalendar');
    
    if (calendarEl) {
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listWeek'
            },
            dayMaxEvents: true, // allow "more" link when too many events
            weekNumbers: true,
            navLinks: true, // can click day/week names to navigate views
            businessHours: true,
            events: [
                <?php foreach ($harvest_assignments as $assignment): ?>
                {
                    title: '<?php echo addslashes($assignment['crop_name']); ?>',
                    start: '<?php echo $assignment['planned_date']; ?>',
                    url: '#assignment-<?php echo $assignment['id']; ?>',
                    backgroundColor: '<?php 
                        switch ($assignment['status']) {
                            case 'scheduled': echo '#3498db'; break;
                            case 'in-progress': echo '#f39c12'; break;
                            case 'completed': echo '#2ecc71'; break;
                            case 'cancelled': echo '#e74c3c'; break;
                            default: echo '#3498db';
                        }
                    ?>',
                    borderColor: '<?php 
                        switch ($assignment['status']) {
                            case 'scheduled': echo '#2980b9'; break;
                            case 'in-progress': echo '#d35400'; break;
                            case 'completed': echo '#27ae60'; break;
                            case 'cancelled': echo '#c0392b'; break;
                            default: echo '#2980b9';
                        }
                    ?>',
                    textColor: '#ffffff',
                },
                <?php endforeach; ?>
                
                <?php foreach ($upcoming_harvests as $harvest): ?>
                {
                    title: '<?php echo addslashes($harvest['crop_name'] . ' - Expected'); ?>',
                    start: '<?php echo $harvest['expected_harvest_date']; ?>',
                    backgroundColor: '#9b59b6',
                    borderColor: '#8e44ad',
                    textColor: '#ffffff',
                },
                <?php endforeach; ?>
            ],
            eventClick: function(info) {
                if (info.event.url && info.event.url.startsWith('#assignment')) {
                    info.jsEvent.preventDefault();
                    // Could add logic to scroll to assignment or highlight it
                }
            }
        });
        
        calendar.render();
    }
    
    // Set default harvest date when crop is selected
    document.getElementById('crop_id').addEventListener('change', function() {
        var selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            var harvestDate = selectedOption.getAttribute('data-harvest-date');
            document.getElementById('planned_date').value = harvestDate;
        }
    });

    // Automatically close alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        });
    }, 5000);
});

// Function to search tables
function searchTable(tableId, inputId) {
    var input, filter, table, tr, td, i, j, txtValue, found;
    input = document.getElementById(inputId);
    filter = input.value.toUpperCase();
    table = document.getElementById(tableId);
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) {
        found = false;
        td = tr[i].getElementsByTagName("td");
        
        for (j = 0; j < 3; j++) { // Search in first 3 columns only
            if (td[j]) {
                txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        if (found) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}

// Function to prepare assignment form with pre-filled data
function prepareAssignment(cropId, cropName, harvestDate) {
    document.getElementById('crop_id').value = cropId;
    document.getElementById('planned_date').value = harvestDate;
    $('#newAssignmentModal').modal('show');
}

// Function to edit an assignment
function editAssignment(assignmentId) {
    document.getElementById('edit_assignment_id').value = assignmentId;
    $('#editAssignmentModal').modal('show');
}

// Function to update assignment status directly
function updateAssignmentStatus(assignmentId, status) {
    document.getElementById('edit_assignment_id').value = assignmentId;
    document.getElementById('edit_status').value = status;
    document.getElementById('editAssignmentForm').submit();
}

// Function to delete an assignment
function deleteAssignment(assignmentId) {
    document.getElementById('delete_assignment_id').value = assignmentId;
    $('#deleteAssignmentModal').modal('show');
}
</script>

<style>
/* Layout Styles */
body {
    background-color: #f5f7fa;
    font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    margin: 0;
    padding: 0;
}

.main-content {
    padding: 20px;
}

/* Page header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    page-break-inside: avoid;
}
    
#harvestCalendar {
    height: 500px !important;
}
    
.card-actions, .filter-buttons {
    display: none;
}
    
.data-table th, .data-table td {
    border: 1px solid #ddd;
}

.page-header h2 {
    margin: 0;
    font-size: 1.8rem;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

/* Alerts */
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
    position: relative;
    transition: opacity 0.5s ease;
}

.alert-dismissible {
    padding-right: 35px;
}

.close-alert {
    position: absolute;
    top: 0;
    right: 0;
    padding: 10px 15px;
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: inherit;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.alert-warning {
    background-color: #fff3cd;
    border-color: #ffeeba;
    color: #856404;
}

.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}

/* Summary Cards */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.summary-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 15px;
    display: flex;
    align-items: center;
}

.summary-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    margin-right: 15px;
}

.summary-details h3 {
    margin-top: 0;
    margin-bottom: 5px;
    font-size: 14px;
    color: #555;
}

.summary-count {
    font-size: 28px;
    font-weight: bold;
    margin: 0;
    color: #333;
}

.summary-subtitle {
    font-size: 12px;
    color: #888;
}

.bg-green {
    background-color: #2ecc71;
}

.bg-blue {
    background-color: #3498db;
}

.bg-orange {
    background-color: #f39c12;
}

.bg-purple {
    background-color: #9b59b6;
}

/* Content Cards */
.content-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.content-card-header {
    padding: 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
}

.content-card-header h3 {
    margin: 0;
    font-size: 16px;
    color: #333;
}

.card-body {
    padding: 15px;
}

/* Filter Form */
.filter-form {
    width: 100%;
}

.filter-row-inline {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 15px;
}

.filter-group {
    margin-bottom: 0;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    font-size: 13px;
    color: #555;
}

.filter-group select, 
.filter-group input {
    height: 35px;
    padding: 0 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    min-width: 150px;
}

.filter-buttons {
    display: flex;
    gap: 10px;
}

/* Tables */
.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.data-table th {
    background-color: #f8f9fa;
}

.data-table tbody tr:hover {
    background-color: #f9f9f9;
}

/* Calendar */
#harvestCalendar {
    height: 650px;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.status-scheduled, .status-available, .status-active {
    background-color: #e8f4fd;
    color: #3498db;
}

.status-in-progress, .status-in-use, .status-assigned {
    background-color: #fef5e7;
    color: #f39c12;
}

.status-completed {
    background-color: #eafaf1;
    color: #2ecc71;
}

.status-cancelled, .status-maintenance, .status-off {
    background-color: #fdedec;
    color: #e74c3c;
}

/* Staff Cards */
.grid-container.two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.staff-availability-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 15px;
    max-height: 300px;
    overflow-y: auto;
}

.staff-card {
    display: flex;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.staff-available {
    border-left: 4px solid #2ecc71;
}

.staff-assigned {
    border-left: 4px solid #f39c12;
}

.staff-off {
    border-left: 4px solid #e74c3c;
}

.staff-avatar {
    padding: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    color: #7f8c8d;
}

.staff-info {
    flex: 1;
    padding: 12px;
}

.staff-info h4 {
    margin: 0 0 5px 0;
    font-size: 14px;
    font-weight: bold;
}

.staff-role {
    color: #7f8c8d;
    font-size: 12px;
    margin-bottom: 8px;
}

.staff-status {
    margin-bottom: 5px;
    font-size: 12px;
}

/* Buttons */
.btn {
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    border: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-primary {
    background-color: #3498db;
    color: white;
}

.btn-primary:hover {
    background-color: #2980b9;
}

.btn-secondary {
    background-color: #7f8c8d;
    color: white;
}

.btn-secondary:hover {
    background-color: #6c7a7a;
}

.btn-outline {
    background-color: transparent;
    border: 1px solid #ddd;
    color: #555;
}

.btn-outline:hover {
    background-color: #f5f5f5;
}

.btn-danger {
    background-color: #e74c3c;
    color: white;
}

.btn-danger:hover {
    background-color: #c0392b;
}

.btn-icon {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    padding: 5px;
    color: #555;
    transition: color 0.2s;
}

.btn-icon:hover {
    color: #3498db;
}

.btn-icon-primary {
    color: #3498db;
}

.btn-icon-primary:hover {
    color: #2980b9;
}

.btn-icon-success {
    color: #2ecc71;
}

.btn-icon-success:hover {
    color: #27ae60;
}

.btn-icon-danger {
    color: #e74c3c;
}

.btn-icon-danger:hover {
    color: #c0392b;
}

.actions {
    white-space: nowrap;
}

/* Card Actions */
.card-actions {
    display: flex;
    gap: 10px;
}

/* Search Bar */
.search-bar {
    position: relative;
}

.search-bar input {
    padding: 8px 12px 8px 35px;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 220px;
}

.search-bar i {
    position: absolute;
    left: 12px;
    top: 10px;
    color: #aaa;
}

/* Modal Styles */
.modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #eee;
}

.modal-title {
    font-size: 18px;
    color: #333;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    border-top: 1px solid #eee;
    padding: 15px;
}

/* Text Colors */
.text-danger {
    color: #e74c3c;
}

.text-warning {
    color: #f39c12;
}

.font-weight-bold {
    font-weight: bold;
}

.text-center {
    text-align: center;
}

/* Responsive Adjustments */
@media (max-width: 1200px) {
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .grid-container.two-columns {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .action-buttons {
        margin-top: 10px;
        flex-wrap: wrap;
    }
    
    #harvestCalendar {
        height: 500px;
    }
}

/* Print Styles */
@media print {
    .main-content {
        padding: 0;
    }
    
    .action-buttons, .btn, .search-bar, .close-alert {
        display: none !important;
    }
    
    .content-card {
        box-shadow: none;
        margin-bottom: 30px;
        page-break-inside: avoid;
    }
    
    #harvestCalendar {
        height: 500px !important;
    }
    
    .card-actions, .filter-buttons {
        display: none;
    }
    
    .data-table th, .data-table td {
        border: 1px solid #ddd;
    }
}

/* Additional spacing for bottom sections */
.grid-container.two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 100px; /* Increased from 20px to 60px for more space before footer */
}

.footer-container {
    margin-top: 60px;
    border-top: 1px solid #eee;
    padding-top: 20px;
    clear: both;
}

footer {
    position: relative;
    margin-top: 60px;
    clear: both;
}


/* Version information styling */
.version-info {
    text-align: right;
    color: #7f8c8d;
    font-size: 12px;
    margin-top: 20px;
}

/* Copyright information styling */
.copyright-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    margin-top: 20px;
}
</style>

<?php include 'includes/footer.php'; ?>