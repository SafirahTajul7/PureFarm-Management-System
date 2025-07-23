<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Handle direct status update
if (isset($_POST['update_status']) && isset($_POST['staff_id']) && isset($_POST['status'])) {
    $staff_id = $_POST['staff_id'];
    $status = $_POST['status'];
    
    // Validate status value
    if (in_array($status, ['active', 'inactive', 'on-leave'])) {
        try {
            // Update the status in the database
            $stmt = $pdo->prepare("UPDATE staff SET status = ? WHERE id = ?");
            $stmt->execute([$status, $staff_id]);
            
            // Set success message for display
            $update_message = "Status updated successfully";
            $update_success = true;
        } catch (PDOException $e) {
            // Set error message for display
            $update_message = "Error updating status: " . $e->getMessage();
            $update_success = false;
            error_log("Error updating staff status: " . $e->getMessage());
        }
    }
}

// Initialize filters
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Base query
$query = "
    SELECT s.*, r.role_name 
    FROM staff s
    LEFT JOIN roles r ON s.role_id = r.id
    WHERE 1=1
";

// Apply filters
$params = [];

if ($role_filter != 'all') {
    $query .= " AND s.role_id = :role_id";
    $params[':role_id'] = $role_filter;
}

if (!empty($search)) {
    // Improved search to handle full names and be more flexible
    $query .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR 
                   CONCAT(s.first_name, ' ', s.last_name) LIKE :search OR
                   s.email LIKE :search OR r.role_name LIKE :search)";
    $params[':search'] = "%$search%";
}

// Order by name
$query .= " ORDER BY s.last_name, s.first_name";

// Get staff data with filters
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching staff: " . $e->getMessage());
    $staff_members = [];
}

// Get roles for filter dropdown
try {
    $roles = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching roles: " . $e->getMessage());
    $roles = [];
}

// Count active staff
try {
    $active_count = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn();
} catch(PDOException $e) {
    error_log("Error counting active staff: " . $e->getMessage());
    $active_count = 0;
}

$pageTitle = 'Staff Directory';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-users"></i> Staff Directory</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='add_staff.php'">
                <i class="fas fa-user-plus"></i> Add New Staff
            </button>
            <button class="btn btn-secondary" onclick="location.href='staff_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Staff Management
            </button>
        </div>
    </div>

    <?php if (isset($update_message)): ?>
        <div class="alert alert-<?php echo $update_success ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <?php echo $update_message; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="summary-row">
        <div class="summary-card">
            <div class="summary-icon bg-blue">
                <i class="fas fa-users"></i>
            </div>
            <div class="summary-details">
                <h3>Total Active Staff</h3>
                <p class="summary-count"><?php echo $active_count; ?></p>
            </div>
        </div>
    </div>

    <!-- Filter Panel -->
    <div class="filter-panel">
        <form method="GET" action="staff_directory.php" class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search by name, email, or role" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="role">Filter by Role</label>
                    <select class="form-control" id="role" name="role">
                        <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>" <?php echo $role_filter == $role['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <a href="staff_directory.php" class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Staff List -->
    <div class="data-table-container">
        <?php if (empty($staff_members)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No staff members found with the current filters.
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Staff Name</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Hire Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_members as $staff): ?>
                        <tr class="<?php echo $staff['status'] == 'inactive' ? 'inactive-row' : ($staff['status'] == 'on-leave' ? 'on-leave-row' : ''); ?>">
                            <td><?php echo htmlspecialchars($staff['staff_id']); ?></td>
                            <td>
                                <div class="staff-name">
                                    <?php if (!empty($staff['profile_image'])): ?>
                                        <img src="uploads/staff/<?php echo htmlspecialchars($staff['profile_image']); ?>" alt="Profile" class="profile-thumbnail">
                                    <?php else: ?>
                                        <div class="profile-icon">
                                            <?php echo strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($staff['email']); ?></div>
                                <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($staff['phone']); ?></div>
                            </td>
                            <td>
                                <span class="badge role-badge">
                                    <?php echo htmlspecialchars($staff['role_name']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $current_status = $staff['status'] ?? 'active';
                                $status_classes = [
                                    'active' => 'status-active',
                                    'inactive' => 'status-inactive',
                                    'on-leave' => 'status-on-leave'
                                ];
                                $status_class = $status_classes[$current_status] ?? 'status-active';
                                ?>
                                <form method="post" action="staff_directory.php" class="status-form">
                                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="status" class="form-control status-select <?php echo $status_class; ?>" onchange="this.form.submit()">
                                        <option value="active" <?php echo $current_status == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $current_status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="on-leave" <?php echo $current_status == 'on-leave' ? 'selected' : ''; ?>>On Leave</option>
                                    </select>
                                </form>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($staff['hire_date'])); ?></td>
                            <td class="actions">
                                <a href="view_staff.php?id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit_staff.php?id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Custom styles for staff directory */
    .staff-name {
        display: flex;
        align-items: center;
    }
    
    .profile-thumbnail {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 10px;
        object-fit: cover;
    }
    
    .profile-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 10px;
        background-color: #3498db;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    .role-badge {
        background-color: #3498db;
        color: white;
        padding: 5px 10px;
        border-radius: 15px;
    }
    
    .inactive-row {
        background-color: #f8f9fa;
        color: #6c757d;
    }
    
    .on-leave-row {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .status-select {
        width: 100%;
        max-width: 120px;
        font-size: 14px;
        padding: 4px 8px;
        border-radius: 4px;
    }
    
    /* Status-specific colors */
    .status-active {
        background-color: #28a745;
        color: white;
    }
    
    .status-inactive {
        background-color: #dc3545;
        color: white;
    }
    
    .status-on-leave {
        background-color: #ffc107;
        color: #212529;
    }
    
    .actions .btn {
        margin-right: 5px;
    }
</style>

<?php include 'includes/footer.php'; ?>