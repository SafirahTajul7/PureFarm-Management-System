<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';


if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No ID provided']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            a.animal_id as animal_code
        FROM vaccinations v
        JOIN animals a ON v.animal_id = a.id
        WHERE v.id = ?
    ");
    
    $stmt->execute([$_GET['id']]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        echo json_encode($record, JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['error' => 'Record not found']);
    }
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>