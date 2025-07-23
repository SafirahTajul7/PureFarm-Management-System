<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is admin
auth()->checkAdmin();

// Get reading ID from URL
$reading_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$reading_id) {
    // Redirect to main page if no ID provided
    header("Location: soil_moisture.php");
    exit();
}

// Fetch the moisture reading record with field name
try {
    $stmt = $pdo->prepare("
        SELECT sm.*, f.field_name
        FROM soil_moisture sm
        JOIN fields f ON sm.field_id = f.id
        WHERE sm.id = ?
    ");
    $stmt->execute([$reading_id]);
    $reading = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reading) {
        // Redirect if reading not found
        header("Location: soil_moisture.php");
        exit();
    }
    
} catch(PDOException $e) {
    error_log("Error fetching moisture reading: " . $e->getMessage());
    header("Location: soil_moisture.php");
    exit();
}

// Set page title and include header
$pageTitle = 'View Moisture Reading';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-tint"></i> View Moisture Reading</h2>
        <div>
            <a href="edit_moisture_reading.php?id=<?php echo $reading_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="soil_moisture.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5>Moisture Reading Details</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th>Field</th>
                            <td><?php echo htmlspecialchars($reading['field_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Reading Date</th>
                            <td><?php echo date('F d, Y', strtotime($reading['reading_date'])); ?></td>
                        </tr>
                        <tr>
                            <th>Moisture Percentage</th>
                            <td>
                                <?php 
                                    $moisture = $reading['moisture_percentage'];
                                    $moisture_class = '';
                                    if ($moisture < 30) $moisture_class = 'text-danger';
                                    elseif ($moisture > 70) $moisture_class = 'text-primary';
                                    echo '<span class="' . $moisture_class . '">' . $moisture . '%</span>';
                                    
                                    // Add moisture status
                                    echo ' (';
                                    if ($moisture < 30) echo '<span class="text-danger">Low</span>';
                                    elseif ($moisture > 70) echo '<span class="text-primary">High</span>';
                                    else echo '<span class="text-success">Optimal</span>';
                                    echo ')';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Reading Depth</th>
                            <td><?php echo !empty($reading['reading_depth']) ? htmlspecialchars($reading['reading_depth']) : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <th>Reading Method</th>
                            <td><?php echo !empty($reading['reading_method']) ? htmlspecialchars($reading['reading_method']) : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <th>Notes</th>
                            <td><?php echo !empty($reading['notes']) ? nl2br(htmlspecialchars($reading['notes'])) : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <th>Created At</th>
                            <td><?php echo date('F d, Y h:i A', strtotime($reading['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6>Reading Analysis</h6>
                        </div>
                        <div class="card-body">
                            <div class="moisture-gauge mb-4">
                                <h6 class="text-center mb-3">Moisture Level</h6>
                                <div class="progress" style="height: 30px;">
                                    <?php
                                    $moisture = $reading['moisture_percentage'];
                                    $color = 'bg-success';
                                    
                                    if ($moisture < 30) {
                                        $color = 'bg-danger';
                                    } elseif ($moisture > 70) {
                                        $color = 'bg-primary';
                                    }
                                    ?>
                                    <div class="progress-bar <?php echo $color; ?>" role="progressbar" style="width: <?php echo $moisture; ?>%" aria-valuenow="<?php echo $moisture; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?php echo $moisture; ?>%
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mt-1">
                                    <small class="text-danger">Dry (0%)</small>
                                    <small class="text-success">Optimal (30-70%)</small>
                                    <small class="text-primary">Wet (100%)</small>
                                </div>
                            </div>
                            
                            <div class="recommendation mt-4">
                                <h6>Recommendation:</h6>
                                <?php if ($moisture < 30): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Low Moisture Detected:</strong> Consider irrigating this field to prevent crop stress. Aim for 30-70% moisture for optimal plant growth.
                                </div>
                                <?php elseif ($moisture > 70): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-tint me-2"></i>
                                    <strong>High Moisture Detected:</strong> Avoid additional irrigation. Monitor for signs of waterlogging or disease development in wet conditions.
                                </div>
                                <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Optimal Moisture Level:</strong> Current soil moisture is within the ideal range. Maintain current irrigation practices.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>