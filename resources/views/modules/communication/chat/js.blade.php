// Clear any existing poll intervals to avoid leaks on reload
if (window.chatPollInterval) {
    clearInterval(window.chatPollInterval);
    window.chatPollInterval = null;
}
if (window.threadPollInterval) {
    clearInterval(window.threadPollInterval);
    window.threadPollInterval = null;
}

// Active State Variables
let currentUser = null;
let channelsList = [];
let contactsList = [];
let selectedType = null; // 'group' or 'direct'
let selectedId = null;   // ID of channel or contact
let activeThreadId = null;
let selectedFiles = [];
let selectedThreadFiles = [];

// DOM Helper Selectors
const mainContainer = document.getElementById('slack-chat-container');
const channelsListEl = document.getElementById('sidebar-channels-list');
const contactsListEl = document.getElementById('sidebar-contacts-list');
const chatLoadingOverlay = document.getElementById('chat-loading-overlay');
const activeChatContainer = document.getElementById('active-chat-container');
const activeChatTitle = document.getElementById('active-chat-title');
const activeChatSubtitle = document.getElementById('active-chat-subtitle');
const messagesStream = document.getElementById('chat-messages-stream');
const messageInput = document.getElementById('chat-message-input');
const sendBtn = document.getElementById('chat-send-btn');
const fileInput = document.getElementById('chat-file-upload-input');
const attachmentsPreview = document.getElementById('input-attachments-preview');

// Thread Elements
const threadPanel = document.getElementById('chat-thread-panel');
const threadParentMsgContainer = document.getElementById('thread-parent-msg-container');
const threadRepliesStream = document.getElementById('thread-replies-stream');
const threadMessageInput = document.getElementById('thread-message-input');
const threadSendBtn = document.getElementById('thread-send-btn');
const threadFileInput = document.getElementById('thread-file-upload-input');
const threadAttachmentsPreview = document.getElementById('thread-attachments-preview');

// Admin Elements
const adminDashboardPanel = document.getElementById('admin-dashboard-panel');
const chatMainPanel = document.getElementById('chat-main-panel');
const sidebarAdminSection = document.getElementById('sidebar-admin-section');
const ruleSenderSelect = document.getElementById('rule-sender-select');
const ruleRecipientSelect = document.getElementById('rule-recipient-select');
const adminRulesTableBody = document.getElementById('admin-rules-table-body');
const adminUsersTableBody = document.getElementById('admin-users-table-body');

// CSRF Request Helper
function getHeaders(extra = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    return {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
        ...extra
    };
}

// Avatar Helper
function getAvatarColor(name) {
    const colors = ['#1e3a8a', '#14532d', '#78350f', '#7f1d1d', '#7c2d12', '#311042', '#3b0764', '#470a24', '#042f2e'];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const index = Math.abs(hash) % colors.length;
    return colors[index];
}

function getInitials(name) {
    if (!name) return '??';
    return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
}

function formatBytes(bytes) {
    if (!bytes || bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getFileIconClass(mimeType) {
    if (!mimeType) return 'bi-file-earmark';
    if (mimeType.startsWith('image/')) return 'bi-file-earmark-image';
    if (mimeType.startsWith('video/')) return 'bi-file-earmark-play';
    if (mimeType.startsWith('audio/')) return 'bi-file-earmark-music';
    if (mimeType.includes('pdf')) return 'bi-file-earmark-pdf';
    if (mimeType.includes('zip') || mimeType.includes('tar') || mimeType.includes('rar')) return 'bi-file-zip';
    if (mimeType.includes('word') || mimeType.includes('document')) return 'bi-file-word';
    if (mimeType.includes('excel') || mimeType.includes('sheet') || mimeType.includes('csv')) return 'bi-file-excel';
    return 'bi-file-earmark';
}

// Init Function
function initializeChat() {
    fetch('/api/chat/context', { headers: getHeaders() })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                console.error(data.error);
                return;
            }
            currentUser = data.user;
            channelsList = data.channels || [];
            contactsList = data.contacts || [];

            // Display logged-in user name
            document.getElementById('current-user-badge').innerText = currentUser.name;

            // Render Sidebar
            renderSidebar();

            // Render Member Checklist in group creation modal
            renderChannelMembersChecklist();

            // Populate Forward Modal destination lists
            populateForwardModalDestinations();

            // Show Admin Console link if super admin
            if (currentUser.is_super_admin) {
                sidebarAdminSection.classList.remove('d-none');
            }

            // Start main polling
            window.chatPollInterval = setInterval(pollMessages, 3000);
        })
        .catch(err => console.error('Failed to load chat context', err));
}

// Sidebar Renderer
function renderSidebar() {
    // Render Channels
    channelsListEl.innerHTML = '';
    channelsList.forEach(ch => {
        const li = document.createElement('li');
        const isActive = selectedType === 'group' && selectedId == ch.id;
        li.innerHTML = `
            <a class="sidebar-item-link ${isActive ? 'active' : ''}" onclick="selectChat(event, 'group', ${ch.id}, '# ${ch.name}')">
                <span class="sidebar-item-icon"><i class="bi bi-hash"></i></span>
                <span class="text-truncate">${ch.name}</span>
            </a>
        `;
        channelsListEl.appendChild(li);
    });

    if (channelsList.length === 0) {
        channelsListEl.innerHTML = '<li class="px-4 py-1 text-muted small">No channels joined</li>';
    }

    // Render Contacts (DMs)
    contactsListEl.innerHTML = '';
    contactsList.forEach(ct => {
        const li = document.createElement('li');
        const isActive = selectedType === 'direct' && selectedId == ct.id;
        li.innerHTML = `
            <a class="sidebar-item-link ${isActive ? 'active' : ''}" onclick="selectChat(event, 'direct', ${ct.id}, '@ ${ct.name}')">
                <span class="status-dot online"></span>
                <span class="text-truncate">${ct.name}</span>
            </a>
        `;
        contactsListEl.appendChild(li);
    });

    if (contactsList.length === 0) {
        contactsListEl.innerHTML = '<li class="px-4 py-1 text-muted small">No contacts available</li>';
    }
}

// Channel Members Checklist Renderer
function renderChannelMembersChecklist() {
    const list = document.getElementById('channel-members-checklist');
    if (!list) return;

    list.innerHTML = '';
    contactsList.forEach(ct => {
        const div = document.createElement('div');
        div.className = 'form-check mb-1';
        div.innerHTML = `
            <input class="form-check-input" type="checkbox" value="${ct.id}" id="new-channel-member-${ct.id}">
            <label class="form-check-label small" for="new-channel-member-${ct.id}">
                ${ct.name} (${ct.email})
            </label>
        `;
        list.appendChild(div);
    });

    if (contactsList.length === 0) {
        list.innerHTML = '<div class="text-muted small">No users available to add.</div>';
    }
}

// Forward Destinations selector population
function populateForwardModalDestinations() {
    const chGroup = document.getElementById('forward-optgroup-channels');
    const ctGroup = document.getElementById('forward-optgroup-contacts');
    if (!chGroup || !ctGroup) return;

    chGroup.innerHTML = '';
    channelsList.forEach(ch => {
        const opt = document.createElement('option');
        opt.value = `group:${ch.id}`;
        opt.innerText = `# ${ch.name}`;
        chGroup.appendChild(opt);
    });

    ctGroup.innerHTML = '';
    contactsList.forEach(ct => {
        const opt = document.createElement('option');
        opt.value = `direct:${ct.id}`;
        opt.innerText = `@ ${ct.name}`;
        ctGroup.appendChild(opt);
    });
}

// Select Conversation
function selectChat(event, type, id, title) {
    if (event) event.preventDefault();
    
    // Toggle Admin Panel off
    toggleAdminPanel(null, false);

    selectedType = type;
    selectedId = id;
    
    // Remove active styles, apply to current
    document.querySelectorAll('.sidebar-item-link').forEach(el => el.classList.remove('active'));
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }

    // Hide placeholder, show active conversation container
    chatLoadingOverlay.classList.add('d-none');
    activeChatContainer.classList.remove('d-none');
    activeChatTitle.innerText = title;

    if (type === 'group') {
        activeChatSubtitle.innerText = 'Group Channel';
    } else {
        const contact = contactsList.find(c => c.id == id);
        activeChatSubtitle.innerText = contact ? `${contact.role_name || 'Staff'} • ${contact.department_name || 'No Dept'}` : 'Direct Conversation';
    }

    // Load messages
    messagesStream.innerHTML = `
        <div class="d-flex justify-content-center py-5">
            <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
        </div>
    `;
    loadConversationMessages();
}

// Load conversation messages
function loadConversationMessages() {
    if (!selectedId || !selectedType) return;
    
    const param = (selectedType === 'group') ? `group_id=${selectedId}` : `recipient_id=${selectedId}`;
    
    fetch(`/api/chat/messages?${param}`, { headers: getHeaders() })
        .then(res => res.json())
        .then(messages => {
            renderMessageStream(messages, messagesStream);
        })
        .catch(err => console.error('Failed to load messages', err));
}

// Render Messages Stream Helper
function renderMessageStream(messages, targetContainer) {
    const isMainStream = (targetContainer === messagesStream);
    
    // Keep track of scroll offset before updating
    const wasScrolledToBottom = targetContainer.scrollHeight - targetContainer.clientHeight <= targetContainer.scrollTop + 50;

    targetContainer.innerHTML = '';

    if (messages.length === 0) {
        targetContainer.innerHTML = `
            <div class="text-center text-muted py-5 small">
                <i class="bi bi-chat-dots d-block fs-3 mb-2 opacity-50"></i>
                No messages yet. Send a message to start the conversation!
            </div>
        `;
        return;
    }

    messages.forEach(msg => {
        const item = document.createElement('div');
        item.className = 'message-item';
        item.id = `message-item-${msg.id}`;

        const initials = getInitials(msg.sender_name);
        const avatarBg = getAvatarColor(msg.sender_name);
        
        let textDisplay = msg.message || '';
        
        // Handle soft-deleted message
        if (msg.deleted_at || msg.is_deleted === 1 || msg.is_deleted === true) {
            textDisplay = '<span class="text-muted italic"><i class="bi bi-trash me-1"></i>This message was deleted.</span>';
        }

        // Parse attachments
        let attachmentsHtml = '';
        if (msg.attachments && msg.attachments.length > 0 && !msg.deleted_at && !msg.is_deleted) {
            attachmentsHtml = '<div class="message-attachments">';
            msg.attachments.forEach(at => {
                if (at.mime_type && at.mime_type.startsWith('image/')) {
                    attachmentsHtml += `
                        <div class="attachment-image-preview" onclick="viewFullImage('${at.file_path}')">
                            <img src="${at.file_path}" alt="${at.filename}">
                        </div>
                    `;
                } else {
                    attachmentsHtml += `
                        <a href="${at.file_path}" target="_blank" download="${at.filename}" class="attachment-card">
                            <span class="attachment-icon"><i class="bi ${getFileIconClass(at.mime_type)}"></i></span>
                            <div class="attachment-info">
                                <span class="attachment-name">${at.filename}</span>
                                <span class="attachment-size">${formatBytes(at.file_size)}</span>
                            </div>
                        </a>
                    `;
                }
            });
            attachmentsHtml += '</div>';
        }

        // Thread Indicator
        let threadIndicatorHtml = '';
        if (isMainStream && msg.reply_count > 0 && !msg.deleted_at && !msg.is_deleted) {
            threadIndicatorHtml = `
                <div class="thread-reply-indicator" onclick="openThreadPanel(${msg.id})">
                    <i class="bi bi-chat-text-fill"></i> ${msg.reply_count} ${msg.reply_count === 1 ? 'reply' : 'replies'}
                </div>
            `;
        }

        // Actions Toolbar
        let actionsHtml = '';
        if (!msg.deleted_at && !msg.is_deleted) {
            const isOwner = msg.sender_id == currentUser.id;
            const canDelete = isOwner || currentUser.is_super_admin;
            const showDelete = canDelete && currentUser.can_delete_chats;

            actionsHtml = `
                <div class="message-actions">
                    ${isMainStream ? `
                        <button class="action-btn" onclick="openThreadPanel(${msg.id})" title="Reply in thread">
                            <i class="bi bi-chat-left-text"></i> Thread
                        </button>
                    ` : ''}
                    <button class="action-btn" onclick="triggerForwardMessage(${msg.id}, '${escapeHtml(msg.message || '')}')" title="Forward message">
                        <i class="bi bi-arrow-right-short"></i> Forward
                    </button>
                    ${showDelete ? `
                        <button class="action-btn delete-btn" onclick="submitDeleteMessage(${msg.id})" title="Delete message">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    ` : ''}
                </div>
            `;
        }

        // Message formatting (convert time to simple human readable)
        const timeStr = new Date(msg.created_at).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });

        item.innerHTML = `
            <div class="message-avatar" style="background-color: ${avatarBg};">
                ${initials}
            </div>
            <div class="message-content-wrapper">
                <div class="message-meta">
                    <span class="message-sender">${msg.sender_name}</span>
                    ${msg.sender_role ? `<span class="message-role">${msg.sender_role}</span>` : ''}
                    <span class="message-time">${timeStr}</span>
                </div>
                <div class="message-text">${textDisplay}</div>
                ${attachmentsHtml}
                ${threadIndicatorHtml}
            </div>
            ${actionsHtml}
        `;
        
        targetContainer.appendChild(item);
    });

    // Auto scroll to bottom if user was already at bottom
    if (wasScrolledToBottom || isMainStream && messages.length <= 10) {
        targetContainer.scrollTop = targetContainer.scrollHeight;
    }
}

// Poll for message updates
function pollMessages() {
    // If the chat container has been unmounted, clean up polling intervals automatically
    if (!document.getElementById('slack-chat-container')) {
        clearInterval(window.chatPollInterval);
        window.chatPollInterval = null;
        return;
    }

    if (adminDashboardPanel && adminDashboardPanel.style.display === 'flex') {
        return; // Don't poll while in admin panel
    }

    if (selectedId && selectedType) {
        const param = (selectedType === 'group') ? `group_id=${selectedId}` : `recipient_id=${selectedId}`;
        
        fetch(`/api/chat/messages?${param}`, { headers: getHeaders() })
            .then(res => res.json())
            .then(messages => {
                // Ensure context hasn't changed during request
                if (selectedId && param.includes(selectedId)) {
                    renderMessageStream(messages, messagesStream);
                }
            })
            .catch(err => console.error('Failed in polling messages', err));
    }
}

// Input send state toggles
messageInput.addEventListener('input', function() {
    sendBtn.disabled = (this.value.trim() === '' && selectedFiles.length === 0);
});

// File selections triggers
function triggerFileUpload() {
    fileInput.click();
}

function handleFileSelection(event) {
    const files = Array.from(event.target.files);
    selectedFiles = selectedFiles.concat(files);
    renderFilePreviews();
    sendBtn.disabled = false;
}

function renderFilePreviews() {
    if (selectedFiles.length === 0) {
        attachmentsPreview.classList.add('d-none');
        return;
    }

    attachmentsPreview.classList.remove('d-none');
    attachmentsPreview.innerHTML = '';
    selectedFiles.forEach((file, index) => {
        const chip = document.createElement('div');
        chip.className = 'preview-chip';
        chip.innerHTML = `
            <span class="preview-chip-name">${file.name}</span>
            <span class="preview-chip-remove" onclick="removeSelectedFile(${index})"><i class="bi bi-x-circle-fill"></i></span>
        `;
        attachmentsPreview.appendChild(chip);
    });
}

function removeSelectedFile(index) {
    selectedFiles.splice(index, 1);
    renderFilePreviews();
    sendBtn.disabled = (messageInput.value.trim() === '' && selectedFiles.length === 0);
}

// Message submission
function submitMessage() {
    if (!selectedId || !selectedType) return;
    
    const text = messageInput.value.trim();
    if (text === '' && selectedFiles.length === 0) return;

    sendBtn.disabled = true;

    const fd = new FormData();
    if (selectedType === 'group') {
        fd.append('group_id', selectedId);
    } else {
        fd.append('recipient_id', selectedId);
    }
    
    if (text) {
        fd.append('message', text);
    }

    selectedFiles.forEach(file => {
        fd.append('attachments[]', file);
    });

    fetch('/api/chat/send', {
        method: 'POST',
        headers: getHeaders(),
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            sendBtn.disabled = false;
            return;
        }

        // Clear input and files
        messageInput.value = '';
        selectedFiles = [];
        renderFilePreviews();
        
        // Reload messages stream
        loadConversationMessages();
    })
    .catch(err => {
        console.error('Failed to send message', err);
        sendBtn.disabled = false;
    });
}

// Delete Message call
function submitDeleteMessage(id) {
    if (!confirm('Are you sure you want to delete this message? This action is toggleable and uses soft-delete.')) {
        return;
    }

    fetch(`/api/chat/delete/${id}`, {
        method: 'DELETE',
        headers: getHeaders()
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            return;
        }
        
        // Reload messages
        loadConversationMessages();
        
        // If thread is active, reload thread too
        if (activeThreadId) {
            loadThreadReplies();
        }
    })
    .catch(err => console.error('Failed to delete message', err));
}

// Forward Handlers
function triggerForwardMessage(msgId, messageText) {
    document.getElementById('forward-source-message-id').value = msgId;
    document.getElementById('forward-message-preview-text').innerText = messageText || '(Attachments / Files)';
    document.getElementById('forward-destination-select').value = '';

    const modal = new bootstrap.Modal(document.getElementById('forwardMessageModal'));
    modal.show();
}

function submitForwardMessage(event) {
    event.preventDefault();

    const msgId = document.getElementById('forward-source-message-id').value;
    const destVal = document.getElementById('forward-destination-select').value;
    
    if (!destVal) return;

    const parts = destVal.split(':');
    const type = parts[0];
    const targetId = parts[1];

    const body = { message_id: msgId };
    if (type === 'group') {
        body.group_id = targetId;
    } else {
        body.recipient_id = targetId;
    }

    fetch('/api/chat/forward', {
        method: 'POST',
        headers: getHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(body)
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            return;
        }

        // Close modal
        const modalEl = document.getElementById('forwardMessageModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();

        // Switch to the target destination conversation to see the forward
        selectChat(null, type, targetId, (type === 'group' ? `# ${channelsList.find(c=>c.id==targetId).name}` : `@ ${contactsList.find(c=>c.id==targetId).name}`));
    })
    .catch(err => console.error('Failed to forward message', err));
}

// Thread Handlers
function openThreadPanel(msgId) {
    activeThreadId = msgId;
    threadPanel.style.display = 'flex';
    
    // Clear existing thread poll
    if (window.threadPollInterval) {
        clearInterval(window.threadPollInterval);
    }

    // Load replies
    loadThreadReplies();
    
    // Setup thread polling
    window.threadPollInterval = setInterval(pollThreadReplies, 3000);
}

function closeThreadPanel() {
    activeThreadId = null;
    threadPanel.style.display = 'none';
    if (window.threadPollInterval) {
        clearInterval(window.threadPollInterval);
        window.threadPollInterval = null;
    }
}

function loadThreadReplies() {
    if (!activeThreadId) return;

    fetch(`/api/chat/thread/${activeThreadId}`, { headers: getHeaders() })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                console.error(data.error);
                closeThreadPanel();
                return;
            }

            renderParentMessageInThread(data.parent);
            renderMessageStream(data.replies, threadRepliesStream);
        })
        .catch(err => console.error('Failed to load thread replies', err));
}

function pollThreadReplies() {
    if (!document.getElementById('slack-chat-container')) {
        clearInterval(window.threadPollInterval);
        window.threadPollInterval = null;
        return;
    }

    if (activeThreadId) {
        fetch(`/api/chat/thread/${activeThreadId}`, { headers: getHeaders() })
            .then(res => res.json())
            .then(data => {
                if (activeThreadId == data.parent.id) {
                    renderMessageStream(data.replies, threadRepliesStream);
                }
            })
            .catch(err => console.error('Failed in polling thread replies', err));
    }
}

function renderParentMessageInThread(msg) {
    const initials = getInitials(msg.sender_name);
    const avatarBg = getAvatarColor(msg.sender_name);
    const timeStr = new Date(msg.created_at).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });

    let textDisplay = msg.message || '';
    if (msg.deleted_at || msg.is_deleted === 1) {
        textDisplay = '<span class="text-muted italic"><i class="bi bi-trash me-1"></i>This message was deleted.</span>';
    }

    let attachmentsHtml = '';
    if (msg.attachments && msg.attachments.length > 0 && !msg.deleted_at) {
        attachmentsHtml = '<div class="message-attachments">';
        msg.attachments.forEach(at => {
            if (at.mime_type && at.mime_type.startsWith('image/')) {
                attachmentsHtml += `
                    <div class="attachment-image-preview" onclick="viewFullImage('${at.file_path}')">
                        <img src="${at.file_path}" alt="${at.filename}">
                    </div>
                `;
            } else {
                attachmentsHtml += `
                    <a href="${at.file_path}" target="_blank" download="${at.filename}" class="attachment-card">
                        <span class="attachment-icon"><i class="bi ${getFileIconClass(at.mime_type)}"></i></span>
                        <div class="attachment-info">
                            <span class="attachment-name">${at.filename}</span>
                            <span class="attachment-size">${formatBytes(at.file_size)}</span>
                        </div>
                    </a>
                `;
            }
        });
        attachmentsHtml += '</div>';
    }

    threadParentMsgContainer.innerHTML = `
        <div class="message-item px-0 py-2">
            <div class="message-avatar" style="background-color: ${avatarBg};">
                ${initials}
            </div>
            <div class="message-content-wrapper">
                <div class="message-meta">
                    <span class="message-sender">${msg.sender_name}</span>
                    ${msg.sender_role ? `<span class="message-role">${msg.sender_role}</span>` : ''}
                    <span class="message-time">${timeStr}</span>
                </div>
                <div class="message-text">${textDisplay}</div>
                ${attachmentsHtml}
            </div>
        </div>
    `;
}

// Thread Input handler triggers
threadMessageInput.addEventListener('input', function() {
    threadSendBtn.disabled = (this.value.trim() === '' && selectedThreadFiles.length === 0);
});

function triggerThreadFileUpload() {
    threadFileInput.click();
}

function handleThreadFileSelection(event) {
    const files = Array.from(event.target.files);
    selectedThreadFiles = selectedThreadFiles.concat(files);
    renderThreadFilePreviews();
    threadSendBtn.disabled = false;
}

function renderThreadFilePreviews() {
    if (selectedThreadFiles.length === 0) {
        threadAttachmentsPreview.classList.add('d-none');
        return;
    }

    threadAttachmentsPreview.classList.remove('d-none');
    threadAttachmentsPreview.innerHTML = '';
    selectedThreadFiles.forEach((file, index) => {
        const chip = document.createElement('div');
        chip.className = 'preview-chip';
        chip.innerHTML = `
            <span class="preview-chip-name">${file.name}</span>
            <span class="preview-chip-remove" onclick="removeSelectedThreadFile(${index})"><i class="bi bi-x-circle-fill"></i></span>
        `;
        threadAttachmentsPreview.appendChild(chip);
    });
}

function removeSelectedThreadFile(index) {
    selectedThreadFiles.splice(index, 1);
    renderThreadFilePreviews();
    threadSendBtn.disabled = (threadMessageInput.value.trim() === '' && selectedThreadFiles.length === 0);
}

// Submit reply thread message
function submitThreadMessage() {
    if (!activeThreadId) return;

    const text = threadMessageInput.value.trim();
    if (text === '' && selectedThreadFiles.length === 0) return;

    threadSendBtn.disabled = true;

    const fd = new FormData();
    fd.append('parent_message_id', activeThreadId);
    
    // Auto populate group_id or recipient_id based on active main thread
    if (selectedType === 'group') {
        fd.append('group_id', selectedId);
    } else {
        fd.append('recipient_id', selectedId);
    }

    if (text) {
        fd.append('message', text);
    }

    selectedThreadFiles.forEach(file => {
        fd.append('attachments[]', file);
    });

    fetch('/api/chat/send', {
        method: 'POST',
        headers: getHeaders(),
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            threadSendBtn.disabled = false;
            return;
        }

        // Clear input and files
        threadMessageInput.value = '';
        selectedThreadFiles = [];
        renderThreadFilePreviews();
        
        // Reload thread and main conversation thread counts
        loadThreadReplies();
        loadConversationMessages();
    })
    .catch(err => {
        console.error('Failed to send reply', err);
        threadSendBtn.disabled = false;
    });
}

// Create Channel Modal trigger
function openCreateChannelModal() {
    const modal = new bootstrap.Modal(document.getElementById('createChannelModal'));
    modal.show();
}

function submitCreateChannel(event) {
    event.preventDefault();

    const name = document.getElementById('new-channel-name').value.trim();
    if (!name) return;

    const checklist = document.getElementById('channel-members-checklist');
    const checkedBoxes = checklist.querySelectorAll('input[type="checkbox"]:checked');
    const userIds = Array.from(checkedBoxes).map(cb => parseInt(cb.value));

    const submitBtn = document.getElementById('create-channel-submit-btn');
    submitBtn.disabled = true;

    fetch('/api/chat/admin/groups', {
        method: 'POST',
        headers: getHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ name: name, user_ids: userIds })
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            submitBtn.disabled = false;
            return;
        }

        // Reset form and modal
        document.getElementById('create-channel-form').reset();
        const modalEl = document.getElementById('createChannelModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
        submitBtn.disabled = false;

        // Reload context to populate new channels list
        fetch('/api/chat/context', { headers: getHeaders() })
            .then(res => res.json())
            .then(data => {
                channelsList = data.channels || [];
                renderSidebar();
                populateForwardModalDestinations();
            });
    })
    .catch(err => {
        console.error('Failed to create channel', err);
        submitBtn.disabled = false;
    });
}

// View Full Image helper modal preview
function viewFullImage(url) {
    window.open(url, '_blank');
}

// Escape HTML utility
function escapeHtml(text) {
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/*
|--------------------------------------------------------------------------
| Admin Console Handlers & Mappings
|--------------------------------------------------------------------------
*/

function toggleAdminPanel(event, show) {
    if (event) event.preventDefault();

    if (show) {
        chatMainPanel.style.display = 'none';
        threadPanel.style.display = 'none';
        adminDashboardPanel.style.display = 'flex';
        
        // Remove active state sidebar
        document.querySelectorAll('.sidebar-item-link').forEach(el => el.classList.remove('active'));
        document.getElementById('admin-panel-toggle').classList.add('active');

        // Load admin directory
        loadAdminDirectory();
    } else {
        adminDashboardPanel.style.display = 'none';
        chatMainPanel.style.display = 'flex';
        document.getElementById('admin-panel-toggle').classList.remove('active');
        
        // Restore active conversation selection if exists
        if (selectedId && selectedType) {
            selectChat(null, selectedType, selectedId, (selectedType === 'group' ? `# ${channelsList.find(c=>c.id==selectedId).name}` : `@ ${contactsList.find(c=>c.id==selectedId).name}`));
        } else {
            chatLoadingOverlay.classList.remove('d-none');
            activeChatContainer.classList.add('d-none');
        }
    }
}

function loadAdminDirectory() {
    fetch('/api/chat/admin/directory', { headers: getHeaders() })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                toggleAdminPanel(null, false);
                return;
            }

            renderAdminRuleDropdowns(data.users);
            renderAdminRulesTable(data.rules);
            renderAdminUsersTable(data.users);
        })
        .catch(err => console.error('Failed to load admin directory', err));
}

function renderAdminRuleDropdowns(users) {
    ruleSenderSelect.innerHTML = '<option value="">Choose User...</option>';
    ruleRecipientSelect.innerHTML = '<option value="">Choose Recipient...</option>';

    users.forEach(u => {
        const optSender = document.createElement('option');
        optSender.value = u.id;
        optSender.innerText = `${u.name} (${u.email})`;
        ruleSenderSelect.appendChild(optSender);

        const optRecipient = document.createElement('option');
        optRecipient.value = u.id;
        optRecipient.innerText = `${u.name} (${u.email})`;
        ruleRecipientSelect.appendChild(optRecipient);
    });
}

function renderAdminRulesTable(rules) {
    adminRulesTableBody.innerHTML = '';
    
    if (rules.length === 0) {
        adminRulesTableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No custom DM mapping rules defined.</td></tr>';
        return;
    }

    rules.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${r.sender_name}</td>
            <td>${r.recipient_name}</td>
            <td class="text-center">
                <button class="btn btn-xs btn-danger p-1 py-0" onclick="deleteCommunicationRule(${r.id})" title="Revoke rule">
                    <i class="bi bi-x-circle"></i>
                </button>
            </td>
        `;
        adminRulesTableBody.appendChild(tr);
    });
}

function renderAdminUsersTable(users) {
    adminUsersTableBody.innerHTML = '';

    users.forEach(u => {
        const tr = document.createElement('tr');
        const isChecked = (u.can_delete_chats == 1 || u.can_delete_chats === true);
        tr.innerHTML = `
            <td>${u.name}</td>
            <td>${u.email}</td>
            <td><span class="badge bg-light text-dark border">${u.role_name || 'Staff'}</span></td>
            <td class="text-center">
                <div class="form-check form-switch d-inline-block">
                    <input class="form-check-input" type="checkbox" role="switch" 
                           id="user-delete-switch-${u.id}" 
                           ${isChecked ? 'checked' : ''} 
                           onchange="toggleDeletePermissionCheckbox(${u.id}, this.checked)">
                </div>
            </td>
        `;
        adminUsersTableBody.appendChild(tr);
    });
}

function submitCommunicationRule(event) {
    event.preventDefault();

    const senderId = ruleSenderSelect.value;
    const recipientId = ruleRecipientSelect.value;

    if (!senderId || !recipientId) return;

    fetch('/api/chat/admin/rules', {
        method: 'POST',
        headers: getHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ user_id: senderId, allowed_user_id: recipientId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            return;
        }

        // Reset selects and refresh table
        ruleSenderSelect.value = '';
        ruleRecipientSelect.value = '';
        loadAdminDirectory();
    })
    .catch(err => console.error('Failed to create permission mapping rule', err));
}

function deleteCommunicationRule(id) {
    if (!confirm('Are you sure you want to revoke this communication rule?')) return;

    fetch(`/api/chat/admin/rules/${id}`, {
        method: 'DELETE',
        headers: getHeaders()
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            return;
        }
        loadAdminDirectory();
    })
    .catch(err => console.error('Failed to delete permission rule', err));
}

function toggleDeletePermissionCheckbox(userId, canDelete) {
    fetch('/api/chat/admin/delete-permission', {
        method: 'POST',
        headers: getHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ user_id: userId, can_delete_chats: canDelete })
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            // Revert state
            document.getElementById(`user-delete-switch-${userId}`).checked = !canDelete;
            return;
        }
        
        // If the toggled user is the current user, update our local cache
        if (userId == currentUser.id) {
            currentUser.can_delete_chats = canDelete;
        }
    })
    .catch(err => {
        console.error('Failed to toggle delete permission', err);
        document.getElementById(`user-delete-switch-${userId}`).checked = !canDelete;
    });
}

// Bind to window for HTML click callbacks
window.openCreateChannelModal = openCreateChannelModal;
window.submitCreateChannel = submitCreateChannel;
window.triggerFileUpload = triggerFileUpload;
window.handleFileSelection = handleFileSelection;
window.removeSelectedFile = removeSelectedFile;
window.submitMessage = submitMessage;
window.submitDeleteMessage = submitDeleteMessage;
window.triggerForwardMessage = triggerForwardMessage;
window.submitForwardMessage = submitForwardMessage;
window.openThreadPanel = openThreadPanel;
window.closeThreadPanel = closeThreadPanel;
window.triggerThreadFileUpload = triggerThreadFileUpload;
window.handleThreadFileSelection = handleThreadFileSelection;
window.removeSelectedThreadFile = removeSelectedThreadFile;
window.submitThreadMessage = submitThreadMessage;
window.toggleAdminPanel = toggleAdminPanel;
window.submitCommunicationRule = submitCommunicationRule;
window.deleteCommunicationRule = deleteCommunicationRule;
window.toggleDeletePermissionCheckbox = toggleDeletePermissionCheckbox;
window.selectChat = selectChat;

// Run initial boot
initializeChat();
