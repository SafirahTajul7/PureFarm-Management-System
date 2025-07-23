<?php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

$messages = [];

try {
    // Step 1: Check if staff_tasks table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'staff_tasks'")->fetchAll();
    if (count($tables) === 0) {
        $messages[] = "Error: staff_tasks table does not exist!";
    } else {
        $messages[] = "✓ staff_tasks table exists.";
        
        // Step 2: Check table structure
        $columns = $pdo->query("SHOW COLUMNS FROM staff_tasks")->fetchAll(PDO::FETCH_COLUMN);
        
        // Step 3: Check for missing columns and add them
        $columnsToAdd = [];
        
        // Check for task_name vs task_title
        if (in_array('task_title', $columns) && !in_array('task_name', $columns)) {
            $columnsToAdd[] = "ALTER TABLE staff_tasks ADD COLUMN task_name VARCHAR(255)";
            $messages[] = "Adding task_name column...";
        }
        
        // Check for start_date
        if (!in_array('start_date', $columns)) {
            $columnsToAdd[] = "ALTER TABLE staff_tasks ADD COLUMN start_date DATE DEFAULT CURRENT_DATE";
            $messages[] = "Adding start_date column...";
        }
        
        // Check for completion_percentage
        if (!in_array('completion_percentage', $columns)) {
            $columnsToAdd[] = "ALTER TABLE staff_tasks ADD COLUMN completion_percentage INT DEFAULT 0";
            $messages[] = "Adding completion_percentage column...";
        }
        
        // Check for last_updated
        if (!in_array('last_updated', $columns)) {
            $columnsToAdd[] = "ALTER TABLE staff_tasks ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
            $messages[] = "Adding last_updated column...";
        }
        
        // Step 4: Add the columns if needed
        foreach ($columnsToAdd as $sql) {
            $pdo->exec($sql);
        }
        
        // Step 5: Update task_name from task_title if needed
        if (in_array('task_title', $columns) && in_array('task_name', $columns)) {
            $pdo->exec("UPDATE staff_tasks SET task_name = task_title WHERE task_name IS NULL");
            $messages[] = "Updated task_name values from task_title.";
        }
        
        // Step 6: Update completion percentage based on status
        if (in_array('completion_percentage', $columns) && in_array('status', $columns)) {
            $pdo->exec("UPDATE staff_tasks SET completion_percentage = 100 WHERE status = 'completed' AND completion_percentage = 0");
            $pdo->exec("UPDATE staff_tasks SET completion_percentage = 50 WHERE status = 'in_progress' AND completion_percentage = 0");
            $messages[] = "Updated completion percentages based on status.";
        }
        
        // Step 7: Add test data if no tasks exist
        $taskCount = $pdo->query("SELECT COUNT(*) FROM staff_tasks")->fetchColumn();
        if ($taskCount == 0) {
            // Get first staff ID
            $staffId = $pdo->query("SELECT id FROM staff LIMIT 1")->fetchColumn();
            
            if ($staffId) {
                // Add some test tasks
                $testTasks = [
                    [
                        'title' => 'Inspect Farm Equipment',
                        'status' => 'completed',
                        'priority' => 'high',
                        'completion' => 100
                    ],
                    [
                        'title' => 'Check Livestock Feed Inventory',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'completion' => 65
                    ],
                    [
                        'title' => 'Prepare Fields for Planting',
                        'status' => 'pending',
                        'priority' => 'high',
                        'completion' => 0
                    ]
                ];
                
                foreach ($testTasks as $task) {
                    $stmt = $pdo->prepare("
                        INSERT INTO staff_tasks 
                        (staff_id, task_title, task_name, status, priority, completion_percentage, due_date, start_date) 
                        VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY), CURRENT_DATE)
                    ");
                    $stmt->execute([
                        $staffId,
                        $task['title'],
                        $task['title'],
                        $task['status'],
                        $task['priority'],
                        $task['completion']
                    ]);
                }
                $messages[] = "Added 3 test tasks for demonstration.";
            }
        }
    }
} catch(PDOException $e) {
    $messages[] = "Error: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureFarm Task System Fix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h2>PureFarm Task Progress System Fix</h2>
            </div>
            <div class="card-body">
                <h4>Database Update Results:</h4>
                <ul class="list-group">
                    <?php foreach ($messages as $message): ?>
                        <li class="list-group-item"><?php echo $message; ?></li>
                    <?php endforeach; ?>
                </ul>
                
                <div class="mt-4">
                    <p>All necessary changes have been applied. You can now use the Task Progress system:</p>
                    <div class="d-flex gap-3">
                        <a href="task_progress.php" class="btn btn-success">Go to Task Progress</a>
                        <a href="task_assignment.php" class="btn btn-secondary">Go to Task Assignment</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>