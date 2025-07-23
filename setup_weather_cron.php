<?php
/**
 * Weather Notification Cron Setup
 * 
 * This script helps set up a cron job to run the weather notification handler
 * automatically at regular intervals. It provides instructions and commands
 * for setting up the cron job on the server.
 */

// Check if running from web or CLI
$isCLI = (php_sapi_name() === 'cli');

// Get server path information
$scriptPath = __DIR__ . '/weather_notification_handler.php';
$phpPath = PHP_BINARY; // Path to PHP executable

// Generate cron job command
$cronCommand = $phpPath . ' ' . $scriptPath . ' >> ' . __DIR__ . '/logs/weather_cron.log 2>&1';

// Common cron intervals (in crontab format)
$intervals = [
    'every_15_minutes' => '*/15 * * * *',
    'every_30_minutes' => '*/30 * * * *',
    'hourly' => '0 * * * *',
    'twice_daily' => '0 */12 * * *',
    'daily' => '0 8 * * *', // 8 AM daily
];

// Output content based on environment
if ($isCLI) {
    // CLI output
    echo "=== PureFarm Weather Notification Cron Setup ===\n\n";
    echo "This utility helps you set up automatic weather notifications.\n\n";
    
    echo "Weather notification script location:\n";
    echo $scriptPath . "\n\n";
    
    echo "To run weather checks every 15 minutes, add this line to your crontab:\n";
    echo $intervals['every_15_minutes'] . " " . $cronCommand . "\n\n";
    
    echo "To run weather checks every 30 minutes:\n";
    echo $intervals['every_30_minutes'] . " " . $cronCommand . "\n\n";
    
    echo "To run weather checks hourly:\n";
    echo $intervals['hourly'] . " " . $cronCommand . "\n\n";
    
    echo "To run weather checks twice daily:\n";
    echo $intervals['twice_daily'] . " " . $cronCommand . "\n\n";
    
    echo "To run weather checks once daily at 8 AM:\n";
    echo $intervals['daily'] . " " . $cronCommand . "\n\n";
    
    echo "How to add this to your crontab:\n";
    echo "1. Type 'crontab -e' to edit your crontab\n";
    echo "2. Add one of the above lines\n";
    echo "3. Save and exit\n\n";
    
    echo "You can also manually run the weather check with:\n";
    echo $phpPath . " " . $scriptPath . "\n";
} else {
    // Web output
    header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PureFarm Weather Notification Cron Setup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 20px;
        }
        pre {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
            overflow-x: auto;
        }
        .step {
            display: flex;
            margin-bottom: 15px;
        }
        .step-number {
            flex: 0 0 30px;
            height: 30px;
            background-color: #20c997;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        .step-content {
            flex: 1;
        }
        .command-copy {
            cursor: pointer;
            color: #0d6efd;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4"><i class="fas fa-clock me-2"></i> PureFarm Weather Notification Cron Setup</h1>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> This utility helps you set up automatic weather notifications that will run at regular intervals.
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-code me-2"></i> Weather Notification Script</h5>
            </div>
            <div class="card-body">
                <p>The weather notification script is located at:</p>
                <pre><?php echo htmlspecialchars($scriptPath); ?></pre>
                <p>This script checks weather conditions against your configured thresholds and sends notifications if needed.</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i> Available Cron Schedules</h5>
            </div>
            <div class="card-body">
                <p>Choose how often you want to run weather checks:</p>
                
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Frequency</th>
                                <th>Cron Expression</th>
                                <th>Command</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Every 15 minutes</td>
                                <td><code><?php echo $intervals['every_15_minutes']; ?></code></td>
                                <td>
                                    <pre class="mb-0"><?php echo htmlspecialchars($intervals['every_15_minutes'] . ' ' . $cronCommand); ?></pre>
                                    <i class="fas fa-copy command-copy" title="Copy to clipboard" data-command="<?php echo htmlspecialchars($intervals['every_15_minutes'] . ' ' . $cronCommand); ?>"></i>
                                </td>
                            </tr>
                            <tr>
                                <td>Every 30 minutes</td>
                                <td><code><?php echo $intervals['every_30_minutes']; ?></code></td>
                                <td>
                                    <pre class="mb-0"><?php echo htmlspecialchars($intervals['every_30_minutes'] . ' ' . $cronCommand); ?></pre>
                                    <i class="fas fa-copy command-copy" title="Copy to clipboard" data-command="<?php echo htmlspecialchars($intervals['every_30_minutes'] . ' ' . $cronCommand); ?>"></i>
                                </td>
                            </tr>
                            <tr>
                                <td>Every hour</td>
                                <td><code><?php echo $intervals['hourly']; ?></code></td>
                                <td>
                                    <pre class="mb-0"><?php echo htmlspecialchars($intervals['hourly'] . ' ' . $cronCommand); ?></pre>
                                    <i class="fas fa-copy command-copy" title="Copy to clipboard" data-command="<?php echo htmlspecialchars($intervals['hourly'] . ' ' . $cronCommand); ?>"></i>
                                </td>
                            </tr>
                            <tr>
                                <td>Twice daily</td>
                                <td><code><?php echo $intervals['twice_daily']; ?></code></td>
                                <td>
                                    <pre class="mb-0"><?php echo htmlspecialchars($intervals['twice_daily'] . ' ' . $cronCommand); ?></pre>
                                    <i class="fas fa-copy command-copy" title="Copy to clipboard" data-command="<?php echo htmlspecialchars($intervals['twice_daily'] . ' ' . $cronCommand); ?>"></i>
                                </td>
                            </tr>
                            <tr>
                                <td>Once daily (8 AM)</td>
                                <td><code><?php echo $intervals['daily']; ?></code></td>
                                <td>
                                    <pre class="mb-0"><?php echo htmlspecialchars($intervals['daily'] . ' ' . $cronCommand); ?></pre>
                                    <i class="fas fa-copy command-copy" title="Copy to clipboard" data-command="<?php echo htmlspecialchars($intervals['daily'] . ' ' . $cronCommand); ?>"></i>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-terminal me-2"></i> Setting Up the Cron Job</h5>
            </div>
            <div class="card-body">
                <p>Follow these steps to set up the cron job on your server:</p>
                
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <strong>Access your server via SSH</strong>
                        <p>Log in to your server using SSH.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <strong>Edit your crontab</strong>
                        <p>Run the following command to edit your crontab:</p>
                        <pre>crontab -e</pre>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <strong>Add the cron job</strong>
                        <p>Add one of the commands from the table above to your crontab.</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <strong>Save and exit</strong>
                        <p>Save the crontab and exit the editor.</p>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i> Note: Cron job setup requires server access and may not be available on all hosting providers. Contact your hosting provider if you need assistance.
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i> Manual Execution</h5>
            </div>
            <div class="card-body">
                <p>You can manually run the weather notification script with this command:</p>
                <pre><?php echo htmlspecialchars($phpPath . ' ' . $scriptPath); ?></pre>
                <i class="fas fa-copy command-copy" title="Copy to clipboard" data-command="<?php echo htmlspecialchars($phpPath . ' ' . $scriptPath); ?>"></i>
                
                <p class="mt-3">This is useful for testing or running the script on demand.</p>
                
                <div class="mt-4">
                    <a href="weather_settings.php" class="btn btn-primary">
                        <i class="fas fa-cog me-2"></i> Back to Weather Settings
                    </a>
                    <a href="notifications.php" class="btn btn-info ms-2">
                        <i class="fas fa-bell me-2"></i> View Notifications
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Clipboard functionality for command copying
        document.addEventListener('DOMContentLoaded', function() {
            const copyButtons = document.querySelectorAll('.command-copy');
            
            copyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const command = this.getAttribute('data-command');
                    navigator.clipboard.writeText(command).then(() => {
                        // Change icon temporarily to show success
                        const originalClass = this.className;
                        this.className = 'fas fa-check text-success';
                        
                        setTimeout(() => {
                            this.className = originalClass;
                        }, 2000);
                    });
                });
            });
        });
    </script>
</body>
</html>
<?php
}
?>