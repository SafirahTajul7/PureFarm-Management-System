<?php
/**
 * Soil Test Management Module for PureFarm Management System
 * 
 * This file contains functions for managing soil tests in the farm management system,
 * including creating, updating, viewing, and analyzing soil test data.
 */

// Database schema for soil_tests table (to be created if not exists)
// 
// CREATE TABLE `soil_tests` (
//   `id` int(11) NOT NULL AUTO_INCREMENT,
//   `field_id` int(11) NOT NULL,
//   `test_date` date NOT NULL,
//   `ph_level` decimal(3,1) DEFAULT NULL,
//   `moisture_percentage` decimal(5,2) DEFAULT NULL,
//   `temperature` decimal(5,2) DEFAULT NULL,
//   `nitrogen_level` varchar(10) DEFAULT NULL,
//   `phosphorus_level` varchar(10) DEFAULT NULL,
//   `potassium_level` varchar(10) DEFAULT NULL,
//   `organic_matter` decimal(5,2) DEFAULT NULL,
//   `notes` text DEFAULT NULL,
//   `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
//   `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
//   PRIMARY KEY (`id`),
//   KEY `field_id` (`field_id`),
//   CONSTRAINT `soil_tests_ibfk_1` FOREIGN KEY (`field_id`) REFERENCES `fields` (`id`)
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

class SoilTestManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Add a new soil test record
     * 
     * @param array $data Soil test data
     * @return bool|int Returns new ID on success, false on failure
     */
    public function addSoilTest($data) {
        try {
            // Required fields
            if (empty($data['field_id']) || empty($data['test_date'])) {
                return false;
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO soil_tests 
                (field_id, test_date, ph_level, moisture_percentage, temperature, 
                nitrogen_level, phosphorus_level, potassium_level, organic_matter, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['field_id'],
                $data['test_date'],
                $data['ph_level'] ?: null,
                $data['moisture_percentage'] ?: null,
                $data['temperature'] ?: null,
                $data['nitrogen_level'] ?: null,
                $data['phosphorus_level'] ?: null,
                $data['potassium_level'] ?: null,
                $data['organic_matter'] ?: null,
                $data['notes'] ?: null
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error adding soil test: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update an existing soil test record
     * 
     * @param int $id Soil test ID
     * @param array $data Updated soil test data
     * @return bool Success or failure
     */
    public function updateSoilTest($id, $data) {
        try {
            // Required fields
            if (empty($data['field_id']) || empty($data['test_date'])) {
                return false;
            }
            
            $stmt = $this->db->prepare("
                UPDATE soil_tests 
                SET field_id = ?, 
                    test_date = ?, 
                    ph_level = ?, 
                    moisture_percentage = ?, 
                    temperature = ?, 
                    nitrogen_level = ?, 
                    phosphorus_level = ?, 
                    potassium_level = ?, 
                    organic_matter = ?, 
                    notes = ?
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $data['field_id'],
                $data['test_date'],
                $data['ph_level'] ?: null,
                $data['moisture_percentage'] ?: null,
                $data['temperature'] ?: null,
                $data['nitrogen_level'] ?: null,
                $data['phosphorus_level'] ?: null,
                $data['potassium_level'] ?: null,
                $data['organic_matter'] ?: null,
                $data['notes'] ?: null,
                $id
            ]);
        } catch (PDOException $e) {
            error_log("Error updating soil test: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a soil test record
     * 
     * @param int $id Soil test ID
     * @return bool Success or failure
     */
    public function deleteSoilTest($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM soil_tests WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error deleting soil test: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get a single soil test record by ID
     * 
     * @param int $id Soil test ID
     * @return array|bool Soil test data or false if not found
     */
    public function getSoilTest($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT st.*, f.field_name, f.location
                FROM soil_tests st
                JOIN fields f ON st.field_id = f.id
                WHERE st.id = ?
            ");
            
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching soil test: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all soil tests with optional filtering
     * 
     * @param array $filters Optional filters (field_id, date_from, date_to)
     * @return array Soil test records
     */
    public function getAllSoilTests($filters = []) {
        try {
            $query = "
                SELECT 
                    st.id, 
                    st.test_date, 
                    st.ph_level, 
                    st.moisture_percentage, 
                    st.temperature, 
                    st.nitrogen_level, 
                    st.phosphorus_level, 
                    st.potassium_level,
                    st.organic_matter,
                    st.notes,
                    f.field_name,
                    f.location
                FROM 
                    soil_tests st
                JOIN 
                    fields f ON st.field_id = f.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if (!empty($filters['field_id'])) {
                $query .= " AND st.field_id = :field_id";
                $params[':field_id'] = $filters['field_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $query .= " AND st.test_date >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $query .= " AND st.test_date <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }
            
            $query .= " ORDER BY st.test_date DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching soil tests: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get summary statistics for soil tests
     * 
     * @param array $filters Optional filters (field_id, date_from, date_to)
     * @return array Statistics
     */
    public function getSoilTestStatistics($filters = []) {
        try {
            $query = "
                SELECT 
                    COUNT(*) as total_tests,
                    ROUND(AVG(ph_level), 1) as avg_ph,
                    ROUND(AVG(moisture_percentage), 1) as avg_moisture,
                    COUNT(DISTINCT field_id) as fields_count,
                    COUNT(CASE WHEN ph_level < 6.0 THEN 1 END) as low_ph_count
                FROM 
                    soil_tests
                WHERE 
                    test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
            ";
            
            $params = [];
            
            if (!empty($filters['field_id'])) {
                $query .= " AND field_id = :field_id";
                $params[':field_id'] = $filters['field_id'];
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Format output
            $stats['avg_ph'] = $stats['avg_ph'] ?: 'N/A';
            $stats['avg_moisture'] = $stats['avg_moisture'] ? $stats['avg_moisture'] . '%' : 'N/A';
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error fetching soil test statistics: " . $e->getMessage());
            return [
                'total_tests' => 0,
                'avg_ph' => 'N/A',
                'avg_moisture' => 'N/A',
                'fields_count' => 0,
                'low_ph_count' => 0
            ];
        }
    }
    
    /**
     * Get pH trend data for charts
     * 
     * @param array $filters Optional filters (field_id, date_from, date_to)
     * @return array pH trend data
     */
    public function getPHTrend($filters = []) {
        try {
            $query = "
                SELECT 
                    DATE_FORMAT(test_date, '%Y-%m') as month,
                    ROUND(AVG(ph_level), 1) as avg_ph
                FROM 
                    soil_tests
                WHERE 
                    test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
            ";
            
            $params = [];
            
            if (!empty($filters['field_id'])) {
                $query .= " AND field_id = :field_id";
                $params[':field_id'] = $filters['field_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $query .= " AND test_date >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $query .= " AND test_date <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }
            
            $query .= " GROUP BY DATE_FORMAT(test_date, '%Y-%m') ORDER BY month";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching pH trend: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate recommendations based on soil test data
     * 
     * @param array $soilTest Soil test data
     * @return array Recommendations
     */
    public function getRecommendations($soilTest) {
        $recommendations = [];
        
        // Check pH level
        if (isset($soilTest['ph_level'])) {
            if ($soilTest['ph_level'] < 6.0) {
                $recommendations[] = [
                    'title' => 'Low pH Detected',
                    'description' => 'Consider applying lime to raise soil pH for optimal nutrient availability.',
                    'type' => 'warning'
                ];
            } elseif ($soilTest['ph_level'] > 7.5) {
                $recommendations[] = [
                    'title' => 'High pH Detected',
                    'description' => 'Consider applying sulfur or acidifying amendments to lower soil pH for optimal nutrient uptake.',
                    'type' => 'warning'
                ];
            }
        }
        
        // Check moisture
        if (isset($soilTest['moisture_percentage']) && $soilTest['moisture_percentage'] < 20) {
            $recommendations[] = [
                'title' => 'Low Soil Moisture',
                'description' => 'Consider scheduling irrigation or checking irrigation systems.',
                'type' => 'warning'
            ];
        }
        
        // Check nutrient levels
        if (isset($soilTest['nitrogen_level']) && $soilTest['nitrogen_level'] == 'Low') {
            $recommendations[] = [
                'title' => 'Low Nitrogen Level',
                'description' => 'Apply nitrogen-rich fertilizers or incorporate legumes into crop rotation.',
                'type' => 'warning'
            ];
        }
        
        if (isset($soilTest['phosphorus_level']) && $soilTest['phosphorus_level'] == 'Low') {
            $recommendations[] = [
                'title' => 'Low Phosphorus Level',
                'description' => 'Apply phosphorus-rich fertilizers like bone meal or rock phosphate.',
                'type' => 'warning'
            ];
        }
        
        if (isset($soilTest['potassium_level']) && $soilTest['potassium_level'] == 'Low') {
            $recommendations[] = [
                'title' => 'Low Potassium Level',
                'description' => 'Apply potassium-rich fertilizers like potash or wood ash.',
                'type' => 'warning'
            ];
        }
        
        // If no issues found
        if (empty($recommendations)) {
            $recommendations[] = [
                'title' => 'Healthy Soil',
                'description' => 'Soil parameters are within optimal ranges. Continue current management practices.',
                'type' => 'success'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Determine soil health status based on test parameters
     * 
     * @param array $soilTest Soil test data
     * @return array Soil health status
     */
    public function getSoilHealthStatus($soilTest) {
        $ph = isset($soilTest['ph_level']) ? $soilTest['ph_level'] : null;
        $nitrogen = isset($soilTest['nitrogen_level']) ? $soilTest['nitrogen_level'] : null;
        $phosphorus = isset($soilTest['phosphorus_level']) ? $soilTest['phosphorus_level'] : null;
        $potassium = isset($soilTest['potassium_level']) ? $soilTest['potassium_level'] : null;
        
        // Poor conditions
        if (($ph && ($ph < 5.5 || $ph > 8.0)) ||
            ($nitrogen === 'Low' && $phosphorus === 'Low') ||
            ($nitrogen === 'Low' && $potassium === 'Low') ||
            ($phosphorus === 'Low' && $potassium === 'Low')) {
            return [
                'status' => 'Poor',
                'class' => 'danger',
                'icon' => 'exclamation-triangle'
            ];
        }
        // Good conditions
        else if (($ph && ($ph >= 6.0 && $ph <= 7.5)) &&
                 (($nitrogen === 'Medium' || $nitrogen === 'High') &&
                  ($phosphorus === 'Medium' || $phosphorus === 'High') &&
                  ($potassium === 'Medium' || $potassium === 'High'))) {
            return [
                'status' => 'Excellent',
                'class' => 'success',
                'icon' => 'check-circle'
            ];
        }
        // Average conditions
        else {
            return [
                'status' => 'Fair',
                'class' => 'warning',
                'icon' => 'exclamation-circle'
            ];
        }
    }
}

// Helper functions for soil test module

/**
 * Create the database schema for soil tests if it doesn't exist
 * 
 * @param PDO $pdo Database connection
 * @return bool True if successful, false otherwise
 */
function ensureSoilTestsSchema($pdo) {
    try {
        // Check if soil_tests table exists
        $tables = $pdo->query("SHOW TABLES LIKE 'soil_tests'")->fetchAll();
        
        if (empty($tables)) {
            // Create soil_tests table if it doesn't exist
            $pdo->exec("
                CREATE TABLE `soil_tests` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `field_id` int(11) NOT NULL,
                  `test_date` date NOT NULL,
                  `ph_level` decimal(3,1) DEFAULT NULL,
                  `moisture_percentage` decimal(5,2) DEFAULT NULL,
                  `temperature` decimal(5,2) DEFAULT NULL,
                  `nitrogen_level` varchar(10) DEFAULT NULL,
                  `phosphorus_level` varchar(10) DEFAULT NULL,
                  `potassium_level` varchar(10) DEFAULT NULL,
                  `organic_matter` decimal(5,2) DEFAULT NULL,
                  `notes` text DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                  PRIMARY KEY (`id`),
                  KEY `field_id` (`field_id`),
                  CONSTRAINT `soil_tests_ibfk_1` FOREIGN KEY (`field_id`) REFERENCES `fields` (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            
            return true;
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error creating soil_tests schema: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate soil test data
 * 
 * @param array $data Soil test data to validate
 * @return array Validation errors (empty if valid)
 */
function validateSoilTestData($data) {
    $errors = [];
    
    // Required fields
    if (empty($data['field_id'])) {
        $errors['field_id'] = 'Field is required';
    }
    
    if (empty($data['test_date'])) {
        $errors['test_date'] = 'Test date is required';
    }
    
    // Validate pH level if provided
    if (!empty($data['ph_level']) && (!is_numeric($data['ph_level']) || $data['ph_level'] < 0 || $data['ph_level'] > 14)) {
        $errors['ph_level'] = 'pH level must be a number between 0 and 14';
    }
    
    // Validate moisture percentage if provided
    if (!empty($data['moisture_percentage']) && (!is_numeric($data['moisture_percentage']) || $data['moisture_percentage'] < 0 || $data['moisture_percentage'] > 100)) {
        $errors['moisture_percentage'] = 'Moisture percentage must be a number between 0 and 100';
    }
    
    // Validate temperature if provided
    if (!empty($data['temperature']) && !is_numeric($data['temperature'])) {
        $errors['temperature'] = 'Temperature must be a valid number';
    }
    
    // Validate organic matter if provided
    if (!empty($data['organic_matter']) && (!is_numeric($data['organic_matter']) || $data['organic_matter'] < 0 || $data['organic_matter'] > 100)) {
        $errors['organic_matter'] = 'Organic matter must be a number between 0 and 100';
    }
    
    return $errors;
}

/**
 * Format soil test data for display
 * 
 * @param array $soilTest Soil test data
 * @return array Formatted soil test data
 */
function formatSoilTestData($soilTest) {
    // Format date
    if (isset($soilTest['test_date'])) {
        $soilTest['formatted_date'] = date('M d, Y', strtotime($soilTest['test_date']));
    }
    
    // Format percentages
    if (isset($soilTest['moisture_percentage'])) {
        $soilTest['formatted_moisture'] = $soilTest['moisture_percentage'] . '%';
    }
    
    if (isset($soilTest['organic_matter'])) {
        $soilTest['formatted_organic_matter'] = $soilTest['organic_matter'] . '%';
    }
    
    // Format temperature
    if (isset($soilTest['temperature'])) {
        $soilTest['formatted_temperature'] = $soilTest['temperature'] . '°C';
    }
    
    return $soilTest;
}