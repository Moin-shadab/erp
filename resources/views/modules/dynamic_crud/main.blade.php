<div class="container-fluid p-0">
    <!-- Page Header -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h4 class="fw-bold mb-1">{{ $pageConfig->title ?: $pageConfig->name }}</h4>
            <p class="text-muted small mb-0">Manage and coordinate records for {{ strtolower($pageConfig->name) }}.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div class="d-inline-flex gap-2">
                @if($permissions['can_create'])
                    <button class="btn btn-primary btn-sm d-flex align-items-center" onclick="openCreateModal()">
                        <i class="bi bi-plus-lg me-1"></i> Add Record
                    </button>
                    <button class="btn btn-light border btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="bi bi-file-earmark-arrow-up me-1"></i> Import CSV
                    </button>
                @endif
                @if($permissions['can_export'])
                    <button class="btn btn-light border btn-sm d-flex align-items-center" onclick="exportData()">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i> Export CSV
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Grid Filter & Search Panel -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-start-0" id="grid-search" placeholder="Search grid data..." oninput="onGridSearch()">
                    </div>
                </div>
                <!-- Dynamic Filters will be handled by AG Grid built-in filters -->
                <div class="col-md-8 text-md-end">
                    <span class="text-muted small">Double-click headers to apply precise column filters.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- AG Grid Container -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div id="crudGrid" class="ag-theme-alpine" style="height: 480px; width: 100%;"></div>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- DYNAMIC CRUD FORM MODAL -->
<!-- ============================================== -->
<div class="modal fade" id="crudModal" tabindex="-1" aria-labelledby="crudModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form id="crudForm" onsubmit="saveRecord(event)">
                <input type="hidden" id="record-id" name="id">
                <div class="modal-header border-bottom-0 py-3 px-4">
                    <h5 class="fw-bold mb-0" id="crudModalLabel">Create Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-0">
                    <div class="row">
                        @foreach(json_decode($pageConfig->form_schema, true) ?? [] as $field)
                            @php
                                $fieldName = $field['name'];
                                $fieldLabel = $field['label'] ?? $fieldName;
                                $fieldType = $field['type'] ?? 'text';
                                $colWidth = $field['grid_width'] ?? 12;
                                $options = $field['options'] ?? [];
                            @endphp
                            <div class="col-md-{{ $colWidth }} mb-3">
                                <label for="field-{{ $fieldName }}" class="form-label small fw-bold text-muted mb-1">{{ $fieldLabel }}</label>
                                
                                @if($fieldType === 'select')
                                    <select class="form-select form-select-sm" id="field-{{ $fieldName }}" name="{{ $fieldName }}">
                                        <option value="">Select {{ $fieldLabel }}</option>
                                        @foreach($options as $opt)
                                            @php
                                                $optVal = is_array($opt) ? ($opt['value'] ?? '') : ($opt->value ?? $opt);
                                                $optLbl = is_array($opt) ? ($opt['label'] ?? '') : ($opt->label ?? $opt);
                                            @endphp
                                            <option value="{{ $optVal }}">{{ $optLbl }}</option>
                                        @endforeach
                                    </select>
                                @elseif($fieldType === 'textarea')
                                    <textarea class="form-control form-control-sm" id="field-{{ $fieldName }}" name="{{ $fieldName }}" rows="3" placeholder="Enter {{ strtolower($fieldLabel) }}"></textarea>
                                @elseif($fieldType === 'password')
                                    <input type="password" class="form-control form-control-sm" id="field-{{ $fieldName }}" name="{{ $fieldName }}" placeholder="Enter password (leave blank if updating)">
                                @else
                                    <input type="{{ $fieldType }}" class="form-control form-control-sm" id="field-{{ $fieldName }}" name="{{ $fieldName }}" placeholder="Enter {{ strtolower($fieldLabel) }}">
                                @endif
                                <div class="invalid-feedback" id="feedback-{{ $fieldName }}"></div>
                            </div>
                        @endforeach
                        @if($pageConfig->slug === 'customers')
                            <div class="col-md-12 mb-3 border-top pt-3 mt-2">
                                <label class="form-label small fw-bold text-muted mb-1 d-block text-primary">Sharing & Visibility Permissions</label>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="field-share_with_everyone" name="share_with_everyone" value="1">
                                    <label class="form-check-label small text-muted fw-semibold" for="field-share_with_everyone">Share with everyone (all sales reps and departments)</label>
                                </div>
                                <div id="specific-sales-reps-container" class="mt-2">
                                    <label class="form-label small fw-bold text-muted mb-1 d-block">Share with specific Sales Representatives</label>
                                    <div id="sales-reps-checkboxes" class="d-flex flex-wrap gap-3 p-2 bg-light rounded border border-light-subtle">
                                        <!-- Will be dynamically populated via JS -->
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer border-top-0 py-3 px-4">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3" id="save-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- CSV IMPORT MODAL -->
<!-- ============================================== -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form id="importForm" onsubmit="submitImport(event)" enctype="multipart/form-data">
                <div class="modal-header border-bottom-0 py-3 px-4">
                    <h5 class="fw-bold mb-0" id="importModalLabel">Import Data from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4">
                    <p class="text-muted small">Please select a valid `.csv` file. Ensure columns match the configuration schema attributes exactly.</p>
                    <div class="mb-3">
                        <label for="import-file" class="form-label fw-bold text-muted small">CSV File</label>
                        <input class="form-control form-control-sm" type="file" id="import-file" name="file" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 py-3 px-4">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3" id="import-submit-btn">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
