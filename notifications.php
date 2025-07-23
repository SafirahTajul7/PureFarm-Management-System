<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Set timezone to Malaysia time (MYT/UTC+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// Process mark as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$_GET['mark_read']]);
        header("Location: notifications.php?success=1");
        exit;
    } catch (PDOException $e) {
        $error = "Could not mark notification as read: " . $e->getMessage();
    }
}

// Process mark all as read
if (isset($_GET['mark_all_read'])) {
    try {
        $pdo->exec("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        header("Location: notifications.php?success=2");
        exit;
    } catch (PDOException $e) {
        $error = "Could not mark all notifications as read: " . $e->getMessage();
    }
}

// Process delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        header("Location: notifications.php?success=3");
        exit;
    } catch (PDOException $e) {
        $error = "Could not delete notification: " . $e->getMessage();
    }
}

// Get notifications
try {
    // Check if notifications table exists
    $hasTable = false;
    $tables = $pdo->query("SHOW TABLES LIKE 'notifications'")->fetchAll();
    if (count($tables) > 0) {
        $hasTable = true;
    }
    
    if (!$hasTable) {
        // Create notifications table
        $pdo->exec("
            CREATE TABLE notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    // Get notification count
    $unreadCount = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
    
    // Get paginated notifications
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total for pagination
    $totalNotifications = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
    $totalPages = ceil($totalNotifications / $perPage);
    
} catch (PDOException $e) {
    $error = "Error loading notifications: " . $e->getMessage();
    $notifications = [];
    $unreadCount = 0;
    $totalPages = 1;
}

$pageTitle = 'Notifications';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-bell"></i> Notifications</h2>
        <div class="action-buttons">
            <?php if ($unreadCount > 0): ?>
            <a href="notifications.php?mark_all_read=1" class="btn btn-primary">
                <i class="fas fa-check-double"></i> Mark All as Read
            </a>
            <?php endif; ?>
            <a href="weather_settings.php" class="btn btn-primary">
                <i class="fas fa-cog"></i> Notification Settings
            </a>
        </div>
    </div>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php 
        switch ($_GET['success']) {
            case 1:
                echo "Notification marked as read.";
                break;
            case 2:
                echo "All notifications marked as read.";
                break;
            case 3:
                echo "Notification deleted successfully.";
                break;
            default:
                echo "Operation completed successfully.";
        }
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="notifications-container">
        <div class="notifications-header">
            <h3>
                Weather Alerts and Notifications
                <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger ms-2"><?php echo $unreadCount; ?> unread</span>
                <?php endif; ?>
            </h3>
            <p class="text-muted">Stay informed about weather conditions affecting your farm operations</p>
        </div>
        
        <?php if (empty($notifications)): ?>
        <div class="no-notifications">
            <i class="fas fa-bell-slash"></i>
            <p>No notifications yet</p>
            <p class="text-muted">Weather alerts and notifications will appear here</p>
        </div>
        <?php else: ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $notification): ?>
            <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                <div class="notification-icon">
                    <?php 
                    // Set icon based on notification type
                    $iconClass = 'info-circle';
                    $iconColor = '#0d6efd';
                    
                    if (strpos($notification['type'], 'temperature') !== false) {
                        $iconClass = 'thermometer-half';
                        $iconColor = '#dc3545';
                    } else if (strpos($notification['type'], 'humidity') !== false) {
                        $iconClass = 'tint';
                        $iconColor = '#0dcaf0';
                    } else if (strpos($notification['type'], 'wind') !== false) {
                        $iconClass = 'wind';
                        $iconColor = '#6c757d';
                    } else if (strpos($notification['type'], 'precipitation') !== false) {
                        $iconClass = 'cloud-rain';
                        $iconColor = '#0dcaf0';
                    } else if (strpos($notification['type'], 'severe') !== false) {
                        $iconClass = 'exclamation-triangle';
                        $iconColor = '#dc3545';
                    } else if (strpos($notification['type'], 'daily_summary') !== false) {
                        $iconClass = 'calendar-day';
                        $iconColor = '#20c997';
                    }
                    ?>
                    <i class="fas fa-<?php echo $iconClass; ?>" style="color: <?php echo $iconColor; ?>"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-header">
                        <h4><?php echo ucfirst(str_replace('_', ' ', $notification['type'])); ?></h4>
                        <span class="notification-time"><?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?></span>
                    </div>
                    <div class="notification-message">
                        <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                    </div>
                </div>
                <div class="notification-actions">
                    <?php if (!$notification['is_read']): ?>
                    <a href="notifications.php?mark_read=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-primary" title="Mark as Read">
                        <i class="fas fa-check"></i>
                    </a>
                    <?php endif; ?>
                    <a href="notifications.php?delete=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this notification?');">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <nav aria-label="Notifications pagination">
                <ul class="pagination">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h5><i class="fas fa-info-circle"></i> About Notifications</h5>
        </div>
        <div class="card-body">
            <p>
                The PureFarm Management System generates weather alerts based on your configured thresholds. 
                When weather conditions exceed your set thresholds, notifications are sent through your selected methods:
            </p>
            <ul class="mb-0">
                <li>In-system notifications appear here</li>
                <li>Email notifications are sent to your configured email address</li>
                <li>SMS notifications are sent to your configured phone number</li>
            </ul>
            <p class="mt-3">
                <a href="weather_settings.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-cog"></i> Configure Notification Settings
                </a>
            </p>
        </div>
    </div>
</div>

<style>
/* Notifications Container */
.notifications-container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
    overflow: hidden;
}

.notifications-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}

.notifications-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
}

.notifications-header p {
    margin: 5px 0 0 0;
    font-size: 14px;
}

/* No Notifications */
.no-notifications {
    text-align: center;
    padding: 50px 20px;
    color: #6c757d;
}

.no-notifications i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.no-notifications p {
    margin: 5px 0;
    font-size: 16px;
}

.no-notifications p.text-muted {
    font-size: 14px;
}

/* Notifications List */
.notifications-list {
    padding: 10px 0;
}

.notification-item {
    display: flex;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    transition: background-color 0.2s;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: #f0f9ff;
}

.notification-icon {
    flex: 0 0 50px;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 5px;
}

.notification-icon i {
    font-size: 24px;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.notification-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.notification-time {
    font-size: 12px;
    color: #6c757d;
}

.notification-message {
    font-size: 14px;
    color: #495057;
    white-space: pre-line;
}

.notification-actions {
    flex: 0 0 auto;
    display: flex;
    gap: 5px;
    align-items: flex-start;
    margin-left: 15px;
}

/* Pagination */
.pagination-container {
    padding: 15px 20px;
    display: flex;
    justify-content: center;
    border-top: 1px solid #e9ecef;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<?php include 'includes/footer.php'; ?>