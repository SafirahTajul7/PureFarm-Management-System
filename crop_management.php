<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Fetch summary statistics
try {
    // Total active crops - Fix the query to correctly count active crops
    $total_crops = $pdo->query("SELECT COUNT(*) FROM crops WHERE status = 'active'")->fetchColumn();
    
    // Crops requiring attention - Fix query to get correct count
    $attention_required = $pdo->query("
        SELECT COUNT(*) 
        FROM crops 
        WHERE next_action_date <= DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
        AND status = 'active'
    ")->fetchColumn();
    
    // Active pest/disease issues - Fix query to get correct count
    $pest_issues = $pdo->query("
        SELECT COUNT(*) 
        FROM crop_issues 
        WHERE resolved = 0
    ")->fetchColumn();
    
    // Upcoming harvests - Fix query to get correct count
    $upcoming_harvests = $pdo->query("
        SELECT COUNT(*) 
        FROM crops 
        WHERE expected_harvest_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
        AND status = 'active'
    ")->fetchColumn();

} catch(PDOException $e) {
    error_log("Error fetching summary data: " . $e->getMessage());
    // Set default values in case of error
    $total_crops = 0;
    $attention_required = 0;
    $pest_issues = 0;
    $upcoming_harvests = 0;
}

$pageTitle = 'Crop Management';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-seedling"></i> Crop Management</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='add_crop.php'">
                <i class="fas fa-plus"></i> Add New Crop
            </button>
            <button class="btn btn-primary" onclick="location.href='crop_reports.php'">
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
    
    /* Card Themes - matching animal management colors */
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
    
    /* Card Themes - matching animal management colors */
    .crop-records { border-top: 4px solid #3498db; }
    .resource-management { border-top: 4px solid #2ecc71; }
    .monitoring-metrics { border-top: 4px solid #f39c12; }
    .analysis-planning { border-top: 4px solid #9b59b6; }

    /* Header Icons Colors */
    .crop-records h3 i { color: #3498db; }
    .resource-management h3 i { color: #2ecc71; }
    .monitoring-metrics h3 i { color: #f39c12; }
    .analysis-planning h3 i { color: #9b59b6; }

    /* Card Hover Effects */
    .crop-records:hover { box-shadow: 0 6px 12px rgba(52, 152, 219, 0.2); }
    .resource-management:hover { box-shadow: 0 6px 12px rgba(46, 204, 113, 0.2); }
    .monitoring-metrics:hover { box-shadow: 0 6px 12px rgba(243, 156, 18, 0.2); }
    .analysis-planning:hover { box-shadow: 0 6px 12px rgba(155, 89, 182, 0.2); }

    /* Theme-specific hover backgrounds */
    .crop-records .menu-item:hover { background: #3498db; }
    .resource-management .menu-item:hover { background: #2ecc71; }
    .monitoring-metrics .menu-item:hover { background: #f39c12; }
    .analysis-planning .menu-item:hover { background: #9b59b6; }
    </style>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon bg-blue">
                <i class="fas fa-leaf"></i>
            </div>
            <div class="summary-details">
                <h3>Total Active Crops</h3>
                <p class="summary-count"><?php echo number_format($total_crops); ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-orange">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="summary-details">
                <h3>Needs Attention</h3>
                <p class="summary-count"><?php echo number_format($attention_required); ?></p>
                <span class="summary-subtitle">Next 7 days</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-red">
                <i class="fas fa-bug"></i>
            </div>
            <div class="summary-details">
                <h3>Pest/Disease Issues</h3>
                <p class="summary-count"><?php echo number_format($pest_issues); ?></p>
                <span class="summary-subtitle">Active cases</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-green">
                <i class="fas fa-tractor"></i>
            </div>
            <div class="summary-details">
                <h3>Upcoming Harvests</h3>
                <p class="summary-count"><?php echo number_format($upcoming_harvests); ?></p>
                <span class="summary-subtitle">Next 30 days</span>
            </div>
        </div>
    </div>

    <!-- Feature Grid -->
    <div class="features-grid">
        <!-- Crop Records - Blue Theme -->
        <div class="feature-card crop-records">
            <h3><i class="fas fa-database"></i> Crop Records</h3>
            <ul>
                <li onclick="location.href='crop_list.php'">
                    <div class="menu-item">
                        <i class="fas fa-list-ul"></i>
                        <div class="menu-content">
                            <span class="menu-title">Crop List</span>
                            <span class="menu-desc">View all crops and their status</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='field_management.php'">
                    <div class="menu-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="menu-content">
                            <span class="menu-title">Field Management</span>
                            <span class="menu-desc">Field details and crop rotation</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='planting_records.php'">
                    <div class="menu-item">
                        <i class="fas fa-clock"></i>
                        <div class="menu-content">
                            <span class="menu-title">Planting Records</span>
                            <span class="menu-desc">Planting dates and growth stages</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Resource Management - Green Theme -->
        <div class="feature-card resource-management">
            <h3><i class="fas fa-fill-drip"></i> Resource Management</h3>
            <ul>
                <li onclick="location.href='irrigation_management.php'">
                    <div class="menu-item">
                        <i class="fas fa-tint"></i>
                        <div class="menu-content">
                            <span class="menu-title">Irrigation Management</span>
                            <span class="menu-desc">Water usage and schedules</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='fertilizer_management.php'">
                    <div class="menu-item">
                        <i class="fas fa-flask"></i>
                        <div class="menu-content">
                            <span class="menu-title">Fertilizer Management</span>
                            <span class="menu-desc">Application schedules and usage</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='pesticide_management.php'">
                    <div class="menu-item">
                        <i class="fas fa-spray-can"></i>
                        <div class="menu-content">
                            <span class="menu-title">Pesticide Management</span>
                            <span class="menu-desc">Application and tracking</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Monitoring - Orange Theme -->
        <div class="feature-card monitoring-metrics">
            <h3><i class="fas fa-chart-line"></i> Monitoring</h3>
            <ul>
                <li onclick="location.href='environmental_monitoring.php'">
                    <div class="menu-item">
                        <i class="fas fa-cloud-sun"></i>
                        <div class="menu-content">
                            <span class="menu-title">Environmental Monitoring</span>
                            <span class="menu-desc">Weather and soil conditions</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='growth_tracking.php'">
                    <div class="menu-item">
                        <i class="fas fa-chart-bar"></i>
                        <div class="menu-content">
                            <span class="menu-title">Growth Tracking</span>
                            <span class="menu-desc">Crop development stages</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Analysis & Planning - Purple Theme -->
        <div class="feature-card analysis-planning">
            <h3><i class="fas fa-brain"></i> Analysis & Planning</h3>
            <ul>
                <li onclick="location.href='crop_performance.php'">
                    <div class="menu-item">
                        <i class="fas fa-chart-pie"></i>
                        <div class="menu-content">
                            <span class="menu-title">Performance Analysis</span>
                            <span class="menu-desc">Yield and quality metrics</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='financial_analysis.php'">
                    <div class="menu-item">
                        <i class="fas fa-dollar-sign"></i>
                        <div class="menu-content">
                            <span class="menu-title">Financial Analysis</span>
                            <span class="menu-desc">Costs and revenue tracking</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='harvest_planning.php'">
                    <div class="menu-item">
                        <i class="fas fa-calendar-alt"></i>
                        <div class="menu-content">
                            <span class="menu-title">Harvest Planning</span>
                            <span class="menu-desc">Schedule and resource allocation</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>

<style>
   .main-content {
    padding-bottom: 60px; /* Add space for footer */
    min-height: calc(100vh - 60px); /* Ensure content takes up full height minus footer */
}


/* Additional spacing for the features grid */
.features-grid {
    margin-bottom: 70px; /* Add extra space before footer */
}

/* Prevent content from being hidden behind footer */
body {
    padding-bottom: 60px;
} 
</style>

<?php include 'includes/footer.php'; ?>