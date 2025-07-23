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
        $condition = $_POST['condition'] ?? '';
        $treatment = $_POST['treatment'] ?? '';
        $vet_name = $_POST['vet_name'] ?? '';

        if (empty($date) || empty($condition) || empty($treatment) || empty($vet_name)) {
            throw new Exception("All fields are required");
        }

        // Insert the new health record - Note the backticks around `condition`
        $stmt = $pdo->prepare(
            "INSERT INTO health_records (animal_id, date, `condition`, treatment, vet_name) 
             VALUES (:animal_id, :date, :condition, :treatment, :vet_name)"
        );
        
        $stmt->execute([
            ':animal_id' => $animal['id'],
            ':date' => $date,
            ':condition' => $condition,
            ':treatment' => $treatment,
            ':vet_name' => $vet_name
        ]);

        $success_message = "Health record added successfully!";
        
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
    <title>Add Health Record - <?php echo htmlspecialchars($animal_id); ?></title>
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
            <h2>Add Health Record - <?php echo htmlspecialchars($animal_id); ?></h2>
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
                    <label for="condition">Condition</label>
                    <input type="text" id="condition" name="condition" class="form-control" required
                           placeholder="Enter animal's condition">
                </div>

                <div class="form-group">
                    <label for="treatment">Treatment</label>
                    <textarea id="treatment" name="treatment" class="form-control" rows="3" required
                            placeholder="Enter treatment details"></textarea>
                </div>

                <div class="form-group">
                    <label for="vet_name">Veterinarian</label>
                    <input type="text" id="vet_name" name="vet_name" class="form-control" required
                           placeholder="Enter veterinarian's name">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Add Health Record</button>
                    <a href="animal_details.php?id=<?php echo htmlspecialchars($animal_id); ?>" 
                       class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>