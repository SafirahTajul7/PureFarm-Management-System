<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Set default values for filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$field_id = isset($_GET['field_id']) ? intval($_GET['field_id']) : 0;

// Fetch all fields for dropdown
try {
    $fields_stmt = $pdo->query("SELECT id, field_name FROM fields ORDER BY field_name");
    $fields = $fields_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

// Check if weather_data table exists
$weather_table_exists = false;
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'weather_data'");
    $weather_table_exists = $table_check->rowCount() > 0;
    
    if (!$weather_table_exists) {
        // Create the weather_data table if it doesn't exist
        $pdo->exec("
            CREATE TABLE `weather_data` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `field_id` int(11) NOT NULL,
                `recorded_at` datetime NOT NULL,
                `temperature` float NOT NULL,
                `humidity` float NOT NULL,
                `pressure` float DEFAULT NULL,
                `wind_speed` float DEFAULT NULL,
                `wind_direction` int(11) DEFAULT NULL,
                `precipitation` float DEFAULT NULL,
                `conditions` varchar(50) DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `field_id` (`field_id`),
                KEY `recorded_at` (`recorded_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        // Insert sample weather data
        $field_ids = array_column($fields, 'id');
        if (!empty($field_ids)) {
            // Generate data for the last 30 days
            $sample_data = [];
            for ($i = 0; $i < 30; $i++) {
                foreach ($field_ids as $field_id) {
                    $date = date('Y-m-d H:i:s', strtotime("-$i days"));
                    $sample_data[] = [
                        'field_id' => $field_id,
                        'recorded_at' => $date,
                        'temperature' => rand(15, 30) + (rand(0, 10) / 10),
                        'humidity' => rand(40, 90) + (rand(0, 10) / 10),
                        'pressure' => rand(1000, 1030) + (rand(0, 10) / 10),
                        'wind_speed' => rand(0, 30) + (rand(0, 10) / 10),
                        'wind_direction' => rand(0, 359),
                        'precipitation' => (rand(0, 100) < 30) ? rand(0, 20) + (rand(0, 10) / 10) : 0,
                        'conditions' => ['Clear', 'Partly Cloudy', 'Cloudy', 'Rain', 'Thunderstorm'][rand(0, 4)]
                    ];
                }
            }
            
            // Insert sample data in batches
            $insert_stmt = $pdo->prepare("
                INSERT INTO weather_data 
                (field_id, recorded_at, temperature, humidity, pressure, wind_speed, wind_direction, precipitation, conditions)
                VALUES 
                (:field_id, :recorded_at, :temperature, :humidity, :pressure, :wind_speed, :wind_direction, :precipitation, :conditions)
            ");
            
            foreach ($sample_data as $data) {
                $insert_stmt->execute($data);
            }
        }
        
        $weather_table_exists = true;
    }
} catch(PDOException $e) {
    error_log("Error checking or creating weather_data table: " . $e->getMessage());
}

// Fetch weather data based on filters
try {
    if ($weather_table_exists) {
        $query = "
            SELECT 
                DATE(w.recorded_at) as date,
                w.temperature,
                w.humidity,
                w.pressure,
                w.wind_speed,
                w.wind_direction,
                w.precipitation,
                w.conditions,
                f.field_name
            FROM 
                weather_data w
            JOIN
                fields f ON w.field_id = f.id
            WHERE 
                DATE(w.recorded_at) BETWEEN :start_date AND :end_date
        ";
        
        $params = [
            ':start_date' => $start_date,
            ':end_date' => $end_date
        ];
        
        if ($field_id > 0) {
            $query .= " AND w.field_id = :field_id";
            $params[':field_id'] = $field_id;
        }
        
        $query .= " ORDER BY w.recorded_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $weather_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get data for chart
        $chart_query = "
            SELECT 
                DATE(recorded_at) as date,
                AVG(temperature) as avg_temp,
                AVG(humidity) as avg_humidity,
                SUM(precipitation) as total_precipitation
            FROM 
                weather_data
            WHERE 
                DATE(recorded_at) BETWEEN :start_date AND :end_date
        ";
        
        $chart_params = [
            ':start_date' => $start_date,
            ':end_date' => $end_date
        ];
        
        if ($field_id > 0) {
            $chart_query .= " AND field_id = :field_id";
            $chart_params[':field_id'] = $field_id;
        }
        
        $chart_query .= " GROUP BY DATE(recorded_at) ORDER BY date ASC";
        
        $chart_stmt = $pdo->prepare($chart_query);
        $chart_stmt->execute($chart_params);
        $chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format data for charts
        $dates = [];
        $temps = [];
        $humidity = [];
        $precipitation = [];
        
        foreach($chart_data as $row) {
            $dates[] = date('M d', strtotime($row['date']));
            $temps[] = round($row['avg_temp'], 1);
            $humidity[] = round($row['avg_humidity'], 1);
            $precipitation[] = round($row['total_precipitation'], 1);
        }
    } else {
        $weather_data = [];
        $dates = [];
        $temps = [];
        $humidity = [];
        $precipitation = [];
    }
    
    // Convert to JSON for JavaScript
    $dates_json = json_encode($dates);
    $temps_json = json_encode($temps);
    $humidity_json = json_encode($humidity);
    $precipitation_json = json_encode($precipitation);
    
} catch(PDOException $e) {
    error_log("Error fetching weather data: " . $e->getMessage());
    $weather_data = [];
    
    // Set empty arrays for chart data
    $dates_json = json_encode([]);
    $temps_json = json_encode([]);
    $humidity_json = json_encode([]);
    $precipitation_json = json_encode([]);
}

$pageTitle = 'Weather History';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-history"></i> Weather History</h2>
        <div class="action-buttons">
            <a href="current_weather.php" class="btn btn-primary">
                <i class="fas fa-cloud-sun"></i> Current Weather
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="filter-section mb-4">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-3">
                <label for="field_id" class="form-label">Field</label>
                <select class="form-select" id="field_id" name="field_id">
                    <option value="0">All Fields</option>
                    <?php foreach($fields as $field): ?>
                        <option value="<?php echo $field['id']; ?>" <?php echo ($field_id == $field['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($field['field_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Weather Charts -->
    <div class="section-header">
        <h3><i class="fas fa-chart-line"></i> Weather Trends</h3>
    </div>

    <div class="charts-container mb-4">
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="chart-card">
                    <h4>Temperature & Humidity</h4>
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="tempHumidityChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="chart-card">
                    <h4>Precipitation</h4>
                    <div class="chart-container" style="position: relative; height: 300px;">
                        <canvas id="precipitationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Weather Data Table -->
    <div class="section-header">
        <h3><i class="fas fa-table"></i> Weather Records</h3>
    </div>

    <div class="data-table-container mb-4">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Field</th>
                    <th>Temperature</th>
                    <th>Humidity</th>
                    <th>Pressure</th>
                    <th>Wind</th>
                    <th>Precipitation</th>
                    <th>Conditions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($weather_data)): ?>
                <tr>
                    <td colspan="8" class="text-center">No weather data available for the selected period.</td>
                </tr>
                <?php else: ?>
                    <?php foreach($weather_data as $data): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($data['date'])); ?></td>
                        <td><?php echo htmlspecialchars($data['field_name']); ?></td>
                        <td><?php echo round($data['temperature'], 1); ?>°C</td>
                        <td><?php echo round($data['humidity'], 1); ?>%</td>
                        <td><?php echo round($data['pressure'], 1); ?> hPa</td>
                        <td>
                            <?php echo round($data['wind_speed'], 1); ?> km/h 
                            <i class="fas fa-arrow-up" style="transform: rotate(<?php echo $data['wind_direction']; ?>deg);"></i>
                        </td>
                        <td><?php echo round($data['precipitation'], 1); ?> mm</td>
                        <td>
                            <?php
                            $condition = strtolower($data['conditions']);
                            $icon = 'cloud';
                            
                            if (strpos($condition, 'clear') !== false || strpos($condition, 'sunny') !== false) {
                                $icon = 'sun';
                            } elseif (strpos($condition, 'rain') !== false) {
                                $icon = 'cloud-rain';
                            } elseif (strpos($condition, 'snow') !== false) {
                                $icon = 'snowflake';
                            } elseif (strpos($condition, 'thunder') !== false || strpos($condition, 'storm') !== false) {
                                $icon = 'bolt';
                            } elseif (strpos($condition, 'fog') !== false || strpos($condition, 'mist') !== false) {
                                $icon = 'smog';
                            } elseif (strpos($condition, 'cloud') !== false) {
                                $icon = 'cloud';
                            } elseif (strpos($condition, 'partly') !== false) {
                                $icon = 'cloud-sun';
                            }
                            ?>
                            <i class="fas fa-<?php echo $icon; ?> me-1"></i> <?php echo htmlspecialchars($data['conditions']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Export Options -->
    <div class="export-section mb-4 text-end">
        <button class="btn btn-success" onclick="exportToCSV()">
            <i class="fas fa-file-csv"></i> Export to CSV
        </button>
        <button class="btn btn-danger" onclick="exportToPDF()">
            <i class="fas fa-file-pdf"></i> Export to PDF
        </button>
        <button class="btn btn-primary" onclick="printReport()">
            <i class="fas fa-print"></i> Print Report
        </button>
    </div>
</div>

<style>
    .main-content {
        padding-bottom: 60px;
        min-height: calc(100vh - 60px);
    }

    .filter-section {
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .chart-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        padding: 20px;
        height: 100%;
    }

    .chart-card h4 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #333;
        font-size: 18px;
    }

    .data-table-container {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        padding: 20px;
        overflow-x: auto;
    }

    .section-header {
        margin: 20px 0;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 10px;
    }
    
    .btn {
        margin-left: 5px;
    }
</style>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Add jsPDF library for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get chart data from PHP
    const dates = <?php echo $dates_json; ?>;
    const temps = <?php echo $temps_json; ?>;
    const humidity = <?php echo $humidity_json; ?>;
    const precipitation = <?php echo $precipitation_json; ?>;
    
    // Temperature & Humidity Chart
// Temperature & Humidity Chart
if (dates.length > 0) {
    const tempHumidityCtx = document.getElementById('tempHumidityChart').getContext('2d');
    new Chart(tempHumidityCtx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [
                {
                    label: 'Temperature (°C)',
                    data: temps,
                    borderColor: '#fd7e14',
                    backgroundColor: 'rgba(253, 126, 20, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    yAxisID: 'y'
                },
                {
                    label: 'Humidity (%)',
                    data: humidity,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 25,
                    top: 20,
                    bottom: 10
                }
            },
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: {
                    type: 'category',
                    distribution: 'series',
                    offset: true,
                    grid: {
                        display: true,
                        drawBorder: true,
                        drawOnChartArea: true
                    },
                    ticks: {
                        major: {
                            enabled: true
                        },
                        minRotation: 0,
                        autoSkip: false
                    },
                    afterFit: function(scale) {
                        // Force the x-axis to take up the full width
                        scale.width = scale.chart.width - 80;
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Temperature (°C)'
                    },
                    min: 0,
                    suggestedMax: 40,
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Humidity (%)'
                    },
                    min: 0,
                    max: 100,
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
        
        // Precipitation Chart
        const precipitationCtx = document.getElementById('precipitationChart').getContext('2d');
        new Chart(precipitationCtx, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Precipitation (mm)',
                        data: precipitation,
                        backgroundColor: 'rgba(13, 110, 253, 0.6)',
                        borderColor: '#0d6efd',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Precipitation (mm)'
                        }
                    }
                }
            }
        });
    } else {
        // If no data, display message in chart containers
        document.getElementById('tempHumidityChart').parentNode.innerHTML = 
            '<div style="display: flex; align-items: center; justify-content: center; height: 300px;">' +
            '<p><i class="fas fa-info-circle" style="font-size: 24px; margin-right: 10px;"></i> ' +
            'No weather data available for the selected period.</p></div>';
            
        document.getElementById('precipitationChart').parentNode.innerHTML = 
            '<div style="display: flex; align-items: center; justify-content: center; height: 300px;">' +
            '<p><i class="fas fa-info-circle" style="font-size: 24px; margin-right: 10px;"></i> ' +
            'No precipitation data available for the selected period.</p></div>';
    }
});

// Export functions
function exportToCSV() {
    // Get the table element
    const table = document.querySelector('.data-table-container table');
    if (!table) return;
    
    // Check if there's data in the table (more than just the header row)
    if (table.rows.length <= 1) {
        alert("No weather data available to export");
        return;
    }
    
    // Create CSV content
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Add headers
    const headers = [];
    for (let i = 0; i < table.rows[0].cells.length - 1; i++) { // Skip the "Actions" column
        headers.push(table.rows[0].cells[i].textContent.trim());
    }
    csvContent += headers.join(",") + "\n";
    
    // Add data rows
    for (let i = 1; i < table.rows.length; i++) {
        const row = table.rows[i];
        const rowData = [];
        
        // Skip if this is a "no data" message row
        if (row.cells.length === 1 && row.cells[0].colSpan > 1) continue;
        
        // Get text content from each cell (except the last "Actions" column)
        for (let j = 0; j < row.cells.length - 1; j++) {
            // Clean the data (remove any commas that would break CSV format)
            let cellData = row.cells[j].textContent.trim().replace(/,/g, ' ');
            // For cells with direction arrows, just get the text part
            if (j === 5) { // Wind column
                cellData = cellData.replace(/[^\d.\s]/g, '').trim() + " km/h";
            }
            rowData.push(cellData);
        }
        
        csvContent += rowData.join(",") + "\n";
    }
    
    // Create download link
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `weather_history_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    
    // Trigger download and remove link
    link.click();
    document.body.removeChild(link);
}

function exportToPDF() {
    // Get the table element
    const table = document.querySelector('.data-table-container table');
    if (!table) return;
    
    // Check if there's data in the table (more than just the header row)
    if (table.rows.length <= 1) {
        alert("No weather data available to export");
        return;
    }
    
    // Create jsPDF instance
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Add title
    doc.setFontSize(18);
    doc.text('Weather History Report', 14, 22);
    
    // Add date range
    const startDate = document.getElementById('start_date')?.value || 'N/A';
    const endDate = document.getElementById('end_date')?.value || 'N/A';
    doc.setFontSize(12);
    doc.text(`Date Range: ${startDate} to ${endDate}`, 14, 30);
    
    // Get table data
    const headers = [];
    for (let i = 0; i < table.rows[0].cells.length - 1; i++) { // Skip the "Actions" column
        headers.push(table.rows[0].cells[i].textContent.trim());
    }
    
    const data = [];
    for (let i = 1; i < table.rows.length; i++) {
        const row = table.rows[i];
        
        // Skip if this is a "no data" message row
        if (row.cells.length === 1 && row.cells[0].colSpan > 1) continue;
        
        const rowData = [];
        for (let j = 0; j < row.cells.length - 1; j++) { // Skip the "Actions" column
            // For wind column, just get the text
            if (j === 5) { // Wind column
                rowData.push(row.cells[j].textContent.replace(/[^\d.\s]/g, '').trim() + " km/h");
            } else {
                rowData.push(row.cells[j].textContent.trim());
            }
        }
        data.push(rowData);
    }
    
    // Add table to PDF
    doc.autoTable({
        head: [headers],
        body: data,
        startY: 40,
        theme: 'grid',
        styles: {
            fontSize: 8,
            cellPadding: 3
        },
        headStyles: {
            fillColor: [0, 123, 255],
            textColor: 255
        },
        columnStyles: {
            0: {cellWidth: 20}, // Date
            1: {cellWidth: 20}, // Field
            2: {cellWidth: 20}, // Temperature
            3: {cellWidth: 20}, // Humidity
            4: {cellWidth: 20}, // Pressure
            5: {cellWidth: 20}, // Wind
            6: {cellWidth: 20}, // Precipitation
            7: {cellWidth: 50}  // Conditions
        }
    });
    
    // Add footer with date and page numbers
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.text(`Generated on ${new Date().toLocaleDateString()} | Page ${i} of ${pageCount}`, 
            doc.internal.pageSize.width / 2, 
            doc.internal.pageSize.height - 10, 
            { align: 'center' }
        );
    }
    
    // Save the PDF
    doc.save(`weather_history_${new Date().toISOString().slice(0,10)}.pdf`);
}

function printReport() {
    // Create a print-specific stylesheet if needed
    const style = document.createElement('style');
    style.innerHTML = `
        @media print {
            body * { visibility: hidden; }
            .main-content, .main-content * { visibility: visible; }
            .action-buttons, .filter-section, .export-section, footer { display: none !important; }
            .main-content { position: absolute; left: 0; top: 0; width: 100%; }
            .table th, .table td { padding: 8px; }
            .table { width: 100%; border-collapse: collapse; }
            .table th { background-color: #f8f9fa !important; }
        }
    `;
    document.head.appendChild(style);
    
    // Print the document
    window.print();
    
    // Remove the style element after printing
    setTimeout(() => {
        document.head.removeChild(style);
    }, 1000);
}
</script>