<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'PureFarm'; ?></title>
    
    <!-- jQuery first -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    
    <!-- Bootstrap CSS and JS (using only one version - 4.5.2) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    
    <!-- Chart.js for graphs and visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Your CSS files -->
    <link href="assets/css/styles.css" rel="stylesheet">
    <link href="assets/css/forms.css" rel="stylesheet">
    <link href="assets/css/tables.css" rel="stylesheet">
    <link href="assets/css/components.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php 
    // Check if user is logged in and has a role set
    if(isset($_SESSION['role'])) {
        // Include the appropriate sidebar based on user role
        if($_SESSION['role'] === 'supervisor') {
            include 'includes/supervisor_sidebar.php';
        } else if($_SESSION['role'] === 'admin') {
            include 'includes/sidebar.php';
        } else {
            // Default sidebar for other roles if needed
            include 'includes/default_sidebar.php';
        }
    } else {
        // If no role is set, include a minimal sidebar or nothing
        echo '<div class="no-sidebar"></div>';
    }
    ?>
    <!-- Main content container will follow after this in the layout -->