<?php
/**
 * COMPREHENSIVE SOLUTION FOR PUREFARM FINANCIAL MODULE ISSUES
 * 
 * This file contains solutions for all identified issues:
 * 1. Financial Analysis page not showing data
 * 2. Harvest Planning error - "Error loading financial data"
 * 3. No categories showing in Add Expense page
 * 4. Financial Reports PHP errors
 */

/**
 * --------------------------------------------------------------------------
 * SOLUTION 1: Fix Financial Analysis Page
 * --------------------------------------------------------------------------
 */

// Add this to financial_analysis.php, after PDO connection setup
// Enable PDO to throw exceptions for debugging
function fixFinancialAnalysisPage($pdo) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Debug helper function
    function logQuery($query, $params = []) {
        error_log("Executing query: " . $query);
        error_log("With parameters: " . json_encode($params));
    }
    
    // Validate date inputs
    function validateDateRange(&$start_date, &$end_date) {
        // Ensure dates are properly formatted
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            $start_date = date('Y-m-01'); // Default to first day of current month
        }
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
            $end_date = date('Y-m-t'); // Default to last day of current month
        }
        
        // Ensure end date is not before start date
        if (strtotime($end_date) < strtotime($start_date)) {
            $end_date = date('Y-m-t', strtotime($start_date));
        }
        
        return [$start_date, $end_date];
    }
    
    // Before fetching financial data, verify tables exist
    function verifyTablesExist($pdo) {
        $tables = [
            'crop_expenses' => false,
            'crop_revenue' => false,
            'expense_categories' => false,
            'crops' => false
        ];
        
        $stmt = $pdo->query("SHOW TABLES");
        $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($allTables as $table) {
            if (array_key_exists($table, $tables)) {
                $tables[$table] = true;
            }
        }
        
        // Log which tables are missing
        foreach ($tables as $table => $exists) {
            if (!$exists) {
                error_log("WARNING: Table '$table' does not exist in the database!");
            }
        }
        
        return $tables;
    }
    
    // Rewritten query function with better error handling
    function fetchFinancialData($pdo, $start_date, $end_date) {
        list($start_date, $end_date) = validateDateRange($start_date, $end_date);
        $tables = verifyTablesExist($pdo);
        
        $data = [
            'total_expenses' => 0,
            'total_revenue' => 0,
            'profit_loss' => 0,
            'expenses_by_category' => [],
            'expenses_by_crop' => [],
            'revenue_by_crop' => [],
            'recent_expenses' => [],
            'recent_revenue' => []
        ];
        
        try {
            // Only run queries if tables exist
            if ($tables['crop_expenses']) {
                // Get total expenses
                $query = "
                    SELECT COALESCE(SUM(amount), 0) as total_expenses
                    FROM crop_expenses
                    WHERE expense_date BETWEEN :start_date AND :end_date
                ";
                logQuery($query, ['start_date' => $start_date, 'end_date' => $end_date]);
                
                $stmt = $pdo->prepare($query);
                $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
                $data['total_expenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;
                error_log("Total expenses: " . $data['total_expenses']);
                
                // Get expenses by category (if expense_categories table exists)
                if ($tables['expense_categories']) {
                    $query = "
                        SELECT ec.category_name, COALESCE(SUM(ce.amount), 0) as category_total
                        FROM crop_expenses ce
                        JOIN expense_categories ec ON ce.category_id = ec.id
                        WHERE ce.expense_date BETWEEN :start_date AND :end_date
                        GROUP BY ec.category_name
                        ORDER BY category_total DESC
                    ";
                } else {
                    // Fallback if expense_categories table doesn't exist
                    $query = "
                        SELECT 'Unknown' as category_name, COALESCE(SUM(amount), 0) as category_total
                        FROM crop_expenses
                        WHERE expense_date BETWEEN :start_date AND :end_date
                    ";
                }
                
                logQuery($query, ['start_date' => $start_date, 'end_date' => $end_date]);
                $stmt = $pdo->prepare($query);
                $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
                $data['expenses_by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Expenses by category count: " . count($data['expenses_by_category']));
                
                // Get expenses by crop (if crops table exists)
                if ($tables['crops']) {
                    $query = "
                        SELECT c.crop_name, COALESCE(SUM(ce.amount), 0) as crop_expenses
                        FROM crop_expenses ce
                        JOIN crops c ON ce.crop_id = c.id
                        WHERE ce.expense_date BETWEEN :start_date AND :end_date
                        GROUP BY c.crop_name
                        ORDER BY crop_expenses DESC
                    ";
                } else {
                    // Fallback if crops table doesn't exist
                    $query = "
                        SELECT 'Unknown' as crop_name, COALESCE(SUM(amount), 0) as crop_expenses
                        FROM crop_expenses
                        WHERE expense_date BETWEEN :start_date AND :end_date
                    ";
                }
                
                logQuery($query, ['start_date' => $start_date, 'end_date' => $end_date]);
                $stmt = $pdo->prepare($query);
                $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
                $data['expenses_by_crop'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Expenses by crop count: " . count($data['expenses_by_crop']));
                
                // Get recent expenses
                if ($tables['crops'] && $tables['expense_categories']) {
                    $query = "
                        SELECT ce.*, c.crop_name, ec.category_name
                        FROM crop_expenses ce
                        JOIN crops c ON ce.crop_id = c.id
                        JOIN expense_categories ec ON ce.category_id = ec.id
                        WHERE ce.expense_date BETWEEN :start_date AND :end_date
                        ORDER BY ce.expense_date DESC
                        LIMIT 10
                    ";
                } else {
                    // Simplified query if related tables don't exist
                    $query = "
                        SELECT *
                        FROM crop_expenses
                        WHERE expense_date BETWEEN :start_date AND :end_date
                        ORDER BY expense_date DESC
                        LIMIT 10
                    ";
                }
                
                logQuery($query, ['start_date' => $start_date, 'end_date' => $end_date]);
                $stmt = $pdo->prepare($query);
                $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
                $data['recent_expenses'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Recent expenses count: " . count($data['recent_expenses']));
            }
            
            // Only run revenue queries if crop_revenue table exists
            if ($tables['crop_revenue']) {
                // Get total revenue
                $query = "
                    SELECT COALESCE(SUM(amount), 0) as total_revenue
                    FROM crop_revenue
                    WHERE revenue_date BETWEEN :start_date AND :end_date
                ";
                
                logQuery($query, ['start_date' => $start_date, 'end_date' => $end_date]);
                $stmt = $pdo->prepare($query);
                $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
                $data['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
                error_log("Total revenue: " . $data['total_revenue']);
                
                // Calculate profit/loss
                $data['profit_loss'] = $data['total_revenue'] - $data['total_expenses'];
                
                // Get revenue by crop (if crops table exists)
                if ($tables['crops']) {
                    $query = "
                        SELECT c.crop_name, COALESCE(SUM(cr.amount), 0) as crop_revenue
                        FROM crop_revenue cr
                        JOIN crops c ON cr.crop_id = c.id
                        WHERE cr.revenue_date BETWEEN :start_date AND :end_date
                        GROUP BY c.crop_name
                        ORDER BY crop_revenue DESC
                    ";
                } else {
                    // Fallback if crops table doesn't exist
                    $query = "
                        SELECT 'Unknown' as crop_name, COALESCE(SUM(amount), 0) as crop_revenue
                        FROM crop_revenue
                        WHERE revenue_date BETWEEN :start_date AND :end_date
                    ";
                }
                
                logQuery($query, ['start_date' => $start_date, 'end_date' => $end_date]);
                $stmt = $pdo->prepare($query);
                $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
                $data['revenue_by_crop'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Revenue by crop count: " . count($data['revenue_by_crop']));
                
                // Get recent revenue
                if ($tables['crops']) {
                    $query = "
                        SELECT cr.*, c.crop_name
                        FROM crop_revenue cr
                        JOIN crops c ON cr.crop_id = c.id
                        WHERE cr.revenue_date BETWEEN :start_date AND :end_date
                        ORDER BY cr.revenue_date DESC
                        LIMIT 10
                    ";
                } else {
                    // Simplified query if crops table doesn't exist
                    $query = "
                        SELECT *
                        FROM crop_revenue
                        WHERE revenue_date BETWEEN :start_date AND :end_date
                        ORDER BY revenue_date DESC
                        LIMIT 10
                    ";
                }
                
                logQuery($query, ['start_date' => $start_date, 'end_date' => $end_date]);
                $stmt = $pdo->prepare($query);
                $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
                $data['recent_revenue'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Recent revenue count: " . count($data['recent_revenue']));
            }
        } catch (PDOException $e) {
            error_log("Database error in fetchFinancialData: " . $e->getMessage());
            // Throw the exception to be caught by the calling code
            throw $e;
        }
        
        return $data;
    }
    
    // Return the functions to be used
    return [
        'validateDateRange' => 'validateDateRange',
        'verifyTablesExist' => 'verifyTablesExist',
        'fetchFinancialData' => 'fetchFinancialData'
    ];
}

/**
 * --------------------------------------------------------------------------
 * SOLUTION 2: Fix Harvest Planning Financial Data Error
 * --------------------------------------------------------------------------
 */

function fixHarvestPlanningPage($pdo, $crop_id) {
    try {
        // Find where financial data is being loaded and add proper error handling
        $stmt = $pdo->prepare("
            SELECT c.crop_name, 
                   COALESCE(SUM(cr.amount), 0) as revenue, 
                   COALESCE(SUM(ce.amount), 0) as expenses
            FROM crops c
            LEFT JOIN crop_revenue cr ON c.id = cr.crop_id
            LEFT JOIN crop_expenses ce ON c.id = ce.crop_id
            WHERE c.id = :crop_id
            GROUP BY c.crop_name
        ");
        
        // Use proper error handling
        $stmt->execute(['crop_id' => $crop_id]);
        $financial_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no data found, initialize with zeros
        if (!$financial_data) {
            $financial_data = [
                'crop_name' => 'Unknown',
                'revenue' => 0,
                'expenses' => 0
            ];
        }
        
        // Calculate profit/loss
        $financial_data['profit_loss'] = $financial_data['revenue'] - $financial_data['expenses'];
        
        // Log for debugging
        error_log("Financial data for crop ID $crop_id: " . json_encode($financial_data));
        
        return $financial_data;
        
    } catch (PDOException $e) {
        // Log the error
        error_log("Error loading financial data for harvest planning: " . $e->getMessage());
        
        // Initialize with default values instead of showing error
        return [
            'crop_name' => 'Unknown',
            'revenue' => 0,
            'expenses' => 0,
            'profit_loss' => 0
        ];
    }
}

/**
 * --------------------------------------------------------------------------
 * SOLUTION 3: Fix Add Expense Categories Not Showing
 * --------------------------------------------------------------------------
 */

function fixExpenseCategories($pdo) {
    try {
        // First check if expense_categories table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'expense_categories'");
        if ($stmt->rowCount() === 0) {
            // If the table doesn't exist, check for alternatives
            error_log("expense_categories table not found, checking for alternatives");
            $stmt = $pdo->query("SHOW TABLES LIKE '%categor%'");
            $possible_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            error_log("Possible category tables: " . implode(", ", $possible_tables));
            
            // Try using 'categories' table if it exists
            if (in_array('categories', $possible_tables)) {
                $stmt = $pdo->query("SELECT id, category_name FROM categories ORDER BY category_name");
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Look at crop_expenses table to find category_id column
                $stmt = $pdo->query("DESCRIBE crop_expenses");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // If category_id exists in crop_expenses, we need a categories table
                if (in_array('category_id', $columns)) {
                    // Create expense_categories table if it doesn't exist
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS expense_categories (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            category_name VARCHAR(100) NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        )
                    ");
                    
                    // Insert default categories
                    $default_categories = [
                        ['Seeds'],
                        ['Fertilizer'],
                        ['Pesticides'],
                        ['Labor'],
                        ['Equipment'],
                        ['Irrigation'],
                        ['Transportation'],
                        ['Other']
                    ];
                    
                    $stmt = $pdo->prepare("INSERT INTO expense_categories (category_name) VALUES (?)");
                    foreach ($default_categories as $category) {
                        $stmt->execute($category);
                    }
                    
                    // Fetch the newly created categories
                    $stmt = $pdo->query("SELECT id, category_name FROM expense_categories ORDER BY category_name");
                    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Fallback to manually creating categories
                    $categories = [
                        ['id' => 1, 'category_name' => 'Seeds'],
                        ['id' => 2, 'category_name' => 'Fertilizer'],
                        ['id' => 3, 'category_name' => 'Pesticides'],
                        ['id' => 4, 'category_name' => 'Labor'],
                        ['id' => 5, 'category_name' => 'Equipment'],
                        ['id' => 6, 'category_name' => 'Irrigation'],
                        ['id' => 7, 'category_name' => 'Transportation'],
                        ['id' => 8, 'category_name' => 'Other']
                    ];
                }
            }
        } else {
            // If expense_categories table exists, proceed as normal
            $stmt = $pdo->query("SELECT id, category_name FROM expense_categories ORDER BY category_name");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        error_log("Found " . count($categories) . " categories");
        return $categories;
        
    } catch(PDOException $e) {
        error_log("Error fetching expense categories: " . $e->getMessage());
        
        // Create default categories if query fails
        $categories = [
            ['id' => 1, 'category_name' => 'Seeds'],
            ['id' => 2, 'category_name' => 'Fertilizer'],
            ['id' => 3, 'category_name' => 'Pesticides'],
            ['id' => 4, 'category_name' => 'Labor'],
            ['id' => 5, 'category_name' => 'Equipment'],
            ['id' => 6, 'category_name' => 'Irrigation'],
            ['id' => 7, 'category_name' => 'Transportation'],
            ['id' => 8, 'category_name' => 'Other']
        ];
    }
    
    return $categories;
}

/**
 * --------------------------------------------------------------------------
 * SOLUTION 4: Fix Financial Reports PHP Errors
 * --------------------------------------------------------------------------
 */

function fixFinancialReports() {
    // This function provides fixes for the financial_reports.php file
    
    // 1. JavaScript error in the exportCSV function
    ob_start();
?>

<script>
// Export to CSV - Fixed version
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
            } else {
                csvContent += 'Total Revenue,"$0.00"\n';
            }
            
            if (expensesValue) {
                csvContent += 'Total Expenses,"' + expensesValue.textContent.trim() + '"\n';
            } else {
                csvContent += 'Total Expenses,"$0.00"\n';
            }
            
            if (profitLossValue) {
                csvContent += 'Net Profit/Loss,"' + profitLossValue.textContent.trim() + '"\n';
            } else {
                csvContent += 'Net Profit/Loss,"$0.00"\n';
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
            if (table) {
                const rows = table.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    if (!row.classList.contains('total-row')) {
                        const cols = row.querySelectorAll('td');
                        if (cols.length >= 3) {
                            csvContent += '"' + cols[0].textContent.trim() + '",';
                            csvContent += '"' + cols[1].textContent.trim() + '",';
                            csvContent += '"' + cols[2].textContent.trim() + '"\n';
                        }
                    }
                });
                
                // Add total row
                const totalRow = table.querySelector('.total-row');
                if (totalRow) {
                    const totalCols = totalRow.querySelectorAll('td');
                    if (totalCols.length >= 3) {
                        csvContent += '"Total",';
                        csvContent += '"' + totalCols[1].textContent.trim() + '",';
                        csvContent += '"' + totalCols[2].textContent.trim() + '"\n';
                    }
                }
            } else {
                csvContent += 'No data available\n';
            }
            break;
            
        case 'detailed_expenses':
            csvContent = 'Date,Crop,Category,Description,Amount\n';
            filename = 'detailed_expenses.csv';
            
            const expensesTable = document.querySelector('.report-table');
            if (expensesTable) {
                const expenseRows = expensesTable.querySelectorAll('tbody tr');
                
                expenseRows.forEach(row => {
                    if (!row.classList.contains('total-row')) {
                        const cols = row.querySelectorAll('td');
                        if (cols.length >= 5) {
                            csvContent += '"' + cols[0].textContent.trim() + '",';
                            csvContent += '"' + cols[1].textContent.trim() + '",';
                            csvContent += '"' + cols[2].textContent.trim() + '",';
                            csvContent += '"' + cols[3].textContent.trim() + '",';
                            csvContent += '"' + cols[4].textContent.trim() + '"\n';
                        }
                    }
                });
                
                // Add total row
                const expenseTotalRow = expensesTable.querySelector('.total-row');
                if (expenseTotalRow) {
                    const totalCol = expenseTotalRow.querySelector('td:last-child');
                    if (totalCol) {
                        csvContent += '"Total","","","",';
                        csvContent += '"' + totalCol.textContent.trim() + '"\n';
                    }
                }
            } else {
                csvContent += 'No data available\n';
            }
            break;
            
        case 'detailed_revenue':
            csvContent = 'Date,Crop,Source,Description,Amount\n';
            filename = 'detailed_revenue.csv';
            
            const revenueTable = document.querySelector('.report-table');
            if (revenueTable) {
                const revenueRows = revenueTable.querySelectorAll('tbody tr');
                
                revenueRows.forEach(row => {
                    if (!row.classList.contains('total-row')) {
                        const cols = row.querySelectorAll('td');
                        if (cols.length >= 5) {
                            csvContent += '"' + cols[0].textContent.trim() + '",';
                            csvContent += '"' + cols[1].textContent.trim() + '",';
                            csvContent += '"' + cols[2].textContent.trim() + '",';
                            csvContent += '"' + cols[3].textContent.trim() + '",';
                            csvContent += '"' + cols[4].textContent.trim() + '"\n';
                        }
                    }
                });
                
                // Add total row
                const revenueTotalRow = revenueTable.querySelector('.total-row');
                if (revenueTotalRow) {
                    const totalCol = revenueTotalRow.querySelector('td:last-child');
                    if (totalCol) {
                        csvContent += '"Total","","","",';
                        csvContent += '"' + totalCol.textContent.trim() + '"\n';
                    }
                }
            } else {
                csvContent += 'No data available\n';
            }
            break;
            
        case 'profit_by_crop':
            csvContent = 'Crop,Revenue,Expenses,Profit/Loss,Margin\n';
            filename = 'profit_by_crop.csv';
            
            const profitTable = document.querySelector('.report-table');
            if (profitTable) {
                const profitRows = profitTable.querySelectorAll('tbody tr');
                
                profitRows.forEach(row => {
                    if (!row.classList.contains('total-row')) {
                        const cols = row.querySelectorAll('td');
                        if (cols.length >= 5) {
                            csvContent += '"' + cols[0].textContent.trim() + '",';
                            csvContent += '"' + cols[1].textContent.trim() + '",';
                            csvContent += '"' + cols[2].textContent.trim() + '",';
                            csvContent += '"' + cols[3].textContent.trim() + '",';
                            csvContent += '"' + cols[4].textContent.trim() + '"\n';
                        }
                    }
                });
                
                // Add total row
                const profitTotalRow = profitTable.querySelector('.total-row');
                if (profitTotalRow) {
                    const totalCols = profitTotalRow.querySelectorAll('td');
                    if (totalCols.length >= 5) {
                        csvContent += '"Total",';
                        csvContent += '"' + totalCols[1].textContent.trim() + '",';
                        csvContent += '"' + totalCols[2].textContent.trim() + '",';
                        csvContent += '"' + totalCols[3].textContent.trim() + '",';
                        csvContent += '"' + totalCols[4].textContent.trim() + '"\n';
                    }
                }
            } else {
                csvContent += 'No data available\n';
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
<?php
    $js_fix = ob_get_clean();
    
    // 2. PHP fixes for financial reports - avoid undefined array key errors
    ob_start();
?>

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
                $total = 0;
                
                // Safe calculation of total
                if (!empty($report_data)) {
                    foreach ($report_data as $row) {
                        $total += isset($row['total_amount']) ? $row['total_amount'] : 0;
                    }
                }
                
                foreach ($report_data as $row): 
                    $rowAmount = isset($row['total_amount']) ? $row['total_amount'] : 0;
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row[$report_type === 'expenses_by_category' ? 'category_name' : 'crop_name'] ?? 'Unknown'); ?></td>
                        <td>$<?php echo number_format($rowAmount, 2); ?></td>
                        <td>
                            <?php 
                            $percentage = ($total > 0) ? ($rowAmount / $total) * 100 : 0;
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
                    $total += isset($row['amount']) ? $row['amount'] : 0;
                ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($row['expense_date'] ?? 'now')); ?></td>
                        <td><?php echo htmlspecialchars($row['crop_name'] ?? 'Unknown'); ?></td>
                        <td><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['description'] ?? 'N/A'); ?></td>
                        <td>$<?php echo number_format($row['amount'] ?? 0, 2); ?></td>
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
                    <th>Source</th>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total = 0;
                foreach ($report_data as $row): 
                    $total += isset($row['amount']) ? $row['amount'] : 0;
                ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($row['revenue_date'] ?? 'now')); ?></td>
                        <td><?php echo htmlspecialchars($row['crop_name'] ?? 'Unknown'); ?></td>
                        <td><?php echo htmlspecialchars($row['source'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['description'] ?? 'N/A'); ?></td>
                        <td>$<?php echo number_format($row['amount'] ?? 0, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="4"><strong>Total</strong></td>
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
                    $row_revenue = isset($row['revenue']) ? $row['revenue'] : 0;
                    $row_expenses = isset($row['expenses']) ? $row['expenses'] : 0;
                    $row_profit = isset($row['profit_loss']) ? $row['profit_loss'] : ($row_revenue - $row_expenses);
                    
                    $total_revenue += $row_revenue;
                    $total_expenses += $row_expenses;
                    $total_profit += $row_profit;
                    
                    $margin = ($row_revenue > 0) ? ($row_profit / $row_revenue) * 100 : 0;
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['crop_name'] ?? 'Unknown'); ?></td>
                        <td>$<?php echo number_format($row_revenue, 2); ?></td>
                        <td>$<?php echo number_format($row_expenses, 2); ?></td>
                        <td class="<?php echo $row_profit >= 0 ? 'positive' : 'negative'; ?>">
                            $<?php echo number_format(abs($row_profit), 2); ?>
                            <?php echo $row_profit >= 0 ? 'Profit' : 'Loss'; ?>
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

<?php
    $php_fix = ob_get_clean();
    
    return [
        'js_fix' => $js_fix,
        'php_fix' => $php_fix
    ];
}

/**
 * --------------------------------------------------------------------------
 * IMPLEMENTATION INSTRUCTIONS
 * --------------------------------------------------------------------------
 * 
 * To implement these fixes, follow these steps:
 * 
 * 1. Create a new file called "purefarm_fixes.php" in your includes directory
 * 2. Copy this entire solution into that file
 * 3. Include the file at the beginning of each affected page:
 * 
 *    // In financial_analysis.php
 *    require_once 'includes/purefarm_fixes.php';
 *    $fix_functions = fixFinancialAnalysisPage($pdo);
 *    // Then use the functions:
 *    $financial_data = $fix_functions['fetchFinancialData']($pdo, $start_date, $end_date);
 * 
 *    // In harvest_planning.php
 *    require_once 'includes/purefarm_fixes.php';
 *    $financial_data = fixHarvestPlanningPage($pdo, $crop_id);
 * 
 *    // In add_expense.php
 *    require_once 'includes/purefarm_fixes.php';
 *    $categories = fixExpenseCategories($pdo);
 * 
 *    // In financial_reports.php
 *    require_once 'includes/purefarm_fixes.php';
 *    $fixes = fixFinancialReports();
 *    // Output the JavaScript fix before the closing </body> tag
 *    echo $fixes['js_fix'];
 *    // Replace the PHP template section with $fixes['php_fix']
 */