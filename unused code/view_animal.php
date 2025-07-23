<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: animal_records.php");
    exit();
}

$id = $_GET['id'];

try {
    // Fetch animal details
    $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
    $stmt->execute([$id]);
    $animal = $stmt->fetch();

    // Fetch health records
    $healthStmt = $pdo->prepare("SELECT * FROM health_records WHERE animal_id = ? ORDER BY date DESC");
    $healthStmt->execute([$id]);
    $healthRecords = $healthStmt->fetchAll();

    // Fetch vaccination records
    $vaccStmt = $pdo->prepare("SELECT * FROM vaccinations WHERE animal_id = ? ORDER BY date DESC");
    $vaccStmt->execute([$id]);
    $vaccinations = $vaccStmt->fetchAll();

    // Fetch weight records
    $weightStmt = $pdo->prepare("SELECT * FROM weight_records WHERE animal_id = ? ORDER BY date DESC");
    $weightStmt->execute([$id]);
    $weightRecords = $weightStmt->fetchAll();
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Animal - PureFarm</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .detail-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .record-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .record-item:last-child {
            border-bottom: none;
        }

        .health-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
        }

        .health-status.healthy {
            background-color: #d4edda;
            color: #155724;
        }

        .health-status.sick {
            background-color: #f8d7da;
            color: #721c24;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            color: white;
            background: #4CAF50;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .weight-chart {
            height: 300px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            .section-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-paw"></i> Animal Details</h2>
            <a href="edit_animal.php?id=<?php echo $id; ?>" class="btn">
                <i class="fas fa-edit"></i> Edit Animal
            </a>
        </div>

        <div class="detail-section">
            <h3>Basic Information</h3>
            <div class="section-grid">
                <div>
                    <p><strong>ID:</strong> <?php echo htmlspecialchars($animal['animal_id']); ?></p>
                    <p><strong>Species:</strong> <?php echo htmlspecialchars($animal['species']); ?></p>
                    <p><strong>Breed:</strong> <?php echo htmlspecialchars($animal['breed']); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo date('d M Y', strtotime($animal['date_of_birth'])); ?></p>
                </div>
                <div>
                    <p><strong>Health Status:</strong> 
                        <span class="health-status <?php echo strtolower($animal['health_status']); ?>">
                            <?php echo htmlspecialchars($animal['health_status']); ?>
                        </span>
                    </p>
                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($animal['gender']); ?></p>
                    <p><strong>Source:</strong> <?php echo htmlspecialchars($animal['source']); ?></p>
                </div>
            </div>
        </div>

        <div class="section-grid">
            <div class="detail-section">
                <h3>Health Records</h3>
                <?php if (!empty($healthRecords)): ?>
                    <?php foreach($healthRecords as $record): ?>
                        <div class="record-item">
                            <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($record['date'])); ?></p>
                            <p><strong>Condition:</strong> <?php echo htmlspecialchars($record['condition']); ?></p>
                            <p><strong>Treatment:</strong> <?php echo htmlspecialchars($record['treatment']); ?></p>
                            <p><strong>Veterinarian:</strong> <?php echo htmlspecialchars($record['vet_name']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No health records found.</p>
                <?php endif; ?>
            </div>

            <div class="detail-section">
                <h3>Vaccination Records</h3>
                <?php if (!empty($vaccinations)): ?>
                    <?php foreach($vaccinations as $vacc): ?>
                        <div class="record-item">
                            <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($vacc['date'])); ?></p>
                            <p><strong>Type:</strong> <?php echo htmlspecialchars($vacc['type']); ?></p>
                            <p><strong>Next Due:</strong> <?php echo date('d M Y', strtotime($vacc['next_due'])); ?></p>
                            <p><strong>Administered By:</strong> <?php echo htmlspecialchars($vacc['administered_by']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No vaccination records found.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="detail-section">
            <h3>Weight History</h3>
            <canvas id="weightChart" class="weight-chart"></canvas>
            <?php if (!empty($weightRecords)): ?>
                <?php foreach($weightRecords as $record): ?>
                    <div class="record-item">
                        <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($record['date'])); ?></p>
                        <p><strong>Weight:</strong> <?php echo htmlspecialchars($record['weight']); ?> kg</p>
                        <?php if (!empty($record['notes'])): ?>
                            <p><strong>Notes:</strong> <?php echo htmlspecialchars($record['notes']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No weight records found.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Initialize weight chart
        const weightData = <?php 
            $dates = array_map(function($record) {
                return date('d M Y', strtotime($record['date']));
            }, $weightRecords);
            $weights = array_map(function($record) {
                return $record['weight'];
            }, $weightRecords);
            echo json_encode(['dates' => $dates, 'weights' => $weights]);
        ?>;

        new Chart(document.getElementById('weightChart'), {
            type: 'line',
            data: {
                labels: weightData.dates,
                datasets: [{
                    label: 'Weight (kg)',
                    data: weightData.weights,
                    borderColor: '#4CAF50',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false
                    }
                }
            }
        });
    </script>
</body>
</html>