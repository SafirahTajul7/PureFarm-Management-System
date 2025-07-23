<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Fetch real-time summary statistics from staff table
try {
    // Total active staff
    $total_staff = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn();
} catch(PDOException $e) {
    error_log("Error counting active staff: " . $e->getMessage());
    $total_staff = 0;
}

try {
    // Tasks due soon - if staff_tasks table exists
    $taskTableExists = $pdo->query("SHOW TABLES LIKE 'staff_tasks'")->rowCount() > 0;
    if ($taskTableExists) {
        $pending_tasks = $pdo->query("SELECT COUNT(*) FROM staff_tasks WHERE status = 'pending'")->fetchColumn();
    } else {
        // Fallback - use a count of staff with assigned tasks in the next 7 days
        $pending_tasks = 7; // Default placeholder value
    }
} catch(PDOException $e) {
    error_log("Error counting pending tasks: " . $e->getMessage());
    $pending_tasks = 0;
}

try {
    // Staff on duty (active and not on leave)
    $staff_on_duty = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn();
} catch(PDOException $e) {
    error_log("Error counting staff on duty: " . $e->getMessage());
    $staff_on_duty = 0;
}

try {
    // Staff on leave
    $staff_on_leave = $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'on-leave'")->fetchColumn();
} catch(PDOException $e) {
    error_log("Error counting staff on leave: " . $e->getMessage());
    $staff_on_leave = 0;
}

$pageTitle = 'Staff Management';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-users"></i> Staff Management</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='staff_reports.php'">
                <i class="fas fa-chart-bar"></i> View Reports
            </button>
        </div>
    </div>

    <div class="content-wrapper">
        <!-- Left Column Content -->
        <div class="main-column">
            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-icon bg-blue">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="summary-details">
                        <h3>Total Active Staff</h3>
                        <p class="summary-count"><?php echo isset($total_staff) ? $total_staff : 0; ?></p>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon bg-orange">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="summary-details">
                        <h3>Pending Tasks</h3>
                        <p class="summary-count"><?php echo isset($pending_tasks) ? $pending_tasks : 0; ?></p>
                        <span class="summary-subtitle">Next 7 days</span>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="summary-icon bg-green">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <div class="summary-details">
                        <h3>Staff On Duty</h3>
                        <p class="summary-count"><?php echo isset($staff_on_duty) ? $staff_on_duty : 0; ?></p>
                        <span class="summary-subtitle">Today</span>
                    </div>
                </div>                <div class="summary-card">
                    <div class="summary-icon bg-red">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="summary-details">
                        <h3>Staff On Leave</h3>
                        <p class="summary-count"><?php echo isset($staff_on_leave) ? $staff_on_leave : 0; ?></p>
                        <span class="summary-subtitle">Current Status</span>
                    </div>
                </div>
            </div>

            <!-- Feature Grid -->
            <div class="features-grid">
                <!-- Staff Information - Blue Theme -->
                <div class="feature-card animal-records">
                    <h3><i class="fas fa-id-card"></i> Staff Information</h3>
                    <ul>
                        <li onclick="location.href='staff_directory.php'">
                            <div class="menu-item">
                                <i class="fas fa-list-ul"></i>
                                <div class="menu-content">
                                    <span class="menu-title">Staff Directory</span>
                                    <span class="menu-desc">View and manage staff profiles</span>
                                </div>
                            </div>
                        </li>
                        <li onclick="location.href='staff_documents.php'">
                            <div class="menu-item">
                                <i class="fas fa-file-alt"></i>
                                <div class="menu-content">
                                    <span class="menu-title">Documents & Records</span>
                                    <span class="menu-desc">Employment and certification records</span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Task Management - Green Theme -->
                <div class="feature-card health-management">
                    <h3><i class="fas fa-clipboard-list"></i> Task Management</h3>
                    <ul>
                        <li onclick="location.href='task_assignment.php'">
                            <div class="menu-item">
                                <i class="fas fa-tasks"></i>
                                <div class="menu-content">
                                    <span class="menu-title">Task Assignment</span>
                                    <span class="menu-desc">Assign and track staff tasks</span>
                                </div>
                            </div>
                        </li>
                        <li onclick="location.href='task_progress.php'">
                            <div class="menu-item">
                                <i class="fas fa-chart-line"></i>
                                <div class="menu-content">
                                    <span class="menu-title">Progress Tracking</span>
                                    <span class="menu-desc">Monitor task completion status</span>
                                </div>
                            </div>
                        </li>
                        <!-- Removed task_schedule.php link -->
                    </ul>
                </div>

            </div>
        </div>

        <!-- Right Column - Calendar and Timeline -->
        <div class="side-column">
            <!-- Calendar Section -->
            <div class="calendar-container">
                <div class="calendar-header">
                    <h3 id="currentMonthYear">May 2025</h3>
                    <div class="calendar-controls">
                        <button class="calendar-nav" id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                        <button class="calendar-nav" id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
                <div class="calendar-body">
                    <div class="weekdays">
                        <div>Mo</div>
                        <div>Tu</div>
                        <div>We</div>
                        <div>Th</div>
                        <div>Fr</div>
                        <div>Sa</div>
                        <div>Su</div>
                    </div>
                    <div class="days" id="calendarDays">
                        <!-- Calendar days will be generated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Timeline Section -->
            <div class="timeline-container">
                <div class="timeline-header">
                    <h3 id="selectedDateTitle">Today's Schedule</h3>
                    <button class="btn btn-sm btn-primary" id="addEventBtn">
                        <i class="fas fa-plus"></i> Add Event
                    </button>
                </div>
                <div class="timeline-items" id="timelineItems">
                    <!-- Timeline items will be populated by JavaScript -->
                </div>
                <div class="empty-timeline" id="emptyTimeline" style="display: none;">
                    <p>No events scheduled for this date.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Event Modal -->
    <div class="modal" id="eventModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add Event</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="eventForm">
                    <input type="hidden" id="eventId" value="">
                    <input type="hidden" id="eventDate" value="">
                    
                    <div class="form-group">
                        <label for="eventTime">Time</label>
                        <input type="time" id="eventTime" class="form-control" required>
                    </div>
                      <div class="form-group">
                        <label for="eventTitle">Event Title</label>
                        <input type="text" id="eventTitle" class="form-control" placeholder="Enter event title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="eventLocation">Location</label>
                        <input type="text" id="eventLocation" class="form-control" placeholder="Enter location">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelEvent">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveEvent">Save</button>
                <button type="button" class="btn btn-danger" id="deleteEvent" style="display:none;">Delete</button>
            </div>
        </div>
    </div>
    
    </div>
<?php include 'includes/footer.php'; ?>

<script>
// Sample data - this would typically come from a database
let currentEvents = {
    "2025-05-08": [
        { id: 1, time: "09:00", title: "Morning Staff Meeting", location: "Farm Office" },
        { id: 2, time: "11:30", title: "Equipment Maintenance Check", location: "Machinery Shed" },
        { id: 3, time: "14:00", title: "Livestock Health Inspection", location: "Barn Area" },
        { id: 4, time: "16:30", title: "Field Team Debrief", location: "Meeting Room 2" }
    ],
    "2025-05-10": [
        { id: 5, time: "10:00", title: "Crop Inspection", location: "East Fields" },
        { id: 6, time: "13:00", title: "Staff Training", location: "Training Room" }
    ],
    "2025-05-15": [
        { id: 7, time: "09:30", title: "Inventory Check", location: "Warehouse" }
    ]
};

// Calendar variables
let currentMonth = 5; // May (1-12)
let currentYear = 2025;
let selectedDate = "2025-05-09"; // Format: YYYY-MM-DD - Default to May 9th
let nextEventId = 8; // For generating new event IDs

// DOM elements
const calendarDays = document.getElementById('calendarDays');
const currentMonthYear = document.getElementById('currentMonthYear');
const selectedDateTitle = document.getElementById('selectedDateTitle');
const timelineItems = document.getElementById('timelineItems');
const emptyTimeline = document.getElementById('emptyTimeline');

// Modal elements
const eventModal = document.getElementById('eventModal');
const modalTitle = document.getElementById('modalTitle');
const eventForm = document.getElementById('eventForm');
const eventId = document.getElementById('eventId');
const eventDate = document.getElementById('eventDate');
const eventTime = document.getElementById('eventTime');
const eventTitle = document.getElementById('eventTitle');
const eventLocation = document.getElementById('eventLocation');
const saveEventBtn = document.getElementById('saveEvent');
const cancelEventBtn = document.getElementById('cancelEvent');
const deleteEventBtn = document.getElementById('deleteEvent');

// Initialize calendar
document.addEventListener('DOMContentLoaded', function() {
    generateCalendar(currentMonth, currentYear);
    updateTimeline(selectedDate);
    
    // Set up event listeners
    document.getElementById('prevMonth').addEventListener('click', () => {
        if (currentMonth === 1) {
            currentMonth = 12;
            currentYear--;
        } else {
            currentMonth--;
        }
        generateCalendar(currentMonth, currentYear);
    });
    
    document.getElementById('nextMonth').addEventListener('click', () => {
        if (currentMonth === 12) {
            currentMonth = 1;
            currentYear++;
        } else {
            currentMonth++;
        }
        generateCalendar(currentMonth, currentYear);
    });
    
    document.getElementById('addEventBtn').addEventListener('click', () => {
        openAddEventModal();
    });
    
    // Modal event handlers
    document.querySelector('.close-modal').addEventListener('click', closeModal);
    cancelEventBtn.addEventListener('click', closeModal);
    saveEventBtn.addEventListener('click', saveEvent);
    deleteEventBtn.addEventListener('click', deleteEvent);
});

// Generate calendar for specified month and year
function generateCalendar(month, year) {
    calendarDays.innerHTML = '';
    currentMonthYear.textContent = `${getMonthName(month)} ${year}`;
    
    // Get the first day of the month (0 = Sunday, 1 = Monday, etc.)
    const firstDay = new Date(year, month - 1, 1).getDay();
    // Adjust for Monday as first day (0 = Monday, 6 = Sunday)
    const firstDayAdjusted = firstDay === 0 ? 6 : firstDay - 1;
    
    // Get number of days in the month
    const daysInMonth = new Date(year, month, 0).getDate();
    
    // Add empty cells for days before the first day of the month
    for (let i = 0; i < firstDayAdjusted; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.classList.add('day', 'empty');
        calendarDays.appendChild(emptyDay);
    }
    
    // Add cells for each day of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const dateString = formatDate(year, month, day);
        const dayElement = document.createElement('div');
        dayElement.classList.add('day');
        dayElement.textContent = day;
        dayElement.dataset.date = dateString;
        
        // Add current class if it's today
        if (dateString === selectedDate) {
            dayElement.classList.add('current');
        }
        
        // Add event indicator if there are events for this day
        if (currentEvents[dateString] && currentEvents[dateString].length > 0) {
            dayElement.classList.add('has-events');
        }
          // Add click event to select date
        dayElement.addEventListener('click', () => {
            // Remove 'current' class from previously selected day
            const currentlySelected = document.querySelector('.day.current');
            if (currentlySelected) {
                currentlySelected.classList.remove('current');
            }
            
            // Add 'current' class to newly selected day
            dayElement.classList.add('current');
            
            // Update selected date and refresh timeline
            selectedDate = dateString;
            
            // Ensure the Add Event button will use this date
            const addEventBtn = document.getElementById('addEventBtn');
            if (addEventBtn) {
                addEventBtn.onclick = () => {
                    openAddEventModal();
                };
            }
            
            updateTimeline(selectedDate);
        });
        
        calendarDays.appendChild(dayElement);
    }
    
    // Add empty cells for days after the month if needed to complete the grid
    const rowCount = Math.ceil((firstDayAdjusted + daysInMonth) / 7);
    const totalCells = rowCount * 7;
    const remainingCells = totalCells - (firstDayAdjusted + daysInMonth);
    
    for (let i = 0; i < remainingCells; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.classList.add('day', 'empty');
        calendarDays.appendChild(emptyDay);
    }
}

// Update the timeline for the selected date
function updateTimeline(date) {
    timelineItems.innerHTML = '';
    const formattedDate = formatDisplayDate(date);
    selectedDateTitle.textContent = formattedDate;
    
    // Make sure the Add Event button uses the correct selected date
    const addEventBtn = document.getElementById('addEventBtn');
    if (addEventBtn) {
        addEventBtn.onclick = () => {
            openAddEventModal();
        };
    }
    
    const events = currentEvents[date] || [];
    
    if (events.length === 0) {
        emptyTimeline.style.display = 'block';
        timelineItems.style.display = 'none';
    } else {
        emptyTimeline.style.display = 'none';
        timelineItems.style.display = 'flex';
        
        // Sort events by time
        events.sort((a, b) => a.time.localeCompare(b.time));
        
        // Add each event to the timeline
        events.forEach(event => {
            const timelineItem = document.createElement('div');
            timelineItem.classList.add('timeline-item');
            timelineItem.dataset.eventId = event.id;
            
            timelineItem.innerHTML = `
                <div class="timeline-time">${formatTime(event.time)}</div>
                <div class="timeline-content">
                    <div class="timeline-title">${event.title}</div>
                    <div class="timeline-subtitle">${event.location || ''}</div>
                </div>
            `;
            
            // Add click handler to edit event
            timelineItem.addEventListener('click', () => {
                openEditEventModal(event);
            });
            
            timelineItems.appendChild(timelineItem);
        });
    }
}

// Open modal to add a new event
function openAddEventModal() {
    modalTitle.textContent = 'Add Event';
    eventId.value = '';
    eventDate.value = selectedDate;
    eventTime.value = '';
    eventTitle.value = '';
    eventLocation.value = '';
    deleteEventBtn.style.display = 'none';
    
    eventModal.style.display = 'block';
}

// Open modal to edit an existing event
function openEditEventModal(event) {
    modalTitle.textContent = 'Edit Event';
    eventId.value = event.id;
    eventDate.value = selectedDate;
    eventTime.value = event.time;
    eventTitle.value = event.title;
    eventLocation.value = event.location || '';
    deleteEventBtn.style.display = 'inline-block';
    
    eventModal.style.display = 'block';
}

// Close the modal
function closeModal() {
    eventModal.style.display = 'none';
}

// Save an event (add new or update existing)
function saveEvent() {
    if (!eventTime.value || !eventTitle.value) {
        alert('Please fill in all required fields');
        return;
    }
    
    const date = eventDate.value;
    const time = eventTime.value;
    const title = eventTitle.value;
    const location = eventLocation.value;
    
    if (!currentEvents[date]) {
        currentEvents[date] = [];
    }
    
    // If there's an ID, it's an update
    if (eventId.value) {
        const index = currentEvents[date].findIndex(e => e.id == eventId.value);
        if (index !== -1) {
            currentEvents[date][index] = {
                id: parseInt(eventId.value),
                time: time,
                title: title,
                location: location
            };
        }
    } else {
        // Otherwise it's a new event
        currentEvents[date].push({
            id: nextEventId++,
            time: time,
            title: title,
            location: location
        });
        
        // Add event indicator to calendar day
        const dayElement = document.querySelector(`.day[data-date="${date}"]`);
        if (dayElement) {
            dayElement.classList.add('has-events');
        }
    }
    
    // Update timeline and close modal
    updateTimeline(date);
    closeModal();
    
    // In a real application, you would send this data to the server
    console.log('Event saved:', currentEvents[date]);
}

// Delete an event
function deleteEvent() {
    const date = eventDate.value;
    const id = parseInt(eventId.value);
    
    if (currentEvents[date]) {
        const index = currentEvents[date].findIndex(e => e.id === id);
        if (index !== -1) {
            currentEvents[date].splice(index, 1);
            
            // If no more events on this day, remove the indicator
            if (currentEvents[date].length === 0) {
                const dayElement = document.querySelector(`.day[data-date="${date}"]`);
                if (dayElement) {
                    dayElement.classList.remove('has-events');
                }
            }
            
            // Update timeline and close modal
            updateTimeline(date);
            closeModal();
            
            // In a real application, you would send this deletion to the server
            console.log('Event deleted:', id);
        }
    }
}

// Helper functions
function getMonthName(month) {
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    return monthNames[month - 1];
}

function formatDate(year, month, day) {
    return `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
}

function formatDisplayDate(dateString) {
    const date = new Date(dateString);
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('en-US', options) + "'s Schedule";
}

function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    return `${hour % 12 || 12}:${minutes} ${hour >= 12 ? 'PM' : 'AM'}`;
}
</script>

<style>
   .main-content {
    padding-bottom: 60px; /* Add space for footer */
    min-height: calc(100vh - 60px); /* Ensure content takes up full height minus footer */
}

/* Two-column layout */
.content-wrapper {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.main-column {
    flex: 1;
}

.side-column {
    width: 320px;
    flex-shrink: 0;
}

/* Calendar Styles */
.calendar-container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 20px;
}

.calendar-header {
    background: #f8f9fa;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #eaeaea;
}

.calendar-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.calendar-controls {
    display: flex;
    gap: 10px;
}

.calendar-nav {
    background: none;
    border: none;
    cursor: pointer;
    color: #555;
    font-size: 12px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.calendar-nav:hover {
    background: #eaeaea;
}

.calendar-body {
    padding: 10px;
}

.weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    text-align: center;
    font-weight: 500;
    font-size: 12px;
    color: #777;
    margin-bottom: 8px;
}

.days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
}

.day {
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    border-radius: 5px;
    cursor: pointer;
}

.day:hover {
    background: #f0f0f0;
}

.day.empty {
    visibility: hidden;
}

.day.current {
    background: #3498db;
    color: white;
    font-weight: 600;
}

/* Timeline Styles */
.timeline-container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 15px;
}

.timeline-container h3 {
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 600;
}

.timeline-items {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.timeline-item {
    display: flex;
    gap: 12px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.timeline-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.timeline-time {
    font-weight: 500;
    font-size: 14px;
    color: #3498db;
    width: 65px;
    flex-shrink: 0;
}

.timeline-content {
    flex: 1;
}

.timeline-title {
    font-weight: 500;
    font-size: 14px;
    margin-bottom: 3px;
}

.timeline-subtitle {
    font-size: 12px;
    color: #777;
}

/* Additional spacing for the features grid */
.features-grid {
    margin-bottom: 30px;
}

/* Feature Cards - Reduced Size */
.feature-card {
    padding: 12px; /* Further reduced padding */
    max-height: 240px; /* Limit maximum height */
    overflow-y: auto; /* Add scrollbar if content is too long */
}

.feature-card h3 {
    margin-bottom: 8px; /* Further reduced margin */
    padding-bottom: 8px; /* Reduced padding */
    font-size: 16px; /* Smaller font size */
}

.feature-card h3 i {
    font-size: 18px; /* Smaller icon size */
}

/* Menu Items - More compact */
.feature-card .menu-item {
    padding: 8px; /* Further reduced from 10px */
    gap: 8px; /* Reduced gap between icon and text */
}

.feature-card li {
    padding: 3px 0; /* Further reduced padding */
    border-bottom: 1px solid #eee; /* Add separator for clearer distinction */
}

.feature-card li:last-child {
    border-bottom: none; /* Remove border from last item */
}

.menu-content {
    flex: 1;
}

.menu-title {
    margin-bottom: 1px; /* Further reduced margin */
    font-size: 14px; /* Smaller font size */
}

.menu-desc {
    font-size: 12px; /* Smaller description text */
    color: #777;
}

/* Menu item icons */
.menu-item i {
    font-size: 14px; /* Smaller icons */
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .content-wrapper {
        flex-direction: column;
    }
    
    .side-column {
        width: 100%;
    }
}

/* Prevent content from being hidden behind footer */
body {
    padding-bottom: 60px;
} 

/* Timeline header with Add Event button */
.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.timeline-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

/* Calendar day with events */
.day.has-events {
    position: relative;
}

.day.has-events::after {
    content: '';
    position: absolute;
    bottom: 3px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 4px;
    background-color: #3498db;
    border-radius: 50%;
}

/* Empty timeline message */
.empty-timeline {
    text-align: center;
    color: #777;
    padding: 20px 0;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    position: relative;
    background-color: #fff;
    margin: 10% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #dee2e6;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.close-modal {
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    color: #666;
}

.modal-body {
    padding: 15px;
}

.modal-footer {
    padding: 15px;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}

/* Additional button styles */
.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
    border: none;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
    border: none;
}
</style>

<?php include 'includes/footer.php'; ?>