<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'mserp') }} - Enterprise ERP</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Typography: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Stylesheets: Bootstrap 5, Bootstrap Icons, AG Grid -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/ag-grid-community@30.2.1/styles/ag-grid.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/ag-grid-community@30.2.1/styles/ag-theme-alpine.min.css" rel="stylesheet">
    
    <!-- Quill WYSIWYG Editor styling and library -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

    <!-- Premium Custom Light Theme styling -->
    <style>
        :root {
            --erp-font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --erp-bg-body: #f8fafc;
            --erp-bg-card: #ffffff;
            --erp-primary: #2563eb;
            --erp-primary-hover: #1d4ed8;
            --erp-primary-light: #eff6ff;
            --erp-text-main: #0f172a;
            --erp-text-muted: #64748b;
            --erp-border: #e2e8f0;
            --erp-sidebar-width: 260px;
            --erp-sidebar-collapsed-width: 70px;
            --erp-transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: var(--erp-font-family);
            background-color: var(--erp-bg-body);
            color: var(--erp-text-main);
            overflow-x: hidden;
            font-size: 0.9rem;
        }

        /* Scrollbars */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Main layout wrapper */
        #wrapper {
            display: flex;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
        }

        /* Sidebar styling */
        #sidebar {
            width: var(--erp-sidebar-width);
            background-color: #ffffff;
            border-right: 1px solid var(--erp-border);
            display: flex;
            flex-direction: column;
            transition: var(--erp-transition);
            z-index: 1000;
        }

        #sidebar.collapsed {
            width: var(--erp-sidebar-collapsed-width);
        }

        .sidebar-brand {
            height: 60px;
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            border-bottom: 1px solid var(--erp-border);
        }

        .sidebar-brand span {
            font-weight: 800;
            font-size: 1.2rem;
            color: var(--erp-primary);
            letter-spacing: -0.5px;
            transition: var(--erp-transition);
        }

        #sidebar.collapsed .sidebar-brand span {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 0.75rem;
        }

        .menu-header {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--erp-text-muted);
            letter-spacing: 0.5px;
            margin: 1rem 0 0.5rem 0.5rem;
            transition: var(--erp-transition);
        }

        #sidebar.collapsed .menu-header {
            opacity: 0;
            font-size: 0;
            margin: 0;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.65rem 0.75rem;
            color: var(--erp-text-main);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.2rem;
            font-weight: 500;
            transition: var(--erp-transition);
        }

        .menu-item:hover {
            background-color: var(--erp-primary-light);
            color: var(--erp-primary);
        }

        .menu-item.active {
            background-color: var(--erp-primary);
            color: #ffffff;
        }

        .menu-item i {
            font-size: 1.15rem;
            margin-right: 0.75rem;
            transition: var(--erp-transition);
        }

        #sidebar.collapsed .menu-item i {
            margin-right: 0;
        }

        .menu-item span {
            transition: var(--erp-transition);
        }

        #sidebar.collapsed .menu-item span {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        /* Content container */
        #content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* Topbar styling */
        #topbar {
            height: 60px;
            background-color: #ffffff;
            border-bottom: 1px solid var(--erp-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
        }

        /* Switches & elements inside topbar */
        .topbar-switches select {
            border: 1px solid var(--erp-border);
            background-color: #ffffff;
            font-size: 0.8rem;
            padding: 0.3rem 0.75rem;
            border-radius: 6px;
            font-weight: 600;
            color: var(--erp-text-main);
            margin-right: 0.5rem;
        }

        .topbar-switches select:focus {
            outline: none;
            border-color: var(--erp-primary);
        }

        .global-search {
            position: relative;
            width: 250px;
            margin-right: 1rem;
        }

        .global-search input {
            padding: 0.35rem 0.75rem 0.35rem 2rem;
            border-radius: 20px;
            border: 1px solid var(--erp-border);
            font-size: 0.8rem;
            width: 100%;
        }

        .global-search i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--erp-text-muted);
            font-size: 0.8rem;
        }

        /* Main dynamic content frame */
        #main-content {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            background-color: var(--erp-bg-body);
        }

        /* Cards styles */
        .card {
            border: 1px solid var(--erp-border);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02), 0 1px 2px rgba(0,0,0,0.01);
            background-color: var(--erp-bg-card);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: transparent;
            border-bottom: 1px solid var(--erp-border);
            padding: 1rem 1.25rem;
            font-weight: 600;
        }

        /* Custom alert styles */
        .alert {
            border-radius: 8px;
            border: none;
        }

        /* Theme-aligned custom AG Grid overrides */
        .ag-theme-alpine {
            --ag-font-family: var(--erp-font-family);
            --ag-font-size: 0.85rem;
            --ag-header-background-color: #f8fafc;
            --ag-header-foreground-color: #475569;
            --ag-border-color: #e2e8f0;
            --ag-row-hover-color: #f1f5f9;
            --ag-selected-row-background-color: #eff6ff;
            --ag-input-focus-border-color: #2563eb;
            --ag-border-radius: 8px;
        }
    </style>
</head>
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-brand">
                <i class="bi bi-cpu text-primary me-2 fs-4"></i>
                <span>MS ERP</span>
            </div>
            
            <div class="sidebar-menu">
                <a href="#" class="menu-item active" onclick="loadDashboard(event)">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>

                <!-- Dynamic Modules & Pages will be seeded and loaded here -->
                <div id="dynamic-nav">
                    <!-- Loaded via JS dynamically -->
                </div>
            </div>
        </nav>

        <!-- Content Area -->
        <div id="content-wrapper">
            <!-- Topbar -->
            <header id="topbar">
                <!-- Left: sidebar toggle & context switches -->
                <div class="d-flex align-items-center">
                    <button class="btn btn-sm btn-light border me-3" id="sidebar-toggle" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i>
                    </button>

                    <!-- Context Switchers -->
                    <div class="topbar-switches d-none d-md-flex align-items-center">
                        <select id="company-switcher" onchange="switchContext()">
                            <!-- Seeded dynamic list -->
                        </select>
                        <select id="branch-switcher" onchange="switchContext()">
                            <!-- Seeded dynamic list -->
                        </select>
                        <select id="department-switcher" onchange="switchContext()">
                            <!-- Seeded dynamic list -->
                        </select>
                    </div>
                </div>

                <!-- Right: search, notifications, account switcher, profile -->
                <div class="d-flex align-items-center">
                    <!-- Global Search -->
                    <div class="global-search d-none d-lg-block">
                        <i class="bi bi-search"></i>
                        <input type="text" id="global-search-input" placeholder="Search customer, invoice, item..." onkeypress="handleGlobalSearch(event)">
                    </div>

                    <!-- Email Account Switcher -->
                    <div class="me-3">
                        <select class="form-select form-select-sm border" id="email-account-switcher" style="font-size:0.8rem; font-weight:600;" onchange="switchEmailAccount()">
                            <!-- Populated dynamically -->
                        </select>
                    </div>

                    <!-- Sync Button -->
                    <button class="btn btn-sm btn-light border me-3" id="sync-email-btn" onclick="syncEmail()" title="Sync Email Account">
                        <i class="bi bi-arrow-repeat" id="sync-icon"></i>
                        <span class="d-none d-sm-inline ms-1" style="font-size:0.8rem; font-weight:600;">Sync</span>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div class="dropdown me-3">
                        <button class="btn btn-sm btn-light border position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notification-badge">
                                0
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border p-0" style="width: 320px; font-size: 0.85rem;" id="notification-dropdown">
                            <li class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light">
                                <span class="fw-bold">Notifications</span>
                                <a href="#" class="text-primary text-decoration-none fw-bold" onclick="markAllNotificationsRead(event)">Clear all</a>
                            </li>
                            <div id="notification-items-container" style="max-height: 250px; overflow-y: auto;">
                                <!-- Populated dynamically -->
                            </div>
                        </ul>
                    </div>

                    <!-- User Profile Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light border dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle text-primary me-1 fs-6"></i>
                            <span class="fw-semibold" id="topbar-username">Loading...</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border">
                            <li><h6 class="dropdown-header" id="topbar-role">Role</h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item d-flex align-items-center" href="#" onclick="showProfile()"><i class="bi bi-gear me-2"></i> Settings</a></li>
                            <li><a class="dropdown-item d-flex align-items-center text-danger" href="/logout"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Main dynamic workspace container -->
            <main id="main-content">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Global Javascript Engine -->
    <script>
        // Global variables representing current user context
        let currentUserId = null;
        let currentUserRole = '';
        let currentCompanyId = null;
        let currentBranchId = null;
        let currentDepartmentId = null;
        let currentEmailAccountId = null;

        // Toggle Sidebar collapsed state
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }

        // Initialize Context lists and user states
        document.addEventListener('DOMContentLoaded', function () {
            fetchInitialContext();
            setupAutoNotificationPoll();
        });

        function fetchInitialContext() {
            fetch('/api/user/context')
                .then(r => r.json())
                .then(data => {
                    currentUserId = data.user.id;
                    currentUserRole = data.user.role_slug;
                    document.getElementById('topbar-username').textContent = data.user.name;
                    document.getElementById('topbar-role').textContent = data.user.role_name + ' (' + data.user.company_code + ')';

                    // Populate context switches
                    populateSelect('company-switcher', data.companies, data.user.company_id);
                    populateSelect('branch-switcher', data.branches, data.user.branch_id);
                    populateSelect('department-switcher', data.departments, data.user.department_id);
                    populateSelect('email-account-switcher', data.email_accounts, data.active_email_account_id);
                    
                    currentCompanyId = data.user.company_id;
                    currentBranchId = data.user.branch_id;
                    currentDepartmentId = data.user.department_id;
                    currentEmailAccountId = data.active_email_account_id;

                    // Load dynamic navigation sidebar items
                    renderDynamicNavigation(data.modules);

                    // Load Dashboard page as default startup screen
                    loadDashboard();
                });
        }

        function populateSelect(id, list, selectedId) {
            const select = document.getElementById(id);
            select.innerHTML = '';
            list.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.name;
                if (item.id == selectedId) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            });
        }

        function switchContext() {
            const comp = document.getElementById('company-switcher').value;
            const br = document.getElementById('branch-switcher').value;
            const dep = document.getElementById('department-switcher').value;
            
            fetch('/api/user/switch-context', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ company_id: comp, branch_id: br, department_id: dep })
            })
            .then(r => r.json())
            .then(data => {
                currentCompanyId = comp;
                currentBranchId = br;
                currentDepartmentId = dep;
                
                // Refresh dashboard or current page
                loadDashboard();
            });
        }

        function switchEmailAccount() {
            const accId = document.getElementById('email-account-switcher').value;
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
                currentEmailAccountId = accId;
                // If currently viewing inbox/compose/contacts, refresh the view
                const activeMenu = document.querySelector('.menu-item.active');
                if (activeMenu && activeMenu.textContent.includes('Email')) {
                    loadEmailApp(null, 'inbox');
                }
            });
        }

        // Render dynamic modules and pages in the navigation panel
        function renderDynamicNavigation(modules) {
            const nav = document.getElementById('dynamic-nav');
            nav.innerHTML = '';

            modules.forEach(mod => {
                const header = document.createElement('div');
                header.className = 'menu-header';
                header.textContent = mod.name;
                nav.appendChild(header);

                mod.pages.forEach(p => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'menu-item';
                    item.innerHTML = `<i class="bi ${p.icon || 'bi-layout-text-window-reverse'}"></i> <span>${p.name}</span>`;
                    item.onclick = function(e) {
                        e.preventDefault();
                        setActiveMenuItem(item);
                        loadPage('/erp/' + p.slug);
                    };
                    nav.appendChild(item);
                });
            });
        }

        function setActiveMenuItem(item) {
            document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
            if (item) {
                item.classList.add('active');
            }
        }

        // AJAX Page Loader Engine
        function loadPage(url) {
            const main = document.getElementById('main-content');
            main.innerHTML = `
                <div class="d-flex justify-content-center align-items-center" style="min-height: 400px;">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 403) {
                        return '<div class="alert alert-danger m-4"><h5>Access Denied</h5><p>You do not have the required permissions to access this page.</p></div>';
                    }
                    throw new Error('HTTP ' + response.status + ' - ' + response.statusText);
                }
                return response.text();
            })
            .then(html => {
                main.innerHTML = html;
                executeInlineScripts(main);
            })
            .catch(error => {
                main.innerHTML = `
                    <div class="alert alert-danger m-4">
                        <h5>Failed to load page</h5>
                        <p>${error.message}</p>
                    </div>
                `;
            });
        }

        function executeInlineScripts(container) {
            const scripts = container.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                document.body.appendChild(newScript);
                newScript.parentNode.removeChild(newScript);
            });
        }

        // Base navigation links helpers
        function loadDashboard(e) {
            if (e) e.preventDefault();
            setActiveMenuItem(document.querySelector('a[onclick="loadDashboard(event)"]'));
            loadPage('/dashboard');
        }

        function navigateToSlug(slug) {
            document.querySelectorAll('.menu-item').forEach(el => {
                if (el.getAttribute('onclick') && el.getAttribute('onclick').includes(slug)) {
                    setActiveMenuItem(el);
                } else if (el.onclick && el.onclick.toString().includes(slug)) {
                    setActiveMenuItem(el);
                }
            });
            loadPage('/erp/' + slug);
        }

        function loadEmailApp(e, route) {
            if (e) e.preventDefault();
            
            const item = Array.from(document.querySelectorAll('#dynamic-nav .menu-item')).find(el => el.onclick && el.onclick.toString().includes('email-' + route));
            if (item) {
                setActiveMenuItem(item);
            }
            
            loadPage('/erp/email-' + route);
        }

        function loadReportBuilder(e) {
            if (e) e.preventDefault();
            const item = Array.from(document.querySelectorAll('#dynamic-nav .menu-item')).find(el => el.onclick && el.onclick.toString().includes('report-builder'));
            if (item) {
                setActiveMenuItem(item);
            }
            loadPage('/erp/report-builder');
        }

        // Email Synchronization triggered from topbar
        function syncEmail() {
            const icon = document.getElementById('sync-icon');
            const btn = document.getElementById('sync-email-btn');
            
            icon.classList.add('spin-animation');
            btn.disabled = true;

            fetch('/api/email/sync/' + currentEmailAccountId, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(r => r.json())
            .then(data => {
                icon.classList.remove('spin-animation');
                btn.disabled = false;
                
                // Show notification toast
                showToast(data.success ? 'success' : 'danger', data.message);

                // Reload active inbox view if open
                const activeMenu = document.querySelector('.menu-item.active');
                if (activeMenu && activeMenu.textContent.includes('Email')) {
                    loadEmailApp(null, 'inbox');
                }
            })
            .catch(err => {
                icon.classList.remove('spin-animation');
                btn.disabled = false;
                showToast('danger', 'Error connecting to email servers.');
            });
        }

        // Notification polling & display
        function setupAutoNotificationPoll() {
            pollNotifications();
            setInterval(pollNotifications, 10000); // every 10s
        }

        function pollNotifications() {
            fetch('/api/notifications')
                .then(r => r.json())
                .then(data => {
                    // Check for active pending broadcast
                    if (data.pending_broadcast) {
                        showBroadcastModal(data.pending_broadcast);
                    }

                    const badge = document.getElementById('notification-badge');
                    const unreadCount = data.unread_count;
                    
                    if (unreadCount > 0) {
                        badge.textContent = unreadCount;
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }

                    const container = document.getElementById('notification-items-container');
                    container.innerHTML = '';
                    
                    if (data.items.length === 0) {
                        container.innerHTML = '<li class="p-3 text-center text-muted">No notifications</li>';
                        return;
                    }

                    data.items.forEach(item => {
                        const li = document.createElement('li');
                        li.className = `p-3 border-bottom ${item.is_read ? '' : 'bg-light'} dropdown-item-text`;
                        li.style.cursor = 'pointer';
                        li.innerHTML = `
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="fw-bold">${item.title}</span>
                                <small class="text-muted">${item.time_ago}</small>
                            </div>
                            <div class="text-muted mt-1" style="font-size: 0.8rem;">${item.message}</div>
                        `;
                        li.onclick = () => markNotificationRead(item.id);
                        container.appendChild(li);
                    });
                });
        }

        function markNotificationRead(id) {
            fetch('/api/notifications/read/' + id, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            }).then(() => pollNotifications());
        }

        function markAllNotificationsRead(e) {
            if (e) e.preventDefault();
            fetch('/api/notifications/read-all', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            }).then(() => pollNotifications());
        }

        // Global search input handling
        function handleGlobalSearch(e) {
            if (e.key === 'Enter') {
                const term = e.target.value.trim();
                if (term) {
                    loadPage('/erp/search?q=' + encodeURIComponent(term));
                }
            }
        }

        // Custom alerts via dynamic Bootstrap Toasts
        function showToast(type, message) {
            const toastContainer = document.createElement('div');
            toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
            toastContainer.style.zIndex = '1100';
            toastContainer.innerHTML = `
                <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            document.body.appendChild(toastContainer);
            const toastEl = toastContainer.querySelector('.toast');
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
            toastEl.addEventListener('hidden.bs.toast', () => {
                toastContainer.remove();
            });
        }

        // Custom Spin Animation for Refresh Switch
        const style = document.createElement('style');
        style.type = 'text/css';
        style.innerHTML = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .spin-animation {
                animation: spin 1s linear infinite;
            }
        `;
        document.getElementsByTagName('head')[0].appendChild(style);

        // Account Profile & Broadcast Scripts
        let settingsModal = null;
        function showProfile() {
            if (!settingsModal) {
                settingsModal = new bootstrap.Modal(document.getElementById('settingsModal'));
            }
            document.getElementById('settings-password-form').reset();
            settingsModal.show();
        }
        window.showProfile = showProfile;

        function submitChangeProfilePassword(e) {
            e.preventDefault();
            const currentPass = document.getElementById('settings-current-password').value;
            const newPass = document.getElementById('settings-new-password').value;
            const confirmPass = document.getElementById('settings-new-password-confirm').value;

            if (newPass !== confirmPass) {
                showToast('danger', 'New password and confirmation do not match.');
                return;
            }

            fetch('/api/profile/change-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    current_password: currentPass,
                    new_password: newPass,
                    new_password_confirmation: confirmPass
                })
            })
            .then(r => r.json().then(json => ({ status: r.status, body: json })))
            .then(res => {
                if (res.status === 200) {
                    showToast('success', res.body.message);
                    settingsModal.hide();
                } else {
                    showToast('danger', res.body.error || 'Failed to update password.');
                }
            })
            .catch(err => {
                showToast('danger', 'Network error changing password.');
            });
        }
        window.submitChangeProfilePassword = submitChangeProfilePassword;

        let activeBroadcastId = null;
        let broadcastModal = null;
        
        function showBroadcastModal(broadcast) {
            activeBroadcastId = broadcast.id;
            document.getElementById('broadcast-title').textContent = broadcast.title;
            document.getElementById('broadcast-message').textContent = broadcast.message;
            if (!broadcastModal) {
                broadcastModal = new bootstrap.Modal(document.getElementById('broadcastModal'));
            }
            broadcastModal.show();
        }
        window.showBroadcastModal = showBroadcastModal;
        
        function acknowledgeBroadcast() {
            if (!activeBroadcastId) return;
            const btn = document.getElementById('btn-ack-broadcast');
            btn.disabled = true;
            btn.textContent = 'Processing...';

            fetch('/api/notifications/broadcast/acknowledge/' + activeBroadcastId, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = 'I Acknowledge';
                if (data.success) {
                    if (broadcastModal) {
                        broadcastModal.hide();
                    }
                    showToast('success', 'Acknowledged system alert.');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = 'I Acknowledge';
                showToast('danger', 'Network error.');
            });
        }
        window.acknowledgeBroadcast = acknowledgeBroadcast;
    </script>

    <!-- User Settings Modal (Password Change) -->
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form id="settings-password-form" onsubmit="submitChangeProfilePassword(event)">
                    <div class="modal-header border-bottom-0 py-3 px-4">
                        <h5 class="fw-bold mb-0" id="settingsModalLabel"><i class="bi bi-gear text-primary me-2"></i>Account Settings</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body px-4 py-0">
                        <div class="mb-3">
                            <label for="settings-current-password" class="form-label small fw-bold text-muted mb-1">Current Password</label>
                            <input type="password" class="form-control form-control-sm" id="settings-current-password" required placeholder="Enter current password">
                        </div>
                        <div class="mb-3">
                            <label for="settings-new-password" class="form-label small fw-bold text-muted mb-1">New Password</label>
                            <input type="password" class="form-control form-control-sm" id="settings-new-password" required placeholder="Min 4 characters">
                        </div>
                        <div class="mb-3">
                            <label for="settings-new-password-confirm" class="form-label small fw-bold text-muted mb-1">Confirm New Password</label>
                            <input type="password" class="form-control form-control-sm" id="settings-new-password-confirm" required placeholder="Retype new password">
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 py-3 px-4">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary px-3">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Live Broadcast Alert Modal -->
    <div class="modal fade" id="broadcastModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="broadcastModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg bg-danger text-white">
                <div class="modal-header border-bottom-0 py-3 px-4 text-white">
                    <h5 class="fw-bold mb-0" id="broadcastModalLabel"><i class="bi bi-exclamation-triangle-fill me-2 spin-animation d-inline-block"></i>SYSTEM BROADCAST</h5>
                </div>
                <div class="modal-body px-4 py-0 text-white">
                    <h4 class="fw-bold mb-2" id="broadcast-title">Title</h4>
                    <p id="broadcast-message" style="font-size:0.95rem; line-height:1.6; white-space: pre-wrap;">Message</p>
                </div>
                <div class="modal-footer border-top-0 py-3 px-4">
                    <button type="button" class="btn btn-sm btn-light text-danger fw-bold px-4" id="btn-ack-broadcast" onclick="acknowledgeBroadcast()">I Acknowledge</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
