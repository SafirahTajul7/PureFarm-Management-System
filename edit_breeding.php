<?php
session_start();
require_once 'includes/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Check if we have an ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid breeding record ID";
    header("Location: animals_lifecycle.php");
    exit();
}

$id = $_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate that all required fields are present
        if (empty($_POST['animal_id']) || empty($_POST['partner_id']) || 
            empty($_POST['date']) || empty($_POST['outcome'])) {
            throw new Exception("All required fields must be filled");
        }
        
        // Format date properly
        $date = date('Y-m-d', strtotime($_POST['date']));
        
        // Update the breeding record
        $stmt = $pdo->prepare("
            UPDATE breeding_history 
            SET animal_id = :animal_id, 
                partner_id = :partner_id, 
                date = :date, 
                outcome = :outcome, 
                notes = :notes
            WHERE id = :id
        ");
        
        $params = [
            ':animal_id' => $_POST['animal_id'],
            ':partner_id' => $_POST['partner_id'],
            ':date' => $date,
            ':outcome' => $_POST['outcome'],
            ':notes' => $_POST['notes'] ?? '',
            ':id' => $id
        ];

        // Execute the statement
        $result = $stmt->execute($params);
        
        if ($result) {
            $_SESSION['success'] = "Breeding record updated successfully";
            header("Location: animals_lifecycle.php?success=1");
            exit();
        } else {
            $error = $stmt->errorInfo();
            throw new Exception("Update failed: " . print_r($error, true));
        }

    } catch(PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    } catch(Exception $e) {
        $_SESSION['error'] = "Error updating breeding record: " . $e->getMessage();
    }
}

// Fetch the breeding record
try {
    $stmt = $pdo->prepare("
        SELECT * FROM breeding_history WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        $_SESSION['error'] = "Breeding record not found";
        header("Location: animals_lifecycle.php");
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = "Error fetching breeding record: " . $e->getMessage();
    header("Location: animals_lifecycle.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Breeding Record - PureFarm</title>
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
            display: inline-flex;
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

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
            }
            
            .form-container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-edit"></i> Edit Breeding Record</h2>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error']; 
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <h3>Edit Breeding Record</h3>
            
            <form action="edit_breeding.php?id=<?php echo $id; ?>" method="POST" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="animal_id">Female Animal</label>
                    <select name="animal_id" id="animal_id" required>
                        <option value="">Select Female Animal</option>
                        <?php
                        try {
                            // Query to get female animals
                            $femaleStmt = $pdo->query("
                                SELECT animal_id, species, breed 
                                FROM animals 
                                WHERE gender = 'female'
                                ORDER BY species, animal_id
                            ");
                            
                            while ($row = $femaleStmt->fetch()) {
                                $selected = ($row['animal_id'] == $record['animal_id']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($row['animal_id']) . "' $selected>" . 
                                    htmlspecialchars($row['animal_id'] . " - " . $row['species'] . " (" . $row['breed'] . ")") . "</option>";
                            }
                        } catch(PDOException $e) {
                            echo "<option value=''>Error loading animals: " . htmlspecialchars($e->getMessage()) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="partner_id">Male Animal</label>
                    <select name="partner_id" id="partner_id" required>
                        <option value="">Select Male Animal</option>
                        <?php
                        try {
                            // Query to get male animals
                            $maleStmt = $pdo->query("
                                SELECT animal_id, species, breed 
                                FROM animals 
                                WHERE gender = 'male'
                                ORDER BY species, animal_id
                            ");
                            
                            while ($row = $maleStmt->fetch()) {
                                $selected = ($row['animal_id'] == $record['partner_id']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($row['animal_id']) . "' $selected>" . 
                                    htmlspecialchars($row['animal_id'] . " - " . $row['species'] . " (" . $row['breed'] . ")") . "</option>";
                            }
                        } catch(PDOException $e) {
                            echo "<option value=''>Error loading animals: " . htmlspecialchars($e->getMessage()) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date">Breeding Date</label>
                    <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($record['date']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="outcome">Outcome</label>
                    <select name="outcome" id="outcome" required>
                        <option value="pending" <?php echo ($record['outcome'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="successful" <?php echo ($record['outcome'] == 'successful') ? 'selected' : ''; ?>>Successful</option>
                        <option value="failed" <?php echo ($record['outcome'] == 'failed') ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes"><?php echo htmlspecialchars($record['notes']); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="animals_lifecycle.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function validateForm() {
            // Get form elements
            const femaleAnimal = document.getElementById('animal_id').value;
            const maleAnimal = document.getElementById('partner_id').value;
            const breedingDate = document.getElementById('date').value;
            const outcome = document.getElementById('outcome').value;
            
            // Check if required fields are filled
            if (!femaleAnimal || !maleAnimal || !breedingDate || !outcome) {
                alert('Please fill in all required fields');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>