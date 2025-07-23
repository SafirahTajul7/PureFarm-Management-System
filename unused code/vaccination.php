<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'db.php';

try {
    // Fetch vaccination records with animal details
    $stmt = $pdo->query("SELECT v.*, a.species, a.breed 
                        FROM vaccinations v 
                        JOIN animals a ON v.animal_id = a.animal_id 
                        ORDER BY v.date DESC");
    $vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination Details - PureFarm</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Base Reset and Typography */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: Arial, sans-serif;
    }

    body {
        background-color: #f0f2f5;
        min-height: 100vh;
        line-height: 1.6;
    }

    /* Layout Container */
    .container {
        display: flex;
    }

    .main-content {
        flex: 1;
        margin-left: 280px;
        padding: 20px;
    }

    /* Page Header Styles */
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

    .page-header h2 {
        color: #333;
        font-size: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .page-header i {
        color: #4CAF50;
    }

    /* Form Container */
    .form-container {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 25px;
    }

    .form-container h3 {
        color: #333;
        font-size: 20px;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #4CAF50;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Form Elements */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
        font-size: 14px;
    }

    .form-group label.required:after {
        content: " *";
        color: #dc3545;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #4CAF50;
        box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
    }

    /* Buttons */
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .btn-primary {
        background: #4CAF50;
        color: white;
    }

    .btn-primary:hover {
        background: #45a049;
        transform: translateY(-1px);
    }

    /* Vaccination Table */
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
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #eee;
        font-size: 14px;
    }

    .animal-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #333;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
    }

    .animal-table tr:hover {
        background-color: #f5f5f5;
    }

    /* Vaccination Status Indicators */
    .vaccination-status {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
        text-align: center;
        min-width: 100px;
    }

    .status-upcoming {
        background-color: #ffc107;
        color: #000;
    }

    .status-overdue {
        background-color: #dc3545;
        color: white;
    }

    .status-completed {
        background-color: #28a745;
        color: white;
    }

    .status-scheduled {
        background-color: #17a2b8;
        color: white;
    }

    /* Due Date Highlights */
    .due-soon {
        color: #ffc107;
        font-weight: bold;
    }

    .overdue {
        color: #dc3545;
        font-weight: bold;
    }

    .future-date {
        color: #28a745;
    }

    /* Action Buttons */
    .action-btns {
        display: flex;
        gap: 8px;
    }

    .action-btn {
        padding: 6px 12px;
        border-radius: 4px;
        color: white;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .view-btn {
        background-color: #17a2b8;
    }

    .edit-btn {
        background-color: #ffc107;
        color: #000;
    }

    .delete-btn {
        background-color: #dc3545;
    }

    .action-btn:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }

    /* Vaccine Type Tags */
    .vaccine-type {
        background-color: #e9ecef;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        color: #495057;
        display: inline-block;
    }

    /* Schedule Reminder Box */
    .schedule-reminder {
        background-color: #fff3cd;
        border: 1px solid #ffeeba;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .schedule-reminder h4 {
        color: #856404;
        margin-bottom: 10px;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #666;
    }

    .empty-state i {
        font-size: 48px;
        color: #ddd;
        margin-bottom: 15px;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .main-content {
            margin-left: 250px;
        }
    }

    @media (max-width: 992px) {
        .main-content {
            margin-left: 200px;
        }
        
        .form-container {
            padding: 20px;
        }
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 15px;
        }
        
        .page-header {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
        
        .animal-table {
            display: block;
            overflow-x: auto;
        }
        
        .vaccination-status {
            min-width: auto;
        }
    }

    @media (max-width: 576px) {
        .btn {
            width: 100%;
            justify-content: center;
        }
        
        .action-btns {
            flex-direction: column;
        }
        
        .action-btn {
            text-align: center;
        }
    }

    /* Print Styles */
    @media print {
        .main-content {
            margin-left: 0;
        }
        
        .form-container,
        .btn,
        .action-btn {
            display: none;
        }
        
        .animal-table {
            box-shadow: none;
        }
    }

    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .form-container,
    .animal-table {
        animation: fadeIn 0.3s ease-in-out;
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    ::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    /* Utility Classes */
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .mb-20 { margin-bottom: 20px; }
    .mt-20 { margin-top: 20px; }
    .hidden { display: none; }

    /* Tooltip Styles */
    [data-tooltip] {
        position: relative;
        cursor: help;
    }

    [data-tooltip]:before {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        padding: 5px 10px;
        background: rgba(0,0,0,0.8);
        color: white;
        font-size: 12px;
        border-radius: 4px;
        white-space: nowrap;
        visibility: hidden;
        opacity: 0;
        transition: all 0.3s ease;
    }

    [data-tooltip]:hover:before {
        visibility: visible;
        opacity: 1;
    }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-syringe"></i> Vaccination Details</h2>
        </div>

        <!-- Add Vaccination Form -->
        <div class="form-container">
            <h3>Add Vaccination Record</h3>
            <form action="add_vaccination.php" method="POST">
                <div class="form-group">
                    <label for="animal_id">Animal ID</label>
                    <select name="animal_id" id="animal_id" required>
                        <option value="">Select Animal</option>
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT animal_id, species, breed FROM animals ORDER BY animal_id");
                            while ($row = $stmt->fetch()) {
                                echo "<option value='" . $row['animal_id'] . "'>" . 
                                     $row['animal_id'] . " - " . $row['species'] . " (" . $row['breed'] . ")</option>";
                            }
                        } catch(PDOException $e) {
                            echo "<option value=''>Error loading animals</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date">Vaccination Date</label>
                    <input type="date" name="date" id="date" required>
                </div>

                <div class="form-group">
                    <label for="type">Vaccine Type</label>
                    <input type="text" name="type" id="type" required>
                </div>

                <div class="form-group">
                    <label for="next_due">Next Due Date</label>
                    <input type="date" name="next_due" id="next_due" required>
                </div>

                <div class="form-group">
                    <label for="administered_by">Administered By</label>
                    <input type="text" name="administered_by" id="administered_by" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Vaccination
                </button>
            </form>
        </div>

        <!-- Vaccinations Table -->
        <table class="animal-table">
            <thead>
                <tr>
                    <th>Animal ID</th>
                    <th>Species</th>
                    <th>Breed</th>
                    <th>Vaccination Date</th>
                    <th>Vaccine Type</th>
                    <th>Next Due Date</th>
                    <th>Status</th>
                    <th>Administered By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(isset($vaccinations) && count($vaccinations) > 0): ?>
                    <?php foreach($vaccinations as $vaccination): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vaccination['animal_id']); ?></td>
                            <td><?php echo htmlspecialchars($vaccination['species']); ?></td>
                            <td><?php echo htmlspecialchars($vaccination['breed']); ?></td>
                            <td><?php echo date('d M Y', strtotime($vaccination['date'])); ?></td>
                            <td><?php echo htmlspecialchars($vaccination['type']); ?></td>
                            <td><?php echo date('d M Y', strtotime($vaccination['next_due'])); ?></td>
                            <td>
                                <?php
                                $today = new DateTime();
                                $next_due = new DateTime($vaccination['next_due']);
                                $diff = $today->diff($next_due);
                                
                                if ($next_due < $today) {
                                    echo '<span class="overdue">Overdue</span>';
                                } elseif ($diff->days <= 30) {
                                    echo '<span class="upcoming">Due Soon</span>';
                                } else {
                                    echo '<span class="completed">Completed</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($vaccination['administered_by']); ?></td>
                            <td>
                                <a href="edit_vaccination.php?id=<?php echo $vaccination['id']; ?>" class="action-btn edit-btn">Edit</a>
                                <a href="delete_vaccination.php?id=<?php echo $vaccination['id']; ?>" 
                                   class="action-btn delete-btn" 
                                   onclick="return confirm('Are you sure you want to delete this record?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="empty-state">
                            No vaccination records found. Add your first vaccination record using the form above.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>