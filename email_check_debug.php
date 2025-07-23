<?php
// This is a debugging script to check email availability functionality

require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

echo "<h1>Email Check Debugging</h1>";

// Check if staff table exists
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'staff'")->rowCount() > 0;
    echo "<p>Staff table exists: " . ($tableExists ? "Yes" : "No") . "</p>";
    
    if ($tableExists) {
        // Check table structure
        $columns = $pdo->query("DESCRIBE staff")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Staff table columns:</p><ul>";
        foreach ($columns as $column) {
            echo "<li>$column</li>";
        }
        echo "</ul>";
        
        // Check if there are any records
        $count = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
        echo "<p>Number of staff records: $count</p>";
        
        // Test email check query
        $testEmail = "test@example.com";
        
        echo "<h2>Testing email check query</h2>";
        echo "<p>Testing with email: $testEmail</p>";
        
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE email = :email");
            $stmt->execute([':email' => $testEmail]);
            $emailExists = $stmt->fetchColumn() > 0;
            
            echo "<p>Query executed successfully.</p>";
            echo "<p>Email exists: " . ($emailExists ? "Yes" : "No") . "</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red'>Error in email check query: " . $e->getMessage() . "</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: red'>Database error: " . $e->getMessage() . "</p>";
}