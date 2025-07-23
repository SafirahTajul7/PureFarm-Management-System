<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$batchId = isset($_GET['id']) ? $_GET['id'] : null;
$batch = null;

// Validate batch ID
if (!$batchId) {
    header('Location: batch_tracking.php');
    exit;
}

// Fetch batch details
try {
    $stmt = $pdo->prepare("
        SELECT b.*, i.item_name, i.sku, i.unit_of_measure, s.name as supplier_name 
        FROM inventory_batches b
        JOIN inventory_items i ON b.item_id = i.id
        LEFT JOIN suppliers s ON b.supplier_id = s.id
        WHERE b.id = ?
    ");
    $stmt->execute([$batchId]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$batch) {
        header('Location: batch_tracking.php');
        exit;
    }
} catch(PDOException $e) {
    error_log("Error fetching batch: " . $e->getMessage());
    $errorMsg = "Failed to load batch details.";
}

$pageTitle = 'View Batch Details';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-boxes"></i> Batch Details</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='batch_tracking.php'">
                <i class="fas fa-arrow-left"></i> Back to Batches
            </button>
            <button class="btn btn-primary" onclick="location.href='edit_batch.php?id=<?php echo $batchId; ?>'">
                <i class="fas fa-edit"></i> Edit Batch
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
        <!-- Batch Details Card -->
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Batch Information</h5>
                    <span class="badge <?php 
                        switch($batch['status']) {
                            case 'active': echo 'badge-success'; break;
                            case 'quarantine': echo 'badge-warning'; break;
                            case 'consumed': echo 'badge-info'; break;
                            case 'expired': echo 'badge-danger'; break;
                            case 'discarded': echo 'badge-dark'; break;
                            default: echo 'badge-secondary';
                        }
                    ?> p-2">
                        <?php echo ucfirst($batch['status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th class="w-40">Batch Number:</th>
                                    <td><?php echo htmlspecialchars($batch['batch_number']); ?></td>
                                </tr>
                                <tr>
                                    <th>Item:</th>
                                    <td><?php echo htmlspecialchars($batch['item_name']); ?> (<?php echo htmlspecialchars($batch['sku']); ?>)</td>
                                </tr>
                                <tr>
                                    <th>Quantity:</th>
                                    <td><?php echo $batch['quantity'] . ' ' . htmlspecialchars($batch['unit_of_measure']); ?></td>
                                </tr>
                                <tr>
                                    <th>Manufacturing Date:</th>
                                    <td><?php echo htmlspecialchars($batch['manufacturing_date'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Expiry Date:</th>
                                    <td>
                                        <?php 
                                            if (!empty($batch['expiry_date'])) {
                                                $expiryDate = new DateTime($batch['expiry_date']);
                                                $today = new DateTime();
                                                $interval = $today->diff($expiryDate);
                                                $daysRemaining = $expiryDate > $today ? $interval->days : -$interval->days;
                                                
                                                $expiryClass = '';
                                                if ($daysRemaining < 0) {
                                                    $expiryClass = 'text-danger';
                                                } elseif ($daysRemaining <= 30) {
                                                    $expiryClass = 'text-warning';
                                                }
                                                
                                                echo '<span class="'.$expiryClass.'">' . htmlspecialchars($batch['expiry_date']);
                                                if ($daysRemaining < 0) {
                                                    echo ' (Expired)';
                                                } elseif ($daysRemaining <= 30) {
                                                    echo ' (' . $daysRemaining . ' days left)';
                                                }
                                                echo '</span>';
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th class="w-40">Received Date:</th>
                                    <td><?php echo htmlspecialchars($batch['received_date']); ?></td>
                                </tr>
                                <tr>
                                    <th>Supplier:</th>
                                    <td><?php echo htmlspecialchars($batch['supplier_name'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Purchase Order:</th>
                                    <td><?php echo htmlspecialchars($batch['purchase_order_id'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Cost Per Unit:</th>
                                    <td>
                                        <?php 
                                            if (!empty($batch['cost_per_unit'])) {
                                                echo '$' . number_format($batch['cost_per_unit'], 2);
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Total Cost:</th>
                                    <td>
                                        <?php 
                                            if (!empty($batch['cost_per_unit'])) {
                                                $totalCost = $batch['quantity'] * $batch['cost_per_unit'];
                                                echo '$' . number_format($totalCost, 2);
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!empty($batch['notes'])): ?>
                        <div class="mt-3">
                            <h6>Notes:</h6>
                            <div class="p-2 bg-light rounded">
                                <?php echo nl2br(htmlspecialchars($batch['notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .table th.w-40 {
        width: 40%;
    }
</style>

<?php include 'includes/footer.php'; ?>