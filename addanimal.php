<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'includes/db.php';

$success_message = '';
$error_message = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animal_id = $_POST['animal_id'];
    $species = $_POST['species'];
    $breed = $_POST['breed'];
    $date_of_birth = $_POST['date_of_birth'];
    $health_status = $_POST['health_status'];
    $gender = $_POST['gender'];
    $source = $_POST['source'];

    try {
        $check_sql = "SELECT animal_id FROM animals WHERE animal_id = :animal_id";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([':animal_id' => $animal_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $error_message = "Error: Animal ID '$animal_id' already exists.";
        } else {
            $pdo->beginTransaction();

            // Insert into animals table
            $animals_sql = "INSERT INTO animals (animal_id, species, breed, date_of_birth, health_status, gender, source) 
                          VALUES (:animal_id, :species, :breed, :date_of_birth, :health_status, :gender, :source)";
            
            $stmt = $pdo->prepare($animals_sql);
            $stmt->execute([
                ':animal_id' => $animal_id,
                ':species' => $species,
                ':breed' => $breed,
                ':date_of_birth' => $date_of_birth,
                ':health_status' => $health_status,
                ':gender' => $gender,
                ':source' => $source
            ]);

            // Get the auto-incremented ID
            $db_animal_id = $pdo->lastInsertId();

            // Insert health record if condition exists
            if (!empty($_POST['initial_condition'])) {
                $health_sql = "INSERT INTO health_records (animal_id, date, `condition`, treatment, vet_name) 
                              VALUES (:animal_id, :date, :condition, :treatment, :vet_name)";
                $stmt = $pdo->prepare($health_sql);
                $stmt->execute([
                    ':animal_id' => $db_animal_id,
                    ':date' => date('Y-m-d'),
                    ':condition' => $_POST['initial_condition'],
                    ':treatment' => $_POST['initial_treatment'],
                    ':vet_name' => $_POST['vet_name']
                ]);
            }

            // Insert vaccination if type exists
            if (!empty($_POST['vaccination_type'])) {
                $vaccination_sql = "INSERT INTO vaccinations (animal_id, date, type, next_due, administered_by) 
                                  VALUES (:animal_id, :date, :type, :next_due, :administered_by)";
                
                $stmt = $pdo->prepare($vaccination_sql);
                $stmt->execute([
                    ':animal_id' => $db_animal_id,
                    ':date' => date('Y-m-d'),
                    ':type' => $_POST['vaccination_type'],
                    ':next_due' => $_POST['next_vaccination_date'],
                    ':administered_by' => $_POST['administered_by']
                ]);
            }

            // Insert weight record if weight exists
            if (!empty($_POST['initial_weight'])) {
                $weight_sql = "INSERT INTO weight_records (animal_id, date, weight, notes) 
                              VALUES (:animal_id, :date, :weight, :notes)";
                
                $stmt = $pdo->prepare($weight_sql);
                $stmt->execute([
                    ':animal_id' => $db_animal_id,
                    ':date' => date('Y-m-d'),
                    ':weight' => $_POST['initial_weight'],
                    ':notes' => $_POST['weight_notes']
                ]);
            }

            $pdo->commit();
            $success_message = "Animal successfully added with all related records!";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Rest of your HTML and form code remains the same...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Animal - PureFarm</title>
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

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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

        .form-group input, .form-group select, .form-group textarea {
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

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .tab-container {
            margin-bottom: 20px;
        }
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab-button {
            padding: 10px 20px;
            border: none;
            background: #f0f0f0;
            cursor: pointer;
            border-radius: 5px;
        }
        .tab-button.active {
            background: #4CAF50;
            color: white;
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
        <div class="form-container">
            <div class="page-header">
                <h2><i class="fas fa-plus"></i> Add New Animal</h2>
                <button class="btn btn-secondary" onclick="location.href='animal_management.php'">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </button>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="tab-container">
                    <div class="tab-buttons">
                        <button type="button" class="tab-button active" data-tab="basic">Basic Info</button>
                        <button type="button" class="tab-button" data-tab="health">Health Details</button>
                        <button type="button" class="tab-button" data-tab="vaccination">Vaccination</button>
                        <button type="button" class="tab-button" data-tab="weight">Weight</button>
                    </div>

                    <!-- Basic Info Tab -->
                    <div class="tab-content active" id="basic">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="animal_id">Animal ID *</label>
                                <input type="text" id="animal_id" name="animal_id" required>
                            </div>

                            <div class="form-group">
                                <label for="species">Species *</label>
                                <select id="species" name="species" required>
                                    <option value="">Select Species</option>
                                    <option value="cattle">Cattle</option>
                                    <option value="goat">Goat</option>
                                    <option value="buffalo">Buffalo</option>
                                    <option value="chicken">Chicken</option>
                                    <option value="duck">Duck</option>
                                    <option value="rabbit">Rabbit</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="breed">Breed *</label>
                                <input type="text" id="breed" name="breed" required>
                            </div>

                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth *</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" required>
                            </div>

                            <div class="form-group">
                                <label for="gender">Gender *</label>
                                <select id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="source">Source *</label>
                                <input type="text" id="source" name="source" required placeholder="Purchase/Birth/Donation">
                            </div>
                        </div>
                    </div>

                    <!-- Health Details Tab -->
                    <div class="tab-content" id="health">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="health_status">Health Status *</label>
                                <select id="health_status" name="health_status" required>
                                    <option value="">Select Health Status</option>
                                    <option value="healthy">Healthy</option>
                                    <option value="sick">Sick</option>
                                    <option value="injured">Injured</option>
                                    <option value="quarantine">Under Quarantine</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="initial_condition">Initial Condition</label>
                                <input type="text" id="initial_condition" name="initial_condition">
                            </div>

                            <div class="form-group">
                                <label for="initial_treatment">Initial Treatment</label>
                                <input type="text" id="initial_treatment" name="initial_treatment">
                            </div>

                            <div class="form-group">
                                <label for="vet_name">Veterinarian Name</label>
                                <input type="text" id="vet_name" name="vet_name">
                            </div>
                        </div>
                    </div>

                    <!-- Vaccination Tab -->
                    <div class="tab-content" id="vaccination">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="vaccination_type">Vaccination Type</label>
                                <input type="text" id="vaccination_type" name="vaccination_type">
                            </div>

                            <div class="form-group">
                                <label for="next_vaccination_date">Next Due Date</label>
                                <input type="date" id="next_vaccination_date" name="next_vaccination_date">
                            </div>

                            <div class="form-group">
                                <label for="administered_by">Administered By</label>
                                <input type="text" id="administered_by" name="administered_by">
                            </div>
                        </div>
                    </div>

                    <!-- Weight Tab -->
                    <div class="tab-content" id="weight">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="initial_weight">Initial Weight (kg)</label>
                                <input type="number" step="0.1" id="initial_weight" name="initial_weight">
                            </div>

                            <div class="form-group">
                                <label for="weight_notes">Weight Notes</label>
                                <textarea id="weight_notes" name="weight_notes"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" id="saveButton">
                        <i class="fas fa-save"></i> Save Animal
                    </button>
                    <p id="validationMessage" style="color: red; margin-top: 10px; display: none;"></p>
                </div>
            </form>
        </div>
    </div>

    <script>
         // Tab switching functionality
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons and contents
                document.querySelectorAll('.tab-button').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                
                // Add active class to clicked button and corresponding content
                button.classList.add('active');
                document.getElementById(button.dataset.tab).classList.add('active');
            });
        });


     // Validation function
     function validateForm() {
            const fields = {
                // Basic Info
                'animal_id': 'Animal ID',
                'species': 'Species',
                'breed': 'Breed',
                'date_of_birth': 'Date of Birth',
                'gender': 'Gender',
                'source': 'Source',
                
                // Health Details
                'health_status': 'Health Status',
                'initial_condition': 'Initial Condition',
                'initial_treatment': 'Initial Treatment',
                'vet_name': 'Veterinarian Name',
                
                // Vaccination
                'vaccination_type': 'Vaccination Type',
                'next_vaccination_date': 'Next Vaccination Date',
                'administered_by': 'Administered By',
                
                // Weight
                'initial_weight': 'Initial Weight',
                'weight_notes': 'Weight Notes'
            };

            let missingFields = [];
            let isValid = true;

            // Reset all field styles
            document.querySelectorAll('input, select, textarea').forEach(field => {
                field.style.borderColor = '#ddd';
            });
            
            // Check each field
            for (let fieldId in fields) {
                const field = document.getElementById(fieldId);
                const value = field.value.trim();
                
                if (!value) {
                    isValid = false;
                    field.style.borderColor = 'red';
                    missingFields.push(fields[fieldId]);
                }
            }

            const validationMessage = document.getElementById('validationMessage');
            
            if (!isValid) {
                validationMessage.style.display = 'block';
                validationMessage.textContent = 'Please fill in all required fields: ' + 
                    missingFields.join(', ');

                // Find first empty field and switch to its tab
                const emptyField = document.querySelector('input[style*="red"], select[style*="red"], textarea[style*="red"]');
                if (emptyField) {
                    const tabContent = emptyField.closest('.tab-content');
                    if (tabContent) {
                        const tabId = tabContent.id;
                        document.querySelector(`[data-tab="${tabId}"]`).click();
                        emptyField.focus();
                    }
                }
            } else {
                validationMessage.style.display = 'none';
            }

            return isValid;
        }

        // Form submission handling
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>