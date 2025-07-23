<?php
require_once 'includes/db.php';

try {
    // Create fields table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fields (
            id INT AUTO_INCREMENT PRIMARY KEY,
            field_name VARCHAR(100) NOT NULL,
            location VARCHAR(255) NOT NULL,
            area DECIMAL(10,2) NOT NULL COMMENT 'in acres/hectares',
            soil_type VARCHAR(100),
            last_crop VARCHAR(100),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Create crops table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crops (
            id INT AUTO_INCREMENT PRIMARY KEY,
            crop_name VARCHAR(100) NOT NULL,
            variety VARCHAR(100),
            field_id INT,
            planting_date DATE NOT NULL,
            expected_harvest_date DATE,
            actual_harvest_date DATE,
            growth_stage VARCHAR(50) DEFAULT 'seedling',
            status VARCHAR(50) DEFAULT 'active' COMMENT 'active, harvested, failed',
            next_action VARCHAR(100),
            next_action_date DATE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE SET NULL
        )
    ");
    
    // Create crop issues table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crop_issues (
            id INT AUTO_INCREMENT PRIMARY KEY,
            crop_id INT NOT NULL,
            issue_type ENUM('pest', 'disease', 'nutrient', 'other') NOT NULL,
            description TEXT NOT NULL,
            date_identified DATE NOT NULL,
            severity ENUM('low', 'medium', 'high') NOT NULL,
            treatment_applied TEXT,
            resolved TINYINT(1) DEFAULT 0,
            resolution_date DATE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE
        )
    ");
    
    // Create crop activities table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS crop_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            crop_id INT NOT NULL,
            activity_type ENUM('planting', 'irrigation', 'fertilization', 'pesticide', 'weeding', 'harvest', 'other') NOT NULL,
            activity_date DATE NOT NULL,
            description TEXT NOT NULL,
            quantity DECIMAL(10,2),
            unit VARCHAR(20),
            performed_by INT COMMENT 'staff ID',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE
        )
    ");
    
    // Insert some sample fields
    $pdo->exec("
        INSERT INTO fields (field_name, location, area, soil_type, last_crop, notes) VALUES
        ('North Field', 'Northern Farm Area', 12.5, 'Loamy', 'Corn', 'Good drainage, sunny exposure'),
        ('South Plot', 'Southern Farm Area', 8.3, 'Clay', 'Soybeans', 'Near water source, partial shade in evening'),
        ('East Acres', 'Eastern Farm Area', 15.0, 'Sandy', 'Wheat', 'Requires additional irrigation')
    ");
    
    echo "Crop management tables created successfully!";
    echo "<br><a href='field_management.php'>Go to Field Management</a>";
    
} catch(PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}
?>