<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';


// Get selected species from URL parameter
$selected_species = isset($_GET['species']) ? $_GET['species'] : '';

try {
    // Fetch all unique species for the tabs
    $speciesStmt = $pdo->query("SELECT DISTINCT species FROM animals ORDER BY species");
    $species_list = $speciesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch animals based on species filter
    if ($selected_species) {
        $stmt = $pdo->prepare("SELECT * FROM animals WHERE species = ? ORDER BY animal_id ASC");
        $stmt->execute([$selected_species]);
    } else {
        $stmt = $pdo->query("SELECT * FROM animals ORDER BY animal_id ASC");
    }
    $animals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Records - PureFarm</title>
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

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .feature-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .feature-card h3 {
            margin-bottom: 15px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feature-card ul {
            list-style: none;
        }

        .feature-card li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }

        .feature-card li:hover {
            background: #f8f9fa;
        }

        .feature-card li:last-child {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
            }
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

        .animal-table th, .animal-table td {
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

        .health-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }

        .health-status.healthy {
            background-color: #d4edda;
            color: #155724;
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

        .species-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .species-tab {
            padding: 6px 12px;
            background: white;
            border: 1px solid #4CAF50;
            border-radius: 4px;
            cursor: pointer;
            color: #4CAF50;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .species-tab:hover {
            background: #4CAF50;
            color: white;
        }

        .species-tab.active {
            background: #4CAF50;
            color: white;
        }

        .health-status.injured {
            background-color: #fff3cd;
            color: #856404;
        }

        .health-status.sick {
            background-color: #f8d7da;
            color: #721c24;
        }

        .health-status.quarantine {
            background-color: #ffeeba;
            color: #856404;
        }
        </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-id-card"></i> Animal Records</h2>
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="location.href='addanimal.php'">
                    <i class="fas fa-plus"></i> Add New Animal
                </button>
                <button class="btn btn-primary" onclick="location.href='animal_management.php'">
                    <i class="fas fa-arrow-left"></i> Back to Animal Management
                </button>
            </div>
        </div>

        <!-- Species Filter Tabs -->
        <div class="species-tabs">
            <a href="animal_records.php" class="species-tab <?php echo $selected_species === '' ? 'active' : ''; ?>">
                All Species
            </a>
            <?php foreach($species_list as $species): ?>
                <a href="?species=<?php echo urlencode($species); ?>" 
                   class="species-tab <?php echo $selected_species === $species ? 'active' : ''; ?>">
                    <?php echo ucfirst($species); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <table class="animal-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Species</th>
                    <th>Breed</th>
                    <th>Date of Birth</th>
                    <th>Health Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(isset($animals) && count($animals) > 0): ?>
                    <?php foreach($animals as $animal): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($animal['animal_id']); ?></td>
                            <td><?php echo htmlspecialchars($animal['species']); ?></td>
                            <td><?php echo htmlspecialchars($animal['breed']); ?></td>
                            <td><?php echo date('d M Y', strtotime($animal['date_of_birth'])); ?></td>
                            <td>
                                <span class="health-status <?php echo strtolower($animal['health_status']); ?>">
                                    <?php echo htmlspecialchars($animal['health_status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="animal_details.php?id=<?php echo htmlspecialchars($animal['animal_id']); ?>" class="action-btn view-btn">View</a>
                                <a href="edit_animal.php?id=<?php echo $animal['id']; ?>" class="action-btn edit-btn">Edit</a>
                                <a href="delete_animal.php?id=<?php echo $animal['id']; ?>" 
                                    class="action-btn delete-btn" 
                                    onclick="return confirm('Are you sure you want to delete this animal? This will also delete all related records.')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px;">
                            <?php if ($selected_species): ?>
                                No <?php echo htmlspecialchars($selected_species); ?> found in the database. 
                                <a href="addanimal.php">Add a new animal</a>
                            <?php else: ?>
                                No animals found in the database. 
                                <a href="addanimal.php">Add your first animal</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>