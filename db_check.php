<?php
// Database structure check and repair script
require_once 'includes/db.php';

echo "<h1>PureFarm Database Structure Check</h1>";

// Check if roles table exists
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'roles'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<p>Creating roles table...</p>";
        
        // Create roles table
        $pdo->exec("
            CREATE TABLE roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_name VARCHAR(50) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Insert default roles
        $roleInsert = $pdo->prepare("
            INSERT INTO roles (role_name, description) 
            VALUES (?, ?)
        ");
        
        $defaultRoles = [
            ['Farm Manager', 'Manages overall farm operations'],
            ['Field Supervisor', 'Supervises field operations and workers'],
            ['Livestock Manager', 'Manages livestock and animal care'],
            ['Crop Specialist', 'Manages crop cultivation and development'],
            ['Equipment Operator', 'Operates farm machinery and equipment'],
            ['General Farmhand', 'Performs various tasks around the farm'],
            ['Administrative Staff', 'Handles office work and documentation'],
            ['Veterinarian', 'Provides animal healthcare services']
        ];
        
        foreach ($defaultRoles as $role) {
            $roleInsert->execute($role);
        }
        
        echo "<p>Roles table created and populated with default roles.</p>";
    } else {
        echo "<p>Roles table exists.</p>";
    }
    
    // Check if staff table exists
    $staffTableExists = $pdo->query("SHOW TABLES LIKE 'staff'")->rowCount() > 0;
    
    if (!$staffTableExists) {
        echo "<p>Creating staff table...</p>";
        
        // Create staff table
        $pdo->exec("
            CREATE TABLE staff (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_id VARCHAR(20) NOT NULL,
                first_name VARCHAR(50) NOT NULL,
                last_name VARCHAR(50) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                address TEXT,
                role_id INT,
                hire_date DATE NOT NULL,
                emergency_contact TEXT,
                notes TEXT,
                profile_image VARCHAR(255),
                status ENUM('active', 'inactive', 'on-leave') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (role_id) REFERENCES roles(id)
            )
        ");
        
        echo "<p>Staff table created.</p>";
    } else {
        echo "<p>Staff table exists.</p>";
        
        // Check if role_id column exists in staff table
        $columnsResult = $pdo->query("SHOW COLUMNS FROM staff LIKE 'role_id'");
        $roleColumnExists = $columnsResult->rowCount() > 0;
        
        if (!$roleColumnExists) {
            echo "<p>Adding role_id column to staff table...</p>";
            
            // Add role_id column
            $pdo->exec("
                ALTER TABLE staff 
                ADD COLUMN role_id INT,
                ADD CONSTRAINT fk_staff_role FOREIGN KEY (role_id) REFERENCES roles(id)
            ");
            
            echo "<p>Role_id column added to staff table.</p>";
        } else {
            echo "<p>Role_id column exists in staff table.</p>";
        }
    }
    
    echo "<p>Database structure check completed successfully.</p>";
    echo "<p><a href='staff_directory.php' class='btn btn-primary'>Go to Staff Directory</a></p>";
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h3>Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}