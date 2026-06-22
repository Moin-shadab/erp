var gridOptions = null;
var pageSlug = "{{ $pageConfig->slug }}";
var primaryKey = "{{ $pageConfig->primary_key }}";

// Ensure AG Grid JS is loaded
if (typeof agGrid === 'undefined') {
    const gridScript = document.createElement('script');
    gridScript.src = 'https://cdn.jsdelivr.net/npm/ag-grid-community@30.2.1/dist/ag-grid-community.min.js';
    gridScript.onload = () => initAgGrid();
    document.head.appendChild(gridScript);
} else {
    initAgGrid();
}

function initAgGrid() {
    const gridDiv = document.querySelector('#crudGrid');
    
    // Parse Column structure from page configuration
    const gridSchema = @json(json_decode($pageConfig->grid_schema, true) ?? []);
    
    // Add dynamic Action Column if Edit/Delete permissions exist
    const canEdit = {{ $permissions['can_edit'] ? 'true' : 'false' }};
    const canDelete = {{ $permissions['can_delete'] ? 'true' : 'false' }};

    const columnDefs = [];
    
    gridSchema.forEach(col => {
        columnDefs.push({
            field: col.field,
            headerName: col.headerName || col.field,
            sortable: col.sortable !== false,
            filter: col.filter !== false ? 'agTextColumnFilter' : false,
            resizable: true,
            flex: col.flex || 1
        });
    });

    if (canEdit || canDelete) {
        columnDefs.push({
            headerName: 'Actions',
            field: 'actions',
            sortable: false,
            filter: false,
            resizable: false,
            width: 140,
            pinned: 'right',
            cellRenderer: function(params) {
                if (!params.data) return '';
                const recordId = params.data[primaryKey];
                let html = `<div class="d-flex gap-1 align-items-center h-100">`;
                if (canEdit) {
                    html += `<button class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:0.75rem;" onclick="openEditModal(${JSON.stringify(params.data).replace(/"/g, '&quot;')})"><i class="bi bi-pencil"></i></button>`;
                }
                if (canDelete) {
                    html += `<button class="btn btn-xs btn-outline-danger py-0 px-2" style="font-size:0.75rem;" onclick="deleteRow(${recordId})"><i class="bi bi-trash"></i></button>`;
                }
                html += `</div>`;
                return html;
            }
        });
    }

    gridOptions = {
        columnDefs: columnDefs,
        pagination: true,
        paginationPageSize: 15,
        rowModelType: 'clientSide', // Client side data binding for ease of sorting/filtering
        rowHeight: 40,
        defaultColDef: {
            resizable: true,
            suppressMovable: true
        }
    };

    // Initialize grid
    new agGrid.Grid(gridDiv, gridOptions);
    
    // Load data
    refreshGridData();
}

function refreshGridData() {
    if (!gridOptions) return;

    if (gridOptions.api) {
        gridOptions.api.showLoadingOverlay();
    }
    fetch(`/erp/api/${pageSlug}/data`)
        .then(response => response.json())
        .then(data => {
            if (gridOptions && gridOptions.api) {
                gridOptions.api.setRowData(data.data);
                gridOptions.api.hideOverlay();
            }
        })
        .catch(err => {
            if (gridOptions && gridOptions.api) {
                gridOptions.api.setRowData([]);
                gridOptions.api.hideOverlay();
            }
            showToast('danger', 'Error loading grid details.');
        });
}

// Grid search
function onGridSearch() {
    const val = document.getElementById('grid-search').value;
    if (gridOptions && gridOptions.api) {
        gridOptions.api.setQuickFilter(val);
    }
}

// Modal forms controls
var formModal = new bootstrap.Modal(document.getElementById('crudModal'));
var crudForm = document.getElementById('crudForm');

function openCreateModal() {
    document.getElementById('crudModalLabel').textContent = 'Create New Record';
    document.getElementById('record-id').value = '';
    crudForm.reset();
    
    // Clear invalid classes
    crudForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    formModal.show();
}

function openEditModal(rowData) {
    document.getElementById('crudModalLabel').textContent = 'Modify Record';
    document.getElementById('record-id').value = rowData[primaryKey];
    
    // Populate inputs matching schema name
    const schema = @json(json_decode($pageConfig->form_schema, true) ?? []);
    schema.forEach(field => {
        const input = document.getElementById('field-' + field.name);
        if (input) {
            if (field.type === 'password') {
                input.value = ''; // Don't populate password
            } else {
                input.value = rowData[field.name] !== null ? rowData[field.name] : '';
            }
        }
    });

    // Clear invalid classes
    crudForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

    formModal.show();
}

// Save Record (Create or Edit)
function saveRecord(e) {
    e.preventDefault();

    const recordId = document.getElementById('record-id').value;
    const formData = new FormData(crudForm);
    const data = Object.fromEntries(formData.entries());

    if (pageSlug === 'customers') {
        const checkedReps = [];
        document.querySelectorAll('#sales-reps-checkboxes input[type="checkbox"]:checked').forEach(cb => {
            checkedReps.push(parseInt(cb.value));
        });
        data.shared_user_ids = checkedReps;
        data.share_with_everyone = document.getElementById('field-share_with_everyone').checked ? 1 : 0;
    }

    const url = recordId ? `/erp/api/${pageSlug}/update/${recordId}` : `/erp/api/${pageSlug}/store`;
    const method = 'POST'; // We will use POST for updates too, mapping Laravel method spoofing inside request if needed
    
    // Put spoofing parameter for updates in headers or body
    const headers = {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        'Content-Type': 'application/json'
    };

    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    fetch(url, {
        method: method,
        headers: headers,
        body: JSON.stringify(data)
    })
    .then(response => response.json().then(json => ({ status: response.status, body: json })))
    .then(res => {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Changes';

        if (res.status === 422) {
            // Validation error
            displayFormValidationErrors(res.body.errors);
        } else if (res.status === 200) {
            // Success
            showToast('success', res.body.message);
            formModal.hide();
            refreshGridData();
        } else {
            showToast('danger', res.body.error || 'Server error saving record.');
        }
    })
    .catch(err => {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Changes';
        showToast('danger', 'Network connection error.');
    });
}

function displayFormValidationErrors(errors) {
    crudForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    Object.keys(errors).forEach(key => {
        const input = document.getElementById('field-' + key);
        const feedback = document.getElementById('feedback-' + key);
        if (input && feedback) {
            input.classList.add('is-invalid');
            feedback.textContent = errors[key][0];
        }
    });
}

// Delete Row
function deleteRow(recordId) {
    if (!confirm('Are you sure you want to permanently delete this record?')) return;

    fetch(`/erp/api/${pageSlug}/destroy/${recordId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
            refreshGridData();
        } else {
            showToast('danger', data.error || 'Deletion failed.');
        }
    })
    .catch(err => {
        showToast('danger', 'Network error executing delete.');
    });
}

// Export Data CSV
function exportData() {
    window.location.href = `/erp/api/${pageSlug}/export`;
}

// Submit CSV Import
var importModal = new bootstrap.Modal(document.getElementById('importModal'));

function submitImport(e) {
    e.preventDefault();

    const fileInput = document.getElementById('import-file');
    if (!fileInput.files.length) return;

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    const submitBtn = document.getElementById('import-submit-btn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Uploading...';

    fetch(`/erp/api/${pageSlug}/import`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Upload & Import';
        
        if (data.success) {
            showToast('success', data.message);
            importModal.hide();
            refreshGridData();
        } else {
            showToast('danger', data.error || 'Import failed.');
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Upload & Import';
        showToast('danger', 'Network connection error during import.');
    });
}

// Bind functions to window object for inline HTML event handlers (e.g. onclick) when executed inside an IIFE
window.initAgGrid = initAgGrid;
window.refreshGridData = refreshGridData;
window.onGridSearch = onGridSearch;
window.openCreateModal = openCreateModal;
window.openEditModal = openEditModal;
window.saveRecord = saveRecord;
window.deleteRow = deleteRow;
window.exportData = exportData;
window.submitImport = submitImport;

// Special hook for State-Capital-City dependency on Customers page
if (pageSlug === 'customers') {
    const stateSelect = document.getElementById('field-state');
    const citySelect = document.getElementById('field-city');
    const addressTextarea = document.getElementById('field-address');

    var stateCityMapping = {
        'Maharashtra': {
            capital: 'Mumbai',
            cities: ['Mumbai', 'Pune', 'Nagpur', 'Thane', 'Nashik', 'Aurangabad']
        },
        'Karnataka': {
            capital: 'Bengaluru',
            cities: ['Bengaluru', 'Mysuru', 'Hubballi', 'Mangaluru', 'Belagavi']
        },
        'Tamil Nadu': {
            capital: 'Chennai',
            cities: ['Chennai', 'Coimbatore', 'Madurai', 'Tiruchirappalli', 'Salem']
        },
        'Delhi': {
            capital: 'New Delhi',
            cities: ['New Delhi', 'Dwarka', 'Rohini', 'Vasant Kunj']
        },
        'Gujarat': {
            capital: 'Gandhinagar',
            cities: ['Ahmedabad', 'Surat', 'Vadodara', 'Rajkot', 'Gandhinagar']
        },
        'Uttar Pradesh': {
            capital: 'Lucknow',
            cities: ['Lucknow', 'Noida', 'Kanpur', 'Agra', 'Varanasi', 'Ghaziabad']
        },
        'West Bengal': {
            capital: 'Kolkata',
            cities: ['Kolkata', 'Howrah', 'Siliguri', 'Durgapur', 'Asansol']
        },
        'Telangana': {
            capital: 'Hyderabad',
            cities: ['Hyderabad', 'Warangal', 'Nizamabad', 'Karimnagar']
        }
    };

    function populateCities(selectedState, selectedCity = '') {
        if (!citySelect) return;
        citySelect.innerHTML = '<option value="">Select City</option>';
        if (!selectedState || !stateCityMapping[selectedState]) return;

        const data = stateCityMapping[selectedState];
        data.cities.forEach(city => {
            const opt = document.createElement('option');
            opt.value = city;
            opt.textContent = city;
            if (city === selectedCity) {
                opt.selected = true;
            }
            citySelect.appendChild(opt);
        });
    }

    if (stateSelect) {
        stateSelect.addEventListener('change', function() {
            const state = this.value;
            populateCities(state);
            
            if (state && stateCityMapping[state] && addressTextarea) {
                const data = stateCityMapping[state];
                if (!addressTextarea.value.trim()) {
                    addressTextarea.value = data.capital + ', ' + state + ', India';
                }
            }
        });
    }

    const shareWithEveryoneSwitch = document.getElementById('field-share_with_everyone');
    const repsContainer = document.getElementById('specific-sales-reps-container');
    const repsCheckboxesDiv = document.getElementById('sales-reps-checkboxes');

    if (shareWithEveryoneSwitch) {
        shareWithEveryoneSwitch.addEventListener('change', function() {
            if (this.checked) {
                repsContainer.style.opacity = '0.5';
                repsContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                    cb.disabled = true;
                    cb.checked = false;
                });
            } else {
                repsContainer.style.opacity = '1';
                repsContainer.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                    cb.disabled = false;
                });
            }
        });
    }

    function loadRepsAndBind(selectedSharedIds = [], shareWithEveryone = false) {
        if (!repsCheckboxesDiv) return;
        repsCheckboxesDiv.innerHTML = '<span class="text-muted small">Loading sales reps...</span>';

        fetch('/erp/api/my-sales-reps')
            .then(r => r.json())
            .then(reps => {
                repsCheckboxesDiv.innerHTML = '';
                if (reps.length === 0) {
                    repsCheckboxesDiv.innerHTML = '<span class="text-muted small">No subordinates found to share with.</span>';
                    return;
                }
                reps.forEach(rep => {
                    const id = rep.id;
                    const name = rep.name;
                    const isChecked = selectedSharedIds.includes(id);

                    const div = document.createElement('div');
                    div.className = 'form-check';
                    div.innerHTML = `
                        <input class="form-check-input rep-share-cb" type="checkbox" value="${id}" id="share-rep-${id}" ${isChecked ? 'checked' : ''} ${shareWithEveryone ? 'disabled' : ''}>
                        <label class="form-check-label small text-muted" for="share-rep-${id}">${name}</label>
                    `;
                    repsCheckboxesDiv.appendChild(div);
                });
            })
            .catch(err => {
                repsCheckboxesDiv.innerHTML = '<span class="text-danger small">Error loading representatives.</span>';
            });
    }

    // Intercept modal loaders to handle dropdown and sharing bindings
    const originalOpenEditModal = window.openEditModal;
    window.openEditModal = function(rowData) {
        originalOpenEditModal(rowData);
        if (rowData.state) {
            populateCities(rowData.state, rowData.city);
        }

        // Fetch customer shares
        fetch('/erp/api/customers/' + rowData[primaryKey] + '/shares')
            .then(r => r.json())
            .then(data => {
                const shareWithEveryone = !!data.share_with_everyone;
                if (shareWithEveryoneSwitch) {
                    shareWithEveryoneSwitch.checked = shareWithEveryone;
                }
                if (repsContainer) {
                    repsContainer.style.opacity = shareWithEveryone ? '0.5' : '1';
                }
                loadRepsAndBind(data.shared_user_ids, shareWithEveryone);
            });
    };

    const originalOpenCreateModal = window.openCreateModal;
    window.openCreateModal = function() {
        originalOpenCreateModal();
        if (citySelect) {
            citySelect.innerHTML = '<option value="">Select City</option>';
        }

        if (shareWithEveryoneSwitch) {
            shareWithEveryoneSwitch.checked = false;
        }
        if (repsContainer) {
            repsContainer.style.opacity = '1';
        }
        loadRepsAndBind([], false);
    };
}
