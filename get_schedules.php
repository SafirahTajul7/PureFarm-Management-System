<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

try {
    // Fetch health records and vaccinations
    $stmt = $pdo->prepare("
        SELECT 
            CONCAT('hr_', hr.id) as id,
            hr.animal_id,
            a.animal_id as animal_code,
            hr.date as start,
            CONCAT(hr.treatment, ' (', hr.condition, ')') as title,
            hr.treatment as description,
            hr.vet_name,
            'Completed' as status,
            '#28a745' as backgroundColor
        FROM health_records hr
        JOIN animals a ON hr.animal_id = a.id
        
        UNION ALL
        
        SELECT 
            CONCAT('vac_', v.id) as id,
            v.animal_id,
            a.animal_id as animal_code,
            v.date as start,
            v.type as title,
            CONCAT('Next due: ', v.next_due) as description,
            v.administered_by as vet_name,
            CASE 
                WHEN v.next_due > CURRENT_DATE THEN 'Pending'
                ELSE 'Completed'
            END as status,
            CASE 
                WHEN v.next_due > CURRENT_DATE THEN '#007bff'
                ELSE '#28a745'
            END as backgroundColor
        FROM vaccinations v
        JOIN animals a ON v.animal_id = a.id
        
        UNION ALL
        
        SELECT 
            CONCAT('next_', v.id) as id,
            v.animal_id,
            a.animal_id as animal_code,
            v.next_due as start,
            CONCAT('Due: ', v.type) as title,
            'Upcoming vaccination' as description,
            v.administered_by as vet_name,
            'Pending' as status,
            '#ffc107' as backgroundColor
        FROM vaccinations v
        JOIN animals a ON v.animal_id = a.id
        WHERE v.next_due > CURRENT_DATE
    ");

    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format events for FullCalendar
    $calendarEvents = array_map(function($event) {
        return [
            'id' => $event['id'],
            'title' => "(" . $event['animal_code'] . ") " . $event['title'],
            'start' => $event['start'],
            'description' => $event['description'],
            'backgroundColor' => $event['backgroundColor'],
            'borderColor' => $event['backgroundColor'],
            'textColor' => $event['backgroundColor'] === '#ffc107' ? '#000' : '#fff',
            'extendedProps' => [
                'animal_code' => $event['animal_code'],
                'vet_name' => $event['vet_name'],
                'status' => $event['status'],
                'description' => $event['description']
            ]
        ];
    }, $events);

    echo json_encode($calendarEvents);

} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>