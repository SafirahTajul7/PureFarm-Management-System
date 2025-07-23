<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Handle export request
if (isset($_GET['export']) && isset($_GET['report_type'])) {
    $reportType = $_GET['report_type'];
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="animal_report_' . $reportType . '_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Generate the appropriate report based on the type
    switch ($reportType) {
        case 'overview':
            // Export overview report
            fputcsv($output, ['Report Type', 'Total Count', 'Last Updated']);
            
            // Total animals
            $total_animals = $pdo->query("SELECT COUNT(*) FROM animals WHERE id NOT IN (SELECT COALESCE(animal_id, 0) FROM deceased_animals)")->fetchColumn();
            fputcsv($output, ['Total Live Animals', $total_animals, date('Y-m-d H:i:s')]);
            
            // Animals by species
            $species_counts = $pdo->query("SELECT species, COUNT(*) as count FROM animals WHERE id NOT IN (SELECT COALESCE(animal_id, 0) FROM deceased_animals) GROUP BY species")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($species_counts as $species) {
                fputcsv($output, ['Animals (' . $species['species'] . ')', $species['count'], date('Y-m-d H:i:s')]);
            }
            
            // Deceased animals
            $deceased_count = $pdo->query("SELECT COUNT(*) FROM deceased_animals")->fetchColumn();
            fputcsv($output, ['Deceased Animals', $deceased_count, date('Y-m-d H:i:s')]);
            
            // Health issues
            $health_issues = $pdo->query("SELECT COUNT(*) FROM health_records WHERE `condition` != 'healthy' AND date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)")->fetchColumn();
            fputcsv($output, ['Health Issues (Last 30 Days)', $health_issues, date('Y-m-d H:i:s')]);
            break;
            
        case 'health':
            // Export health report
            fputcsv($output, ['Animal ID', 'Species', 'Breed', 'Condition', 'Treatment', 'Date', 'Veterinarian']);
            
            $health_records = $pdo->query("
                SELECT h.*, a.animal_id as animal_code, a.species, a.breed 
                FROM health_records h 
                JOIN animals a ON h.animal_id = a.id 
                ORDER BY h.date DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($health_records as $record) {
                fputcsv($output, [
                    $record['animal_code'],
                    $record['species'],
                    $record['breed'],
                    $record['condition'],
                    $record['treatment'],
                    $record['date'],
                    $record['vet_name']
                ]);
            }
            break;
            
        case 'breeding':
            // Export breeding report
            fputcsv($output, ['Female ID', 'Female Species', 'Male ID', 'Male Species', 'Breeding Date', 'Outcome', 'Notes']);
            
            $breeding_records = $pdo->query("
                SELECT 
                    bh.*,
                    a1.species as female_species,
                    a2.species as male_species
                FROM breeding_history bh
                LEFT JOIN animals a1 ON bh.animal_id = a1.animal_id
                LEFT JOIN animals a2 ON bh.partner_id = a2.animal_id
                ORDER BY bh.date DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($breeding_records as $record) {
                fputcsv($output, [
                    $record['animal_id'],
                    $record['female_species'],
                    $record['partner_id'],
                    $record['male_species'],
                    $record['date'],
                    $record['outcome'],
                    $record['notes']
                ]);
            }
            break;
            
        case 'deceased':
            // Export deceased report
            fputcsv($output, ['Animal ID', 'Species', 'Breed', 'Date of Death', 'Cause', 'Notes']);
            
            $deceased_records = $pdo->query("
                SELECT 
                    d.*,
                    a.species,
                    a.breed 
                FROM deceased_animals d
                LEFT JOIN animals a ON d.animal_id = a.animal_id
                ORDER BY d.date_of_death DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($deceased_records as $record) {
                fputcsv($output, [
                    $record['animal_id'],
                    $record['species'],
                    $record['breed'],
                    $record['date_of_death'],
                    $record['cause'],
                    $record['notes']
                ]);
            }
            break;
            
        case 'vaccination':
            // Export vaccination report
            fputcsv($output, ['Animal ID', 'Species', 'Breed', 'Vaccination Type', 'Date', 'Next Due', 'Administered By']);
            
            $vaccination_records = $pdo->query("
                SELECT v.*, a.animal_id as animal_code, a.species, a.breed 
                FROM vaccinations v
                JOIN animals a ON v.animal_id = a.id
                ORDER BY v.date DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($vaccination_records as $record) {
                fputcsv($output, [
                    $record['animal_code'],
                    $record['species'],
                    $record['breed'],
                    $record['type'],
                    $record['date'],
                    $record['next_due'],
                    $record['administered_by']
                ]);
            }
            break;
    }
    
    fclose($output);
    exit;
}

// Fetch summary statistics
try {
    // Total animals
    $total_animals = $pdo->query("SELECT COUNT(*) FROM animals WHERE id NOT IN (SELECT COALESCE(animal_id, 0) FROM deceased_animals)")->fetchColumn();
    
    // Animals by species
    $species_counts = $pdo->query("
        SELECT species, COUNT(*) as count 
        FROM animals 
        WHERE id NOT IN (SELECT COALESCE(animal_id, 0) FROM deceased_animals) 
        GROUP BY species
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Animals requiring vaccination
    $vaccination_due = $pdo->query("
        SELECT COUNT(DISTINCT a.id) 
        FROM animals a 
        LEFT JOIN vaccinations v ON a.id = v.animal_id 
        WHERE v.next_due <= DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY) 
        OR v.id IS NULL
    ")->fetchColumn();

    // Add detailed vaccination breakdown
    $vaccination_due_detailed = $pdo->query("
        SELECT 
            COUNT(CASE WHEN DATEDIFF(v.next_due, CURRENT_DATE) <= 1 THEN 1 END) as due_24h,
            COUNT(CASE WHEN DATEDIFF(v.next_due, CURRENT_DATE) BETWEEN 2 AND 3 THEN 1 END) as due_3days,
            COUNT(CASE WHEN DATEDIFF(v.next_due, CURRENT_DATE) BETWEEN 4 AND 7 THEN 1 END) as due_7days,
            COUNT(CASE WHEN v.next_due < CURRENT_DATE THEN 1 END) as overdue
        FROM vaccinations v
        JOIN animals a ON v.animal_id = a.id
        WHERE v.next_due <= DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
        OR v.next_due < CURRENT_DATE
    ")->fetch(PDO::FETCH_ASSOC);

    // Add vaccination type breakdown
    $vaccination_types = $pdo->query("
        SELECT 
            v.type,
            COUNT(*) as count
        FROM vaccinations v
        JOIN animals a ON v.animal_id = a.id
        WHERE v.next_due <= DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
        GROUP BY v.type
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Add vaccination by species
    $vaccination_by_species = $pdo->query("
        SELECT 
            a.species,
            COUNT(*) as count
        FROM vaccinations v
        JOIN animals a ON v.animal_id = a.id
        WHERE v.next_due <= DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
        GROUP BY a.species
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent health issues (last 30 days)
    $health_issues = $pdo->query("
        SELECT COUNT(*) 
        FROM health_records 
        WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) 
        AND `condition` != 'healthy'
    ")->fetchColumn();
    
    // Upcoming breeding - using the correct 'date' column
    $upcoming_breeding = $pdo->query("
        SELECT COUNT(*) 
        FROM breeding_history 
        WHERE date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
    ")->fetchColumn();
    
    // Deceased animals (last 6 months)
    $deceased_recent = $pdo->query("
        SELECT COUNT(*) 
        FROM deceased_animals 
        WHERE date_of_death >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
    ")->fetchColumn();
    
    // Total deceased
    $deceased_total = $pdo->query("SELECT COUNT(*) FROM deceased_animals")->fetchColumn();
    
    // Recent breeding records (success rate)
    $breeding_results = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN outcome = 'successful' THEN 1 ELSE 0 END) as successful
        FROM breeding_history
        WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
    ")->fetch(PDO::FETCH_ASSOC);
    
    $breeding_total = $breeding_results['total'] ?? 0;
    $breeding_success = $breeding_results['successful'] ?? 0;
    $breeding_success_rate = $breeding_total > 0 ? round(($breeding_success / $breeding_total) * 100, 2) : 0;
    
} catch(PDOException $e) {
    error_log("Error fetching report data: " . $e->getMessage());
    // Set default values in case of error
    $total_animals = 0;
    $species_counts = [];
    $vaccination_due = 0;
    $vaccination_due_detailed = ['due_24h' => 0, 'due_3days' => 0, 'due_7days' => 0, 'overdue' => 0];
    $health_issues = 0;
    $upcoming_breeding = 0;
    $deceased_recent = 0;
    $deceased_total = 0;
    $breeding_total = 0;
    $breeding_success = 0;
    $breeding_success_rate = 0;
}

$pageTitle = 'Animal Reports';
include 'includes/header.php';
?>

<style>
/* Enhanced styling for better UI */
.nav-tabs {
    border-bottom: 2px solid #dee2e6;
    margin-bottom: 20px;
}

.nav-tabs .nav-link {
    border: none;
    color: #495057;
    font-weight: 500;
    padding: 12px 20px;
    margin-right: 5px;
    border-radius: 5px 5px 0 0;
}

.nav-tabs .nav-link:hover {
    background-color: #f8f9fa;
    border-color: transparent;
}

.nav-tabs .nav-link.active {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
}

.summary-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    padding: 25px;
    color: white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card h5 {
    margin-bottom: 15px;
    font-size: 1.1rem;
    opacity: 0.9;
}

.stat-card .display-4 {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 10px;
}

.stat-card.bg-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card.bg-success {
    background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
}

.stat-card.bg-warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-card.bg-info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.chart-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.table-responsive {
    max-height: 500px;
    overflow-y: auto;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
    border-top: none;
    position: sticky;
    top: 0;
    z-index: 10;
}

.btn-group-sm .btn {
    margin-right: 5px;
}

.tab-content {
    min-height: 400px;
}

.no-data {
    text-align: center;
    padding: 50px;
    color: #6c757d;
}

.no-data i {
    font-size: 3rem;
    margin-bottom: 20px;
    opacity: 0.5;
}
</style>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-bar"></i> Animal Reports</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='animal_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Animal Management
            </button>
        </div>
    </div>

    <!-- Report Selection Tabs -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Select Report Type</h3>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs" id="reportTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="overview-tab" data-bs-toggle="tab" href="#overview" role="tab">
                        <i class="fas fa-chart-pie"></i> Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="health-tab" data-bs-toggle="tab" href="#health" role="tab">
                        <i class="fas fa-heartbeat"></i> Health
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="breeding-tab" data-bs-toggle="tab" href="#breeding" role="tab">
                        <i class="fas fa-baby"></i> Breeding
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="deceased-tab" data-bs-toggle="tab" href="#deceased" role="tab">
                        <i class="fas fa-dizzy"></i> Deceased
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="vaccination-tab" data-bs-toggle="tab" href="#vaccination" role="tab">
                        <i class="fas fa-syringe"></i> Vaccination
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Report Content -->
    <div class="tab-content" id="reportTabContent">
        <!-- Overview Report -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3>Animal Population Overview</h3>
                    <a href="?export=true&report_type=overview" class="btn btn-success">
                        <i class="fas fa-file-export"></i> Export CSV
                    </a>
                </div>
                <div class="card-body">
                    <!-- Summary Statistics Cards -->
                    <div class="summary-stats-grid">
                        <div class="stat-card bg-primary">
                            <h5 class="card-title">Total Live Animals</h5>
                            <div class="display-4"><?php echo number_format($total_animals); ?></div>
                            <small>Currently active animals</small>
                        </div>
                        
                        <div class="stat-card bg-warning">
                            <h5 class="card-title">Health Issues</h5>
                            <div class="display-4"><?php echo number_format($health_issues); ?></div>
                            <small>Last 30 days</small>
                        </div>
                        
                        <div class="stat-card bg-info">
                            <h5 class="card-title">Vaccinations Due</h5>
                            <div class="display-4"><?php echo number_format($vaccination_due); ?></div>
                            <small>Next 7 days</small>
                        </div>
                        
                        <div class="stat-card bg-success">
                            <h5 class="card-title">Breeding Success Rate</h5>
                            <div class="display-4"><?php echo $breeding_success_rate; ?>%</div>
                            <small>Last 6 months</small>
                        </div>
                    </div>
                    
                    <!-- Detailed Charts and Tables -->
                    <div class="chart-grid">
                        <!-- Species Distribution -->
                        <div class="card">
                            <div class="card-header">
                                <h5>Species Distribution</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($species_counts)): ?>
                                    <ul class="list-group">
                                        <?php foreach ($species_counts as $species): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($species['species']); ?>
                                                <span class="badge badge-primary badge-pill">
                                                    <?php echo $species['count']; ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <div class="no-data">
                                        <i class="fas fa-chart-pie"></i>
                                        <p>No species data available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Vaccination Details -->
                        <div class="card">
                            <div class="card-header">
                                <h5>Vaccination Status Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-center border-right">
                                            <h6 class="text-danger">Overdue</h6>
                                            <h4><?php echo $vaccination_due_detailed['overdue'] ?? 0; ?></h4>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h6 class="text-warning">Due Within 24h</h6>
                                            <h4><?php echo $vaccination_due_detailed['due_24h'] ?? 0; ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Health Report -->
        <div class="tab-pane fade" id="health" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3>Animal Health Report</h3>
                    <a href="?export=true&report_type=health" class="btn btn-success">
                        <i class="fas fa-file-export"></i> Export CSV
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Animal ID</th>
                                    <th>Species</th>
                                    <th>Date</th>
                                    <th>Condition</th>
                                    <th>Treatment</th>
                                    <th>Veterinarian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $health_records = $pdo->query("
                                        SELECT h.*, a.animal_id as animal_code, a.species 
                                        FROM health_records h 
                                        JOIN animals a ON h.animal_id = a.id 
                                        ORDER BY h.date DESC
                                        LIMIT 100
                                    ")->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (!empty($health_records)) {
                                        foreach ($health_records as $record) {
                                            echo '<tr>';
                                            echo '<td>' . htmlspecialchars($record['animal_code']) . '</td>';
                                            echo '<td>' . htmlspecialchars($record['species']) . '</td>';
                                            echo '<td>' . htmlspecialchars($record['date']) . '</td>';
                                            echo '<td>' . htmlspecialchars($record['condition']) . '</td>';
                                            echo '<td>' . htmlspecialchars($record['treatment']) . '</td>';
                                            echo '<td>' . htmlspecialchars($record['vet_name']) . '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="6" class="text-center">No health records found.</td></tr>';
                                    }
                                } catch(PDOException $e) {
                                    echo '<tr><td colspan="6" class="text-center text-danger">Error loading health records: ' . $e->getMessage() . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Breeding Report -->
        <div class="tab-pane fade" id="breeding" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3>Breeding Report</h3>
                    <a href="?export=true&report_type=breeding" class="btn btn-success">
                        <i class="fas fa-file-export"></i> Export CSV
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Female ID</th>
                                    <th>Female Species</th>
                                    <th>Male ID</th>
                                    <th>Male Species</th>
                                    <th>Breeding Date</th>
                                    <th>Outcome</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $breeding_records = $pdo->query("
                                        SELECT 
                                            bh.id,
                                            bh.date,
                                            bh.outcome,
                                            bh.notes,
                                            bh.animal_id as female_id,
                                            bh.partner_id as male_id,
                                            a1.species as female_species,
                                            a2.species as male_species
                                        FROM breeding_history bh
                                        LEFT JOIN animals a1 ON bh.animal_id = a1.animal_id
                                        LEFT JOIN animals a2 ON bh.partner_id = a2.animal_id
                                        ORDER BY bh.date DESC
                                        LIMIT 100
                                    ")->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (!empty($breeding_records)) {
                                        foreach ($breeding_records as $record) {
                                            echo '<tr>';
                                            echo '<td>' . htmlspecialchars($record['female_id'] ?? 'N/A') . '</td>';
                                            echo '<td>' . htmlspecialchars($record['female_species'] ?? 'N/A') . '</td>';
                                            echo '<td>' . htmlspecialchars($record['male_id'] ?? 'N/A') . '</td>';
                                            echo '<td>' . htmlspecialchars($record['male_species'] ?? 'N/A') . '</td>';
                                            echo '<td>' . htmlspecialchars($record['date'] ?? 'N/A') . '</td>';
                                            echo '<td>' . htmlspecialchars($record['outcome'] ?? 'N/A') . '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="6" class="text-center">No breeding records found.</td></tr>';
                                    }
                                } catch(PDOException $e) {
                                    echo '<tr><td colspan="6" class="text-center text-danger">Error loading breeding records: ' . $e->getMessage() . '</td></tr>';
                                }   
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Deceased Report -->
        <div class="tab-pane fade" id="deceased" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3>Deceased Animal Report</h3>
                    <a href="?export=true&report_type=deceased" class="btn btn-success">
                        <i class="fas fa-file-export"></i> Export CSV
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Animal ID</th>
                                    <th>Species</th>
                                    <th>Breed</th>
                                    <th>Date of Death</th>
                                    <th>Cause</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $deceased_records = $pdo->query("
                                        SELECT 
                                            d.id,
                                            d.date_of_death,
                                            d.cause,
                                            d.notes,
                                            d.animal_id,
                                            a.species,
                                            a.breed 
                                        FROM deceased_animals d
                                        LEFT JOIN animals a ON d.animal_id = a.animal_id
                                        ORDER BY d.date_of_death DESC
                                        LIMIT 100
                                    ")->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (!empty($deceased_records)) {
                                        foreach ($deceased_records as $record) {
                                            echo '<tr>';
                                            echo '<td>' . htmlspecialchars($record['animal_id'] ?? 'N/A') . '</td>';
                                            echo '<td>' . htmlspecialchars($record['species'] ?? 'N/A') . '</td>';
                                            echo '<td>' . htmlspecialchars($record['breed'] ?? 'N/A') . '</td>';
                                            echo '<td>' . htmlspecialchars($record['date_of_death'] ?? 'N/A') . '</td>';
                                            echo '<td>' . htmlspecialchars($record['cause'] ?? 'N/A') . '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="5" class="text-center">No deceased animal records found.</td></tr>';
                                    }
                                } catch(PDOException $e) {
                                    echo '<tr><td colspan="5" class="text-center text-danger">Error loading deceased records: ' . $e->getMessage() . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Vaccination Report -->
        <div class="tab-pane fade" id="vaccination" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3>Vaccination Report</h3>
                    <a href="?export=true&report_type=vaccination" class="btn btn-success">
                        <i class="fas fa-file-export"></i> Export CSV
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Animal ID</th>
                                    <th>Species</th>
                                    <th>Vaccination Type</th>
                                    <th>Date</th>
                                    <th>Next Due</th>
                                    <th>Administered By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $vaccination_records = $pdo->query("
                                        SELECT v.*, a.animal_id as animal_code, a.species 
                                        FROM vaccinations v
                                        JOIN animals a ON v.animal_id = a.id
                                        ORDER BY v.date DESC
                                        LIMIT 100
                                    ")->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (!empty($vaccination_records)) {
                                        foreach ($vaccination_records as $record) {
                                            $nextDueClass = '';
                                            if (strtotime($record['next_due']) < strtotime('+7 days')) {
                                                $nextDueClass = 'class="text-danger font-weight-bold"';
                                            }
                                            
                                            echo '<tr>';
                                            echo '<td>' . htmlspecialchars($record['animal_code']) . '</td>';
                                            echo '<td>' . htmlspecialchars($record['species']) . '</td>';
                                            echo '<td>' . htmlspecialchars($record['type']) . '</td>';
                                            echo '<td>' . htmlspecialchars($record['date']) . '</td>';
                                            echo '<td ' . $nextDueClass . '>' . htmlspecialchars($record['next_due']) . '</td>';
                                            echo '<td>' . htmlspecialchars($record['administered_by']) . '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="6" class="text-center">No vaccination records found.</td></tr>';
                                    }
                                } catch(PDOException $e) {
                                    echo '<tr><td colspan="6" class="text-center text-danger">Error loading vaccination records: ' . $e->getMessage() . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tabs if available
    if (typeof bootstrap !== 'undefined') {
        var triggerTabList = [].slice.call(document.querySelectorAll('#reportTabs a'));
        triggerTabList.forEach(function (triggerEl) {
            var tabTrigger = new bootstrap.Tab(triggerEl);
            triggerEl.addEventListener('click', function (event) {
                event.preventDefault();
                tabTrigger.show();
            });
        });
    } else {
        // Fallback for manual tab switching
        document.querySelectorAll('#reportTabs a').forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all tabs and content
                document.querySelectorAll('#reportTabs .nav-link').forEach(function(link) {
                    link.classList.remove('active');
                });
                document.querySelectorAll('.tab-pane').forEach(function(pane) {
                    pane.classList.remove('show', 'active');
                });
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Show corresponding content
                var targetId = this.getAttribute('href').substring(1);
                var targetPane = document.getElementById(targetId);
                if (targetPane) {
                    targetPane.classList.add('show', 'active');
                }
            });
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>