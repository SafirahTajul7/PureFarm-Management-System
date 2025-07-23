<?php
// Start session before any output
session_start();

require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Check if success message exists in session
$successMessage = '';
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

try {
    // Fetch health records with animal details
    $stmt = $pdo->query("SELECT h.*, a.animal_id as animal_code 
                         FROM health_records h 
                         JOIN animals a ON h.animal_id = a.id 
                         ORDER BY CAST(SUBSTRING(a.animal_id, 2) AS UNSIGNED), h.date DESC");
    $health_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Records - PureFarm</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f0f2f5;
        }

        .container {
            display: flex;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .health-records-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .health-records-table th, 
        .health-records-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .health-records-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .health-records-table tr:hover {
            background-color: #f5f5f5;
        }

        .health-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }

        .health-status.good {
            background-color: #d4edda;
            color: #155724;
        }

        .health-status.normal {
            background-color: #fff3cd;
            color: #856404;
        }

        .health-status.excellent {
            background-color: #cce5ff;
            color: #004085;
        }

        .health-status.fever {
            background-color: #f8d7da;
            color: #721c24;
        }

        .action-btn {
            padding: 5px 10px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            font-size: 12px;
            margin-right: 5px;
            display: inline-block;
        }

        .edit-btn { background-color: #ffc107; }
        .delete-btn { background-color: #dc3545; }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal.show {
            display: block;
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php if ($successMessage): ?>
            <div class="success-message">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h2><i class="fas fa-notes-medical"></i> Health Records</h2>
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Record
                </button>
                <button class="btn btn-primary" onclick="location.href='animal_management.php'">
                    <i class="fas fa-arrow-left"></i> Back to Animal Management
                </button>
            </div>
        </div>

        <table class="health-records-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Animal ID</th>
                    <th>Date</th>
                    <th>Condition</th>
                    <th>Treatment</th>
                    <th>Veterinarian</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(isset($health_records) && count($health_records) > 0): ?>
                    <?php foreach($health_records as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['id']); ?></td>
                            <td><?php echo htmlspecialchars($record['animal_code']); ?></td>
                            <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                            <td>
                                <span class="health-status <?php echo strtolower($record['condition']); ?>">
                                    <?php echo htmlspecialchars($record['condition']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($record['treatment']); ?></td>
                            <td><?php echo htmlspecialchars($record['vet_name']); ?></td>
                            <td>
                                <button class="action-btn edit-btn" onclick="editRecord(<?php echo $record['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete-btn" onclick="deleteRecord(<?php echo $record['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">
                            No health records found. 
                            <button onclick="openAddModal()" style="color: #4CAF50; border: none; background: none; cursor: pointer;">
                                Add your first record
                            </button>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add/Edit Modal -->
    <div id="recordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Health Record</h3>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            <form id="recordForm" onsubmit="handleSubmit(event)">
                <input type="hidden" id="record_id" name="id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="animal_id">Animal ID</label>
                        <select class="form-control" id="animal_id" name="animal_id" required>
                            <option value="">Select Animal</option>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT id, animal_id, species, breed FROM animals");
                                while($animal = $stmt->fetch()) {
                                    echo "<option value='{$animal['id']}'>{$animal['animal_id']} - {$animal['species']} ({$animal['breed']})</option>";
                                }
                            } catch(PDOException $e) {
                                echo "Error: " . $e->getMessage();
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="date" class="form-control" id="date" name="date" required>
                    </div>
                    <div class="form-group">
                        <label for="condition">Condition</label>
                        <select class="form-control" id="condition" name="condition" required>
                            <option value="">Select Condition</option>
                            <option value="Good">Good</option>
                            <option value="Normal">Normal</option>
                            <option value="Excellent">Excellent</option>
                            <option value="Fever">Fever</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="treatment">Treatment</label>
                        <input type="text" class="form-control" id="treatment" name="treatment" required>
                    </div>
                    <div class="form-group">
                        <label for="vet_name">Veterinarian</label>
                        <select class="form-control" id="vet_name" name="vet_name" required>
                            <option value="">Select Veterinarian</option>
                            <option value="Dr. Sarah Lee">Dr. Sarah Lee</option>
                            <option value="Dr. Ahmad Kamal">Dr. Ahmad Kamal</option>
                            <option value="Dr. Ahmed Khan">Dr. Ahmed Khan</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Function to refresh the table content
        function refreshTable() {
            fetch('get_health_records.php')
                .then(response => response.text())
                .then(html => {
                    document.querySelector('.health-records-table tbody').innerHTML = html;
                })
                .catch(error => console.error('Error:', error));
        }

        function showMessage(message, isError = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = isError ? 'error-message' : 'success-message';
            messageDiv.textContent = message;
            
            const container = document.querySelector('.main-content');
            container.insertBefore(messageDiv, container.firstChild);
            
            setTimeout(() => {
                messageDiv.remove();
            }, 5000);
        }

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Health Record';
            document.getElementById('recordForm').reset();
            document.getElementById('record_id').value = '';
            document.getElementById('recordModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('recordModal').classList.remove('show');
        }

        function editRecord(id) {
            fetch('get_record.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        showMessage(data.error, true);
                        return;
                    }
                    
                    document.getElementById('modalTitle').textContent = 'Edit Health Record';
                    document.getElementById('record_id').value = data.id;
                    document.getElementById('animal_id').value = data.animal_id;
                    document.getElementById('date').value = data.date;
                    document.getElementById('condition').value = data.condition;
                    document.getElementById('treatment').value = data.treatment;
                    document.getElementById('vet_name').value = data.vet_name;
                    document.getElementById('recordModal').classList.add('show');
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Error fetching record details', true);
                });
        }

        function deleteRecord(id) {
            if (confirm('Are you sure you want to delete this record?')) {
                fetch('delete_record.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage('Record deleted successfully');
                        refreshTable();
                    } else {
                        showMessage(data.message || 'Error deleting record', true);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Error deleting record', true);
                });
            }
        }

        function handleSubmit(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            const id = formData.get('id');
            const url = id ? 'edit_record.php' : 'add_record.php';

            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())  // Change this line to see raw response
            .then(data => {
                console.log('Raw response:', data);  // Add this line
                try {
                    const jsonData = JSON.parse(data);
                    if (jsonData.success) {
                        closeModal();
                        showMessage(id ? 'Record updated successfully' : 'Record added successfully');
                        refreshTable();
                    } else {
                        showMessage(jsonData.message || 'Error saving record', true);
                    }
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    showMessage('Error processing server response', true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Error saving record', true);
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('recordModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Close success message after 5 seconds
        const successMessage = document.querySelector('.success-message');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>