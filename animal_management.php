<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

try {
    $test_query = $pdo->query("SELECT 1");
    echo "<!-- Database connection OK -->";
} catch(PDOException $e) {
    echo "<!-- Database connection error: " . $e->getMessage() . " -->";
}

// Fetch summary statistics
try {
    // Total animals
    $total_animals = $pdo->query("SELECT COUNT(*) FROM animals WHERE id NOT IN (SELECT COALESCE(animal_id, 0) FROM deceased_animals)")->fetchColumn();
    
    // Animals requiring vaccination
    $vaccination_due = $pdo->query("
        SELECT COUNT(DISTINCT a.id) 
        FROM animals a 
        LEFT JOIN vaccinations v ON a.id = v.animal_id 
        WHERE v.next_due <= DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY) 
        OR v.id IS NULL
    ")->fetchColumn();
    
    // Recent health issues (last 7 days)
    $health_issues = $pdo->query("
        SELECT COUNT(*) 
        FROM health_records 
        WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY) 
        AND `condition` != 'healthy'
    ")->fetchColumn();
    
    // Upcoming breeding - using the correct 'date' column
    $upcoming_breeding = $pdo->query("
        SELECT COUNT(*) 
        FROM breeding_history 
        WHERE date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
    ")->fetchColumn();

} catch(PDOException $e) {
    error_log("Error fetching summary data: " . $e->getMessage());
    // Set default values in case of error
    $total_animals = 0;
    $vaccination_due = 0;
    $health_issues = 0;
    $upcoming_breeding = 0;
}

$pageTitle = 'Animal Management';
include 'includes/header.php';

// Debug statements
echo "<!-- Debug: total_animals = " . $total_animals . " -->";
echo "<!-- Debug: vaccination_due = " . $vaccination_due . " -->";
echo "<!-- Debug: health_issues = " . $health_issues . " -->";
echo "<!-- Debug: upcoming_breeding = " . $upcoming_breeding . " -->";
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-paw"></i> Animal Management</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='addanimal.php'">
                <i class="fas fa-plus"></i> Add New Animal
            </button>
            <button class="btn btn-primary" onclick="location.href='animal_reports.php'">
                <i class="fas fa-chart-bar"></i> View Reports
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <style>
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 25px;
    }
    .summary-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 20px;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
    }
    .summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .summary-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
    }
    .summary-icon i {
        font-size: 24px;
        color: white;
    }
    .summary-details {
        flex: 1;
    }
    .summary-details h3 {
        font-size: 16px;
        margin: 0 0 5px 0;
        color: #555;
    }
    .summary-count {
        font-size: 28px;
        font-weight: bold;
        margin: 0;
        line-height: 1.2;
    }
    .summary-subtitle {
        font-size: 12px;
        color: #888;
    }
    .bg-blue { background: #3498db !important; }
    .bg-orange { background: #f39c12 !important; }
    .bg-red { background: #e74c3c !important; }
    .bg-green { background: #2ecc71 !important; }
    
    /* Card Themes - matching crop management style */
    .feature-card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden;
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    .feature-card h3 {
        padding: 20px 20px 10px;
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }
    .feature-card ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .feature-card li {
        cursor: pointer;
        transition: background 0.3s ease;
    }
    .menu-item {
        display: flex;
        padding: 15px 20px;
        border-top: 1px solid #f0f0f0;
        align-items: center;
    }
    .menu-item i {
        margin-right: 15px;
        font-size: 18px;
        width: 20px;
        text-align: center;
    }
    .menu-content {
        flex: 1;
    }
    .menu-title {
        display: block;
        font-weight: 500;
        margin-bottom: 3px;
    }
    .menu-desc {
        display: block;
        font-size: 12px;
        color: #777;
    }
    .menu-item:hover {
        background: #f7f7f7;
        color: white;
    }
    .menu-item:hover .menu-desc {
        color: rgba(255,255,255,0.8);
    }
    .features-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    
    /* Card Themes - matching crop management colors */
    .animal-records { border-top: 4px solid #3498db; }
    .health-management { border-top: 4px solid #2ecc71; }
    .performance-metrics { border-top: 4px solid #f39c12; }
    .resource-planning { border-top: 4px solid #9b59b6; }

    /* Header Icons Colors */
    .animal-records h3 i { color: #3498db; }
    .health-management h3 i { color: #2ecc71; }
    .performance-metrics h3 i { color: #f39c12; }
    .resource-planning h3 i { color: #9b59b6; }

    /* Card Hover Effects */
    .animal-records:hover { box-shadow: 0 6px 12px rgba(52, 152, 219, 0.2); }
    .health-management:hover { box-shadow: 0 6px 12px rgba(46, 204, 113, 0.2); }
    .performance-metrics:hover { box-shadow: 0 6px 12px rgba(243, 156, 18, 0.2); }
    .resource-planning:hover { box-shadow: 0 6px 12px rgba(155, 89, 182, 0.2); }

    /* Theme-specific hover backgrounds */
    .animal-records .menu-item:hover { background: #3498db; }
    .health-management .menu-item:hover { background: #2ecc71; }
    .performance-metrics .menu-item:hover { background: #f39c12; }
    .resource-planning .menu-item:hover { background: #9b59b6; }

    /* Additional spacing and footer handling */
    .main-content {
        padding-bottom: 60px;
        min-height: calc(100vh - 60px);
    }
    .features-grid {
        margin-bottom: 70px;
    }
    body {
        padding-bottom: 60px;
    }
    </style>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon bg-blue">
                <i class="fas fa-dog"></i>
            </div>
            <div class="summary-details">
                <h3>Total Animals</h3>
                <p class="summary-count"><?php echo number_format($total_animals); ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-orange">
                <i class="fas fa-syringe"></i>
            </div>
            <div class="summary-details">
                <h3>Vaccinations Due</h3>
                <p class="summary-count"><?php echo number_format($vaccination_due); ?></p>
                <span class="summary-subtitle">Next 7 days</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-red">
                <i class="fas fa-heartbeat"></i>
            </div>
            <div class="summary-details">
                <h3>Health Issues</h3>
                <p class="summary-count"><?php echo number_format($health_issues); ?></p>
                <span class="summary-subtitle">Last 7 days</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-green">
                <i class="fas fa-calendar"></i>
            </div>
            <div class="summary-details">
                <h3>Upcoming Breeding</h3>
                <p class="summary-count"><?php echo number_format($upcoming_breeding); ?></p>
                <span class="summary-subtitle">Next 30 days</span>
            </div>
        </div>
    </div>

    <!-- Feature Grid -->
    <div class="features-grid">
        <!-- Animal Records - Blue Theme -->
        <div class="feature-card animal-records">
            <h3><i class="fas fa-database"></i> Animal Records</h3>
            <ul>
                <li onclick="location.href='animal_records.php'">
                    <div class="menu-item">
                        <i class="fas fa-list-ul"></i>
                        <div class="menu-content">
                            <span class="menu-title">Animal List</span>
                            <span class="menu-desc">View all animals and their status</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='animals_lifecycle.php'">
                    <div class="menu-item">
                        <i class="fas fa-circle-notch"></i>
                        <div class="menu-content">
                            <span class="menu-title">Lifecycle Records</span>
                            <span class="menu-desc">Breeding, birth, and lifecycle tracking</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Health Management - Green Theme -->
        <div class="feature-card health-management">
            <h3><i class="fas fa-heartbeat"></i> Health Management</h3>
            <ul>
                <li onclick="location.href='health_records.php'">
                    <div class="menu-item">
                        <i class="fas fa-notes-medical"></i>
                        <div class="menu-content">
                            <span class="menu-title">Health & Medical Records</span>
                            <span class="menu-desc">Includes vaccinations, treatments, and checkups</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='health_schedules.php'">
                    <div class="menu-item">
                        <i class="fas fa-calendar-alt"></i>
                        <div class="menu-content">
                            <span class="menu-title">Health Schedules</span>
                            <span class="menu-desc">Vaccination schedules and medical appointments</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='health_analytics.php'">
                    <div class="menu-item">
                        <i class="fas fa-chart-line"></i>
                        <div class="menu-content">
                            <span class="menu-title">Health Analytics</span>
                            <span class="menu-desc">Health trends and reporting</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Performance Metrics - Orange Theme -->
        <div class="feature-card performance-metrics">
            <h3><i class="fas fa-chart-bar"></i> Performance Metrics</h3>
            <ul>
                <li onclick="location.href='monitoring.php'">
                    <div class="menu-item">
                        <i class="fas fa-weight"></i>
                        <div class="menu-content">
                            <span class="menu-title">Growth Monitoring</span>
                            <span class="menu-desc">Weight, health metrics, and milestones</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='performance_analytics.php'">
                    <div class="menu-item">
                        <i class="fas fa-chart-line"></i>
                        <div class="menu-content">
                            <span class="menu-title">Performance Analytics</span>
                            <span class="menu-desc">Trends and comparative analysis</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Resource Planning - Purple Theme -->
        <div class="feature-card resource-planning">
            <h3><i class="fas fa-cubes"></i> Resource Planning</h3>
            <ul>
                <li onclick="location.href='feeding_management.php'">
                    <div class="menu-item">
                        <i class="fas fa-utensils"></i>
                        <div class="menu-content">
                            <span class="menu-title">Feeding Management</span>
                            <span class="menu-desc">Diet plans and nutrition tracking</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='housing_location.php'">
                    <div class="menu-item">
                        <i class="fas fa-home"></i>
                        <div class="menu-content">
                            <span class="menu-title">Housing & Location</span>
                            <span class="menu-desc">Facility management and assignments</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='incident_management.php'">
                    <div class="menu-item">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="menu-content">
                            <span class="menu-title">Incident Management</span>
                            <span class="menu-desc">Issue tracking and resolution</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>