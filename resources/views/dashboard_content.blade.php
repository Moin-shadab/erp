<div class="container-fluid p-0">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="fw-bold mb-1">Welcome to your Dashboard</h4>
            <p class="text-muted small">Here is a summary of your enterprise activity and system health.</p>
        </div>
    </div>

    <!-- KPI Cards Row -->
    <div class="row mb-4">
        <!-- Sales Card -->
        <div class="col-md-3">
            <div class="card p-3 border-0 bg-white shadow-sm h-100 position-relative overflow-hidden" style="transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold">APPROVED SALES</span>
                        <h3 class="fw-bold mt-1 mb-0">₹{{ number_format($totalSales, 2) }}</h3>
                    </div>
                    <div class="bg-primary-subtle p-3 rounded-3 text-primary">
                        <i class="bi bi-currency-rupee fs-4"></i>
                    </div>
                </div>
                <div class="progress mt-3" style="height: 4px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: 80%" aria-valuenow="80" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>

        <!-- Users Card -->
        <div class="col-md-3">
            <div class="card p-3 border-0 bg-white shadow-sm h-100 position-relative overflow-hidden" style="transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold">ACTIVE USERS</span>
                        <h3 class="fw-bold mt-1 mb-0">{{ $usersCount }}</h3>
                    </div>
                    <div class="bg-success-subtle p-3 rounded-3 text-success">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                </div>
                <div class="progress mt-3" style="height: 4px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 65%" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>

        <!-- Pending Workflows Card -->
        <div class="col-md-3">
            <div class="card p-3 border-0 bg-white shadow-sm h-100 position-relative overflow-hidden" style="transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold">PENDING APPROVALS</span>
                        <h3 class="fw-bold mt-1 mb-0">{{ count($myPendingApprovals) }} <span class="text-muted fs-6">/ {{ $pendingWorkflows }}</span></h3>
                    </div>
                    <div class="bg-warning-subtle p-3 rounded-3 text-warning">
                        <i class="bi bi-diagram-3 fs-4"></i>
                    </div>
                </div>
                <div class="progress mt-3" style="height: 4px;">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: 45%" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>

        <!-- System Audit Log Card -->
        <div class="col-md-3">
            <div class="card p-3 border-0 bg-white shadow-sm h-100 position-relative overflow-hidden" style="transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold">SYSTEM AUDIT TRAIL</span>
                        <h3 class="fw-bold mt-1 mb-0">{{ $totalAudits }}</h3>
                    </div>
                    <div class="bg-danger-subtle p-3 rounded-3 text-danger">
                        <i class="bi bi-shield-check fs-4"></i>
                    </div>
                </div>
                <div class="progress mt-3" style="height: 4px;">
                    <div class="progress-bar bg-danger" role="progressbar" style="width: 90%" aria-valuenow="90" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Approvals (Actionable Workflow Center) -->
    @if(!empty($myPendingApprovals))
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white border-bottom-0 py-3">
                    <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-check2-square me-2"></i>Workflow Approvals Center</h5>
                </div>
                <div class="table-responsive p-3 pt-0">
                    <table class="table table-hover align-middle mb-0" style="font-size:0.85rem;">
                        <thead>
                            <tr class="table-light">
                                <th>Workflow Name</th>
                                <th>Record Reference</th>
                                <th>Pending Step</th>
                                <th>Requested</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($myPendingApprovals as $appr)
                            <tr id="wf-row-{{ $appr['id'] }}">
                                <td class="fw-semibold">{{ $appr['workflow_name'] }}</td>
                                <td>{{ $appr['record_summary'] }}</td>
                                <td><span class="badge bg-warning text-dark">{{ $appr['current_step_name'] }}</span></td>
                                <td class="text-muted">{{ $appr['time_ago'] }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <input type="text" class="form-control form-control-sm border" placeholder="Comments..." id="wf-comments-{{ $appr['id'] }}" style="max-width:180px;">
                                        <button class="btn btn-sm btn-success" onclick="processApproval({{ $appr['id'] }}, 'approve')"><i class="bi bi-check-lg"></i> Approve</button>
                                        <button class="btn btn-sm btn-danger" onclick="processApproval({{ $appr['id'] }}, 'reject')"><i class="bi bi-x-lg"></i> Reject</button>
                                    </div>
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

    <!-- Main Charts & Details Grid -->
    <div class="row mb-4">
        <!-- Sales Chart -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Monthly Sales Summary</h5>
                    <span class="badge bg-primary-subtle text-primary fw-bold">Live Data</span>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 250px; width: 100%;">
                        <canvas id="salesSummaryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Diagnostics (Health) -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">System Diagnostic Health</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" style="font-size:0.85rem;">
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-0 fw-semibold">Database Engine</h6>
                                <small class="text-muted">Database name: {{ $systemHealth['db_name'] }}</small>
                            </div>
                            <span class="badge bg-success-subtle text-success py-2 px-3 fw-bold">{{ $systemHealth['db_size'] }}</span>
                        </div>
                        <div class="list-group-item py-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="mb-0 fw-semibold">Host Storage Partition</h6>
                                <small class="text-muted">{{ $systemHealth['disk_used'] }} of {{ $systemHealth['disk_total'] }} used</small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $systemHealth['disk_percent'] }}%"></div>
                            </div>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-0 fw-semibold">Process Load average</h6>
                                <small class="text-muted">CPU cycle occupancy</small>
                            </div>
                            <span class="badge bg-info-subtle text-info py-2 px-3 fw-bold">{{ $systemHealth['cpu_load'] }}</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h6 class="mb-0 fw-semibold">Framework Specs</h6>
                                <small class="text-muted">PHP {{ $systemHealth['php_version'] }}</small>
                            </div>
                            <span class="badge bg-secondary py-2 px-3 fw-bold" style="font-size: 0.75rem;">MySQL {{ substr($systemHealth['mysql_version'], 0, 5) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Audits Row -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-0">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">Recent Enterprise Audit Trails</h5>
                </div>
                <div class="table-responsive p-3 pt-0">
                    <table class="table table-hover align-middle mb-0" style="font-size:0.8rem;">
                        <thead>
                            <tr class="table-light">
                                <th>User</th>
                                <th>Action</th>
                                <th>Target Entity</th>
                                <th>Record ID</th>
                                <th>IP Address</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentAudits as $log)
                            <tr>
                                <td class="fw-semibold">{{ $log->user_name ?: 'System Job' }}</td>
                                <td>
                                    <span class="badge bg-{{ $log->action === 'CREATE' ? 'success' : ($log->action === 'UPDATE' ? 'info' : 'danger') }} px-2 py-1">
                                        {{ $log->action }}
                                    </span>
                                </td>
                                <td class="font-monospace text-muted">{{ $log->table_name }}</td>
                                <td>#{{ $log->record_id }}</td>
                                <td>{{ $log->ip_address }}</td>
                                <td>{{ $log->time_ago }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dynamic chart loading scripting -->
<script>
    // Ensure Chart.js is loaded
    if (typeof Chart === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        script.onload = () => initSalesChart();
        document.head.appendChild(script);
    } else {
        initSalesChart();
    }

    function initSalesChart() {
        const ctx = document.getElementById('salesSummaryChart').getContext('2d');
        
        // Parse Monthly sales object
        const chartData = @json($monthlySales);
        const labels = Object.keys(chartData);
        const values = Object.values(chartData);

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels.length ? labels : ['No Data'],
                datasets: [{
                    label: 'Approved Sales Volume (₹)',
                    data: values.length ? values : [0],
                    backgroundColor: 'rgba(37, 99, 235, 0.85)',
                    borderColor: 'rgb(37, 99, 235)',
                    borderWidth: 1,
                    borderRadius: 6,
                    barThickness: 24
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { 
                            font: { family: 'Plus Jakarta Sans' },
                            callback: function(value) {
                                return '₹' + value.toLocaleString('en-IN');
                            }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans' } }
                    }
                }
            }
        });
    }

    // Process Inline approvals
    function processApproval(instanceId, action) {
        const comments = document.getElementById('wf-comments-' + instanceId).value;
        const endpoint = action === 'approve' 
            ? '/api/workflow/approve/' + instanceId 
            : '/api/workflow/reject/' + instanceId;

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ comments: comments })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('success', data.message);
                // Refresh dashboard content
                loadDashboard();
            } else {
                showToast('danger', data.error || 'Workflow action failed.');
            }
        })
        .catch(err => {
            showToast('danger', 'Network error executing approval.');
        });
    }
</script>
