<div class="container-fluid p-0 d-flex flex-column" style="height: calc(100vh - 100px); overflow: hidden;">
    @php
        $user = Auth::user();
        $isSuperAdmin = DB::table('roles')->where('id', $user->role_id)->value('slug') === 'super-admin';
    @endphp

    @if($isSuperAdmin)
        <!-- Super Admin Tabs Navigation -->
        <ul class="nav nav-tabs mb-3 border-bottom-0" id="userMgmtTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold py-2 px-3 border-0 border-bottom" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-pane" type="button" role="tab" aria-controls="users-pane" aria-selected="true">
                    <i class="bi bi-people me-2"></i>Staff User Directory
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold py-2 px-3 border-0 border-bottom" id="tokens-tab" data-bs-toggle="tab" data-bs-target="#tokens-pane" type="button" role="tab" aria-controls="tokens-pane" aria-selected="false">
                    <i class="bi bi-shield-check me-2"></i>Modules & Page Tokens
                </button>
            </li>
        </ul>
    @endif

    <div class="tab-content flex-grow-1 overflow-hidden" id="userMgmtTabContent">
        <!-- Tab 1: Users Directory -->
        <div class="tab-pane show active h-100 overflow-hidden" id="users-pane" role="tabpanel" aria-labelledby="users-tab">
            <div class="row g-3 h-100 m-0">
                <!-- Left Panel: Users list -->
                <div class="col-md-5 border-end pe-3 h-100 d-flex flex-column overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-people me-2"></i>Users Directory</h5>
                        <button class="btn btn-primary btn-sm" onclick="openCreateUserModal()">
                            <i class="bi bi-plus-lg me-1"></i> Create User
                        </button>
                    </div>
                    
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="user-search" class="form-control" placeholder="Search by name, email, role..." onkeyup="filterUserList()">
                    </div>

                    <!-- Users List Container -->
                    <div class="flex-grow-1 overflow-y-auto" id="user-list-container">
                        <div class="list-group list-group-flush gap-2">
                            @foreach($users as $usr)
                            <div class="list-group-item list-group-item-action border rounded-3 p-3 shadow-sm user-card" 
                                 id="user-card-{{ $usr->id }}" 
                                 data-id="{{ $usr->id }}"
                                 data-name="{{ strtolower($usr->name) }}"
                                 data-email="{{ strtolower($usr->email) }}"
                                 data-role-name="{{ strtolower($usr->role_name) }}"
                                 data-role-id="{{ $usr->role_id }}"
                                 data-reports-to="{{ $usr->reports_to_id }}"
                                 data-active="{{ $usr->is_active }}"
                                 data-send-anyone="{{ $usr->can_send_to_anyone ? 1 : 0 }}"
                                 style="cursor: pointer; transition: all 0.2s;"
                                 onclick="selectUser({{ $usr->id }})">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="fw-bold mb-1 user-name-text">{{ $usr->name }}</h6>
                                        <small class="text-muted d-block"><i class="bi bi-envelope me-1"></i>{{ $usr->email }}</small>
                                    </div>
                                    <span class="badge bg-{{ $usr->is_active ? 'success-subtle text-success' : 'danger-subtle text-danger' }} px-2 py-1">
                                        {{ $usr->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top text-muted small">
                                    <span><i class="bi bi-shield-lock me-1"></i>{{ $usr->role_name }}</span>
                                    <span><i class="bi bi-person-badge me-1"></i>Mgr: {{ $usr->manager_name ?: 'None' }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Right Panel: Configurations & Permissions -->
                <div class="col-md-7 h-100 d-flex flex-column overflow-hidden ps-3" id="config-panel">
                    <!-- No Selection State -->
                    <div id="no-selection-state" class="d-flex flex-column align-items-center justify-content-center h-100 text-muted border border-dashed rounded-3 bg-white">
                        <i class="bi bi-person-gear fs-1 mb-2 text-secondary"></i>
                        <p class="small">Select a staff user from the directory to edit settings, reset passwords, or manage module/page permissions.</p>
                    </div>

                    <!-- Configuration & Permissions Console (Hidden by default) -->
                    <div id="user-console" class="d-none flex-grow-1 d-flex flex-column overflow-hidden">
                        <!-- User Profile Header Summary Card -->
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body p-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted small fw-semibold">SELECTED USER</span>
                                    <h4 class="fw-bold text-primary mb-0" id="console-user-name">-</h4>
                                    <small class="text-muted" id="console-user-email">-</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-primary btn-sm" onclick="editSelectedUser()">
                                        <i class="bi bi-pencil me-1"></i> Edit Details
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm" onclick="openChangePasswordModal()">
                                        <i class="bi bi-key me-1"></i> Password
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="openCloneModal()">
                                        <i class="bi bi-copy me-1"></i> Clone Access
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs Container -->
                        <div class="card border-0 shadow-sm flex-grow-1 d-flex flex-column overflow-hidden">
                            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                                <span class="fw-bold"><i class="bi bi-grid-3x3-gap text-primary me-2"></i>Page & Module Permissions Matrix</span>
                                <span class="badge bg-primary-subtle text-primary" id="permissions-count-badge">Override Active</span>
                            </div>
                            <div class="card-body p-3 flex-grow-1 overflow-y-auto">
                                <div id="super-admin-override-notice" class="alert alert-info d-none mb-3" style="font-size:0.85rem;">
                                    <i class="bi bi-info-circle-fill me-2"></i> This user is a <b>Super Admin</b>. Super Admins bypass all permissions verification and have full unrestricted system access.
                                </div>

                                <!-- Permissions Grid Table -->
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" style="font-size:0.8rem;" id="permissions-table">
                                        <thead>
                                            <tr class="table-light">
                                                <th>Module / Page</th>
                                                <th class="text-center">View</th>
                                                <th class="text-center">Create</th>
                                                <th class="text-center">Edit</th>
                                                <th class="text-center">Delete</th>
                                                <th class="text-center">Export</th>
                                                <th class="text-center">Print</th>
                                                <th class="text-center">Approve</th>
                                                <th class="text-center">Reject</th>
                                            </tr>
                                        </thead>
                                        <tbody id="permissions-table-body">
                                            @php $currentModule = ''; @endphp
                                            @foreach($pages as $pg)
                                                @php
                                                    $mod = $modules->firstWhere('id', $pg->module_id);
                                                    $modName = $mod ? $mod->name : 'General';
                                                @endphp
                                                @if($currentModule !== $modName)
                                                    @php $currentModule = $modName; @endphp
                                                    <tr class="table-secondary fw-bold text-uppercase" style="font-size: 0.75rem;">
                                                        <td colspan="9"><i class="bi bi-folder-fill me-2"></i>{{ $modName }}</td>
                                                    </tr>
                                                @endif
                                                <tr data-page-id="{{ $pg->id }}">
                                                    <td class="fw-semibold ps-3">{{ $pg->name }} <span class="text-muted font-monospace small">({{ $pg->slug }})</span></td>
                                                    <td class="text-center"><input type="checkbox" class="form-check-input perm-chk" data-action="can_view" onchange="toggleUserPermission({{ $pg->id }}, 'can_view', this.checked)"></td>
                                                    <td class="text-center"><input type="checkbox" class="form-check-input perm-chk" data-action="can_create" onchange="toggleUserPermission({{ $pg->id }}, 'can_create', this.checked)"></td>
                                                    <td class="text-center"><input type="checkbox" class="form-check-input perm-chk" data-action="can_edit" onchange="toggleUserPermission({{ $pg->id }}, 'can_edit', this.checked)"></td>
                                                    <td class="text-center"><input type="checkbox" class="form-check-input perm-chk" data-action="can_delete" onchange="toggleUserPermission({{ $pg->id }}, 'can_delete', this.checked)"></td>
                                                    <td class="text-center"><input type="checkbox" class="form-check-input perm-chk" data-action="can_export" onchange="toggleUserPermission({{ $pg->id }}, 'can_export', this.checked)"></td>
                                                    <td class="text-center"><input type="checkbox" class="form-check-input perm-chk" data-action="can_print" onchange="toggleUserPermission({{ $pg->id }}, 'can_print', this.checked)"></td>
                                                    <td class="text-center"><input type="checkbox" class="form-check-input perm-chk" data-action="can_approve" onchange="toggleUserPermission({{ $pg->id }}, 'can_approve', this.checked)"></td>
                                                    <td class="text-center"><input type="checkbox" class="form-check-input perm-chk" data-action="can_reject" onchange="toggleUserPermission({{ $pg->id }}, 'can_reject', this.checked)"></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($isSuperAdmin)
            <!-- Tab 2: Modules & Page Tokens -->
            <div class="tab-pane fade h-100 overflow-hidden" id="tokens-pane" role="tabpanel" aria-labelledby="tokens-tab">
                <div class="card border-0 shadow-sm h-100 d-flex flex-column overflow-hidden bg-white">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-shield-check me-2"></i>Modules & Page Tokens Manager</h5>
                            <small class="text-muted">Register custom views or copy-pasted folders as database modules and manage their security page tokens.</small>
                        </div>
                    </div>
                    <div class="card-body p-3 flex-grow-1 overflow-y-auto">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size:0.85rem;">
                                <thead>
                                    <tr class="table-light">
                                        <th style="width: 25%;">Module Name</th>
                                        <th style="width: 25%;">Page Name</th>
                                        <th style="width: 20%;">Page Slug</th>
                                        <th style="width: 15%;">Page Token</th>
                                        <th style="width: 15%;" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $currentMod = ''; @endphp
                                    @foreach($pages as $pg)
                                        @php
                                            $mod = $modules->firstWhere('id', $pg->module_id);
                                            $modName = $mod ? $mod->name : 'General';
                                        @endphp
                                        @if($currentMod !== $modName)
                                            @php $currentMod = $modName; @endphp
                                            <tr class="table-secondary fw-bold text-uppercase" style="font-size: 0.75rem;">
                                                <td colspan="5"><i class="bi bi-folder-fill me-2"></i>{{ $modName }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td class="ps-3 text-muted">{{ $modName }}</td>
                                            <td>
                                                <span class="fw-semibold">{{ $pg->name }}</span>
                                                @if($pg->is_custom)
                                                    <span class="badge bg-info-subtle text-info ms-1 small" style="font-size: 0.65rem;">Custom</span>
                                                @endif
                                                <div class="text-muted small font-monospace" style="font-size: 0.75rem;">View: {{ $pg->custom_view ?: 'dynamic_crud' }}</div>
                                            </td>
                                            <td><code class="small">{{ $pg->slug }}</code></td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm font-monospace page-token-input" 
                                                       value="{{ $pg->token }}" 
                                                       placeholder="Auto-assigned" 
                                                       style="width: 160px; font-weight: bold; color: var(--bs-primary);">
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-primary btn-sm px-3 py-1" onclick="savePageToken({{ $pg->id }}, this)">
                                                    <i class="bi bi-save me-1"></i> Save
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Modal 1: Create New User -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="createUserModalLabel"><i class="bi bi-person-plus text-primary me-2"></i>Create New User Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="create-user-form" onsubmit="submitCreateUser(event)">
                <div class="modal-body" style="font-size:0.85rem;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="new-user-name" class="form-label fw-bold text-muted small">Employee Full Name</label>
                            <input type="text" class="form-control form-control-sm" id="new-user-name" required placeholder="E.g., Rajesh Sharma">
                        </div>
                        <div class="col-md-6">
                            <label for="new-user-email" class="form-label fw-bold text-muted small">Corporate Email Address</label>
                            <input type="email" class="form-control form-control-sm" id="new-user-email" required placeholder="E.g., rajesh@mserp.com">
                        </div>
                        <div class="col-md-6">
                            <label for="new-user-password" class="form-label fw-bold text-muted small">Login Password Credentials</label>
                            <input type="password" class="form-control form-control-sm" id="new-user-password" required placeholder="Min 4 characters">
                        </div>
                        <div class="col-md-6">
                            <label for="new-user-role" class="form-label fw-bold text-muted small">Security Authorization Role</label>
                            <select class="form-select form-select-sm" id="new-user-role" required>
                                @foreach($roles as $rl)
                                    <option value="{{ $rl->id }}">{{ $rl->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="new-user-reports-to" class="form-label fw-bold text-muted small">Reporting Supervisor (Hierarchy)</label>
                            <select class="form-select form-select-sm" id="new-user-reports-to">
                                <option value="">None (Top Level)</option>
                                @foreach($allUsers as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="new-user-active" class="form-label fw-bold text-muted small">Account Enabled Status</label>
                            <select class="form-select form-select-sm" id="new-user-active" required>
                                <option value="1">Active / Enabled</option>
                                <option value="0">Inactive / Deactivated</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-center pt-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="new-user-send-anyone">
                                <label class="form-check-label fw-bold text-muted small" for="new-user-send-anyone">Can Send to Anyone</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <hr class="my-2">
                            <label for="new-user-clone-source" class="form-label fw-bold text-primary small">
                                <i class="bi bi-copy me-1"></i> Copy Access Permissions From Existing User (Optional)
                            </label>
                            <select class="form-select form-select-sm" id="new-user-clone-source">
                                <option value="">Don't Copy (Default to base Role permissions)</option>
                                @foreach($allUsers as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-1">If selected, the new user will instantly replicate all page and module permissions of the chosen user.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Edit Existing User -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editUserModalLabel"><i class="bi bi-pencil-square text-primary me-2"></i>Edit User Account Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="edit-user-form" onsubmit="submitEditUser(event)">
                <div class="modal-body" style="font-size:0.85rem;">
                    <input type="hidden" id="edit-user-id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit-user-name" class="form-label fw-bold text-muted small">Employee Full Name</label>
                            <input type="text" class="form-control form-control-sm" id="edit-user-name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-user-email" class="form-label fw-bold text-muted small">Corporate Email Address</label>
                            <input type="email" class="form-control form-control-sm" id="edit-user-email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-user-role" class="form-label fw-bold text-muted small">Security Authorization Role</label>
                            <select class="form-select form-select-sm" id="edit-user-role" required>
                                @foreach($roles as $rl)
                                    <option value="{{ $rl->id }}">{{ $rl->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-user-reports-to" class="form-label fw-bold text-muted small">Reporting Supervisor (Hierarchy)</label>
                            <select class="form-select form-select-sm" id="edit-user-reports-to">
                                <option value="">None (Top Level)</option>
                                @foreach($allUsers as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-user-active" class="form-label fw-bold text-muted small">Account Enabled Status</label>
                            <select class="form-select form-select-sm" id="edit-user-active" required>
                                <option value="1">Active / Enabled</option>
                                <option value="0">Inactive / Deactivated</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-center pt-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit-user-send-anyone">
                                <label class="form-check-label fw-bold text-muted small" for="edit-user-send-anyone">Can Send to Anyone</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Change Password -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="changePasswordModalLabel"><i class="bi bi-key text-warning me-2"></i>Reset User Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="change-password-form" onsubmit="submitChangePassword(event)">
                <div class="modal-body" style="font-size:0.85rem;">
                    <div class="mb-3">
                        <label for="reset-password-val" class="form-label fw-bold text-muted small">New Login Password</label>
                        <input type="password" class="form-control form-control-sm" id="reset-password-val" required placeholder="Enter new password (min 4 characters)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm"><i class="bi bi-arrow-repeat"></i> Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 4: Clone Permissions -->
<div class="modal fade" id="clonePermissionsModal" tabindex="-1" aria-labelledby="clonePermissionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="clonePermissionsModalLabel"><i class="bi bi-copy text-secondary me-2"></i>Clone Access Permissions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="clone-permissions-form" onsubmit="submitClonePermissions(event)">
                <div class="modal-body" style="font-size:0.85rem;">
                    <div class="alert alert-warning mb-3" style="font-size:0.8rem;">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Warning: Cloning will completely overwrite the selected user's current permissions with the chosen source user's permissions.
                    </div>
                    <div class="mb-3">
                        <label for="clone-source-user-id" class="form-label fw-bold text-muted small">Select Source User</label>
                        <select class="form-select form-select-sm" id="clone-source-user-id" required>
                            <option value="">Choose User...</option>
                            @foreach($allUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2-circle"></i> Clone Permissions</button>
                </div>
            </form>
        </div>
    </div>
</div>
