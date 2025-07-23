<?php
require_once 'includes/auth.php';
auth()->checkAdmin();
require_once 'includes/db.php';

// Set default date range if not provided
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// Fetch report data based on the selected report type
try {
    $report_data = [];
    
    switch($report_type) {
        case 'summary':
            // Get total expenses - Fixed column name
            $stmt = $pdo->prepare("
                SELECT SUM(amount) as total_expenses
                FROM crop_expenses
                WHERE date BETWEEN :start_date AND :end_date
            ");
            $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
            $expenses = $stmt->fetch()['total_expenses'] ?? 0;
            
            // Get total revenue - Fixed column name
            $stmt = $pdo->prepare("
                SELECT SUM(amount) as total_revenue
                FROM crop_revenue
                WHERE date BETWEEN :start_date AND :end_date
            ");
            $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
            $revenue = $stmt->fetch()['total_revenue'] ?? 0;
            
            // Calculate net profit/loss
            $net = $revenue - $expenses;
            
            $report_data = [
                'total_expenses' => $expenses,
                'total_revenue' => $revenue,
                'net_profit_loss' => $net
            ];
            break;
            
        case 'expenses_by_category':
            // Get expenses grouped by category - Using direct category column
            $stmt = $pdo->prepare("
                SELECT category, SUM(amount) as total_amount
                FROM crop_expenses
                WHERE date BETWEEN :start_date AND :end_date
                GROUP BY category
                ORDER BY total_amount DESC
            ");
            $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
            $report_data = $stmt->fetchAll();
            break;
            
        case 'expenses_by_crop':
            // Get expenses grouped by crop
            $stmt = $pdo->prepare("
                SELECT c.crop_name, SUM(ce.amount) as total_amount
                FROM crop_expenses ce
                JOIN crops c ON ce.crop_id = c.id
                WHERE ce.date BETWEEN :start_date AND :end_date
                GROUP BY c.crop_name
                ORDER BY total_amount DESC
            ");
            $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
            $report_data = $stmt->fetchAll();
            break;
            
        case 'revenue_by_crop':
            // Get revenue grouped by crop
            $stmt = $pdo->prepare("
                SELECT c.crop_name, SUM(cr.amount) as total_amount
                FROM crop_revenue cr
                JOIN crops c ON cr.crop_id = c.id
                WHERE cr.date BETWEEN :start_date AND :end_date
                GROUP BY c.crop_name
                ORDER BY total_amount DESC
            ");
            $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
            $report_data = $stmt->fetchAll();
            break;
            
        case 'detailed_expenses':
            // Get detailed expense transactions - Fixed to use direct category
            $stmt = $pdo->prepare("
                SELECT ce.date, c.crop_name, ce.category, ce.amount, ce.description
                FROM crop_expenses ce
                JOIN crops c ON ce.crop_id = c.id
                WHERE ce.date BETWEEN :start_date AND :end_date
                ORDER BY ce.date DESC
            ");
            $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
            $report_data = $stmt->fetchAll();
            break;
            
        case 'detailed_revenue':
            // Get detailed revenue transactions
            $stmt = $pdo->prepare("
                SELECT cr.date, c.crop_name, cr.amount, cr.description
                FROM crop_revenue cr
                JOIN crops c ON cr.crop_id = c.id
                WHERE cr.date BETWEEN :start_date AND :end_date
                ORDER BY cr.date DESC
            ");
            $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
            $report_data = $stmt->fetchAll();
            break;
            
        case 'profit_by_crop':
            // Get profit/loss by crop
            $stmt = $pdo->prepare("
                SELECT c.crop_name,
                    COALESCE(SUM(CASE WHEN ce.id IS NOT NULL THEN ce.amount ELSE 0 END), 0) as expenses,
                    COALESCE(SUM(CASE WHEN cr.id IS NOT NULL THEN cr.amount ELSE 0 END), 0) as revenue,
                    COALESCE(SUM(CASE WHEN cr.id IS NOT NULL THEN cr.amount ELSE 0 END), 0) - 
                    COALESCE(SUM(CASE WHEN ce.id IS NOT NULL THEN ce.amount ELSE 0 END), 0) as profit_loss
                FROM crops c
                LEFT JOIN crop_expenses ce ON c.id = ce.crop_id AND ce.date BETWEEN :start_date AND :end_date
                LEFT JOIN crop_revenue cr ON c.id = cr.crop_id AND cr.date BETWEEN :start_date AND :end_date
                GROUP BY c.crop_name
                HAVING expenses > 0 OR revenue > 0
                ORDER BY profit_loss DESC
            ");
            $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
            $report_data = $stmt->fetchAll();
            break;
    }
    
} catch(PDOException $e) {
    error_log("Error generating financial report: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error generating financial report. Please try again.';
    $report_data = [];
}

$pageTitle = 'Financial Reports';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-file-invoice-dollar"></i> Financial Reports</h2>
        <div class="action-buttons">
            <button class="btn btn-primary" id="printReport">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button class="btn btn-primary" id="exportCSV">
                <i class="fas fa-file-csv"></i> Export to CSV
            </button>
            <button class="btn btn-secondary" onclick="location.href='financial_analysis.php'">
                <i class="fas fa-arrow-left"></i> Back to Analysis
            </button>
        </div>
    </div>
    
    <!-- Report Configuration -->
    <div class="content-card">
        <div class="content-card-header">
            <h3><i class="fas fa-cog"></i> Report Configuration</h3>
        </div>
        <div class="filter-container">
            <form method="GET" action="" id="reportForm">
                <div class="filter-group">
                    <label for="report_type">Report Type:</label>
                    <select id="report_type" name="report_type" onchange="this.form.submit()">
                        <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Financial Summary</option>
                        <option value="expenses_by_category" <?php echo $report_type === 'expenses_by_category' ? 'selected' : ''; ?>>Expenses by Category</option>
                        <option value="expenses_by_crop" <?php echo $report_type === 'expenses_by_crop' ? 'selected' : ''; ?>>Expenses by Crop</option>
                        <option value="revenue_by_crop" <?php echo $report_type === 'revenue_by_crop' ? 'selected' : ''; ?>>Revenue by Crop</option>
                        <option value="profit_by_crop" <?php echo $report_type === 'profit_by_crop' ? 'selected' : ''; ?>>Profit/Loss by Crop</option>
                        <option value="detailed_expenses" <?php echo $report_type === 'detailed_expenses' ? 'selected' : ''; ?>>Detailed Expenses</option>
                        <option value="detailed_revenue" <?php echo $report_type === 'detailed_revenue' ? 'selected' : ''; ?>>Detailed Revenue</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" onchange="this.form.submit()">
                </div>
                
                <div class="filter-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" onchange="this.form.submit()">
                </div>
            </form>
        </div>
    </div>
    
    <!-- Report Results -->
    <div class="content-card" id="reportContent">
        <div class="content-card-header">
            <h3>
                <i class="fas fa-chart-pie"></i> 
                <?php
                $report_titles = [
                    'summary' => 'Financial Summary',
                    'expenses_by_category' => 'Expenses by Category',
                    'expenses_by_crop' => 'Expenses by Crop',
                    'revenue_by_crop' => 'Revenue by Crop',
                    'detailed_expenses' => 'Detailed Expense Transactions',
                    'detailed_revenue' => 'Detailed Revenue Transactions',
                    'profit_by_crop' => 'Profit/Loss by Crop'
                ];
                echo $report_titles[$report_type] ?? 'Report';
                ?>
            </h3>
            <div class="report-date-range">
                <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>
            </div>
        </div>
        
        <div class="report-content">
            <?php if (empty($report_data) && $report_type !== 'summary'): ?>
                <p class="text-center">No data available for the selected report type and date range.</p>
            <?php else: ?>
                <?php switch($report_type): 
                    case 'summary': ?>
                        <div class="summary-stats">
                            <div class="stat-card">
                                <div class="stat-title">Total Revenue</div>
                                <div class="stat-value">$<?php echo number_format($report_data['total_revenue'] ?? 0, 2); ?></div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-title">Total Expenses</div>
                                <div class="stat-value">$<?php echo number_format($report_data['total_expenses'] ?? 0, 2); ?></div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-title">Net Profit/Loss</div>
                                <div class="stat-value <?php echo ($report_data['net_profit_loss'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                                    $<?php echo number_format(abs($report_data['net_profit_loss'] ?? 0), 2); ?>
                                    <?php echo ($report_data['net_profit_loss'] ?? 0) >= 0 ? 'Profit' : 'Loss'; ?>
                                </div>
                            </div>
                        </div>
                        <?php break; ?>
                        
                    <?php case 'expenses_by_category': 
                    case 'expenses_by_crop': 
                    case 'revenue_by_crop': ?>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th><?php echo $report_type === 'expenses_by_category' ? 'Category' : 'Crop'; ?></th>
                                    <th>Amount</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = array_sum(array_column($report_data, 'total_amount'));
                                foreach ($report_data as $row): 
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row[$report_type === 'expenses_by_category' ? 'category' : 'crop_name']); ?></td>
                                        <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                                        <td>
                                            <?php 
                                            $percentage = ($total > 0) ? ($row['total_amount'] / $total) * 100 : 0;
                                            echo number_format($percentage, 1) . '%'; 
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td><strong>Total</strong></td>
                                    <td><strong>$<?php echo number_format($total, 2); ?></strong></td>
                                    <td><strong>100.0%</strong></td>
                                </tr>
                            </tbody>
                        </table>
                        <?php break; ?>
                        
                    <?php case 'detailed_expenses': ?>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Crop</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = 0;
                                foreach ($report_data as $row): 
                                    $total += $row['amount'];
                                ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['crop_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                                        <td><?php echo htmlspecialchars($row['description'] ?: 'N/A'); ?></td>
                                        <td>$<?php echo number_format($row['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="4"><strong>Total</strong></td>
                                    <td><strong>$<?php echo number_format($total, 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                        <?php break; ?>
                        
                    <?php case 'detailed_revenue': ?>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Crop</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = 0;
                                foreach ($report_data as $row): 
                                    $total += $row['amount'];
                                ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['crop_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['description'] ?: 'N/A'); ?></td>
                                        <td>$<?php echo number_format($row['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="3"><strong>Total</strong></td>
                                    <td><strong>$<?php echo number_format($total, 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                        <?php break; ?>
                        
                    <?php case 'profit_by_crop': ?>
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Crop</th>
                                    <th>Revenue</th>
                                    <th>Expenses</th>
                                    <th>Profit/Loss</th>
                                    <th>Margin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_revenue = 0;
                                $total_expenses = 0;
                                $total_profit = 0;
                                
                                foreach ($report_data as $row): 
                                    $total_revenue += $row['revenue'];
                                    $total_expenses += $row['expenses'];
                                    $total_profit += $row['profit_loss'];
                                    
                                    $margin = ($row['revenue'] > 0) ? ($row['profit_loss'] / $row['revenue']) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['crop_name']); ?></td>
                                        <td>$<?php echo number_format($row['revenue'], 2); ?></td>
                                        <td>$<?php echo number_format($row['expenses'], 2); ?></td>
                                        <td class="<?php echo $row['profit_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                                            $<?php echo number_format(abs($row['profit_loss']), 2); ?>
                                            <?php echo $row['profit_loss'] >= 0 ? 'Profit' : 'Loss'; ?>
                                        </td>
                                        <td class="<?php echo $margin >= 0 ? 'positive' : 'negative'; ?>">
                                            <?php echo number_format($margin, 1); ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td><strong>Total</strong></td>
                                    <td><strong>$<?php echo number_format($total_revenue, 2); ?></strong></td>
                                    <td><strong>$<?php echo number_format($total_expenses, 2); ?></strong></td>
                                    <td class="<?php echo $total_profit >= 0 ? 'positive' : 'negative'; ?>">
                                        <strong>$<?php echo number_format(abs($total_profit), 2); ?>
                                        <?php echo $total_profit >= 0 ? 'Profit' : 'Loss'; ?></strong>
                                    </td>
                                    <td class="<?php echo $total_profit >= 0 ? 'positive' : 'negative'; ?>">
                                        <strong>
                                            <?php 
                                            $total_margin = ($total_revenue > 0) ? ($total_profit / $total_revenue) * 100 : 0;
                                            echo number_format($total_margin, 1); 
                                            ?>%
                                        </strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <?php break; ?>
                <?php endswitch; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
// Print Report
document.getElementById('printReport').addEventListener('click', function() {
    const reportContent = document.getElementById('reportContent').innerHTML;
    const reportTitle = document.querySelector('.content-card-header h3').textContent;
    const dateRange = document.querySelector('.report-date-range').textContent;
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    
    // Setup the print content
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Financial Report - ${reportTitle}</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    color: #333;
                }
                h1 {
                    text-align: center;
                    margin-bottom: 5px;
                }
                .subtitle {
                    text-align: center;
                    margin-bottom: 20px;
                    font-size: 16px;
                    color: #666;
                }
                .report-content {
                    margin-top: 20px;
                }
                .summary-stats {
                    display: flex;
                    justify-content: space-around;
                    margin-bottom: 20px;
                }
                .stat-card {
                    padding: 15px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    text-align: center;
                    min-width: 200px;
                }
                .stat-title {
                    font-size: 14px;
                    margin-bottom: 5px;
                }
                .stat-value {
                    font-size: 24px;
                    font-weight: bold;
                }
                .positive {
                    color: #1cc88a;
                }
                .negative {
                    color: #e74a3b;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                th, td {
                    padding: 10px;
                    border: 1px solid #ddd;
                    text-align: left;
                }
                th {
                    background-color: #f8f9fc;
                }
                .total-row {
                    background-color: #f8f9fc;
                }
                .footer {
                    text-align: center;
                    font-size: 12px;
                    margin-top: 30px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <h1>Financial Report - ${reportTitle}</h1>
            <div class="subtitle">${dateRange}</div>
            <div class="report-content">
                ${reportContent}
            </div>
            <div class="footer">
                Generated on ${new Date().toLocaleDateString()} by PureFarm Management System
            </div>
        </body>
        </html>
    `);
    
    // Trigger the print dialog
    printWindow.document.close();
    printWindow.onload = function() {
        printWindow.print();
        // printWindow.close();
    };
});

// Export to CSV
document.getElementById('exportCSV').addEventListener('click', function() {
    const reportType = document.getElementById('report_type').value;
    let csvContent = '';
    let filename = '';
    
    // Generate CSV content based on report type
    switch(reportType) {
        case 'summary':
            csvContent = 'Category,Amount\n';
            
            // Fix the querySelector syntax and string concatenation
            const revenueValue = document.querySelector('.summary-stats .stat-card:nth-child(1) .stat-value');
            const expensesValue = document.querySelector('.summary-stats .stat-card:nth-child(2) .stat-value');
            const profitLossValue = document.querySelector('.summary-stats .stat-card:nth-child(3) .stat-value');
            
            if (revenueValue) {
                csvContent += 'Total Revenue,"' + revenueValue.textContent.trim() + '"\n';
            }
            
            if (expensesValue) {
                csvContent += 'Total Expenses,"' + expensesValue.textContent.trim() + '"\n';
            }
            
            if (profitLossValue) {
                csvContent += 'Net Profit/Loss,"' + profitLossValue.textContent.trim() + '"\n';
            }
            
            filename = 'financial_summary.csv';
            break;
            
        case 'expenses_by_category':
        case 'expenses_by_crop':
        case 'revenue_by_crop':
            // Determine CSV header based on report type
            if (reportType === 'expenses_by_category') {
                csvContent = 'Category,Amount,Percentage\n';
                filename = 'expenses_by_category.csv';
            } else {
                csvContent = 'Crop,Amount,Percentage\n';
                filename = reportType + '.csv';
            }
            
            // Extract data from table
            const table = document.querySelector('.report-table');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                if (!row.classList.contains('total-row')) {
                    const cols = row.querySelectorAll('td');
                    csvContent += '"' + cols[0].textContent.trim() + '",';
                    csvContent += '"' + cols[1].textContent.trim() + '",';
                    csvContent += '"' + cols[2].textContent.trim() + '"\n';
                }
            });
            
            // Add total row
            const totalRow = table.querySelector('.total-row');
            if (totalRow) {
                const totalCols = totalRow.querySelectorAll('td');
                csvContent += '"Total",';
                csvContent += '"' + totalCols[1].textContent.trim() + '",';
                csvContent += '"' + totalCols[2].textContent.trim() + '"\n';
            }
            break;
            
        case 'detailed_expenses':
            csvContent = 'Date,Crop,Category,Description,Amount\n';
            filename = 'detailed_expenses.csv';
            
            const expensesTable = document.querySelector('.report-table');
            const expenseRows = expensesTable.querySelectorAll('tbody tr');
            
            expenseRows.forEach(row => {
                if (!row.classList.contains('total-row')) {
                    const cols = row.querySelectorAll('td');
                    csvContent += '"' + cols[0].textContent.trim() + '",';
                    csvContent += '"' + cols[1].textContent.trim() + '",';
                    csvContent += '"' + cols[2].textContent.trim() + '",';
                    csvContent += '"' + cols[3].textContent.trim() + '",';
                    csvContent += '"' + cols[4].textContent.trim() + '"\n';
                }
            });
            
            // Add total row
            const expenseTotalRow = expensesTable.querySelector('.total-row');
            if (expenseTotalRow) {
                csvContent += '"Total","","","",';
                csvContent += '"' + expenseTotalRow.querySelector('td:last-child').textContent.trim() + '"\n';
            }
            break;
            
        case 'detailed_revenue':
            csvContent = 'Date,Crop,Description,Amount\n';
            filename = 'detailed_revenue.csv';
            
            const revenueTable = document.querySelector('.report-table');
            const revenueRows = revenueTable.querySelectorAll('tbody tr');
            
            revenueRows.forEach(row => {
                if (!row.classList.contains('total-row')) {
                    const cols = row.querySelectorAll('td');
                    csvContent += '"' + cols[0].textContent.trim() + '",';
                    csvContent += '"' + cols[1].textContent.trim() + '",';
                    csvContent += '"' + cols[2].textContent.trim() + '",';
                    csvContent += '"' + cols[3].textContent.trim() + '"\n';
                }
            });
            
            // Add total row
            const revenueTotalRow = revenueTable.querySelector('.total-row');
            if (revenueTotalRow) {
                csvContent += '"Total","","",';
                csvContent += '"' + revenueTotalRow.querySelector('td:last-child').textContent.trim() + '"\n';
            }
            break;
            
        case 'profit_by_crop':
            csvContent = 'Crop,Revenue,Expenses,Profit/Loss,Margin\n';
            filename = 'profit_by_crop.csv';
            
            const profitTable = document.querySelector('.report-table');
            const profitRows = profitTable.querySelectorAll('tbody tr');
            
            profitRows.forEach(row => {
                if (!row.classList.contains('total-row')) {
                    const cols = row.querySelectorAll('td');
                    csvContent += '"' + cols[0].textContent.trim() + '",';
                    csvContent += '"' + cols[1].textContent.trim() + '",';
                    csvContent += '"' + cols[2].textContent.trim() + '",';
                    csvContent += '"' + cols[3].textContent.trim() + '",';
                    csvContent += '"' + cols[4].textContent.trim() + '"\n';
                }
            });
            
            // Add total row
            const profitTotalRow = profitTable.querySelector('.total-row');
            if (profitTotalRow) {
                const totalCols = profitTotalRow.querySelectorAll('td');
                csvContent += '"Total",';
                csvContent += '"' + totalCols[1].textContent.trim() + '",';
                csvContent += '"' + totalCols[2].textContent.trim() + '",';
                csvContent += '"' + totalCols[3].textContent.trim() + '",';
                csvContent += '"' + totalCols[4].textContent.trim() + '"\n';
            }
            break;
    }
    
    // Create download link
    const downloadLink = document.createElement('a');
    downloadLink.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvContent);
    downloadLink.download = filename;
    
    // Append to the document, trigger click, and remove
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
});
</script>

<style>
/* Financial Reports Styles */
.content-card {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

.content-card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #f1f1f1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.content-card-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.report-date-range {
    font-size: 14px;
    color: #666;
}

.filter-container {
    padding: 15px 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-group label {
    font-weight: 500;
    margin-bottom: 0;
}

.filter-group select,
.filter-group input {
    min-width: 200px;
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.report-content {
    padding: 20px;
}

/* Summary Stats */
.summary-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: space-between;
    margin-bottom: 20px;
}

.stat-card {
    flex: 1;
    min-width: 200px;
    background-color: #f8f9fc;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    text-align: center;
}

.stat-title {
    font-size: 16px;
    color: #666;
    margin-bottom: 10px;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
}

.positive {
    color: #1cc88a;
}

.negative {
    color: #e74a3b;
}

/* Report Tables */
.report-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.report-table th,
.report-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.report-table th {
    background-color: #f8f9fc;
    font-weight: 600;
    color: #333;
}

.report-table tr:hover {
    background-color: #f9f9f9;
}

.total-row {
    background-color: #f8f9fc;
    font-weight: 500;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .summary-stats {
        flex-direction: column;
    }
    
    .stat-card {
        width: 100%;
    }
    
    .filter-container {
        flex-direction: column;
    }
    
    .report-table {
        display: block;
        overflow-x: auto;
    }
}
</style>

<?php include 'includes/footer.php'; ?>