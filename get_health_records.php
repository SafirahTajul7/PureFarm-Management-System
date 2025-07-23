<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

auth()->checkAdmin();

try {
    $stmt = $pdo->query("SELECT h.*, a.animal_id as animal_code 
                         FROM health_records h 
                         JOIN animals a ON h.animal_id = a.id 
                         ORDER BY CAST(SUBSTRING(a.animal_id, 2) AS UNSIGNED), h.date DESC");
    $health_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($health_records) > 0) {
        foreach($health_records as $record) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($record['id']) . "</td>";
            echo "<td>" . htmlspecialchars($record['animal_code']) . "</td>";
            echo "<td>" . date('d M Y', strtotime($record['date'])) . "</td>";
            echo "<td><span class='health-status " . strtolower($record['condition']) . "'>" . 
                 htmlspecialchars($record['condition']) . "</span></td>";
            echo "<td>" . htmlspecialchars($record['treatment']) . "</td>";
            echo "<td>" . htmlspecialchars($record['vet_name']) . "</td>";
            echo "<td>";
            echo "<button class='action-btn edit-btn' onclick='editRecord(" . $record['id'] . ")'>";
            echo "<i class='fas fa-edit'></i></button>";
            echo "<button class='action-btn delete-btn' onclick='deleteRecord(" . $record['id'] . ")'>";
            echo "<i class='fas fa-trash'></i></button>";
            echo "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='7' style='text-align: center; padding: 20px;'>";
        echo "No health records found. ";
        echo "<button onclick='openAddModal()' style='color: #4CAF50; border: none; background: none; cursor: pointer;'>";
        echo "Add your first record</button></td></tr>";
    }
} catch(PDOException $e) {
    echo "<tr><td colspan='7' style='text-align: center; color: red;'>Error loading records</td></tr>";
}