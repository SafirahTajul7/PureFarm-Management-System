<?php
session_start();
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("INSERT INTO health_schedules (animal_id, date, appointment_type, details, vet_name, status) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['animal_id'],
            $_POST['date'],
            $_POST['appointment_type'],
            $_POST['details'],
            $_POST['vet_name'],
            $_POST['status']
        ]);

        $_SESSION['success_message'] = "Schedule added successfully!";
        echo "Success";
    } catch (PDOException $e) {
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Health Schedule - PureFarm</title>
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
            font-size: 14px;
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

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-plus"></i> Add New Health Schedule</h2>
            <a href="health_schedules.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Schedules
            </a>
        </div>

        <div class="form-container">
            <form action="add_schedule.php" method="POST">
                <div class="form-group">
                    <label for="animal_id">Animal ID</label>
                    <select class="form-control" name="animal_id" required>
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
                    <label for="date">Date & Time</label>
                    <input type="datetime-local" class="form-control" name="date" required>
                </div>

                <div class="form-group">
                    <label for="appointment_type">Appointment Type</label>
                    <select class="form-control" name="appointment_type" required>
                        <option value="">Select Type</option>
                        <option value="Vaccination">Vaccination</option>
                        <option value="Check-up">Check-up</option>
                        <option value="Treatment">Treatment</option>
                        <option value="Surgery">Surgery</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="details">Details</label>
                    <textarea class="form-control" name="details" rows="3" required></textarea>
                </div>

                <div class="form-group">
                    <label for="vet_name">Veterinarian</label>
                    <select class="form-control" name="vet_name" required>
                        <option value="">Select Veterinarian</option>
                        <option value="Dr. Sarah Lee">Dr. Sarah Lee</option>
                        <option value="Dr. Ahmad Kamal">Dr. Ahmad Kamal</option>
                        <option value="Dr. Ahmed Khan">Dr. Ahmed Khan</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" name="status" required>
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Schedule
                    </button>
                    <a href="health_schedules.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>