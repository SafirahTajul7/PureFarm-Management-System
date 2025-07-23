<?php
// includes/supervisor_sidebar.php
?>
<div class="sidebar" style="width: 280px; background-color: #1a1a1a; height: 100vh; padding: 20px; position: fixed;">
    <div style="text-align: center; margin-bottom: 40px;">
        <img src="images/pure-logo.png" alt="Pure Logo" style="width: 100px; margin-bottom: 10px;">
        <h1 style="color: white; font-size: 24px; margin-bottom: 5px;">PureFarm</h1>
        <p style="color: #6c757d; font-size: 14px;">FOSTERING SMARTER FARM</p>
    </div>
    
    <!-- Dashboard -->
    <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisordashboard.php') ? 'active' : ''; ?>" 
         onclick="window.location.href='supervisordashboard.php'"
         style="display: flex; align-items: center; padding: 12px 20px; margin: 5px 0; border-radius: 8px; cursor: pointer; color: white; <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisordashboard.php') ? 'background-color: #4CAF50;' : ''; ?>">
        <i class="fas fa-th-large" style="margin-right: 10px;"></i>
        Dashboard
    </div>
    
    <!-- 1. Animal Management -->
    <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisor_animal_management.php') ? 'active' : ''; ?>"
         onclick="window.location.href='supervisor_animal_management.php'"
         style="display: flex; align-items: center; padding: 12px 20px; margin: 5px 0; border-radius: 8px; cursor: pointer; color: white; <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisor_animal_management.php') ? 'background-color: #4CAF50;' : ''; ?>">
        <i class="fas fa-paw" style="margin-right: 10px;"></i>
        Animal Management
    </div>
    
    <!-- 2. Crop Management -->
    <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisor_crop_management.php') ? 'active' : ''; ?>"
         onclick="window.location.href='supervisor_crop_management.php'"
         style="display: flex; align-items: center; padding: 12px 20px; margin: 5px 0; border-radius: 8px; cursor: pointer; color: white; <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisor_crop_management.php') ? 'background-color: #4CAF50;' : ''; ?>">
        <i class="fas fa-seedling" style="margin-right: 10px;"></i>
        Crop Management
    </div>
    
    <!-- 3. Inventory & Stock Management -->
    <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisor_inventory.php') ? 'active' : ''; ?>"
         onclick="window.location.href='supervisor_inventory.php'"
         style="display: flex; align-items: center; padding: 12px 20px; margin: 5px 0; border-radius: 8px; cursor: pointer; color: white; <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisor_inventory.php') ? 'background-color: #4CAF50;' : ''; ?>">
        <i class="fas fa-box" style="margin-right: 10px;"></i>
        Inventory & Stock
    </div>
    
    <!-- 4. Staff & Task Management -->
    <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisor_staff_tasks.php') ? 'active' : ''; ?>"
         onclick="window.location.href='supervisor_staff_tasks.php'"
         style="display: flex; align-items: center; padding: 12px 20px; margin: 5px 0; border-radius: 8px; cursor: pointer; color: white; <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisor_staff_tasks.php') ? 'background-color: #4CAF50;' : ''; ?>">
        <i class="fas fa-users" style="margin-right: 10px;"></i>
        Staff & Tasks
    </div>
    
    <!-- 5. Feeding & Nutrition Plan -->
    <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisor_feeding.php') ? 'active' : ''; ?>"
         onclick="window.location.href='supervisor_feeding.php'"
         style="display: flex; align-items: center; padding: 12px 20px; margin: 5px 0; border-radius: 8px; cursor: pointer; color: white; <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisor_feeding.php') ? 'background-color: #4CAF50;' : ''; ?>">
        <i class="fas fa-utensils" style="margin-right: 10px;"></i>
        Feeding & Nutrition
    </div>
    
    
    <!-- 7. Environmental Monitoring -->
    <div class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisor_environmental.php') ? 'active' : ''; ?>"
         onclick="window.location.href='supervisor_environmental.php'"
         style="display: flex; align-items: center; padding: 12px 20px; margin: 5px 0; border-radius: 8px; cursor: pointer; color: white; <?php echo (basename($_SERVER['PHP_SELF']) == 'supervisor_environmental.php') ? 'background-color: #4CAF50;' : ''; ?>">
        <i class="fas fa-cloud-sun" style="margin-right: 10px;"></i>
        Environmental
    </div>
    
</div>

<style>
.menu-item:hover {
    background-color: #333;
    transition: background-color 0.3s ease;
}

.menu-item.active {
    background-color: #4CAF50;
}

@media (max-width: 768px) {
    .sidebar {
        width: 70px;
    }
    
    .sidebar h1,
    .sidebar p,
    .menu-item span {
        display: none;
    }
    
    .menu-item {
        padding: 15px;
        justify-content: center;
    }
    
    .menu-item i {
        margin-right: 0;
    }
    
    .main-content {
        margin-left: 70px;
    }
}
</style>