// Rich Text Editor Commands
function execCmd(command, val = null) {
    document.execCommand(command, false, val);
}

function insertLink() {
    const url = prompt("Enter link URL:");
    if (url) {
        execCmd('createLink', url);
    }
}

// Attachment files handling
let selectedFiles = [];

const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('attachment-input');

if (dropzone) {
    dropzone.onclick = () => fileInput.click();

    dropzone.ondragover = (e) => {
        e.preventDefault();
        dropzone.classList.add('bg-primary-subtle');
    };

    dropzone.ondragleave = () => {
        dropzone.classList.remove('bg-primary-subtle');
    };

    dropzone.ondrop = (e) => {
        e.preventDefault();
        dropzone.classList.remove('bg-primary-subtle');
        if (e.dataTransfer.files.length) {
            addFiles(e.dataTransfer.files);
        }
    };
}

function handleFileSelect(e) {
    if (e.target.files.length) {
        addFiles(e.target.files);
    }
}

function addFiles(files) {
    Array.from(files).forEach(f => {
        selectedFiles.push(f);
    });
    renderFileList();
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    renderFileList();
}

function renderFileList() {
    const list = document.getElementById('file-list');
    if (!list) return;
    list.innerHTML = '';
    selectedFiles.forEach((f, idx) => {
        const div = document.createElement('div');
        div.className = 'badge bg-white border text-muted d-flex align-items-center p-2 rounded-3';
        div.innerHTML = `
            <i class="bi bi-paperclip me-1"></i> ${f.name} (${(f.size/1024).toFixed(1)} KB)
            <button type="button" class="btn-close ms-2" style="font-size:0.55rem;" onclick="event.stopPropagation(); removeFile(${idx})"></button>
        `;
        list.appendChild(div);
    });
}

// Templates and signatures injection
function applyTemplate(tmpl) {
    document.getElementById('c-subject').value = tmpl.subject || '';
    document.getElementById('compose-body').innerHTML = tmpl.body || '';
    toggleSignature(); // append signature if active
    showToast('success', 'Template applied.');
}

function toggleSignature() {
    const hasSig = document.getElementById('signature-switch') ? document.getElementById('signature-switch').checked : false;
    const sigVal = document.getElementById('raw-signature') ? document.getElementById('raw-signature').value : '';
    const editor = document.getElementById('compose-body');

    if (!editor) return;

    // Remove existing signature placeholder if present
    let html = editor.innerHTML;
    const sigMarkIndex = html.indexOf('<div class="email-sig-wrapper"');
    if (sigMarkIndex !== -1) {
        html = html.substring(0, sigMarkIndex);
    }

    if (hasSig && sigVal) {
        html += `<div class="email-sig-wrapper" style="margin-top: 20px;"><br>--<br>${sigVal}</div>`;
    }

    editor.innerHTML = html;
}

// Append signature initially if exists
setTimeout(toggleSignature, 100);

// Auto-save draft setup
let isFormDirty = false;
const composeForm = document.getElementById('email-compose-form');
const composeBody = document.getElementById('compose-body');

if (composeForm) {
    composeForm.oninput = () => isFormDirty = true;
}
if (composeBody) {
    composeBody.oninput = () => isFormDirty = true;
}

const autoSaveInterval = setInterval(() => {
    if (isFormDirty) {
        saveAsDraft(true); // silent auto-save
    }
}, 30000); // every 30 seconds

// Destroy interval on page transition
const mainContent = document.getElementById('main-content');
if (mainContent) {
    const observer = new MutationObserver((mutations, obs) => {
        if (!document.getElementById('compose-body')) {
            clearInterval(autoSaveInterval);
            obs.disconnect();
        }
    });
    observer.observe(mainContent, { childList: true });
}

function saveAsDraft(silent = false) {
    const draftIdInput = document.getElementById('draft-id');
    const threadIdInput = document.getElementById('thread-id');
    const cToInput = document.getElementById('c-to');
    const cCcInput = document.getElementById('c-cc');
    const cBccInput = document.getElementById('c-bcc');
    const cSubjectInput = document.getElementById('c-subject');
    const composeBodyDiv = document.getElementById('compose-body');

    if (!composeBodyDiv) return;

    const draftId = draftIdInput ? draftIdInput.value : '';
    const threadId = threadIdInput ? threadIdInput.value : '';
    const to = cToInput ? cToInput.value : '';
    const cc = cCcInput ? cCcInput.value : '';
    const bcc = cBccInput ? cBccInput.value : '';
    const subject = cSubjectInput ? cSubjectInput.value : '';
    const bodyHtml = composeBodyDiv.innerHTML;

    const data = {
        draft_id: draftId,
        thread_id: threadId,
        to: to,
        cc: cc,
        bcc: bcc,
        subject: subject,
        body_html: bodyHtml
    };

    fetch('/api/email/save-draft', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && document.getElementById('draft-id')) {
            document.getElementById('draft-id').value = res.draft_id;
            isFormDirty = false;
            if (!silent) {
                showToast('success', 'Draft saved.');
            }
        }
    });
}

// Send email
function sendEmail() {
    const form = document.getElementById('email-compose-form');
    const to = document.getElementById('c-to') ? document.getElementById('c-to').value : '';
    const cc = document.getElementById('c-cc') ? document.getElementById('c-cc').value : '';
    const bcc = document.getElementById('c-bcc') ? document.getElementById('c-bcc').value : '';
    const subject = document.getElementById('c-subject') ? document.getElementById('c-subject').value : '';
    const bodyHtml = document.getElementById('compose-body') ? document.getElementById('compose-body').innerHTML : '';
    const threadId = document.getElementById('thread-id') ? document.getElementById('thread-id').value : '';

    if (!to || !subject || !bodyHtml.trim()) {
        showToast('danger', 'Please complete the To, Subject, and Body fields.');
        return;
    }

    const formData = new FormData();
    formData.append('to', to);
    formData.append('cc', cc);
    formData.append('bcc', bcc);
    formData.append('subject', subject);
    formData.append('body_html', bodyHtml);
    if (threadId) {
        formData.append('thread_id', threadId);
    }

    // Add attachments files
    selectedFiles.forEach(f => {
        formData.append('attachments[]', f);
    });

    // Show sending state
    const sendBtn = document.querySelector('button[onclick="sendEmail()"]');
    const originalText = sendBtn ? sendBtn.innerHTML : 'Send Message';
    if (sendBtn) {
        sendBtn.disabled = true;
        sendBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span> Sending...`;
    }

    fetch('/api/email/send', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
    })
    .then(r => r.json().then(json => ({ status: r.status, body: json })))
    .then(res => {
        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = originalText;
        }

        if (res.status === 200) {
            showToast('success', res.body.message);
            isFormDirty = false;
            // Transition back to inbox
            loadEmailApp(null, 'inbox');
        } else {
            showToast('danger', res.body.error || 'Failed to transmit message.');
        }
    })
    .catch(err => {
        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.innerHTML = originalText;
        }
        showToast('danger', 'Network transmission error.');
    });
}

// Bind functions to window object for inline HTML event handlers (e.g. onclick) when executed inside an IIFE
window.execCmd = execCmd;
window.insertLink = insertLink;
window.handleFileSelect = handleFileSelect;
window.addFiles = addFiles;
window.removeFile = removeFile;
window.renderFileList = renderFileList;
window.applyTemplate = applyTemplate;
window.toggleSignature = toggleSignature;
window.saveAsDraft = saveAsDraft;
window.sendEmail = sendEmail;

// Init chips input elements
const contactsList = {!! json_encode($contacts ?? []) !!};
const canSendToAnyone = {!! json_encode($canSendToAnyone ?? false) !!};

function initChipsInput(inputId, allContacts, canSendToAnyone) {
    const originalInput = document.getElementById(inputId);
    if (!originalInput) return;

    // Prevent duplicate initialization
    if (document.getElementById(inputId + '-chips-container')) return;

    // Create container
    const container = document.createElement('div');
    container.className = 'email-chips-container form-control form-control-sm d-flex flex-wrap align-items-center gap-1 mb-2';
    container.id = inputId + '-chips-container';

    const chipsList = document.createElement('div');
    chipsList.className = 'email-chips-list d-flex flex-wrap gap-1 align-items-center';
    container.appendChild(chipsList);

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'email-chips-input flex-grow-1';
    input.style.border = 'none';
    input.style.outline = 'none';
    input.style.boxShadow = 'none';
    input.style.padding = '0';
    input.style.margin = '0';
    input.style.minWidth = '120px';
    input.style.fontSize = '0.85rem';
    input.placeholder = originalInput.placeholder || 'Type email...';
    container.appendChild(input);

    // Insert container right after the original input, and hide the original input
    originalInput.parentNode.insertBefore(container, originalInput);
    originalInput.type = 'hidden';

    // List of added emails
    let addedEmails = [];

    // Set up autocomplete dropdown wrapper
    const dropdownWrapper = document.createElement('div');
    dropdownWrapper.style.position = 'relative';
    dropdownWrapper.style.width = '100%';
    container.parentNode.appendChild(dropdownWrapper);

    const dropdown = document.createElement('div');
    dropdown.className = 'autocomplete-dropdown d-none';
    dropdownWrapper.appendChild(dropdown);

    // Synchronize original input value
    function updateOriginalInput() {
        originalInput.value = addedEmails.join(', ');
        // Trigger input event to flag form as dirty
        originalInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function addChip(email, name = '') {
        email = email.trim();
        if (!email) return;
        if (addedEmails.includes(email)) {
            input.value = '';
            return;
        }

        // Validate email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showToast('danger', `"${email}" is not a valid email address.`);
            input.value = '';
            return;
        }

        // Validate against address book if not allowed to send to anyone
        if (!canSendToAnyone) {
            const exists = allContacts.some(c => c.email.toLowerCase() === email.toLowerCase());
            if (!exists) {
                showToast('danger', `You can only send to contacts in your Address Book. Please add "${email}" to your Address Book first.`);
                input.value = '';
                return;
            }
        } else {
            // Auto-save contact for canSendToAnyone users if it's new
            const exists = allContacts.some(c => c.email.toLowerCase() === email.toLowerCase());
            if (!exists) {
                // Save to address book via background post
                const contactName = name || email.split('@')[0];
                fetch('/api/email/contacts/store', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        name: contactName,
                        email: email
                    })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        allContacts.push({ name: contactName, email: email });
                    }
                })
                .catch(() => {});
            }
        }

        addedEmails.push(email);

        const chip = document.createElement('div');
        chip.className = 'email-chip badge bg-light border text-dark d-inline-flex align-items-center gap-1 p-1 px-2 rounded-pill';
        const displayLabel = name ? `${name} <${email}>` : email;
        chip.innerHTML = `
            <span>${displayLabel}</span>
            <button type="button" class="btn-close ms-1" style="font-size:0.5rem;" onclick="event.stopPropagation();"></button>
        `;
        chip.querySelector('.btn-close').onclick = () => {
            addedEmails = addedEmails.filter(e => e !== email);
            chip.remove();
            updateOriginalInput();
        };

        chipsList.appendChild(chip);
        input.value = '';
        updateOriginalInput();
        hideDropdown();
    }

    function showDropdown(items) {
        dropdown.innerHTML = '';
        if (items.length === 0) {
            hideDropdown();
            return;
        }
        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'autocomplete-item';
            div.innerHTML = `<strong>${item.name}</strong> <span class="text-muted">(${item.email})</span>`;
            div.onclick = () => {
                addChip(item.email, item.name);
            };
            dropdown.appendChild(div);
        });
        dropdown.classList.remove('d-none');
    }

    function hideDropdown() {
        dropdown.classList.add('d-none');
    }

    // Input events
    input.oninput = () => {
        const query = input.value.trim().toLowerCase();
        if (!query) {
            hideDropdown();
            return;
        }
        const filtered = allContacts.filter(c => 
            (c.name.toLowerCase().includes(query) || c.email.toLowerCase().includes(query)) &&
            !addedEmails.includes(c.email)
        );
        showDropdown(filtered);
    };

    input.onkeydown = (e) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const val = input.value.replace(/,/g, '').trim();
            if (val) {
                addChip(val);
            }
        } else if (e.key === 'Backspace' && !input.value && addedEmails.length > 0) {
            // Remove last chip
            const lastEmail = addedEmails[addedEmails.length - 1];
            addedEmails.pop();
            const chips = chipsList.querySelectorAll('.email-chip');
            if (chips.length > 0) {
                chips[chips.length - 1].remove();
            }
            updateOriginalInput();
        }
    };

    // Close dropdown on click outside
    document.addEventListener('click', (e) => {
        if (!container.contains(e.target) && !dropdown.contains(e.target)) {
            hideDropdown();
        }
    });

    container.onclick = () => {
        input.focus();
    };

    // Parse initial value if present
    if (originalInput.value) {
        const initialEmails = originalInput.value.split(',').map(e => e.trim()).filter(Boolean);
        initialEmails.forEach(email => addChip(email));
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initChipsInput('c-to', contactsList, canSendToAnyone);
    initChipsInput('c-cc', contactsList, canSendToAnyone);
    initChipsInput('c-bcc', contactsList, canSendToAnyone);
});

if (document.readyState === 'complete' || document.readyState === 'interactive') {
    initChipsInput('c-to', contactsList, canSendToAnyone);
    initChipsInput('c-cc', contactsList, canSendToAnyone);
    initChipsInput('c-bcc', contactsList, canSendToAnyone);
}
