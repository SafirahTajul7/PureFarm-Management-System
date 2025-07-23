<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Check if staff ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: staff_directory.php');
    exit;
}

$staff_id = $_GET['id'];

// Get staff details
try {
    $stmt = $pdo->prepare("
        SELECT s.*, r.role_name 
        FROM staff s
        LEFT JOIN roles r ON s.role_id = r.id
        WHERE s.id = :id
    ");
    $stmt->execute([':id' => $staff_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        // Staff not found, redirect to directory
        header('Location: staff_directory.php');
        exit;
    }
} catch(PDOException $e) {
    error_log("Error fetching staff details: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while retrieving staff details";
    header('Location: staff_directory.php');
    exit;
}

// Get roles for dropdown
try {
    $roles = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching roles: " . $e->getMessage());
    $roles = [];
}

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $required_fields = ['first_name', 'last_name', 'email', 'phone', 'role_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Please enter " . str_replace('_', ' ', $field);
        }
    }
    
    // Validate email format
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    // Check if email is already in use by another staff member
    if (!empty($_POST['email'])) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE email = :email AND id != :id");
            $stmt->execute([':email' => $_POST['email'], ':id' => $staff_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Email is already in use by another staff member";
            }
        } catch(PDOException $e) {
            error_log("Error checking email uniqueness: " . $e->getMessage());
            $errors[] = "An error occurred while checking email availability";
        }
    }
    
    // Check if staff_id is already in use by another staff member
    if (!empty($_POST['staff_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE staff_id = :staff_id AND id != :id");
            $stmt->execute([':staff_id' => $_POST['staff_id'], ':id' => $staff_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Staff ID is already in use";
            }
        } catch(PDOException $e) {
            error_log("Error checking staff_id uniqueness: " . $e->getMessage());
            $errors[] = "An error occurred while checking Staff ID availability";
        }
    }
    
    // Process and validate profile image if uploaded
    $profile_image = $staff['profile_image']; // Keep existing image by default
    if (!empty($_FILES['profile_image']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
            $errors[] = "Profile image must be a JPG, PNG, or GIF file";
        } elseif ($_FILES['profile_image']['size'] > $max_size) {
            $errors[] = "Profile image size must be less than 5MB";
        } else {
            // Create upload directory if it doesn't exist
            $upload_dir = 'uploads/staff/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('staff_') . '.' . $file_extension;
            $target_file = $upload_dir . $filename;
            
            // Upload file
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                $profile_image = $filename;
                
                // Delete old profile image if exists
                if (!empty($staff['profile_image']) && file_exists($upload_dir . $staff['profile_image'])) {
                    unlink($upload_dir . $staff['profile_image']);
                }
            } else {
                $errors[] = "Failed to upload profile image";
            }
        }
    }
      // If no errors, update staff record
    if (empty($errors)) {
        try {
            // Begin transaction for safer operation
            $pdo->beginTransaction();
              $stmt = $pdo->prepare("
                UPDATE staff SET
                    staff_id = :staff_id,
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    phone = :phone,
                    address = :address,
                    role_id = :role_id,
                    hire_date = :hire_date,
                    emergency_contact = :emergency_contact,
                    notes = :notes,
                    profile_image = :profile_image,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ");
              // Ensure values match database field types
            $params = [
                ':staff_id' => $_POST['staff_id'],
                ':first_name' => $_POST['first_name'],
                ':last_name' => $_POST['last_name'],
                ':email' => $_POST['email'],
                ':phone' => $_POST['phone'],
                ':address' => $_POST['address'] ?? null,
                ':role_id' => (int)$_POST['role_id'], // Ensure role_id is an integer
                ':hire_date' => $_POST['hire_date'],
                ':emergency_contact' => $_POST['emergency_contact'] ?? null,
                ':notes' => $_POST['notes'] ?? null,
                ':profile_image' => $profile_image,
                ':status' => $_POST['status'] ?? 'active', // Include status parameter
                ':id' => (int)$staff_id // Ensure ID is an integer
            ];
            
            $stmt->execute($params);
            
            // Check if activity_logs table exists before logging
            $tableExists = false;
            try {
                $checkTable = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
                $tableExists = ($checkTable && $checkTable->rowCount() > 0);
            } catch(PDOException $e) {
                // Ignore the error, just assume table doesn't exist
                error_log("Error checking for activity_logs table: " . $e->getMessage());
            }
            
            // Only log if table exists
            if ($tableExists) {
                $admin_id = auth()->getUserId();
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, entity_type, entity_id, details, created_at) 
                    VALUES 
                    (:user_id, 'update', 'staff', :staff_id, :details, NOW())
                ");
                
                $log_stmt->execute([
                    ':user_id' => $admin_id,
                    ':staff_id' => $staff_id,
                    ':details' => json_encode([
                        'staff_id' => $_POST['staff_id'],
                        'name' => $_POST['first_name'] . ' ' . $_POST['last_name'],
                        'role_id' => $_POST['role_id']
                    ])
                ]);
            }
              // Commit transaction
            $pdo->commit();
            
            // Log the submitted status for debugging
            error_log("Staff ID: $staff_id - Status updated to: " . $_POST['status']);
            
            $success = true;
            
            // Store success message in session
            session_start();
            $_SESSION['success_message'] = "Staff member updated successfully!";
            $_SESSION['updated_staff_id'] = $staff_id;
            
            // Redirect to prevent form resubmission on refresh
            header("Location: edit_staff.php?id=$staff_id&status_updated=1");
            exit;
            
        } catch(PDOException $e) {
            // Roll back in case of error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            error_log("Error updating staff record: " . $e->getMessage());
            $errors[] = "An error occurred while updating the staff record: " . $e->getMessage();
        }
    }
}

// Check for success message in session
if (!isset($_SESSION)) {
    session_start();
}

if (isset($_SESSION['success_message'])) {
    $success = true;
    unset($_SESSION['success_message']);
}

$pageTitle = 'Edit Staff';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-user-edit"></i> Edit Staff Member</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='staff_directory.php'">
                <i class="fas fa-arrow-left"></i> Back to Staff Directory
            </button>
            <button class="btn btn-info" onclick="location.href='view_staff.php?id=<?php echo $staff_id; ?>'">
                <i class="fas fa-eye"></i> View Profile
            </button>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Staff member updated successfully!
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> Please correct the following errors:
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3>Staff Information</h3>
            <span class="badge <?php echo $staff['status'] == 'active' ? 'badge-success' : ($staff['status'] == 'on-leave' ? 'badge-warning' : 'badge-danger'); ?>">
                <?php echo ucfirst($staff['status']); ?>
            </span>
        </div>
        <div class="card-body">
            <form action="edit_staff.php?id=<?php echo $staff_id; ?>" method="POST" enctype="multipart/form-data">
                <div class="row">
                    <!-- Basic Information -->
                    <div class="col-md-6">
                        <h4>Basic Information</h4>
                        
                        <div class="form-group">
                            <label for="staff_id">Staff ID</label>
                            <input type="text" class="form-control" id="staff_id" name="staff_id" 
                                value="<?php echo htmlspecialchars($staff['staff_id']); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="first_name">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required
                                        value="<?php echo htmlspecialchars($staff['first_name']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="last_name">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required
                                        value="<?php echo htmlspecialchars($staff['last_name']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                value="<?php echo htmlspecialchars($staff['email']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required
                                value="<?php echo htmlspecialchars($staff['phone']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="role_id">Role *</label>
                            <select class="form-control" id="role_id" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" <?php echo ($staff['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                          <div class="form-group">
                            <label for="hire_date">Hire Date *</label>
                            <input type="date" class="form-control" id="hire_date" name="hire_date" required
                                value="<?php echo htmlspecialchars($staff['hire_date']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="active" <?php echo ($staff['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($staff['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="on-leave" <?php echo ($staff['status'] == 'on-leave') ? 'selected' : ''; ?>>On Leave</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="col-md-6">
                        <h4>Additional Information</h4>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($staff['address']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact">Emergency Contact</label>
                            <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                placeholder="Name and phone number" 
                                value="<?php echo htmlspecialchars($staff['emergency_contact']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="profile_image">Profile Image</label>
                            
                            <?php if (!empty($staff['profile_image'])): ?>
                                <div class="current-image mb-2">
                                    <img src="uploads/staff/<?php echo htmlspecialchars($staff['profile_image']); ?>" alt="Profile" class="img-thumbnail" style="max-height: 100px;">
                                    <span class="ml-2">Current image</span>
                                </div>
                            <?php endif; ?>
                            
                            <input type="file" class="form-control-file" id="profile_image" name="profile_image">
                            <small class="form-text text-muted">Maximum file size: 5MB. Accepted formats: JPG, PNG, GIF. Leave blank to keep current image.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($staff['notes']); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>    $(document).ready(function() {
        // Track original status
        var originalStatus = $('#status').val();
        console.log('Original status: ' + originalStatus);
        
        // Form validation
        $('form').submit(function(event) {
            let isValid = true;
            
            // Make sure status is properly included
            var submittedStatus = $('#status').val();
            console.log('Submitting status: ' + submittedStatus);
            
            // Validate required fields
            $('input[required], select[required]').each(function() {
                if ($(this).val() === '') {
                    isValid = false;
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            
            // Email validation
            const email = $('#email').val();
            if (email && !isValidEmail(email)) {
                isValid = false;
                $('#email').addClass('is-invalid');
            }
            
            if (!isValid) {
                event.preventDefault();
                toastr.error('Please correct the errors in the form');
            }
        });
        
        // Helper function for email validation
        function isValidEmail(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }
        
        // Clear validation on input
        $('input, select, textarea').on('input change', function() {
            $(this).removeClass('is-invalid');
        });
    });
</script>

<style>
    .form-actions {
        margin-top: 30px;
        border-top: 1px solid #ddd;
        padding-top: 20px;
        text-align: right;
    }
    
    h4 {
        margin-bottom: 20px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
        color: #3498db;
    }
    
    .card {
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        margin-bottom: 30px;
    }
    
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #eee;
    }
    
    .card-header h3 {
        margin-bottom: 0;
        color: #2c3e50;
    }
    
    .is-invalid {
        border-color: #e74c3c;
    }
    
    .badge-success {
        background-color: #2ecc71;
    }
    
    .badge-warning {
        background-color: #f39c12;
    }
    
    .badge-danger {
        background-color: #e74c3c;
    }
</style>

<?php include 'includes/footer.php'; ?>