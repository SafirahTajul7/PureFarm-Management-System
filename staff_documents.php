<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Handle document upload
$success_message = '';
$error_message = '';

// First, check if the staff_documents table exists
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'staff_documents'");
    if ($tableCheck->rowCount() == 0) {
        // Create the staff_documents table if it doesn't exist
        $pdo->exec("CREATE TABLE staff_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            staff_id INT NOT NULL,
            document_type VARCHAR(50) NOT NULL,
            document_title VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            upload_date DATE NOT NULL,
            expiry_date DATE NULL,
            FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
        )");
    }
} catch (PDOException $e) {
    error_log("Error checking/creating staff_documents table: " . $e->getMessage());
    $error_message = "Database initialization error. Please contact administrator.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'upload') {
        // Handle document upload
        $staff_id = $_POST['staff_id'];
        $document_type = $_POST['document_type'];
        $document_title = $_POST['document_title'];
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        
        // Check if file was uploaded without errors
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === 0) {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $file_type = $_FILES['document_file']['type'];
            
            // For security, also check file extension
            $file_extension = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            
            if (in_array($file_type, $allowed_types) || in_array($file_extension, $allowed_extensions)) {
                $file_name = time() . '_' . basename($_FILES['document_file']['name']);
                $target_dir = 'uploads/staff_documents/';
                
                // Create directory if it doesn't exist
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $target_file = $target_dir . $file_name;
                
                if (move_uploaded_file($_FILES['document_file']['tmp_name'], $target_file)) {
                    try {
                        // Insert document record into database with prepared statement
                        if ($expiry_date) {
                            $stmt = $pdo->prepare("
                                INSERT INTO staff_documents (staff_id, document_type, document_title, file_path, upload_date, expiry_date) 
                                VALUES (?, ?, ?, ?, CURRENT_DATE, ?)
                            ");
                            $stmt->execute([$staff_id, $document_type, $document_title, $target_file, $expiry_date]);
                        } else {
                            $stmt = $pdo->prepare("
                                INSERT INTO staff_documents (staff_id, document_type, document_title, file_path, upload_date) 
                                VALUES (?, ?, ?, ?, CURRENT_DATE)
                            ");
                            $stmt->execute([$staff_id, $document_type, $document_title, $target_file]);
                        }
                        
                        $success_message = "Document uploaded successfully!";
                    } catch (PDOException $e) {
                        error_log("Database error: " . $e->getMessage());
                        $error_message = "Database error occurred: " . $e->getMessage();
                    }
                } else {
                    $error_message = "Failed to upload file. Please check if the uploads directory is writable.";
                }
            } else {
                $error_message = "Invalid file type. Allowed types: PDF, JPEG, PNG, DOC, DOCX";
            }
        } else {
            $error_message = "Please select a file to upload. Error code: " . $_FILES['document_file']['error'];
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        // Handle document deletion
        $document_id = $_POST['document_id'];
        
        try {
            // Get file path before deleting record
            $stmt = $pdo->prepare("SELECT file_path FROM staff_documents WHERE id = ?");
            $stmt->execute([$document_id]);
            $file_path = $stmt->fetchColumn();
            
            // Delete record from database
            $stmt = $pdo->prepare("DELETE FROM staff_documents WHERE id = ?");
            $stmt->execute([$document_id]);
            
            // Delete file from server if it exists
            if ($file_path && file_exists($file_path)) {
                unlink($file_path);
            }
            
            $success_message = "Document deleted successfully!";
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $error_message = "Failed to delete document. Please try again.";
        }
    }
}

// Fetch all staff members for dropdown
try {
    $staff_query = $pdo->query("
        SELECT id, CONCAT(first_name, ' ', last_name) AS full_name 
        FROM staff 
        WHERE status = 'active' 
        ORDER BY full_name
    ");
    $staff_members = $staff_query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching staff: " . $e->getMessage());
    $staff_members = [];
    $error_message = "Error loading staff data. Please try again.";
}

// Fetch document types for dropdown
$document_types = [
    'employment_contract' => 'Employment Contract',
    'certification' => 'Certification',
    'id_proof' => 'ID Proof',
    'qualification' => 'Qualification',
    'training' => 'Training Certificate',
    'performance_review' => 'Performance Review',
    'other' => 'Other'
];

// Initialize document arrays
$documents = [];
$expiring_documents = [];

// Fetch all documents
try {
    $documents_query = $pdo->query("
        SELECT d.id, d.staff_id, d.document_type, d.document_title, d.file_path, 
               DATE_FORMAT(d.upload_date, '%d %b %Y') AS formatted_upload_date, 
               DATE_FORMAT(d.expiry_date, '%d %b %Y') AS formatted_expiry_date,
               DATE_FORMAT(d.upload_date, '%e %b %Y') AS upload_date_short,
               DATE_FORMAT(d.expiry_date, '%e %b %Y') AS expiry_date_short,
               DATE_FORMAT(d.expiry_date, '%d %M %Y') AS expiry_date_long,
               CONCAT(s.first_name, ' ', s.last_name) AS staff_name
        FROM staff_documents d
        JOIN staff s ON d.staff_id = s.id
        ORDER BY d.upload_date DESC
    ");
    
    if ($documents_query) {
        $documents = $documents_query->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching documents: " . $e->getMessage());
    $error_message = "Error loading document data. Please try again.";
}

// Fetch documents that will expire in the next 30 days
try {
    $expiring_docs_query = $pdo->query("
        SELECT d.id, d.document_title, d.document_type, d.file_path,
               DATE_FORMAT(d.expiry_date, '%d %b %Y') AS formatted_expiry_date,
               DATE_FORMAT(d.expiry_date, '%e %b %Y') AS expiry_date_short,
               CONCAT(s.first_name, ' ', s.last_name) AS staff_name
        FROM staff_documents d
        JOIN staff s ON d.staff_id = s.id
        WHERE d.expiry_date IS NOT NULL 
        AND d.expiry_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
        ORDER BY d.expiry_date ASC
    ");
    
    if ($expiring_docs_query) {
        $expiring_documents = $expiring_docs_query->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error fetching expiring documents: " . $e->getMessage());
}

// Count documents by type
$employment_contracts_count = 0;
$certifications_count = 0;

foreach ($documents as $doc) {
    if ($doc['document_type'] === 'employment_contract') {
        $employment_contracts_count++;
    } else if ($doc['document_type'] === 'certification') {
        $certifications_count++;
    }
}

$pageTitle = 'Staff Documents & Records';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-file-alt"></i> Staff Documents & Records</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                <i class="fas fa-file-upload"></i> Upload Document
            </button>
            <button class="btn btn-secondary" onclick="location.href='staff_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Staff Management
            </button>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Document Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon bg-blue">
                <i class="fas fa-file-contract"></i>
            </div>
            <div class="summary-details">
                <h3>Total Documents</h3>
                <p class="summary-count"><?php echo count($documents); ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-orange">
                <i class="fas fa-file-signature"></i>
            </div>
            <div class="summary-details">
                <h3>Employment Contracts</h3>
                <p class="summary-count"><?php echo $employment_contracts_count; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-green">
                <i class="fas fa-certificate"></i>
            </div>
            <div class="summary-details">
                <h3>Certifications</h3>
                <p class="summary-count"><?php echo $certifications_count; ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-red">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="summary-details">
                <h3>Expiring Soon</h3>
                <p class="summary-count"><?php echo count($expiring_documents); ?></p>
                <span class="summary-subtitle">Next 30 days</span>
            </div>
        </div>
    </div>

    <!-- Expiring Documents Section -->
    <?php if (count($expiring_documents) > 0): ?>
    <div class="card mt-4 mb-4">
        <div class="card-header bg-warning text-dark">
            <h5><i class="fas fa-exclamation-circle"></i> Documents Expiring Soon</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Document Title</th>
                            <th>Type</th>
                            <th>Staff Member</th>
                            <th>Expiry Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiring_documents as $doc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doc['document_title']); ?></td>
                            <td><?php echo $document_types[$doc['document_type']] ?? $doc['document_type']; ?></td>
                            <td><?php echo htmlspecialchars($doc['staff_name']); ?></td>
                            <td>
                                <span class="badge bg-warning text-dark">
                                    <?php echo $doc['expiry_date_short']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- All Documents Section -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-folder-open"></i> All Documents</h5>
            <div class="card-tools">
                <input type="text" id="documentSearch" class="form-control form-control-sm" placeholder="Search documents...">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="documentsTable">
                    <thead>
                        <tr>
                            <th>Document Title</th>
                            <th>Staff Member</th>
                            <th>Type</th>
                            <th>Upload Date</th>
                            <th>Expiry Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($documents) > 0): ?>
                            <?php foreach ($documents as $document): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($document['document_title']); ?></td>
                                <td><?php echo htmlspecialchars($document['staff_name']); ?></td>
                                <td><?php echo $document_types[$document['document_type']] ?? $document['document_type']; ?></td>
                                <td><?php echo $document['upload_date_short']; ?></td>
                                <td>
                                    <?php if ($document['formatted_expiry_date']): ?>
                                        <?php 
                                        $expiry_class = 'bg-success';
                                        $current_date = new DateTime();
                                        $expiry_date = DateTime::createFromFormat('d M Y', $document['formatted_expiry_date']);
                                        
                                        if ($expiry_date) {
                                            $diff = $current_date->diff($expiry_date);
                                            $days_remaining = $diff->days;
                                            
                                            if ($expiry_date < $current_date) {
                                                $expiry_class = 'bg-danger';
                                            } elseif ($days_remaining <= 30) {
                                                $expiry_class = 'bg-warning text-dark';
                                            }
                                        }
                                        ?>
                                        <span class="badge <?php echo $expiry_class; ?>">
                                            <?php echo $document['expiry_date_short']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?php echo $document['file_path']; ?>" class="btn btn-sm btn-info" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo $document['file_path']; ?>" class="btn btn-sm btn-success" download>
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger delete-document" 
                                                data-id="<?php echo $document['id']; ?>" 
                                                data-title="<?php echo htmlspecialchars($document['document_title']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No documents found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadDocumentModalLabel">Upload New Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="staff_documents.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="staff_id" class="form-label">Staff Member <span class="text-danger">*</span></label>
                            <select class="form-select" id="staff_id" name="staff_id" required>
                                <option value="">Select Staff Member</option>
                                <?php foreach ($staff_members as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="document_type" class="form-label">Document Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="document_type" name="document_type" required>
                                <option value="">Select Document Type</option>
                                <?php foreach ($document_types as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="document_title" class="form-label">Document Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="document_title" name="document_title" required>
                        </div>
                        <div class="col-md-6">
                            <label for="expiry_date" class="form-label">Expiry Date (if applicable)</label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="document_file" class="form-label">Document File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="document_file" name="document_file" required>
                        <div class="form-text">Allowed file types: PDF, JPEG, PNG, DOC, DOCX. Max file size: 5MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Document Confirmation Modal -->
<div class="modal fade" id="deleteDocumentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the document "<span id="documentTitle"></span>"?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form action="staff_documents.php" method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="document_id" id="documentIdToDelete">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Document Preview Modal -->
<div class="modal fade" id="documentPreviewModal" tabindex="-1" aria-labelledby="documentPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentPreviewModalLabel">Document Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <iframe id="documentPreviewFrame" style="width: 100%; height: 600px; border: none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a id="documentDownloadLink" href="#" class="btn btn-primary" download>Download</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Upload Document Modal Button
    const uploadDocBtn = document.querySelector('button[data-bs-target="#uploadDocumentModal"]');
    if (uploadDocBtn) {
        uploadDocBtn.addEventListener('click', function() {
            const uploadModal = new bootstrap.Modal(document.getElementById('uploadDocumentModal'));
            uploadModal.show();
        });
    }
    
    // Document search functionality
    const documentSearch = document.getElementById('documentSearch');
    if (documentSearch) {
        documentSearch.addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const table = document.getElementById('documentsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length - 1; j++) { // Skip the actions column
                    const cellText = cells[j].textContent.toLowerCase();
                    
                    if (cellText.indexOf(searchValue) > -1) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        });
    }
    
    // Delete document functionality
    const deleteButtons = document.querySelectorAll('.delete-document');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const documentId = this.getAttribute('data-id');
            const documentTitle = this.getAttribute('data-title');
            
            document.getElementById('documentIdToDelete').value = documentId;
            document.getElementById('documentTitle').textContent = documentTitle;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteDocumentModal'));
            deleteModal.show();
        });
    });

    // Document preview functionality
    const previewButtons = document.querySelectorAll('.btn-info');
    previewButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Only handle clicks on the buttons with eye icon (preview)
            if(this.querySelector('.fa-eye')) {
                e.preventDefault(); // Prevent default link behavior
                
                const filePath = this.getAttribute('href');
                const fileExtension = filePath.split('.').pop().toLowerCase();
                
                // If it's a file type that can be displayed in browser
                if(['pdf', 'jpg', 'jpeg', 'png'].includes(fileExtension)) {
                    const previewFrame = document.getElementById('documentPreviewFrame');
                    const downloadLink = document.getElementById('documentDownloadLink');
                    
                    // Set the iframe src to the file path
                    previewFrame.src = filePath;
                    
                    // Update the download link
                    downloadLink.href = filePath;
                    
                    // Show the modal
                    const previewModal = new bootstrap.Modal(document.getElementById('documentPreviewModal'));
                    previewModal.show();
                } else {
                    // If it's a file type that can't be displayed, just download it
                    window.open(filePath, '_blank');
                }
            }
        });
    });
});
</script>

<style>
/* Custom styles for document cards */
.document-card {
    transition: transform 0.2s;
    margin-bottom: 20px;
}

.document-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.document-card .card-img-top {
    height: 160px;
    object-fit: cover;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}

.document-card .document-icon {
    font-size: 4rem;
    color: #6c757d;
}

.document-card .card-body {
    padding: 1.25rem;
}

.document-card .document-title {
    font-weight: 600;
    margin-bottom: 0.5rem;
    height: 2.5rem;
    overflow: hidden;
    text-overflow: ellipsis;
    /* Alternative solution without webkit properties */
    display: block;
    max-height: 2.5rem;
    line-height: 1.25rem;
}

.document-type {
    margin-bottom: 0.75rem;
}

.document-meta {
    font-size: 0.875rem;
    color: #6c757d;
}

.document-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 1rem;
}

/* Responsive table adjustments */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn-group .btn {
        padding: 0.25rem 0.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>