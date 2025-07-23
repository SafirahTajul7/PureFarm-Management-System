<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Ensure user is admin
auth()->checkAdmin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get the user ID
        $user_id = auth()->getUserId();
        
        // Update theme preference
        if (isset($_POST['theme_preference'])) {
            $theme = $_POST['theme_preference'];
            $stmt = $pdo->prepare("UPDATE user_settings SET theme_preference = ? WHERE user_id = ?");
            $stmt->execute([$theme, $user_id]);
            
            // Update session theme
            $_SESSION['theme'] = $theme;
        }

        // Update password if provided
        if (!empty($_POST['new_password'])) {
            if ($_POST['new_password'] === $_POST['confirm_password']) {
                $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$new_password, $user_id]);
                $_SESSION['success'] = "Password updated successfully!";
            } else {
                $_SESSION['error'] = "Passwords do not match!";
            }
        }

        // Update language preference
        if (isset($_POST['language'])) {
            $language = $_POST['language'];
            $stmt = $pdo->prepare("UPDATE user_settings SET language = ? WHERE user_id = ?");
            $stmt->execute([$language, $user_id]);
            
            // Update session language
            $_SESSION['language'] = $language;
        }

        if (!isset($_SESSION['error'])) {
            $_SESSION['success'] = "Settings updated successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating settings: " . $e->getMessage();
    }
    
    header("Location: settings.php");
    exit();
}

// Get current settings
try {
    $user_id = auth()->getUserId();
    $stmt = $pdo->prepare("
        SELECT us.*, u.username, u.role
        FROM user_settings us 
        JOIN users u ON us.user_id = u.id 
        WHERE us.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch();
    
    // If no settings exist for user, create default settings
    if (!$settings) {
        $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, theme_preference, email_notifications, sms_notifications, language) VALUES (?, 'light', 1, 0, 'english')");
        $stmt->execute([$user_id]);
        
        $stmt = $pdo->prepare("
            SELECT us.*, u.username, u.role
            FROM user_settings us 
            JOIN users u ON us.user_id = u.id 
            WHERE us.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch();
    }
    
    // Convert any legacy language settings
    if ($settings && !in_array($settings['language'], ['english', 'malaysian', 'indonesian'])) {
        // Convert malay to malaysian if that's the current setting
        $newLanguage = ($settings['language'] === 'malay') ? 'malaysian' : 'english';
        
        $stmt = $pdo->prepare("UPDATE user_settings SET language = ? WHERE user_id = ?");
        $stmt->execute([$newLanguage, $user_id]);
        
        $settings['language'] = $newLanguage;
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching settings: " . $e->getMessage();
    $settings = [];
}

$pageTitle = 'Settings';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-cog"></i> Settings</h2>
    </div>

    <?php include 'includes/messages.php'; ?>

    <div class="settings-container">
        <!-- User Profile Info -->
        <div class="settings-profile">
            <div class="profile-icon">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="profile-details">
                <h3><?php echo htmlspecialchars($settings['username'] ?? 'User'); ?></h3>
                <p class="role-badge <?php echo $settings['role'] ?? ''; ?>"><?php echo ucfirst($settings['role'] ?? 'User'); ?></p>
                <p class="email"><?php echo htmlspecialchars($settings['email'] ?? ''); ?></p>
            </div>
        </div>

        <!-- Theme Settings -->
        <div class="settings-card">
            <h3><i class="fas fa-paint-brush"></i> Theme Settings</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Theme Preference</label>
                    <div class="theme-toggle">
                        <input type="radio" id="light" name="theme_preference" value="light" 
                            <?php echo ($settings['theme_preference'] ?? 'light') === 'light' ? 'checked' : ''; ?>>
                        <label for="light"><i class="fas fa-sun"></i> Light</label>
                        
                        <input type="radio" id="dark" name="theme_preference" value="dark"
                            <?php echo ($settings['theme_preference'] ?? 'light') === 'dark' ? 'checked' : ''; ?>>
                        <label for="dark"><i class="fas fa-moon"></i> Dark</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save Theme</button>
            </form>
        </div>

        <!-- Language Settings -->
        <div class="settings-card">
            <h3><i class="fas fa-language"></i> Language Settings</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Language</label>
                    <select name="language" class="form-control">
                        <option value="english" <?php echo ($settings['language'] ?? 'english') === 'english' ? 'selected' : ''; ?>>English</option>
                        <option value="malaysian" <?php echo ($settings['language'] ?? 'english') === 'malaysian' ? 'selected' : ''; ?>>Malaysian</option>
                        <option value="indonesian" <?php echo ($settings['language'] ?? 'english') === 'indonesian' ? 'selected' : ''; ?>>Indonesian</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Save Language</button>
            </form>
        </div>

        <!-- Password Change -->
        <div class="settings-card">
            <h3><i class="fas fa-key"></i> Change Password</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label>New Password</label>
                    <div class="password-input">
                        <input type="password" name="new_password" id="new_password" class="form-control">
                        <i class="fas fa-eye toggle-password" data-target="new_password"></i>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="password-input">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control">
                        <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Change Password</button>
            </form>
        </div>

        
    </div>
</div>

<style>
/* Settings Page Styles */
.settings-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.settings-profile {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    background-color: var(--card-bg);
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-bottom: 10px;
}

.profile-icon {
    font-size: 4rem;
    color: var(--primary-color);
    margin-right: 20px;
}

.profile-details h3 {
    margin: 0;
    font-size: 1.8rem;
}

.role-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
    margin: 5px 0;
}

.role-badge.admin {
    background-color: #3498db;
    color: white;
}

.role-badge.farmer {
    background-color: #2ecc71;
    color: white;
}

.email {
    color: var(--text-muted);
    margin: 5px 0;
}

.settings-card {
    background-color: var(--card-bg);
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.settings-card h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
}

.settings-card h3 i {
    margin-right: 10px;
    color: var(--primary-color);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background-color: var(--input-bg);
    color: var(--text-color);
}

.theme-toggle {
    display: flex;
    gap: 15px;
    margin-top: 10px;
}

.theme-toggle input {
    display: none;
}

.theme-toggle label {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.theme-toggle label:hover {
    background-color: var(--hover-bg);
}

.theme-toggle input:checked + label {
    background-color: var(--primary-color);
    color: white;
}

.toggle-switch {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.toggle-switch input {
    margin-right: 10px;
}

.password-input {
    position: relative;
}

.password-input input {
    width: 100%;
    padding-right: 35px;
}

.toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--text-muted);
}

.admin-only {
    border-left: 4px solid #3498db;
}

/* Dark theme specific styles */
.dark-theme .settings-card {
    background-color: #2c3e50;
}

.dark-theme .form-control {
    background-color: #34495e;
    color: #ecf0f1;
    border-color: #4a6380;
}

.dark-theme .toggle-password {
    color: #bdc3c7;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .settings-container {
        grid-template-columns: 1fr;
    }
    
    .settings-profile {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-icon {
        margin-right: 0;
        margin-bottom: 15px;
    }
}
</style>

<script>
// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(function(toggle) {
    toggle.addEventListener('click', function(e) {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        
        if (input.type === 'password') {
            input.type = 'text';
            this.classList.remove('fa-eye');
            this.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            this.classList.remove('fa-eye-slash');
            this.classList.add('fa-eye');
        }
    });
});

// Apply theme immediately when changed
document.querySelectorAll('input[name="theme_preference"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.body.className = this.value + '-theme';
    });
});
</script>

<?php include 'includes/footer.php'; ?>