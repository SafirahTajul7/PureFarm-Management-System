<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

$success_message = '';
$error_message = '';

// Get animal ID from URL parameter
if (!isset($_GET['id'])) {
    header("Location: animal_records.php");
    exit();
}

// At the beginning of the file, after getting the ID
$id = $_GET['id'];

// First, verify we got the correct animal
try {
    $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ? OR animal_id = ?");
    $stmt->execute([$id, $id]);
    $animal = $stmt->fetch();

    if (!$animal) {
        header("Location: animal_records.php?error=Animal not found");
        exit();
    }

    // Store the actual database ID
    $actualId = $animal['id'];

    // Fetch latest records...
    $healthStmt = $pdo->prepare("SELECT * FROM health_records WHERE animal_id = ? ORDER BY date DESC LIMIT 1");
    $healthStmt->execute([$actualId]);
    $healthRecord = $healthStmt->fetch();

    // Fetch latest vaccination
    $vaccStmt = $pdo->prepare("SELECT * FROM vaccinations WHERE animal_id = ? ORDER BY date DESC LIMIT 1");
    $vaccStmt->execute([$id]);
    $vaccination = $vaccStmt->fetch();

    // Fetch latest weight record
    $weightStmt = $pdo->prepare("SELECT * FROM weight_records WHERE animal_id = ? ORDER BY date DESC LIMIT 1");
    $weightStmt->execute([$id]);
    $weightRecord = $weightStmt->fetch();

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Update animals table
        $updateAnimal = $pdo->prepare("
            UPDATE animals 
            SET species = :species,
                breed = :breed,
                date_of_birth = :date_of_birth,
                health_status = :health_status,
                gender = :gender,
                source = :source
            WHERE id = :id
        ");


        $updateResult = $updateAnimal->execute([
            ':species' => $_POST['species'],
            ':breed' => $_POST['breed'],
            ':date_of_birth' => $_POST['date_of_birth'],
            ':health_status' => $_POST['health_status'],
            ':gender' => $_POST['gender'],
            ':source' => $_POST['source'],
            ':id' => $actualId
        ]);

        if (!$updateResult) {
            throw new PDOException("Failed to update animal details");
        }

        // Insert new health record if provided
        if (!empty($_POST['condition']) || !empty($_POST['treatment']) || !empty($_POST['vet_name'])) {
            $healthSql = "INSERT INTO health_records (animal_id, date, `condition`, treatment, vet_name) 
                         VALUES (:animal_id, :date, :condition, :treatment, :vet_name)";
            $healthStmt = $pdo->prepare($healthSql);
            $healthResult = $healthStmt->execute([
                ':animal_id' => $actualId,
                ':date' => date('Y-m-d'),
                ':condition' => $_POST['condition'] ?? '',
                ':treatment' => $_POST['treatment'] ?? '',
                ':vet_name' => $_POST['vet_name'] ?? ''
            ]);

            if (!$healthResult) {
                throw new PDOException("Failed to insert health record");
            }
        }

        // Insert new vaccination if provided
        if (!empty($_POST['vaccination_type']) || !empty($_POST['next_vaccination_date']) || !empty($_POST['administered_by'])) {
            $vaccSql = "INSERT INTO vaccinations (animal_id, date, type, next_due, administered_by) 
                       VALUES (:animal_id, :date, :type, :next_due, :administered_by)";
            $vaccStmt = $pdo->prepare($vaccSql);
            $vaccResult = $vaccStmt->execute([
                ':animal_id' => $actualId,
                ':date' => date('Y-m-d'),
                ':type' => $_POST['vaccination_type'] ?? '',
                ':next_due' => $_POST['next_vaccination_date'] ?? null,
                ':administered_by' => $_POST['administered_by'] ?? ''
            ]);

            if (!$vaccResult) {
                throw new PDOException("Failed to insert vaccination record");
            }
        }


         // Insert new weight record if provided
        if (!empty($_POST['weight'])) {
            $weightSql = "INSERT INTO weight_records (animal_id, date, weight, notes) 
                         VALUES (:animal_id, :date, :weight, :notes)";
            $weightStmt = $pdo->prepare($weightSql);
            $weightResult = $weightStmt->execute([
                ':animal_id' => $actualId,
                ':date' => date('Y-m-d'),
                ':weight' => $_POST['weight'],
                ':notes' => $_POST['weight_notes'] ?? ''
            ]);

            if (!$weightResult) {
                throw new PDOException("Failed to insert weight record");
            }
        }

        $pdo->commit();
        $success_message = "Animal details updated successfully!";
        
        // Refresh the animal data
        $stmt = $pdo->prepare("SELECT * FROM animals WHERE id = ?");
        $stmt->execute([$actualId]);
        $animal = $stmt->fetch();

        // Redirect to avoid form resubmission
        header("Location: edit_animal.php?id=" . $actualId . "&success=1");
        exit();

    } catch(PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error updating animal: " . $e->getMessage();
    }
}

// Add this at the top of the file to display success message after redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Animal details updated successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Animal - PureFarm</title>
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
            margin-bottom: 25px;
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
            font-weight: bold;
        }

        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
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

        .historical-data {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
            font-style: italic;
        }

        input::placeholder, textarea::placeholder {
            color: #999;
            font-style: italic;
        }

    
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="form-container">
            <div class="page-header">
                <h2><i class="fas fa-edit"></i> Edit Animal</h2>
                <button class="btn btn-secondary" onclick="location.href='animal_records.php'">
                    <i class="fas fa-arrow-left"></i> Back to Records
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
                                <label>Animal ID</label>
                                <input type="text" value="<?php echo htmlspecialchars($animal['animal_id']); ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label for="species">Species *</label>
                                <select id="species" name="species" required>
                                    <option value="">Select Species</option>
                                    <?php
                                    $species_options = ['cattle', 'goat', 'buffalo', 'chicken', 'duck', 'rabbit'];
                                    foreach ($species_options as $option) {
                                        $selected = ($animal['species'] === $option) ? 'selected' : '';
                                        echo "<option value=\"$option\" $selected>" . ucfirst($option) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="breed">Breed *</label>
                                <input type="text" id="breed" name="breed" value="<?php echo htmlspecialchars($animal['breed']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth *</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo $animal['date_of_birth']; ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="gender">Gender *</label>
                                <select id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo $animal['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $animal['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="source">Source *</label>
                                <input type="text" id="source" name="source" value="<?php echo htmlspecialchars($animal['source']); ?>" required>
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
                                    <?php
                                    $status_options = ['healthy', 'sick', 'injured', 'quarantine'];
                                    foreach ($status_options as $option) {
                                        $selected = ($animal['health_status'] === $option) ? 'selected' : '';
                                        echo "<option value=\"$option\" $selected>" . ucfirst($option) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="condition">New Condition</label>
                                <input type="text" id="condition" name="condition" 
                                    placeholder="<?php echo $healthRecord ? htmlspecialchars($healthRecord['condition']) : ''; ?>">
                                <?php if ($healthRecord): ?>
                                    <div class="historical-data">
                                        Last recorded: <?php echo htmlspecialchars($healthRecord['condition']); ?> 
                                        (<?php echo date('Y-m-d', strtotime($healthRecord['date'])); ?>)
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="treatment">New Treatment</label>
                                <input type="text" id="treatment" name="treatment" 
                                    placeholder="<?php echo $healthRecord ? htmlspecialchars($healthRecord['treatment']) : ''; ?>">
                                <?php if ($healthRecord): ?>
                                    <div class="historical-data">
                                        Last recorded: <?php echo htmlspecialchars($healthRecord['treatment']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="vet_name">Veterinarian Name</label>
                                <input type="text" id="vet_name" name="vet_name" 
                                    placeholder="<?php echo $healthRecord ? htmlspecialchars($healthRecord['vet_name']) : ''; ?>">
                                <?php if ($healthRecord): ?>
                                    <div class="historical-data">
                                        Last recorded: <?php echo htmlspecialchars($healthRecord['vet_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Vaccination Tab -->
                    <div class="tab-content" id="vaccination">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="vaccination_type">New Vaccination Type</label>
                                <input type="text" id="vaccination_type" name="vaccination_type" 
                                    placeholder="<?php echo $vaccination ? htmlspecialchars($vaccination['type']) : ''; ?>">
                                <?php if ($vaccination): ?>
                                    <div class="historical-data">
                                        Last recorded: <?php echo htmlspecialchars($vaccination['type']); ?> 
                                        (<?php echo date('Y-m-d', strtotime($vaccination['date'])); ?>)
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="next_vaccination_date">Next Due Date</label>
                                <input type="date" id="next_vaccination_date" name="next_vaccination_date" 
                                    placeholder="<?php echo $vaccination ? $vaccination['next_due'] : ''; ?>">
                                <?php if ($vaccination): ?>
                                    <div class="historical-data">
                                        Last due date: <?php echo date('Y-m-d', strtotime($vaccination['next_due'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="administered_by">Administered By</label>
                                <input type="text" id="administered_by" name="administered_by" 
                                    placeholder="<?php echo $vaccination ? htmlspecialchars($vaccination['administered_by']) : ''; ?>">
                                <?php if ($vaccination): ?>
                                    <div class="historical-data">
                                        Last recorded: <?php echo htmlspecialchars($vaccination['administered_by']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Weight Tab -->
                    <div class="tab-content" id="weight">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="weight">New Weight (kg)</label>
                                <input type="number" step="0.1" id="weight" name="weight" 
                                    placeholder="<?php echo $weightRecord ? $weightRecord['weight'] : ''; ?>">
                                <?php if ($weightRecord): ?>
                                    <div class="historical-data">
                                        Last recorded: <?php echo $weightRecord['weight']; ?> kg 
                                        (<?php echo date('Y-m-d', strtotime($weightRecord['date'])); ?>)
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="weight_notes">Weight Notes</label>
                                <textarea id="weight_notes" name="weight_notes" rows="3" 
                                        placeholder="<?php echo $weightRecord ? htmlspecialchars($weightRecord['notes']) : ''; ?>"></textarea>
                                <?php if ($weightRecord): ?>
                                    <div class="historical-data">
                                        Last notes: <?php echo htmlspecialchars($weightRecord['notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                
                <!-- Submit Button -->
                <div class="form-group" style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript for tab switching -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    // Add active class to clicked button and corresponding content
                    button.classList.add('active');
                    const tabId = button.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });

            // Show success message for 3 seconds only
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.display = 'none';
                }, 3000);
            }
        });
    </script>
</body>
</html>
