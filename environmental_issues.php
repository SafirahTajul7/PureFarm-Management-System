<?php
require_once 'includes/auth.php';
auth()->checkSupervisor(); // SUPERVISOR ACCESS ONLY

require_once 'includes/db.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $supervisor_id = $_SESSION['user_id'];
        $field_id = $_POST['field_id'];
        $issue_type = $_POST['issue_type'];
        $severity = $_POST['severity'];
        $issue_date = $_POST['issue_date'];
        $issue_time = $_POST['issue_time'];
        $affected_area = $_POST['affected_area'];
        $description = $_POST['description'];
        $immediate_action = $_POST['immediate_action'];
        $photos = $_POST['photos'] ?? '';
        $weather_conditions = $_POST['weather_conditions'];
        $estimated_impact = $_POST['estimated_impact'];
        $admin_notification = isset($_POST['admin_notification']) ? 1 : 0;

        // Create environmental_issues table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS environmental_issues (
                id INT AUTO_INCREMENT PRIMARY KEY,
                supervisor_id INT NOT NULL,
                field_id INT NOT NULL,
                issue_type VARCHAR(100) NOT NULL,
                severity ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL,
                issue_date DATE NOT NULL,
                issue_time TIME NOT NULL,
                affected_area VARCHAR(255),
                description TEXT NOT NULL,
                immediate_action TEXT,
                photos TEXT,
                weather_conditions VARCHAR(255),
                estimated_impact TEXT,
                admin_notification BOOLEAN DEFAULT FALSE,
                status ENUM('Open', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Open',
                resolution_notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                resolved_at TIMESTAMP NULL
            )
        ");

        // Insert environmental issue report
        $stmt = $pdo->prepare("
            INSERT INTO environmental_issues 
            (supervisor_id, field_id, issue_type, severity, issue_date, issue_time, 
             affected_area, description, immediate_action, photos, weather_conditions, 
             estimated_impact, admin_notification, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Open')
        ");
        
        $stmt->execute([
            $supervisor_id, $field_id, $issue_type, $severity, $issue_date, $issue_time,
            $affected_area, $description, $immediate_action, $photos, $weather_conditions,
            $estimated_impact, $admin_notification
        ]);

        $issue_id = $pdo->lastInsertId();

        // If admin notification is requested, create notification record
        if ($admin_notification) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS admin_notifications (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        type VARCHAR(50) NOT NULL,
                        title VARCHAR(255) NOT NULL,
                        message TEXT NOT NULL,
                        related_id INT,
                        is_read BOOLEAN DEFAULT FALSE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");

                $notification_stmt = $pdo->prepare("
                    INSERT INTO admin_notifications (type, title, message, related_id)
                    VALUES ('environmental_issue', ?, ?, ?)
                ");

                $notification_title = "Environmental Issue Reported - {$issue_type} ({$severity} Priority)";
                $notification_message = "A {$severity} priority {$issue_type} has been reported by supervisor in field. Immediate attention may be required.";

                $notification_stmt->execute([$notification_title, $notification_message, $issue_id]);
            } catch(PDOException $e) {
                error_log("Error creating admin notification: " . $e->getMessage());
            }
        }

        $success_message = "Environmental issue reported successfully! Issue ID: #" . $issue_id;
        
        // Clear form data
        $_POST = [];
        
    } catch(PDOException $e) {
        $error_message = "Error reporting environmental issue: " . $e->getMessage();
    }
}

// Get supervisor's assigned fields
try {
    $supervisor_id = $_SESSION['user_id'];
    
    $fields_stmt = $pdo->prepare("
        SELECT DISTINCT f.id, f.field_name, f.location 
        FROM fields f
        LEFT JOIN staff_field_assignments sfa ON f.id = sfa.field_id 
        WHERE sfa.staff_id = ? AND sfa.status = 'active'
        OR NOT EXISTS (
            SELECT 1 FROM staff_field_assignments 
            WHERE staff_id = ? AND status = 'active'
        )
        ORDER BY f.field_name
    ");
    $fields_stmt->execute([$supervisor_id, $supervisor_id]);
    $assigned_fields = $fields_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($assigned_fields)) {
        $fields_stmt = $pdo->query("SELECT id, field_name, location FROM fields ORDER BY field_name");
        $assigned_fields = $fields_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $assigned_fields = [];
}

// Get recent environmental issues for reference
try {
    $recent_issues_stmt = $pdo->prepare("
        SELECT 
            ei.*,
            f.field_name
        FROM environmental_issues ei
        LEFT JOIN fields f ON ei.field_id = f.id
        WHERE ei.supervisor_id = ?
        ORDER BY ei.created_at DESC
        LIMIT 5
    ");
    $recent_issues_stmt->execute([$supervisor_id]);
    $recent_issues = $recent_issues_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $recent_issues = [];
}

$pageTitle = 'Report Environmental Issues';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-exclamation-triangle"></i> Report Environmental Issues</h2>
        <div class="action-buttons">
            <button class="btn btn-info" onclick="location.href='view_my_environmental_issues.php'">
                <i class="fas fa-list"></i> View My Reports
            </button>
            <button class="btn btn-secondary" onclick="location.href='supervisor_environmental.php'">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>
    </div>

    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <div class="report-container">
        <div class="row">
            <div class="col-md-8">
                <div class="form-container">
                    <h3><i class="fas fa-clipboard-list"></i> Environmental Issue Report</h3>
                    
                    <form method="POST" class="issue-form" id="issueReportForm">
                        <div class="form-section">
                            <h4><i class="fas fa-info-circle"></i> Basic Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="field_id"><i class="fas fa-map-marker-alt"></i> Affected Field *</label>
                                        <select name="field_id" id="field_id" class="form-control" required>
                                            <option value="">Select Field</option>
                                            <?php foreach($assigned_fields as $field): ?>
                                            <option value="<?php echo $field['id']; ?>" 
                                                    <?php echo (isset($_POST['field_id']) && $_POST['field_id'] == $field['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($field['field_name']); ?>
                                                <?php if($field['location']): ?> - <?php echo htmlspecialchars($field['location']); ?><?php endif; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Please select the affected field.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="issue_type"><i class="fas fa-tag"></i> Issue Type *</label>
                                        <select name="issue_type" id="issue_type" class="form-control" required>
                                            <option value="">Select Issue Type</option>
                                            <option value="Soil Erosion" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'Soil Erosion') ? 'selected' : ''; ?>>Soil Erosion</option>
                                            <option value="Water Contamination" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'Water Contamination') ? 'selected' : ''; ?>>Water Contamination</option>
                                            <option value="Chemical Spill" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'Chemical Spill') ? 'selected' : ''; ?>>Chemical Spill</option>
                                            <option value="Drainage Problems" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'Drainage Problems') ? 'selected' : ''; ?>>Drainage Problems</option>
                                            <option value="Air Quality" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'Air Quality') ? 'selected' : ''; ?>>Air Quality Issues</option>
                                            <option value="Noise Pollution" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'Noise Pollution') ? 'selected' : ''; ?>>Noise Pollution</option>
                                            <option value="Waste Management" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'Waste Management') ? 'selected' : ''; ?>>Waste Management</option>
                                            <option value="Wildlife Impact" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'Wildlife Impact') ? 'selected' : ''; ?>>Wildlife Impact</option>
                                            <option value="Climate Related" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'Climate Related') ? 'selected' : ''; ?>>Climate Related</option>
                                            <option value="Equipment Malfunction" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'Equipment Malfunction') ? 'selected' : ''; ?>>Equipment Malfunction</option>
                                            <option value="Other" <?php echo (isset($_POST['issue_type']) && $_POST['issue_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                        <div class="invalid-feedback">Please select the issue type.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="severity"><i class="fas fa-exclamation-circle"></i> Severity Level *</label>
                                        <select name="severity" id="severity" class="form-control" required>
                                            <option value="">Select Severity</option>
                                            <option value="Low" <?php echo (isset($_POST['severity']) && $_POST['severity'] == 'Low') ? 'selected' : ''; ?>>Low - Minor concern</option>
                                            <option value="Medium" <?php echo (isset($_POST['severity']) && $_POST['severity'] == 'Medium') ? 'selected' : ''; ?>>Medium - Needs attention</option>
                                            <option value="High" <?php echo (isset($_POST['severity']) && $_POST['severity'] == 'High') ? 'selected' : ''; ?>>High - Urgent action needed</option>
                                            <option value="Critical" <?php echo (isset($_POST['severity']) && $_POST['severity'] == 'Critical') ? 'selected' : ''; ?>>Critical - Immediate action required</option>
                                        </select>
                                        <div class="invalid-feedback">Please select the severity level.</div>
                                        <div id="severityHelp" class="form-text text-muted"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="issue_date"><i class="fas fa-calendar"></i> Issue Date *</label>
                                        <input type="date" name="issue_date" id="issue_date" class="form-control" 
                                               value="<?php echo isset($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d'); ?>" 
                                               max="<?php echo date('Y-m-d'); ?>" required>
                                        <div class="invalid-feedback">Please select the issue date.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="issue_time"><i class="fas fa-clock"></i> Issue Time *</label>
                                        <input type="time" name="issue_time" id="issue_time" class="form-control" 
                                               value="<?php echo isset($_POST['issue_time']) ? $_POST['issue_time'] : date('H:i'); ?>" required>
                                        <div class="invalid-feedback">Please select the issue time.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4><i class="fas fa-map"></i> Location & Impact Details</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="affected_area"><i class="fas fa-ruler"></i> Affected Area</label>
                                        <input type="text" name="affected_area" id="affected_area" class="form-control" 
                                               value="<?php echo isset($_POST['affected_area']) ? htmlspecialchars($_POST['affected_area']) : ''; ?>"
                                               placeholder="e.g., North section, 2 hectares, Near irrigation canal"
                                               maxlength="255">
                                        <small class="form-text text-muted">Specify the location and size of the affected area</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="weather_conditions"><i class="fas fa-cloud-sun"></i> Weather Conditions</label>
                                        <input type="text" name="weather_conditions" id="weather_conditions" class="form-control" 
                                               value="<?php echo isset($_POST['weather_conditions']) ? htmlspecialchars($_POST['weather_conditions']) : ''; ?>"
                                               placeholder="e.g., Heavy rain, Strong winds, Sunny and dry"
                                               maxlength="255">
                                        <small class="form-text text-muted">Weather conditions when the issue was discovered</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="estimated_impact"><i class="fas fa-chart-line"></i> Estimated Impact</label>
                                        <textarea name="estimated_impact" id="estimated_impact" class="form-control" rows="3"
                                                  placeholder="Describe the potential impact on crops, livestock, environment, or operations..."
                                                  maxlength="1000"><?php echo isset($_POST['estimated_impact']) ? htmlspecialchars($_POST['estimated_impact']) : ''; ?></textarea>
                                        <small class="form-text text-muted"><span id="impactCharCount">0</span>/1000 characters</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4><i class="fas fa-file-alt"></i> Issue Description</h4>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description"><i class="fas fa-clipboard"></i> Detailed Description *</label>
                                        <textarea name="description" id="description" class="form-control" rows="4" required
                                                  placeholder="Provide a detailed description of the environmental issue, including what you observed, when it started, and any other relevant details..."
                                                  maxlength="2000"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                        <div class="invalid-feedback">Please provide a detailed description of the issue.</div>
                                        <small class="form-text text-muted"><span id="descCharCount">0</span>/2000 characters</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="immediate_action"><i class="fas fa-tools"></i> Immediate Actions Taken</label>
                                        <textarea name="immediate_action" id="immediate_action" class="form-control" rows="3"
                                                  placeholder="Describe any immediate actions you have taken to address or contain the issue..."
                                                  maxlength="1000"><?php echo isset($_POST['immediate_action']) ? htmlspecialchars($_POST['immediate_action']) : ''; ?></textarea>
                                        <small class="form-text text-muted"><span id="actionCharCount">0</span>/1000 characters</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4><i class="fas fa-camera"></i> Documentation & Photos</h4>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="photos"><i class="fas fa-images"></i> Photo References</label>
                                        <textarea name="photos" id="photos" class="form-control" rows="2"
                                                  placeholder="List any photos taken or reference numbers (e.g., Photo_001.jpg, Photo_002.jpg)..."
                                                  maxlength="500"><?php echo isset($_POST['photos']) ? htmlspecialchars($_POST['photos']) : ''; ?></textarea>
                                        <small class="form-text text-muted">
                                            Note: Actual photo upload functionality would be implemented in full system. 
                                            <span id="photoCharCount">0</span>/500 characters
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="form-actions">
                            <button type="submit" class="btn btn-danger" id="submitBtn">
                                <i class="fas fa-exclamation-triangle"></i> Report Environmental Issue
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="location.href='supervisor_environmental_monitoring.php'">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="button" class="btn btn-info" onclick="previewReport()">
                                <i class="fas fa-eye"></i> Preview Report
                            </button>
                            <button type="button" class="btn btn-warning" onclick="resetForm()">
                                <i class="fas fa-undo"></i> Reset Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Quick Reference Panel -->
                <div class="quick-reference-panel">
                    <h4><i class="fas fa-lightbulb"></i> Reporting Guidelines</h4>
                    
                    <div class="guideline-section">
                        <h5>Severity Levels</h5>
                        <div class="severity-guide">
                            <div class="severity-item critical">
                                <span class="severity-label">Critical</span>
                                <small>Immediate threat to safety, environment, or operations</small>
                            </div>
                            <div class="severity-item high">
                                <span class="severity-label">High</span>
                                <small>Significant impact requiring urgent attention</small>
                            </div>
                            <div class="severity-item medium">
                                <span class="severity-label">Medium</span>
                                <small>Moderate impact that needs timely resolution</small>
                            </div>
                            <div class="severity-item low">
                                <span class="severity-label">Low</span>
                                <small>Minor issue for future attention</small>
                            </div>
                        </div>
                    </div>

                    <div class="guideline-section">
                        <h5>What to Include</h5>
                        <ul class="guideline-list">
                            <li><i class="fas fa-check"></i> Exact location and affected area</li>
                            <li><i class="fas fa-check"></i> Time and date of discovery</li>
                            <li><i class="fas fa-check"></i> Detailed description of the issue</li>
                            <li><i class="fas fa-check"></i> Potential causes if known</li>
                            <li><i class="fas fa-check"></i> Actions already taken</li>
                            <li><i class="fas fa-check"></i> Photos or evidence if available</li>
                        </ul>
                    </div>
                </div>

                <!-- Recent Issues Panel -->
                <?php if (!empty($recent_issues)): ?>
                <div class="recent-issues-panel">
                    <h4><i class="fas fa-history"></i> My Recent Reports</h4>
                    <div class="recent-issues-list">
                        <?php foreach($recent_issues as $issue): ?>
                        <div class="recent-issue-item">
                            <div class="issue-header">
                                <span class="issue-type"><?php echo htmlspecialchars($issue['issue_type']); ?></span>
                                <span class="issue-severity severity-<?php echo strtolower($issue['severity']); ?>">
                                    <?php echo $issue['severity']; ?>
                                </span>
                            </div>
                            <div class="issue-details">
                                <div class="issue-field">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($issue['field_name']); ?>
                                </div>
                                <div class="issue-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y', strtotime($issue['issue_date'])); ?>
                                </div>
                                <div class="issue-status">
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $issue['status'])); ?>">
                                        <?php echo $issue['status']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="issue-description">
                                <?php echo htmlspecialchars(substr($issue['description'], 0, 100)); ?>
                                <?php echo strlen($issue['description']) > 100 ? '...' : ''; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="view-all-link">
                        <a href="view_my_environmental_issues.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list"></i> View All My Reports
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Issue Types Quick Guide -->
                <div class="issue-types-panel">
                    <h4><i class="fas fa-tags"></i> Common Issue Types</h4>
                    <div class="issue-types-list">
                        <div class="issue-type-item" onclick="selectIssueType('Soil Erosion')">
                            <i class="fas fa-mountain text-warning"></i>
                            <div>
                                <strong>Soil Erosion</strong>
                                <small>Loss of topsoil due to water or wind</small>
                            </div>
                        </div>
                        <div class="issue-type-item" onclick="selectIssueType('Water Contamination')">
                            <i class="fas fa-tint text-danger"></i>
                            <div>
                                <strong>Water Contamination</strong>
                                <small>Pollution of water sources</small>
                            </div>
                        </div>
                        <div class="issue-type-item" onclick="selectIssueType('Chemical Spill')">
                            <i class="fas fa-flask text-danger"></i>
                            <div>
                                <strong>Chemical Spill</strong>
                                <small>Accidental release of chemicals</small>
                            </div>
                        </div>
                        <div class="issue-type-item" onclick="selectIssueType('Drainage Problems')">
                            <i class="fas fa-water text-primary"></i>
                            <div>
                                <strong>Drainage Problems</strong>
                                <small>Poor water drainage or flooding</small>
                            </div>
                        </div>
                        <div class="issue-type-item" onclick="selectIssueType('Wildlife Impact')">
                            <i class="fas fa-paw text-success"></i>
                            <div>
                                <strong>Wildlife Impact</strong>
                                <small>Damage caused by wildlife</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Preview Modal -->
<div class="modal fade" id="reportPreviewModal" tabindex="-1" role="dialog" aria-labelledby="reportPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportPreviewModalLabel">
                    <i class="fas fa-eye"></i> Environmental Issue Report Preview
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="reportPreviewContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="$('#reportPreviewModal').modal('hide'); $('#issueReportForm').submit();">
                    <i class="fas fa-exclamation-triangle"></i> Confirm & Submit Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog" aria-labelledby="confirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmationModalLabel">
                    <i class="fas fa-question-circle"></i> Confirm Environmental Issue Report
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to submit this environmental issue report?</p>
                <div id="confirmationDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmSubmitBtn">
                    <i class="fas fa-exclamation-triangle"></i> Submit Report
                </button>
            </div>
        </div>
    </div>
</div>



<script>
// Global variables
let isSubmitting = false;

// Preview report before submission
function previewReport() {
    const form = document.querySelector('.issue-form');
    const formData = new FormData(form);
    const previewContent = document.getElementById('reportPreviewContent');
    
    let html = '<div class="report-preview">';
    
    // Basic Information
    html += '<div class="preview-section">';
    html += '<h5><i class="fas fa-info-circle"></i> Basic Information</h5>';
    html += '<div class="preview-grid">';
    
    const fieldSelect = document.getElementById('field_id');
    const fieldText = fieldSelect.options[fieldSelect.selectedIndex].text;
    if (formData.get('field_id')) {
        html += `<div class="preview-item"><strong>Field:</strong> ${fieldText}</div>`;
    }
    
    if (formData.get('issue_type')) {
        html += `<div class="preview-item"><strong>Issue Type:</strong> ${formData.get('issue_type')}</div>`;
    }
    
    if (formData.get('severity')) {
        const severityClass = formData.get('severity').toLowerCase();
        html += `<div class="preview-item"><strong>Severity:</strong> <span class="severity-${severityClass}">${formData.get('severity')}</span></div>`;
    }
    
    if (formData.get('issue_date')) {
        const date = new Date(formData.get('issue_date'));
        html += `<div class="preview-item"><strong>Date:</strong> ${date.toLocaleDateString()}</div>`;
    }
    
    if (formData.get('issue_time')) {
        html += `<div class="preview-item"><strong>Time:</strong> ${formData.get('issue_time')}</div>`;
    }
    
    html += '</div></div>';
    
    // Location & Impact
    if (formData.get('affected_area') || formData.get('weather_conditions') || formData.get('estimated_impact')) {
        html += '<div class="preview-section">';
        html += '<h5><i class="fas fa-map"></i> Location & Impact</h5>';
        html += '<div class="preview-grid">';
        
        if (formData.get('affected_area')) {
            html += `<div class="preview-item"><strong>Affected Area:</strong> ${formData.get('affected_area')}</div>`;
        }
        
        if (formData.get('weather_conditions')) {
            html += `<div class="preview-item"><strong>Weather Conditions:</strong> ${formData.get('weather_conditions')}</div>`;
        }
        
        if (formData.get('estimated_impact')) {
            html += `<div class="preview-item full-width"><strong>Estimated Impact:</strong><br>${formData.get('estimated_impact')}</div>`;
        }
        
        html += '</div></div>';
    }
    
    // Description
    if (formData.get('description')) {
        html += '<div class="preview-section">';
        html += '<h5><i class="fas fa-file-alt"></i> Issue Description</h5>';
        html += `<div class="preview-description">${formData.get('description')}</div>`;
        html += '</div>';
    }
    
    // Immediate Actions
    if (formData.get('immediate_action')) {
        html += '<div class="preview-section">';
        html += '<h5><i class="fas fa-tools"></i> Immediate Actions Taken</h5>';
        html += `<div class="preview-description">${formData.get('immediate_action')}</div>`;
        html += '</div>';
    }
    
    // Photos
    if (formData.get('photos')) {
        html += '<div class="preview-section">';
        html += '<h5><i class="fas fa-camera"></i> Photo References</h5>';
        html += `<div class="preview-description">${formData.get('photos')}</div>`;
        html += '</div>';
    }
    
    // Notification
    if (formData.get('admin_notification')) {
        html += '<div class="preview-section">';
        html += '<div class="alert alert-info">';
        html += '<i class="fas fa-bell"></i> <strong>Admin Notification:</strong> Immediate notification will be sent to farm administration';
        html += '</div>';
        html += '</div>';
    }
    
    html += '</div>';
    
    previewContent.innerHTML = html;
    $('#reportPreviewModal').modal('show');
}

// Reset form
function resetForm() {
    if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
        document.getElementById('issueReportForm').reset();
        
        // Reset validation states
        const formControls = document.querySelectorAll('.form-control');
        formControls.forEach(control => {
            control.classList.remove('is-valid', 'is-invalid');
        });
        
        // Reset character counts
        updateCharacterCounts();
        
        showNotification('Form has been reset', 'info');
    }
}

// Select issue type from quick guide
function selectIssueType(issueType) {
    document.getElementById('issue_type').value = issueType;
    document.getElementById('issue_type').dispatchEvent(new Event('change'));
    showNotification(`Selected issue type: ${issueType}`, 'success');
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.issue-form');
    const requiredFields = form.querySelectorAll('[required]');
    
    // Real-time validation
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            validateField(this);
        });
        
        field.addEventListener('change', function() {
            validateField(this);
        });
        
        field.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                validateField(this);
            }
        });
    });
    
    // Character count updates
    const textareas = document.querySelectorAll('textarea[maxlength]');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', updateCharacterCounts);
    });
    
    // Initial character count update
    updateCharacterCounts();
    
    // Form submission validation
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (isSubmitting) return;
        
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!validateField(field)) {
                isValid = false;
            }
        });
        
        if (!isValid) {
            showNotification('Please fill in all required fields correctly', 'danger');
            
            // Scroll to first invalid field
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstInvalid.focus();
            }
            return;
        }
        
        // Show confirmation modal
        showConfirmationModal();
    });
    
    // Auto-update time field
    setInterval(updateCurrentTime, 60000); // Update every minute
    
    // Initialize auto-save
    startAutoSave();
    loadDraft();
});

// Validate individual field
function validateField(field) {
    field.classList.remove('is-invalid', 'is-valid');
    
    if (field.hasAttribute('required') && !field.value.trim()) {
        field.classList.add('is-invalid');
        return false;
    } else if (field.value.trim()) {
        // Additional validation for specific fields
        if (field.type === 'date' && field.max && field.value > field.max) {
            field.classList.add('is-invalid');
            return false;
        }
        
        field.classList.add('is-valid');
    }
    
    return true;
}

// Update character counts
function updateCharacterCounts() {
    const textareas = [
        { element: 'description', counter: 'descCharCount' },
        { element: 'estimated_impact', counter: 'impactCharCount' },
        { element: 'immediate_action', counter: 'actionCharCount' },
        { element: 'photos', counter: 'photoCharCount' }
    ];
    
    textareas.forEach(item => {
        const textarea = document.getElementById(item.element);
        const counter = document.getElementById(item.counter);
        
        if (textarea && counter) {
            const currentLength = textarea.value.length;
            const maxLength = textarea.getAttribute('maxlength');
            
            counter.textContent = currentLength;
            
            // Update counter color based on usage
            counter.className = '';
            if (currentLength > maxLength * 0.8) {
                counter.classList.add('char-warning');
            }
            if (currentLength > maxLength * 0.95) {
                counter.classList.add('char-danger');
            }
        }
    });
}

// Show confirmation modal
function showConfirmationModal() {
    const severity = document.getElementById('severity').value;
    const issueType = document.getElementById('issue_type').value;
    const fieldSelect = document.getElementById('field_id');
    const fieldName = fieldSelect.options[fieldSelect.selectedIndex].text;
    
    const confirmationDetails = document.getElementById('confirmationDetails');
    confirmationDetails.innerHTML = `
        <div class="alert alert-warning">
            <strong>Issue Type:</strong> ${issueType}<br>
            <strong>Severity:</strong> <span class="severity-${severity.toLowerCase()}">${severity}</span><br>
            <strong>Field:</strong> ${fieldName}
        </div>
        ${severity === 'Critical' || severity === 'High' ? 
            '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> This is a high priority issue. Admin will be notified immediately.</div>' : 
            ''}
    `;
    
    $('#confirmationModal').modal('show');
    
    // Handle confirmation
    document.getElementById('confirmSubmitBtn').onclick = function() {
        $('#confirmationModal').modal('hide');
        submitForm();
    };
}

// Submit form
function submitForm() {
    if (isSubmitting) return;
    
    isSubmitting = true;
    const submitBtn = document.getElementById('submitBtn');
    
    // Show loading state
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
    
    // Clear draft when submitting
    localStorage.removeItem('environmental_issue_draft');
    
    // Submit the form
    document.getElementById('issueReportForm').submit();
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification`;
    notification.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideInRight 0.3s ease-out;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Update current time in time field
function updateCurrentTime() {
    const timeField = document.getElementById('issue_time');
    if (timeField && !timeField.dataset.userModified) {
        const now = new Date();
        const timeString = now.toTimeString().slice(0, 5);
        timeField.value = timeString;
    }
}

// Track if user has modified time field
document.getElementById('issue_time').addEventListener('input', function() {
    this.dataset.userModified = 'true';
});

// Severity level guidance
document.getElementById('severity').addEventListener('change', function() {
    const severity = this.value;
    const helpText = document.getElementById('severityHelp');
    
    const descriptions = {
        'Critical': 'Immediate threat to safety, environment, or operations. Requires emergency response.',
        'High': 'Significant environmental impact requiring urgent attention within hours.',
        'Medium': 'Moderate impact that needs attention within 1-2 days.',
        'Low': 'Minor concern that can be addressed during routine operations.'
    };
    
    if (descriptions[severity]) {
        helpText.textContent = descriptions[severity];
        helpText.style.color = severity === 'Critical' || severity === 'High' ? '#dc3545' : '#6c757d';
    } else {
        helpText.textContent = '';
    }
    
    // Auto-check admin notification for critical/high issues
    if (severity === 'Critical' || severity === 'High') {
        document.getElementById('admin_notification').checked = true;
        showNotification(`Admin notification automatically enabled for ${severity} severity issues`, 'info');
    }
});

// Auto-save draft functionality
let autoSaveInterval;

function startAutoSave() {
    autoSaveInterval = setInterval(() => {
        const formData = new FormData(document.querySelector('.issue-form'));
        const draftData = {};
        
        for (let [key, value] of formData.entries()) {
            if (value.trim() !== '') {
                draftData[key] = value;
            }
        }
        
        if (Object.keys(draftData).length > 3) { // More than just basic fields
            localStorage.setItem('environmental_issue_draft', JSON.stringify(draftData));
            console.log('Draft saved automatically');
        }
    }, 30000); // Save every 30 seconds
}

function loadDraft() {
    const draft = localStorage.getItem('environmental_issue_draft');
    if (draft) {
        const draftData = JSON.parse(draft);
        
        if (confirm('A draft environmental issue report was found. Would you like to load it?')) {
            Object.keys(draftData).forEach(key => {
                const element = document.getElementById(key);
                if (element) {
                    if (element.type === 'checkbox') {
                        element.checked = draftData[key] === '1';
                    } else {
                        element.value = draftData[key];
                    }
                    
                    // Trigger validation
                    element.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            
            // Update character counts after loading
            updateCharacterCounts();
            
            showNotification('Draft report loaded successfully', 'success');
        }
        
        localStorage.removeItem('environmental_issue_draft');
    }
}

// Clear draft when form is submitted successfully
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.issue-form');
    form.addEventListener('submit', function() {
        localStorage.removeItem('environmental_issue_draft');
        if (autoSaveInterval) {
            clearInterval(autoSaveInterval);
        }
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + S to save draft
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        
        const formData = new FormData(document.querySelector('.issue-form'));
        const draftData = {};
        
        for (let [key, value] of formData.entries()) {
            if (value.trim() !== '') {
                draftData[key] = value;
            }
        }
        
        localStorage.setItem('environmental_issue_draft', JSON.stringify(draftData));
        showNotification('Draft saved manually', 'success');
    }
    
    // Ctrl/Cmd + Enter to preview
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        previewReport();
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        $('.modal').modal('hide');
    }
});

// Form field enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Auto-resize textareas
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
    
    // Enhanced field validation with debouncing
    let validationTimeout;
    const inputs = document.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            clearTimeout(validationTimeout);
            validationTimeout = setTimeout(() => {
                if (this.value.trim() !== '') {
                    validateField(this);
                }
            }, 500);
        });
    });
    
    // Focus management
    const firstField = document.getElementById('field_id');
    if (firstField) {
        firstField.focus();
    }
});

// Issue type specific validations and suggestions
document.getElementById('issue_type').addEventListener('change', function() {
    const issueType = this.value;
    const suggestedActions = {
        'Chemical Spill': 'Contain the spill, prevent spread, evacuate area if necessary, contact emergency services',
        'Water Contamination': 'Stop using contaminated water source, identify contamination source, test water quality',
        'Soil Erosion': 'Implement erosion control measures, check drainage systems, assess affected area',
        'Air Quality': 'Identify pollution source, ensure staff safety, monitor air quality levels',
        'Drainage Problems': 'Clear blocked drains, assess water flow, implement temporary solutions',
        'Wildlife Impact': 'Assess damage extent, implement wildlife deterrents, protect vulnerable areas',
        'Equipment Malfunction': 'Stop equipment operation, assess safety risks, contact maintenance team',
        'Waste Management': 'Contain waste properly, follow disposal protocols, prevent environmental contamination'
    };
    
    if (suggestedActions[issueType]) {
        const actionField = document.getElementById('immediate_action');
        if (!actionField.value.trim()) {
            actionField.placeholder = suggestedActions[issueType];
        }
    }
    
    // Suggest severity based on issue type
    const severityField = document.getElementById('severity');
    const criticalIssues = ['Chemical Spill', 'Water Contamination'];
    const highIssues = ['Air Quality', 'Equipment Malfunction'];
    
    if (criticalIssues.includes(issueType) && !severityField.value) {
        severityField.value = 'Critical';
        severityField.dispatchEvent(new Event('change'));
    } else if (highIssues.includes(issueType) && !severityField.value) {
        severityField.value = 'High';
        severityField.dispatchEvent(new Event('change'));
    }
});

// Weather conditions auto-fill based on recent data
function getRecentWeatherConditions() {
    // This would ideally fetch from the environmental_readings table
    // For now, we'll provide a simple interface
    const weatherField = document.getElementById('weather_conditions');
    const commonConditions = [
        'Sunny and clear',
        'Partly cloudy',
        'Overcast',
        'Light rain',
        'Heavy rain',
        'Thunderstorm',
        'Strong winds',
        'Hot and humid',
        'Cool and dry'
    ];
    
    if (!weatherField.value.trim()) {
        // Create a simple dropdown suggestion
        const datalist = document.createElement('datalist');
        datalist.id = 'weatherConditions';
        
        commonConditions.forEach(condition => {
            const option = document.createElement('option');
            option.value = condition;
            datalist.appendChild(option);
        });
        
        weatherField.setAttribute('list', 'weatherConditions');
        weatherField.parentNode.appendChild(datalist);
    }
}

// Initialize weather conditions helper
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(getRecentWeatherConditions, 1000);
});

// Enhanced error handling
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
    showNotification('An unexpected error occurred. Please try refreshing the page.', 'danger');
});

// Prevent accidental page leave with unsaved data
window.addEventListener('beforeunload', function(e) {
    const form = document.querySelector('.issue-form');
    const formData = new FormData(form);
    let hasData = false;
    
    for (let [key, value] of formData.entries()) {
        if (value.trim() !== '' && key !== 'issue_date' && key !== 'issue_time') {
            hasData = true;
            break;
        }
    }
    
    if (hasData && !isSubmitting) {
        e.preventDefault();
        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        return e.returnValue;
    }
});

// Performance monitoring
const performanceObserver = new PerformanceObserver((list) => {
    for (const entry of list.getEntries()) {
        if (entry.entryType === 'navigation') {
            console.log('Page load time:', entry.loadEventEnd - entry.loadEventStart, 'ms');
        }
    }
});

try {
    performanceObserver.observe({ entryTypes: ['navigation'] });
} catch (e) {
    // PerformanceObserver not supported in older browsers
    console.log('PerformanceObserver not supported');
}

// Accessibility enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Add ARIA labels for better screen reader support
    const requiredFields = document.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        field.setAttribute('aria-required', 'true');
    });
    
    // Enhance error messages with ARIA
    const invalidFields = document.querySelectorAll('.is-invalid');
    invalidFields.forEach(field => {
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            const id = 'error-' + field.id;
            feedback.id = id;
            field.setAttribute('aria-describedby', id);
        }
    });
    
    // Add keyboard navigation for custom elements
    const clickableElements = document.querySelectorAll('.severity-item, .issue-type-item');
    clickableElements.forEach(element => {
        element.setAttribute('tabindex', '0');
        element.setAttribute('role', 'button');
        
        element.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
});

// Initialize tooltips if Bootstrap is available
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $().tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    }
});

console.log('Environmental Issues reporting system initialized successfully');
</script>


<style>
.report-container {
    margin-bottom: 30px;
}

.form-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 30px;
    margin-bottom: 30px;
}

.form-container h3 {
    color: #333;
    margin-bottom: 25px;
    font-size: 20px;
    font-weight: 600;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 15px;
}

.form-container h3 i {
    color: #dc3545;
    margin-right: 8px;
}

.issue-form .form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #dee2e6;
}

.issue-form .form-section:last-of-type {
    border-bottom: none;
}

.issue-form .form-section h4 {
    color: #333;
    margin-bottom: 20px;
    font-size: 16px;
    font-weight: 600;
}

.issue-form .form-section h4 i {
    color: #dc3545;
    margin-right: 8px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    font-weight: 500;
    color: #555;
    margin-bottom: 8px;
    display: block;
}

.form-group label i {
    margin-right: 5px;
    color: #6c757d;
}

.form-control {
    border-radius: 6px;
    border: 1px solid #ddd;
    padding: 10px 12px;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.form-check {
    margin-bottom: 15px;
}

.form-check-label {
    font-weight: 500;
    color: #555;
    margin-left: 5px;
}

.form-check-label i {
    color: #17a2b8;
    margin-right: 5px;
}

.form-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dee2e6;
    text-align: right;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.form-actions .btn {
    padding: 10px 20px;
}

.alert {
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

/* Quick Reference Panel */
.quick-reference-panel {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 25px;
    margin-bottom: 20px;
}

.quick-reference-panel h4 {
    color: #333;
    margin-bottom: 20px;
    font-size: 16px;
    font-weight: 600;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.quick-reference-panel h4 i {
    color: #ffc107;
    margin-right: 8px;
}

.guideline-section {
    margin-bottom: 25px;
}

.guideline-section h5 {
    color: #555;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 12px;
}

.severity-guide {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.severity-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    border-radius: 6px;
    border-left: 3px solid;
    cursor: pointer;
    transition: all 0.3s ease;
}

.severity-item:hover {
    transform: translateX(5px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.severity-item.critical {
    background: #fff5f5;
    border-left-color: #dc3545;
}

.severity-item.high {
    background: #fff8f0;
    border-left-color: #fd7e14;
}

.severity-item.medium {
    background: #fffbf0;
    border-left-color: #ffc107;
}

.severity-item.low {
    background: #f0f8ff;
    border-left-color: #17a2b8;
}

.severity-label {
    font-weight: 600;
    font-size: 13px;
}

.severity-item small {
    color: #666;
    font-size: 11px;
}

.guideline-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.guideline-list li {
    display: flex;
    align-items: center;
    padding: 5px 0;
    font-size: 14px;
    color: #555;
}

.guideline-list i {
    color: #28a745;
    margin-right: 8px;
    font-size: 12px;
}

.emergency-contacts {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.contact-item {
    display: flex;
    align-items: center;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.contact-item:hover {
    background: #e9ecef;
    transform: translateY(-2px);
}

.contact-item i {
    font-size: 18px;
    color: #dc3545;
    margin-right: 12px;
}

.contact-item div {
    display: flex;
    flex-direction: column;
}

.contact-item strong {
    font-size: 13px;
    margin-bottom: 2px;
}

.contact-item span {
    font-size: 12px;
    color: #666;
}

/* Recent Issues Panel */
.recent-issues-panel {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 25px;
    margin-bottom: 20px;
}

.recent-issues-panel h4 {
    color: #333;
    margin-bottom: 20px;
    font-size: 16px;
    font-weight: 600;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.recent-issues-panel h4 i {
    color: #6c757d;
    margin-right: 8px;
}

.recent-issues-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
}

.recent-issue-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.recent-issue-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.issue-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.issue-type {
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.issue-severity {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    color: white;
}

.severity-critical { background-color: #dc3545; }
.severity-high { background-color: #fd7e14; }
.severity-medium { background-color: #ffc107; color: #333; }
.severity-low { background-color: #17a2b8; }

.issue-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 10px;
}

.issue-field,
.issue-date {
    display: flex;
    align-items: center;
    font-size: 12px;
    color: #666;
}

.issue-field i,
.issue-date i {
    margin-right: 5px;
    width: 12px;
}

.issue-status {
    margin-bottom: 8px;
}

.status-badge {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.status-open { background-color: #fff3cd; color: #856404; }
.status-in-progress { background-color: #d1ecf1; color: #0c5460; }
.status-resolved { background-color: #d4edda; color: #155724; }
.status-closed { background-color: #e2e3e5; color: #383d41; }

.issue-description {
    font-size: 12px;
    color: #666;
    line-height: 1.4;
    font-style: italic;
}

.view-all-link {
    text-align: center;
}

/* Issue Types Panel */
.issue-types-panel {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 25px;
}

.issue-types-panel h4 {
    color: #333;
    margin-bottom: 20px;
    font-size: 16px;
    font-weight: 600;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.issue-types-panel h4 i {
    color: #28a745;
    margin-right: 8px;
}

.issue-types-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.issue-type-item {
    display: flex;
    align-items: center;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    cursor: pointer;
    transition: all 0.3s ease;
}

.issue-type-item:hover {
    background: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.issue-type-item i {
    font-size: 20px;
    margin-right: 12px;
}

.issue-type-item div {
    display: flex;
    flex-direction: column;
}

.issue-type-item strong {
    font-size: 13px;
    margin-bottom: 2px;
    color: #333;
}

.issue-type-item small {
    font-size: 11px;
    color: #666;
}

/* Modal Styles */
.modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.modal-body {
    padding: 20px;
}

/* Form validation styles */
.form-control.is-invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.form-control.is-valid {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
}

.valid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #28a745;
}

/* Character count styling */
.form-text.text-muted {
    font-size: 12px;
}

.char-warning {
    color: #ffc107 !important;
}

.char-danger {
    color: #dc3545 !important;
}

/* Loading state */
.btn.loading {
    pointer-events: none;
    position: relative;
}

.btn.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    margin: -8px 0 0 -8px;
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .form-container {
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-actions .btn {
        margin-bottom: 10px;
    }
    
    .quick-reference-panel,
    .recent-issues-panel,
    .issue-types-panel {
        margin-top: 20px;
    }
    
    .severity-guide {
        gap: 5px;
    }
    
    .severity-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .emergency-contacts {
        gap: 8px;
    }
    
    .contact-item {
        padding: 10px;
    }
    
    .issue-types-list {
        gap: 8px;
    }
    
    .issue-type-item {
        padding: 10px;
    }
}

/* Preview styles */
.report-preview {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.preview-section {
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #dee2e6;
}

.preview-section:last-child {
    border-bottom: none;
}

.preview-section h5 {
    color: #333;
    margin-bottom: 15px;
    font-size: 16px;
    font-weight: 600;
}

.preview-section h5 i {
    color: #dc3545;
    margin-right: 8px;
}

.preview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.preview-item {
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid #dc3545;
}

.preview-item.full-width {
    grid-column: 1 / -1;
}

.preview-description {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid #dc3545;
    white-space: pre-wrap;
    line-height: 1.5;
}

/* Notification animation */
.notification {
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}
</style>

<?php include 'includes/footer.php'; ?>