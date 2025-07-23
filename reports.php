<?php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

// Fetch summary statistics for each module
try {
    // Animal Management Statistics
    $total_animals = $pdo->query("SELECT COUNT(*) FROM animals WHERE id NOT IN (SELECT COALESCE(animal_id, 0) FROM deceased_animals)")->fetchColumn();
    $health_issues = $pdo->query("
        SELECT COUNT(*) 
        FROM health_records 
        WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY) 
        AND `condition` != 'healthy'
    ")->fetchColumn();

    // Crop Management Statistics
    $active_crops = $pdo->query("SELECT COUNT(*) FROM crop_details WHERE status = 'active'")->fetchColumn();
    $pending_harvests = $pdo->query("
        SELECT COUNT(*) 
        FROM crop_details 
        WHERE harvest_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
    ")->fetchColumn();

    // Inventory Statistics
    $low_stock_items = $pdo->query("
        SELECT COUNT(*) 
        FROM inventory 
        WHERE quantity <= reorder_level
    ")->fetchColumn();
    $expiring_items = $pdo->query("
        SELECT COUNT(*) 
        FROM inventory 
        WHERE expiry_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
    ")->fetchColumn();

    // Staff Management Statistics
    $total_staff = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn();
    $pending_tasks = $pdo->query("
        SELECT COUNT(*) 
        FROM tasks 
        WHERE status = 'pending'
    ")->fetchColumn();

} catch(PDOException $e) {
    error_log("Error fetching report data: " . $e->getMessage());
    // Set default values in case of error
    $total_animals = $health_issues = $active_crops = $pending_harvests = 
    $low_stock_items = $expiring_items = $total_staff = $pending_tasks = 0;
}

$pageTitle = 'Reports Dashboard';
include 'includes/header.php';
?>

<div class="main-content" style="min-height: calc(100vh - 80px); padding-bottom: 80px;">
    <div class="page-header">
        <h2><i class="fas fa-chart-bar"></i> Reports Dashboard</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button class="btn btn-primary" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
        </div>
    </div>

    <!-- Module Statistics -->
    <div class="features-grid">
        <!-- Animal Management Stats -->
        <div class="feature-card animal-records">
            <h3><i class="fas fa-paw"></i> Animal Management</h3>
            <div class="stats-container">
                <div class="stat-item">
                    <span class="stat-label">Total Animals</span>
                    <span class="stat-value"><?php echo $total_animals; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Health Issues (7 days)</span>
                    <span class="stat-value"><?php echo $health_issues; ?></span>
                </div>
                <div class="stat-chart">
                    <!-- Add chart here using Chart.js -->
                    <canvas id="animalChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Crop Management Stats -->
        <div class="feature-card health-management">
            <h3><i class="fas fa-seedling"></i> Crop Management</h3>
            <div class="stats-container">
                <div class="stat-item">
                    <span class="stat-label">Active Crops</span>
                    <span class="stat-value"><?php echo $active_crops; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Pending Harvests (30 days)</span>
                    <span class="stat-value"><?php echo $pending_harvests; ?></span>
                </div>
                <div class="stat-chart">
                    <canvas id="cropChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Inventory Stats -->
        <div class="feature-card performance-metrics">
            <h3><i class="fas fa-box"></i> Inventory</h3>
            <div class="stats-container">
                <div class="stat-item">
                    <span class="stat-label">Low Stock Items</span>
                    <span class="stat-value"><?php echo $low_stock_items; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Expiring Items (30 days)</span>
                    <span class="stat-value"><?php echo $expiring_items; ?></span>
                </div>
                <div class="stat-chart">
                    <canvas id="inventoryChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Staff Management Stats -->
        <div class="feature-card resource-planning">
            <h3><i class="fas fa-users"></i> Staff Management</h3>
            <div class="stats-container">
                <div class="stat-item">
                    <span class="stat-label">Total Active Staff</span>
                    <span class="stat-value"><?php echo $total_staff; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Pending Tasks</span>
                    <span class="stat-value"><?php echo $pending_tasks; ?></span>
                </div>
                <div class="stat-chart">
                    <canvas id="staffChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Add necessary CSS -->
    <style>
        .stats-container {
            padding: 15px;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .stat-label {
            color: #666;
        }
        .stat-value {
            font-weight: bold;
            color: #333;
        }
        .stat-chart {
            height: 200px;
            margin-top: 20px;
        }
    </style>

    <!-- Add Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Initialize charts
        function initCharts() {
            // Animal Management Chart
            new Chart(document.getElementById('animalChart'), {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Animal Health Trends',
                        data: [65, 59, 80, 81, 56, 55],
                        borderColor: '#3498db'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Similar charts for other modules...
        }

        // Export to Excel function
        function exportToExcel() {
            // Implementation for Excel export
            alert('Exporting to Excel...');
        }

        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', initCharts);
    </script>
</div>

<?php include 'includes/footer.php'; ?>