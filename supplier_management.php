<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$error = '';
$success = '';
$suppliers = [];

// Handle supplier deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("UPDATE suppliers SET status = 'inactive' WHERE id = ?");
        if ($stmt->execute([$_GET['delete']])) {
            $success = "Supplier marked as inactive successfully.";
        } else {
            $error = "Failed to remove supplier.";
        }
    } catch(PDOException $e) {
        error_log("Error deleting supplier: " . $e->getMessage());
        $error = "An error occurred while processing your request.";
    }
}

// Handle form submission for adding/editing supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? trim($_POST['id']) : null;
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    
    // Validate input
    if (empty($name) || empty($phone) || empty($email)) {
        $error = "Name, phone, and email are required fields.";
    } else {
        try {
            if ($id) {
                // Update existing supplier
                $stmt = $pdo->prepare("
                    UPDATE suppliers 
                    SET name = ?, phone = ?, email = ?, address = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                if ($stmt->execute([$name, $phone, $email, $address, $id])) {
                    $success = "Supplier updated successfully.";
                } else {
                    $error = "Failed to update supplier.";
                }
            } else {
                // Add new supplier
                $stmt = $pdo->prepare("
                    INSERT INTO suppliers (name, phone, email, address, status, created_at) 
                    VALUES (?, ?, ?, ?, 'active', NOW())
                ");
                if ($stmt->execute([$name, $phone, $email, $address])) {
                    $success = "Supplier added successfully.";
                } else {
                    $error = "Failed to add supplier.";
                }
            }
        } catch(PDOException $e) {
            error_log("Error adding/updating supplier: " . $e->getMessage());
            $error = "An error occurred while processing your request.";
        }
    }
}

// Fetch all active suppliers
try {
    $stmt = $pdo->query("
        SELECT id, name, phone, email, address, status, 
               DATE_FORMAT(created_at, '%d-%m-%Y') as formatted_created_at 
        FROM suppliers 
        WHERE status = 'active' 
        ORDER BY name ASC
    ");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching suppliers: " . $e->getMessage());
    $error = "An error occurred while retrieving suppliers.";
}

// Fetch supplier delivery history
function getSupplierDeliveryHistory($supplierId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT p.id, p.purchase_date, p.delivery_date, p.status,
                   COUNT(pi.id) as item_count, SUM(pi.quantity) as total_quantity
            FROM purchases p
            JOIN purchase_items pi ON p.id = pi.purchase_id
            WHERE p.supplier_id = ?
            GROUP BY p.id
            ORDER BY p.purchase_date DESC
            LIMIT 10
        ");
        $stmt->execute([$supplierId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching supplier delivery history: " . $e->getMessage());
        return [];
    }
}

$pageTitle = 'Supplier Management';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-handshake"></i> Supplier Management</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" data-toggle="modal" data-target="#supplierModal">
                <i class="fas fa-plus"></i> Add New Supplier
            </button>
            <button class="btn btn-secondary" onclick="location.href='manage_deliveries.php'">
                <i class="fas fa-truck"></i> Manage Deliveries
            </button>
            
            <button class="btn btn-secondary" onclick="location.href='inventory.php'">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Suppliers List -->
    <div class="card">
        <div class="card-header">
            <h3>Suppliers Directory</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="suppliersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Created On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($supplier['id']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['address']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['formatted_created_at']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info view-delivery-history" data-id="<?php echo $supplier['id']; ?>" data-name="<?php echo htmlspecialchars($supplier['name']); ?>">
                                        <i class="fas fa-history"></i> Delivery History
                                    </button>
                                    <button class="btn btn-sm btn-primary edit-supplier" 
                                        data-id="<?php echo $supplier['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($supplier['name']); ?>"
                                        data-phone="<?php echo htmlspecialchars($supplier['phone']); ?>"
                                        data-email="<?php echo htmlspecialchars($supplier['email']); ?>"
                                        data-address="<?php echo htmlspecialchars($supplier['address']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="?delete=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this supplier?')">
                                        <i class="fas fa-trash"></i> Remove
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1" role="dialog" aria-labelledby="supplierModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supplierModalLabel">Add New Supplier</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id" id="supplier_id">
                    <div class="form-group">
                        <label for="name">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="phone" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delivery History Modal -->
<div class="modal fade" id="deliveryHistoryModal" tabindex="-1" role="dialog" aria-labelledby="deliveryHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deliveryHistoryModalLabel">Delivery History</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="deliveryHistoryContent">
                    <!-- Delivery history will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <!-- Remove the View Full Purchase History button that appears here -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    console.log("Document ready - initializing supplier management");

    // Initialize DataTable
    if ($.fn.DataTable) {
        $('#suppliersTable').DataTable({
            "pageLength": 10,
            "ordering": true,
            "responsive": true
        });
    } else {
        console.error("DataTable plugin not found");
    }

    // Edit supplier - explicit binding
    $(document).on('click', '.edit-supplier', function() {
        console.log("Edit supplier clicked");
        var id = $(this).data('id');
        var name = $(this).data('name');
        var phone = $(this).data('phone');
        var email = $(this).data('email');
        var address = $(this).data('address');
        
        $('#supplierModalLabel').text('Edit Supplier');
        $('#supplier_id').val(id);
        $('#name').val(name);
        $('#phone').val(phone);
        $('#email').val(email);
        $('#address').val(address);
        
        $('#supplierModal').modal('show');
    });

    // Reset modal when closed
    $('#supplierModal').on('hidden.bs.modal', function () {
        $('#supplierModalLabel').text('Add New Supplier');
        $('#supplier_id').val('');
        $('#name').val('');
        $('#phone').val('');
        $('#email').val('');
        $('#address').val('');
    });

    // View delivery history - explicit binding
    $(document).on('click', '.view-delivery-history', function() {
        console.log("View delivery history clicked");
        var supplierId = $(this).data('id');
        var supplierName = $(this).data('name');
        
        $('#deliveryHistoryModalLabel').text('Delivery History - ' + supplierName);
        $('#deliveryHistoryContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading delivery history...</p></div>');
        $('#deliveryHistoryModal').modal('show');
        
        // AJAX request to get delivery history
        $.ajax({
            url: 'get_delivery_history.php',
            type: 'GET',
            data: {supplier_id: supplierId},
            success: function(response) {
                $('#deliveryHistoryContent').html(response);
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error: " + status + " - " + error);
                $('#deliveryHistoryContent').html('<div class="alert alert-danger">Failed to load delivery history. Error: ' + error + '</div>');
            }
        });
    });
});
</script>

<?php
// Create a separate file called get_delivery_history.php to handle AJAX requests for delivery history
?>

<?php include 'includes/footer.php'; ?>