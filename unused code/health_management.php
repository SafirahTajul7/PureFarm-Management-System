<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

try {
    // Fetch health records with animal details
    $stmt = $pdo->query("SELECT h.*, a.species, a.breed 
                        FROM health_records h 
                        JOIN animals a ON h.animal_id = a.animal_id 
                        ORDER BY h.date DESC");
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
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f0f2f5;
            min-height: 100vh;
        }

        /* Layout Styles */
        .container {
            display: flex;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
        }

        /* Header Styles */
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

        .page-header h2 {
            color: #333;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header i {
            color: #4CAF50;
        }

        /* Form Container Styles */
        .form-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .form-container h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4CAF50;
        }

        /* Form Group Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }

        .form-group label.required:after {
            content: " *";
            color: #dc3545;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
        }

        .form-group textarea {
            height: 120px;
            resize: vertical;
        }

        /* Button Styles */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-1px);
        }

        .btn i {
            font-size: 16px;
        }

        /* Table Styles */
        .animal-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .animal-table th,
        .animal-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .animal-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        .animal-table tr:hover {
            background-color: #f5f5f5;
        }

        .animal-table tr:last-child td {
            border-bottom: none;
        }

        /* Health Status Indicators */
        .health-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
        }

        .condition-excellent {
            background-color: #28a745;
            color: white;
        }

        .condition-good {
            background-color: #17a2b8;
            color: white;
        }

        .condition-normal {
            background-color: #ffc107;
            color: black;
        }

        .condition-poor {
            background-color: #dc3545;
            color: white;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .view-btn {
            background-color: #17a2b8;
        }

        .edit-btn {
            background-color: #ffc107;
            color: #000;
        }

        .delete-btn {
            background-color: #dc3545;
        }

        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Empty State Styles */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-size: 15px;
        }

        .empty-state a {
            color: #4CAF50;
            text-decoration: none;
            font-weight: bold;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 250px;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 200px;
            }
            
            .form-container {
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .form-container {
                padding: 15px;
            }
            
            .animal-table {
                display: block;
                overflow-x: auto;
            }
            
            .animal-table th,
            .animal-table td {
                padding: 12px 10px;
            }
        }

        @media (max-width: 576px) {
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                text-align: center;
            }
            
            .health-status {
                width: 100%;
                margin: 2px 0;
            }
        }

        /* Print Styles */
        @media print {
            .main-content {
                margin-left: 0;
            }
            
            .form-container,
            .btn,
            .action-btn {
                display: none;
            }
            
            .animal-table {
                box-shadow: none;
            }
            
            .health-status {
                border: 1px solid #000;
            }
        }

        /* Animation Effects */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .form-container,
        .animal-table {
            animation: fadeIn 0.3s ease-in-out;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-notes-medical"></i> Health Records</h2>
        </div>

        <!-- Add Health Record Form -->
        <div class="form-container">
            <h3>Add Health Record</h3>
            <form action="add_health_record.php" method="POST">
                <div class="form-group">
                    <label for="animal_id">Animal ID</label>
                    <select name="animal_id" id="animal_id" required>
                        <option value="">Select Animal</option>
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT animal_id, species, breed FROM animals ORDER BY animal_id");
                            while ($row = $stmt->fetch()) {
                                echo "<option value='" . $row['animal_id'] . "'>" . 
                                     $row['animal_id'] . " - " . $row['species'] . " (" . $row['breed'] . ")</option>";
                            }
                        } catch(PDOException $e) {
                            echo "<option value=''>Error loading animals</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date">Examination Date</label>
                    <input type="date" name="date" id="date" required>
                </div>

                <div class="form-group">
                    <label for="condition">Health Condition</label>
                    <select name="condition" id="condition" required>
                        <option value="Excellent">Excellent</option>
                        <option value="Good">Good</option>
                        <option value="Normal">Normal</option>
                        <option value="Poor">Poor</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="treatment">Treatment/Notes</label>
                    <textarea name="treatment" id="treatment" required></textarea>
                </div>

                <div class="form-group">
                    <label for="vet_name">Veterinarian Name</label>
                    <input type="text" name="vet_name" id="vet_name" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Record
                </button>
            </form>
        </div>

        <!-- Health Records Table -->
        <table class="animal-table">
            <thead>
                <tr>
                    <th>Animal ID</th>
                    <th>Species</th>
                    <th>Breed</th>
                    <th>Date</th>
                    <th>Condition</th>
                    <th>Treatment/Notes</th>
                    <th>Veterinarian</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(isset($health_records) && count($health_records) > 0): ?>
                    <?php foreach($health_records as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['animal_id']); ?></td>
                            <td><?php echo htmlspecialchars($record['species']); ?></td>
                            <td><?php echo htmlspecialchars($record['breed']); ?></td>
                            <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                            <td>
                                <span class="health-status condition-<?php echo strtolower($record['condition']); ?>">
                                    <?php echo htmlspecialchars($record['condition']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($record['treatment']); ?></td>
                            <td><?php echo htmlspecialchars($record['vet_name']); ?></td>
                            <td>
                                <a href="edit_health_record.php?id=<?php echo $record['id']; ?>" class="action-btn edit-btn">Edit</a>
                                <a href="delete_health_record.php?id=<?php echo $record['id']; ?>" 
                                   class="action-btn delete-btn" 
                                   onclick="return confirm('Are you sure you want to delete this record?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="empty-state">
                            No health records found. Add your first health record using the form above.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>