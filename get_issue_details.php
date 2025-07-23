<?php
// Save this as get_issue_details.php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $issueId = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT ci.*, c.crop_name, f.field_name
            FROM crop_issues ci
            JOIN crops c ON ci.crop_id = c.id
            JOIN fields f ON c.field_id = f.id
            WHERE ci.id = ?
        ");
        $stmt->execute([$issueId]);
        $issue = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($issue) {
            // Return issue details as JSON
            header('Content-Type: application/json');
            echo json_encode($issue);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Issue not found']);
        }
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid issue ID']);
}
