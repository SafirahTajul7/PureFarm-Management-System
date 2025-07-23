<?php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Date filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month

// Debug
error_log("Start date: " . $start_date . ", End date: " . $end_date);

// Fetch summary financial data
try {
    // Get total expenses - Fixed column name to match actual database structure
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total_expenses
        FROM crop_expenses
        WHERE date BETWEEN :start_date AND :end_date
    ");
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $total_expenses = $stmt->fetch()['total_expenses'] ?? 0;
    error_log("Total expenses: " . $total_expenses);

    // Get total revenue - Fixed column name to match actual database structure
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total_revenue
        FROM crop_revenue
        WHERE date BETWEEN :start_date AND :end_date
    ");
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $total_revenue = $stmt->fetch()['total_revenue'] ?? 0;
    error_log("Total revenue: " . $total_revenue);

    // Calculate profit/loss
    $profit_loss = $total_revenue - $total_expenses;
    
    // Get expenses by category - Fixed to use direct category column
    $stmt = $pdo->prepare("
        SELECT category, SUM(amount) as category_total
        FROM crop_expenses
        WHERE date BETWEEN :start_date AND :end_date
        GROUP BY category
        ORDER BY category_total DESC
    ");
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $expenses_by_category = $stmt->fetchAll();
    error_log("Expenses by category count: " . count($expenses_by_category));
    
    // Get expenses by crop - Fixed to use crop_id directly
    $stmt = $pdo->prepare("
        SELECT c.crop_name, SUM(ce.amount) as crop_expenses
        FROM crop_expenses ce
        JOIN crops c ON ce.crop_id = c.id
        WHERE ce.date BETWEEN :start_date AND :end_date
        GROUP BY c.crop_name
        ORDER BY crop_expenses DESC
    ");
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $expenses_by_crop = $stmt->fetchAll();
    error_log("Expenses by crop count: " . count($expenses_by_crop));
    
    // Get revenue by crop - Fixed to use crop_id directly
    $stmt = $pdo->prepare("
        SELECT c.crop_name, SUM(cr.amount) as crop_revenue
        FROM crop_revenue cr
        JOIN crops c ON cr.crop_id = c.id
        WHERE cr.date BETWEEN :start_date AND :end_date
        GROUP BY c.crop_name
        ORDER BY crop_revenue DESC
    ");
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $revenue_by_crop = $stmt->fetchAll();
    error_log("Revenue by crop count: " . count($revenue_by_crop));
    
    // Get recent expenses - Fixed column names and removed category_id reference
    $stmt = $pdo->prepare("
        SELECT ce.*, c.crop_name
        FROM crop_expenses ce
        JOIN crops c ON ce.crop_id = c.id
        WHERE ce.date BETWEEN :start_date AND :end_date
        ORDER BY ce.date DESC
        LIMIT 10
    ");
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $recent_expenses = $stmt->fetchAll();
    error_log("Recent expenses count: " . count($recent_expenses));
    
    // Get recent revenue - Fixed column names
    $stmt = $pdo->prepare("
        SELECT cr.*, c.crop_name
        FROM crop_revenue cr
        JOIN crops c ON cr.crop_id = c.id
        WHERE cr.date BETWEEN :start_date AND :end_date
        ORDER BY cr.date DESC
        LIMIT 10
    ");
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $recent_revenue = $stmt->fetchAll();
    error_log("Recent revenue count: " . count($recent_revenue));
    

} catch(PDOException $e) {
    error_log("Error fetching financial data: " . $e->getMessage());
    $_SESSION['error_message'] = "Error loading financial data. Please try again.";
    
    // Set default values in case of error
    $total_expenses = 0;
    $total_revenue = 0;
    $profit_loss = 0;
    $expenses_by_category = [];
    $expenses_by_crop = [];
    $revenue_by_crop = [];
    $recent_expenses = [];
    $recent_revenue = [];
}

$pageTitle = 'Financial Analysis';
include 'includes/header.php';
?>
<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-chart-line"></i> Financial Analysis</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="location.href='add_expense.php'">
                <i class="fas fa-plus"></i> Add Expense
            </button>
            <button class="btn btn-primary" onclick="location.href='add_revenue.php'">
                <i class="fas fa-plus"></i> Add Revenue
            </button>
            <button class="btn btn-secondary" onclick="location.href='financial_reports.php'">
                <i class="fas fa-file-export"></i> Export Reports
            </button>
            
            <button class="btn btn-secondary" onclick="location.href='crop_management.php'">
                <i class="fas fa-arrow-left"></i> Back to Crop Management
            </button>
        </div>
    </div>
    
    <!-- Date Range Filter -->
    <div class="filter-card">
        <form method="GET" action="" class="date-filter-form">
            <div class="filter-group">
                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" required>
            </div>
            <div class="filter-group">
                <label for="end_date">End Date:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Apply Filter</button>
        </form>
    </div>

    <!-- Financial Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon bg-green">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="summary-details">
                <h3>Total Revenue</h3>
                <p class="summary-count">$<?php echo number_format($total_revenue, 2); ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon bg-red">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="summary-details">
                <h3>Total Expenses</h3>
                <p class="summary-count">$<?php echo number_format($total_expenses, 2); ?></p>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-icon <?php echo $profit_loss >= 0 ? 'bg-blue' : 'bg-orange'; ?>">
                <i class="fas fa-calculator"></i>
            </div>
            <div class="summary-details">
                <h3>Net Profit/Loss</h3>
                <p class="summary-count <?php echo $profit_loss >= 0 ? 'text-success' : 'text-danger'; ?>">
                    $<?php echo number_format(abs($profit_loss), 2); ?>
                    <?php echo $profit_loss >= 0 ? 'Profit' : 'Loss'; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Main Financial Analysis Section -->
    <div class="content-row">
        <!-- Expense Categories Chart -->
        <div class="content-card half-width">
            <div class="content-card-header">
                <h3><i class="fas fa-chart-pie"></i> Expenses by Category</h3>
            </div>
            <div class="chart-container">
                <?php if (empty($expenses_by_category)) : ?>
                    <p class="text-center">No expense data available for the selected period.</p>
                <?php else : ?>
                    <canvas id="expenseCategoryChart"></canvas>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses_by_category as $category) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['category']); ?></td>
                                <td>$<?php echo number_format($category['category_total'], 2); ?></td>
                                <td>
                                    <?php 
                                    $percentage = ($total_expenses > 0) 
                                        ? ($category['category_total'] / $total_expenses) * 100 
                                        : 0;
                                    echo number_format($percentage, 1) . '%'; 
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($expenses_by_category)) : ?>
                            <tr>
                                <td colspan="3" class="text-center">No data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Profit/Loss by Crop -->
        <div class="content-card half-width">
            <div class="content-card-header">
                <h3><i class="fas fa-balance-scale"></i> Profit/Loss by Crop</h3>
            </div>
            <div class="chart-container">
                <?php if (empty($expenses_by_crop) && empty($revenue_by_crop)) : ?>
                    <p class="text-center">No profit/loss data available for the selected period.</p>
                <?php else : ?>
                    <canvas id="cropProfitChart"></canvas>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Crop</th>
                            <th>Revenue</th>
                            <th>Expenses</th>
                            <th>Profit/Loss</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Combine crop data for profit/loss calculation
                        $crop_profit_data = [];
                        
                        // Add revenue data
                        foreach ($revenue_by_crop as $revenue) {
                            $crop_name = $revenue['crop_name'];
                            if (!isset($crop_profit_data[$crop_name])) {
                                $crop_profit_data[$crop_name] = [
                                    'revenue' => 0,
                                    'expenses' => 0
                                ];
                            }
                            $crop_profit_data[$crop_name]['revenue'] = $revenue['crop_revenue'];
                        }
                        
                        // Add expense data
                        foreach ($expenses_by_crop as $expense) {
                            $crop_name = $expense['crop_name'];
                            if (!isset($crop_profit_data[$crop_name])) {
                                $crop_profit_data[$crop_name] = [
                                    'revenue' => 0,
                                    'expenses' => 0
                                ];
                            }
                            $crop_profit_data[$crop_name]['expenses'] = $expense['crop_expenses'];
                        }
                        
                        // Calculate profit/loss for each crop
                        foreach ($crop_profit_data as $crop_name => $data) {
                            $profit_loss = $data['revenue'] - $data['expenses'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($crop_name); ?></td>
                                <td>$<?php echo number_format($data['revenue'], 2); ?></td>
                                <td>$<?php echo number_format($data['expenses'], 2); ?></td>
                                <td class="<?php echo $profit_loss >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    $<?php echo number_format(abs($profit_loss), 2); ?>
                                    <?php echo $profit_loss >= 0 ? 'Profit' : 'Loss'; ?>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if (empty($crop_profit_data)) : ?>
                            <tr>
                                <td colspan="4" class="text-center">No data available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="content-row">
        <!-- Recent Expenses -->
        <div class="content-card half-width">
            <div class="content-card-header">
                <h3><i class="fas fa-receipt"></i> Recent Expenses</h3>
                <a href="expenses.php" class="view-all">View All</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Crop</th>
                            <th>Category</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_expenses as $expense) : ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($expense['date'])); ?></td>
                                <td><?php echo htmlspecialchars($expense['crop_name']); ?></td>
                                <td><?php echo htmlspecialchars($expense['category']); ?></td>
                                <td>$<?php echo number_format($expense['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent_expenses)) : ?>
                            <tr>
                                <td colspan="4" class="text-center">No recent expenses found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Revenue -->
        <div class="content-card half-width">
            <!-- For Recent Revenue section, find: -->
            <div class="content-card-header">
                <h3><i class="fas fa-hand-holding-usd"></i> Recent Revenue</h3>
                <a href="revenue.php" class="view-all">View All</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Crop</th>
                            <th>Description</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_revenue as $revenue) : ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($revenue['date'])); ?></td>
                                <td><?php echo htmlspecialchars($revenue['crop_name']); ?></td>
                                <td><?php echo htmlspecialchars($revenue['description']); ?></td>
                                <td>$<?php echo number_format($revenue['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent_revenue)) : ?>
                            <tr>
                                <td colspan="4" class="text-center">No recent revenue found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

<script>
// Function to generate colors
function generateColors(numColors) {
    const colors = [
        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
        '#5a5c69', '#858796', '#f8f9fc', '#d1d3e2', '#6610f2'
    ];
    
    // If we need more colors than available, generate random ones
    if (numColors > colors.length) {
        for (let i = colors.length; i < numColors; i++) {
            const r = Math.floor(Math.random() * 255);
            const g = Math.floor(Math.random() * 255);
            const b = Math.floor(Math.random() * 255);
            colors.push(`rgb(${r}, ${g}, ${b})`);
        }
    }
    
    return colors.slice(0, numColors);
}

// Expense Category Chart
<?php if (!empty($expenses_by_category)) : ?>
document.addEventListener('DOMContentLoaded', function() {
    const categoryLabels = <?php echo json_encode(array_column($expenses_by_category, 'category')); ?>;
    const categoryData = <?php echo json_encode(array_column($expenses_by_category, 'category_total')); ?>;
    const backgroundColors = generateColors(categoryLabels.length);
    
    const ctx = document.getElementById('expenseCategoryChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: categoryData,
                backgroundColor: backgroundColors,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
<?php endif; ?>

// Crop Profit/Loss Chart
<?php if (!empty($crop_profit_data)) : ?>
document.addEventListener('DOMContentLoaded', function() {
    const cropLabels = <?php echo json_encode(array_keys($crop_profit_data)); ?>;
    const revenueData = [];
    const expenseData = [];
    
    <?php foreach ($crop_profit_data as $crop_name => $data) : ?>
    revenueData.push(<?php echo $data['revenue']; ?>);
    expenseData.push(<?php echo $data['expenses']; ?>);
    <?php endforeach; ?>
    
    const ctx = document.getElementById('cropProfitChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: cropLabels,
            datasets: [{
                label: 'Revenue',
                data: revenueData,
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }, {
                label: 'Expenses',
                data: expenseData,
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Amount ($)'
                    }
                }
            }
        }
    });
});
<?php endif; ?>
</script>

<style>
.date-filter-form {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 15px;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-card {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

.content-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.half-width {
    flex: 1 1 calc(50% - 20px);
    min-width: 400px;
}

.chart-container {
    height: 300px;
    position: relative;
    margin-bottom: 20px;
    padding: 10px;
}

.text-success {
    color: #1cc88a;
}

.text-danger {
    color: #e74a3b;
}

.view-all {
    font-size: 14px;
    color: #4e73df;
    text-decoration: none;
}

.view-all:hover {
    text-decoration: underline;
}

@media (max-width: 992px) {
    .content-row {
        flex-direction: column;
    }
    
    .half-width {
        flex: 1 1 100%;
    }
    
    .date-filter-form {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<?php include 'includes/footer.php'; ?>