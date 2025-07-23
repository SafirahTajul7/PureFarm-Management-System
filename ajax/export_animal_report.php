<?php
/**
 * Ajax Handler for Animal Report Exports
 * 
 * This file handles AJAX requests for exporting animal reports in CSV or PDF formats
 */

require_once '../includes/auth.php';
auth()->checkAdmin();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check for required parameters
if (!isset($_POST['report_type']) || !isset($_POST['format'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

$reportType = $_POST['report_type'];
$format = $_POST['format'];
$startDate = isset($_POST['start_date']) ? $_POST['start_date'] : null;
$endDate = isset($_POST['end_date']) ? $_POST['end_date'] : null;

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

// Build date filter clause if dates are provided
$dateFilter = '';
if ($startDate && $endDate) {
    switch ($reportType) {
        case 'health':
            $dateFilter = " AND h.date BETWEEN '$startDate' AND '$endDate'";
            break;
        case 'breeding':
            $dateFilter = " AND bh.date BETWEEN '$startDate' AND '$endDate'";
            break;
        case 'deceased':
            $dateFilter = " AND d.date_of_death BETWEEN '$startDate' AND '$endDate'";
            break;
        case 'vaccination':
            $dateFilter = " AND v.date BETWEEN '$startDate' AND '$endDate'";
            break;
    }
}

try {
    // Generate filename for export
    $filename = 'animal_report_' . $reportType . '_' . date('Y-m-d') . '.' . ($format === 'pdf' ? 'pdf' : 'csv');
    
    // Generate the appropriate report based on the type
    switch ($reportType) {
        case 'overview':
            $data = generateOverviewReport($pdo);
            break;
        case 'health':
            $data = generateHealthReport($pdo, $dateFilter);
            break;
        case 'breeding':
            $data = generateBreedingReport($pdo, $dateFilter);
            break;
        case 'deceased':
            $data = generateDeceasedReport($pdo, $dateFilter);
            break;
        case 'vaccination':
            $data = generateVaccinationReport($pdo, $dateFilter);
            break;
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid report type'
            ]);
            exit;
    }
    
    // Process data based on format
    if ($format === 'csv') {
        $filePath = generateCSV($data, $filename, $reportType);
    } else if ($format === 'pdf') {
        $filePath = generatePDF($data, $filename, $reportType);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid export format'
        ]);
        exit;
    }
    
    // Return success with file details
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'path' => $filePath
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating report: ' . $e->getMessage()
    ]);
}

/**
 * Generate Overview Report
 * 
 * @param PDO $pdo Database connection
 * @return array Report data
 */
function generateOverviewReport($pdo) {
    $data = [
        'headers' => ['Report Type', 'Total Count', 'Last Updated'],
        'rows' => []
    ];
    
    // Total animals
    $total_animals = $pdo->query("SELECT COUNT(*) FROM animals WHERE id NOT IN (SELECT COALESCE(animal_id, 0) FROM deceased_animals)")->fetchColumn();
    $data['rows'][] = ['Total Live Animals', $total_animals, date('Y-m-d H:i:s')];
    
    // Animals by species
    $species_counts = $pdo->query("SELECT species, COUNT(*) as count FROM animals WHERE id NOT IN (SELECT COALESCE(animal_id, 0) FROM deceased_animals) GROUP BY species")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($species_counts as $species) {
        $data['rows'][] = ['Animals (' . $species['species'] . ')', $species['count'], date('Y-m-d H:i:s')];
    }
    
    // Deceased animals
    $deceased_count = $pdo->query("SELECT COUNT(*) FROM deceased_animals")->fetchColumn();
    $data['rows'][] = ['Deceased Animals', $deceased_count, date('Y-m-d H:i:s')];
    
    // Health issues
    $health_issues = $pdo->query("SELECT COUNT(*) FROM health_records WHERE `condition` != 'healthy' AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)")->fetchColumn();
    $data['rows'][] = ['Health Issues (Last 30 Days)', $health_issues, date('Y-m-d H:i:s')];
    
    return $data;
}

/**
 * Generate Health Report
 * 
 * @param PDO $pdo Database connection
 * @param string $dateFilter SQL date filter clause
 * @return array Report data
 */
function generateHealthReport($pdo, $dateFilter) {
    $data = [
        'headers' => ['Animal ID', 'Species', 'Breed', 'Condition', 'Treatment', 'Date', 'Veterinarian'],
        'rows' => []
    ];
    
    $query = "
        SELECT h.*, a.animal_id as animal_code, a.species, a.breed 
        FROM health_records h 
        JOIN animals a ON h.animal_id = a.id 
        WHERE 1=1 $dateFilter
        ORDER BY h.date DESC
    ";
    
    $health_records = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($health_records as $record) {
        $data['rows'][] = [
            $record['animal_code'],
            $record['species'],
            $record['breed'],
            $record['condition'],
            $record['treatment'],
            $record['date'],
            $record['vet_name']
        ];
    }
    
    return $data;
}

/**
 * Generate Breeding Report
 * 
 * @param PDO $pdo Database connection
 * @param string $dateFilter SQL date filter clause
 * @return array Report data
 */
function generateBreedingReport($pdo, $dateFilter) {
    $data = [
        'headers' => ['Female ID', 'Female Species', 'Male ID', 'Male Species', 'Breeding Date', 'Outcome', 'Notes'],
        'rows' => []
    ];
    
    $query = "
        SELECT bh.*, 
               a1.species as animal_species, a1.animal_id as female_code,
               a2.species as partner_species, a2.animal_id as male_code
        FROM breeding_history bh
        LEFT JOIN animals a1 ON bh.animal_id = a1.id
        LEFT JOIN animals a2 ON bh.partner_id = a2.id
        WHERE 1=1 $dateFilter
        ORDER BY bh.date DESC
    ";
    
    $breeding_records = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($breeding_records as $record) {
        $data['rows'][] = [
            $record['female_code'],
            $record['animal_species'],
            $record['male_code'],
            $record['partner_species'],
            $record['date'],
            $record['outcome'],
            $record['notes']
        ];
    }
    
    return $data;
}

/**
 * Generate Deceased Report
 * 
 * @param PDO $pdo Database connection
 * @param string $dateFilter SQL date filter clause
 * @return array Report data
 */
function generateDeceasedReport($pdo, $dateFilter) {
    $data = [
        'headers' => ['Animal ID', 'Species', 'Breed', 'Date of Death', 'Cause', 'Notes'],
        'rows' => []
    ];
    
    $query = "
        SELECT d.*, a.animal_id as animal_code, a.species, a.breed 
        FROM deceased_animals d
        LEFT JOIN animals a ON d.animal_id = a.id
        WHERE 1=1 $dateFilter
        ORDER BY d.date_of_death DESC
    ";
    
    $deceased_records = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($deceased_records as $record) {
        $data['rows'][] = [
            $record['animal_code'],
            $record['species'],
            $record['breed'],
            $record['date_of_death'],
            $record['cause'],
            $record['notes']
        ];
    }
    
    return $data;
}

/**
 * Generate Vaccination Report
 * 
 * @param PDO $pdo Database connection
 * @param string $dateFilter SQL date filter clause
 * @return array Report data
 */
function generateVaccinationReport($pdo, $dateFilter) {
    $data = [
        'headers' => ['Animal ID', 'Species', 'Breed', 'Vaccination Type', 'Date', 'Next Due', 'Administered By'],
        'rows' => []
    ];
    
    $query = "
        SELECT v.*, a.animal_id as animal_code, a.species, a.breed 
        FROM vaccinations v
        JOIN animals a ON v.animal_id = a.id
        WHERE 1=1 $dateFilter
        ORDER BY v.date DESC
    ";
    
    $vaccination_records = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($vaccination_records as $record) {
        $data['rows'][] = [
            $record['animal_code'],
            $record['species'],
            $record['breed'],
            $record['type'],
            $record['date'],
            $record['next_due'],
            $record['administered_by']
        ];
    }
    
    return $data;
}

/**
 * Generate CSV file
 * 
 * @param array $data Report data
 * @param string $filename Filename for the CSV
 * @param string $reportType Type of report
 * @return string Path to the generated file
 */
function generateCSV($data, $filename, $reportType) {
    // Create export directory if it doesn't exist
    $exportDir = '../exports';
    if (!file_exists($exportDir)) {
        mkdir($exportDir, 0755, true);
    }
    
    $filePath = $exportDir . '/' . $filename;
    
    // Create CSV file
    $file = fopen($filePath, 'w');
    
    // Add report title
    fputcsv($file, ['PureFarm Management System - ' . ucfirst($reportType) . ' Report']);
    fputcsv($file, ['Generated on: ' . date('Y-m-d H:i:s')]);
    fputcsv($file, []); // Empty line for spacing
    
    // Add headers
    fputcsv($file, $data['headers']);
    
    // Add rows
    foreach ($data['rows'] as $row) {
        fputcsv($file, $row);
    }
    
    fclose($file);
    
    return 'exports/' . $filename;
}

/**
 * Generate PDF file
 * 
 * @param array $data Report data
 * @param string $filename Filename for the PDF
 * @param string $reportType Type of report
 * @return string Path to the generated file
 */
function generatePDF($data, $filename, $reportType) {
    // This is a simplified example. In a real implementation,
    // you would use a PDF library like TCPDF, FPDF, or mPDF.
    
    // For now, we'll just create a simple HTML file that can be printed as PDF
    $exportDir = '../exports';
    if (!file_exists($exportDir)) {
        mkdir($exportDir, 0755, true);
    }
    
    $htmlFilePath = $exportDir . '/' . str_replace('.pdf', '.html', $filename);
    
    // Generate HTML content
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <title>PureFarm Management System - ' . ucfirst($reportType) . ' Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1, h2 { color: #4e73df; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #f8f9fc; padding: 10px; text-align: left; border-bottom: 1px solid #e3e6f0; }
            td { padding: 8px; border-bottom: 1px solid #e3e6f0; }
            tr:nth-child(even) { background-color: #f8f9fc; }
            .report-date { margin-bottom: 20px; color: #5a5c69; }
            @media print { body { margin: 0; } }
        </style>
    </head>
    <body>
        <h1>PureFarm Management System</h1>
        <h2>' . ucfirst($reportType) . ' Report</h2>
        <p class="report-date">Generated on: ' . date('Y-m-d H:i:s') . '</p>
        
        <table>
            <thead>
                <tr>';
    
    // Add headers
    foreach ($data['headers'] as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    
    $html .= '</tr>
            </thead>
            <tbody>';
    
    // Add rows
    foreach ($data['rows'] as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</tbody>
        </table>
        
        <script>
            window.onload = function() {
                // Automatically print when loaded
                window.print();
            };
        </script>
    </body>
    </html>';
    
    // Write HTML to file
    file_put_contents($htmlFilePath, $html);
    
    // Return path to HTML file (which will trigger print dialog to save as PDF)
    return 'exports/' . str_replace('.pdf', '.html', $filename);
}
?>