<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$item = [];
$logs = [];
$usage = [];

// Check if item ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: item_details.php');
    exit;
}

$itemId = $_GET['id'];

// Fetch item details
try {
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as category_name, s.name as supplier_name
        FROM inventory_items i
        LEFT JOIN item_categories c ON i.category_id = c.id
        LEFT JOIN suppliers s ON i.supplier_id = s.id
        WHERE i.id = ? AND i.status = 'active'
    ");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        header('Location: item_details.php');
        exit;
    }
} catch(PDOException $e) {
    error_log("Error fetching item details: " . $e->getMessage());
    $errorMsg = "Failed to load item details. Please try again later.";
}

// Fetch recent logs
try {
    $logStmt = $pdo->prepare("
        SELECT l.*, u.username as user_name
        FROM inventory_log l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.item_id = ?
        ORDER BY l.created_at DESC
        LIMIT 5
    ");
    $logStmt->execute([$itemId]);
    $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching inventory logs: " . $e->getMessage());
}

// Fetch usage statistics
try {
    // Get monthly usage data (last 6 months)
    $usageStmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(CASE WHEN action_type IN ('manual_remove', 'sale', 'waste') THEN quantity ELSE 0 END) as usage
        FROM inventory_log
        WHERE item_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $usageStmt->execute([$itemId]);
    $usage = $usageStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching usage statistics: " . $e->getMessage());
}

$pageTitle = 'View Item Details';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-eye"></i> Item Details: <?php echo htmlspecialchars($item['item_name']); ?></h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='item_details.php'">
                <i class="fas fa-arrow-left"></i> Back to Items
            </button>
            <button class="btn btn-primary" onclick="location.href='edit_item.php?id=<?php echo $itemId; ?>'">
                <i class="fas fa-edit"></i> Edit Item
            </button>
            <button class="btn btn-info" onclick="window.print();">
                <i class="fas fa-print"></i> Print
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

    <div class="row">
        <!-- Item Details Card -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Basic Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered detail-table">
                        <tbody>
                            <tr>
                                <th width="35%">Item Name</th>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            </tr>
                            <tr>
                                <th>SKU</th>
                                <td><?php echo htmlspecialchars($item['sku']); ?></td>
                            </tr>
                            <tr>
                                <th>Category</th>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Supplier</th>
                                <td><?php echo !empty($item['supplier_name']) ? htmlspecialchars($item['supplier_name']) : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Description</th>
                                <td><?php echo nl2br(htmlspecialchars($item['description'] ?? 'N/A')); ?></td>
                            </tr>
                            <tr>
                                <th>Date Added</th>
                                <td><?php echo date('Y-m-d', strtotime($item['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Last Updated</th>
                                <td><?php echo !empty($item['updated_at']) ? date('Y-m-d', strtotime($item['updated_at'])) : 'N/A'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Inventory Status</h5>
                </div>
                <div class="card-body">
                    <div class="current-inventory-status">
                        <div class="inventory-metric">
                            <span class="metric-label">Current Quantity</span>
                            <span class="metric-value <?php echo ($item['current_quantity'] <= $item['reorder_level']) ? 'text-warning' : ''; ?>">
                                <?php echo $item['current_quantity']; ?> <?php echo htmlspecialchars($item['unit_of_measure']); ?>
                            </span>
                        </div>
                        
                        <div class="inventory-metric">
                            <span class="metric-label">Reorder Level</span>
                            <span class="metric-value"><?php echo $item['reorder_level']; ?> <?php echo htmlspecialchars($item['unit_of_measure']); ?></span>
                        </div>
                        
                        <div class="inventory-metric">
                            <span class="metric-label">Maximum Level</span>
                            <span class="metric-value">
                                <?php echo ($item['maximum_level'] > 0) ? $item['maximum_level'] . ' ' . htmlspecialchars($item['unit_of_measure']) : 'Not set'; ?>
                            </span>
                        </div>
                        
                        <div class="inventory-metric">
                            <span class="metric-label">Status</span>
                            <span class="metric-value">
                                <?php 
                                    if ($item['current_quantity'] <= $item['reorder_level']) {
                                        echo '<span class="badge badge-warning">Low Stock</span>';
                                    } else {
                                        echo '<span class="badge badge-success">In Stock</span>';
                                    }
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Stock Level Progress Bar -->
                    <?php if ($item['maximum_level'] > 0): ?>
                        <div class="stock-progress-container mt-3">
                            <label>Stock Level</label>
                            <?php 
                                $stockPercentage = min(100, ($item['current_quantity'] / $item['maximum_level']) * 100);
                                $progressClass = 'bg-success';
                                
                                if ($stockPercentage <= 25) {
                                    $progressClass = 'bg-danger';
                                } elseif ($stockPercentage <= 50) {
                                    $progressClass = 'bg-warning';
                                }
                            ?>
                            <div class="progress">
                                <div class="progress-bar <?php echo $progressClass; ?>" role="progressbar" 
                                     style="width: <?php echo $stockPercentage; ?>%" 
                                     aria-valuenow="<?php echo $stockPercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo round($stockPercentage); ?>%
                                </div>
                            </div>
                            <small class="text-muted">
                                <?php echo $item['current_quantity']; ?> of <?php echo $item['maximum_level']; ?> <?php echo htmlspecialchars($item['unit_of_measure']); ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Additional Details -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Procurement & Quality</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered detail-table">
                        <tbody>
                            <tr>
                                <th width="35%">Purchase Price</th>
                                <td>RM <?php echo number_format($item['unit_cost'], 2); ?> per <?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                            </tr>
                            <tr>
                                <th>Total Value</th>
                                <td>RM <?php echo number_format($item['unit_cost'] * $item['current_quantity'], 2); ?></td>
                            </tr>
                            <tr>
                                <th>Batch Number</th>
                                <td><?php echo !empty($item['batch_number']) ? htmlspecialchars($item['batch_number']) : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Expiry Date</th>
                                <td>
                                    <?php 
                                        if (!empty($item['expiry_date'])) {
                                            $expiryDate = new DateTime($item['expiry_date']);
                                            $today = new DateTime();
                                            $interval = $today->diff($expiryDate);
                                            $daysRemaining = $expiryDate > $today ? $interval->days : -$interval->days;
                                            
                                            echo htmlspecialchars($item['expiry_date']);
                                            
                                            if ($daysRemaining < 0) {
                                                echo ' <span class="badge badge-danger">Expired</span>';
                                            } elseif ($daysRemaining <= 30) {
                                                echo ' <span class="badge badge-warning">Expires in ' . $daysRemaining . ' days</span>';
                                            }
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Supplier Contact</th>
                                <td>
                                    <?php 
                                        if (!empty($item['supplier_id'])) {
                                            try {
                                                $supplierStmt = $pdo->prepare("
                                                    SELECT phone, email, address FROM suppliers 
                                                    WHERE id = ? AND status = 'active'
                                                ");
                                                $supplierStmt->execute([$item['supplier_id']]);
                                                $supplierDetails = $supplierStmt->fetch(PDO::FETCH_ASSOC);
                                                
                                                if ($supplierDetails) {
                                                    echo '<div class="supplier-contact-info">';
                                                    if (!empty($supplierDetails['phone'])) {
                                                        echo '<div><strong>Phone:</strong> ' . htmlspecialchars($supplierDetails['phone']) . '</div>';
                                                    }
                                                    if (!empty($supplierDetails['email'])) {
                                                        echo '<div><strong>Email:</strong> ' . htmlspecialchars($supplierDetails['email']) . '</div>';
                                                    }
                                                    if (!empty($supplierDetails['address'])) {
                                                        echo '<div><strong>Address:</strong> ' . htmlspecialchars($supplierDetails['address']) . '</div>';
                                                    }
                                                    echo '</div>';
                                                } else {
                                                    echo 'No contact information available';
                                                }
                                            } catch(PDOException $e) {
                                                error_log("Error fetching supplier details: " . $e->getMessage());
                                                echo 'Error retrieving supplier contact information';
                                            }
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="card mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <?php if (count($logs) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Action</th>
                                        <th>Qty</th>
                                        <th>User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d', strtotime($log['created_at'])); ?></td>
                                            <td>
                                                <?php 
                                                    $actionLabel = '';
                                                    switch ($log['action_type']) {
                                                        case 'initial_add':
                                                            $actionLabel = '<span class="badge badge-success">Initial Add</span>';
                                                            break;
                                                        case 'manual_add':
                                                            $actionLabel = '<span class="badge badge-primary">Manual Add</span>';
                                                            break;
                                                        case 'manual_remove':
                                                            $actionLabel = '<span class="badge badge-warning">Manual Remove</span>';
                                                            break;
                                                        case 'sale':
                                                            $actionLabel = '<span class="badge badge-info">Sale</span>';
                                                            break;
                                                        case 'purchase':
                                                            $actionLabel = '<span class="badge badge-info">Purchase</span>';
                                                            break;
                                                        case 'waste':
                                                            $actionLabel = '<span class="badge badge-danger">Waste</span>';
                                                            break;
                                                        default:
                                                            $actionLabel = '<span class="badge badge-secondary">' . ucfirst($log['action_type']) . '</span>';
                                                    }
                                                    echo $actionLabel;
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    if (in_array($log['action_type'], ['manual_remove', 'sale', 'waste'])) {
                                                        echo '-';
                                                    } else {
                                                        echo '+';
                                                    }
                                                    echo $log['quantity']; 
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center">
                            <a href="item_log.php?id=<?php echo $itemId; ?>" class="btn btn-sm btn-outline-secondary">
                                View Full Activity Log
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No recent activity for this item.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Usage Statistics -->
    <div class="card mt-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Usage Statistics</h5>
        </div>
        <div class="card-body">
            <?php if (count($usage) > 0): ?>
                <div class="row">
                    <div class="col-md-12">
                        <h6>Monthly Usage (Last 6 Months)</h6>
                        <div class="usage-chart-container" style="height: 300px;">
                            <canvas id="usageChart"></canvas>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No usage data available for this item.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card mt-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Quick Actions</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <a href="edit_item.php?id=<?php echo $itemId; ?>" class="btn btn-outline-primary btn-block">
                        <i class="fas fa-edit"></i> Edit Item
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="add_stock.php?id=<?php echo $itemId; ?>" class="btn btn-outline-success btn-block">
                        <i class="fas fa-plus-circle"></i> Add Stock
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="remove_stock.php?id=<?php echo $itemId; ?>" class="btn btn-outline-warning btn-block">
                        <i class="fas fa-minus-circle"></i> Remove Stock
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="item_log.php?id=<?php echo $itemId; ?>" class="btn btn-outline-info btn-block">
                        <i class="fas fa-history"></i> View Log
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (count($usage) > 0): ?>
    // Usage chart
    var ctx = document.getElementById('usageChart').getContext('2d');
    var usageChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [
                <?php 
                    $labels = [];
                    foreach ($usage as $monthData) {
                        $monthLabel = date('M Y', strtotime($monthData['month'] . '-01'));
                        $labels[] = "'" . $monthLabel . "'";
                    }
                    echo implode(', ', $labels);
                ?>
            ],
            datasets: [{
                label: 'Monthly Usage (<?php echo htmlspecialchars($item['unit_of_measure']); ?>)',
                data: [
                    <?php 
                        $usageData = [];
                        foreach ($usage as $monthData) {
                            $usageData[] = $monthData['usage'];
                        }
                        echo implode(', ', $usageData);
                    ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantity (<?php echo htmlspecialchars($item['unit_of_measure']); ?>)'
                    }
                }
            }
        }
    });
<?php endif; ?>
</script>

<style>
    .main-content {
        padding-bottom: 60px; /* Space for footer */
    }
    
    .card {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }
    
    .detail-table th {
        background-color: #f8f9fa;
    }
    
    .current-inventory-status {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        grid-gap: 15px;
    }
    
    .inventory-metric {
        display: flex;
        flex-direction: column;
        padding: 10px;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        background-color: #f8f9fa;
    }
    
    .metric-label {
        font-size: 14px;
        color: #6c757d;
        margin-bottom: 5px;
    }
    
    .metric-value {
        font-size: 18px;
        font-weight: 600;
    }
    
    .supplier-contact-info {
        margin-top: 5px;
    }
    
    .supplier-contact-info div {
        margin-bottom: 5px;
    }
    
    .badge {
        padding: 0.4em 0.6em;
        font-size: 85%;
    }
    
    .text-warning {
        color: #ffc107 !important;
    }
    
    .usage-chart-container {
        width: 100%;
    }
    
    @media print {
        .action-buttons, 
        .btn, 
        footer {
            display: none !important;
        }
        
        body {
            padding: 0 !important;
            margin: 0 !important;
        }
        
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            break-inside: avoid;
        }
        
        .page-header h2 {
            font-size: 24px !important;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>