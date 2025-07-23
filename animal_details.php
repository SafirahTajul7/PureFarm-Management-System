<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

// Get animal ID from URL parameter
$animal_id = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($animal_id)) {
    echo '<div style="padding: 20px; text-align: center;">';
    echo '<h2>Error: Animal ID is required</h2>';
    echo '<p>Please select an animal from the ';
    echo '<a href="animal_records.php" style="color: blue; text-decoration: underline;">Animal Records List</a>';
    echo '</p></div>';
    exit();
}

try {
    // Fetch animal basic details
    $stmt = $pdo->prepare("SELECT * FROM animals WHERE animal_id = ?");
    $stmt->execute([$animal_id]);
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$animal) {
        echo '<div style="padding: 20px; text-align: center;">';
        echo '<h2>Error: Animal not found</h2>';
        echo '<p>Please select an animal from the ';
        echo '<a href="animal_records.php" style="color: blue; text-decoration: underline;">Animal Records List</a>';
        echo '</p></div>';
        exit();
    }

    // Pagination setup
    $records_per_page = 5;
    
    // Health Records Pagination
    $health_page = isset($_GET['health_page']) ? (int)$_GET['health_page'] : 1;
    $health_start = ($health_page - 1) * $records_per_page;
    
    // Weight Records Pagination
    $weight_page = isset($_GET['weight_page']) ? (int)$_GET['weight_page'] : 1;
    $weight_start = ($weight_page - 1) * $records_per_page;
    
    // Vaccination Records Pagination
    $vacc_page = isset($_GET['vacc_page']) ? (int)$_GET['vacc_page'] : 1;
    $vacc_start = ($vacc_page - 1) * $records_per_page;

    // Fetch health records with pagination
    $healthStmt = $pdo->prepare(
        "SELECT hr.* 
        FROM health_records hr
        INNER JOIN animals a ON a.id = hr.animal_id
        WHERE a.animal_id = ?
        ORDER BY hr.date DESC 
        LIMIT $health_start, $records_per_page"
    );
    $healthStmt->execute([$animal['animal_id']]);  // Use the animal_id from animals table
    $healthRecords = $healthStmt->fetchAll(PDO::FETCH_ASSOC);


    // Get total health records
    $total_health_stmt = $pdo->prepare(
        "SELECT COUNT(*) 
        FROM health_records hr
        INNER JOIN animals a ON a.id = hr.animal_id
        WHERE a.animal_id = ?"
    );
    $total_health_stmt->execute([$animal['animal_id']]);
    $total_health = $total_health_stmt->fetchColumn();
    $total_health_pages = ceil($total_health / $records_per_page);


    // Fetch weight records with pagination
    $weightStmt = $pdo->prepare(
        "SELECT wr.* 
        FROM weight_records wr
        INNER JOIN animals a ON a.id = wr.animal_id
        WHERE a.animal_id = ?
        ORDER BY wr.date DESC 
        LIMIT $weight_start, $records_per_page"
    );
    $weightStmt->execute([$animal['animal_id']]);
    $weightRecords = $weightStmt->fetchAll(PDO::FETCH_ASSOC);


    // Get total weight records
    $total_weight_stmt = $pdo->prepare(
        "SELECT COUNT(*) 
        FROM weight_records wr
        INNER JOIN animals a ON a.id = wr.animal_id
        WHERE a.animal_id = ?"
    );
    $total_weight_stmt->execute([$animal['animal_id']]);
    $total_weight = $total_weight_stmt->fetchColumn();
    $total_weight_pages = ceil($total_weight / $records_per_page);


    // Fetch vaccination records with pagination
    $vaccStmt = $pdo->prepare(
        "SELECT v.* 
        FROM vaccinations v
        INNER JOIN animals a ON a.id = v.animal_id
        WHERE a.animal_id = ?
        ORDER BY v.date DESC 
        LIMIT $vacc_start, $records_per_page"
    );
    $vaccStmt->execute([$animal['animal_id']]);
    $vaccinations = $vaccStmt->fetchAll(PDO::FETCH_ASSOC);


    // Get total vaccination records
    $total_vacc_stmt = $pdo->prepare(
        "SELECT COUNT(*) 
        FROM vaccinations v
        INNER JOIN animals a ON a.id = v.animal_id
        WHERE a.animal_id = ?"
    );
    $total_vacc_stmt->execute([$animal['animal_id']]);
    $total_vacc = $total_vacc_stmt->fetchColumn();
    $total_vacc_pages = ceil($total_vacc / $records_per_page);
    
} catch(PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Details - PureFarm</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
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

        
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .nav-tab {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            background: white;
            color: #666;
        }

        .nav-tab.active {
            background: #4CAF50;
            color: white;
        }

        .content-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .tab-container {
            margin-bottom: 20px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab {
            padding: 10px 20px;
            background: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: #666;
        }

        .tab.active {
            background: #4CAF50;
            color: white;
        }

        .tab-content {
            display: none;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .tab-content.active {
            display: block;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .detail-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .detail-card h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .data-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }

        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-add {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .health-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }

        .info-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .info-label {
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
        }
        .info-value {
            color: #333;
        }
        .health-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
            display: inline-block;
        }
        .health-badge.healthy { background-color: #d4edda; color: #155724; }
        .health-badge.injured { background-color: #fff3cd; color: #856404; }
        .health-badge.sick { background-color: #f8d7da; color: #721c24; }

        .header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 20px;
    }

    .page-link {
        padding: 5px 10px;
        border: 1px solid #4CAF50;
        border-radius: 3px;
        text-decoration: none;
        color: #4CAF50;
    }

    .page-link.active {
        background-color: #4CAF50;
        color: white;
    }

    .no-records {
        text-align: center;
        padding: 20px;
        color: #666;
    }

    .action-btn {
        padding: 4px 8px;
        border-radius: 3px;
        text-decoration: none;
        color: white;
        font-size: 12px;
        margin-right: 5px;
    }

    .edit-btn {
        background-color: #ffc107;
    }

    .delete-btn {
        background-color: #dc3545;
    }

    .due-date {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.9em;
    }

    .due-date.overdue {
        background-color: #ffebee;
        color: #c62828;
    }

    .due-date.due-today {
        background-color: #fff3e0;
        color: #ef6c00;
    }

    .due-date.upcoming {
        background-color: #e8f5e9;
        color: #2e7d32;
    }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-top: 20px;
    }

    .alert-warning {
        background-color: #fff3e0;
        border: 1px solid #ffe0b2;
        color: #ef6c00;
    }

    .alert h4 {
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .alert ul {
        margin: 0;
        padding-left: 20px;
    }

    .alert li {
        margin: 5px 0;
    }

    .mt-4 {
        margin-top: 1rem;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 20px;
        padding: 10px;
    }

    .page-link {
        padding: 8px 12px;
        border: 1px solid #4CAF50;
        border-radius: 4px;
        text-decoration: none;
        color: #4CAF50;
        background: white;
    }

    .page-link:hover {
        background: #4CAF50;
        color: white;
    }

    .page-link.active {
        background: #4CAF50;
        color: white;
        cursor: default;
    }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2>Animal Details - <?php echo htmlspecialchars($animal_id); ?></h2>
            <a href="animal_records.php" class="btn-add" style="background: #6c757d;">← Back to Records</a>
        </div>

        <!-- Basic Information Section -->
        <div class="info-card">
            <h3>Basic Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Species</div>
                    <div class="info-value"><?php echo htmlspecialchars($animal['species']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Breed</div>
                    <div class="info-value"><?php echo htmlspecialchars($animal['breed']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date of Birth</div>
                    <div class="info-value"><?php echo date('Y-m-d', strtotime($animal['date_of_birth'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Health Status</div>
                    <div class="info-value">
                        <span class="health-badge <?php echo strtolower($animal['health_status']); ?>">
                            <?php echo htmlspecialchars($animal['health_status']); ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Gender</div>
                    <div class="info-value"><?php echo htmlspecialchars($animal['gender']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Source</div>
                    <div class="info-value"><?php echo htmlspecialchars($animal['source']); ?></div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="nav-tabs">
            <button class="nav-tab active" onclick="openTab(event, 'health')">Health Records</button>
            <button class="nav-tab" onclick="openTab(event, 'weight')">Weight Tracking</button>
            <button class="nav-tab" onclick="openTab(event, 'vaccination')">Vaccination History</button>
        </div>

       <!-- Health Records Tab -->
        <div id="health" class="tab-content content-card">
            <div class="header-actions">
                <h3>Health Records</h3>
                <button class="btn-add" onclick="location.href='add_health_record.php?id=<?php echo htmlspecialchars($animal_id); ?>'">
                    <i class="fas fa-plus"></i> Add Health Record
                </button>
            </div>

            <?php if (!empty($healthRecords)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Condition</th>
                            <th>Treatment</th>
                            <th>Veterinarian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($healthRecords as $record): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($record['date'])); ?></td>
                                <td><?php echo htmlspecialchars($record['condition']); ?></td>
                                <td><?php echo htmlspecialchars($record['treatment']); ?></td>
                                <td><?php echo htmlspecialchars($record['vet_name']); ?></td>
                                
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Health Records Pagination -->
                <?php if ($total_health_pages > 1): ?>
                    <div class="pagination">
                        <?php if($health_page > 1): ?>
                            <a href='?id=<?php echo $animal_id; ?>&health_page=1' class="page-link">First</a>
                            <a href='?id=<?php echo $animal_id; ?>&health_page=<?php echo $health_page-1; ?>' class="page-link">Previous</a>
                        <?php endif; ?>

                        <?php 
                        for($i = max(1, $health_page-2); $i <= min($total_health_pages, $health_page+2); $i++): 
                            if($i == $health_page):
                        ?>
                            <span class="page-link active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href='?id=<?php echo $animal_id; ?>&health_page=<?php echo $i; ?>' class="page-link"><?php echo $i; ?></a>
                        <?php 
                            endif;
                        endfor; 
                        ?>

                        <?php if($health_page < $total_health_pages): ?>
                            <a href='?id=<?php echo $animal_id; ?>&health_page=<?php echo $health_page+1; ?>' class="page-link">Next</a>
                            <a href='?id=<?php echo $animal_id; ?>&health_page=<?php echo $total_health_pages; ?>' class="page-link">Last</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="no-records">No health records found for this animal.</p>
            <?php endif; ?>
        </div>


        <!-- Weight Records Tab -->
        <div id="weight" class="tab-content content-card" style="display: none;">
            <div class="header-actions">
                <h3>Weight Records</h3>
                <button class="btn-add" onclick="location.href='add_weight_record.php?id=<?php echo htmlspecialchars($animal_id); ?>'">
                    <i class="fas fa-plus"></i> Add Weight Record
                </button>
            </div>

            <?php if (!empty($weightRecords)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Weight (kg)</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($weightRecords as $record): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($record['date'])); ?></td>
                                <td><?php echo number_format($record['weight'], 2); ?></td>
                                <td><?php echo htmlspecialchars($record['notes']); ?></td>
                                
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Weight Records Pagination -->
                <?php if ($total_weight_pages > 1): ?>
                    <div class="pagination">
                        <?php if($weight_page > 1): ?>
                            <a href='?id=<?php echo $animal_id; ?>&weight_page=1' class="page-link">First</a>
                            <a href='?id=<?php echo $animal_id; ?>&weight_page=<?php echo $weight_page-1; ?>' class="page-link">Previous</a>
                        <?php endif; ?>

                        <?php 
                        for($i = max(1, $weight_page-2); $i <= min($total_weight_pages, $weight_page+2); $i++): 
                            if($i == $weight_page):
                        ?>
                            <span class="page-link active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href='?id=<?php echo $animal_id; ?>&weight_page=<?php echo $i; ?>' class="page-link"><?php echo $i; ?></a>
                        <?php 
                            endif;
                        endfor; 
                        ?>

                        <?php if($weight_page < $total_weight_pages): ?>
                            <a href='?id=<?php echo $animal_id; ?>&weight_page=<?php echo $weight_page+1; ?>' class="page-link">Next</a>
                            <a href='?id=<?php echo $animal_id; ?>&weight_page=<?php echo $total_weight_pages; ?>' class="page-link">Last</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="no-records">No weight records found for this animal.</p>
            <?php endif; ?>
        </div>

        <!-- Vaccination Records Tab -->
        <div id="vaccination" class="tab-content content-card" style="display: none;">
            <div class="header-actions">
                <h3>Vaccination Records</h3>
                <button class="btn-add" onclick="location.href='add_vaccination.php?id=<?php echo htmlspecialchars($animal_id); ?>'">
                    <i class="fas fa-plus"></i> Add Vaccination Record
                </button>
            </div>

            <?php if (!empty($vaccinations)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Vaccine Type</th>
                            <th>Next Due Date</th>
                            <th>Administered By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($vaccinations as $vacc): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($vacc['date'])); ?></td>
                                <td><?php echo htmlspecialchars($vacc['type']); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($vacc['next_due'])); ?></td>
                                <td><?php echo htmlspecialchars($vacc['administered_by']); ?></td>
                                
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Vaccination Records Pagination -->
                <?php if ($total_vacc_pages > 1): ?>
                    <div class="pagination">
                        <?php if($vacc_page > 1): ?>
                            <a href='?id=<?php echo $animal_id; ?>&vacc_page=1' class="page-link">First</a>
                            <a href='?id=<?php echo $animal_id; ?>&vacc_page=<?php echo $vacc_page-1; ?>' class="page-link">Previous</a>
                        <?php endif; ?>

                        <?php 
                        for($i = max(1, $vacc_page-2); $i <= min($total_vacc_pages, $vacc_page+2); $i++): 
                            if($i == $vacc_page):
                        ?>
                            <span class="page-link active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href='?id=<?php echo $animal_id; ?>&vacc_page=<?php echo $i; ?>' class="page-link"><?php echo $i; ?></a>
                        <?php 
                            endif;
                        endfor; 
                        ?>

                        <?php if($vacc_page < $total_vacc_pages): ?>
                            <a href='?id=<?php echo $animal_id; ?>&vacc_page=<?php echo $vacc_page+1; ?>' class="page-link">Next</a>
                            <a href='?id=<?php echo $animal_id; ?>&vacc_page=<?php echo $total_vacc_pages; ?>' class="page-link">Last</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="no-records">No vaccination records found for this animal.</p>
            <?php endif; ?>
        </div>

        <script>
        function openTab(evt, tabName) {
            var tabContents = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].style.display = "none";
            }

            var tabs = document.getElementsByClassName("nav-tab");
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].className = tabs[i].className.replace(" active", "");
            }

            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }

        // Maintain active tab on page reload
        document.addEventListener('DOMContentLoaded', function() {
            // Check for tab-specific URL parameters
            if (window.location.href.includes('health_page')) {
                openTab({ currentTarget: document.querySelector('[onclick*="health"]') }, 'health');
            }
            else if (window.location.href.includes('weight_page')) {
                openTab({ currentTarget: document.querySelector('[onclick*="weight"]') }, 'weight');
            }
            else if (window.location.href.includes('vacc_page')) {
                openTab({ currentTarget: document.querySelector('[onclick*="vaccination"]') }, 'vaccination');
            }
        });
        </script>
</body>
</html>