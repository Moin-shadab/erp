<div class="container-fluid p-0">
    <!-- Page Header -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h4 class="fw-bold mb-1"><i class="bi bi-shield-lock me-2 text-primary"></i>Role Permissions Matrix</h4>
            <p class="text-muted small mb-0">Control module and action level access scopes across the organizational hierarchy.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div class="d-inline-flex align-items-center justify-content-md-end gap-2 w-100">
                <label for="role-select" class="fw-bold text-muted small text-nowrap mb-0">Select Target Role:</label>
                <select class="form-select form-select-sm w-50 border fw-bold text-primary" id="role-select" onchange="onRoleChanged(this.value)">
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ $role->id == $activeRoleId ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Permissions Matrix Card Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:0.85rem;">
                    <thead>
                        <tr class="table-light">
                            <th class="py-3 ps-4" style="width: 250px;">Navigation Page</th>
                            <th class="text-center py-3">View</th>
                            <th class="text-center py-3">Create</th>
                            <th class="text-center py-3">Edit</th>
                            <th class="text-center py-3">Delete</th>
                            <th class="text-center py-3">Export</th>
                            <th class="text-center py-3">Print</th>
                            <th class="text-center py-3">Approve</th>
                            <th class="text-center py-3">Reject</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($modules as $mod)
                            @php
                                // Get pages for this module
                                $modPages = $pages->where('module_id', $mod->id);
                            @endphp
                            
                            @if($modPages->isNotEmpty())
                                <!-- Module Header Row -->
                                <tr class="table-secondary-subtle">
                                    <td colspan="9" class="fw-bold ps-4 text-primary">
                                        <i class="bi {{ $mod->icon ?: 'bi-folder' }} me-2"></i> {{ $mod->name }}
                                    </td>
                                </tr>

                                @foreach($modPages as $p)
                                    @php
                                        // Get permission for this page
                                        $perm = $rolePermissions->get($p->id);
                                    @endphp
                                    <tr>
                                        <td class="ps-5 fw-semibold">{{ $p->name }}</td>
                                        
                                        <!-- View Checkbox -->
                                        <td class="text-center">
                                            <div class="form-check d-inline-block">
                                                <input class="form-check-input" type="checkbox" 
                                                       data-role-id="{{ $activeRoleId }}" data-page-id="{{ $p->id }}" data-action="can_view"
                                                       onchange="updatePermissionCheckbox(this)"
                                                       {{ $perm && $perm->can_view ? 'checked' : '' }}>
                                            </div>
                                        </td>
                                        
                                        <!-- Create Checkbox -->
                                        <td class="text-center">
                                            <div class="form-check d-inline-block">
                                                <input class="form-check-input" type="checkbox" 
                                                       data-role-id="{{ $activeRoleId }}" data-page-id="{{ $p->id }}" data-action="can_create"
                                                       onchange="updatePermissionCheckbox(this)"
                                                       {{ $perm && $perm->can_create ? 'checked' : '' }}>
                                            </div>
                                        </td>
                                        
                                        <!-- Edit Checkbox -->
                                        <td class="text-center">
                                            <div class="form-check d-inline-block">
                                                <input class="form-check-input" type="checkbox" 
                                                       data-role-id="{{ $activeRoleId }}" data-page-id="{{ $p->id }}" data-action="can_edit"
                                                       onchange="updatePermissionCheckbox(this)"
                                                       {{ $perm && $perm->can_edit ? 'checked' : '' }}>
                                            </div>
                                        </td>
                                        
                                        <!-- Delete Checkbox -->
                                        <td class="text-center">
                                            <div class="form-check d-inline-block">
                                                <input class="form-check-input" type="checkbox" 
                                                       data-role-id="{{ $activeRoleId }}" data-page-id="{{ $p->id }}" data-action="can_delete"
                                                       onchange="updatePermissionCheckbox(this)"
                                                       {{ $perm && $perm->can_delete ? 'checked' : '' }}>
                                            </div>
                                        </td>
                                        
                                        <!-- Export Checkbox -->
                                        <td class="text-center">
                                            <div class="form-check d-inline-block">
                                                <input class="form-check-input" type="checkbox" 
                                                       data-role-id="{{ $activeRoleId }}" data-page-id="{{ $p->id }}" data-action="can_export"
                                                       onchange="updatePermissionCheckbox(this)"
                                                       {{ $perm && $perm->can_export ? 'checked' : '' }}>
                                            </div>
                                        </td>
                                        
                                        <!-- Print Checkbox -->
                                        <td class="text-center">
                                            <div class="form-check d-inline-block">
                                                <input class="form-check-input" type="checkbox" 
                                                       data-role-id="{{ $activeRoleId }}" data-page-id="{{ $p->id }}" data-action="can_print"
                                                       onchange="updatePermissionCheckbox(this)"
                                                       {{ $perm && $perm->can_print ? 'checked' : '' }}>
                                            </div>
                                        </td>
                                        
                                        <!-- Approve Checkbox -->
                                        <td class="text-center">
                                            <div class="form-check d-inline-block">
                                                <input class="form-check-input" type="checkbox" 
                                                       data-role-id="{{ $activeRoleId }}" data-page-id="{{ $p->id }}" data-action="can_approve"
                                                       onchange="updatePermissionCheckbox(this)"
                                                       {{ $perm && $perm->can_approve ? 'checked' : '' }}>
                                            </div>
                                        </td>
                                        
                                        <!-- Reject Checkbox -->
                                        <td class="text-center">
                                            <div class="form-check d-inline-block">
                                                <input class="form-check-input" type="checkbox" 
                                                       data-role-id="{{ $activeRoleId }}" data-page-id="{{ $p->id }}" data-action="can_reject"
                                                       onchange="updatePermissionCheckbox(this)"
                                                       {{ $perm && $perm->can_reject ? 'checked' : '' }}>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
