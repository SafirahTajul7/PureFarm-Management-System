<?php
// Start session before any output
session_start();

require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Check if success message exists in session
$successMessage = '';
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Schedules - PureFarm</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
<style>
    * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        html, body {
            height: 100%;
            min-height: 100vh;
        }
        
        body {
            display: flex;
            background-color: #f0f2f5;
            flex-direction: column;
        }

        .container {
            display: flex;
        }

        .main-content {
            flex: 1 0 auto;
            margin-left: 280px;
            padding: 20px;
            padding-bottom: 100px; /* Add padding to prevent footer overlap */
            overflow-y: auto;
        }

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

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .calendar-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .schedules-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .schedules-table th, 
        .schedules-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .schedules-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .schedules-table tr:hover {
            background-color: #f5f5f5;
        }

        .status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }

        .status.pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status.completed {
            background-color: #d4edda;
            color: #155724;
        }

        .status.cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .action-btn {
            padding: 5px 10px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            font-size: 12px;
            margin-right: 5px;
            display: inline-block;
        }

        .edit-btn { background-color: #ffc107; }
        .delete-btn { background-color: #dc3545; }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal.show {
            display: block;
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 70px;
                padding-bottom: 120px;
            }
            
            .footer {
                width: calc(100% - 70px);
                margin-left: 70px;
            }
        }

        .table-actions {
            white-space: nowrap;
        }
        .success-message {
            animation: fadeOut 5s forwards;
        }
        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; }
        }
        .card-header {
            background-color: #198754 !important;
        }
        .calendar-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: auto;
            min-height: 500px;
            max-height: 600px;
            overflow: visible;
        }
        .calendar-event {
            background-color: #198754;
            color: white;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 0.9em;
            margin: 1px 0;
        }

        .calendar-container #calendar {
            height: 100%; /* Make calendar fill container */
            font-size: 0.9em; /* Slightly reduce font size */
        }

        /* Adjust the calendar header size */
        .fc .fc-toolbar {
            font-size: 0.9em;
            margin-bottom: 0.4em !important;
            padding: 6px !important;
        }

        /* Make the calendar cells more compact */
        .fc .fc-daygrid-day-frame {
            padding: 2px !important;
            min-height: 35px !important;
        }

        /* Adjust event text size */
        .fc-event-title {
            font-size: 0.8em;
        }

        .fc .fc-daygrid-day-number {
            font-size: 0.85em;
            padding: 3px 5px !important;
        }

        /* Make toolbar buttons smaller */
        .fc .fc-button {
            padding: 0.25em 0.45em !important;
            font-size: 0.9em !important;
        }

        .footer {
            flex-shrink: 0;
            background-color: #f8f9fa;
            padding: 15px 0;
            width: calc(100% - 280px); /* Adjust width to account for sidebar */
            border-top: 1px solid #dee2e6;
            margin-left: 280px;
            position: fixed;
            bottom: 0;
            z-index: 1000;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-left, .footer-right {
            padding: 0 15px;
        }

        .footer-left p, .footer-right p {
            margin: 0;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .footer {
                margin-left: 70px;
            }
            
            .footer-content {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
        }

        .fc .fc-toolbar-title {
            font-size: 1.2em;
        }

        .fc-event {
            cursor: pointer;
            margin: 2px 0;
            padding: 2px 5px;
        }

        .fc-event:hover {
            opacity: 0.9;
        }

        /* Make the table scrollable if needed */
        .upcoming-schedules {
            margin-bottom: 80px; /* Add space above footer */
        }
</style>

</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <?php if ($successMessage): ?>
            <div class="success-message">
                <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h2><i class="fas fa-calendar-alt"></i> Health Schedules</h2>
            <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='animal_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Animal Management
            </button>
                
            </div>
        </div>

        <div class="calendar-container">
            <div id="calendar"></div>
        </div>

        <div class="upcoming-schedules">
        <h3>Upcoming Appointments</h3>
        <table class="schedules-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Animal ID</th>
                    <th>Type</th>
                    <th>Details</th>
                    <th>Veterinarian</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    // Query to get both health records and upcoming vaccinations
                    $stmt = $pdo->prepare("
                        SELECT 
                            'health' as record_type,
                            hr.id,
                            hr.date,
                            a.animal_id as animal_code,
                            hr.treatment as type,
                            CONCAT(hr.condition, ' - ', hr.treatment) as details,
                            hr.vet_name,
                            'Completed' as status
                        FROM health_records hr
                        JOIN animals a ON hr.animal_id = a.id
                        WHERE hr.date >= CURRENT_DATE
                        
                        UNION ALL
                        
                        SELECT 
                            'vaccination' as record_type,
                            v.id,
                            v.next_due as date,
                            a.animal_id as animal_code,
                            v.type,
                            CONCAT('Next vaccination due: ', v.type) as details,
                            v.administered_by as vet_name,
                            CASE 
                                WHEN v.next_due > CURRENT_DATE THEN 'Pending'
                                ELSE 'Completed'
                            END as status
                        FROM vaccinations v
                        JOIN animals a ON v.animal_id = a.id
                        WHERE v.next_due >= CURRENT_DATE
                        
                        ORDER BY date ASC
                        LIMIT 10
                    ");

                    $stmt->execute();

                    while($row = $stmt->fetch()) {
                        $statusClass = strtolower($row['status']);
                        ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['animal_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['type']); ?></td>
                            <td><?php echo htmlspecialchars($row['details']); ?></td>
                            <td><?php echo htmlspecialchars($row['vet_name']); ?></td>
                            <td>
                                <span class="status <?php echo $statusClass; ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td class="table-actions">
                                <button class="action-btn view-btn" 
                                        onclick="viewDetails('<?php echo $row['record_type']; ?>', <?php echo $row['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php
                    }
                } catch(PDOException $e) {
                    echo "<tr><td colspan='7'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    </div>

    <!-- Add/Edit Schedule Modal -->
    <div id="scheduleModal" class="modal">
        <!-- Add Schedule Modal -->
        <div class="modal fade" id="addScheduleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Add New Schedule</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="addScheduleForm" action="add_schedule.php" method="POST">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Animal ID</label>
                                <select class="form-select" name="animal_id" required>
                                    <option value="">Select Animal</option>
                                    <?php
                                    try {
                                        $stmt = $pdo->query("SELECT id, animal_id, species, breed FROM animals");
                                        while($animal = $stmt->fetch()) {
                                            echo "<option value='{$animal['id']}'>{$animal['animal_id']} - {$animal['species']} ({$animal['breed']})</option>";
                                        }
                                    } catch(PDOException $e) {
                                        echo "Error: " . $e->getMessage();
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date & Time</label>
                                <input type="datetime-local" class="form-control" name="date" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Appointment Type</label>
                                <select class="form-select" name="appointment_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Vaccination">Vaccination</option>
                                    <option value="Check-up">Check-up</option>
                                    <option value="Treatment">Treatment</option>
                                    <option value="Surgery">Surgery</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Details</label>
                                <textarea class="form-control" name="details" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Veterinarian</label>
                                <select class="form-select" name="vet_name" required>
                                    <option value="">Select Veterinarian</option>
                                    <option value="Dr. Sarah Lee">Dr. Sarah Lee</option>
                                    <option value="Dr. Ahmad Kamal">Dr. Ahmad Kamal</option>
                                    <option value="Dr. Ahmed Khan">Dr. Ahmed Khan</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="Pending">Pending</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success">Save Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Schedule Modal -->
        <div class="modal fade" id="editScheduleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Edit Schedule</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="editScheduleForm" action="edit_schedule.php" method="POST">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="modal-body">
                            <!-- Same form fields as Add Schedule Modal -->
                            <div class="mb-3">
                                <label class="form-label">Animal ID</label>
                                <select class="form-select" name="animal_id" id="edit_animal_id" required>
                                    <?php
                                    try {
                                        $stmt = $pdo->query("SELECT id, animal_id, species, breed FROM animals");
                                        while($animal = $stmt->fetch()) {
                                            echo "<option value='{$animal['id']}'>{$animal['animal_id']} - {$animal['species']} ({$animal['breed']})</option>";
                                        }
                                    } catch(PDOException $e) {
                                        echo "Error: " . $e->getMessage();
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date & Time</label>
                                <input type="datetime-local" class="form-control" name="date" id="edit_date" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Appointment Type</label>
                                <select class="form-select" name="appointment_type" id="edit_appointment_type" required>
                                    <option value="Vaccination">Vaccination</option>
                                    <option value="Check-up">Check-up</option>
                                    <option value="Treatment">Treatment</option>
                                    <option value="Surgery">Surgery</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Details</label>
                                <textarea class="form-control" name="details" id="edit_details" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Veterinarian</label>
                                <select class="form-select" name="vet_name" id="edit_vet_name" required>
                                    <option value="Dr. Sarah Lee">Dr. Sarah Lee</option>
                                    <option value="Dr. Ahmad Kamal">Dr. Ahmad Kamal</option>
                                    <option value="Dr. Ahmed Khan">Dr. Ahmed Khan</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="edit_status" required>
                                    <option value="Pending">Pending</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Required Scripts - Place these just before closing body tag -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    


    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: 'get_schedules.php',
            eventDidMount: function(info) {
                // Add tooltips
                const event = info.event;
                const tipContent = `
                    Animal: ${event.extendedProps.animal_code}
                    Event: ${event.title}
                    ${event.extendedProps.description}
                    Veterinarian: ${event.extendedProps.vet_name}
                    Status: ${event.extendedProps.status}
                `;
                
                info.el.setAttribute('title', tipContent);
            },
            eventClick: function(info) {
                // Show detailed event information
                const event = info.event;
                alert(
                    `Event Details:\n\n` +
                    `Animal: ${event.extendedProps.animal_code}\n` +
                    `Event: ${event.title}\n` +
                    `Date: ${event.start.toLocaleDateString()}\n` +
                    `Details: ${event.extendedProps.description}\n` +
                    `Veterinarian: ${event.extendedProps.vet_name}\n` +
                    `Status: ${event.extendedProps.status}`
                );
            },
            dayMaxEvents: true,
            height: 'auto',
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            },
            slotMinTime: '08:00:00',
            slotMaxTime: '18:00:00',
            // Improve event display
            eventDisplay: 'block',
            displayEventEnd: true,
            eventInteractive: true,
            // Color coding legend
            eventDidMount: function(info) {
                if (!document.getElementById('calendar-legend')) {
                    const legend = document.createElement('div');
                    legend.id = 'calendar-legend';
                    legend.style.padding = '10px';
                    legend.style.marginTop = '10px';
                    legend.innerHTML = `
                        <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center;">
                                <span style="display: inline-block; width: 12px; height: 12px; background: #28a745; margin-right: 5px; border-radius: 2px;"></span>
                                <span>Completed</span>
                            </div>
                            <div style="display: flex; align-items: center;">
                                <span style="display: inline-block; width: 12px; height: 12px; background: #007bff; margin-right: 5px; border-radius: 2px;"></span>
                                <span>Pending</span>
                            </div>
                            <div style="display: flex; align-items: center;">
                                <span style="display: inline-block; width: 12px; height: 12px; background: #ffc107; margin-right: 5px; border-radius: 2px;"></span>
                                <span>Upcoming Vaccination</span>
                            </div>
                        </div>
                    `;
                    calendarEl.parentNode.appendChild(legend);
                }
            }
        });
        calendar.render();

        // Initialize DataTable
        $('#scheduleTable').DataTable({
            "order": [[0, "asc"]],
            "pageLength": 10,
            "language": {
                "search": "Search schedules:"
            }
        });

        // Form Submissions
        $('#addScheduleForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    $('#addScheduleModal').modal('hide');
                    location.reload();
                },
                error: function(xhr, status, error) {
                    alert('Error adding schedule: ' + error);
                }
            });
        });

        $('#editScheduleForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    $('#editScheduleModal').modal('hide');
                    location.reload();
                },
                error: function(xhr, status, error) {
                    alert('Error updating schedule: ' + error);
                }
            });
        });
    });

    function editSchedule(id) {
        $.ajax({
            url: 'get_schedule.php',
            type: 'GET',
            data: { id: id },
            success: function(response) {
                const schedule = JSON.parse(response);
                $('#edit_id').val(schedule.id);
                $('#edit_animal_id').val(schedule.animal_id);
                $('#edit_date').val(schedule.date.replace(' ', 'T')); // Convert to datetime-local format
                $('#edit_appointment_type').val(schedule.appointment_type);
                $('#edit_details').val(schedule.details);
                $('#edit_vet_name').val(schedule.vet_name);
                $('#edit_status').val(schedule.status);
                $('#editScheduleModal').modal('show');
            },
            error: function(xhr, status, error) {
                alert('Error fetching schedule: ' + error);
            }
        });
    }

    function deleteSchedule(id) {
        if(confirm('Are you sure you want to delete this schedule?')) {
            $.ajax({
                url: 'delete_schedule.php',
                type: 'POST',
                data: { id: id },
                success: function(response) {
                    location.reload();
                },
                error: function(xhr, status, error) {
                    alert('Error deleting schedule: ' + error);
                }
            });
        }
    }

    function viewDetails(recordType, id) {
        // Use the exact filenames of your PHP files
        let url = recordType === 'health' ? 'get_health_record.php' : 'get_vaccination.php';
        
        $.ajax({
            url: url,
            type: 'GET',
            data: { id: id },
            success: function(response) {
                try {
                    // Handle the case where response is already an object
                    const record = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    let details = '';
                    if (recordType === 'health') {
                        details = `Health Record Details:\n\n` +
                                `Date: ${new Date(record.date).toLocaleDateString()}\n` +
                                `Animal ID: ${record.animal_code}\n` +
                                `Condition: ${record.condition}\n` +
                                `Treatment: ${record.treatment}\n` +
                                `Veterinarian: ${record.vet_name}`;
                    } else {
                        details = `Vaccination Details:\n\n` +
                                `Date: ${new Date(record.date).toLocaleDateString()}\n` +
                                `Animal ID: ${record.animal_code}\n` +
                                `Type: ${record.type}\n` +
                                `Next Due: ${new Date(record.next_due).toLocaleDateString()}\n` +
                                `Administered By: ${record.administered_by}`;
                    }
                    
                    alert(details);
                } catch (e) {
                    console.error('Error parsing response:', e);
                    alert('Error displaying details. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
                alert('Error fetching details. Please try again.');
            }
        });
    }
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>