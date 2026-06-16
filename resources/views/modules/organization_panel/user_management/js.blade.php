var selectedUserId = null;
var rolesData = @json($roles);

var createUserModal = new bootstrap.Modal(document.getElementById('createUserModal'));
var editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
var changePasswordModal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
var clonePermissionsModal = new bootstrap.Modal(document.getElementById('clonePermissionsModal'));

// Filter user directory on search input
function filterUserList() {
    const query = document.getElementById('user-search').value.toLowerCase();
    document.querySelectorAll('.user-card').forEach(card => {
        const name = card.getAttribute('data-name');
        const email = card.getAttribute('data-email');
        const role = card.getAttribute('data-role-name');
        if (name.includes(query) || email.includes(query) || role.includes(query)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Select user from directory
function selectUser(userId) {
    selectedUserId = userId;
    
    // Toggle selected styling
    document.querySelectorAll('.user-card').forEach(card => {
        card.classList.remove('border-primary', 'bg-primary-subtle');
    });
    const activeCard = document.getElementById('user-card-' + userId);
    activeCard.classList.add('border-primary', 'bg-primary-subtle');

    // Reveal Console
    document.getElementById('no-selection-state').classList.add('d-none');
    document.getElementById('user-console').classList.remove('d-none');

    // Update Console Headers
    const name = activeCard.querySelector('.user-name-text').textContent;
    const email = activeCard.getAttribute('data-email');
    const roleId = activeCard.getAttribute('data-role-id');
    
    document.getElementById('console-user-name').textContent = name;
    document.getElementById('console-user-email').textContent = email;

    // Check for Super Admin override
    const role = rolesData.find(r => r.id == roleId);
    const overrideNotice = document.getElementById('super-admin-override-notice');
    const permCheckboxes = document.querySelectorAll('.perm-chk');

    if (role && role.slug === 'super-admin') {
        overrideNotice.classList.remove('d-none');
        permCheckboxes.forEach(cb => {
            cb.checked = true;
            cb.disabled = true;
        });
    } else {
        overrideNotice.classList.add('d-none');
        permCheckboxes.forEach(cb => {
            cb.disabled = false;
            cb.checked = false;
        });
        // Fetch custom permission matrix from server
        fetchUserPermissions(userId);
    }
}

// Load custom permissions for selected user
function fetchUserPermissions(userId) {
    fetch('/api/users/' + userId + '/permissions')
        .then(r => r.json())
        .then(data => {
            if (data.permissions) {
                const perms = data.permissions;
                
                // Uncheck everything first
                document.querySelectorAll('.perm-chk').forEach(cb => cb.checked = false);

                // Map checkboxes to loaded permissions
                Object.values(perms).forEach(p => {
                    const row = document.querySelector(`tr[data-page-id="${p.page_id}"]`);
                    if (row) {
                        row.querySelector('input[data-action="can_view"]').checked = !!p.can_view;
                        row.querySelector('input[data-action="can_create"]').checked = !!p.can_create;
                        row.querySelector('input[data-action="can_edit"]').checked = !!p.can_edit;
                        row.querySelector('input[data-action="can_delete"]').checked = !!p.can_delete;
                        row.querySelector('input[data-action="can_export"]').checked = !!p.can_export;
                        row.querySelector('input[data-action="can_print"]').checked = !!p.can_print;
                        row.querySelector('input[data-action="can_approve"]').checked = !!p.can_approve;
                        row.querySelector('input[data-action="can_reject"]').checked = !!p.can_reject;
                    }
                });
            }
        })
        .catch(err => {
            showToast('danger', 'Failed to retrieve permissions override.');
        });
}

// Toggle individual checkbox
function toggleUserPermission(pageId, action, value) {
    if (!selectedUserId) return;

    fetch('/api/users/permissions/update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            user_id: selectedUserId,
            page_id: pageId,
            action: action,
            value: value ? 1 : 0
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Permission override updated.');
        }
    });
}

// Open Modals
function openCreateUserModal() {
    document.getElementById('create-user-form').reset();
    createUserModal.show();
}

// Prepare Edit details
function editSelectedUser() {
    if (!selectedUserId) return;
    const activeCard = document.getElementById('user-card-' + selectedUserId);
    
    document.getElementById('edit-user-id').value = selectedUserId;
    document.getElementById('edit-user-name').value = activeCard.querySelector('.user-name-text').textContent;
    document.getElementById('edit-user-email').value = activeCard.getAttribute('data-email');
    document.getElementById('edit-user-role').value = activeCard.getAttribute('data-role-id');
    document.getElementById('edit-user-reports-to').value = activeCard.getAttribute('data-reports-to') || "";
    document.getElementById('edit-user-active').value = activeCard.getAttribute('data-active');
    document.getElementById('edit-user-send-anyone').checked = activeCard.getAttribute('data-send-anyone') == '1';
    
    editUserModal.show();
}

function openChangePasswordModal() {
    if (!selectedUserId) return;
    document.getElementById('change-password-form').reset();
    changePasswordModal.show();
}

function openCloneModal() {
    if (!selectedUserId) return;
    document.getElementById('clone-permissions-form').reset();
    
    // Remove self from clone dropdown to prevent circular dependencies
    const dropdown = document.getElementById('clone-source-user-id');
    for (let i = 0; i < dropdown.options.length; i++) {
        if (dropdown.options[i].value == selectedUserId) {
            dropdown.options[i].disabled = true;
        } else {
            dropdown.options[i].disabled = false;
        }
    }
    clonePermissionsModal.show();
}

// Submit Actions
function submitCreateUser(e) {
    e.preventDefault();
    const payload = {
        name: document.getElementById('new-user-name').value,
        email: document.getElementById('new-user-email').value,
        password: document.getElementById('new-user-password').value,
        role_id: document.getElementById('new-user-role').value,
        reports_to_id: document.getElementById('new-user-reports-to').value || null,
        is_active: document.getElementById('new-user-active').value,
        source_user_id: document.getElementById('new-user-clone-source').value || null,
        can_send_to_anyone: document.getElementById('new-user-send-anyone').checked ? 1 : 0
    };

    fetch('/api/users/store', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(payload)
    })
    .then(r => {
        if (!r.ok) {
            return r.json().then(errData => {
                let errMsg = 'Validation failed: ';
                if (errData.errors) {
                    errMsg += Object.values(errData.errors).flat().join(' ');
                } else {
                    errMsg += errData.message || 'Unknown error';
                }
                throw new Error(errMsg);
            });
        }
        return r.json();
    })
    .then(data => {
        if (data.success) {
            showToast('success', 'New user account created successfully.');
            createUserModal.hide();
            loadStaffConsole();
        } else {
            showToast('danger', data.error || 'Failed to create user account.');
        }
    })
    .catch(err => {
        showToast('danger', err.message || 'Validation errors or connection failure.');
    });
}

function submitEditUser(e) {
    e.preventDefault();
    const id = document.getElementById('edit-user-id').value;
    const payload = {
        name: document.getElementById('edit-user-name').value,
        email: document.getElementById('edit-user-email').value,
        role_id: document.getElementById('edit-user-role').value,
        reports_to_id: document.getElementById('edit-user-reports-to').value || null,
        is_active: document.getElementById('edit-user-active').value,
        can_send_to_anyone: document.getElementById('edit-user-send-anyone').checked ? 1 : 0
    };

    fetch('/api/users/update/' + id, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(payload)
    })
    .then(r => {
        if (!r.ok) {
            return r.json().then(errData => {
                let errMsg = 'Validation failed: ';
                if (errData.errors) {
                    errMsg += Object.values(errData.errors).flat().join(' ');
                } else {
                    errMsg += errData.message || 'Unknown error';
                }
                throw new Error(errMsg);
            });
        }
        return r.json();
    })
    .then(data => {
        if (data.success) {
            showToast('success', 'User profile details updated.');
            editUserModal.hide();
            loadStaffConsole();
        } else {
            showToast('danger', data.error || 'Failed to update user profile.');
        }
    })
    .catch(err => {
        showToast('danger', err.message || 'Validation errors or connection failure.');
    });
}

function submitChangePassword(e) {
    e.preventDefault();
    if (!selectedUserId) return;
    const payload = {
        password: document.getElementById('reset-password-val').value
    };

    fetch('/api/users/change-password/' + selectedUserId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(payload)
    })
    .then(r => {
        if (!r.ok) {
            return r.json().then(errData => {
                let errMsg = 'Validation failed: ';
                if (errData.errors) {
                    errMsg += Object.values(errData.errors).flat().join(' ');
                } else {
                    errMsg += errData.message || 'Unknown error';
                }
                throw new Error(errMsg);
            });
        }
        return r.json();
    })
    .then(data => {
        if (data.success) {
            showToast('success', 'User login password reset completed.');
            changePasswordModal.hide();
        } else {
            showToast('danger', data.error || 'Failed to reset password.');
        }
    })
    .catch(err => {
        showToast('danger', err.message || 'Validation errors or connection failure.');
    });
}

function submitClonePermissions(e) {
    e.preventDefault();
    if (!selectedUserId) return;
    const sourceId = document.getElementById('clone-source-user-id').value;

    fetch('/api/users/permissions/clone', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            target_user_id: selectedUserId,
            source_user_id: sourceId
        })
    })
    .then(r => {
        if (!r.ok) {
            return r.json().then(errData => {
                throw new Error(errData.message || 'Clone failed.');
            });
        }
        return r.json();
    })
    .then(data => {
        if (data.success) {
            showToast('success', 'User permissions cloned successfully.');
            clonePermissionsModal.hide();
            fetchUserPermissions(selectedUserId);
        } else {
            showToast('danger', data.error || 'Failed to clone permissions.');
        }
    })
    .catch(err => {
        showToast('danger', err.message || 'Failed to clone permissions.');
    });
}

function savePageToken(pageId, btn) {
    const row = btn.closest('tr');
    const tokenInput = row.querySelector('.page-token-input');
    const tokenVal = tokenInput.value;

    btn.disabled = true;
    tokenInput.disabled = true;

    fetch('/api/pages/update-token', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            page_id: pageId,
            token: tokenVal
        })
    })
    .then(r => {
        if (!r.ok) {
            return r.json().then(errData => {
                throw new Error(errData.error || errData.message || 'Failed to update token');
            });
        }
        return r.json();
    })
    .then(data => {
        if (data.success) {
            showToast('success', 'Page token updated successfully.');
            tokenInput.value = data.token;
        } else {
            showToast('danger', data.error || 'Failed to update token.');
        }
    })
    .catch(err => {
        showToast('danger', err.message || 'Failed to update token.');
    })
    .finally(() => {
        btn.disabled = false;
        tokenInput.disabled = false;
    });
}

function loadStaffConsole() {
    navigateToSlug('staff-profiles');
}

// Bind functions to window object for inline HTML event handlers (e.g. onclick) when executed inside an IIFE
window.filterUserList = filterUserList;
window.selectUser = selectUser;
window.toggleUserPermission = toggleUserPermission;
window.openCreateUserModal = openCreateUserModal;
window.editSelectedUser = editSelectedUser;
window.openChangePasswordModal = openChangePasswordModal;
window.openCloneModal = openCloneModal;
window.submitCreateUser = submitCreateUser;
window.submitEditUser = submitEditUser;
window.submitChangePassword = submitChangePassword;
window.submitClonePermissions = submitClonePermissions;
window.savePageToken = savePageToken;
window.loadStaffConsole = loadStaffConsole;
