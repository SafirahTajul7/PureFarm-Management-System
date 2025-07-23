<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

try {
    // Fetch treatment records with animal details
    $stmt = $pdo->query("SELECT t.*, a.species, a.breed 
                        FROM treatments t 
                        JOIN animals a ON t.animal_id = a.animal_id 
                        ORDER BY t.treatment_date DESC");
    $treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treatment History - PureFarm</title>
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

        .form-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
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

        .treatment-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }

        .status-ongoing { background-color: #ffc107; color: black; }
        .status-completed { background-color: #28a745; color: white; }
        .status-scheduled { background-color: #17a2b8; color: white; }

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
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .animal-table th {
            background-color: #f8f9fa;
            font-weight: bold;
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

        .view-btn { background-color: #17a2b8; }
        .edit-btn { background-color: #ffc107; }
        .delete-btn { background-color: #dc3545; }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-clipboard-list"></i> Treatment History</h2>
        </div>

        <!-- Add Treatment Form -->
        <div class="form-container">
            <h3>Add Treatment Record</h3>
            <form action="add_treatment.php" method="POST">
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
                    <label for="treatment_date">Treatment Date</label>
                    <input type="date" name="treatment_date" id="treatment_date" required>
                </div>

                <div class="form-group">
                    <label for="condition">Medical Condition</label>
                    <input type="text" name="condition" id="condition" required>
                </div>

                <div class="form-group">
                    <label for="treatment_type">Treatment Type</label>
                    <select name="treatment_type" id="treatment_type" required>
                        <option value="">Select Treatment Type</option>
                        <option value="Medication">Medication</option>
                        <option value="Surgery">Surgery</option>
                        <option value="Therapy">Therapy</option>
                        <option value="Preventive">Preventive Care</option>
                        <option value="Emergency">Emergency Care</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="medication">Medications/Procedures</label>
                    <textarea name="medication" id="medication" required></textarea>
                </div>

                <div class="form-group">
                    <label for="vet_name">Veterinarian Name</label>
                    <input type="text" name="vet_name" id="vet_name" required>
                </div>

                <div class="form-group">
                    <label for="notes">Additional Notes</label>
                    <textarea name="notes" id="notes"></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Treatment Status</label>
                    <select name="status" id="status" required>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                        <option value="Scheduled">Scheduled</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Treatment Record
                </button>
            </form>
        </div>

        <!-- Treatment History Table -->
        <table class="animal-table">
            <thead>
                <tr>
                    <th>Animal ID</th>
                    <th>Species</th>
                    <th>Treatment Date</th>
                    <th>Condition</th>
                    <th>Treatment Type</th>
                    <th>Medications</th>
                    <th>Veterinarian</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(isset($treatments) && count($treatments) > 0): ?>
                    <?php foreach($treatments as $treatment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($treatment['animal_id']); ?></td>
                            <td><?php echo htmlspecialchars($treatment['species']); ?></td>
                            <td><?php echo date('d M Y', strtotime($treatment['treatment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($treatment['condition']); ?></td>
                            <td><?php echo htmlspecialchars($treatment['treatment_type']); ?></td>
                            <td><?php echo htmlspecialchars($treatment['medication']); ?></td>
                            <td><?php echo htmlspecialchars($treatment['vet_name']); ?></td>
                            <td>
                                <span class="treatment-status status-<?php echo strtolower($treatment['status']); ?>">
                                    <?php echo htmlspecialchars($treatment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="view_treatment.php?id=<?php echo $treatment['id']; ?>" class="action-btn view-btn">View</a>
                                <a href="edit_treatment.php?id=<?php echo $treatment['id']; ?>" class="action-btn edit-btn">Edit</a>
                                <a href="delete_treatment.php?id=<?php echo $treatment['id']; ?>" 
                                   class="action-btn delete-btn" 
                                   onclick="return confirm('Are you sure you want to delete this treatment record?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px;">
                            No treatment records found. Add your first treatment record using the form above.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>