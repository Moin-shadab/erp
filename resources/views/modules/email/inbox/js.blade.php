// Ensure AG Grid loaded
if (typeof agGrid === 'undefined') {
    const gridScript = document.createElement('script');
    gridScript.src = 'https://cdn.jsdelivr.net/npm/ag-grid-community@30.2.1/dist/ag-grid-community.min.js';
    gridScript.onload = () => initEmailGrid();
    document.head.appendChild(gridScript);
} else {
    initEmailGrid();
}

var emailGridOptions = null;
var gridApi = null;
var activeFolder = 'INBOX';
var activeLabel = null;
var activeEmail = null; // Represents currently loaded email metadata
var activeEmailAccountId = {{ $account->id ?? 'null' }};

function initEmailGrid() {
    const gridDiv = document.querySelector('#emailGrid');
    if (!gridDiv) {
        // Retry safety check if DOM is not fully painted yet
        setTimeout(initEmailGrid, 50);
        return;
    }

    if (gridDiv.hasAttribute('data-grid-initialized')) {
        return;
    }
    gridDiv.setAttribute('data-grid-initialized', 'true');
    
    const columnDefs = [
        {
            field: 'from_name',
            headerName: 'From',
            width: 140,
            cellStyle: params => params.data && !params.data.is_read ? { fontWeight: 'bold' } : null
        },
        {
            field: 'subject',
            headerName: 'Subject',
            flex: 1,
            cellRenderer: params => {
                if (!params.data) return params.value || '';
                let html = '';
                if (params.data.labels && params.data.labels.length > 0) {
                    params.data.labels.forEach(lbl => {
                        html += `<span class="badge me-1 border" style="background-color: ${lbl.color}15; color: ${lbl.color}; border-color: ${lbl.color}40 !important; font-size: 0.7rem; padding: 2px 6px;">${lbl.name}</span>`;
                    });
                }
                html += `<span>${params.value || '(No Subject)'}</span>`;
                return html;
            },
            cellStyle: params => params.data && !params.data.is_read ? { fontWeight: 'bold' } : null
        },
        {
            field: 'date_sent',
            headerName: 'Date',
            width: 100,
            cellRenderer: params => {
                if (!params.value) return '';
                const date = new Date(params.value);
                return date.toLocaleDateString(undefined, {month: 'short', day: 'numeric'});
            },
            cellStyle: params => params.data && !params.data.is_read ? { fontWeight: 'bold' } : null
        }
    ];

    emailGridOptions = {
        columnDefs: columnDefs,
        rowSelection: 'single',
        rowHeight: 45,
        headerHeight: 0, // Gmail style clean headerless
        pagination: true,
        paginationPageSize: 15,
        onRowClicked: event => loadEmailThread(event.data),
        onGridReady: event => {
            gridApi = event.api;
            loadEmails();
        },
        getRowStyle: params => {
            if (params.data && !params.data.is_read) {
                return { background: '#f8fafc' };
            }
            return null;
        }
    };

    try {
        new agGrid.Grid(gridDiv, emailGridOptions);
    } catch (e) {
        console.error('Failed to initialize AG Grid:', e);
    }
}

function loadEmails() {
    if (!gridApi) return;
    gridApi.showLoadingOverlay();
    
    let url = `/api/email/list`;
    const params = [];
    if (activeFolder === 'LABEL' && activeLabel) {
        params.push(`label=${encodeURIComponent(activeLabel)}`);
    } else {
        params.push(`folder=${activeFolder}`);
    }
    
    const searchVal = document.getElementById('email-search')?.value || '';
    if (searchVal.trim()) {
        params.push(`search=${encodeURIComponent(searchVal)}`);
    }
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (gridApi) {
                gridApi.setRowData(data.data);
                gridApi.hideOverlay();
            }
        })
        .catch(err => {
            if (gridApi) {
                gridApi.setRowData([]);
                gridApi.hideOverlay();
            }
        });
}

function switchFolder(element, folder) {
    if (window.event) window.event.preventDefault();
    document.querySelectorAll('#folder-list a').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('#label-list a').forEach(el => el.classList.remove('active'));
    if (element) element.classList.add('active');
    activeFolder = folder;
    activeLabel = null;
    document.getElementById('current-folder-title').textContent = folder.charAt(0) + folder.slice(1).toLowerCase();
    
    // Hide preview pane
    const previewPane = document.getElementById('preview-pane');
    previewPane.classList.add('d-none');
    previewPane.classList.remove('d-flex');
    
    const placeholder = document.getElementById('preview-placeholder');
    placeholder.classList.remove('d-none');
    placeholder.classList.add('d-flex');
    
    loadEmails();
}

var searchTimeout = null;
function onEmailSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadEmails();
    }, 450);
}

// Load message stream & attachments
function loadEmailThread(mail) {
    activeEmail = mail;
    populateThreadLabelDropdown(mail);
    
    const placeholder = document.getElementById('preview-placeholder');
    placeholder.classList.add('d-none');
    placeholder.classList.remove('d-flex');
    
    const preview = document.getElementById('preview-pane');
    preview.classList.remove('d-none');
    preview.classList.add('d-flex');
    
    document.getElementById('preview-subject').textContent = mail.subject || '(No Subject)';
    
    // Configure Star icon
    const starBtn = document.getElementById('preview-star-btn');
    starBtn.innerHTML = mail.is_starred ? `<i class="bi bi-star-fill text-warning"></i>` : `<i class="bi bi-star"></i>`;

    const container = document.getElementById('preview-messages-container');
    container.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
        </div>
    `;

    fetch('/api/email/thread/' + mail.thread_id)
        .then(r => r.json())
        .then(data => {
            container.innerHTML = '';
            data.messages.forEach(msg => {
                const div = document.createElement('div');
                div.className = 'border rounded-3 p-3 mb-3 bg-white shadow-sm';
                
                let attachmentsHtml = '';
                if (msg.attachments && msg.attachments.length > 0) {
                    attachmentsHtml = '<div class="mt-3 border-top pt-2"><strong class="small text-muted d-block mb-2"><i class="bi bi-paperclip"></i> Attachments</strong><div class="d-flex flex-wrap gap-2">';
                    msg.attachments.forEach(att => {
                        attachmentsHtml += `
                            <a href="/api/email/attachment/${att.id}" target="_blank" class="btn btn-xs btn-light border d-inline-flex align-items-center text-decoration-none py-1 px-2 rounded" style="font-size:0.75rem;">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i> ${att.filename} (${(att.file_size/1024).toFixed(1)} KB)
                            </a>
                        `;
                    });
                    attachmentsHtml += '</div></div>';
                }

                div.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong style="font-size:0.85rem;">${msg.from_name}</strong>
                            <span class="text-muted small">&lt;${msg.from_address}&gt;</span>
                        </div>
                        <small class="text-muted">${msg.date_formatted}</small>
                    </div>
                    <div class="email-body-content" style="font-size:0.85rem; line-height:1.5;">
                        ${msg.body_html || nl2br(msg.body_text) || '(No Content)'}
                    </div>
                    ${attachmentsHtml}
                `;
                container.appendChild(div);
            });

            // Update unread status in mid-grid row data
            mail.is_read = true;
            if (gridApi) {
                gridApi.applyTransaction({ update: [mail] });
            }
            
            // Refresh counts in folder badge (approximate)
            const badge = document.getElementById('count-inbox');
            if (badge && activeFolder === 'INBOX') {
                const curr = parseInt(badge.textContent) - 1;
                badge.textContent = curr > 0 ? curr : 0;
            }
        });
}

function nl2br(str) {
    return (str || '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
}

function toggleActiveStar() {
    if (!activeEmail) return;

    fetch('/api/email/toggle-star/' + activeEmail.id, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(r => r.json())
    .then(data => {
        activeEmail.is_starred = data.is_starred;
        const starBtn = document.getElementById('preview-star-btn');
        starBtn.innerHTML = data.is_starred ? `<i class="bi bi-star-fill text-warning"></i>` : `<i class="bi bi-star"></i>`;
        showToast('success', data.is_starred ? 'Conversation starred' : 'Conversation unstarred');
    });
}

function moveActiveEmail(folder) {
    if (!activeEmail) return;

    fetch('/api/email/move-folder/' + activeEmail.id, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ folder: folder })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Conversation moved to ' + folder.toLowerCase());
            
            // Hide preview pane
            const previewPane = document.getElementById('preview-pane');
            previewPane.classList.add('d-none');
            previewPane.classList.remove('d-flex');
            
            const placeholder = document.getElementById('preview-placeholder');
            placeholder.classList.remove('d-none');
            placeholder.classList.add('d-flex');
            
            // Remove from grid list
            if (gridApi) {
                gridApi.applyTransaction({ remove: [activeEmail] });
            }
            activeEmail = null;
        }
    });
}

// Reply & Forward actions transition to compose view with context parameters
function replyToActiveEmail() {
    if (!activeEmail) return;
    loadPage('/email/compose?reply_to=' + activeEmail.id);
    setActiveMenuItem(document.querySelector('a[onclick*="compose"]'));
}

function forwardActiveEmail() {
    if (!activeEmail) return;
    loadPage('/email/compose?reply_to=' + activeEmail.id + '&forward=1');
    setActiveMenuItem(document.querySelector('a[onclick*="compose"]'));
}

function refreshActiveAccountEmails(btn) {
    if (!activeEmailAccountId) {
        showToast('error', 'No active email account to sync.');
        return;
    }

    const icon = document.getElementById('sync-icon');
    if (icon) {
        icon.classList.add('spin');
        if (!document.getElementById('sync-spin-style')) {
            const style = document.createElement('style');
            style.id = 'sync-spin-style';
            style.innerHTML = `
                @keyframes sync-spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                .spin {
                    animation: sync-spin 1s linear infinite;
                    display: inline-block;
                }
            `;
            document.head.appendChild(style);
        }
    }

    if (btn) btn.disabled = true;

    fetch('/api/email/sync/' + activeEmailAccountId, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message || 'Emails synced successfully.');
            loadEmails();
            // Refresh folder counts
            fetch('/api/email/folder-counts')
                .then(r => r.json())
                .then(counts => {
                    for (const folder in counts) {
                        const badge = document.getElementById('count-' + folder.toLowerCase());
                        if (badge) {
                            badge.textContent = counts[folder];
                        }
                    }
                });
        } else {
            showToast('error', data.message || 'Sync failed.');
            
            // Populate and show the sync logs modal
            const errorMsgEl = document.getElementById('sync-error-message');
            const logsContainer = document.getElementById('sync-logs-container');
            const modalEl = document.getElementById('syncLogsModal');
            
            if (errorMsgEl) {
                errorMsgEl.textContent = data.message || 'Failed to sync email account.';
            }
            if (logsContainer) {
                if (data.logs && Array.isArray(data.logs) && data.logs.length > 0) {
                    logsContainer.textContent = data.logs.join('\n');
                } else {
                    logsContainer.textContent = 'No session connection logs captured.';
                }
            }
            if (modalEl) {
                // Move to body to prevent truncation/z-index issues
                document.body.appendChild(modalEl);
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        }
    })
    .catch(err => {
        showToast('error', 'Network error during sync.');
    })
    .finally(() => {
        if (icon) icon.classList.remove('spin');
        if (btn) btn.disabled = false;
    });
}

function openEmailSettingsModal() {
    const modalEl = document.getElementById('emailSettingsModal');
    if (!modalEl) return;
    
    // Move to body to prevent clipping/truncation by parent container styles
    document.body.appendChild(modalEl);
    
    fetch('/api/email/settings')
        .then(r => r.json())
        .then(data => {
            document.getElementById('settings-email').value = data.email || '';
            document.getElementById('settings-display-name').value = data.display_name || '';
            
            const passInput = document.getElementById('settings-password');
            if (passInput) {
                passInput.value = data.password || '';
            }
            
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        })
        .catch(err => {
            showToast('error', 'Failed to retrieve mail settings.');
        });
}

function toggleSettingsPasswordVisibility() {
    const passInput = document.getElementById('settings-password');
    const icon = document.getElementById('settings-password-toggle-icon');
    if (passInput && icon) {
        if (passInput.type === 'password') {
            passInput.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            passInput.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    }
}

function saveEmailSettings(event) {
    event.preventDefault();
    
    const saveBtn = document.getElementById('save-settings-btn');
    if (saveBtn) saveBtn.disabled = true;
    
    const payload = {
        email: document.getElementById('settings-email').value,
        display_name: document.getElementById('settings-display-name').value
    };
    
    const passInput = document.getElementById('settings-password');
    if (passInput && passInput.value) {
        payload.password = passInput.value;
    }
    
    fetch('/api/email/settings', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Mail settings saved successfully.');
            const modalEl = document.getElementById('emailSettingsModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            
            // Reload page or email accounts
            loadPage('/email/inbox');
        } else {
            showToast('error', data.error || 'Failed to save settings.');
        }
    })
    .catch(err => {
        showToast('error', 'Network error during save.');
    })
    .finally(() => {
        if (saveBtn) saveBtn.disabled = false;
    });
}

function loadLabelsSidebar() {
    fetch('/api/email/labels')
        .then(r => r.json())
        .then(labels => {
            const list = document.getElementById('label-list');
            if (!list) return;
            list.innerHTML = '';
            labels.forEach(lbl => {
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action border-0 rounded-2 py-2 d-flex justify-content-between align-items-center';
                if (activeFolder === 'LABEL' && activeLabel === lbl.name) {
                    a.classList.add('active');
                }
                a.onclick = (e) => {
                    e.preventDefault();
                    switchLabel(a, lbl.name);
                };
                a.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <span><i class="bi bi-tag-fill me-2" style="color: ${lbl.color};"></i> ${lbl.name}</span>
                        <button class="btn btn-link p-0 text-muted label-delete-btn" onclick="deleteLabel(event, ${lbl.id})" style="visibility: hidden;">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                `;
                list.appendChild(a);
            });
        });
}

function switchLabel(element, labelName) {
    if (window.event) window.event.preventDefault();
    document.querySelectorAll('#folder-list a').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('#label-list a').forEach(el => el.classList.remove('active'));
    if (element) element.classList.add('active');
    activeFolder = 'LABEL';
    activeLabel = labelName;
    document.getElementById('current-folder-title').textContent = 'Label: ' + labelName;
    
    // Hide preview pane
    const previewPane = document.getElementById('preview-pane');
    previewPane.classList.add('d-none');
    previewPane.classList.remove('d-flex');
    
    const placeholder = document.getElementById('preview-placeholder');
    placeholder.classList.remove('d-none');
    placeholder.classList.add('d-flex');
    
    loadEmails();
}

function openCreateLabelModal() {
    const modalEl = document.getElementById('createLabelModal');
    if (!modalEl) return;
    
    // Move to body to prevent clipping/truncation by parent container styles
    document.body.appendChild(modalEl);
    
    document.getElementById('label-name-input').value = '';
    document.getElementById('label-color-input').value = '#3b82f6';
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function saveNewLabel(event) {
    event.preventDefault();
    const saveBtn = document.getElementById('save-label-btn');
    if (saveBtn) saveBtn.disabled = true;

    const payload = {
        name: document.getElementById('label-name-input').value,
        color: document.getElementById('label-color-input').value
    };

    fetch('/api/email/labels', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Label created.');
            const modalEl = document.getElementById('createLabelModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            loadLabelsSidebar();
        } else {
            showToast('error', data.error || 'Failed to create label.');
        }
    })
    .catch(err => showToast('error', 'Network error.'))
    .finally(() => {
        if (saveBtn) saveBtn.disabled = false;
    });
}

function deleteLabel(event, id) {
    event.stopPropagation();
    event.preventDefault();
    if (!confirm('Are you sure you want to delete this label?')) return;

    fetch('/api/email/label/' + id, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Label deleted.');
            if (activeFolder === 'LABEL') {
                switchFolder(document.querySelector('#folder-list a'), 'INBOX');
            } else {
                loadLabelsSidebar();
            }
        } else {
            showToast('error', data.error || 'Failed to delete label.');
        }
    })
    .catch(err => showToast('error', 'Network error.'));
}

function populateThreadLabelDropdown(mail) {
    const dropdownMenu = document.getElementById('thread-label-dropdown-menu');
    if (!dropdownMenu) return;

    fetch('/api/email/labels')
        .then(r => r.json())
        .then(labels => {
            dropdownMenu.innerHTML = '';
            if (labels.length === 0) {
                dropdownMenu.innerHTML = '<li class="px-3 py-1 text-muted small">No labels created yet.</li>';
                return;
            }

            const activeLabelIds = (mail.labels || []).map(l => l.id);

            labels.forEach(lbl => {
                const isChecked = activeLabelIds.includes(lbl.id);
                const li = document.createElement('li');
                li.className = 'px-3 py-1';
                li.innerHTML = `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="${lbl.id}" id="chk-label-${lbl.id}" ${isChecked ? 'checked' : ''} onchange="toggleLabelOnActiveEmail(this, ${lbl.id})">
                        <label class="form-check-label small" for="chk-label-${lbl.id}">
                            <i class="bi bi-tag-fill me-1" style="color: ${lbl.color};"></i> ${lbl.name}
                        </label>
                    </div>
                `;
                dropdownMenu.appendChild(li);
            });
        });
}

function toggleLabelOnActiveEmail(checkbox, labelId) {
    if (!activeEmail) return;

    const apply = checkbox.checked;
    fetch('/api/email/apply-label', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            email_id: activeEmail.id,
            label_id: labelId,
            apply: apply
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', apply ? 'Label applied' : 'Label removed');
            if (!activeEmail.labels) activeEmail.labels = [];
            if (apply) {
                fetch('/api/email/labels')
                    .then(r => r.json())
                    .then(labels => {
                        const lbl = labels.find(l => l.id === labelId);
                        if (lbl) {
                            activeEmail.labels.push(lbl);
                            if (gridApi) {
                                gridApi.applyTransaction({ update: [activeEmail] });
                            }
                        }
                    });
            } else {
                activeEmail.labels = activeEmail.labels.filter(l => l.id !== labelId);
                if (gridApi) {
                    gridApi.applyTransaction({ update: [activeEmail] });
                }
            }
        } else {
            checkbox.checked = !apply;
            showToast('error', data.error || 'Failed to update label.');
        }
    })
    .catch(err => {
        checkbox.checked = !apply;
        showToast('error', 'Network error.');
    });
}

// Bind functions to window object for inline HTML event handlers (e.g. onclick) when executed inside an IIFE
window.initEmailGrid = initEmailGrid;
window.loadEmails = loadEmails;
window.switchFolder = switchFolder;
window.onEmailSearch = onEmailSearch;
window.loadEmailThread = loadEmailThread;
window.toggleActiveStar = toggleActiveStar;
window.moveActiveEmail = moveActiveEmail;
window.replyToActiveEmail = replyToActiveEmail;
window.forwardActiveEmail = forwardActiveEmail;
window.refreshActiveAccountEmails = refreshActiveAccountEmails;
window.openEmailSettingsModal = openEmailSettingsModal;
window.toggleSettingsPasswordVisibility = toggleSettingsPasswordVisibility;
window.saveEmailSettings = saveEmailSettings;
window.loadLabelsSidebar = loadLabelsSidebar;
window.switchLabel = switchLabel;
window.openCreateLabelModal = openCreateLabelModal;
window.saveNewLabel = saveNewLabel;
window.deleteLabel = deleteLabel;
window.populateThreadLabelDropdown = populateThreadLabelDropdown;
window.toggleLabelOnActiveEmail = toggleLabelOnActiveEmail;

function switchEmailAccountLocal(event, accId, email) {
    if (event) event.preventDefault();
    
    fetch('/api/email/switch-account', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ email_account_id: accId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            activeEmailAccountId = accId;
            const topbarSwitcher = document.getElementById('email-account-switcher');
            if (topbarSwitcher) {
                topbarSwitcher.value = accId;
            }
            loadPage('/erp/email-inbox');
            showToast('success', 'Switched to account: ' + email);
        } else {
            showToast('error', 'Failed to switch account.');
        }
    })
    .catch(err => showToast('error', 'Network error.'));
}
window.switchEmailAccountLocal = switchEmailAccountLocal;
