<div class="container-fluid p-0">
    <div class="row g-3" style="height: calc(100vh - 130px); overflow: hidden;">
        <!-- Left Pane: Folders/Labels -->
        <div class="col-md-2 d-flex flex-column h-100 border-end pe-3">
            <!-- Email Account Selector -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">Active Account</label>
                <div class="dropdown">
                    <button class="btn btn-light btn-sm w-100 border text-start d-flex justify-content-between align-items-center py-2" type="button" id="emailAccountDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 8px;">
                        <span class="text-truncate fw-semibold" style="font-size: 0.8rem;" id="active-account-display-email">
                            {{ $account->email ?? 'No Account Configured' }}
                        </span>
                        <i class="bi bi-chevron-down text-muted small ms-1"></i>
                    </button>
                    <ul class="dropdown-menu w-100 shadow border-0" aria-labelledby="emailAccountDropdown" id="email-accounts-list" style="border-radius: 8px;">
                        @foreach($allAcc as $acc)
                            <li>
                                <a class="dropdown-item py-2 d-flex justify-content-between align-items-center @if($account && $account->id == $acc->id) active @endif" href="#" onclick="switchEmailAccountLocal(event, {{ $acc->id }}, '{{ $acc->email }}')">
                                    <span class="text-truncate small fw-medium @if($account && $account->id == $acc->id) text-white @else text-dark @endif">{{ $acc->email }}</span>
                                    @if($account && $account->id == $acc->id)
                                        <i class="bi bi-check-lg small"></i>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                        @if($allAcc->isEmpty())
                            <li class="px-3 py-2 text-muted small text-center">No accounts found.</li>
                        @endif
                    </ul>
                </div>
            </div>

            <button class="btn btn-primary btn-sm mb-3 w-100 py-2 d-flex align-items-center justify-content-center" onclick="loadEmailApp(null, 'compose')">
                <i class="bi bi-pencil-square me-2 fs-6"></i> Compose
            </button>

            <div class="list-group list-group-flush small" id="folder-list">
                <a href="#" class="list-group-item list-group-item-action border-0 rounded-2 py-2 d-flex justify-content-between align-items-center active" onclick="switchFolder(this, 'INBOX')">
                    <span><i class="bi bi-inbox me-2"></i> Inbox</span>
                    <span class="badge rounded-pill bg-primary" id="count-inbox">{{ $counts['INBOX'] ?? 0 }}</span>
                </a>
                <a href="#" class="list-group-item list-group-item-action border-0 rounded-2 py-2 d-flex justify-content-between align-items-center" onclick="switchFolder(this, 'SENT')">
                    <span><i class="bi bi-send me-2"></i> Sent</span>
                    <span class="badge rounded-pill bg-light text-muted" id="count-sent">{{ $counts['SENT'] ?? 0 }}</span>
                </a>
                <a href="#" class="list-group-item list-group-item-action border-0 rounded-2 py-2 d-flex justify-content-between align-items-center" onclick="switchFolder(this, 'DRAFTS')">
                    <span><i class="bi bi-file-earmark me-2"></i> Drafts</span>
                    <span class="badge rounded-pill bg-light text-muted" id="count-drafts">{{ $counts['DRAFTS'] ?? 0 }}</span>
                </a>
                <a href="#" class="list-group-item list-group-item-action border-0 rounded-2 py-2 d-flex justify-content-between align-items-center" onclick="switchFolder(this, 'SPAM')">
                    <span><i class="bi bi-exclamation-triangle me-2"></i> Spam</span>
                    <span class="badge rounded-pill bg-light text-muted" id="count-spam">{{ $counts['SPAM'] ?? 0 }}</span>
                </a>
                <a href="#" class="list-group-item list-group-item-action border-0 rounded-2 py-2 d-flex justify-content-between align-items-center" onclick="switchFolder(this, 'TRASH')">
                    <span><i class="bi bi-trash me-2"></i> Trash</span>
                    <span class="badge rounded-pill bg-light text-muted" id="count-trash">{{ $counts['TRASH'] ?? 0 }}</span>
                </a>
                <a href="#" class="list-group-item list-group-item-action border-0 rounded-2 py-2 d-flex justify-content-between align-items-center" onclick="switchFolder(this, 'ARCHIVE')">
                    <span><i class="bi bi-archive me-2"></i> Archive</span>
                    <span class="badge rounded-pill bg-light text-muted" id="count-archive">{{ $counts['ARCHIVE'] ?? 0 }}</span>
                </a>
            </div>

            <div class="mt-4 mb-2 d-flex justify-content-between align-items-center px-2">
                <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.75rem;">Labels</span>
                <button class="btn btn-link p-0 text-primary d-flex align-items-center" onclick="openCreateLabelModal()" title="New Label" style="text-decoration: none;">
                    <i class="bi bi-plus-lg fs-6"></i>
                </button>
            </div>
            <div class="list-group list-group-flush small overflow-y-auto" id="label-list" style="max-height: 200px;">
                @foreach($labels as $lbl)
                    <a href="#" class="list-group-item list-group-item-action border-0 rounded-2 py-2 d-flex justify-content-between align-items-center" onclick="switchLabel(this, '{{ $lbl->name }}')">
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <span><i class="bi bi-tag-fill me-2" style="color: {{ $lbl->color }};"></i> {{ $lbl->name }}</span>
                            <button class="btn btn-link p-0 text-muted label-delete-btn" onclick="deleteLabel(event, {{ $lbl->id }})" style="visibility: hidden;">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-auto pt-3 border-top">
                <button class="btn btn-outline-secondary btn-sm w-100 py-2 d-flex align-items-center justify-content-center" onclick="openEmailSettingsModal()">
                    <i class="bi bi-gear me-2 fs-6"></i> Mail Settings
                </button>
            </div>
        </div>

        <!-- Middle Pane: AG Grid list of emails -->
        <div class="col-md-5 d-flex flex-column h-100 border-end px-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="fw-bold mb-0" id="current-folder-title">Inbox</h6>
                    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 py-1" onclick="refreshActiveAccountEmails(this)" title="Sync Emails">
                        <i class="bi bi-arrow-clockwise" id="sync-icon"></i> <span class="d-none d-lg-inline small">Sync</span>
                    </button>
                </div>
                <div class="input-group input-group-sm w-50">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0" id="email-search" placeholder="Search emails..." oninput="onEmailSearch()">
                </div>
            </div>
            
            <div class="flex-grow-1 border rounded-3 overflow-hidden shadow-sm">
                <div id="emailGrid" class="ag-theme-alpine" style="height: 100%; width: 100%;"></div>
            </div>
        </div>

        <!-- Right Pane: Preview Pane -->
        <div class="col-md-5 d-flex flex-column h-100 ps-3 overflow-hidden" style="min-height: 0;">
            <div id="preview-placeholder" class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                <i class="bi bi-envelope-open fs-1 mb-2 text-secondary"></i>
                <p class="small">Select an email to view the conversation thread.</p>
            </div>

            <div id="preview-pane" class="d-none d-flex h-100 flex-column overflow-hidden" style="min-height: 0;">
                <!-- Thread Header Actions -->
                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                    <div class="d-inline-flex gap-1">
                        <button class="btn btn-sm btn-light border" onclick="moveActiveEmail('ARCHIVE')" title="Archive"><i class="bi bi-archive"></i></button>
                        <button class="btn btn-sm btn-light border" onclick="moveActiveEmail('TRASH')" title="Delete"><i class="bi bi-trash"></i></button>
                        <button class="btn btn-sm btn-light border" onclick="moveActiveEmail('SPAM')" title="Spam"><i class="bi bi-exclamation-triangle"></i></button>
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center gap-1" type="button" id="labelDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Labels">
                                <i class="bi bi-tag"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="labelDropdown" id="thread-label-dropdown-menu" style="min-width: 180px; border-radius: 8px;">
                                <!-- Populate dynamically -->
                            </ul>
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-light border" onclick="toggleActiveStar()" id="preview-star-btn"><i class="bi bi-star"></i></button>
                    </div>
                </div>

                <!-- Thread Subject -->
                <h5 class="fw-bold mb-3" id="preview-subject">Subject</h5>

                <!-- Messages Stream Scroll Container -->
                <div class="flex-grow-1 overflow-y-auto pe-1" id="preview-messages-container" style="min-height: 0;">
                    <!-- Loaded dynamically -->
                </div>

                <!-- Quick Action Reply Footer -->
                <div class="border-top pt-3 mt-2 bg-white">
                    <button class="btn btn-sm btn-primary" onclick="replyToActiveEmail()"><i class="bi bi-reply-fill"></i> Reply</button>
                    <button class="btn btn-sm btn-light border" onclick="forwardActiveEmail()"><i class="bi bi-forward-fill"></i> Forward</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mail Settings Modal -->
<div class="modal fade" id="emailSettingsModal" tabindex="-1" aria-labelledby="emailSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden; background: #ffffff;">
            <div class="modal-header border-0 px-4 pt-4 pb-2 d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold text-dark" id="emailSettingsModalLabel">Configure Email Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="emailSettingsForm" onsubmit="saveEmailSettings(event)">
                <div class="modal-body px-4 pb-4">
                    <p class="text-muted small mb-4">Set up your credentials below. For Gmail accounts, please use an App Password generated from your Google Account settings.</p>
                    
                    <div class="mb-3">
                        <label for="settings-email" class="form-label small fw-bold text-muted">Email Address</label>
                        <input type="email" class="form-control form-control-sm py-2 text-dark" id="settings-email" placeholder="e.g. name@gmail.com" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="settings-display-name" class="form-label small fw-bold text-muted">Display Name</label>
                        <input type="text" class="form-control form-control-sm py-2 text-dark" id="settings-display-name" placeholder="e.g. John Doe (Sales)" required>
                    </div>
                    
                    <!-- Password field hidden as per user request (can be enabled by uncommenting in future)
                    <div class="mb-3">
                        <label for="settings-password" class="form-label small fw-bold text-muted">Password / App Password</label>
                        <div class="input-group input-group-sm">
                            <input type="password" class="form-control form-control-sm py-2 text-dark border-end-0" id="settings-password" placeholder="Enter password">
                            <button class="btn btn-outline-secondary border-start-0" type="button" onclick="toggleSettingsPasswordVisibility()" style="border-color: #dee2e6;"><i class="bi bi-eye" id="settings-password-toggle-icon"></i></button>
                        </div>
                    </div>
                    -->
                </div>
                
                <div class="modal-footer border-0 px-4 pb-4 pt-2">
                    <button type="button" class="btn btn-sm btn-light border px-3 text-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3" id="save-settings-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Label Modal -->
<div class="modal fade" id="createLabelModal" tabindex="-1" aria-labelledby="createLabelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; background: #ffffff;">
            <div class="modal-header border-0 px-3 pt-3 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="modal-title fw-bold text-dark" id="createLabelModalLabel">Create New Label</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createLabelForm" onsubmit="saveNewLabel(event)">
                <div class="modal-body px-3 pb-3">
                    <div class="mb-3">
                        <label for="label-name-input" class="form-label small fw-bold text-muted">Label Name</label>
                        <input type="text" class="form-control form-control-sm" id="label-name-input" placeholder="e.g. Work, Bills" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted d-block">Label Color</label>
                        <input type="color" class="form-control form-control-color border-0 p-0" id="label-color-input" value="#3b82f6" title="Choose color" style="width: 100%; height: 38px; border-radius: 6px; cursor: pointer;">
                    </div>
                </div>
                <div class="modal-footer border-0 px-3 pb-3 pt-0">
                    <button type="button" class="btn btn-xs btn-light border text-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-xs btn-primary px-3" id="save-label-btn">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Connection / Sync Logs Modal -->
<div class="modal fade" id="syncLogsModal" tabindex="-1" aria-labelledby="syncLogsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden; background: #ffffff;">
            <div class="modal-header border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="syncLogsModalLabel">
                    <i class="bi bi-exclamation-triangle-fill text-danger"></i> Connection / Sync Failed
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-3 small" role="alert">
                    <i class="bi bi-x-circle-fill"></i>
                    <span id="sync-error-message">Failed to sync email account.</span>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted mb-1">IMAP Session Connection Logs</label>
                    <div class="bg-dark text-light p-3 rounded font-monospace small overflow-auto" id="sync-logs-container" style="max-height: 300px; white-space: pre-wrap; font-family: SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 0.8rem; line-height: 1.4;">
                        <!-- Logs populated dynamically -->
                    </div>
                </div>
                <div class="text-muted small" style="font-size: 0.75rem;">
                    <i class="bi bi-info-circle me-1"></i> For Google/Gmail accounts, verify that 2-Step Verification is enabled and you are using a 16-character <strong>App Password</strong> instead of your personal password.
                </div>
            </div>
            <div class="modal-footer border-top px-4 py-2">
                <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>