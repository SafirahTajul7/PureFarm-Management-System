<?php
/**
 * PureFarm Management System - Database Setup Script
 * 
 * This script creates and populates all necessary tables for the financial module.
 * Run this script once to initialize your database with proper structure and sample data.
 */

require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = 'Database Setup';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-database"></i> Database Setup</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='index.php'">
                <i class="fas fa-home"></i> Return to Dashboard
            </button>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-header">
            <h3>Setup Results</h3>
        </div>
        <div class="card-body">
            <div class="setup-log">
                <?php
                // Function to create a table if it doesn't exist
                function createTableIfNotExists($pdo, $tableName, $createTableSQL) {
                    try {
                        $tableCheck = $pdo->query("SHOW TABLES LIKE '$tableName'");
                        if ($tableCheck->rowCount() === 0) {
                            $pdo->exec($createTableSQL);
                            echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Created table: $tableName</div>";
                            return true;
                        } else {
                            echo "<div class='log-entry log-info'><i class='fas fa-info-circle'></i> Table already exists: $tableName</div>";
                            return false;
                        }
                    } catch (PDOException $e) {
                        echo "<div class='log-entry log-error'><i class='fas fa-times-circle'></i> Error creating table $tableName: " . $e->getMessage() . "</div>";
                        return false;
                    }
                }

                try {
                    echo "<div class='log-entry'><strong>Starting database setup...</strong></div>";
                    
                    // Create crops table
                    $created = createTableIfNotExists($pdo, 'crops', "
                        CREATE TABLE `crops` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `name` varchar(100) NOT NULL,
                            `variety` varchar(100) DEFAULT NULL,
                            `field_id` int(11) DEFAULT NULL,
                            `status` enum('active','inactive','harvested') NOT NULL DEFAULT 'active',
                            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                    
                    // Populate crops table with sample data if newly created
                    if ($created) {
                        $pdo->exec("
                            INSERT INTO `crops` (`name`, `variety`, `status`) VALUES
                            ('Cili Merah Kulai', 'Spicy', 'active'),
                            ('Corn', 'Sweet Corn', 'active'),
                            ('Tomatoes', 'Roma', 'active'),
                            ('Lettuce', 'Iceberg', 'active'),
                            ('Wheat', 'Winter Wheat', 'active')
                        ");
                        echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Added sample crops data</div>";
                    }
                    
                    // Create fields table
                    $created = createTableIfNotExists($pdo, 'fields', "
                        CREATE TABLE `fields` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `field_name` varchar(100) NOT NULL,
                            `location` varchar(255) DEFAULT NULL,
                            `area` decimal(10,2) DEFAULT NULL,
                            `soil_type` varchar(50) DEFAULT NULL,
                            `status` enum('active','inactive') NOT NULL DEFAULT 'active',
                            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                    
                    // Populate fields table with sample data if newly created
                    if ($created) {
                        $pdo->exec("
                            INSERT INTO `fields` (`field_name`, `location`, `area`, `soil_type`, `status`) VALUES
                            ('North Field', 'North Farm Area', 10.5, 'Loam', 'active'),
                            ('South Field', 'South Farm Area', 8.2, 'Clay', 'active'),
                            ('East Field', 'East Farm Area', 12.0, 'Sandy', 'active'),
                            ('West Field', 'West Farm Area', 9.7, 'Silt', 'active'),
                            ('Greenhouse', 'Central Farm Area', 5.0, 'Controlled', 'active')
                        ");
                        echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Added sample fields data</div>";
                    }
                    
                    // Create expense_categories table
                    $created = createTableIfNotExists($pdo, 'expense_categories', "
                        CREATE TABLE `expense_categories` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `name` varchar(50) NOT NULL,
                            `description` text,
                            PRIMARY KEY (`id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                    
                    // Populate expense_categories table with sample data if newly created
                    if ($created) {
                        $pdo->exec("
                            INSERT INTO `expense_categories` (`name`, `description`) VALUES
                            ('Seeds', 'Expenses related to purchasing seeds'),
                            ('Fertilizer', 'Costs for all types of fertilizers'),
                            ('Pesticides', 'Costs for pest control chemicals'),
                            ('Irrigation', 'Water and irrigation system costs'),
                            ('Labor', 'Worker wages and contractor fees'),
                            ('Equipment', 'Costs for farm equipment rental or purchase'),
                            ('Fuel', 'Fuel for tractors and other equipment'),
                            ('Transportation', 'Costs for transporting crops'),
                            ('Storage', 'Storage facility costs'),
                            ('Other', 'Miscellaneous expenses')
                        ");
                        echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Added expense categories</div>";
                    }
                    
                    // Create crop_expenses table
                    $created = createTableIfNotExists($pdo, 'crop_expenses', "
                        CREATE TABLE `crop_expenses` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `crop_id` int(11) NOT NULL,
                            `date` date NOT NULL,
                            `amount` decimal(10,2) NOT NULL,
                            `category` varchar(50) NOT NULL,
                            `description` text,
                            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            KEY `crop_id` (`crop_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                    
                    // Create crop_revenue table
                    $created = createTableIfNotExists($pdo, 'crop_revenue', "
                        CREATE TABLE `crop_revenue` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `crop_id` int(11) NOT NULL,
                            `date` date NOT NULL,
                            `amount` decimal(10,2) NOT NULL,
                            `description` text,
                            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`),
                            KEY `crop_id` (`crop_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                    
                    // Check if we need to add sample financial data
                    $expenseCount = $pdo->query("SELECT COUNT(*) FROM crop_expenses")->fetchColumn();
                    $revenueCount = $pdo->query("SELECT COUNT(*) FROM crop_revenue")->fetchColumn();
                    
                    if ($expenseCount == 0) {
                        // Get crop IDs
                        $cropStmt = $pdo->query("SELECT id, name FROM crops ORDER BY id LIMIT 5");
                        $crops = $cropStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($crops)) {
                            echo "<div class='log-entry'><i class='fas fa-spinner fa-spin'></i> Adding sample expense data...</div>";
                            
                            // Current year for timestamps
                            $currentYear = date('Y');
                            
                            // Create sample expenses for each crop
                            $expenseStmt = $pdo->prepare("
                                INSERT INTO `crop_expenses` 
                                (`crop_id`, `date`, `amount`, `category`, `description`) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            
                            $totalExpensesAdded = 0;
                            
                            foreach ($crops as $crop) {
                                $cropId = $crop['id'];
                                $cropName = $crop['name'];
                                
                                // Sample expenses data with different dates
                                $sampleExpenses = [
                                    [$cropId, "$currentYear-03-15", 450.00, 'Seeds', "Initial seed purchase for $cropName"],
                                    [$cropId, "$currentYear-03-25", 320.50, 'Fertilizer', "Organic fertilizer for initial planting"],
                                    [$cropId, "$currentYear-04-08", 275.25, 'Fertilizer', "NPK fertilizer application"],
                                    [$cropId, "$currentYear-04-02", 180.75, 'Pesticides', "Preventive pest control treatment"],
                                    [$cropId, "$currentYear-04-12", 215.00, 'Pesticides', "Treatment for aphids as noted in crop monitoring"],
                                    [$cropId, "$currentYear-03-20", 550.00, 'Labor', "Land preparation and initial planting labor"],
                                    [$cropId, "$currentYear-04-05", 325.00, 'Labor', "Weeding and maintenance work"],
                                    [$cropId, "$currentYear-04-15", 275.00, 'Labor', "Fertilizer and pesticide application labor"],
                                    [$cropId, "$currentYear-03-18", 420.00, 'Irrigation', "Installation of drip irrigation system"],
                                    [$cropId, "$currentYear-04-10", 150.00, 'Irrigation', "Water usage charges"],
                                    [$cropId, "$currentYear-03-22", 350.00, 'Equipment', "Rental of tractor for land preparation"],
                                    [$cropId, "$currentYear-04-01", 125.50, 'Other', "Miscellaneous supplies and tools"]
                                ];
                                
                                foreach ($sampleExpenses as $expense) {
                                    $expenseStmt->execute($expense);
                                    $totalExpensesAdded++;
                                }
                            }
                            
                            echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Added $totalExpensesAdded sample expenses</div>";
                        }
                    } else {
                        echo "<div class='log-entry log-info'><i class='fas fa-info-circle'></i> Expenses data already exists ($expenseCount records)</div>";
                    }
                    
                    if ($revenueCount == 0) {
                        // Get crop IDs
                        $cropStmt = $pdo->query("SELECT id, name FROM crops ORDER BY id LIMIT 5");
                        $crops = $cropStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($crops)) {
                            echo "<div class='log-entry'><i class='fas fa-spinner fa-spin'></i> Adding sample revenue data...</div>";
                            
                            // Current year for timestamps
                            $currentYear = date('Y');
                            
                            // Create sample revenue for each crop
                            $revenueStmt = $pdo->prepare("
                                INSERT INTO `crop_revenue` 
                                (`crop_id`, `date`, `amount`, `description`) 
                                VALUES (?, ?, ?, ?)
                            ");
                            
                            $totalRevenueAdded = 0;
                            
                            foreach ($crops as $crop) {
                                $cropId = $crop['id'];
                                $cropName = $crop['name'];
                                
                                // Sample revenue data with different dates
                                $sampleRevenue = [
                                    [$cropId, "$currentYear-05-05", 850.00, "Advance purchase contract for first harvest batch of $cropName"],
                                    [$cropId, "$currentYear-06-15", 2250.00, "First partial harvest - 150kg @ $15/kg"],
                                    [$cropId, "$currentYear-07-01", 3750.00, "Main harvest - 250kg @ $15/kg"],
                                    [$cropId, "$currentYear-07-18", 1500.00, "Final harvest - 100kg @ $15/kg"]
                                ];
                                
                                foreach ($sampleRevenue as $revenue) {
                                    $revenueStmt->execute($revenue);
                                    $totalRevenueAdded++;
                                }
                            }
                            
                            echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> Added $totalRevenueAdded sample revenue records</div>";
                        }
                    } else {
                        echo "<div class='log-entry log-info'><i class='fas fa-info-circle'></i> Revenue data already exists ($revenueCount records)</div>";
                    }
                    
                    // Final status check
                    $tableStatus = [];
                    $tables = ['crops', 'fields', 'expense_categories', 'crop_expenses', 'crop_revenue'];
                    
                    foreach ($tables as $table) {
                        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                        $tableStatus[$table] = $count;
                    }
                    
                    echo "<div class='log-entry log-success'><i class='fas fa-check-circle'></i> <strong>Database setup completed successfully!</strong></div>";
                    echo "<div class='log-entry'><strong>Current database status:</strong></div>";
                    echo "<ul>";
                    foreach ($tableStatus as $table => $count) {
                        echo "<li>$table: $count records</li>";
                    }
                    echo "</ul>";
                    
                } catch (PDOException $e) {
                    echo "<div class='log-entry log-error'><i class='fas fa-times-circle'></i> <strong>Database Error:</strong> " . $e->getMessage() . "</div>";
                }
                ?>
            </div>
            
            <div class="actions mt-4">
                <a href="financial_analysis.php" class="btn btn-primary">
                    <i class="fas fa-chart-line"></i> Go to Financial Analysis
                </a>
                <a href="add_expense.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Expense
                </a>
                <a href="add_revenue.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Revenue
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Return to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.setup-log {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    max-height: 500px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 14px;
    line-height: 1.5;
}

.log-entry {
    padding: 8px 12px;
    margin-bottom: 8px;
    border-left: 4px solid #ddd;
    background-color: #fff;
}

.log-success {
    border-left-color: #28a745;
    background-color: #f0fff0;
}

.log-error {
    border-left-color: #dc3545;
    background-color: #fff0f0;
}

.log-info {
    border-left-color: #17a2b8;
    background-color: #f0f8ff;
}

.actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn {
    display: inline-block;
    font-weight: 400;
    color: #212529;
    text-align: center;
    vertical-align: middle;
    cursor: pointer;
    user-select: none;
    background-color: transparent;
    border: 1px solid transparent;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    line-height: 1.5;
    border-radius: 0.25rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    text-decoration: none;
}

.btn-primary {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
}

.btn-success {
    color: #fff;
    background-color: #28a745;
    border-color: #28a745;
}

.btn-secondary {
    color: #fff;
    background-color: #6c757d;
    border-color: #6c757d;
}

.mt-4 {
    margin-top: 1.5rem;
}
</style>

<?php include 'includes/footer.php'; ?>