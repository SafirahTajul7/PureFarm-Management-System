<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';

// Handle Stock Request Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_request'])) {
    $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $adminNotes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
    
    if ($requestId > 0 && in_array($action, ['approve', 'reject', 'fulfill'])) {
        try {
            $pdo->beginTransaction();
            
            if ($action === 'approve') {
                // Update request status to approved
                $stmt = $pdo->prepare("
                    UPDATE stock_requests 
                    SET status = 'approved', 
                        approved_date = NOW(), 
                        approved_by = ?, 
                        admin_notes = ? 
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$_SESSION['user_id'], $adminNotes, $requestId]);
                
                if ($stmt->rowCount() > 0) {
                    $successMsg = "Stock request approved successfully.";
                } else {
                    $errorMsg = "Request not found or already processed.";
                }
                
            } elseif ($action === 'reject') {
                // Update request status to rejected
                $stmt = $pdo->prepare("
                    UPDATE stock_requests 
                    SET status = 'rejected', 
                        approved_date = NOW(), 
                        approved_by = ?, 
                        admin_notes = ? 
                    WHERE id = ? AND status IN ('pending', 'approved')
                ");
                $stmt->execute([$_SESSION['user_id'], $adminNotes, $requestId]);
                
                if ($stmt->rowCount() > 0) {
                    $successMsg = "Stock request rejected.";
                } else {
                    $errorMsg = "Request not found or already processed.";
                }
                
            } elseif ($action === 'fulfill') {
                // Get request details first
                $stmt = $pdo->prepare("
                    SELECT sr.*, i.item_name, i.current_quantity, i.unit_of_measure
                    FROM stock_requests sr
                    JOIN inventory_items i ON sr.item_id = i.id
                    WHERE sr.id = ? AND sr.status = 'approved'
                ");
                $stmt->execute([$requestId]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($request) {
                    // Update inventory - add the requested quantity
                    $newQuantity = $request['current_quantity'] + $request['requested_quantity'];
                    
                    $stmt = $pdo->prepare("
                        UPDATE inventory_items 
                        SET current_quantity = ?, 
                            last_updated = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$newQuantity, $request['item_id']]);
                    
                    // Update request status to fulfilled
                    $stmt = $pdo->prepare("
                        UPDATE stock_requests 
                        SET status = 'fulfilled', 
                            admin_notes = CONCAT(COALESCE(admin_notes, ''), ' | Fulfilled on ', NOW())
                        WHERE id = ?
                    ");
                    $stmt->execute([$requestId]);
                    
                    $successMsg = "Stock request fulfilled! Added {$request['requested_quantity']} {$request['unit_of_measure']} to {$request['item_name']}. New stock: {$newQuantity} {$request['unit_of_measure']}.";
                } else {
                    $errorMsg = "Request not found or not approved yet.";
                }
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error processing stock request: " . $e->getMessage());
            $errorMsg = "Failed to process request: " . $e->getMessage();
        }
    } else {
        $errorMsg = "Invalid request data.";
    }
}

// Fetch summary statistics
try {
    // Total items
    $total_items = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status = 'active'")->fetchColumn();
    
    // Low stock items
    $low_stock_items = $pdo->query("
        SELECT COUNT(*) 
        FROM inventory_items 
        WHERE current_quantity <= reorder_level
        AND status = 'active'
    ")->fetchColumn();
    
    // Expiring items
    $expiring_items = $pdo->query("
        SELECT COUNT(*) 
        FROM inventory_items 
        WHERE expiry_date IS NOT NULL 
        AND expiry_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
        AND status = 'active'
    ")->fetchColumn();
    
    // Pending stock requests
    $pending_requests = 0;
    $stmt = $pdo->query("SHOW TABLES LIKE 'stock_requests'");
    if ($stmt->rowCount() > 0) {
        $pending_requests = $pdo->query("SELECT COUNT(*) FROM stock_requests WHERE status = 'pending'")->fetchColumn();
    }

} catch(PDOException $e) {
    error_log("Error fetching inventory summary data: " . $e->getMessage());
    $total_items = $low_stock_items = $expiring_items = $pending_requests = 0;
}

// Fetch stock requests
$stockRequests = [];
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'stock_requests'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT sr.*, i.item_name, i.unit_of_measure, i.current_quantity,
                   u.username as requested_by_name
            FROM stock_requests sr
            JOIN inventory_items i ON sr.item_id = i.id
            LEFT JOIN users u ON sr.requested_by = u.id
            ORDER BY 
                CASE sr.status 
                    WHEN 'pending' THEN 1 
                    WHEN 'approved' THEN 2 
                    WHEN 'fulfilled' THEN 3 
                    WHEN 'rejected' THEN 4 
                END,
                sr.priority = 'urgent' DESC,
                sr.priority = 'high' DESC,
                sr.priority = 'medium' DESC,
                sr.requested_date DESC
        ");
        $stmt->execute();
        $stockRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    error_log("Error fetching stock requests: " . $e->getMessage());
}

$pageTitle = 'Admin Inventory Management';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-warehouse"></i> Inventory Management</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='add_inventory_item.php'">
                <i class="fas fa-plus"></i> Add New Item
            </button>
            <button class="btn btn-success" onclick="location.href='stock_requests.php'">
                <i class="fas fa-clipboard-check"></i> Manage Stock Requests 
                <?php if ($pending_requests > 0): ?>
                    <span class="badge badge-warning"><?php echo $pending_requests; ?></span>
                <?php endif; ?>
            </button>
            <button class="btn btn-info" onclick="location.href='inventory_reports.php'">
                <i class="fas fa-chart-bar"></i> View Reports
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

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon bg-blue">
                <i class="fas fa-box"></i>
            </div>
            <div class="summary-details">
                <h3>Total Items</h3>
                <p class="summary-count"><?php echo number_format($total_items); ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-orange">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="summary-details">
                <h3>Low Stock Items</h3>
                <p class="summary-count"><?php echo number_format($low_stock_items); ?></p>
                <span class="summary-subtitle">Below reorder level</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-red">
                <i class="fas fa-clock"></i>
            </div>
            <div class="summary-details">
                <h3>Expiring Items</h3>
                <p class="summary-count"><?php echo number_format($expiring_items); ?></p>
                <span class="summary-subtitle">Next 30 days</span>
            </div>
        </div>

        <div class="summary-card <?php echo $pending_requests > 0 ? 'summary-card-highlight' : ''; ?>" onclick="location.href='stock_requests.php'" style="cursor: pointer;">
            <div class="summary-icon bg-purple">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <div class="summary-details">
                <h3>Pending Requests</h3>
                <p class="summary-count"><?php echo number_format($pending_requests); ?></p>
                <span class="summary-subtitle">Awaiting review</span>
            </div>
        </div>
    </div>

    <!-- Feature Grid based on Functional Requirements -->
    <div class="features-grid">
        <!-- FR1: Inventory Details - Blue Theme -->
        <div class="feature-card inventory-details">
            <h3><i class="fas fa-clipboard-list"></i> Inventory Details</h3>
            <ul>
                <li onclick="location.href='item_details.php'">
                    <div class="menu-item">
                        <i class="fas fa-tag"></i>
                        <div class="menu-content">
                            <span class="menu-title">Item Details</span>
                            <span class="menu-desc">Manage tools, seeds, pesticides, feeds</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='categories.php'">
                    <div class="menu-item">
                        <i class="fas fa-sitemap"></i>
                        <div class="menu-content">
                            <span class="menu-title">Categories</span>
                            <span class="menu-desc">Item categorization and organization</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='sku_management.php'">
                    <div class="menu-item">
                        <i class="fas fa-barcode"></i>
                        <div class="menu-content">
                            <span class="menu-title">SKU Management</span>
                            <span class="menu-desc">Stock Keeping Unit assignment</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- FR3 & FR4: Procurement & Quality - Green Theme -->
        <div class="feature-card procurement-quality">
            <h3><i class="fas fa-truck-loading"></i> Procurement & Quality</h3>
            <ul>
                <li onclick="location.href='supplier_management.php'">
                    <div class="menu-item">
                        <i class="fas fa-handshake"></i>
                        <div class="menu-content">
                            <span class="menu-title">Supplier Management</span>
                            <span class="menu-desc">Supplier info and delivery history</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='batch_tracking.php'">
                    <div class="menu-item">
                        <i class="fas fa-box-open"></i>
                        <div class="menu-content">
                            <span class="menu-title">Batch Tracking</span>
                            <span class="menu-desc">Batch numbers and quality monitoring</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='expiry_tracking.php'">
                    <div class="menu-item">
                        <i class="fas fa-calendar-times"></i>
                        <div class="menu-content">
                            <span class="menu-title">Expiry Tracking</span>
                            <span class="menu-desc">Expiry dates and quality checks</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- FR5 & FR6: Usage & Financial - Orange Theme -->
        <div class="feature-card usage-financial">
            <h3><i class="fas fa-chart-line"></i> Usage & Financial</h3>
            <ul>
                <li onclick="location.href='usage_tracking.php'">
                    <div class="menu-item">
                        <i class="fas fa-tasks"></i>
                        <div class="menu-content">
                            <span class="menu-title">Usage Tracking</span>
                            <span class="menu-desc">Consumption records and allocation</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='asset_checkup.php'">
                    <div class="menu-item">
                        <i class="fas fa-tools"></i>
                        <div class="menu-content">
                            <span class="menu-title">Asset Checkup</span>
                            <span class="menu-desc">Equipment maintenance records</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='financial_data.php'">
                    <div class="menu-item">
                        <i class="fas fa-dollar-sign"></i>
                        <div class="menu-content">
                            <span class="menu-title">Financial Data</span>
                            <span class="menu-desc">Costs and revenue tracking</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- FR7, FR8 & FR9: Management Tools - Purple Theme -->
        <div class="feature-card management-tools">
            <h3><i class="fas fa-cogs"></i> Management Tools</h3>
            <ul>
                <li onclick="location.href='waste_management.php'">
                    <div class="menu-item">
                        <i class="fas fa-trash"></i>
                        <div class="menu-content">
                            <span class="menu-title">Waste Management</span>
                            <span class="menu-desc">Track losses and damages</span>
                        </div>
                    </div>
                </li>
                <li onclick="location.href='reports_analysis.php'">
                    <div class="menu-item">
                        <i class="fas fa-chart-bar"></i>
                        <div class="menu-content">
                            <span class="menu-title">Reports & Analysis</span>
                            <span class="menu-desc">Inventory analytics and forecasting</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Highlight pending requests card to draw attention
    <?php if ($pending_requests > 0): ?>
        setTimeout(function() {
            $('.summary-card-highlight').addClass('animated-highlight');
        }, 1000);
    <?php endif; ?>
});
</script>

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
    
    /* Highlight for pending requests */
    .summary-card-highlight {
        border: 2px solid #ffc107;
        box-shadow: 0 0 15px rgba(255, 193, 7, 0.3);
    }
    
    .animated-highlight {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { box-shadow: 0 0 15px rgba(255, 193, 7, 0.3); }
        50% { box-shadow: 0 0 25px rgba(255, 193, 7, 0.6); }
        100% { box-shadow: 0 0 15px rgba(255, 193, 7, 0.3); }
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
    .bg-purple { background: #9b59b6 !important; }
    
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
    
    /* Card Themes - inventory specific colors */
    .inventory-details { border-top: 4px solid #3498db; }
    .procurement-quality { border-top: 4px solid #2ecc71; }
    .usage-financial { border-top: 4px solid #f39c12; }
    .management-tools { border-top: 4px solid #9b59b6; }

    /* Header Icons Colors */
    .inventory-details h3 i { color: #3498db; }
    .procurement-quality h3 i { color: #2ecc71; }
    .usage-financial h3 i { color: #f39c12; }
    .management-tools h3 i { color: #9b59b6; }

    /* Card Hover Effects */
    .inventory-details:hover { box-shadow: 0 6px 12px rgba(52, 152, 219, 0.2); }
    .procurement-quality:hover { box-shadow: 0 6px 12px rgba(46, 204, 113, 0.2); }
    .usage-financial:hover { box-shadow: 0 6px 12px rgba(243, 156, 18, 0.2); }
    .management-tools:hover { box-shadow: 0 6px 12px rgba(155, 89, 182, 0.2); }

    /* Theme-specific hover backgrounds */
    .inventory-details .menu-item:hover { background: #3498db; }
    .procurement-quality .menu-item:hover { background: #2ecc71; }
    .usage-financial .menu-item:hover { background: #f39c12; }
    .management-tools .menu-item:hover { background: #9b59b6; }

    /* Stock Requests Table Styling */
    .table-warning {
        background-color: rgba(255, 193, 7, 0.1);
    }
    
    .table-warning:hover {
        background-color: rgba(255, 193, 7, 0.2);
    }
    
    /* Badge improvements */
    .badge {
        font-size: 0.75em;
        padding: 0.35em 0.65em;
        font-weight: 500;
    }
    
    /* Button group styling */
    .btn-group .btn {
        margin-right: 2px;
    }
    
    .btn-group .btn:last-child {
        margin-right: 0;
    }
    
    /* Page header styling */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e9ecef;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
    }
    
    .action-buttons .badge {
        position: relative;
        top: -2px;
        margin-left: 5px;
    }

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
    
    /* Modal improvements */
    .modal-header {
        border-bottom: 1px solid #dee2e6;
    }
    
    .modal-footer {
        border-top: 1px solid #dee2e6;
    }
    
    .alert {
        border-radius: 6px;
        border: none;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    /* Request priority and status indicators */
    .badge-danger.pulse {
        animation: pulse-red 1.5s infinite;
    }
    
    @keyframes pulse-red {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    
    /* Responsive design improvements */
    @media (max-width: 768px) {
        .summary-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .features-grid {
            grid-template-columns: 1fr;
        }
        
        .action-buttons {
            flex-direction: column;
            gap: 5px;
        }
        
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .page-header h2 {
            margin-bottom: 15px;
        }
        
        .table-responsive {
            font-size: 0.9rem;
        }
        
        .btn-group {
            flex-direction: column;
        }
        
        .btn-group .btn {
            margin-bottom: 2px;
            margin-right: 0;
        }
    }
    
    @media (max-width: 576px) {
        .summary-grid {
            grid-template-columns: 1fr;
        }
        
        .summary-card {
            padding: 15px;
        }
        
        .summary-icon {
            width: 50px;
            height: 50px;
            margin-right: 10px;
        }
        
        .summary-icon i {
            font-size: 20px;
        }
        
        .summary-count {
            font-size: 24px;
        }
    }
    
    /* Print styles */
    @media print {
        .action-buttons,
        .btn,
        .modal {
            display: none !important;
        }
        
        .summary-card {
            break-inside: avoid;
        }
        
        .table {
            font-size: 12px;
        }
        
        .page-header {
            border-bottom: 2px solid #000;
            margin-bottom: 20px;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>