<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

$animal_id = isset($_GET['id']) ? $_GET['id'] : '';
$success_message = '';
$error_message = '';

// Fetch animal details first
try {
    $stmt = $pdo->prepare("SELECT id FROM animals WHERE animal_id = ?");
    $stmt->execute([$animal_id]);
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$animal) {
        die("Animal not found");
    }
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize inputs
        $date = $_POST['date'] ?? '';
        $weight = $_POST['weight'] ?? '';
        $notes = $_POST['notes'] ?? '';

        if (empty($date) || empty($weight)) {
            throw new Exception("Date and weight are required fields");
        }

        // Validate weight is a positive number
        if (!is_numeric($weight) || $weight <= 0) {
            throw new Exception("Weight must be a positive number");
        }

        // Insert the new weight record
        $stmt = $pdo->prepare(
            "INSERT INTO weight_records (animal_id, date, weight, notes) 
             VALUES (:animal_id, :date, :weight, :notes)"
        );
        
        $stmt->execute([
            ':animal_id' => $animal['id'],
            ':date' => $date,
            ':weight' => $weight,
            ':notes' => $notes
        ]);

        $success_message = "Weight record added successfully!";
        
        // Redirect back to animal details page
        header("Location: animal_details.php?id=" . $animal_id);
        exit();

    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Weight Record - <?php echo htmlspecialchars($animal_id); ?></title>
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

        .main-content {
            margin-left: 280px;
            padding: 20px;
        }

        .page-header {
            margin-bottom: 20px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2>Add Weight Record - <?php echo htmlspecialchars($animal_id); ?></h2>
            <a href="animal_details.php?id=<?php echo htmlspecialchars($animal_id); ?>" class="btn btn-secondary">
                ← Back to Details
            </a>
        </div>

        <div class="form-container">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="weight">Weight (kg)</label>
                    <input type="number" id="weight" name="weight" step="0.01" min="0" 
                           class="form-control" required placeholder="Enter weight in kilograms">
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3" 
                            placeholder="Enter any additional notes"></textarea>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Add Weight Record</button>
                    <a href="animal_details.php?id=<?php echo htmlspecialchars($animal_id); ?>" 
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>