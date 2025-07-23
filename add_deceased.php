<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("INSERT INTO deceased_animals (animal_id, date_of_death, cause, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['animal_id'],
            $_POST['date_of_death'],
            $_POST['cause'],
            $_POST['notes']
        ]);

        header("Location: animals_lifecycle.php?success=1");
        exit();
    } catch(PDOException $e) {
        header("Location: deceased_records.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}