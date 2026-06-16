// Run duplicate detection scan immediately
detectDuplicates();

// Run duplicate detection scan on emails list
function detectDuplicates() {
    const emails = [];
    const duplicates = [];
    
    document.querySelectorAll('.contact-row').forEach(row => {
        const email = row.getAttribute('data-email');
        if (emails.includes(email)) {
            duplicates.push(email);
        } else {
            emails.push(email);
        }
    });

    // Show duplicate badges on rows matching emails in duplicates list
    document.querySelectorAll('.duplicate-badge').forEach(badge => {
        const email = badge.getAttribute('data-email-match');
        if (duplicates.includes(email)) {
            badge.classList.remove('d-none');
        } else {
            badge.classList.add('d-none');
        }
    });
}

// Filter list
function filterContacts() {
    const term = document.getElementById('contacts-search').value.toLowerCase();
    document.querySelectorAll('.contact-row').forEach(row => {
        const name = row.getAttribute('data-name');
        const email = row.getAttribute('data-email');
        const comp = row.getAttribute('data-company');

        if (name.includes(term) || email.includes(term) || comp.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

const cModal = new bootstrap.Modal(document.getElementById('contactModal'));
const contactForm = document.getElementById('contactForm');

function openContactModal() {
    document.getElementById('contactModalLabel').textContent = 'Add New Contact';
    document.getElementById('contact-id').value = '';
    contactForm.reset();
    cModal.show();
}

function editContact(contact) {
    document.getElementById('contactModalLabel').textContent = 'Modify Contact';
    document.getElementById('contact-id').value = contact.id;
    document.getElementById('c-name').value = contact.name;
    document.getElementById('c-email').value = contact.email;
    document.getElementById('c-phone').value = contact.phone || '';
    document.getElementById('c-company').value = contact.company || '';
    document.getElementById('c-notes').value = contact.notes || '';
    cModal.show();
}

function saveContact(e) {
    e.preventDefault();

    const formData = new FormData(contactForm);
    const data = Object.fromEntries(formData.entries());

    fetch('/api/email/contacts/store', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            showToast('success', 'Contact profile saved successfully.');
            cModal.hide();
            loadEmailApp(null, 'contacts'); // reload view
        } else {
            showToast('danger', 'Failed to save contact.');
        }
    });
}

function deleteContact(id) {
    if (!confirm('Are you sure you want to delete this contact?')) return;

    fetch('/api/email/contacts/delete/' + id, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Contact profile deleted.');
            loadEmailApp(null, 'contacts'); // reload view
        }
    });
}

// Bind functions to window object for inline HTML event handlers (e.g. onclick) when executed inside an IIFE
window.detectDuplicates = detectDuplicates;
window.filterContacts = filterContacts;
window.openContactModal = openContactModal;
window.editContact = editContact;
window.saveContact = saveContact;
window.deleteContact = deleteContact;
