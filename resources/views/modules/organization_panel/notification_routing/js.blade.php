<script>
(function() {
    // Local memory map of all users for quick client-side name translations
    const userMap = {
        @foreach($allUsers as $au)
            "{{ $au->id }}": "{{ addslashes($au->name) }}",
        @endforeach
    };

    let activeUserId = null;
    let activeRoutes = { incoming: [], outgoing: [] };

    window.filterStaff = function() {
        const query = document.getElementById('staff-search').value.toLowerCase();
        const cards = document.querySelectorAll('.staff-card');
        
        cards.forEach(card => {
            const name = card.querySelector('.name-field').textContent.toLowerCase();
            if (name.includes(query)) {
                card.classList.remove('d-none');
            } else {
                card.classList.add('d-none');
            }
        });
    };

    window.selectActiveUser = function(userId, name, role, dept) {
        activeUserId = userId;
        
        // Update active highlight classes
        document.querySelectorAll('.staff-card').forEach(card => {
            card.classList.remove('active-selected');
        });
        const activeCard = document.getElementById('staff-card-' + userId);
        if (activeCard) {
            activeCard.classList.add('active-selected');
        }

        // Show config panel and hide blank state
        document.getElementById('routing-blank-state').classList.add('d-none');
        document.getElementById('routing-config-panel').classList.remove('d-none');

        // Update active user meta header
        document.getElementById('active-user-name').textContent = name;
        document.getElementById('active-user-role').textContent = role;
        document.getElementById('active-user-dept').textContent = dept;
        
        // Update name references inside descriptions
        document.querySelectorAll('.active-user-name-text').forEach(el => {
            el.textContent = name;
        });

        // Set avatar letters
        const initials = name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        document.getElementById('active-user-avatar').textContent = initials;

        // Reset copy dropdown select (exclude active user)
        const select = document.getElementById('copy-source-user');
        select.value = '';
        Array.from(select.options).forEach(opt => {
            if (opt.value === userId) {
                opt.disabled = true;
            } else {
                opt.disabled = false;
            }
        });

        // Load rules
        fetchUserRoutes(userId);
    };

    function fetchUserRoutes(userId) {
        fetch('/api/notification-routes/' + userId)
            .then(r => r.json())
            .then(data => {
                activeRoutes = data;
                renderRoutesList();
            })
            .catch(() => {
                showToast('danger', 'Failed to retrieve notification routing rules.');
            });
    }

    function renderRoutesList() {
        // Render Senders list
        const incomingContainer = document.getElementById('incoming-list-container');
        incomingContainer.innerHTML = '';
        if (activeRoutes.incoming.length === 0) {
            incomingContainer.innerHTML = `<div class="text-center py-4 text-muted small" id="incoming-empty-state">No incoming routes defined.</div>`;
        } else {
            activeRoutes.incoming.forEach(senderId => {
                const senderName = userMap[senderId] || 'User #' + senderId;
                const div = document.createElement('div');
                div.className = 'd-flex justify-content-between align-items-center bg-white border p-2 mb-2 routing-item shadow-sm';
                div.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-person-fill text-muted me-2"></i>
                        <span class="fw-bold small text-dark">${senderName}</span>
                    </div>
                    <button class="btn btn-sm btn-link text-danger p-0 border-0" onclick="updateRouteRule(${senderId}, 'incoming', false)">
                        <i class="bi bi-trash"></i>
                    </button>
                `;
                incomingContainer.appendChild(div);
            });
        }

        // Render Receivers list
        const outgoingContainer = document.getElementById('outgoing-list-container');
        outgoingContainer.innerHTML = '';
        if (activeRoutes.outgoing.length === 0) {
            outgoingContainer.innerHTML = `<div class="text-center py-4 text-muted small" id="outgoing-empty-state">No outgoing routes defined.</div>`;
        } else {
            activeRoutes.outgoing.forEach(receiverId => {
                const receiverName = userMap[receiverId] || 'User #' + receiverId;
                const div = document.createElement('div');
                div.className = 'd-flex justify-content-between align-items-center bg-white border p-2 mb-2 routing-item shadow-sm';
                div.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-person-fill text-muted me-2"></i>
                        <span class="fw-bold small text-dark">${receiverName}</span>
                    </div>
                    <button class="btn btn-sm btn-link text-danger p-0 border-0" onclick="updateRouteRule(${receiverId}, 'outgoing', false)">
                        <i class="bi bi-trash"></i>
                    </button>
                `;
                outgoingContainer.appendChild(div);
            });
        }
    }

    window.updateRouteRule = function(targetUserId, direction, enable) {
        if (!activeUserId) return;

        fetch('/api/notification-routes/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                user_id: activeUserId,
                target_user_id: targetUserId,
                direction: direction,
                value: enable
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Notification routing rules updated.');
                fetchUserRoutes(activeUserId);
            } else {
                showToast('danger', 'Failed to update routing rules.');
            }
        })
        .catch(() => {
            showToast('danger', 'Network error.');
        });
    };

    window.copyRoutingRules = function() {
        const sourceUserId = document.getElementById('copy-source-user').value;
        if (!sourceUserId) {
            showToast('warning', 'Please select a source user first.');
            return;
        }
        if (!activeUserId) return;

        fetch('/api/notification-routes/copy', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                source_user_id: sourceUserId,
                target_user_id: activeUserId
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Notification routing rules copied successfully.');
                fetchUserRoutes(activeUserId);
            } else {
                showToast('danger', data.error || 'Failed to copy rules.');
            }
        })
        .catch(() => {
            showToast('danger', 'Network error.');
        });
    };

    window.resetAllRules = function() {
        activeUserId = null;
        document.querySelectorAll('.staff-card').forEach(card => {
            card.classList.remove('active-selected');
        });
        document.getElementById('routing-config-panel').classList.add('d-none');
        document.getElementById('routing-blank-state').classList.remove('d-none');
    };

    // HTML5 Drag and Drop handlers
    window.handleDragStart = function(event, userId, userName) {
        event.dataTransfer.setData('text/plain', userId);
        event.dataTransfer.effectAllowed = 'copy';
    };

    window.handleDragOver = function(event, elementId) {
        event.preventDefault();
        const el = document.getElementById(elementId);
        if (el) {
            el.classList.add('dragover');
        }
    };

    window.handleDragLeave = function(event, elementId) {
        const el = document.getElementById(elementId);
        if (el) {
            el.classList.remove('dragover');
        }
    };

    window.handleDrop = function(event, direction) {
        event.preventDefault();
        
        // Remove dragover indicators
        document.querySelectorAll('.routing-dropzone').forEach(dz => {
            dz.classList.remove('dragover');
        });

        const draggedUserId = parseInt(event.dataTransfer.getData('text/plain'));
        if (!draggedUserId) return;
        
        if (!activeUserId) {
            showToast('warning', 'Please select an active user to configure routing rules first.');
            return;
        }

        if (draggedUserId === parseInt(activeUserId)) {
            showToast('warning', 'Cannot route notifications to the same user.');
            return;
        }

        // Check if rule already exists to avoid redundant calls
        const targetList = (direction === 'incoming') ? activeRoutes.incoming : activeRoutes.outgoing;
        if (targetList.includes(draggedUserId)) {
            showToast('info', 'This routing connection already exists.');
            return;
        }

        updateRouteRule(draggedUserId, direction, true);
    };
})();
</script>
