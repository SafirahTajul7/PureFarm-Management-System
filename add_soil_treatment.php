<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is admin
auth()->checkAdmin();

// Define form fields with default values
$form_data = [
    'field_id' => '',
    'application_date' => date('Y-m-d'),
    'treatment_type' => '',
    'product_name' => '',
    'application_rate' => '',
    'application_method' => '',
    'target_ph' => '',
    'target_nutrient' => '',
    'cost_per_acre' => '',
    'total_cost' => '',
    'weather_conditions' => '',
    'notes' => ''
];

// Define treatment types
$treatment_types = [
    'lime' => 'Lime Application',
    'gypsum' => 'Gypsum Application',
    'compost' => 'Compost/Organic Matter',
    'sulfur' => 'Sulfur Amendment',
    'manure' => 'Manure Application',
    'biochar' => 'Biochar',
    'cover_crop' => 'Cover Crop Integration',
    'other' => 'Other Amendment'
];

// Application methods
$application_methods = [
    'broadcast' => 'Broadcast Spreading',
    'banded' => 'Banded Application',
    'incorporated' => 'Soil Incorporated',
    'foliar' => 'Foliar Application',
    'drip' => 'Drip Irrigation',
    'injection' => 'Soil Injection',
    'other' => 'Other Method'
];

// Fetch all fields for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, field_name FROM fields ORDER BY field_name ASC");
    $stmt->execute();
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    $fields = [];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $form_data = [
        'field_id' => $_POST['field_id'] ?? '',
        'application_date' => $_POST['application_date'] ?? date('Y-m-d'),
        'treatment_type' => $_POST['treatment_type'] ?? '',
        'product_name' => $_POST['product_name'] ?? '',
        'application_rate' => $_POST['application_rate'] ?? '',
        'application_method' => $_POST['application_method'] ?? '',
        'target_ph' => $_POST['target_ph'] ?? '',
        'target_nutrient' => $_POST['target_nutrient'] ?? '',
        'cost_per_acre' => $_POST['cost_per_acre'] ?? '',
        'total_cost' => $_POST['total_cost'] ?? '',
        'weather_conditions' => $_POST['weather_conditions'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ];
    
    // Validate form data
    $errors = [];
    
    if (empty($form_data['field_id'])) {
        $errors[] = "Field is required";
    }
    
    if (empty($form_data['application_date'])) {
        $errors[] = "Application date is required";
    }
    
    if (empty($form_data['treatment_type'])) {
        $errors[] = "Treatment type is required";
    }
    
    if (empty($form_data['product_name'])) {
        $errors[] = "Product name is required";
    }
    
    if (empty($form_data['application_rate'])) {
        $errors[] = "Application rate is required";
    }
    
    if (!empty($form_data['cost_per_acre']) && !is_numeric($form_data['cost_per_acre'])) {
        $errors[] = "Cost per acre must be a number";
    }
    
    if (!empty($form_data['total_cost']) && !is_numeric($form_data['total_cost'])) {
        $errors[] = "Total cost must be a number";
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO soil_treatments (
                    field_id, application_date, treatment_type, product_name, 
                    application_rate, application_method, target_ph, target_nutrient,
                    cost_per_acre, total_cost, weather_conditions, notes, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");
            
            $stmt->execute([
                $form_data['field_id'],
                $form_data['application_date'],
                $form_data['treatment_type'],
                $form_data['product_name'],
                $form_data['application_rate'],
                $form_data['application_method'],
                $form_data['target_ph'],
                $form_data['target_nutrient'],
                $form_data['cost_per_acre'],
                $form_data['total_cost'],
                $form_data['weather_conditions'],
                $form_data['notes']
            ]);
            
            // Redirect to soil treatments page with success message
            header("Location: soil_treatments.php?success=1");
            exit();
            
        } catch(PDOException $e) {
            error_log("Error saving soil treatment: " . $e->getMessage());
            $db_error = "Database error: " . $e->getMessage();
        }
    }
}

// Set page title and include header
$pageTitle = 'Add Soil Treatment';
include 'includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-flask"></i> Add Soil Treatment</h2>
    </div>
    
    <?php if (isset($errors) && count($errors) > 0): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if (isset($db_error)): ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($db_error); ?>
    </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-edit me-1"></i> Treatment Details
        </div>
        <div class="card-body">
            <form method="post" action="add_soil_treatment.php">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="field_id" class="form-label">Field *</label>
                        <select class="form-select" id="field_id" name="field_id" required>
                            <option value="">Select Field</option>
                            <?php foreach ($fields as $field): ?>
                            <option value="<?php echo $field['id']; ?>" <?php echo ($form_data['field_id'] == $field['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($field['field_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="application_date" class="form-label">Application Date *</label>
                        <input type="date" class="form-control" id="application_date" name="application_date" 
                            value="<?php echo htmlspecialchars($form_data['application_date']); ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="treatment_type" class="form-label">Treatment Type *</label>
                        <select class="form-select" id="treatment_type" name="treatment_type" required>
                            <option value="">Select Treatment Type</option>
                            <?php foreach ($treatment_types as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($form_data['treatment_type'] == $key) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="product_name" class="form-label">Product Name *</label>
                        <input type="text" class="form-control" id="product_name" name="product_name" 
                            value="<?php echo htmlspecialchars($form_data['product_name']); ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="application_rate" class="form-label">Application Rate *</label>
                        <input type="text" class="form-control" id="application_rate" name="application_rate" 
                            placeholder="e.g., 2 tons/acre, 100 lbs/acre" 
                            value="<?php echo htmlspecialchars($form_data['application_rate']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="application_method" class="form-label">Application Method</label>
                        <select class="form-select" id="application_method" name="application_method">
                            <option value="">Select Application Method</option>
                            <?php foreach ($application_methods as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($form_data['application_method'] == $key) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="target_ph" class="form-label">Target pH Level</label>
                        <input type="text" class="form-control" id="target_ph" name="target_ph" 
                            placeholder="e.g., 6.5" 
                            value="<?php echo htmlspecialchars($form_data['target_ph']); ?>">
                        <div class="form-text">For pH adjustment treatments</div>
                    </div>
                    <div class="col-md-6">
                        <label for="target_nutrient" class="form-label">Target Nutrient</label>
                        <input type="text" class="form-control" id="target_nutrient" name="target_nutrient" 
                            placeholder="e.g., Organic Matter, Calcium" 
                            value="<?php echo htmlspecialchars($form_data['target_nutrient']); ?>">
                        <div class="form-text">Specific nutrient being targeted by this treatment</div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="cost_per_acre" class="form-label">Cost per Acre ($)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="cost_per_acre" name="cost_per_acre" 
                            value="<?php echo htmlspecialchars($form_data['cost_per_acre']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="total_cost" class="form-label">Total Cost ($)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="total_cost" name="total_cost" 
                            value="<?php echo htmlspecialchars($form_data['total_cost']); ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="weather_conditions" class="form-label">Weather Conditions</label>
                    <input type="text" class="form-control" id="weather_conditions" name="weather_conditions" 
                        placeholder="e.g., Sunny, Light Rain, Cloudy" 
                        value="<?php echo htmlspecialchars($form_data['weather_conditions']); ?>">
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($form_data['notes']); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <div class="form-text mb-2">* Required fields</div>
                    <button type="submit" class="btn btn-primary">Save Treatment</button>
                    <a href="soil_treatments.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-calculate total cost when cost per acre changes
    const costPerAcreInput = document.getElementById('cost_per_acre');
    const totalCostInput = document.getElementById('total_cost');
    const fieldSelect = document.getElementById('field_id');
    
    // Function to update total cost
    const updateTotalCost = async () => {
        const fieldId = fieldSelect.value;
        const costPerAcre = parseFloat(costPerAcreInput.value) || 0;
        
        if (fieldId && costPerAcre > 0) {
            try {
                // Fetch field size from database
                const response = await fetch(`get_field_size.php?field_id=${fieldId}`);
                const data = await response.json();
                
                if (data.success && data.size) {
                    // Calculate total cost based on field size
                    const fieldSize = parseFloat(data.size);
                    const totalCost = (costPerAcre * fieldSize).toFixed(2);
                    totalCostInput.value = totalCost;
                }
            } catch (error) {
                console.error('Error fetching field size:', error);
            }
        }
    };
    
    // Add event listeners
    costPerAcreInput.addEventListener('change', updateTotalCost);
    fieldSelect.addEventListener('change', updateTotalCost);
    
    // Populate treatment specific fields based on treatment type
    const treatmentTypeSelect = document.getElementById('treatment_type');
    const targetPhInput = document.getElementById('target_ph');
    const targetNutrientInput = document.getElementById('target_nutrient');
    
    treatmentTypeSelect.addEventListener('change', function() {
        const treatmentType = this.value;
        
        // Reset fields
        targetPhInput.value = '';
        targetNutrientInput.value = '';
        
        // Set default values based on treatment type
        switch(treatmentType) {
            case 'lime':
                targetPhInput.value = '6.5';
                targetNutrientInput.value = 'Calcium';
                break;
            case 'gypsum':
                targetNutrientInput.value = 'Calcium, Sulfur';
                break;
            case 'compost':
                targetNutrientInput.value = 'Organic Matter';
                break;
            case 'sulfur':
                targetPhInput.value = '6.0';
                targetNutrientInput.value = 'Sulfur';
                break;
            case 'manure':
                targetNutrientInput.value = 'Organic Matter, NPK';
                break;
            case 'biochar':
                targetNutrientInput.value = 'Carbon, Soil Structure';
                break;
            case 'cover_crop':
                targetNutrientInput.value = 'Soil Structure, Nitrogen';
                break;
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>