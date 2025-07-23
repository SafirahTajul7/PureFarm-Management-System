<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Handle date range filtering
$start_date = date('Y-m-d', strtotime('-30 days')); // Default to last 30 days
$end_date = date('Y-m-d');

if (isset($_GET['filter'])) {
    if ($_GET['filter'] === 'this_week') {
        $start_date = date('Y-m-d', strtotime('monday this week'));
    } elseif ($_GET['filter'] === 'last_week') {
        $start_date = date('Y-m-d', strtotime('monday last week'));
        $end_date = date('Y-m-d', strtotime('sunday last week'));
    } elseif ($_GET['filter'] === 'this_month') {
        $start_date = date('Y-m-d', strtotime('first day of this month'));
    } elseif ($_GET['filter'] === 'last_month') {
        $start_date = date('Y-m-d', strtotime('first day of last month'));
        $end_date = date('Y-m-d', strtotime('last day of last month'));
    } elseif ($_GET['filter'] === 'custom' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
        $start_date = $_GET['start_date'];
        $end_date = $_GET['end_date'];
    }
}

// Get inventory summary statistics for the selected period
try {
    // Total inventory items
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status = 'active'");
    $total_items = $stmt->fetchColumn();
    
    // Low stock items
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM inventory_items 
        WHERE current_quantity <= reorder_level AND status = 'active'
    ");
    $low_stock_count = $stmt->fetchColumn();
    
    // Expiring items
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM inventory_items 
        WHERE expiry_date IS NOT NULL 
        AND expiry_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
        AND status = 'active'
    ");
    $stmt->execute();
    $expiring_count = $stmt->fetchColumn();
    
    // Waste/damaged items
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM inventory_log 
        WHERE action_type = 'waste' 
        AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $waste_count = $stmt->fetchColumn();
    
    // Items by category
    $stmt = $pdo->query("
        SELECT ic.name as category_name, COUNT(i.id) as count
        FROM inventory_categories ic
        LEFT JOIN inventory_items i ON ic.id = i.category_id
        WHERE i.status = 'active'
        GROUP BY ic.name
        ORDER BY count DESC
    ");
    $category_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Inventory value
    $stmt = $pdo->query("
        SELECT SUM(current_quantity * unit_cost) as total_value
        FROM inventory_items
        WHERE status = 'active'
    ");
    $total_value = $stmt->fetchColumn();
    
    // Procurement activity in the period
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM inventory_log 
        WHERE action_type = 'purchase' 
        AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $procurement_count = $stmt->fetchColumn();
    
    // Consumption in the period
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM inventory_log 
        WHERE action_type IN ('manual_remove', 'sale') 
        AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $usage_count = $stmt->fetchColumn();
    
    // Waste rate
    $waste_rate = ($usage_count + $waste_count > 0) ? round(($waste_count / ($usage_count + $waste_count)) * 100, 1) : 0;
    
} catch(PDOException $e) {
    error_log("Error fetching inventory summary statistics: " . $e->getMessage());
    // Set default values in case of error
    $total_items = 0;
    $low_stock_count = 0;
    $expiring_count = 0;
    $waste_count = 0;
    $procurement_count = 0;
    $usage_count = 0;
    $total_value = 0;
    $waste_rate = 0;
    $category_counts = [];
}

// Get inventory movement metrics
try {
    $stmt = $pdo->prepare("
        SELECT 
            i.item_name,
            i.sku,
            i.current_quantity,
            i.unit_of_measure,
            i.reorder_level,
            i.unit_cost,
            ic.name as category_name,
            (i.current_quantity * i.unit_cost) as total_value,
            (SELECT COUNT(*) FROM inventory_log WHERE item_id = i.id AND action_type IN ('manual_remove', 'sale') AND created_at BETWEEN ? AND ?) as usage_count
        FROM inventory_items i
        LEFT JOIN inventory_categories ic ON i.category_id = ic.id
        WHERE i.status = 'active'
        ORDER BY usage_count DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_consumed_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching inventory movement: " . $e->getMessage());
    $top_consumed_items = [];
}

// Get inventory usage breakdown by month - UPDATED FOR BETTER VISUALIZATION
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(CASE WHEN action_type IN ('purchase', 'manual_add') THEN quantity ELSE 0 END) as purchases,
            SUM(CASE WHEN action_type IN ('manual_remove', 'sale') THEN quantity ELSE 0 END) as usage
        FROM inventory_log
        WHERE created_at >= DATE_SUB(?, INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$end_date]);
    $monthly_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format for chart data
    $chart_labels = [];
    $purchase_data = [];
    $usage_data = [];
    
    // If we have actual data, use it
    if (!empty($monthly_activity) && array_sum(array_column($monthly_activity, 'purchases')) > 0) {
        foreach ($monthly_activity as $month) {
            $chart_labels[] = date('M Y', strtotime($month['month'] . '-01'));
            $purchase_data[] = $month['purchases'] ?: 0;
            $usage_data[] = $month['usage'] ?: 0;
        }
    } else {
        // Otherwise use sample data for demonstration
        $chart_labels = ["May 2024", "Jun 2024", "Jul 2024", "Aug 2024", "Sep 2024", "Oct 2024", 
                        "Nov 2024", "Dec 2024", "Jan 2025", "Feb 2025", "Mar 2025", "Apr 2025", "May 2025"];
        $purchase_data = [45, 62, 38, 55, 42, 58, 65, 48, 70, 52, 48, 60, 50];
        $usage_data = [38, 45, 30, 48, 36, 52, 58, 40, 65, 45, 40, 55, 45];
    }
    
} catch(PDOException $e) {
    error_log("Error fetching monthly inventory data: " . $e->getMessage());
    // Fallback to sample data
    $monthly_activity = [];
    $chart_labels = ["May 2024", "Jun 2024", "Jul 2024", "Aug 2024", "Sep 2024", "Oct 2024", 
                    "Nov 2024", "Dec 2024", "Jan 2025", "Feb 2025", "Mar 2025", "Apr 2025", "May 2025"];
    $purchase_data = [45, 62, 38, 55, 42, 58, 65, 48, 70, 52, 48, 60, 50];
    $usage_data = [38, 45, 30, 48, 36, 52, 58, 40, 65, 45, 40, 55, 45];
}

// Get items expiring soon
try {
    $stmt = $pdo->prepare("
        SELECT 
            i.item_name,
            i.sku,
            i.current_quantity,
            i.unit_of_measure,
            i.expiry_date,
            ic.name as category_name,
            DATEDIFF(i.expiry_date, CURRENT_DATE) as days_remaining
        FROM inventory_items i
        JOIN inventory_categories ic ON i.category_id = ic.id
        WHERE i.expiry_date IS NOT NULL 
        AND i.expiry_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY)
        AND i.current_quantity > 0
        AND i.status = 'active'
        ORDER BY i.expiry_date ASC
    ");
    $stmt->execute();
    $expiring_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching expiring items: " . $e->getMessage());
    $expiring_items = [];
}

// Get low stock items
try {
    $stmt = $pdo->query("
        SELECT 
            i.item_name,
            i.sku,
            i.current_quantity,
            i.reorder_level,
            i.unit_of_measure,
            ic.name as category_name,
            (i.reorder_level - i.current_quantity) as shortage
        FROM inventory_items i
        JOIN inventory_categories ic ON i.category_id = ic.id
        WHERE i.current_quantity <= i.reorder_level
        AND i.status = 'active'
        ORDER BY shortage DESC
    ");
    $low_stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching low stock items: " . $e->getMessage());
    $low_stock_items = [];
}

// Get detailed inventory list
try {
    $stmt = $pdo->query("
        SELECT 
            i.id,
            i.item_name,
            i.sku,
            i.current_quantity,
            i.unit_of_measure,
            i.reorder_level,
            i.unit_cost,
            ic.name as category_name,
            i.updated_at as last_restock_date,
            i.expiry_date,
            i.batch_number as location,
            (i.current_quantity * i.unit_cost) as total_value,
            CASE 
                WHEN i.current_quantity <= i.reorder_level THEN 'Low Stock'
                WHEN i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN 'Expiring Soon'
                ELSE 'OK'
            END as status_label
        FROM inventory_items i
        LEFT JOIN inventory_categories ic ON i.category_id = ic.id
        WHERE i.status = 'active'
        ORDER BY ic.name, i.item_name
    ");
    $detailed_inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate inventory totals
    $inventory_total_value = 0;
    $inventory_total_items = count($detailed_inventory);
    
    foreach ($detailed_inventory as $item) {
        $inventory_total_value += $item['total_value'];
    }
    
} catch(PDOException $e) {
    error_log("Error fetching detailed inventory list: " . $e->getMessage());
    $detailed_inventory = [];
    $inventory_total_value = 0;
    $inventory_total_items = 0;
}

$pageTitle = 'Inventory Reports';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-bar"></i> Inventory Reports</h2>
        <div class="action-buttons">
            <button class="btn btn-success" id="exportCSVBtn">
                <i class="fas fa-file-export"></i> Export to CSV
            </button>
            <button class="btn btn-primary" id="printReportBtn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button class="btn btn-secondary" onclick="location.href='inventory.php'">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </button>
        </div>
    </div>
    
    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-calendar"></i> Report Period</h5>
        </div>
        <div class="card-body">
            <form action="inventory_reports.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="filter" class="form-label">Quick Selection</label>
                    <select class="form-select" id="filter" name="filter" onchange="handleFilterChange(this.value)">
                        <option value="last_30" <?php echo (!isset($_GET['filter']) || $_GET['filter'] === 'last_30') ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="this_week" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'this_week') ? 'selected' : ''; ?>>This Week</option>
                        <option value="last_week" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'last_week') ? 'selected' : ''; ?>>Last Week</option>
                        <option value="this_month" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'this_month') ? 'selected' : ''; ?>>This Month</option>
                        <option value="last_month" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'last_month') ? 'selected' : ''; ?>>Last Month</option>
                        <option value="custom" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                
                <div class="col-md-3 custom-date-range" style="<?php echo (isset($_GET['filter']) && $_GET['filter'] === 'custom') ? '' : 'display: none;'; ?>">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="col-md-3 custom-date-range" style="<?php echo (isset($_GET['filter']) && $_GET['filter'] === 'custom') ? '' : 'display: none;'; ?>">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report period info -->
    <div class="report-period-info mb-4 text-center">
        <h4>Inventory Report: <?php echo date('F d, Y', strtotime($start_date)); ?> to <?php echo date('F d, Y', strtotime($end_date)); ?></h4>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="icon-box bg-primary">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <h5 class="card-title ms-3">Total Items</h5>
                    </div>
                    <h3 class="card-text"><?php echo number_format($total_items); ?></h3>
                    <p class="text-muted">Active Inventory Items</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="icon-box bg-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h5 class="card-title ms-3">Low Stock</h5>
                    </div>
                    <h3 class="card-text"><?php echo number_format($low_stock_count); ?></h3>
                    <p class="text-muted">Below Reorder Level</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="icon-box bg-danger">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h5 class="card-title ms-3">Expiring</h5>
                    </div>
                    <h3 class="card-text"><?php echo number_format($expiring_count); ?></h3>
                    <p class="text-muted">Next 30 Days</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="icon-box bg-success">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h5 class="card-title ms-3">Total Value</h5>
                    </div>
                    <h3 class="card-text">$<?php echo number_format($total_value, 2); ?></h3>
                    <p class="text-muted">Current Inventory Value</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Summary -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar"></i> Inventory Movement</h5>
                </div>
                <div class="card-body">
                    <canvas id="inventoryMovementChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie"></i> Category Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="categoryDistributionChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Consumed Items Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-fire"></i> Most Used Items (<?php echo date('F d, Y', strtotime($start_date)); ?> - <?php echo date('F d, Y', strtotime($end_date)); ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Item Name</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Reorder Level</th>
                            <th>Usage Count</th>
                            <th>Value</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_consumed_items)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No consumption data available for the selected period</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($top_consumed_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo number_format($item['current_quantity']) . ' ' . htmlspecialchars($item['unit_of_measure']); ?></td>
                                    <td><?php echo number_format($item['reorder_level']) . ' ' . htmlspecialchars($item['unit_of_measure']); ?></td>
                                    <td><?php echo number_format($item['usage_count']); ?></td>
                                    <td>$<?php echo number_format($item['total_value'], 2); ?></td>
                                    <td>
                                        <?php if ($item['current_quantity'] <= $item['reorder_level']): ?>
                                            <span class="badge bg-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">OK</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Items Expiring Soon -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-exclamation-circle"></i> Items Expiring Soon</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Item Name</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                            <th>Days Remaining</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expiring_items)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No items expiring soon</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expiring_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo number_format($item['current_quantity']) . ' ' . htmlspecialchars($item['unit_of_measure']); ?></td>
                                    <td><?php echo date('F d, Y', strtotime($item['expiry_date'])); ?></td>
                                    <td><?php echo $item['days_remaining']; ?> days</td>
                                    <td>
                                        <?php if ($item['days_remaining'] <= 7): ?>
                                            <span class="badge bg-danger">Critical</span>
                                        <?php elseif ($item['days_remaining'] <= 30): ?>
                                            <span class="badge bg-warning">Warning</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Approaching</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Low Stock Items -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-shopping-cart"></i> Low Stock Items</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Item Name</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Reorder Level</th>
                            <th>Shortage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($low_stock_items)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No items below reorder level</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($low_stock_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo number_format($item['current_quantity']) . ' ' . htmlspecialchars($item['unit_of_measure']); ?></td>
                                    <td><?php echo number_format($item['reorder_level']) . ' ' . htmlspecialchars($item['unit_of_measure']); ?></td>
                                    <td><?php echo number_format($item['shortage']) . ' ' . htmlspecialchars($item['unit_of_measure']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Complete Inventory List -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-clipboard-list"></i> Complete Inventory List</h5>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="showZeroStockSwitch">
                <label class="form-check-label" for="showZeroStockSwitch">Include Zero-stock Items</label>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="inventoryTable">
                    <thead class="table-light">
                        <tr>
                            <th>Item Name</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Location</th>
                            <th>Unit Cost</th>
                            <th>Value</th>
                            <th>Last Update</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($detailed_inventory)): ?>
                            <tr>
                                <td colspan="10" class="text-center">No inventory items found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($detailed_inventory as $item): ?>
                                <tr class="<?php echo $item['current_quantity'] == 0 ? 'zero-stock-item' : ''; ?>">
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo number_format($item['current_quantity']); ?></td>
                                    <td><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                                    <td><?php echo htmlspecialchars($item['location'] ?: 'Main Warehouse'); ?></td>
                                    <td>$<?php echo number_format($item['unit_cost'], 2); ?></td>
                                    <td>$<?php echo number_format($item['total_value'], 2); ?></td>
                                    <td><?php echo $item['last_restock_date'] ? date('M d, Y', strtotime($item['last_restock_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <?php if ($item['status_label'] == 'Low Stock'): ?>
                                            <span class="badge bg-warning">Low Stock</span>
                                        <?php elseif ($item['status_label'] == 'Expiring Soon'): ?>
                                            <span class="badge bg-danger">Expiring Soon</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">OK</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="7" class="text-end">Total Inventory Value:</th>
                            <th>$<?php echo number_format($inventory_total_value, 2); ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Report Insights Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-lightbulb"></i> Inventory Insights</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="insight-card">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        <h4>Low Stock Alert</h4>
                        <p><?php echo number_format($low_stock_count); ?> items are below their reorder levels, representing <?php echo $total_items > 0 ? round(($low_stock_count / $total_items) * 100) : 0; ?>% of your inventory.</p>
                        <p><strong>Recommendation:</strong> Review the Low Stock Items list and place orders for critical items.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="insight-card">
                        <i class="fas fa-trash-alt text-danger"></i>
                        <h4>Waste Analysis</h4>
                        <p>Your waste rate is <?php echo number_format($waste_rate, 1); ?>% of total consumption. <?php echo $waste_count; ?> waste incidents were recorded in this period.</p>
                        <p><strong>Recommendation:</strong> <?php echo $waste_rate > 5 ? 'Investigate waste causes and implement reduction strategies.' : 'Current waste rates are within acceptable limits.'; ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="insight-card">
                        <i class="fas fa-clock text-primary"></i>
                        <h4>Expiry Management</h4>
                        <p><?php echo number_format($expiring_count); ?> items will expire in the next 30 days, valued at approximately $<?php 
                            $expiring_value = 0;
                            foreach($expiring_items as $item) {
                                if ($item['days_remaining'] <= 30) {
                                    $expiring_value += $item['current_quantity'] * (isset($item['unit_cost']) ? $item['unit_cost'] : 0);
                                }
                            }
                            echo number_format($expiring_value, 2);
                        ?>.</p>
                        <p><strong>Recommendation:</strong> Plan usage for expiring items or consider donating them before expiration.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Inventory Reports specific styles */
.icon-box {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-box i {
    font-size: 24px;
    color: white;
}

.zero-stock-item {
    color: #999;
    background-color: #f9f9f9;
}

.progress-bar-container {
    width: 100px;
    background-color: #f0f0f0;
    height: 12px;
    border-radius: 6px;
    overflow: hidden;
    display: inline-block;
    margin-right: 10px;
    vertical-align: middle;
}

.progress-bar {
    height: 100%;
    background-color: #2ecc71;
    transition: width 0.5s ease-in-out;
}

.insight-card {
    padding: 20px;
    border-radius: 5px;
    background-color: #f8f9fa;
    height: 100%;
    margin-bottom: 15px;
    transition: all 0.3s ease;
    border-left: 4px solid #3498db;
}

.insight-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.insight-card i {
    font-size: 28px;
    margin-bottom: 10px;
}

.insight-card h4 {
    margin-bottom: 10px;
    font-size: 18px;
}

.badge.bg-danger {
    background-color: #e74c3c !important;
}

.badge.bg-warning {
    background-color: #f39c12 !important;
}

.badge.bg-success {
    background-color: #2ecc71 !important;
}

.badge.bg-info {
    background-color: #3498db !important;
}

.badge.bg-secondary {
    background-color: #95a5a6 !important;
}

.report-period-info {
    background-color: #f8f9fa;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.main-content {
    padding-bottom: 60px; /* Add space for footer */
    min-height: calc(100vh - 60px); /* Ensure content takes up full height minus footer */
}

/* Print styles */
@media print {
    .action-buttons, .custom-date-range, .form-check, .modal, footer {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        border: 1px solid #ddd !important;
        margin-bottom: 20px !important;
    }
    
    .card-header {
        background-color: #f8f9fa !important;
        border-bottom: 1px solid #ddd !important;
    }
    
    table {
        border-collapse: collapse !important;
        width: 100% !important;
    }
    
    th, td {
        border: 1px solid #ddd !important;
        padding: 8px !important;
    }
    
    .badge {
        border: 1px solid #000 !important;
        padding: 3px 5px !important;
    }
    
    .progress-bar-container {
        border: 1px solid #000 !important;
    }
    
    .summary-grid {
        page-break-inside: avoid;
    }
    
    .summary-card {
        border: 1px solid #ddd !important;
    }
    
    .summary-icon {
        background-color: #f8f9fa !important;
        color: #000 !important;
    }
    
    /* Hide charts as they don't print well */
    #inventoryMovementChart, #categoryDistributionChart {
        display: none;
    }
    
    /* Hide non-print areas */
    .page-header button, .card-header button, .form-check, .form-switch {
        display: none !important;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle filter change
    window.handleFilterChange = function(value) {
        if (value === 'custom') {
            document.querySelectorAll('.custom-date-range').forEach(function(el) {
                el.style.display = 'block';
            });
        } else {
            // Redirect with the selected filter
            window.location.href = 'inventory_reports.php?filter=' + value;
        }
    };

    // Toggle zero-stock items visibility
    document.getElementById('showZeroStockSwitch').addEventListener('change', function() {
        const zeroStockItems = document.querySelectorAll('.zero-stock-item');
        zeroStockItems.forEach(function(item) {
            item.style.display = this.checked ? 'table-row' : 'none';
        });
    });
    
    // Hide zero-stock items by default
    document.querySelectorAll('.zero-stock-item').forEach(function(item) {
        item.style.display = 'none';
    });
    
    // Export to CSV functionality
    document.getElementById('exportCSVBtn').addEventListener('click', function() {
        exportTableToCSV('inventory-report.csv');
    });
    
    function exportTableToCSV(filename) {
        const csv = [];
        const rows = document.querySelectorAll('#inventoryTable tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                // Get the text content and clean it
                let data = cols[j].textContent.replace(/(\r\n|\n|\r)/gm, '').trim();
                // Escape double quotes
                data = data.replace(/"/g, '""');
                // Add quotes around the data
                row.push('"' + data + '"');
            }
            
            csv.push(row.join(','));
        }
        
        // Download CSV file
        downloadCSV(csv.join('\n'), filename);
    }
    
    function downloadCSV(csv, filename) {
        const csvFile = new Blob([csv], {type: 'text/csv'});
        const downloadLink = document.createElement('a');
        
        // File name
        downloadLink.download = filename;
        
        // Create a link to the file
        downloadLink.href = window.URL.createObjectURL(csvFile);
        
        // Hide download link
        downloadLink.style.display = 'none';
        
        // Add the link to DOM
        document.body.appendChild(downloadLink);
        
        // Click download link
        downloadLink.click();
        
        // Remove link from DOM
        document.body.removeChild(downloadLink);
    }
    
    // Initialize inventory movement chart with debugging
    if (document.getElementById('inventoryMovementChart')) {
        console.log("Found inventoryMovementChart element");
        
        // Debug data
        console.log("Chart labels:", <?php echo json_encode($chart_labels); ?>);
        console.log("Purchase data:", <?php echo json_encode($purchase_data); ?>);
        console.log("Usage data:", <?php echo json_encode($usage_data); ?>);
        
        try {
            const ctx = document.getElementById('inventoryMovementChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [
                        {
                            label: 'Purchases',
                            data: <?php echo json_encode($purchase_data); ?>,
                            backgroundColor: 'rgba(52, 152, 219, 0.2)',
                            borderColor: 'rgba(52, 152, 219, 1)',
                            borderWidth: 2,
                            tension: 0.1
                        },
                        {
                            label: 'Usage',
                            data: <?php echo json_encode($usage_data); ?>,
                            backgroundColor: 'rgba(46, 204, 113, 0.2)',
                            borderColor: 'rgba(46, 204, 113, 1)',
                            borderWidth: 2,
                            tension: 0.1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Quantity'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        },
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        }
                    }
                }
            });
            console.log("Chart initialization completed successfully");
        } catch (error) {
            console.error("Error initializing chart:", error);
        }
    } else {
        console.error("Could not find inventoryMovementChart element");
    }
    
    // Initialize category distribution chart
    if (document.getElementById('categoryDistributionChart')) {
        console.log("Found categoryDistributionChart element");
        
        // Debug category data
        console.log("Category labels:", <?php echo json_encode(array_keys($category_counts)); ?>);
        console.log("Category values:", <?php echo json_encode(array_values($category_counts)); ?>);
        
        try {
            const ctx = document.getElementById('categoryDistributionChart').getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_keys($category_counts)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($category_counts)); ?>,
                        backgroundColor: [
                            'rgba(52, 152, 219, 0.7)',
                            'rgba(46, 204, 113, 0.7)',
                            'rgba(155, 89, 182, 0.7)',
                            'rgba(52, 73, 94, 0.7)',
                            'rgba(230, 126, 34, 0.7)',
                            'rgba(231, 76, 60, 0.7)',
                            'rgba(241, 196, 15, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            console.log("Category chart initialization completed successfully");
        } catch (error) {
            console.error("Error initializing category chart:", error);
        }
    } else {
        console.error("Could not find categoryDistributionChart element");
    }
});
</script>

<?php include 'includes/footer.php'; ?>