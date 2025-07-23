<?php
/**
 * fix_crops_data.php - Fix for missing crops in harvest planning
 * 
 * This script will:
 * 1. Check if the crops table exists
 * 2. Verify if there are active crops in the database
 * 3. Add sample crop data if needed
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';

// Ensure only admin can run this script
auth()->checkAdmin();

// Set page title and include header
$pageTitle = 'Database Fix - Crops';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-database"></i> Database Fix - Crops Table</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='harvest_planning.php'">
                <i class="fas fa-arrow-left"></i> Back to Harvest Planning
            </button>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-tools"></i> Diagnostic Results</h3>
        </div>
        <div class="card-body">
            <?php
            try {
                echo "<div class='log-entry'><strong>Starting database diagnosis...</strong></div>";
                
                // 1. Check if crops table exists
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'crops'");
                $cropTableExists = $tableCheck->rowCount() > 0;
                
                if (!$cropTableExists) {
                    echo "<div class='log-entry log-error'><i class='fas fa-times-circle'></i> Crops table does not exist!</div>";
                    
                    // Create crops table
                    echo "<div class='log-entry'><i class='fas fa-cog fa-spin'></i> Creating crops table...</div>";
                    
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS `crops` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `crop_name` varchar(100) NOT NULL,
                            `variety` varchar(100) DEFAULT NULL,
                            `field_id` int(11) NOT NULL,
                            `planting_date` date DEFAULT NULL,
                            `expected_harvest_date` date DEFAULT NULL,
                            `actual_harvest_date` date DEFAULT NULL,
                            `growth_stage` varchar(50) DEFAULT NULL,
                            `status` enum('active','inactive','harvested','failed') NOT NULL DEFAULT 'active',
                            `next_action` varchar(100) DEFAULT NULL,
                            `next_action_date` date DEFAULT NULL,
                            `notes` text DEFAULT NULL,
                            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            KEY `field_id` (`field_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                    
                    echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Crops table created successfully!</div>";
                } else {
                    echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Crops table exists.</div>";
                }
                
                // 2. Check if fields table exists
                $fieldsCheck = $pdo->query("SHOW TABLES LIKE 'fields'");
                $fieldsTableExists = $fieldsCheck->rowCount() > 0;
                
                if (!$fieldsTableExists) {
                    echo "<div class='log-entry log-error'><i class='fas fa-times-circle'></i> Fields table does not exist!</div>";
                    
                    // Create fields table
                    echo "<div class='log-entry'><i class='fas fa-cog fa-spin'></i> Creating fields table...</div>";
                    
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS `fields` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `field_name` varchar(100) NOT NULL,
                            `location` varchar(255) DEFAULT NULL,
                            `area` decimal(10,2) DEFAULT NULL,
                            `soil_type` varchar(50) DEFAULT NULL,
                            `status` enum('active','inactive') NOT NULL DEFAULT 'active',
                            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");
                    
                    echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Fields table created successfully!</div>";
                    
                    // Add sample field data
                    echo "<div class='log-entry'><i class='fas fa-cog fa-spin'></i> Adding sample field data...</div>";
                    
                    $pdo->exec("
                        INSERT INTO `fields` (`field_name`, `location`, `area`, `soil_type`, `status`) 
                        VALUES 
                        ('North Field', 'North Farm Area', 10.5, 'Loam', 'active'),
                        ('South Field', 'South Farm Area', 8.2, 'Clay', 'active'),
                        ('East Field', 'East Farm Area', 12.0, 'Sandy', 'active'),
                        ('West Field', 'West Farm Area', 9.7, 'Silt', 'active');
                    ");
                    
                    echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Sample field data added successfully!</div>";
                } else {
                    echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Fields table exists.</div>";
                }
                
                // 3. Check if there are any active crops
                $cropCountStmt = $pdo->query("SELECT COUNT(*) FROM crops WHERE status = 'active'");
                $activeCropCount = $cropCountStmt->fetchColumn();
                
                echo "<div class='log-entry'><i class='fas fa-info-circle'></i> Found $activeCropCount active crops in database.</div>";
                
                if ($activeCropCount == 0) {
                    echo "<div class='log-entry log-warning'><i class='fas fa-exclamation-triangle'></i> No active crops found!</div>";
                    
                    // Get field IDs for reference
                    $fieldStmt = $pdo->query("SELECT id FROM fields WHERE status = 'active' LIMIT 4");
                    $fields = $fieldStmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (count($fields) === 0) {
                        echo "<div class='log-entry log-error'><i class='fas fa-times-circle'></i> No active fields found. Please add fields first.</div>";
                    } else {
                        // Add sample crop data
                        echo "<div class='log-entry'><i class='fas fa-cog fa-spin'></i> Adding sample crop data...</div>";
                        
                        // Current date for reference
                        $currentDate = date('Y-m-d');
                        
                        // Sample crops with upcoming harvests
                        $cropInsertStmt = $pdo->prepare("
                            INSERT INTO `crops` 
                            (`crop_name`, `variety`, `field_id`, `planting_date`, `expected_harvest_date`, 
                            `actual_harvest_date`, `growth_stage`, `status`, `next_action`, `next_action_date`, `notes`) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        // Add sample crops with different expected harvest dates
                        $sampleCrops = [
                            ['Corn', 'Sweet Corn', $fields[0], date('Y-m-d', strtotime($currentDate . ' - 30 days')), date('Y-m-d', strtotime($currentDate . ' + 30 days')), null, 'flowering', 'active', 'Fertilize', date('Y-m-d', strtotime($currentDate . ' + 5 days')), 'Monitor for pests'],
                            ['Wheat', 'Winter Wheat', $fields[1], date('Y-m-d', strtotime($currentDate . ' - 60 days')), date('Y-m-d', strtotime($currentDate . ' + 45 days')), null, 'growing', 'active', 'Irrigate', date('Y-m-d', strtotime($currentDate . ' + 7 days')), 'Check for disease'],
                            ['Soybeans', 'Round-Up Ready', $fields[2], date('Y-m-d', strtotime($currentDate . ' - 45 days')), date('Y-m-d', strtotime($currentDate . ' + 60 days')), null, 'seedling', 'active', 'Apply Herbicide', date('Y-m-d', strtotime($currentDate . ' + 10 days')), 'Monitor growth'],
                            ['Cili Merah Kulai', 'Spicy', $fields[3], date('Y-m-d', strtotime($currentDate . ' - 15 days')), date('Y-m-d', strtotime($currentDate . ' + 75 days')), null, 'flowering', 'active', 'Fertilize, Irrigate', date('Y-m-d', strtotime($currentDate . ' + 3 days')), 'Monitor for pests (especially aphids & thrips)']
                        ];
                        
                        foreach ($sampleCrops as $crop) {
                            $cropInsertStmt->execute($crop);
                        }
                        
                        echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Added " . count($sampleCrops) . " sample crops successfully!</div>";
                        
                        // Verify the crops were added
                        $cropCountStmt = $pdo->query("SELECT COUNT(*) FROM crops WHERE status = 'active'");
                        $newActiveCropCount = $cropCountStmt->fetchColumn();
                        
                        echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Now you have $newActiveCropCount active crops in the database.</div>";
                    }
                }
                
                // 4. Display current crops in the database
                $cropsStmt = $pdo->query("
                    SELECT 
                        c.id, c.crop_name, c.variety, c.growth_stage, c.expected_harvest_date, c.status,
                        f.field_name, f.location
                    FROM 
                        crops c
                    JOIN 
                        fields f ON c.field_id = f.id
                    WHERE 
                        c.status = 'active'
                    ORDER BY 
                        c.expected_harvest_date ASC
                ");
                
                $crops = $cropsStmt->fetchAll();
                
                if (count($crops) > 0) {
                    echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Your active crops are listed below:</div>";
                    
                    echo "<table class='data-table mt-4'>";
                    echo "<thead><tr><th>ID</th><th>Crop Name</th><th>Variety</th><th>Field</th><th>Growth Stage</th><th>Expected Harvest</th></tr></thead>";
                    echo "<tbody>";
                    
                    foreach ($crops as $crop) {
                        echo "<tr>";
                        echo "<td>{$crop['id']}</td>";
                        echo "<td>{$crop['crop_name']}</td>";
                        echo "<td>{$crop['variety']}</td>";
                        echo "<td>{$crop['field_name']} ({$crop['location']})</td>";
                        echo "<td>{$crop['growth_stage']}</td>";
                        echo "<td>" . date('M d, Y', strtotime($crop['expected_harvest_date'])) . "</td>";
                        echo "</tr>";
                    }
                    
                    echo "</tbody></table>";
                }
                
                echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Database check and fix complete!</div>";
                
            } catch (PDOException $e) {
                echo "<div class='log-entry log-error'><i class='fas fa-times-circle'></i> Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
            ?>
            
            <div class="actions mt-4">
                <p><strong>Next Steps:</strong></p>
                <a href="harvest_planning.php" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i> Return to Harvest Planning
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.log-entry {
    padding: 8px 12px;
    margin-bottom: 8px;
    border-left: 4px solid #ddd;
    background-color: #f9f9f9;
}

.log-success {
    border-left-color: #2ecc71;
    background-color: #eafaf1;
}

.log-error {
    border-left-color: #e74c3c;
    background-color: #fdedec;
}

.log-warning {
    border-left-color: #f39c12;
    background-color: #fef5e7;
}

.mt-4 {
    margin-top: 20px;
}

.actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}
</style>

<?php include 'includes/footer.php'; ?>