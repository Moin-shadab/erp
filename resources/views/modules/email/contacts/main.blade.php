<div class="container-fluid p-0">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h4 class="fw-bold mb-1"><i class="bi bi-journal-bookmark me-2 text-primary"></i>Address Book</h4>
            <p class="text-muted small mb-0">Manage and coordinate business contact profiles.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <button class="btn btn-primary btn-sm" onclick="openContactModal()">
                <i class="bi bi-person-plus me-1"></i> Add Contact
            </button>
        </div>
    </div>

    <!-- Contacts List Layout -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">All Contacts</h6>
            <div class="w-25">
                <input type="text" class="form-control form-control-sm" id="contacts-search" placeholder="Search contacts..." oninput="filterContacts()">
            </div>
        </div>
        <div class="table-responsive px-3 pb-3">
            <table class="table table-hover align-middle mb-0" id="contacts-table" style="font-size:0.85rem;">
                <thead>
                    <tr class="table-light">
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Company</th>
                        <th>Notes</th>
                        <th>Integrity Alerts</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="contacts-tbody">
                    @forelse($contacts as $c)
                    <tr class="contact-row" data-name="{{ strtolower($c->name) }}" data-email="{{ strtolower($c->email) }}" data-company="{{ strtolower($c->company) }}">
                        <td class="fw-semibold">{{ $c->name }}</td>
                        <td>{{ $c->email }}</td>
                        <td>{{ $c->phone ?: '-' }}</td>
                        <td>{{ $c->company ?: '-' }}</td>
                        <td class="text-muted" style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $c->notes ?: '-' }}</td>
                        <td>
                            <!-- Duplicate detection loaded dynamically by checking list in JS -->
                            <span class="badge bg-danger-subtle text-danger d-none duplicate-badge" data-email-match="{{ $c->email }}">
                                <i class="bi bi-exclamation-octagon-fill me-1"></i> Duplicate
                            </span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-xs btn-outline-primary py-0 px-2 me-1" style="font-size:0.75rem;" onclick="editContact({{ json_encode($c) }})"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-xs btn-outline-danger py-0 px-2" style="font-size:0.75rem;" onclick="deleteContact({{ $c->id }})"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No contact profiles created yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Contact Modal -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form id="contactForm" onsubmit="saveContact(event)">
                <input type="hidden" id="contact-id" name="id">
                <div class="modal-header border-bottom-0 py-3 px-4">
                    <h5 class="fw-bold mb-0" id="contactModalLabel">Add Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-0">
                    <div class="mb-3">
                        <label for="c-name" class="form-label small fw-bold text-muted mb-1">Full Name</label>
                        <input type="text" class="form-control form-control-sm" id="c-name" name="name" required placeholder="Enter contact name">
                    </div>
                    <div class="mb-3">
                        <label for="c-email" class="form-label small fw-bold text-muted mb-1">Email Address</label>
                        <input type="email" class="form-control form-control-sm" id="c-email" name="email" required placeholder="name@domain.com">
                    </div>
                    <div class="mb-3">
                        <label for="c-phone" class="form-label small fw-bold text-muted mb-1">Phone Number</label>
                        <input type="text" class="form-control form-control-sm" id="c-phone" name="phone" placeholder="e.g. +123456789">
                    </div>
                    <div class="mb-3">
                        <label for="c-company" class="form-label small fw-bold text-muted mb-1">Company</label>
                        <input type="text" class="form-control form-control-sm" id="c-company" name="company" placeholder="Business name">
                    </div>
                    <div class="mb-3">
                        <label for="c-notes" class="form-label small fw-bold text-muted mb-1">Notes</label>
                        <textarea class="form-control form-control-sm" id="c-notes" name="notes" rows="3" placeholder="Additional details"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 py-3 px-4">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-3">Save Contact</button>
                </div>
            </form>
        </div>
    </div>
</div>
