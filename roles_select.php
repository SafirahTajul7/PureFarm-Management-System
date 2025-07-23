<?php
// Fetch roles for dropdown
try {
    $roles_query = "SELECT id, role_name FROM roles ORDER BY role_name";
    $roles_stmt = $pdo->query($roles_query);
    $roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching roles: " . $e->getMessage());
    $roles = [];
}
?>

<div class="form-group">
    <label for="role_id">Role *</label>
    <select class="form-control" id="role_id" name="role_id" required>
        <option value="">Select Role</option>
        <?php foreach ($roles as $role): ?>
            <option value="<?php echo $role['id']; ?>" <?php echo (isset($_POST['role_id']) && $_POST['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($role['role_name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>