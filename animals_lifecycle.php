<?php
session_start();

// Debug session variables
echo "<!-- Session Variables: " . print_r($_SESSION, true) . " -->";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

try {
    // Fetch breeding records
    $breedingStmt = $pdo->query("
        SELECT bh.*, 
               a1.species as animal_species,
               a2.species as partner_species
        FROM breeding_history bh
        LEFT JOIN animals a1 ON bh.animal_id = a1.animal_id
        LEFT JOIN animals a2 ON bh.partner_id = a2.animal_id
        ORDER BY bh.date DESC
    ");
    $breeding_records = $breedingStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch deceased records with animal information
    $deceasedStmt = $pdo->query("
        SELECT d.*, a.species, a.breed 
        FROM deceased_animals d
        LEFT JOIN animals a ON d.animal_id = a.animal_id
        ORDER BY d.date_of_death DESC
    ");
    $deceased_animals = $deceasedStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lifecycle Records - PureFarm</title>
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            background: white;
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }

        .tab.active {
            border-bottom: 2px solid #4CAF50;
            color: #4CAF50;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-circle-notch"></i> Animal Lifecycle Records</h2>
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="location.href='animal_management.php'">
                    <i class="fas fa-arrow-left"></i> Back to Animal Management
                </button>
            </div>
        </div>

        <div class="tabs">
            <div class="tab active" data-tab="breeding">Breeding Records</div>
            <div class="tab" data-tab="deceased">Deceased Records</div>
        </div>

        <!-- Breeding Records Section -->
        <div id="breeding-content" class="tab-content active">
            <!-- Form section -->
            <div class="form-container">
                <h3>Add Breeding Record</h3>
                
                <form action="add_breeding.php" method="POST" onsubmit="return validateBreedingForm()">
                    <!-- Add this to debug the form submission -->
                    <input type="hidden" name="debug" value="1">
                    
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
                                    echo "<option value='" . htmlspecialchars($row['animal_id']) . "'>" . 
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
                                    echo "<option value='" . htmlspecialchars($row['animal_id']) . "'>" . 
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
                        <input type="date" name="date" id="date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="outcome">Outcome</label>
                        <select name="outcome" id="outcome" required>
                            <option value="pending">Pending</option>
                            <option value="successful">Successful</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea name="notes" id="notes"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Record
                    </button>
                </form>
            </div>

            <!-- Table section -->
            <table class="animal-table">
                <thead>
                    <tr>
                        <th>Female ID</th>
                        <th>Species</th>
                        <th>Male ID</th>
                        <th>Species</th>
                        <th>Breeding Date</th>
                        <th>Outcome</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($breeding_records)): ?>
                        <?php foreach($breeding_records as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['animal_id']); ?></td>
                                <td><?php echo htmlspecialchars($record['animal_species']); ?></td>
                                <td><?php echo htmlspecialchars($record['partner_id']); ?></td>
                                <td><?php echo htmlspecialchars($record['partner_species']); ?></td>
                                <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                                <td><?php echo htmlspecialchars($record['outcome']); ?></td>
                                <td><?php echo htmlspecialchars($record['notes']); ?></td>
                                <td>
                                    <a href="edit_breeding.php?id=<?php echo $record['id']; ?>" class="action-btn edit-btn">Edit</a>
                                    <a href="delete_breeding.php?id=<?php echo $record['id']; ?>" class="action-btn delete-btn" 
                                    onclick="return confirm('Are you sure?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-state">No breeding records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Deceased Records Section -->
        <div id="deceased-content" class="tab-content">
            <!-- Add Deceased Record Form -->
            <div class="form-container">
                <h3>Record Deceased Animal</h3>
                <form action="add_deceased.php" method="POST">
                    <div class="form-group">
                        <label for="deceased_animal_id">Animal ID</label>
                        <select name="animal_id" id="deceased_animal_id" required>
                            <option value="">Select Animal</option>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT animal_id, species, breed FROM animals WHERE animal_id NOT IN (SELECT animal_id FROM deceased_animals)");
                                while ($row = $stmt->fetch()) {
                                    echo "<option value='" . htmlspecialchars($row['animal_id']) . "'>" . 
                                        htmlspecialchars($row['animal_id'] . " - " . $row['species'] . " (" . $row['breed'] . ")") . "</option>";
                                }
                            } catch(PDOException $e) {
                                echo "<option value=''>Error loading animals: " . htmlspecialchars($e->getMessage()) . "</option>";
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
                        <label for="deceased_notes">Additional Notes</label>
                        <textarea name="notes" id="deceased_notes"></textarea>
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
                    <?php if(!empty($deceased_animals)): ?>
                        <?php foreach($deceased_animals as $animal): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($animal['animal_id']); ?></td>
                                <td><?php echo htmlspecialchars($animal['species'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($animal['breed'] ?? 'N/A'); ?></td>
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
                            <td colspan="7" class="empty-state">No deceased animal records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Add event listeners to tabs
        document.addEventListener('DOMContentLoaded', function() {
            // Get all tab elements
            const tabs = document.querySelectorAll('.tab');
            
            // Add click event listener to each tab
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and content
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Show corresponding content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId + '-content').classList.add('active');
                });
            });
        });

        // Form validation function
        function validateBreedingForm() {
            console.log("Validating breeding form");
            
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
            
            // Log form data for debugging
            console.log({
                femaleAnimal,
                maleAnimal,
                breedingDate,
                outcome,
                notes: document.getElementById('notes').value
            });
            
            return true;
        }
    </script>
</body>
</html>