<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Get supplier ID from request
$supplierId = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

if ($supplierId <= 0) {
    echo '<div class="alert alert-danger">Invalid supplier ID.</div>';
    exit;
}

try {
    // Fetch supplier details
    $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE id = ?");
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$supplier) {
        echo '<div class="alert alert-danger">Supplier not found.</div>';
        exit;
    }
    
    // Fetch delivery history
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.purchase_date, 
            p.delivery_date, 
            p.status,
            p.expected_delivery_date,
            COUNT(pi.id) as item_count, 
            SUM(pi.quantity) as total_quantity,
            SUM(pi.quantity * pi.unit_price) as total_cost
        FROM purchases p
        LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
        WHERE p.supplier_id = ?
        GROUP BY p.id
        ORDER BY p.purchase_date DESC
        LIMIT 10
    ");
    $stmt->execute([$supplierId]);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($deliveries)) {
        echo '<div class="alert alert-info">No delivery history found for this supplier.</div>';
        exit;
    }
    
    // Display delivery history
    ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Purchase ID</th>
                    <th>Purchase Date</th>
                    <th>Expected Delivery</th>
                    <th>Actual Delivery</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th>Total Quantity</th>
                    <th>Total Cost</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deliveries as $delivery): ?>
                    <tr>
                        <td><?php echo $delivery['id']; ?></td>
                        <td><?php echo date('d-m-Y', strtotime($delivery['purchase_date'])); ?></td>
                        <td>
                            <?php echo $delivery['expected_delivery_date'] ? date('d-m-Y', strtotime($delivery['expected_delivery_date'])) : 'N/A'; ?>
                        </td>
                        <td>
                            <?php echo $delivery['delivery_date'] ? date('d-m-Y', strtotime($delivery['delivery_date'])) : 'Pending'; ?>
                        </td>
                        <td>
                            <?php 
                                $statusClass = '';
                                switch($delivery['status']) {
                                    case 'delivered':
                                        $statusClass = 'badge badge-success';
                                        break;
                                    case 'pending':
                                        $statusClass = 'badge badge-warning';
                                        break;
                                    case 'delayed':
                                        $statusClass = 'badge badge-danger';
                                        break;
                                    default:
                                        $statusClass = 'badge badge-secondary';
                                }
                            ?>
                            <span class="<?php echo $statusClass; ?>"><?php echo ucfirst($delivery['status']); ?></span>
                        </td>
                        <td><?php echo $delivery['item_count']; ?></td>
                        <td><?php echo $delivery['total_quantity']; ?></td>
                        <td>RM <?php echo number_format($delivery['total_cost'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    
} catch(PDOException $e) {
    error_log("Error fetching delivery history: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while retrieving delivery history.</div>';
}
?>