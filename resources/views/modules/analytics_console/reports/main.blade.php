<div class="container-fluid p-0" style="height: calc(100vh - 100px); overflow: hidden;">
    <div class="row g-3 h-100">
        <!-- Left Column: Saved Reports -->
        <div class="col-md-3 border-end pe-3 h-100 overflow-y-auto">
            <h6 class="fw-bold mb-3"><i class="bi bi-folder2-open me-2 text-primary"></i>Saved Layouts</h6>
            <div class="list-group list-group-flush small" id="saved-reports-list">
                @forelse($savedReports as $rep)
                <button class="list-group-item list-group-item-action border-0 rounded-2 py-2 mb-1 d-flex justify-content-between align-items-center" onclick="loadSavedReport({{ json_encode($rep) }})">
                    <span><i class="bi bi-file-earmark-bar-graph me-2 text-secondary"></i> {{ $rep->name }}</span>
                    <i class="bi bi-trash text-danger delete-rep-btn" onclick="event.stopPropagation(); deleteSavedReport({{ $rep->id }})"></i>
                </button>
                @empty
                <div class="text-muted p-3 text-center">No saved layouts.</div>
                @endforelse
            </div>
        </div>

        <!-- Right Column: Builder Controls & Output -->
        <div class="col-md-9 d-flex flex-column h-100 ps-3 overflow-hidden">
            <!-- Builder Parameters Card -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold mb-0">Dynamic Report Builder</h5>
                </div>
                <div class="card-body p-3 pt-0" style="font-size:0.85rem;">
                    <div class="row g-3">
                        <!-- Table Select -->
                        <div class="col-md-4">
                            <label for="rep-table" class="form-label fw-bold text-muted small mb-1">Select Data Source</label>
                            <select class="form-select form-select-sm" id="rep-table" onchange="onTableSelected()">
                                <option value="">Select source...</option>
                                @foreach($tables as $key => $lbl)
                                    <option value="{{ $key }}">{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Saved Config Id (hidden) -->
                        <input type="hidden" id="saved-report-id">

                        <!-- Report Name (for saving) -->
                        <div class="col-md-5">
                            <label for="rep-name" class="form-label fw-bold text-muted small mb-1">Report Name (for saving)</label>
                            <input type="text" class="form-control form-control-sm" id="rep-name" placeholder="E.g., High Value Invoices Q2">
                        </div>

                        <!-- Action Buttons -->
                        <div class="col-md-3 d-flex align-items-end gap-1">
                            <button class="btn btn-primary btn-sm flex-grow-1" onclick="generateReport()"><i class="bi bi-gear-wide-connected"></i> Run</button>
                            <button class="btn btn-light border btn-sm flex-grow-1" onclick="saveReport()"><i class="bi bi-floppy"></i> Save</button>
                            <button class="btn btn-light border btn-sm" onclick="resetBuilder()" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></button>
                        </div>
                    </div>

                    <!-- Columns Selection Section -->
                    <div class="mt-3 d-none" id="columns-section">
                        <label class="form-label fw-bold text-muted small mb-1 d-block">Choose Fields to Include</label>
                        <div class="d-flex flex-wrap gap-2 border rounded p-2 bg-light" id="columns-container" style="max-height: 80px; overflow-y: auto;">
                            <!-- Populated dynamically -->
                        </div>
                    </div>

                    <!-- Filters Selection Section -->
                    <div class="mt-3 d-none" id="filters-section">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold text-muted small mb-0">Query Filters</label>
                            <button class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:0.75rem;" onclick="addFilterRow()"><i class="bi bi-plus-lg"></i> Add Filter</button>
                        </div>
                        <div id="filters-container" class="d-flex flex-column gap-2" style="max-height: 110px; overflow-y: auto;">
                            <!-- Populated dynamically -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Grid Output -->
            <div class="flex-grow-1 d-flex flex-column overflow-hidden">
                <div id="report-grid-placeholder" class="d-flex flex-column align-items-center justify-content-center h-100 text-muted border border-dashed rounded-3 bg-white">
                    <i class="bi bi-grid-3x3-gap fs-1 mb-2 text-secondary"></i>
                    <p class="small">Configure data source and run the query to see the report.</p>
                </div>

                <div id="report-grid-container" class="d-none flex-grow-1 border rounded-3 overflow-hidden shadow-sm">
                    <div id="reportGrid" class="ag-theme-alpine" style="height: 100%; width: 100%;"></div>
                </div>
            </div>
        </div>
    </div>
</div>
