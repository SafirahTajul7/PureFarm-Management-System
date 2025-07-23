<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Missing deceased record ID";
    header("Location: animals_lifecycle.php");
    exit();
}

$id = $_GET['id'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate inputs
        if (empty($_POST['animal_id']) || empty($_POST['date_of_death']) || empty($_POST['cause'])) {
            $_SESSION['error'] = "All required fields must be filled";
            header("Location: edit_deceased.php?id=" . $id);
            exit();
        }

        // Update the record
        $stmt = $pdo->prepare("
            UPDATE deceased_animals 
            SET animal_id = :animal_id, 
                date_of_death = :date_of_death, 
                cause = :cause, 
                notes = :notes 
            WHERE id = :id
        ");

        $stmt->execute([
            ':animal_id' => $_POST['animal_id'],
            ':date_of_death' => $_POST['date_of_death'],
            ':cause' => $_POST['cause'],
            ':notes' => $_POST['notes'],
            ':id' => $id
        ]);

        $_SESSION['success'] = "Deceased animal record updated successfully";
        header("Location: animals_lifecycle.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating record: " . $e->getMessage();
        header("Location: edit_deceased.php?id=" . $id);
        exit();
    }
}

// Get the deceased record
try {
    $stmt = $pdo->prepare("
        SELECT d.*, a.species, a.breed 
        FROM deceased_animals d
        LEFT JOIN animals a ON d.animal_id = a.animal_id
        WHERE d.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        $_SESSION['error'] = "Record not found";
        header("Location: animals_lifecycle.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error retrieving record: " . $e->getMessage();
    header("Location: animals_lifecycle.php");
    exit();
}

// Get all animals for dropdown (including the current one)
try {
    $animalsStmt = $pdo->prepare("
        SELECT animal_id, species, breed 
        FROM animals 
        WHERE animal_id = :current_animal_id
        OR animal_id NOT IN (SELECT animal_id FROM deceased_animals WHERE id != :current_id)
        ORDER BY species, animal_id
    ");
    $animalsStmt->execute([
        ':current_animal_id' => $record['animal_id'],
        ':current_id' => $id
    ]);
    $animals = $animalsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error retrieving animals: " . $e->getMessage();
    // Continue anyway, but with potentially incomplete dropdown
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Deceased Record - PureFarm</title>
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

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
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
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        /* Responsive adjustments */
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
            <h2><i class="fas fa-pen"></i> Edit Deceased Animal Record</h2>
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
            <form action="edit_deceased.php?id=<?php echo $id; ?>" method="POST">
                <div class="form-group">
                    <label for="animal_id">Animal ID</label>
                    <select name="animal_id" id="animal_id" required>
                        <?php foreach ($animals as $animal): ?>
                            <option value="<?php echo htmlspecialchars($animal['animal_id']); ?>" 
                                <?php echo ($animal['animal_id'] == $record['animal_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($animal['animal_id'] . " - " . $animal['species'] . " (" . $animal['breed'] . ")"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_of_death">Date of Death</label>
                    <input type="date" name="date_of_death" id="date_of_death" value="<?php echo htmlspecialchars($record['date_of_death']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="cause">Cause of Death</label>
                    <input type="text" name="cause" id="cause" value="<?php echo htmlspecialchars($record['cause']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="notes">Additional Notes</label>
                    <textarea name="notes" id="notes"><?php echo htmlspecialchars($record['notes']); ?></textarea>
                </div>

                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Record
                    </button>
                    <a href="animals_lifecycle.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>