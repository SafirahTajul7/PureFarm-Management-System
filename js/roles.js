/**
 * PureFarm Management System
 * Roles Management JavaScript Functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    try {
        // Initialize tooltips
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Setup permission group select-all checkboxes
        setupSelectAllCheckboxes();

        // Confirmation dialogs for delete actions
        setupDeleteConfirmations();

        // Permission visualization
        initializePermissionVisualization();
    } catch (error) {
        console.error("Error in initialization:", error);
    }
});

/**
 * Sets up "Select All" checkboxes for permission groups
 */
function setupSelectAllCheckboxes() {
    try {
        const permissionGroups = [
            "animal", "crop", "inventory", "staff", "reports", "system"
        ];
        
        permissionGroups.forEach(group => {
            // For Add Role modal
            const groupCheckboxes = document.querySelectorAll(`input[name^="permissions[${group}_"]`);
            if (groupCheckboxes.length > 0) {
                // Add "Select All" checkbox
                const firstCheckbox = groupCheckboxes[0];
                const parentCard = firstCheckbox.closest('.card-body');
                
                if (parentCard) {
                    const selectAllDiv = document.createElement('div');
                    selectAllDiv.className = 'form-check mb-2';
                    selectAllDiv.innerHTML = `
                        <input class="form-check-input select-all-checkbox" type="checkbox" id="${group}_select_all">
                        <label class="form-check-label fw-bold" for="${group}_select_all">Select All ${capitalizeFirstLetter(group)}</label>
                    `;
                    parentCard.insertBefore(selectAllDiv, parentCard.firstChild);
                    
                    // Add event listener to "Select All" checkbox
                    const selectAllCheckbox = document.getElementById(`${group}_select_all`);
                    if (selectAllCheckbox) {
                        selectAllCheckbox.addEventListener('change', function() {
                            groupCheckboxes.forEach(checkbox => {
                                checkbox.checked = this.checked;
                            });
                        });
                        
                        // Update "Select All" checkbox when individual permissions change
                        groupCheckboxes.forEach(checkbox => {
                            checkbox.addEventListener('change', function() {
                                const allChecked = Array.from(groupCheckboxes).every(cb => cb.checked);
                                selectAllCheckbox.checked = allChecked;
                            });
                        });
                        
                        // Initialize "Select All" checkbox state
                        const allChecked = Array.from(groupCheckboxes).every(cb => cb.checked);
                        selectAllCheckbox.checked = allChecked;
                    }
                }
            }
            
            // For Edit Role modal
            const editGroupCheckboxes = document.querySelectorAll(`input[name^="permissions[${group}_"][id^="edit_"]`);
            if (editGroupCheckboxes.length > 0) {
                // Add "Select All" checkbox
                const firstCheckbox = editGroupCheckboxes[0];
                const parentCard = firstCheckbox.closest('.card-body');
                
                if (parentCard) {
                    const selectAllDiv = document.createElement('div');
                    selectAllDiv.className = 'form-check mb-2';
                    selectAllDiv.innerHTML = `
                        <input class="form-check-input select-all-checkbox" type="checkbox" id="edit_${group}_select_all">
                        <label class="form-check-label fw-bold" for="edit_${group}_select_all">Select All ${capitalizeFirstLetter(group)}</label>
                    `;
                    parentCard.insertBefore(selectAllDiv, parentCard.firstChild);
                    
                    // Add event listener to "Select All" checkbox
                    const selectAllCheckbox = document.getElementById(`edit_${group}_select_all`);
                    if (selectAllCheckbox) {
                        selectAllCheckbox.addEventListener('change', function() {
                            editGroupCheckboxes.forEach(checkbox => {
                                checkbox.checked = this.checked;
                            });
                        });
                        
                        // Update "Select All" checkbox when individual permissions change
                        editGroupCheckboxes.forEach(checkbox => {
                            checkbox.addEventListener('change', function() {
                                const allChecked = Array.from(editGroupCheckboxes).every(cb => cb.checked);
                                selectAllCheckbox.checked = allChecked;
                            });
                        });
                        
                        // Initialize "Select All" checkbox state
                        const allChecked = Array.from(editGroupCheckboxes).every(cb => cb.checked);
                        selectAllCheckbox.checked = allChecked;
                    }
                }
            }
        });
    } catch (error) {
        console.error("Error in setupSelectAllCheckboxes:", error);
    }
}

/**
 * Setup confirmation dialogs for delete actions
 */
function setupDeleteConfirmations() {
    try {
        const deleteButtons = document.querySelectorAll('.delete-role-btn, a[href*="delete"]');
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    } catch (error) {
        console.error("Error in setupDeleteConfirmations:", error);
    }
}

/**
 * Initialize permission visualization in the table
 */
function initializePermissionVisualization() {
    try {
        const permissionCells = document.querySelectorAll('.permission-cell');
        
        permissionCells.forEach(cell => {
            const permissions = cell.getAttribute('data-permissions');
            if (permissions) {
                try {
                    const permObj = JSON.parse(permissions);
                    let html = '';
                    
                    const categories = {
                        'animal': { icon: 'fas fa-paw', color: '#3498db', label: 'Animal' },
                        'crop': { icon: 'fas fa-seedling', color: '#2ecc71', label: 'Crop' },
                        'inventory': { icon: 'fas fa-boxes', color: '#f39c12', label: 'Inventory' },
                        'staff': { icon: 'fas fa-users', color: '#9b59b6', label: 'Staff' },
                        'reports': { icon: 'fas fa-chart-bar', color: '#e74c3c', label: 'Reports' },
                        'system': { icon: 'fas fa-cogs', color: '#34495e', label: 'System' }
                    };
                    
                    // Count permissions by category
                    const counts = {};
                    
                    for (const key in permObj) {
                        if (permObj[key]) {
                            const category = key.split('_')[0];
                            if (!counts[category]) {
                                counts[category] = 0;
                            }
                            counts[category]++;
                        }
                    }
                    
                    // Generate badge for each category
                    for (const category in counts) {
                        if (categories[category]) {
                            const { icon, color, label } = categories[category];
                            html += `<span class="badge rounded-pill" style="background-color: ${color}; margin-right: 5px; margin-bottom: 5px;">
                                <i class="${icon}"></i> ${label}: ${counts[category]}
                            </span>`;
                        }
                    }
                    
                    if (html) {
                        cell.innerHTML = html;
                    } else {
                        cell.innerHTML = '<span class="text-muted">No permissions</span>';
                    }
                } catch (e) {
                    console.error('Error parsing permissions:', e);
                    cell.innerHTML = '<span class="text-danger">Error parsing permissions</span>';
                }
            }
        });
    } catch (error) {
        console.error("Error in initializePermissionVisualization:", error);
    }
}

/**
 * Capitalize the first letter of a string
 */
function capitalizeFirstLetter(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}