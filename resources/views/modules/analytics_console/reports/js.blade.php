var reportGridOptions = null;
var tableColumns = [];

// Triggered when table datasource is switched
function onTableSelected() {
    const table = document.getElementById('rep-table').value;
    const colsSection = document.getElementById('columns-section');
    const filtsSection = document.getElementById('filters-section');
    const colsContainer = document.getElementById('columns-container');
    const filtsContainer = document.getElementById('filters-container');

    if (!table) {
        colsSection.classList.add('d-none');
        filtsSection.classList.add('d-none');
        return;
    }

    // Fetch columns via API
    fetch('/api/reports/columns/' + table)
        .then(r => r.json())
        .then(data => {
            tableColumns = data.columns;
            
            // Render columns checkboxes
            colsContainer.innerHTML = '';
            tableColumns.forEach(col => {
                const div = document.createElement('div');
                div.className = 'form-check form-check-inline mb-0';
                div.innerHTML = `
                    <input class="form-check-input rep-col-check" type="checkbox" id="chk-${col}" value="${col}" checked>
                    <label class="form-check-input-label small" for="chk-${col}">${col}</label>
                `;
                colsContainer.appendChild(div);
            });

            colsSection.classList.remove('d-none');
            filtsSection.classList.remove('d-none');

            // Clear previous filters and add one default row
            filtsContainer.innerHTML = '';
            addFilterRow();
        });
}

// Add filter row block
function addFilterRow(savedCol = null, savedOp = null, savedVal = null) {
    const container = document.getElementById('filters-container');
    const rowId = 'filter-row-' + Date.now() + Math.random().toString(36).substr(2, 5);

    const row = document.createElement('div');
    row.className = 'd-flex gap-2 align-items-center filter-row';
    row.id = rowId;

    // Column select
    let colOpts = '';
    tableColumns.forEach(c => {
        colOpts += `<option value="${c}" ${c === savedCol ? 'selected' : ''}>${c}</option>`;
    });

    row.innerHTML = `
        <select class="form-select form-select-sm filter-col" style="max-width:180px;">
            <option value="">Choose Column...</option>
            ${colOpts}
        </select>
        <select class="form-select form-select-sm filter-op" style="max-width:130px;">
            <option value="eq" ${savedOp === 'eq' ? 'selected' : ''}>Equals (=)</option>
            <option value="neq" ${savedOp === 'neq' ? 'selected' : ''}>Not Equals (<>)</option>
            <option value="like" ${savedOp === 'like' ? 'selected' : ''}>Contains (LIKE)</option>
            <option value="gt" ${savedOp === 'gt' ? 'selected' : ''}>Greater Than (&gt;)</option>
            <option value="lt" ${savedOp === 'lt' ? 'selected' : ''}>Less Than (&lt;)</option>
        </select>
        <input type="text" class="form-control form-control-sm filter-val" placeholder="Value..." value="${savedVal !== null ? savedVal : ''}">
        <button class="btn btn-sm btn-light border text-danger" onclick="document.getElementById('${rowId}').remove()"><i class="bi bi-trash"></i></button>
    `;

    container.appendChild(row);
}

// Reset Builder inputs
function resetBuilder() {
    document.getElementById('rep-table').value = '';
    document.getElementById('rep-name').value = '';
    document.getElementById('saved-report-id').value = '';
    document.getElementById('columns-section').classList.add('d-none');
    document.getElementById('filters-section').classList.add('d-none');
    
    // Hide Grid
    document.getElementById('report-grid-container').classList.add('d-none');
    document.getElementById('report-grid-placeholder').classList.remove('d-none');
}

// Run report
function generateReport() {
    const table = document.getElementById('rep-table').value;
    if (!table) {
        showToast('danger', 'Please choose a data source table.');
        return;
    }

    // Get selected columns
    const cols = [];
    document.querySelectorAll('.rep-col-check:checked').forEach(c => {
        cols.push(c.value);
    });

    if (cols.length === 0) {
        showToast('danger', 'Please select at least one column.');
        return;
    }

    // Get filters
    const filters = [];
    document.querySelectorAll('.filter-row').forEach(row => {
        const col = row.querySelector('.filter-col').value;
        const op = row.querySelector('.filter-op').value;
        const val = row.querySelector('.filter-val').value;

        if (col && op) {
            filters.push({ column: col, operator: op, value: val });
        }
    });

    // Toggle Grid display
    document.getElementById('report-grid-placeholder').classList.add('d-none');
    const gridCont = document.getElementById('report-grid-container');
    gridCont.classList.remove('d-none');

    // Fetch Grid script if needed
    if (typeof agGrid === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/ag-grid-community@30.2.1/dist/ag-grid-community.min.js';
        script.onload = () => execReportFetch(table, cols, filters);
        document.head.appendChild(script);
    } else {
        execReportFetch(table, cols, filters);
    }
}

function execReportFetch(table, columns, filters) {
    const gridDiv = document.querySelector('#reportGrid');
    
    // Destroy existing grid if present
    if (reportGridOptions && reportGridOptions.api) {
        reportGridOptions.api.destroy();
    }

    // Build columns definitions
    const colDefs = [];
    columns.forEach(c => {
        colDefs.push({
            field: c,
            headerName: c,
            sortable: true,
            filter: true,
            resizable: true,
            flex: 1
        });
    });

    reportGridOptions = {
        columnDefs: colDefs,
        pagination: true,
        paginationPageSize: 15,
        rowHeight: 40,
        defaultColDef: { resizable: true }
    };

    new agGrid.Grid(gridDiv, reportGridOptions);
    if (reportGridOptions.api) {
        reportGridOptions.api.showLoadingOverlay();
    }

    fetch('/api/reports/generate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ table: table, columns: columns, filters: filters })
    })
    .then(r => r.json())
    .then(data => {
        if (reportGridOptions && reportGridOptions.api) {
            if (data.error) {
                showToast('danger', data.error);
                reportGridOptions.api.setRowData([]);
            } else {
                reportGridOptions.api.setRowData(data.data);
            }
            reportGridOptions.api.hideOverlay();
        }
    })
    .catch(err => {
        if (reportGridOptions && reportGridOptions.api) {
            reportGridOptions.api.setRowData([]);
            reportGridOptions.api.hideOverlay();
        }
        showToast('danger', 'Error executing query.');
    });
}

// Save report configuration layout
function saveReport() {
    const name = document.getElementById('rep-name').value.trim();
    const table = document.getElementById('rep-table').value;
    const id = document.getElementById('saved-report-id').value;

    if (!name || !table) {
        showToast('danger', 'Ensure Report Name and Data Source are configured.');
        return;
    }

    const cols = [];
    document.querySelectorAll('.rep-col-check:checked').forEach(c => {
        cols.push(c.value);
    });

    const filters = [];
    document.querySelectorAll('.filter-row').forEach(row => {
        const col = row.querySelector('.filter-col').value;
        const op = row.querySelector('.filter-op').value;
        const val = row.querySelector('.filter-val').value;
        if (col && op) {
            filters.push({ column: col, operator: op, value: val });
        }
    });

    fetch('/api/reports/save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ id: id, name: name, table: table, columns: cols, filters: filters })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Report configuration layout saved.');
            loadReportBuilder(); // Refresh reporting panel
        }
    });
}

// Load saved report config
function loadSavedReport(rep) {
    document.getElementById('saved-report-id').value = rep.id;
    document.getElementById('rep-name').value = rep.name;
    document.getElementById('rep-table').value = rep.base_table;

    // Fetch columns and set up choices
    fetch('/api/reports/columns/' + rep.base_table)
        .then(r => r.json())
        .then(data => {
            tableColumns = data.columns;
            
            const colsSection = document.getElementById('columns-section');
            const filtsSection = document.getElementById('filters-section');
            const colsContainer = document.getElementById('columns-container');
            const filtsContainer = document.getElementById('filters-container');

            colsSection.classList.remove('d-none');
            filtsSection.classList.remove('d-none');

            // Set column checkmarks
            const savedCols = JSON.parse(rep.columns) || [];
            colsContainer.innerHTML = '';
            tableColumns.forEach(col => {
                const isChecked = savedCols.includes(col) ? 'checked' : '';
                const div = document.createElement('div');
                div.className = 'form-check form-check-inline mb-0';
                div.innerHTML = `
                    <input class="form-check-input rep-col-check" type="checkbox" id="chk-${col}" value="${col}" ${isChecked}>
                    <label class="form-check-input-label small" for="chk-${col}">${col}</label>
                `;
                colsContainer.appendChild(div);
            });

            // Clear and recreate filters
            filtsContainer.innerHTML = '';
            const savedFilters = JSON.parse(rep.filters) || [];
            if (savedFilters.length === 0) {
                addFilterRow();
            } else {
                savedFilters.forEach(f => {
                    addFilterRow(f.column, f.operator, f.value);
                });
            }

            // Auto-run report
            generateReport();
        });
}

// Delete saved report
function deleteSavedReport(id) {
    if (!confirm('Are you sure you want to delete this saved report?')) return;

    fetch('/api/reports/delete/' + id, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Report configuration deleted.');
            loadReportBuilder(); // Reload view
        }
    });
}

// Bind functions to window object for inline HTML event handlers (e.g. onclick) when executed inside an IIFE
window.onTableSelected = onTableSelected;
window.addFilterRow = addFilterRow;
window.resetBuilder = resetBuilder;
window.generateReport = generateReport;
window.execReportFetch = execReportFetch;
window.saveReport = saveReport;
window.loadSavedReport = loadSavedReport;
window.deleteSavedReport = deleteSavedReport;
