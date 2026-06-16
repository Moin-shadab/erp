<div class="container-fluid p-0">
    <div class="chat-container" id="slack-chat-container">
        
        <!-- 1. Left Sidebar: Channels & DMs -->
        <div class="chat-sidebar">
            <div class="chat-sidebar-header">
                <h5 style="color:black;"><i class="bi bi-chat-left-dots-fill me-2 text-warning"></i>Internal Chat</h5>
                <span id="current-user-badge" class="badge bg-light text-dark small" style="font-size: 0.75rem;"></span>
            </div>
            
            <div class="chat-sidebar-content">
                <!-- Admin tab selector (super-admin only, hidden by default) -->
                <div class="sidebar-section d-none" id="sidebar-admin-section">
                    <ul class="sidebar-list">
                        <li>
                            <a href="#" class="sidebar-item-link text-warning fw-bold" onclick="toggleAdminPanel(event, true)" id="admin-panel-toggle">
                                <span class="sidebar-item-icon"><i class="bi bi-shield-lock-fill" style="color:black;"></i></span>
                                Admin Console
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Channels / Groups -->
                <div class="sidebar-section">
                    <div class="sidebar-section-header">
                        <span style="color:black;" >Channels</span>
                        <button class="add-btn" onclick="openCreateChannelModal()" title="Create Channel">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <ul class="sidebar-list" id="sidebar-channels-list" style="color:black;">
                        <!-- Filled dynamically by JS -->
                    </ul>
                </div>

                <!-- Direct Messages (DMs) -->
                <div class="sidebar-section" style="color:black;">
                    <div class="sidebar-section-header">
                        <span style="color:black;" >Direct Messages</span>
                    </div>
                    <ul class="sidebar-list" id="sidebar-contacts-list" style="color:black;">
                        <!-- Filled dynamically by JS -->
                    </ul>
                </div>
            </div>
        </div>

        

        <!-- 2. Main Chat Panel -->
        <div class="chat-main" id="chat-main-panel">
            <!-- Loading Placeholder -->
            <div class="chat-loading-placeholder" id="chat-loading-overlay">
                <i class="bi bi-chat-quote"></i>
                <h5>Select a channel or conversation to start chatting</h5>
                <p>Choose from your channels or DMs in the left sidebar.</p>
            </div>

            <!-- Active Conversation Container (Hidden by default) -->
            <div class="d-none flex-column h-100" id="active-chat-container" style="display: flex;">
                <!-- Chat Header -->
                <div class="chat-header">
                    <div class="chat-header-info">
                        <h6 id="active-chat-title"># general</h6>
                        <span id="active-chat-subtitle">3 members</span>
                    </div>
                    <div class="chat-header-actions">
                        <!-- Optional header controls -->
                    </div>
                </div>

                <!-- Messages Stream -->
                <div class="chat-messages-scroll" id="chat-messages-stream">
                    <!-- Loaded dynamically -->
                </div>

                <!-- Input area -->
                <div class="chat-input-container">
                    <div class="chat-input-box">
                        <!-- Attachments preview bar -->
                        <div class="attachments-preview d-none" id="input-attachments-preview">
                            <!-- Selected attachment chips -->
                        </div>
                        
                        <textarea class="chat-textarea" id="chat-message-input" placeholder="Message..." rows="1"></textarea>
                        
                        <div class="chat-input-toolbar">
                            <div class="toolbar-left">
                                <button class="toolbar-btn" onclick="triggerFileUpload()" title="Add file">
                                    <i class="bi bi-paperclip"></i>
                                </button>
                                <input type="file" id="chat-file-upload-input" class="d-none" multiple onchange="handleFileSelection(event)">
                            </div>
                            <button class="send-msg-btn" id="chat-send-btn" onclick="submitMessage()" disabled>
                                <i class="bi bi-send-fill"></i> Send
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Thread Sidebar Panel (Hidden by default) -->
        <div class="chat-thread-sidebar" id="chat-thread-panel">
            <div class="thread-header">
                <h6>Thread</h6>
                <button class="thread-close-btn" onclick="closeThreadPanel()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <div class="thread-content-scroll">
                <!-- Parent Message Container -->
                <div class="thread-parent-message" id="thread-parent-msg-container">
                    <!-- Dynamic -->
                </div>

                <div class="replies-divider">Replies</div>

                <!-- Replies Stream -->
                <div class="d-flex flex-column gap-3" id="thread-replies-stream">
                    <!-- Dynamic -->
                </div>
            </div>

            <!-- Thread Input -->
            <div class="thread-input-container">
                <div class="chat-input-box">
                    <!-- Thread attachment preview bar -->
                    <div class="attachments-preview d-none" id="thread-attachments-preview">
                        <!-- Selected thread attachment chips -->
                    </div>
                    
                    <textarea class="chat-textarea" id="thread-message-input" placeholder="Reply..." rows="1"></textarea>
                    
                    <div class="chat-input-toolbar">
                        <div class="toolbar-left">
                            <button class="toolbar-btn" onclick="triggerThreadFileUpload()" title="Add file">
                                    <i class="bi bi-paperclip"></i>
                            </button>
                            <input type="file" id="thread-file-upload-input" class="d-none" multiple onchange="handleThreadFileSelection(event)">
                        </div>
                        <button class="send-msg-btn" id="thread-send-btn" onclick="submitThreadMessage()" disabled>
                            <i class="bi bi-send-fill"></i> Reply
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Admin Settings Panel (Hidden by default, Super-Admin only) -->
        <div class="admin-dashboard" id="admin-dashboard-panel">
            <div class="admin-header">
                <h6><i class="bi bi-shield-lock-fill me-2 text-danger"></i>Chat System Administration</h6>
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleAdminPanel(event, false)">
                    Back to Chat
                </button>
            </div>
            
            <div class="admin-content">
                
                <div class="row g-4">
                    <!-- Column 1: Messaging Permission Rules -->
                    <div class="col-md-6">
                        <div class="admin-card h-100">
                            <div class="admin-card-title">
                                <span>DM Communication Permission Mapping Rules</span>
                            </div>
                            <p class="text-muted small">By default, users can DM supervisors, subordinates, and department peers. Map custom rules below to permit communication outside of this hierarchy.</p>
                            
                            <form id="admin-add-rule-form" onsubmit="submitCommunicationRule(event)" class="mb-4">
                                <div class="row g-2">
                                    <div class="col-sm-5">
                                        <label class="slack-form-label">Sender User</label>
                                        <select class="form-select slack-form-control" id="rule-sender-select" required>
                                            <option value="">Choose User...</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-5">
                                        <label class="slack-form-label">Allowed Recipient</label>
                                        <select class="form-select slack-form-control" id="rule-recipient-select" required>
                                            <option value="">Choose Recipient...</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary btn-sm w-100 py-2">
                                            Add
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <h6 class="fw-bold mb-2 small text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">Active Rules</h6>
                            <div class="table-responsive" style="max-height: 250px;">
                                <table class="table table-sm table-bordered small">
                                    <thead>
                                        <tr>
                                            <th>Sender</th>
                                            <th>Allowed Recipient</th>
                                            <th style="width: 50px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="admin-rules-table-body">
                                        <!-- Dynamic -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Chat Delete Permissions -->
                    <div class="col-md-6">
                        <div class="admin-card h-100">
                            <div class="admin-card-title">
                                <span>User soft-delete Permissions</span>
                            </div>
                            <p class="text-muted small">Enable or disable whether a user is allowed to soft-delete their sent chat messages.</p>
                            
                            <div class="table-responsive" style="max-height: 350px;">
                                <table class="table table-sm table-striped small align-middle">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th class="text-center" style="width: 120px;">Can Delete Chats</th>
                                        </tr>
                                    </thead>
                                    <tbody id="admin-users-table-body">
                                        <!-- Dynamic -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- 5. Create Channel Modal -->
<div class="modal fade slack-modal" id="createChannelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-hash text-muted"></i> Create new channel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="create-channel-form" onsubmit="submitCreateChannel(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new-channel-name" class="slack-form-label">Channel Name</label>
                        <input type="text" class="form-control slack-form-control" id="new-channel-name" placeholder="e.g. sales-strategy" required>
                    </div>
                    <div class="mb-3">
                        <label class="slack-form-label">Select Members to add</label>
                        <div class="border rounded p-2 overflow-y-auto" style="max-height: 180px;" id="channel-members-checklist">
                            <!-- Dynamic -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3" id="create-channel-submit-btn">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 6. Message Forward Modal -->
<div class="modal fade slack-modal" id="forwardMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-right-short text-muted"></i> Forward Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="forward-message-form" onsubmit="submitForwardMessage(event)">
                <input type="hidden" id="forward-source-message-id">
                <div class="modal-body">
                    <div class="p-3 bg-light rounded mb-3 small text-muted border-start border-3 border-secondary" id="forward-message-preview-text">
                        <!-- Dynamic -->
                    </div>
                    <div class="mb-3">
                        <label for="forward-destination-select" class="slack-form-label">Forward to:</label>
                        <select class="form-select slack-form-control" id="forward-destination-select" required>
                            <option value="">Select channel or user...</option>
                            <optgroup label="Channels" id="forward-optgroup-channels"></optgroup>
                            <optgroup label="Direct Messages" id="forward-optgroup-contacts"></optgroup>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Forward</button>
                </div>
            </form>
        </div>
    </div>
</div>
