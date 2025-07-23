<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

// Predefined species list
$species_list = ['Cattle', 'Goat', 'Buffalo', 'Chicken', 'Duck', 'Rabbit'];

// Get selected species from URL parameter
$selected_species = isset($_GET['species']) ? $_GET['species'] : '';

try {
    // Fetch animals based on species filter
    if ($selected_species) {
        $stmt = $pdo->prepare("SELECT animal_id, species FROM animals WHERE species = ? ORDER BY animal_id ASC");
        $stmt->execute([$selected_species]);
    } else {
        $stmt = $pdo->query("SELECT animal_id, species FROM animals ORDER BY animal_id ASC");
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
    <title>Animal Identification - PureFarm</title>
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

        .identification-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .id-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .animal-id {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        .species-tag {
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .species-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #5a6268;
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

        .header-title h2 {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="header-title">
                <h2><i class="fas fa-fingerprint"></i> Animal Identification</h2>
            </div>
            <button class="btn btn-secondary" onclick="location.href='animal_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </button>
        </div>

        <!-- Species Filter Tabs -->
        <div class="species-tabs">
            <a href="animal_identification.php" class="species-tab <?php echo $selected_species === '' ? 'active' : ''; ?>">
                All Species
            </a>
            <?php foreach($species_list as $species): ?>
                <a href="?species=<?php echo urlencode($species); ?>" 
                   class="species-tab <?php echo $selected_species === $species ? 'active' : ''; ?>">
                    <?php echo $species; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="identification-grid">
            <?php if(isset($animals) && count($animals) > 0): ?>
                <?php foreach($animals as $animal): ?>
                    <div class="id-card">
                        <div class="animal-id">
                            <?php echo htmlspecialchars($animal['animal_id']); ?>
                        </div>
                        <div class="species-tag">
                            <span class="species-icon">
                                <?php
                                $icon = '';
                                switch(strtolower($animal['species'])) {
                                    case 'cattle':
                                        $icon = 'fa-cow';
                                        break;
                                    case 'goat':
                                        $icon = 'fa-goat';
                                        break;
                                    case 'buffalo':
                                        $icon = 'fa-horse';
                                        break;
                                    case 'chicken':
                                        $icon = 'fa-kiwi-bird';
                                        break;
                                    case 'duck':
                                        $icon = 'fa-dove';
                                        break;
                                    case 'rabbit':
                                        $icon = 'fa-rabbit';
                                        break;
                                    default:
                                        $icon = 'fa-paw';
                                }
                                ?>
                                <i class="fas <?php echo $icon; ?>"></i>
                            </span>
                            <?php echo htmlspecialchars($animal['species']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 20px;">
                    No animals found for this species.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>