<div class="container-fluid p-0">
    <!-- Page Header -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h4 class="fw-bold mb-1"><i class="bi bi-diagram-3 me-2 text-primary"></i>Notification Routing Hierarchy</h4>
            <p class="text-muted small mb-0">Configure dynamic notification routes (Sender → Receiver) with user drag-and-drop hierarchy capabilities.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <button class="btn btn-sm btn-outline-primary fw-bold" onclick="resetAllRules()">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Active View
            </button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: Staff Directory -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                    <h5 class="fw-bold text-dark mb-2">Staff Directory</h5>
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="staff-search" class="form-control bg-light border-start-0" placeholder="Search staff members..." onkeyup="filterStaff()">
                    </div>
                </div>
                <div class="card-body px-4 pb-4 pt-0">
                    <div class="staff-list-container scrollable-y" style="max-height: 600px; overflow-y: auto;">
                        @foreach($users as $user)
                            <div class="card staff-card mb-2 border border-light shadow-sm" 
                                 id="staff-card-{{ $user->id }}"
                                 draggable="true" 
                                 ondragstart="handleDragStart(event, '{{ $user->id }}', '{{ addslashes($user->name) }}')"
                                 onclick="selectActiveUser('{{ $user->id }}', '{{ addslashes($user->name) }}', '{{ addslashes($user->role_name ?? 'Staff') }}', '{{ addslashes($user->department_name ?? 'General') }}')"
                                 style="cursor: pointer; transition: all 0.2s ease;">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-weight: 700;">
                                            {{ strtoupper(substr($user->name, 0, 2)) }}
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <h6 class="mb-0 fw-bold text-truncate text-dark name-field">{{ $user->name }}</h6>
                                            <div class="d-flex align-items-center gap-1 mt-1">
                                                <span class="badge bg-light text-secondary border small-badge">{{ $user->role_name ?? 'Staff' }}</span>
                                                <span class="badge bg-light text-primary border small-badge">{{ $user->department_name ?? 'General' }}</span>
                                            </div>
                                        </div>
                                        <div class="text-muted ps-2">
                                            <i class="bi bi-grip-vertical drag-handle"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Routing Matrix Configurations -->
        <div class="col-lg-8">
            <!-- Blank State -->
            <div id="routing-blank-state" class="card border-0 shadow-sm h-100 text-center py-5">
                <div class="card-body d-flex flex-column align-items-center justify-content-center py-5">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                        <i class="bi bi-diagram-3 fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Select a Staff Member</h5>
                    <p class="text-muted small max-width-350 mb-0">Click on any staff member in the directory to manage their incoming and outgoing notification routes.</p>
                </div>
            </div>

            <!-- Configuration Content -->
            <div id="routing-config-panel" class="card border-0 shadow-sm h-100 d-none">
                <div class="card-header bg-transparent border-bottom-0 pt-4 px-4 pb-0">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" id="active-user-avatar" style="width: 50px; height: 50px; font-weight: 700; font-size: 1.2rem;">
                                --
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0" id="active-user-name">Active User</h5>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <span class="badge bg-light text-secondary border" id="active-user-role">Role</span>
                                    <span class="badge bg-light text-primary border" id="active-user-dept">Department</span>
                                </div>
                            </div>
                        </div>

                        <!-- Copy Rules Section -->
                        <div class="bg-light p-3 rounded border">
                            <h6 class="fw-bold small mb-2"><i class="bi bi-copy me-1 text-primary"></i>Copy Rules</h6>
                            <div class="d-flex gap-2 align-items-center">
                                <select id="copy-source-user" class="form-select form-select-sm border" style="min-width: 180px;">
                                    <option value="">Choose source user...</option>
                                    @foreach($allUsers as $au)
                                        <option value="{{ $au->id }}">{{ $au->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-primary fw-bold px-3 text-nowrap" onclick="copyRoutingRules()">Copy</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="row g-4">
                        <!-- Column: Incoming Dropzone & List -->
                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light h-100">
                                <h6 class="fw-bold mb-3 text-dark d-flex align-items-center">
                                    <i class="bi bi-box-arrow-in-down text-success me-2 fs-5"></i>
                                    Incoming Senders
                                </h6>
                                <p class="text-muted small mb-3">Who will send notifications to <strong class="active-user-name-text">this user</strong>?</p>
                                
                                <!-- Dropzone -->
                                <div class="routing-dropzone text-center p-4 border border-2 border-dashed rounded mb-4" 
                                     id="dropzone-incoming"
                                     ondragover="handleDragOver(event, 'dropzone-incoming')"
                                     ondragleave="handleDragLeave(event, 'dropzone-incoming')"
                                     ondrop="handleDrop(event, 'incoming')"
                                     style="transition: all 0.2s ease;">
                                    <i class="bi bi-plus-circle text-muted fs-3 mb-2"></i>
                                    <p class="text-muted small mb-0">Drag a staff member and drop here to ADD as sender</p>
                                </div>

                                <!-- Active Route Senders List -->
                                <div id="incoming-list-container" class="scrollable-y" style="max-height: 300px; overflow-y: auto;">
                                    <div class="text-center py-4 text-muted small" id="incoming-empty-state">
                                        No incoming routes defined.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Column: Outgoing Dropzone & List -->
                        <div class="col-md-6">
                            <div class="border rounded p-3 bg-light h-100">
                                <h6 class="fw-bold mb-3 text-dark d-flex align-items-center">
                                    <i class="bi bi-box-arrow-up text-primary me-2 fs-5"></i>
                                    Outgoing Receivers
                                </h6>
                                <p class="text-muted small mb-3">Who will receive notifications from <strong class="active-user-name-text">this user</strong>?</p>
                                
                                <!-- Dropzone -->
                                <div class="routing-dropzone text-center p-4 border border-2 border-dashed rounded mb-4" 
                                     id="dropzone-outgoing"
                                     ondragover="handleDragOver(event, 'dropzone-outgoing')"
                                     ondragleave="handleDragLeave(event, 'dropzone-outgoing')"
                                     ondrop="handleDrop(event, 'outgoing')"
                                     style="transition: all 0.2s ease;">
                                    <i class="bi bi-plus-circle text-muted fs-3 mb-2"></i>
                                    <p class="text-muted small mb-0">Drag a staff member and drop here to ADD as receiver</p>
                                </div>

                                <!-- Active Route Receivers List -->
                                <div id="outgoing-list-container" class="scrollable-y" style="max-height: 300px; overflow-y: auto;">
                                    <div class="text-center py-4 text-muted small" id="outgoing-empty-state">
                                        No outgoing routes defined.
                                    </div>
                                </div>
                            </div>
                    </div>

                    <!-- Test Notification Routing -->
                    <div class="border-top mt-4 pt-4">
                        <h6 class="fw-bold text-dark mb-2">
                            <i class="bi bi-bell text-warning me-2"></i>
                            Test Notification Routing
                        </h6>
                        <p class="text-muted small mb-3">
                            Simulate sending a notification from <strong class="active-user-name-text">Active User</strong>. It will be routed to all active <strong>Outgoing Receivers</strong> configured above.
                        </p>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <input type="text" id="test-notif-title" class="form-control form-control-sm border" placeholder="Notification Title (e.g. Action Required)">
                            </div>
                            <div class="col-md-6">
                                <input type="text" id="test-notif-message" class="form-control form-control-sm border" placeholder="Notification Message (e.g. Please review the pending draft invoice.)">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-sm btn-warning fw-bold w-100" onclick="sendTestNotification()">
                                    <i class="bi bi-send me-1"></i> Send Test
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
