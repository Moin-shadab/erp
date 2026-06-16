window.onRoleChanged = function(roleId) {
    // Reload active layout using our SPA page loader engine
    loadPage('/erp/permissions-matrix?role_id=' + roleId);
};

window.updatePermissionCheckbox = function(checkbox) {
    const roleId = checkbox.getAttribute('data-role-id');
    const pageId = checkbox.getAttribute('data-page-id');
    const action = checkbox.getAttribute('data-action');
    const val = checkbox.checked ? 1 : 0;

    fetch('/api/permissions/update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            role_id: roleId,
            page_id: pageId,
            action: action,
            value: val
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Permissions configuration updated.');
            
            // If it is the active logged in user's role, reload the sidebar dynamic menu context
            if (parseInt(roleId) === parseInt(currentUserRoleId)) {
                fetchInitialContext(); // reload topbar & sidebar modules
            }
        } else {
            showToast('danger', 'Failed to update permissions.');
            checkbox.checked = !checkbox.checked; // revert checkbox state
        }
    })
    .catch(err => {
        showToast('danger', 'Network connection error.');
        checkbox.checked = !checkbox.checked; // revert checkbox state
    });
};

// Capture user role ID from main framework settings for dynamic context syncing
var currentUserRoleId = null;
fetch('/api/user/context')
    .then(r => r.json())
    .then(data => {
        currentUserRoleId = data.user.role_id;
    });
