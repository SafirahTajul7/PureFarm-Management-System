<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

try {
    // Fetch deceased animals
    $stmt = $pdo->query("SELECT d.*, a.species, a.breed 
                        FROM deceased_animals d 
                        JOIN animals a ON d.animal_id = a.animal_id 
                        ORDER BY date_of_death DESC");
    $deceased_animals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deceased Records - PureFarm</title>
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

        .btn-primary:hover {
            background: #45a049;
        }

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

        .animal-table tr:hover {
            background-color: #f5f5f5;
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

        .deceased-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            background-color: #dc3545;
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
            }
            
            .form-container {
                padding: 15px;
            }
            
            .animal-table {
                display: block;
                overflow-x: auto;
            }
        }

        /* Form Header Styling */
        .form-container h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 18px;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }

        /* Input Focus States */
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
        }

        /* Required Field Indicator */
        .form-group label.required:after {
            content: " *";
            color: #dc3545;
        }

        /* Table Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-book-dead"></i> Deceased Animal Records</h2>
        </div>

        <!-- Add Deceased Record Form -->
        <div class="form-container">
            <h3>Record Deceased Animal</h3>
            <form action="add_deceased.php" method="POST">
                <div class="form-group">
                    <label for="animal_id">Animal ID</label>
                    <select name="animal_id" id="animal_id" required>
                        <option value="">Select Animal</option>
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT animal_id, species, breed FROM animals WHERE animal_id NOT IN (SELECT animal_id FROM deceased_animals)");
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
                    <label for="date_of_death">Date of Death</label>
                    <input type="date" name="date_of_death" id="date_of_death" required>
                </div>

                <div class="form-group">
                    <label for="cause">Cause of Death</label>
                    <input type="text" name="cause" id="cause" required>
                </div>

                <div class="form-group">
                    <label for="notes">Additional Notes</label>
                    <textarea name="notes" id="notes"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Record
                </button>
            </form>
        </div>

        <!-- Deceased Animals Table -->
        <table class="animal-table">
            <thead>
                <tr>
                    <th>Animal ID</th>
                    <th>Species</th>
                    <th>Breed</th>
                    <th>Date of Death</th>
                    <th>Cause</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(isset($deceased_animals) && count($deceased_animals) > 0): ?>
                    <?php foreach($deceased_animals as $animal): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($animal['animal_id']); ?></td>
                            <td><?php echo htmlspecialchars($animal['species']); ?></td>
                            <td><?php echo htmlspecialchars($animal['breed']); ?></td>
                            <td><?php echo date('d M Y', strtotime($animal['date_of_death'])); ?></td>
                            <td><?php echo htmlspecialchars($animal['cause']); ?></td>
                            <td><?php echo htmlspecialchars($animal['notes']); ?></td>
                            <td>
                                <a href="edit_deceased.php?id=<?php echo $animal['id']; ?>" class="action-btn edit-btn">Edit</a>
                                <a href="delete_deceased.php?id=<?php echo $animal['id']; ?>" 
                                   class="action-btn delete-btn" 
                                   onclick="return confirm('Are you sure you want to delete this record?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px;">
                            No deceased animal records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>