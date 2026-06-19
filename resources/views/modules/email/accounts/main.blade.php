<div class="container-fluid p-0">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-envelope-open-fill text-primary me-2"></i>Email Accounts Settings</h4>
            <p class="text-muted small mb-0">Configure connection settings for IMAP/SMTP and assign accounts to multiple users.</p>
        </div>
        <button class="btn btn-primary btn-sm px-3 py-2 d-flex align-items-center gap-2" onclick="openAddAccountModal()">
            <i class="bi bi-plus-lg fs-6"></i> Add Email Account
        </button>
    </div>

    <!-- Accounts Table Grid -->
    <div class="accounts-card p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase;">Display Name</th>
                        <th style="font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase;">Email Address</th>
                        <th style="font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase;">IMAP Config</th>
                        <th style="font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase;">SMTP Config</th>
                        <th style="font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase;">Assigned Users</th>
                        <th class="text-end" style="font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase; width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="email-accounts-table-body">
                    @foreach($accounts as $acc)
                        <tr id="account-row-{{ $acc->id }}">
                            <td>
                                <div class="fw-semibold text-dark">{{ $acc->display_name }}</div>
                            </td>
                            <td>
                                <div class="text-secondary small">{{ $acc->email }}</div>
                            </td>
                            <td>
                                <div class="small text-muted">{{ $acc->imap_host }}:{{ $acc->imap_port }} <span class="badge bg-light text-secondary border">{{ $acc->imap_encryption }}</span></div>
                            </td>
                            <td>
                                <div class="small text-muted">{{ $acc->smtp_host }}:{{ $acc->smtp_port }} <span class="badge bg-light text-secondary border">{{ $acc->smtp_encryption }}</span></div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @php $assignedCount = 0; @endphp
                                    @foreach($users as $usr)
                                        @if(in_array($usr->id, $acc->user_ids))
                                            <span class="badge-user" title="{{ $usr->email }}">
                                                <i class="bi bi-person-fill"></i> {{ $usr->name }}
                                            </span>
                                            @php $assignedCount++; @endphp
                                        @endif
                                    @endforeach
                                    @if($assignedCount === 0)
                                        <span class="text-muted small italic"><i class="bi bi-exclamation-triangle me-1"></i>Unassigned</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-sm btn-light border" onclick="openEditAccountModal({{ json_encode($acc) }})" title="Edit Connection">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteAccountLocal({{ $acc->id }}, '{{ $acc->email }}')" title="Delete Account">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @if(count($accounts) === 0)
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-envelope d-block fs-3 mb-2 opacity-50"></i>
                                No email accounts configured. Add an account to get started.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Configure Email Account -->
<div class="modal fade" id="emailAccountModal" tabindex="-1" aria-labelledby="emailAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-dark" id="emailAccountModalLabel">Add Email Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="emailAccountForm" onsubmit="saveAccountLocal(event)">
                <input type="hidden" id="account-id">
                <div class="modal-body p-4">
                    
                    <div class="row g-3">
                        <!-- Display Name -->
                        <div class="col-md-6">
                            <label for="acc-display-name" class="form-label small fw-bold text-muted mb-1">Display Name</label>
                            <input type="text" class="form-control form-control-sm py-2 text-dark" id="acc-display-name" placeholder="e.g. Sales Team / support" required>
                        </div>
                        
                        <!-- Email Address -->
                        <div class="col-md-6">
                            <label for="acc-email" class="form-label small fw-bold text-muted mb-1">Email Address</label>
                            <input type="email" class="form-control form-control-sm py-2 text-dark" id="acc-email" placeholder="e.g. company@gmail.com" oninput="handleEmailInput(this.value)" required>
                        </div>

                        <!-- Password / App Password -->
                        <div class="col-md-12">
                            <label for="acc-password" class="form-label small fw-bold text-muted mb-1" id="acc-password-label">Password / App Password</label>
                            <input type="password" class="form-control form-control-sm py-2 text-dark" id="acc-password" placeholder="Enter password or app password">
                            <div class="form-text small" style="font-size: 0.75rem;">For safety reasons, active passwords are encrypted. Leave blank to keep existing password.</div>
                        </div>

                        <!-- Advanced Configurations toggle -->
                        <div class="col-md-12">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="show-advanced-switch" onchange="toggleAdvancedSettings(this.checked)">
                                <label class="form-check-label small fw-semibold text-muted" for="show-advanced-switch">Show Custom Host / Port Configurations</label>
                            </div>
                        </div>

                        <!-- Advanced Host Ports Settings Section -->
                        <div class="col-md-12 d-none" id="advanced-settings-container">
                            <div class="advanced-settings-section">
                                <h6 class="fw-bold mb-3 small text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Advanced Server Settings</h6>
                                
                                <div class="row g-3">
                                    <!-- IMAP Host -->
                                    <div class="col-md-4">
                                        <label for="acc-imap-host" class="form-label small fw-bold text-muted mb-1">IMAP Host</label>
                                        <input type="text" class="form-control form-control-sm py-2 text-dark" id="acc-imap-host" placeholder="imap.gmail.com" required>
                                    </div>
                                    <!-- IMAP Port -->
                                    <div class="col-md-4">
                                        <label for="acc-imap-port" class="form-label small fw-bold text-muted mb-1">IMAP Port</label>
                                        <input type="number" class="form-control form-control-sm py-2 text-dark" id="acc-imap-port" placeholder="993" required>
                                    </div>
                                    <!-- IMAP Encryption -->
                                    <div class="col-md-4">
                                        <label for="acc-imap-encryption" class="form-label small fw-bold text-muted mb-1">IMAP Encryption</label>
                                        <select class="form-select form-select-sm py-2 text-dark" id="acc-imap-encryption" required>
                                            <option value="ssl">SSL</option>
                                            <option value="tls">TLS</option>
                                            <option value="starttls">STARTTLS</option>
                                            <option value="none">None</option>
                                        </select>
                                    </div>

                                    <!-- SMTP Host -->
                                    <div class="col-md-4">
                                        <label for="acc-smtp-host" class="form-label small fw-bold text-muted mb-1">SMTP Host</label>
                                        <input type="text" class="form-control form-control-sm py-2 text-dark" id="acc-smtp-host" placeholder="smtp.gmail.com" required>
                                    </div>
                                    <!-- SMTP Port -->
                                    <div class="col-md-4">
                                        <label for="acc-smtp-port" class="form-label small fw-bold text-muted mb-1">SMTP Port</label>
                                        <input type="number" class="form-control form-control-sm py-2 text-dark" id="acc-smtp-port" placeholder="465" required>
                                    </div>
                                    <!-- SMTP Encryption -->
                                    <div class="col-md-4">
                                        <label for="acc-smtp-encryption" class="form-label small fw-bold text-muted mb-1">SMTP Encryption</label>
                                        <select class="form-select form-select-sm py-2 text-dark" id="acc-smtp-encryption" required>
                                            <option value="ssl">SSL</option>
                                            <option value="tls">TLS</option>
                                            <option value="starttls">STARTTLS</option>
                                            <option value="none">None</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- User Assignment Search Dropdown -->
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-muted mb-1">Assign to Users</label>
                            
                            <!-- Chips container showing currently selected users -->
                            <div id="selected-users-chips" class="selected-users-chips-container d-flex flex-wrap gap-1 p-2 border rounded bg-light mb-2 align-items-center" style="min-height: 38px; max-height: 120px; overflow-y: auto;">
                                <span class="text-muted small px-1" id="no-users-selected-label">No users assigned</span>
                            </div>

                            <!-- Search Input and Dropdown Menu -->
                            <div class="dropdown" id="userSearchDropdownContainer">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control form-control-sm text-dark" id="user-search-input" placeholder="Type user name or email to search..." autocomplete="off" oninput="filterUserDropdown(this.value)" onclick="showUserDropdown()">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="clearUserSearch()" id="clear-search-btn" style="display: none;"><i class="bi bi-x"></i></button>
                                </div>
                                <ul class="dropdown-menu w-100 shadow border-0 overflow-auto" id="user-dropdown-list" style="max-height: 250px; border-radius: 8px; font-size: 0.85rem; display: none; position: absolute; z-index: 1050;">
                                    @foreach($users as $usr)
                                        <li class="user-dropdown-item" data-id="{{ $usr->id }}" data-name="{{ $usr->name }}" data-email="{{ $usr->email }}">
                                            <a class="dropdown-item d-flex align-items-center justify-content-between py-2" href="javascript:void(0)" onclick="toggleUserAssignment({{ $usr->id }}, event)">
                                                <div>
                                                    <span class="fw-semibold text-dark">{{ $usr->name }}</span>
                                                    <span class="text-muted text-xs ms-1">({{ $usr->email }})</span>
                                                </div>
                                                <i class="bi bi-check-lg text-primary d-none" id="check-icon-{{ $usr->id }}"></i>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-top p-3">
                    <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3" id="save-account-submit-btn">Save Configurations</button>
                </div>
            </form>
        </div>
    </div>
</div>
