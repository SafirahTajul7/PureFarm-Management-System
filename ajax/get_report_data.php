<?php
/**
 * Ajax Handler for Fetching Report Data
 * 
 * This file handles AJAX requests for fetching dynamic report data
 */

require_once '../includes/auth.php';
auth()->checkAdmin();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check for required parameters
if (!isset($_GET['report_type'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing report_type parameter'
    ]);
    exit;
}

$reportType = $_GET['report_type'];
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Validate date parameters if provided
if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid start date format. Use YYYY-MM-DD'
    ]);
    exit;
}

if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid end date format. Use YYYY-MM-DD'
    ]);
    exit;
}

try {
    $data = [];
    
    switch ($reportType) {
        case 'overview':
            $data = getOverviewData($pdo, $startDate, $endDate);
            break;
        case 'health':
            $data = getHealthData($pdo, $startDate, $endDate);
            break;
        case 'breeding':
            $data = getBreedingData($pdo, $startDate, $endDate);
            break;
        case 'deceased':
            $data = getDeceasedData($pdo, $startDate, $endDate);
            break;
        case 'vaccination':
            $data = getVaccinationData($pdo, $startDate, $endDate);
            break;
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid report type'
            ]);
            exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating report data: ' . $e->getMessage()
    ]);
}

/**
 * Get Overview Report Data
 */
function getOverviewData($pdo, $startDate = null, $endDate = null) {
    // Get date filter
    $dateFilter = getDateFilterClause($startDate, $endDate);
    
    // Total animals
    $total_animals = $pdo->query("
        SELECT COUNT(*) 
        FROM animals 
        WHERE id NOT IN (SELECT COALESCE(animal_id, 0) FROM deceased_animals)
    ")->fetchColumn();
    
    // Animals by species
    $speciesStmt = $pdo->query("
        SELECT species, COUNT(*) as count 
        FROM animals 
        WHERE id NOT IN (SELECT COALESCE(animal_id, 0) FROM deceased_animals) 
        GROUP BY species
    ");
    $speciesData = $speciesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $data = [];
    $colors = [
        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', 
        '#e74a3b', '#fd7e14', '#6f42c1', '#20c9a6'
    ];
    
    foreach ($speciesData as $i => $species) {
        $labels[] = $species['species'];
        $data[] = $species['count'];
    }
    
    // Health trends by month (last 6 months)
    $healthTrendsQuery = "
        SELECT 
            DATE_FORMAT(date, '%Y-%m') as month, 
            `condition`, 
            COUNT(*) as count
        FROM health_records
        WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m'), `condition`
        ORDER BY month
    ";
    
    $healthTrendsStmt = $pdo->query($healthTrendsQuery);
    $healthTrendsData = $healthTrendsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare structured data for health trends chart
    $months = [];
    $healthConditions = [];
    $healthTrendsCounts = [];
    
    foreach ($healthTrendsData as $record) {
        if (!in_array($record['month'], $months)) {
            $months[] = $record['month'];
        }
        
        if (!in_array($record['condition'], $healthConditions)) {
            $healthConditions[] = $record['condition'];
        }
        
        $healthTrendsCounts[$record['month']][$record['condition']] = $record['count'];
    }
    
    // Prepare datasets for health trends chart
    $healthTrendsDatasets = [];
    $healthColors = [
        'healthy' => '#1cc88a',
        'sick' => '#e74a3b',
        'injured' => '#f6c23e',
        'recovery' => '#4e73df',
        'quarantine' => '#6f42c1'
    ];
    
    foreach ($healthConditions as $i => $condition) {
        $conditionData = [];
        
        foreach ($months as $month) {
            $conditionData[] = isset($healthTrendsCounts[$month][$condition]) 
                ? $healthTrendsCounts[$month][$condition] 
                : 0;
        }
        
        $healthTrendsDatasets[] = [
            'label' => ucfirst($condition),
            'data' => $conditionData,
            'borderColor' => isset($healthColors[$condition]) ? $healthColors[$condition] : $colors[$i % count($colors)],
            'backgroundColor' => isset($healthColors[$condition]) ? $healthColors[$condition] : $colors[$i % count($colors)],
            'fill' => false
        ];
    }
    
    // Format months for display
    $formattedMonths = [];
    foreach ($months as $month) {
        $dateObj = date_create($month . '-01');
        $formattedMonths[] = date_format($dateObj, 'M Y');
    }
    
    // Get breeding success rates
    $breedingQuery = "
        SELECT
            DATE_FORMAT(date, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN outcome = 'successful' THEN 1 ELSE 0 END) as successful
        FROM breeding_history
        WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY month
    ";
    
    $breedingStmt = $pdo->query($breedingQuery);
    $breedingData = $breedingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $breedingMonths = [];
    $breedingRates = [];
    
    foreach ($breedingData as $record) {
        $dateObj = date_create($record['month'] . '-01');
        $breedingMonths[] = date_format($dateObj, 'M Y');
        
        $rate = $record['total'] > 0 
            ? round(($record['successful'] / $record['total']) * 100, 2) 
            : 0;
            
        $breedingRates[] = $rate;
    }
    
    return [
        'totalAnimals' => $total_animals,
        'speciesData' => [
            'labels' => $labels,
            'data' => $data,
            'colors' => array_slice($colors, 0, count($labels))
        ],
        'healthTrends' => [
            'labels' => $formattedMonths,
            'datasets' => $healthTrendsDatasets
        ],
        'breedingData' => [
            'labels' => $breedingMonths,
            'rates' => $breedingRates
        ]
    ];
}

/**
 * Get Health Report Data
 */
function getHealthData($pdo, $startDate = null, $endDate = null) {
    // Build date filter clause
    $dateFilterClause = '';
    if ($startDate && $endDate) {
        $dateFilterClause = " AND h.date BETWEEN '$startDate' AND '$endDate'";
    }
    
    // Get health records
    $query = "
        SELECT h.*, a.animal_id as animal_code, a.species, a.breed 
        FROM health_records h 
        JOIN animals a ON h.animal_id = a.id 
        WHERE 1=1 $dateFilterClause
        ORDER BY h.date DESC
        LIMIT 1000
    ";
    
    $stmt = $pdo->query($query);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get condition counts
    $conditionQuery = "
        SELECT `condition`, COUNT(*) as count
        FROM health_records
        WHERE 1=1 " . str_replace('h.date', 'date', $dateFilterClause) . "
        GROUP BY `condition`
    ";
    
    $conditionStmt = $pdo->query($conditionQuery);
    $conditionData = $conditionStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $conditionLabels = [];
    $conditionCounts = [];
    
    foreach ($conditionData as $record) {
        $conditionLabels[] = ucfirst($record['condition']);
        $conditionCounts[] = $record['count'];
    }
    
    return [
        'records' => $records,
        'conditionChart' => [
            'labels' => $conditionLabels,
            'counts' => $conditionCounts
        ]
    ];
}

/**
 * Get Breeding Report Data
 */
function getBreedingData($pdo, $startDate = null, $endDate = null) {
    // Build date filter clause
    $dateFilterClause = '';
    if ($startDate && $endDate) {
        $dateFilterClause = " AND bh.date BETWEEN '$startDate' AND '$endDate'";
    }
    
    // Get breeding records
    $query = "
        SELECT bh.*, 
               a1.species as animal_species, a1.animal_id as female_code,
               a2.species as partner_species, a2.animal_id as male_code
        FROM breeding_history bh
        LEFT JOIN animals a1 ON bh.animal_id = a1.id
        LEFT JOIN animals a2 ON bh.partner_id = a2.id
        WHERE 1=1 $dateFilterClause
        ORDER BY bh.date DESC
        LIMIT 1000
    ";
    
    $stmt = $pdo->query($query);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get outcome counts
    $outcomeQuery = "
        SELECT outcome, COUNT(*) as count
        FROM breeding_history
        WHERE 1=1 " . str_replace('bh.date', 'date', $dateFilterClause) . "
        GROUP BY outcome
    ";
    
    $outcomeStmt = $pdo->query($outcomeQuery);
    $outcomeData = $outcomeStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $outcomeLabels = [];
    $outcomeCounts = [];
    
    foreach ($outcomeData as $record) {
        $outcomeLabels[] = ucfirst($record['outcome']);
        $outcomeCounts[] = $record['count'];
    }
    
    return [
        'records' => $records,
        'outcomeChart' => [
            'labels' => $outcomeLabels,
            'counts' => $outcomeCounts
        ]
    ];
}

/**
 * Get Deceased Report Data
 */
function getDeceasedData($pdo, $startDate = null, $endDate = null) {
    // Build date filter clause
    $dateFilterClause = '';
    if ($startDate && $endDate) {
        $dateFilterClause = " AND d.date_of_death BETWEEN '$startDate' AND '$endDate'";
    }
    
    // Get deceased records
    $query = "
        SELECT d.*, a.animal_id as animal_code, a.species, a.breed 
        FROM deceased_animals d
        LEFT JOIN animals a ON d.animal_id = a.id
        WHERE 1=1 $dateFilterClause
        ORDER BY d.date_of_death DESC
        LIMIT 1000
    ";
    
    $stmt = $pdo->query($query);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get cause counts
    $causeQuery = "
        SELECT cause, COUNT(*) as count
        FROM deceased_animals
        WHERE 1=1 " . str_replace('d.date_of_death', 'date_of_death', $dateFilterClause) . "
        GROUP BY cause
    ";
    
    $causeStmt = $pdo->query($causeQuery);
    $causeData = $causeStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $causeLabels = [];
    $causeCounts = [];
    
    foreach ($causeData as $record) {
        $causeLabels[] = ucfirst($record['cause']);
        $causeCounts[] = $record['count'];
    }
    
    return [
        'records' => $records,
        'causeChart' => [
            'labels' => $causeLabels,
            'counts' => $causeCounts
        ]
    ];
}

/**
 * Get Vaccination Report Data
 */
function getVaccinationData($pdo, $startDate = null, $endDate = null) {
    // Build date filter clause
    $dateFilterClause = '';
    if ($startDate && $endDate) {
        $dateFilterClause = " AND v.date BETWEEN '$startDate' AND '$endDate'";
    }
    
    // Get vaccination records
    $query = "
        SELECT v.*, a.animal_id as animal_code, a.species, a.breed 
        FROM vaccinations v
        JOIN animals a ON v.animal_id = a.id
        WHERE 1=1 $dateFilterClause
        ORDER BY v.date DESC
        LIMIT 1000
    ";
    
    $stmt = $pdo->query($query);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get vaccination type counts
    $typeQuery = "
        SELECT type, COUNT(*) as count
        FROM vaccinations
        WHERE 1=1 " . str_replace('v.date', 'date', $dateFilterClause) . "
        GROUP BY type
    ";
    
    $typeStmt = $pdo->query($typeQuery);
    $typeData = $typeStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $typeLabels = [];
    $typeCounts = [];
    
    foreach ($typeData as $record) {
        $typeLabels[] = ucfirst($record['type']);
        $typeCounts[] = $record['count'];
    }
    
    // Get upcoming vaccinations
    $upcomingQuery = "
        SELECT COUNT(*) as count 
        FROM vaccinations v
        JOIN animals a ON v.animal_id = a.id
        WHERE v.next_due <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
        AND v.next_due >= CURRENT_DATE
    ";
    
    $upcomingCount = $pdo->query($upcomingQuery)->fetchColumn();
    
    return [
        'records' => $records,
        'typeChart' => [
            'labels' => $typeLabels,
            'counts' => $typeCounts
        ],
        'upcomingCount' => $upcomingCount
    ];
}

/**
 * Helper function to build date filter clause
 */
function getDateFilterClause($startDate, $endDate) {
    if ($startDate && $endDate) {
        return " AND date BETWEEN '$startDate' AND '$endDate'";
    }
    return '';
}
?>