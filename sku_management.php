<?php
require_once 'includes/auth.php';
auth()->checkAdmin();

require_once 'includes/db.php';

// Initialize variables
$errorMsg = '';
$successMsg = '';
$skuPrefix = '';
$skuSuffix = '';
$skuFormat = '';
$skuCategories = [];
$skuItems = [];
$noSkuItems = [];
$infoMsg = '';

// Function to check database table structure and adapt if needed
function ensureTableStructure($pdo) {
    try {
        // Check if inventory_items table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'inventory_items'");
        if ($tableCheck->rowCount() === 0) {
            return "Table 'inventory_items' doesn't exist. Please check your database setup.";
        }

        // Get actual column names from inventory_items table
        $columnsStmt = $pdo->query("DESCRIBE inventory_items");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Determine which column stores the item name
        $nameColumn = in_array('name', $columns) ? 'name' : 
                     (in_array('item_name', $columns) ? 'item_name' : null);
        
        if (!$nameColumn) {
            return "Could not find name column in inventory_items table. Please check your database schema.";
        }
        
        return [
            'status' => 'success',
            'nameColumn' => $nameColumn,
            'columns' => $columns
        ];
    } catch (PDOException $e) {
        return "Error checking table structure: " . $e->getMessage();
    }
}

// Run the table structure check
$structureCheck = ensureTableStructure($pdo);
$nameColumn = is_array($structureCheck) && isset($structureCheck['nameColumn']) 
    ? $structureCheck['nameColumn'] 
    : 'name'; // Default fallback

// Process SKU configuration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_config') {
        try {
            // Update SKU configuration
            $skuPrefix = $_POST['sku_prefix'] ?? '';
            $skuSuffix = $_POST['sku_suffix'] ?? '';
            $skuFormat = $_POST['sku_format'] ?? 'category-number';
            
            // Check if system_settings table exists
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'system_settings'");
            if ($tableCheck->rowCount() === 0) {
                // Create system_settings table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS system_settings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        setting_name VARCHAR(100) NOT NULL UNIQUE,
                        value TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
                ");
                
                // Insert default SKU settings
                $pdo->exec("
                    INSERT INTO system_settings (setting_name, value) VALUES
                    ('sku_prefix', '$skuPrefix'),
                    ('sku_suffix', '$skuSuffix'),
                    ('sku_format', '$skuFormat')
                ");
                
                $successMsg = 'SKU configuration created and updated successfully.';
            } else {
                // Update existing settings
                // First check if settings exist
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_name = ?");
                
                $checkStmt->execute(['sku_prefix']);
                $prefixExists = (int)$checkStmt->fetchColumn() > 0;
                
                $checkStmt->execute(['sku_suffix']);
                $suffixExists = (int)$checkStmt->fetchColumn() > 0;
                
                $checkStmt->execute(['sku_format']);
                $formatExists = (int)$checkStmt->fetchColumn() > 0;
                
                // Insert or update settings
                if ($prefixExists) {
                    $stmt = $pdo->prepare("UPDATE system_settings SET value = ? WHERE setting_name = 'sku_prefix'");
                    $stmt->execute([$skuPrefix]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_name, value) VALUES ('sku_prefix', ?)");
                    $stmt->execute([$skuPrefix]);
                }
                
                if ($suffixExists) {
                    $stmt = $pdo->prepare("UPDATE system_settings SET value = ? WHERE setting_name = 'sku_suffix'");
                    $stmt->execute([$skuSuffix]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_name, value) VALUES ('sku_suffix', ?)");
                    $stmt->execute([$skuSuffix]);
                }
                
                if ($formatExists) {
                    $stmt = $pdo->prepare("UPDATE system_settings SET value = ? WHERE setting_name = 'sku_format'");
                    $stmt->execute([$skuFormat]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_name, value) VALUES ('sku_format', ?)");
                    $stmt->execute([$skuFormat]);
                }
                
                $successMsg = 'SKU configuration updated successfully.';
            }
        } catch (PDOException $e) {
            error_log("Error updating SKU configuration: " . $e->getMessage());
            $errorMsg = 'Failed to update SKU configuration: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'generate_sku' && isset($_POST['item_id'])) {
        // Generate new SKU for the selected item
        $itemId = $_POST['item_id'];
        
        try {
            // Get item details - use explicit column names with adaptation for name column
            $stmt = $pdo->prepare("
                SELECT 
                    inventory_items.id,
                    inventory_items.{$nameColumn} AS item_name, 
                    inventory_items.category_id, 
                    item_categories.name AS category_name 
                FROM inventory_items
                JOIN item_categories ON inventory_items.category_id = item_categories.id
                WHERE inventory_items.id = :item_id
            ");
            $stmt->execute(['item_id' => $itemId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($item) {
                // Get current SKU configuration
                $skuPrefixStmt = $pdo->query("SELECT value FROM system_settings WHERE setting_name = 'sku_prefix'");
                $skuPrefix = $skuPrefixStmt->fetchColumn() ?: '';
                
                $skuSuffixStmt = $pdo->query("SELECT value FROM system_settings WHERE setting_name = 'sku_suffix'");
                $skuSuffix = $skuSuffixStmt->fetchColumn() ?: '';
                
                $skuFormatStmt = $pdo->query("SELECT value FROM system_settings WHERE setting_name = 'sku_format'");
                $skuFormat = $skuFormatStmt->fetchColumn() ?: 'category-number';
                
                // Get the next sequential number for this category
                $seqStmt = $pdo->prepare("
                    SELECT MAX(CAST(SUBSTRING_INDEX(sku, '-', -1) AS UNSIGNED)) AS max_seq
                    FROM inventory_items
                    WHERE category_id = :category_id
                    AND sku REGEXP '[0-9]+$'
                ");
                $seqStmt->execute(['category_id' => $item['category_id']]);
                $maxSeq = $seqStmt->fetchColumn();
                $nextSeq = ($maxSeq ? $maxSeq + 1 : 1);
                
                // Format the sequence number to ensure consistent length
                $seqFormatted = sprintf('%04d', $nextSeq);
                
                // Create category code (first 3 letters of category)
                $categoryCode = substr(strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $item['category_name'])), 0, 3);
                
                // Generate the SKU based on the selected format
                switch ($skuFormat) {
                    case 'category-number':
                        $newSku = $skuPrefix . $categoryCode . '-' . $seqFormatted . $skuSuffix;
                        break;
                    case 'number-only':
                        $newSku = $skuPrefix . $seqFormatted . $skuSuffix;
                        break;
                    case 'custom':
                        // For custom format, implement your own logic
                        $itemCode = substr(strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $item['item_name'])), 0, 3);
                        $newSku = $skuPrefix . $itemCode . '-' . $categoryCode . '-' . $seqFormatted . $skuSuffix;
                        break;
                    default:
                        $newSku = $skuPrefix . $categoryCode . '-' . $seqFormatted . $skuSuffix;
                }
                
                // Update the item with the new SKU
                $updateStmt = $pdo->prepare("
                    UPDATE inventory_items
                    SET sku = :sku
                    WHERE id = :item_id
                ");
                $updateStmt->execute([
                    'sku' => $newSku,
                    'item_id' => $itemId
                ]);
                
                $successMsg = "Successfully generated SKU: $newSku for item.";
            } else {
                $errorMsg = 'Item not found.';
            }
        } catch (PDOException $e) {
            error_log("Error generating SKU: " . $e->getMessage());
            $errorMsg = 'Failed to generate SKU: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'bulk_generate') {
        // Bulk generate SKUs for all items without SKUs
        try {
            // Get all items without SKUs
            $stmt = $pdo->query("
                SELECT 
                    inventory_items.id, 
                    inventory_items.{$nameColumn} AS item_name, 
                    inventory_items.category_id, 
                    item_categories.name AS category_name 
                FROM inventory_items
                JOIN item_categories ON inventory_items.category_id = item_categories.id
                WHERE inventory_items.sku IS NULL OR inventory_items.sku = ''
            ");
            
            if (!$stmt) {
                throw new Exception("Failed to query items without SKUs. Please check your database schema.");
            }
            
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($items) > 0) {
                // Get current SKU configuration
                $skuPrefixStmt = $pdo->query("SELECT value FROM system_settings WHERE setting_name = 'sku_prefix'");
                $skuPrefix = $skuPrefixStmt->fetchColumn() ?: '';
                
                $skuSuffixStmt = $pdo->query("SELECT value FROM system_settings WHERE setting_name = 'sku_suffix'");
                $skuSuffix = $skuSuffixStmt->fetchColumn() ?: '';
                
                $skuFormatStmt = $pdo->query("SELECT value FROM system_settings WHERE setting_name = 'sku_format'");
                $skuFormat = $skuFormatStmt->fetchColumn() ?: 'category-number';
                
                // Track categories and their current max sequence
                $categorySeq = [];
                
                foreach ($items as $item) {
                    $categoryId = $item['category_id'];
                    
                    // If we haven't processed this category yet, get its max sequence
                    if (!isset($categorySeq[$categoryId])) {
                        $seqStmt = $pdo->prepare("
                            SELECT MAX(CAST(SUBSTRING_INDEX(sku, '-', -1) AS UNSIGNED)) AS max_seq
                            FROM inventory_items
                            WHERE category_id = :category_id
                            AND sku REGEXP '[0-9]+$'
                        ");
                        $seqStmt->execute(['category_id' => $categoryId]);
                        $maxSeq = $seqStmt->fetchColumn();
                        $categorySeq[$categoryId] = ($maxSeq ? $maxSeq : 0);
                    }
                    
                    // Increment the sequence for this category
                    $categorySeq[$categoryId]++;
                    $nextSeq = $categorySeq[$categoryId];
                    
                    // Format the sequence number
                    $seqFormatted = sprintf('%04d', $nextSeq);
                    
                    // Create category code
                    $categoryCode = substr(strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $item['category_name'])), 0, 3);
                    
                    // Generate the SKU based on the selected format
                    switch ($skuFormat) {
                        case 'category-number':
                            $newSku = $skuPrefix . $categoryCode . '-' . $seqFormatted . $skuSuffix;
                            break;
                        case 'number-only':
                            $newSku = $skuPrefix . $seqFormatted . $skuSuffix;
                            break;
                        case 'custom':
                            $itemCode = substr(strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $item['item_name'])), 0, 3);
                            $newSku = $skuPrefix . $itemCode . '-' . $categoryCode . '-' . $seqFormatted . $skuSuffix;
                            break;
                        default:
                            $newSku = $skuPrefix . $categoryCode . '-' . $seqFormatted . $skuSuffix;
                    }
                    
                    // Update the item with the new SKU
                    $updateStmt = $pdo->prepare("
                        UPDATE inventory_items
                        SET sku = :sku
                        WHERE id = :item_id
                    ");
                    $updateStmt->execute([
                        'sku' => $newSku,
                        'item_id' => $item['id']
                    ]);
                }
                
                $successMsg = "Successfully generated SKUs for " . count($items) . " items.";
            } else {
                $infoMsg = 'No items without SKUs found.';
            }
        } catch (Exception $e) {
            error_log("Error bulk generating SKUs: " . $e->getMessage());
            $errorMsg = 'Failed to generate SKUs: ' . $e->getMessage();
        }
    }
}

// Retrieve current SKU configuration
try {
    // Initialize arrays to prevent undefined variable errors
    $noSkuItems = [];
    $skuItems = [];
    
    // Check if system_settings table exists first
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'system_settings'");
    $tableExists = $tableCheck->rowCount() > 0;
    
    if ($tableExists) {
        // Retrieve SKU settings
        $skuPrefixStmt = $pdo->query("SELECT value FROM system_settings WHERE setting_name = 'sku_prefix'");
        if ($skuPrefixStmt && $skuPrefixStmt->rowCount() > 0) {
            $skuPrefix = $skuPrefixStmt->fetchColumn();
        } else {
            $skuPrefix = '';
        }
        
        $skuSuffixStmt = $pdo->query("SELECT value FROM system_settings WHERE setting_name = 'sku_suffix'");
        if ($skuSuffixStmt && $skuSuffixStmt->rowCount() > 0) {
            $skuSuffix = $skuSuffixStmt->fetchColumn();
        } else {
            $skuSuffix = '';
        }
        
        $skuFormatStmt = $pdo->query("SELECT value FROM system_settings WHERE setting_name = 'sku_format'");
        if ($skuFormatStmt && $skuFormatStmt->rowCount() > 0) {
            $skuFormat = $skuFormatStmt->fetchColumn();
        } else {
            $skuFormat = 'category-number';
        }
    } else {
        // Default values if system_settings table doesn't exist
        $skuPrefix = '';
        $skuSuffix = '';
        $skuFormat = 'category-number';
    }
    
    // Get items from inventory
    $categoryStmt = $pdo->query("SELECT id, name FROM item_categories ORDER BY name");
    if ($categoryStmt) {
        $skuCategories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get items without SKUs
    $noSkuStmt = $pdo->query("
        SELECT 
            inventory_items.id, 
            inventory_items.{$nameColumn} AS name, 
            inventory_items.sku, 
            item_categories.name AS category_name 
        FROM inventory_items
        LEFT JOIN item_categories ON inventory_items.category_id = item_categories.id
        WHERE inventory_items.sku IS NULL OR inventory_items.sku = ''
        ORDER BY item_categories.name, inventory_items.{$nameColumn}
    ");
    
    if ($noSkuStmt) {
        $noSkuItems = $noSkuStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get items with SKUs
    $skuStmt = $pdo->query("
        SELECT 
            inventory_items.id, 
            inventory_items.{$nameColumn} AS name, 
            inventory_items.sku, 
            item_categories.name AS category_name 
        FROM inventory_items
        LEFT JOIN item_categories ON inventory_items.category_id = item_categories.id
        WHERE inventory_items.sku IS NOT NULL AND inventory_items.sku != ''
        ORDER BY item_categories.name, inventory_items.sku
        LIMIT 50
    ");
    
    if ($skuStmt) {
        $skuItems = $skuStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch(PDOException $e) {
    error_log("Error fetching SKU data: " . $e->getMessage());
    $errorMsg = 'Failed to load SKU configuration: ' . $e->getMessage() . ' [' . $e->getCode() . ']';
    
    // Initialize empty arrays to prevent undefined variable errors
    $noSkuItems = [];
    $skuItems = [];
    $skuCategories = [];
}

$pageTitle = 'SKU Management';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-barcode"></i> SKU Management</h2>
        <div class="action-buttons">
            <a href="inventory.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger"><?php echo $errorMsg; ?></div>
    <?php endif; ?>
    
    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    
    <?php if ($infoMsg): ?>
        <div class="alert alert-info"><?php echo $infoMsg; ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- SKU Configuration Section -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>SKU Configuration</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <input type="hidden" name="action" value="update_config">
                        
                        <div class="form-group">
                            <label for="sku_prefix">SKU Prefix:</label>
                            <input type="text" class="form-control" id="sku_prefix" name="sku_prefix" 
                                value="<?php echo htmlspecialchars($skuPrefix); ?>" 
                                placeholder="e.g., PF-">
                            <small class="form-text text-muted">Optional prefix to add to all SKUs</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="sku_suffix">SKU Suffix:</label>
                            <input type="text" class="form-control" id="sku_suffix" name="sku_suffix" 
                                value="<?php echo htmlspecialchars($skuSuffix); ?>" 
                                placeholder="e.g., -FARM">
                            <small class="form-text text-muted">Optional suffix to add to all SKUs</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="sku_format">SKU Format:</label>
                            <select class="form-control" id="sku_format" name="sku_format">
                                <option value="category-number" <?php echo $skuFormat === 'category-number' ? 'selected' : ''; ?>>
                                    Category Code + Number (e.g., FER-0001)
                                </option>
                                <option value="number-only" <?php echo $skuFormat === 'number-only' ? 'selected' : ''; ?>>
                                    Number Only (e.g., 0001)
                                </option>
                                <option value="custom" <?php echo $skuFormat === 'custom' ? 'selected' : ''; ?>>
                                    Custom (Item Code + Category + Number)
                                </option>
                            </select>
                            <small class="form-text text-muted">Format for generating new SKUs</small>
                        </div>
                        
                        <div class="form-group">
                            <h5>Example SKUs with current settings:</h5>
                            <ul class="list-unstyled">
                                <li><strong>Fertilizer item:</strong> <?php echo htmlspecialchars($skuPrefix . "FER-0001" . $skuSuffix); ?></li>
                                <li><strong>Seed item:</strong> <?php echo htmlspecialchars($skuPrefix . "SEE-0001" . $skuSuffix); ?></li>
                                <li><strong>Tool item:</strong> <?php echo htmlspecialchars($skuPrefix . "TOO-0001" . $skuSuffix); ?></li>
                            </ul>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Configuration</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Generate SKUs Section -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Generate SKUs</h3>
                </div>
                <div class="card-body">
                    <?php if (count($noSkuItems) > 0): ?>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="bulk_generate">
                            <p>There are <?php echo count($noSkuItems); ?> items without SKUs.</p>
                            <button type="submit" class="btn btn-warning mb-3">
                                <i class="fas fa-magic"></i> Generate SKUs for All Items
                            </button>
                        </form>
                        
                        <hr>
                        
                        <h4>Generate Individual SKU</h4>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="generate_sku">
                            
                            <div class="form-group">
                                <label for="item_id">Select Item:</label>
                                <select class="form-control" id="item_id" name="item_id" required>
                                    <option value="">-- Select an item --</option>
                                    <?php foreach ($noSkuItems as $item): ?>
                                        <option value="<?php echo $item['id']; ?>">
                                            <?php echo htmlspecialchars($item['name']); ?> 
                                            (<?php echo htmlspecialchars($item['category_name'] ?? 'No Category'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Generate SKU</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            All inventory items have SKUs assigned.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- SKU List Table -->
    <div class="card mt-4">
        <div class="card-header">
            <h3>Existing SKUs</h3>
        </div>
        <div class="card-body">
            <?php if (count($skuItems) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($skuItems as $item): ?>
                                <tr>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($item['sku']); ?></span></td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'No Category'); ?></td>
                                    <td>
                                        <a href="edit_inventory_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (count($skuItems) >= 50): ?>
                    <div class="alert alert-info">
                        Showing first 50 items. For a complete list, please use the inventory reports.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning">
                    No items with SKUs found.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Simple inline script to avoid dependency on external JS files -->
<script>
    // Basic JavaScript functionality that doesn't require jQuery
    document.addEventListener('DOMContentLoaded', function() {
        // Format selector
        const formatSelector = document.getElementById('sku_format');
        if (formatSelector) {
            formatSelector.addEventListener('change', function() {
                // Format selector logic can be added here if needed
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>