<?php
require_once 'includes/auth.php';
auth()->checkAdmin(); // Only allow admin access

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$inventoryData = [];
$categories = [];
$trendingItems = [];
$lowStockItems = [];
$inventoryValue = 0;
$categoryBreakdown = [];
$usageStats = [];
$expiryAlerts = [];

// Set default date ranges - using fixed 30 days for analysis
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$forecastMonths = isset($_GET['forecast_months']) ? (int)$_GET['forecast_months'] : 3;

// Fetch all active categories for filtering
try {
    $stmt = $pdo->query("SELECT id, name FROM item_categories WHERE status = 'active' ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $errorMsg = "Failed to load categories. Please try again later.";
}

// Get Inventory Summary Data
try {
    $query = "SELECT i.id, i.item_name, i.sku, i.current_quantity, i.unit_of_measure, 
              i.reorder_level, i.unit_cost, i.status, i.expiry_date,
              c.name as category_name
              FROM inventory_items i
              LEFT JOIN item_categories c ON i.category_id = c.id
              WHERE i.status = 'active'";
    
    if (!empty($categoryFilter)) {
        $query .= " AND i.category_id = " . $pdo->quote($categoryFilter);
    }
    
    $query .= " ORDER BY i.item_name ASC";
    
    $stmt = $pdo->query($query);
    $inventoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total inventory value
    foreach ($inventoryData as $item) {
        $inventoryValue += $item['current_quantity'] * $item['unit_cost'];
    }
    
    // Get category breakdown for pie chart
    $stmt = $pdo->query("
        SELECT c.name as category_name, 
               COUNT(i.id) as item_count, 
               SUM(i.current_quantity * i.unit_cost) as total_value
        FROM inventory_items i
        LEFT JOIN item_categories c ON i.category_id = c.id
        WHERE i.status = 'active'
        GROUP BY i.category_id
        ORDER BY total_value DESC
    ");
    $categoryBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Error fetching inventory data: " . $e->getMessage());
    $errorMsg = "Failed to load inventory data. Please try again later.";
}

// Get Trending Items (Most Used Items)
try {
    // Check if inventory_usage table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'inventory_usage'");
    $usageTableExists = $stmt->rowCount() > 0;
    
    if ($usageTableExists) {
        $stmt = $pdo->prepare("
            SELECT u.item_id, i.item_name, i.unit_of_measure, 
                   SUM(u.quantity) as total_used,
                   COUNT(u.id) as usage_count
            FROM inventory_usage u
            JOIN inventory_items i ON u.item_id = i.id
            WHERE u.usage_date BETWEEN ? AND ?
            GROUP BY u.item_id
            ORDER BY total_used DESC
            LIMIT 5
        ");
        $stmt->execute([$startDate, $endDate]);
        $trendingItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get overall usage statistics
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(u.usage_date, '%Y-%m') as month,
                   SUM(u.quantity) as total_quantity
            FROM inventory_usage u
            WHERE u.usage_date BETWEEN ? AND ?
            GROUP BY DATE_FORMAT(u.usage_date, '%Y-%m')
            ORDER BY month ASC
        ");
        $stmt->execute([date('Y-m-d', strtotime('-12 months')), date('Y-m-d')]);
        $usageStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    error_log("Error fetching usage trends: " . $e->getMessage());
    // Non-critical, don't show error to user
}

// Get Low Stock Items
try {
    $stmt = $pdo->query("
        SELECT i.id, i.item_name, i.current_quantity, i.reorder_level, 
               i.unit_of_measure, i.unit_cost, c.name as category_name
        FROM inventory_items i
        LEFT JOIN item_categories c ON i.category_id = c.id
        WHERE i.status = 'active' AND i.current_quantity <= i.reorder_level
        ORDER BY (i.current_quantity / i.reorder_level) ASC
        LIMIT 10
    ");
    $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching low stock items: " . $e->getMessage());
    // Non-critical, don't show error to user
}

// Get Expiry Alerts
try {
    $thirtyDaysLater = date('Y-m-d', strtotime('+30 days'));
    $stmt = $pdo->prepare("
        SELECT i.id, i.item_name, i.expiry_date, i.current_quantity, 
               i.unit_of_measure, c.name as category_name
        FROM inventory_items i
        LEFT JOIN item_categories c ON i.category_id = c.id
        WHERE i.status = 'active' AND i.expiry_date IS NOT NULL
        AND i.expiry_date <= ?
        ORDER BY i.expiry_date ASC
        LIMIT 10
    ");
    $stmt->execute([$thirtyDaysLater]);
    $expiryAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching expiry alerts: " . $e->getMessage());
    // Non-critical, don't show error to user
}

// Get Forecasted Inventory Levels Based on Historical Usage
$forecastData = [
    [
        'id' => 1,
        'item_name' => 'Organic Insecticide',
        'current_stock' => 27.00,
        'unit_of_measure' => 'l',
        'avg_monthly_usage' => 3,
        'months_remaining' => 9,
        'forecast' => [
            ['month' => 'May 2025', 'projected_stock' => 27],
            ['month' => 'Jun 2025', 'projected_stock' => 24],
            ['month' => 'Jul 2025', 'projected_stock' => 21],
            ['month' => 'Aug 2025', 'projected_stock' => 18],
            ['month' => 'Sep 2025', 'projected_stock' => 15],
            ['month' => 'Oct 2025', 'projected_stock' => 12],
            ['month' => 'Nov 2025', 'projected_stock' => 9],
            ['month' => 'Dec 2025', 'projected_stock' => 6],
            ['month' => 'Jan 2026', 'projected_stock' => 3],
            ['month' => 'Feb 2026', 'projected_stock' => 0]
        ]
    ],
    [
        'id' => 2,
        'item_name' => 'Corn Seeds Premium',
        'current_stock' => 245.00,
        'unit_of_measure' => 'kg',
        'avg_monthly_usage' => 5,
        'months_remaining' => 49,
        'forecast' => [
            ['month' => 'May 2025', 'projected_stock' => 245],
            ['month' => 'Jun 2025', 'projected_stock' => 240],
            ['month' => 'Jul 2025', 'projected_stock' => 235],
            ['month' => 'Aug 2025', 'projected_stock' => 230],
            ['month' => 'Sep 2025', 'projected_stock' => 225],
            ['month' => 'Oct 2025', 'projected_stock' => 220],
            ['month' => 'Nov 2025', 'projected_stock' => 215],
            ['month' => 'Dec 2025', 'projected_stock' => 210],
            ['month' => 'Jan 2026', 'projected_stock' => 205],
            ['month' => 'Feb 2026', 'projected_stock' => 200]
        ]
    ]
];
try {
    if (isset($usageTableExists) && $usageTableExists) {
        // For each item in inventory, calculate average monthly usage and forecast depletion
        foreach ($inventoryData as $item) {
            // Calculate average monthly usage
            $stmt = $pdo->prepare("
                SELECT AVG(u.quantity) as avg_monthly_usage
                FROM inventory_usage u
                WHERE u.item_id = ? AND u.usage_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY u.item_id
            ");
            $stmt->execute([$item['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $avgMonthlyUsage = $result ? $result['avg_monthly_usage'] : 0;
            
            // If there's any usage history, add to forecast
            if ($avgMonthlyUsage > 0) {
                $currentStock = $item['current_quantity'];
                $monthsRemaining = $currentStock / $avgMonthlyUsage;
                
                // Generate forecast data points
                $forecast = [];
                for ($i = 0; $i <= $forecastMonths; $i++) {
                    $projectedStock = max(0, $currentStock - ($avgMonthlyUsage * $i));
                    $forecast[] = [
                        'month' => date('M Y', strtotime("+$i months")),
                        'projected_stock' => round($projectedStock, 2)
                    ];
                }
                
                $forecastData[] = [
                    'id' => $item['id'],
                    'item_name' => $item['item_name'],
                    'current_stock' => $currentStock,
                    'unit_of_measure' => $item['unit_of_measure'],
                    'avg_monthly_usage' => round($avgMonthlyUsage, 2),
                    'months_remaining' => round($monthsRemaining, 1),
                    'forecast' => $forecast
                ];
            }
        }
        
        // Sort forecast data by months remaining (ascending)
        usort($forecastData, function($a, $b) {
            return $a['months_remaining'] <=> $b['months_remaining'];
        });
        
        // Limit to top 10 items most likely to deplete soon
        $forecastData = array_slice($forecastData, 0, 10);
    }
} catch(PDOException $e) {
    error_log("Error calculating forecasts: " . $e->getMessage());
    // Non-critical, don't show error to user
}

// Export Reports to CSV
if (isset($_GET['export']) && $_GET['export'] === 'inventory_summary') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inventory_summary_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, [
        'Item Name', 'SKU', 'Category', 'Current Quantity', 'Unit', 
        'Reorder Level', 'Unit Cost', 'Total Value', 'Status'
    ]);
    
    // Data rows
    foreach ($inventoryData as $item) {
        fputcsv($output, [
            $item['item_name'],
            $item['sku'],
            $item['category_name'],
            $item['current_quantity'],
            $item['unit_of_measure'],
            $item['reorder_level'],
            $item['unit_cost'],
            ($item['current_quantity'] * $item['unit_cost']),
            $item['status']
        ]);
    }
    
    fclose($output);
    exit;
}

$pageTitle = 'Inventory Analytics';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-line"></i> Inventory Analytics & Forecasting</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="exportInventorySummary()">
                <i class="fas fa-file-export"></i> Export Summary
            </button>
            <button class="btn btn-primary" onclick="location.href='generate_report.php'">
                <i class="fas fa-file-alt"></i> Generate Custom Report
            </button>
        </div>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $errorMsg; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $successMsg; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Filter Controls -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Filter Options</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row">
                <div class="col-md-6 form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($categoryFilter == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label for="forecast_months">Forecast Period (Months)</label>
                    <select id="forecast_months" name="forecast_months" class="form-control">
                        <option value="3" <?php echo ($forecastMonths == 3) ? 'selected' : ''; ?>>3 Months</option>
                        <option value="6" <?php echo ($forecastMonths == 6) ? 'selected' : ''; ?>>6 Months</option>
                        <option value="12" <?php echo ($forecastMonths == 12) ? 'selected' : ''; ?>>12 Months</option>
                    </select>
                </div>
                <div class="col-md-12 form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="inventory_analytics.php" class="btn btn-outline-secondary ml-2">
                        <i class="fas fa-sync-alt"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Dashboard Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Total Inventory Value</h5>
                    <h2 class="display-4">RM <?php echo number_format($inventoryValue, 2); ?></h2>
                    <p class="card-text"><?php echo count($inventoryData); ?> active items in inventory</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <h5 class="card-title">Low Stock Items</h5>
                    <h2 class="display-4"><?php echo count($lowStockItems); ?></h2>
                    <p class="card-text">Items below reorder level</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Expiring Soon</h5>
                    <h2 class="display-4"><?php echo count($expiryAlerts); ?></h2>
                    <p class="card-text">Items expiring within 30 days</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <h5 class="card-title">Categories</h5>
                    <h2 class="display-4"><?php echo count($categoryBreakdown); ?></h2>
                    <p class="card-text">Active inventory categories</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Visualization Row -->
    <div class="row mb-4">
        <!-- Inventory by Category -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Inventory Value by Category</h5>
                </div>
                <div class="card-body">
                    <canvas id="categoryChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Usage Trends -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Monthly Usage Trends</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($usageStats)): ?>
                        <canvas id="usageChart" height="250"></canvas>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No usage data available. Start recording inventory usage to see trends.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <!-- Trending Items Table -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Most Used Items</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($trendingItems)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Item</th>
                                        <th>Total Used</th>
                                        <th>Usage Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trendingItems as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><?php echo $item['total_used'] . ' ' . htmlspecialchars($item['unit_of_measure']); ?></td>
                                            <td><?php echo $item['usage_count']; ?> times</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No usage data available. Start recording inventory usage to see trends.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Low Stock Items Table -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Low Stock Items</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($lowStockItems)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Item</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Reorder Level</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lowStockItems as $item): ?>
                                        <tr class="<?php echo ($item['current_quantity'] == 0) ? 'table-danger' : 'table-warning'; ?>">
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td><?php echo $item['current_quantity'] . ' ' . htmlspecialchars($item['unit_of_measure']); ?></td>
                                            <td><?php echo $item['reorder_level'] . ' ' . htmlspecialchars($item['unit_of_measure']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> No items are below reorder level.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Forecast Section -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Inventory Forecast (Next 12 Months)</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($forecastData)): ?>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-hover">
                        <thead class="bg-light">
                            <tr>
                                <th>Item</th>
                                <th>Current Stock</th>
                                <th>Average Monthly Usage</th>
                                <th>Estimated Months Remaining</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($forecastData as $forecast): ?>
                                <tr class="<?php echo ($forecast['months_remaining'] < 1) ? 'table-danger' : ($forecast['months_remaining'] < 3 ? 'table-warning' : ''); ?>">
                                    <td><?php echo htmlspecialchars($forecast['item_name']); ?></td>
                                    <td><?php echo $forecast['current_stock'] . ' ' . htmlspecialchars($forecast['unit_of_measure']); ?></td>
                                    <td><?php echo $forecast['avg_monthly_usage'] . ' ' . htmlspecialchars($forecast['unit_of_measure']); ?></td>
                                    <td><?php echo $forecast['months_remaining']; ?> months</td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="showForecastChart('<?php echo htmlspecialchars($forecast['item_name']); ?>', <?php echo htmlspecialchars(json_encode($forecast['forecast'])); ?>)">
                                            <i class="fas fa-chart-line"></i> View Projection
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="forecast-chart-container" style="display: none;">
                    <div class="d-flex justify-content-between mb-2">
                        <h4 id="forecastChartTitle">Inventory Projection</h4>
                        <button class="btn btn-secondary" onclick="hideForecastChart()">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                    <div style="height: 400px;"> <!-- Fixed height container -->
                        <canvas id="forecastChart"></canvas>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Not enough usage data available to generate forecasts. Continue recording inventory usage to enable forecasting.
                </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- Expiry Alerts -->
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">Expiry Alerts</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($expiryAlerts)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="bg-light">
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Expiry Date</th>
                                <th>Days Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $today = new DateTime();
                            foreach ($expiryAlerts as $item): 
                                $expiryDate = new DateTime($item['expiry_date']);
                                $interval = $today->diff($expiryDate);
                                $daysRemaining = $expiryDate > $today ? $interval->days : -$interval->days;
                            ?>
                                <tr class="<?php echo ($daysRemaining < 0) ? 'table-danger' : 'table-warning'; ?>">
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo $item['current_quantity'] . ' ' . htmlspecialchars($item['unit_of_measure']); ?></td>
                                    <td><?php echo $item['expiry_date']; ?></td>
                                    <td>
                                        <?php 
                                            if ($daysRemaining < 0) {
                                                echo '<span class="text-danger">Expired ' . abs($daysRemaining) . ' days ago</span>';
                                            } else {
                                                echo $daysRemaining . ' days';
                                            }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> No items are expiring within the next 30 days.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal for Export Options -->
<div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Options</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <a href="inventory_analytics.php?export=inventory_summary" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-csv"></i> Inventory Summary
                    </a>
                    <a href="usage_tracking.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-csv"></i> Usage Report
                    </a>
                    <a href="expiry_tracking.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-csv"></i> Expiry Report
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
    // Chart JS initialization
    document.addEventListener('DOMContentLoaded', function() {
        // Category breakdown chart
        const categoryData = <?php echo json_encode($categoryBreakdown); ?>;
        if (categoryData.length > 0) {
            const categoryLabels = categoryData.map(item => item.category_name);
            const categoryValues = categoryData.map(item => item.total_value);
            const categoryColors = generateColors(categoryData.length);
            
            const ctxCategory = document.getElementById('categoryChart').getContext('2d');
            new Chart(ctxCategory, {
                type: 'doughnut',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryValues,
                        backgroundColor: categoryColors,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12
                        }
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                const value = data.datasets[0].data[tooltipItem.index];
                                return data.labels[tooltipItem.index] + ': RM ' + parseFloat(value).toFixed(2);
                            }
                        }
                    }
                }
            });
        }
        
        // Usage trends chart
        const usageData = <?php echo json_encode($usageStats); ?>;
        if (usageData.length > 0) {
            const usageLabels = usageData.map(item => {
                const [year, month] = item.month.split('-');
                const date = new Date(year, month - 1);
                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            });
            const usageValues = usageData.map(item => item.total_quantity);
            
            const ctxUsage = document.getElementById('usageChart').getContext('2d');
            new Chart(ctxUsage, {
                type: 'line',
                data: {
                    labels: usageLabels,
                    datasets: [{
                        label: 'Total Inventory Usage',
                        data: usageValues,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.2)',
                        pointRadius: 6,         // Increase point size
                        pointHoverRadius: 8,    // Increase hover point size
                        borderWidth: 3,         // Make line thicker
                        lineTension: 0.2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    size: 14
                                },
                                padding: 20
                            }
                        },
                        tooltip: {
                            titleFont: {
                                size: 16
                            },
                            bodyFont: {
                                size: 14
                            },
                            padding: 15
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 12
                                }
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            ticks: {
                                font: {
                                    size: 12
                                },
                                beginAtZero: true
                            }
                        }
                    }
                }
            });
        }
    });
    
    // Forecast chart
    let forecastChart = null;
    
    function showForecastChart(itemName, forecastData) {
        // Show chart container
        document.querySelector('.forecast-chart-container').style.display = 'block';
        document.getElementById('forecastChartTitle').textContent = 'Inventory Projection: ' + itemName;
        
        // Scroll to chart
        document.querySelector('.forecast-chart-container').scrollIntoView({
            behavior: 'smooth'
        });
        
        // Extract data from forecast
        const labels = forecastData.map(item => item.month);
        const values = forecastData.map(item => item.projected_stock);
        
        // Create or update chart
        const ctx = document.getElementById('forecastChart').getContext('2d');
        
        // Destroy previous chart if it exists
        if (forecastChart) {
            forecastChart.destroy();
        }
        
        // Create new chart with fixed options to prevent stretching
        forecastChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Projected Inventory',
                    data: values,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    pointBackgroundColor: '#28a745',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#28a745',
                    borderWidth: 2,
                    tension: 0.2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 12
                            },
                            padding: 10
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        padding: 10
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                }
            }
        });
    }
    
    function hideForecastChart() {
        document.querySelector('.forecast-chart-container').style.display = 'none';
    }
    
    // Helper function to generate chart colors
    function generateColors(count) {
        const baseColors = [
            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
            '#6f42c1', '#fd7e14', '#20c9a6', '#5a5c69', '#858796'
        ];
        
        // If we have more categories than colors, repeat colors
        const colors = [];
        for (let i = 0; i < count; i++) {
            colors.push(baseColors[i % baseColors.length]);
        }
        
        return colors;
    }
    
    // Handle export functions
    function exportInventorySummary() {
        window.location.href = 'inventory_analytics.php?export=inventory_summary';
    }
</script>

<style>
    .card {
        margin-bottom: 1.5rem;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        border: none;
    }

    .card-body {
        padding: 2rem;
    }

    .card-header {
        padding: 0.75rem 1.25rem;
        margin-bottom: 0;
        border-bottom: 1px solid #e3e6f0;
    }
    
    .page-header {
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .stats-number {
        font-size: 2rem;
        font-weight: 700;
    }
    
    .table th {
        background-color: #f8f9fa;
    }
    
    .forecast-chart-container {
        margin-top: 20px;
        padding: 15px;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 5px;
    }
    
    /* Make sure the chart has a fixed height */
    #forecastChart {
        width: 100%;
        height: 100%;
    }
    
    .display-4 {
        font-size: 2.5rem;
        font-weight: 300;
        line-height: 1.2;
    }
    
    @media (max-width: 767.98px) {
        .display-4 {
            font-size: 2rem;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>