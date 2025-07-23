<?php
require_once 'includes/auth.php';
auth()->checkSupervisor();

require_once 'includes/db.php';

// Initialize filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Base query - supervisor can only view, not modify
$query = "
    SELECT s.*, r.role_name 
    FROM staff s
    LEFT JOIN roles r ON s.role_id = r.id
    WHERE 1=1
";

// Apply filters
$params = [];

if ($status_filter != 'all') {
    $query .= " AND s.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
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

// Count statistics
try {
    $active_count = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn();
    $total_count = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
    $on_leave_count = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'on-leave'")->fetchColumn();
} catch(PDOException $e) {
    error_log("Error counting staff: " . $e->getMessage());
    $active_count = 0;
    $total_count = 0;
    $on_leave_count = 0;
}

$pageTitle = 'Team Directory';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-address-book"></i> Team Directory</h2>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="location.href='staff_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Staff Management
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-row">
        <div class="summary-card">
            <div class="summary-icon bg-blue">
                <i class="fas fa-users"></i>
            </div>
            <div class="summary-details">
                <h3>Total Team Members</h3>
                <p class="summary-count"><?php echo $total_count; ?></p>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon bg-green">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="summary-details">
                <h3>Active Members</h3>
                <p class="summary-count"><?php echo $active_count; ?></p>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-icon bg-orange">
                <i class="fas fa-calendar-times"></i>
            </div>
            <div class="summary-details">
                <h3>On Leave</h3>
                <p class="summary-count"><?php echo $on_leave_count; ?></p>
            </div>
        </div>
    </div>

    <!-- Filter Panel -->
    <div class="filter-panel">
        <form method="GET" action="team_directory.php" class="row">
            <div class="col-md-8">
                <div class="form-group">
                    <label for="search">Search Team Members</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search by name, email, or role" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="status">Filter by Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="on-leave" <?php echo $status_filter == 'on-leave' ? 'selected' : ''; ?>>On Leave</option>
                    </select>
                </div>
            </div>
            <div class="col-md-1">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="team_directory.php" class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Team Members Grid -->
    <div class="team-grid">
        <?php if (empty($staff_members)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No team members found with the current filters.
            </div>
        <?php else: ?>
            <?php foreach ($staff_members as $staff): ?>
                <div class="team-member-card <?php echo $staff['status'] == 'inactive' ? 'inactive' : ($staff['status'] == 'on-leave' ? 'on-leave' : ''); ?>">
                    <div class="member-avatar">
                        <?php if (!empty($staff['profile_image'])): ?>
                            <img src="uploads/staff/<?php echo htmlspecialchars($staff['profile_image']); ?>" alt="Profile">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?php echo strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Status indicator -->
                        <div class="status-indicator status-<?php echo $staff['status']; ?>"></div>
                    </div>
                    
                    <div class="member-info">
                        <h4 class="member-name">
                            <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                        </h4>
                        <p class="member-role">
                            <?php echo htmlspecialchars($staff['role_name'] ?? 'Staff'); ?>
                        </p>
                        <p class="member-id">ID: <?php echo htmlspecialchars($staff['staff_id']); ?></p>
                    </div>
                    
                    <div class="member-contact">
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($staff['email']); ?></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($staff['phone']); ?></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-calendar"></i>
                            <span>Joined: <?php echo date('M Y', strtotime($staff['hire_date'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="member-status">
                        <?php
                        $status_classes = [
                            'active' => 'badge-success',
                            'inactive' => 'badge-danger',
                            'on-leave' => 'badge-warning'
                        ];
                        $status_class = $status_classes[$staff['status']] ?? 'badge-secondary';
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo ucfirst(str_replace('-', ' ', $staff['status'])); ?>
                        </span>
                    </div>
                    
                    <div class="member-actions">
                        <button class="btn btn-sm btn-info view-details-btn" 
                                data-id="<?php echo $staff['id']; ?>"
                                data-name="<?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>"
                                data-role="<?php echo htmlspecialchars($staff['role_name'] ?? 'Staff'); ?>"
                                data-email="<?php echo htmlspecialchars($staff['email']); ?>"
                                data-phone="<?php echo htmlspecialchars($staff['phone']); ?>"
                                data-hire-date="<?php echo date('F d, Y', strtotime($staff['hire_date'])); ?>"
                                data-status="<?php echo $staff['status']; ?>">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewDetailsModalLabel">
                    <i class="fas fa-user"></i> Team Member Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <div class="member-avatar-large">
                            <div class="avatar-placeholder-large" id="modalAvatarPlaceholder"></div>
                        </div>
                        <h4 id="modalMemberName" class="mt-3"></h4>
                        <p id="modalMemberRole" class="text-muted"></p>
                        <span id="modalMemberStatus" class="status-badge"></span>
                    </div>
                    <div class="col-md-8">
                        <h6>Contact Information</h6>
                        <div class="info-group">
                            <div class="info-item">
                                <strong><i class="fas fa-envelope"></i> Email:</strong>
                                <span id="modalMemberEmail"></span>
                            </div>
                            <div class="info-item">
                                <strong><i class="fas fa-phone"></i> Phone:</strong>
                                <span id="modalMemberPhone"></span>
                            </div>
                        </div>
                        
                        <h6 class="mt-4">Employment Information</h6>
                        <div class="info-group">
                            <div class="info-item">
                                <strong><i class="fas fa-calendar"></i> Hire Date:</strong>
                                <span id="modalHireDate"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Team directory specific styles */
.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.team-member-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
    border-left: 4px solid #2ecc71;
}

.team-member-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.team-member-card.inactive {
    border-left-color: #e74c3c;
    background-color: #f8f9fa;
    opacity: 0.8;
}

.team-member-card.on-leave {
    border-left-color: #f39c12;
    background-color: #fff8e1;
}

.member-avatar {
    position: relative;
    width: 60px;
    height: 60px;
    margin: 0 auto 15px;
}

.member-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: #3498db;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
}

.status-indicator {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid white;
}

.status-indicator.status-active {
    background-color: #2ecc71;
}

.status-indicator.status-inactive {
    background-color: #e74c3c;
}

.status-indicator.status-on-leave {
    background-color: #f39c12;
}

.member-info {
    text-align: center;
    margin-bottom: 15px;
}

.member-name {
    margin: 0 0 5px 0;
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
}

.member-role {
    margin: 0 0 5px 0;
    color: #7f8c8d;
    font-weight: 500;
}

.member-id {
    margin: 0;
    font-size: 12px;
    color: #95a5a6;
}

.member-contact {
    margin-bottom: 15px;
}

.contact-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-size: 14px;
    color: #555;
}

.contact-item i {
    width: 20px;
    color: #3498db;
    margin-right: 10px;
}

.member-status {
    text-align: center;
    margin-bottom: 15px;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
}

.badge-success {
    background-color: #2ecc71;
    color: white;
}

.badge-danger {
    background-color: #e74c3c;
    color: white;
}

.badge-warning {
    background-color: #f39c12;
    color: white;
}

.member-actions {
    text-align: center;
}

/* Modal styles */
.member-avatar-large {
    margin-bottom: 20px;
}

.avatar-placeholder-large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background-color: #3498db;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 24px;
    margin: 0 auto;
}

.info-group {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.info-item {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.info-item:last-child {
    margin-bottom: 0;
}

.info-item strong {
    min-width: 120px;
    color: #2c3e50;
}

.info-item i {
    margin-right: 5px;
    color: #3498db;
}

/* Summary cards */
.summary-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.summary-card {
    flex: 1;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px;
    display: flex;
    align-items: center;
}

.summary-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.summary-icon.bg-blue {
    background-color: #3498db;
    color: white;
}

.summary-icon.bg-green {
    background-color: #2ecc71;
    color: white;
}

.summary-icon.bg-orange {
    background-color: #f39c12;
    color: white;
}

.summary-details h3 {
    margin: 0 0 5px 0;
    font-size: 16px;
    color: #2c3e50;
}

.summary-count {
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
    margin: 0;
}

/* Filter panel */
.filter-panel {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
}

/* Responsive design */
@media (max-width: 768px) {
    .team-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-row {
        flex-direction: column;
    }
    
    .filter-panel .row {
        flex-direction: column;
    }
    
    .filter-panel .col-md-8,
    .filter-panel .col-md-3,
    .filter-panel .col-md-1 {
        margin-bottom: 10px;
    }
}

.main-content {
    padding-bottom: 60px;
    min-height: calc(100vh - 60px);
}

body {
    padding-bottom: 60px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View details modal functionality
    const viewDetailsButtons = document.querySelectorAll('.view-details-btn');
    const viewDetailsModal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
    
    viewDetailsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const name = this.getAttribute('data-name');
            const role = this.getAttribute('data-role');
            const email = this.getAttribute('data-email');
            const phone = this.getAttribute('data-phone');
            const hireDate = this.getAttribute('data-hire-date');
            const status = this.getAttribute('data-status');
            
            // Update modal content
            document.getElementById('modalMemberName').textContent = name;
            document.getElementById('modalMemberRole').textContent = role;
            document.getElementById('modalMemberEmail').textContent = email;
            document.getElementById('modalMemberPhone').textContent = phone;
            document.getElementById('modalHireDate').textContent = hireDate;
            
            // Update avatar placeholder
            const nameParts = name.split(' ');
            const initials = nameParts.map(part => part.charAt(0)).join('');
            document.getElementById('modalAvatarPlaceholder').textContent = initials;
            
            // Update status badge
            const statusBadge = document.getElementById('modalMemberStatus');
            statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1).replace('-', ' ');
            statusBadge.className = 'status-badge';
            
            if (status === 'active') {
                statusBadge.classList.add('badge-success');
            } else if (status === 'inactive') {
                statusBadge.classList.add('badge-danger');
            } else if (status === 'on-leave') {
                statusBadge.classList.add('badge-warning');
            }
            
            // Show modal
            viewDetailsModal.show();
        });
    });
    
    // Search functionality enhancement
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });
    }
    
    // Add animation to team member cards
    const teamCards = document.querySelectorAll('.team-member-card');
    teamCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Contact item click to copy
    const contactItems = document.querySelectorAll('.contact-item');
    contactItems.forEach(item => {
        const emailItem = item.querySelector('i.fa-envelope');
        const phoneItem = item.querySelector('i.fa-phone');
        
        if (emailItem || phoneItem) {
            item.style.cursor = 'pointer';
            item.title = 'Click to copy';
            
            item.addEventListener('click', function() {
                const text = this.querySelector('span').textContent;
                navigator.clipboard.writeText(text).then(() => {
                    // Show temporary feedback
                    const originalText = this.querySelector('span').textContent;
                    this.querySelector('span').textContent = 'Copied!';
                    this.style.color = '#2ecc71';
                    
                    setTimeout(() => {
                        this.querySelector('span').textContent = originalText;
                        this.style.color = '';
                    }, 1500);
                }).catch(err => {
                    console.log('Could not copy text: ', err);
                });
            });
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>