<div class="sidebar" style="width: 280px; background-color: #1a1a1a; height: 100vh; padding: 20px; position: fixed; display: flex; flex-direction: column;">
    <div style="text-align: center; margin-bottom: 40px;">
        <img src="images/pure-logo.png" alt="Pure Logo" style="width: 100px; margin-bottom: 10px;">
        <h1 style="color: white; font-size: 24px; margin-bottom: 5px;">PureFarm</h1>
        <p style="color: #6c757d; font-size: 14px;">FOSTERING SMARTER FARM</p>
    </div>
    
    <div class="menu-section" style="flex-grow: 1;">
        <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" 
             onclick="window.location.href='index.php'"
             style="display: flex; align-items: center; padding: 12px 20px; margin: 5px 0; border-radius: 8px; cursor: pointer; color: white; <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'background-color: #4CAF50;' : ''; ?>">
            <i class="fas fa-th-large" style="margin-right: 10px;"></i>
            Dashboard
        </div>
        
        <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'animal_management.php') ? 'active' : ''; ?>"
             onclick="window.location.href='animal_management.php'"
             style="display: flex; align-items: center; padding: 12px 20px; margin: 5px 0; border-radius: 8px; cursor: pointer; color: white; <?php echo (basename($_SERVER['PHP_SELF']) == 'animal_management.php') ? 'background-color: #4CAF50;' : ''; ?>">
            <i class="fas fa-paw" style="margin-right: 10px;"></i>
            Animal Management
        </div>
        
        <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'crop_management.php') ? 'active' : ''; ?>"
             onclick="window.location.href='crop_management.php'"
             style="display: flex; align-items: center; padding: 12px 20px; margin: 5px 0; border-radius: 8px; cursor: pointer; color: white; <?php echo (basename($_SERVER['PHP_SELF']) == 'crop_management.php') ? 'background-color: #4CAF50;' : ''; ?>">
            <i class="fas fa-seedling" style="margin-right: 10px;"></i>
            Crop Management
        </div>
        
        <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'inventory.php') ? 'active' : ''; ?>"
             onclick="window.location.href='inventory.php'"
             style="display: flex; align-items: center; padding: 12px 20px; margin: 5px 0; border-radius: 8px; cursor: pointer; color: white; <?php echo (basename($_SERVER['PHP_SELF']) == 'inventory.php') ? 'background-color: #4CAF50;' : ''; ?>">
            <i class="fas fa-box" style="margin-right: 10px;"></i>
            Inventory
        </div>
        
        <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'staff_management.php') ? 'active' : ''; ?>"
             onclick="window.location.href='staff_management.php'"
             style="display: flex; align-items: center; padding: 12px 20px; margin: 5px 0; border-radius: 8px; cursor: pointer; color: white; <?php echo (basename($_SERVER['PHP_SELF']) == 'staff_management.php') ? 'background-color: #4CAF50;' : ''; ?>">
            <i class="fas fa-users" style="margin-right: 10px;"></i>
            Staff Management
        </div>
    </div>
    
    <!-- Logout Button (at bottom of sidebar) -->
    <div class="logout-section" style="margin-top: auto; border-top: 1px solid #333; padding-top: 15px;">
        <div class="menu-item logout-btn" 
             onclick="confirmLogout()"
             style="display: flex; align-items: center; padding: 12px 20px; margin: 5px 0; border-radius: 8px; cursor: pointer; color: white; background-color: #e74c3c;">
            <i class="fas fa-sign-out-alt" style="margin-right: 10px;"></i>
            Logout
        </div>
    </div>
</div>

<style>
.menu-item:hover {
    background-color: #333;
}

.menu-item.active {
    background-color: #4CAF50;
}

.logout-btn:hover {
    background-color: #c0392b !important;
    transition: background-color 0.3s;
}
</style>

<script>
function confirmLogout() {
    if (confirm("Are you sure you want to logout?")) {
        window.location.href = "logout.php";
    }
}
</script>