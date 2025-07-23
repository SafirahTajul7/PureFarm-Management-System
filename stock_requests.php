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

// Get filter parameters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$priorityFilter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : 'all';

// Build WHERE clause based on filters
$whereConditions = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereConditions[] = "sr.status = ?";
    $params[] = $statusFilter;
}

if ($priorityFilter !== 'all') {
    $whereConditions[] = "sr.priority = ?";
    $params[] = $priorityFilter;
}

if ($dateFilter !== 'all') {
    switch ($dateFilter) {
        case 'today':
            $whereConditions[] = "DATE(sr.requested_date) = CURDATE()";
            break;
        case 'week':
            $whereConditions[] = "sr.requested_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $whereConditions[] = "sr.requested_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Fetch stock requests with filters
$stockRequests = [];
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'stock_requests'");
    if ($stmt->rowCount() > 0) {
        $sql = "
            SELECT sr.*, i.item_name, i.unit_of_measure, i.current_quantity,
                   u.username as requested_by_name
            FROM stock_requests sr
            JOIN inventory_items i ON sr.item_id = i.id
            LEFT JOIN users u ON sr.requested_by = u.id
            $whereClause
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
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $stockRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    error_log("Error fetching stock requests: " . $e->getMessage());
}

// Get summary counts
$totalRequests = count($stockRequests);
$pendingCount = count(array_filter($stockRequests, function($r) { return $r['status'] === 'pending'; }));
$approvedCount = count(array_filter($stockRequests, function($r) { return $r['status'] === 'approved'; }));
$urgentCount = count(array_filter($stockRequests, function($r) { return $r['priority'] === 'urgent'; }));

$pageTitle = 'Stock Requests Management';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-clipboard-check"></i> Stock Requests Management</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='inventory.php'">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </button>
            <button class="btn btn-primary" onclick="exportRequests()">
                <i class="fas fa-download"></i> Export
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
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="summary-details">
                <h3>Total Requests</h3>
                <p class="summary-count"><?php echo $totalRequests; ?></p>
            </div>
        </div>

        <div class="summary-card <?php echo $pendingCount > 0 ? 'summary-card-highlight' : ''; ?>">
            <div class="summary-icon bg-orange">
                <i class="fas fa-clock"></i>
            </div>
            <div class="summary-details">
                <h3>Pending</h3>
                <p class="summary-count"><?php echo $pendingCount; ?></p>
                <span class="summary-subtitle">Need action</span>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="summary-details">
                <h3>Approved</h3>
                <p class="summary-count"><?php echo $approvedCount; ?></p>
                <span class="summary-subtitle">Ready to fulfill</span>
            </div>
        </div>

        <div class="summary-card <?php echo $urgentCount > 0 ? 'summary-card-urgent' : ''; ?>">
            <div class="summary-icon bg-red">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="summary-details">
                <h3>Urgent</h3>
                <p class="summary-count"><?php echo $urgentCount; ?></p>
                <span class="summary-subtitle">High priority</span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row">
                    <div class="col-md-3">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="fulfilled" <?php echo $statusFilter === 'fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="priority">Priority</label>
                        <select name="priority" id="priority" class="form-control">
                            <option value="all" <?php echo $priorityFilter === 'all' ? 'selected' : ''; ?>>All Priority</option>
                            <option value="low" <?php echo $priorityFilter === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $priorityFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $priorityFilter === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo $priorityFilter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date">Date Range</label>
                        <select name="date" id="date" class="form-control">
                            <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stock Requests Table -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-list"></i> Stock Requests</h4>
            <small>Showing: <?php echo count($stockRequests); ?> requests</small>
        </div>
        <div class="card-body">
            <?php if (count($stockRequests) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="requestsTable">
                        <thead class="thead-dark">
                            <tr>
                                <th>Date</th>
                                <th>Requested By</th>
                                <th>Item</th>
                                <th>Current Stock</th>
                                <th>Requested Qty</th>
                                <th>Purpose</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stockRequests as $request): ?>
                                <tr class="<?php echo ($request['status'] === 'pending') ? 'table-warning' : ''; ?> <?php echo ($request['priority'] === 'urgent') ? 'table-urgent' : ''; ?>">
                                    <td>
                                        <small><?php echo date('M d, Y H:i', strtotime($request['requested_date'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['requested_by_name'] ?? 'Unknown'); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($request['item_name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="<?php echo ($request['current_quantity'] <= 0) ? 'text-danger' : ''; ?>">
                                            <?php echo $request['current_quantity']; ?> <?php echo $request['unit_of_measure']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo $request['requested_quantity']; ?> <?php echo $request['unit_of_measure']; ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['purpose']); ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                                switch($request['priority']) {
                                                    case 'low': echo 'badge-secondary'; break;
                                                    case 'medium': echo 'badge-primary'; break;
                                                    case 'high': echo 'badge-warning'; break;
                                                    case 'urgent': echo 'badge-danger pulse'; break;
                                                    default: echo 'badge-secondary';
                                                }
                                            ?>">
                                            <?php echo ucfirst($request['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                                switch($request['status']) {
                                                    case 'pending': echo 'badge-warning'; break;
                                                    case 'approved': echo 'badge-info'; break;
                                                    case 'fulfilled': echo 'badge-success'; break;
                                                    case 'rejected': echo 'badge-danger'; break;
                                                    default: echo 'badge-secondary';
                                                }
                                            ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($request['admin_notes'] ?? $request['notes'] ?? 'N/A'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group-vertical">
                                            <?php if ($request['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-success mb-1" title="Approve Request"
                                                        onclick="processRequest(<?php echo $request['id']; ?>, 'approve', '<?php echo addslashes(htmlspecialchars($request['item_name'])); ?>', '<?php echo $request['requested_quantity']; ?>', '<?php echo $request['unit_of_measure']; ?>')">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-sm btn-danger" title="Reject Request"
                                                        onclick="processRequest(<?php echo $request['id']; ?>, 'reject', '<?php echo addslashes(htmlspecialchars($request['item_name'])); ?>', '<?php echo $request['requested_quantity']; ?>', '<?php echo $request['unit_of_measure']; ?>')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            <?php elseif ($request['status'] === 'approved'): ?>
                                                <button class="btn btn-sm btn-primary mb-1" title="Fulfill Request (Add Stock)"
                                                        onclick="processRequest(<?php echo $request['id']; ?>, 'fulfill', '<?php echo addslashes(htmlspecialchars($request['item_name'])); ?>', '<?php echo $request['requested_quantity']; ?>', '<?php echo $request['unit_of_measure']; ?>')">
                                                    <i class="fas fa-plus-circle"></i> Fulfill
                                                </button>
                                                <button class="btn btn-sm btn-danger" title="Reject Request"
                                                        onclick="processRequest(<?php echo $request['id']; ?>, 'reject', '<?php echo addslashes(htmlspecialchars($request['item_name'])); ?>', '<?php echo $request['requested_quantity']; ?>', '<?php echo $request['unit_of_measure']; ?>')">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-check-circle"></i> Processed
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No stock requests found</h5>
                    <p class="text-muted">Try adjusting your filters or check back later.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Process Request Modal -->
<div class="modal fade" id="processRequestModal" tabindex="-1" role="dialog" aria-labelledby="processRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="processRequestModalLabel">Process Stock Request</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div id="requestDetails" class="mb-3">
                        <!-- Request details will be populated here -->
                    </div>
                    <div class="form-group">
                        <label for="admin_notes">Admin Notes</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" placeholder="Add notes for this decision..."></textarea>
                    </div>
                    <input type="hidden" id="request_id" name="request_id">
                    <input type="hidden" id="action" name="action">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="action_request" class="btn" id="submitButton">Process</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function processRequest(requestId, action, itemName, quantity, unit) {
    $('#request_id').val(requestId);
    $('#action').val(action);
    
    let title, buttonText, buttonClass, details;
    
    switch(action) {
        case 'approve':
            title = 'Approve Stock Request';
            buttonText = 'Approve Request';
            buttonClass = 'btn-success';
            details = `<div class="alert alert-info">
                <strong>Action:</strong> Approve request for <strong>${quantity} ${unit}</strong> of <strong>${itemName}</strong><br>
                <small>This will mark the request as approved. You can fulfill it later by adding stock to inventory.</small>
            </div>`;
            break;
        case 'reject':
            title = 'Reject Stock Request';
            buttonText = 'Reject Request';
            buttonClass = 'btn-danger';
            details = `<div class="alert alert-warning">
                <strong>Action:</strong> Reject request for <strong>${quantity} ${unit}</strong> of <strong>${itemName}</strong><br>
                <small>This request will be marked as rejected and no stock will be added.</small>
            </div>`;
            break;
        case 'fulfill':
            title = 'Fulfill Stock Request';
            buttonText = 'Fulfill Request';
            buttonClass = 'btn-primary';
            details = `<div class="alert alert-success">
                <strong>Action:</strong> Fulfill request by adding <strong>${quantity} ${unit}</strong> to <strong>${itemName}</strong> inventory<br>
                <small>This will automatically increase the stock quantity and mark the request as fulfilled.</small>
            </div>`;
            break;
    }
    
    $('#processRequestModalLabel').text(title);
    $('#requestDetails').html(details);
    $('#submitButton').text(buttonText).removeClass().addClass('btn ' + buttonClass);
    
    $('#processRequestModal').modal('show');
}

function exportRequests() {
    // Create CSV export functionality
    const table = document.getElementById('requestsTable');
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length - 1; j++) { // Exclude actions column
            let text = cols[j].innerText.replace(/"/g, '""');
            row.push('"' + text + '"');
        }
        csv.push(row.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'stock_requests_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

$(document).ready(function() {
    // Auto-refresh every 3 minutes
    setInterval(function() {
        if (!$('.modal').hasClass('show')) {
            window.location.reload();
        }
    }, 180000); // 3 minutes
    
    // Highlight urgent and pending requests
    $('.table-urgent').addClass('animated-pulse');
    $('.summary-card-highlight, .summary-card-urgent').addClass('animated-highlight');
});
</script>

<style>
/* Include all the styles from inventory.php but add some specific ones */
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

.summary-card-highlight {
    border: 2px solid #ffc107;
    box-shadow: 0 0 15px rgba(255, 193, 7, 0.3);
}

.summary-card-urgent {
    border: 2px solid #dc3545;
    box-shadow: 0 0 15px rgba(220, 53, 69, 0.3);
}

.animated-highlight {
    animation: pulse 2s infinite;
}

.animated-pulse {
    animation: pulse-red 1.5s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 15px rgba(255, 193, 7, 0.3); }
    50% { box-shadow: 0 0 25px rgba(255, 193, 7, 0.6); }
    100% { box-shadow: 0 0 15px rgba(255, 193, 7, 0.3); }
}

@keyframes pulse-red {
    0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}

.table-urgent {
    background-color: rgba(220, 53, 69, 0.1);
}

.badge.pulse {
    animation: pulse-red 1.5s infinite;
}

.btn-group-vertical .btn {
    margin-bottom: 3px;
}

.filters-section {
    margin-bottom: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
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

@media (max-width: 768px) {
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .btn-group-vertical .btn {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }
}

@media (max-width: 576px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>