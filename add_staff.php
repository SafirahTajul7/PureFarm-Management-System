<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Check if staff table exists and has the necessary structure
try {
    // Check if staff table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'staff'")->rowCount() > 0;
    
    if (!$tableExists) {
        // Redirect to db_check.php if table doesn't exist
        header('Location: db_check.php');
        exit;
    }
    
    // Check if email column exists in staff table
    $emailColumnExists = $pdo->query("SHOW COLUMNS FROM staff LIKE 'email'")->rowCount() > 0;
    
    if (!$emailColumnExists) {
        // Redirect to db_check.php if email column doesn't exist
        header('Location: db_check.php');
        exit;
    }
} catch (PDOException $e) {
    // If there's an error, redirect to db_check.php
    header('Location: db_check.php');
    exit;
}

// Get roles for dropdown
try {
    $roleQuery = "SELECT id, role_name FROM roles ORDER BY role_name";
    $roleStmt = $pdo->query($roleQuery);
    $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching roles: " . $e->getMessage());
    $roles = [];
    
    // If there's an error with roles, create a default role if none exist
    try {
        $roleCountStmt = $pdo->query("SELECT COUNT(*) FROM roles");
        $roleCount = $roleCountStmt->fetchColumn();
        
        if ($roleCount == 0) {
            // Insert a default role
            $pdo->exec("INSERT INTO roles (role_name, description) VALUES ('Farm Staff', 'Default farm staff role')");
            
            // Fetch the role again
            $roleStmt = $pdo->query($roleQuery);
            $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e2) {
        error_log("Error creating default role: " . $e2->getMessage());
    }
}

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $required_fields = ['first_name', 'last_name', 'email', 'phone', 'role_id', 'hire_date'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Please enter " . str_replace('_', ' ', $field);
        }
    }
    
    // Validate email format
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    // Check if email is already in use - with error handling
    if (!empty($_POST['email']) && empty($errors)) {
        try {
            $checkEmailSql = "SELECT COUNT(*) FROM staff WHERE email = ?";
            $stmt = $pdo->prepare($checkEmailSql);
            $stmt->execute([$_POST['email']]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Email is already in use by another staff member";
            }
        } catch(PDOException $e) {
            error_log("Error checking email uniqueness: " . $e->getMessage());
            // Continue without the email check rather than stopping the whole process
            // Just log the error but don't add it to user-facing errors
        }
    }
    
    // Check if staff_id is already in use (if provided) - with error handling
    if (!empty($_POST['staff_id'])) {
        try {
            $checkStaffIdSql = "SELECT COUNT(*) FROM staff WHERE staff_id = ?";
            $stmt = $pdo->prepare($checkStaffIdSql);
            $stmt->execute([$_POST['staff_id']]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Staff ID is already in use";
            }
        } catch(PDOException $e) {
            error_log("Error checking staff_id uniqueness: " . $e->getMessage());
            // Continue without the staff_id check
        }
    }
    
    // Process and validate profile image if uploaded
    $profile_image = '';
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
                if (!mkdir($upload_dir, 0755, true)) {
                    $errors[] = "Failed to create upload directory";
                }
            }
            
            if (empty($errors)) {
                // Generate unique filename
                $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('staff_') . '.' . $file_extension;
                $target_file = $upload_dir . $filename;
                
                // Upload file
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                    $profile_image = $filename;
                } else {
                    $errors[] = "Failed to upload profile image";
                }
            }
        }
    }
    
    // If no errors, insert staff record
    if (empty($errors)) {
        try {
            // Generate a staff_id if not provided
            $staff_id = !empty($_POST['staff_id']) ? $_POST['staff_id'] : generateStaffId();
            
            // Prepare SQL with explicit column list
            $sql = "INSERT INTO staff (
                staff_id, first_name, last_name, email, phone, address, 
                role_id, hire_date, emergency_contact, notes, profile_image, status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, 'active'
            )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $staff_id,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['address'] ?? '',
                $_POST['role_id'],
                $_POST['hire_date'],
                $_POST['emergency_contact'] ?? '',
                $_POST['notes'] ?? '',
                $profile_image
            ]);
            
            $success = true;
            
            // Clear form data after successful submission
            $_POST = [];
            
        } catch(PDOException $e) {
            error_log("Error creating staff record: " . $e->getMessage());
            $errors[] = "An error occurred while creating the staff record. Please check the database structure.";
        }
    }
}

// Function to generate unique staff ID
function generateStaffId() {
    global $pdo;
    
    // Format: PF-[YY]-[XXXX] where YY is current year and XXXX is sequential number
    $year = date('y');
    $prefix = "PF-{$year}-";
    
    try {
        // Get the highest sequential number for the current year
        $stmt = $pdo->prepare("
            SELECT MAX(CAST(SUBSTRING(staff_id, 7) AS UNSIGNED)) 
            FROM staff 
            WHERE staff_id LIKE :prefix
        ");
        $stmt->execute([':prefix' => $prefix . '%']);
        $max_num = $stmt->fetchColumn();
        
        // Start from 1 if no records found for this year
        $next_num = $max_num ? $max_num + 1 : 1;
        
        // Format with leading zeros to ensure 4 digits
        return $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
    } catch(PDOException $e) {
        error_log("Error generating staff ID: " . $e->getMessage());
        // Fallback to timestamp-based ID if database query fails
        return $prefix . date('His');
    }
}

$pageTitle = 'Add Staff';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-user-plus"></i> Add New Staff</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='staff_directory.php'">
                <i class="fas fa-arrow-left"></i> Back to Staff Directory
            </button>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Staff member added successfully!
            <a href="staff_directory.php" class="alert-link">View Staff Directory</a>
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
        <div class="card-header">
            <h3>Staff Information</h3>
        </div>
        <div class="card-body">
            <form action="add_staff.php" method="POST" enctype="multipart/form-data">
                <div class="row">
                    <!-- Basic Information -->
                    <div class="col-md-6">
                        <h4>Basic Information</h4>
                        
                        <div class="form-group">
                            <label for="staff_id">Staff ID (Optional)</label>
                            <input type="text" class="form-control" id="staff_id" name="staff_id" 
                                placeholder="Leave blank for auto-generation" 
                                value="<?php echo isset($_POST['staff_id']) ? htmlspecialchars($_POST['staff_id']) : ''; ?>">
                            <small class="form-text text-muted">If left blank, a unique ID will be generated automatically.</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="first_name">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required
                                        value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="last_name">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required
                                        value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required
                                value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="role_id">Role *</label>
                            <select class="form-control" id="role_id" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php if (!empty($roles)): ?>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" <?php echo (isset($_POST['role_id']) && $_POST['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="1">Farm Staff</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="hire_date">Hire Date *</label>
                            <input type="date" class="form-control" id="hire_date" name="hire_date" required
                                value="<?php echo isset($_POST['hire_date']) ? htmlspecialchars($_POST['hire_date']) : date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="col-md-6">
                        <h4>Additional Information</h4>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact">Emergency Contact</label>
                            <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                placeholder="Name and phone number" 
                                value="<?php echo isset($_POST['emergency_contact']) ? htmlspecialchars($_POST['emergency_contact']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="profile_image">Profile Image</label>
                            <input type="file" class="form-control-file" id="profile_image" name="profile_image">
                            <small class="form-text text-muted">Maximum file size: 5MB. Accepted formats: JPG, PNG, GIF</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Staff Member
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset Form
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Form validation
        $('form').submit(function(event) {
            let isValid = true;
            
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
                alert('Please fill all required fields correctly');
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
</style>

<?php include 'includes/footer.php'; ?>