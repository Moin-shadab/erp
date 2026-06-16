{{-- Internal Chat Custom CSS --}}
<style>
    :root {
        --slack-aubergine: #1e1b4b; /* Deep Indigo Dark Sidebar */
        --slack-aubergine-hover: #312e81; /* Medium Indigo Hover */
        --slack-sidebar-text: #cbd5e1; /* Light Slate text for contrast */
        --slack-sidebar-active: #4f46e5; /* Electric Indigo Active */
        --slack-border: #cbd5e1;
        --slack-active-green: #22c55e; /* Vibrant Green */
        
        /* High Contrast Main Theme colors */
        --chat-bg-main: #f1f5f9; /* Slate Light Gray Main Chat background */
        --chat-bg-bubble: #ffffff; /* White Message Bubbles */
        --chat-text-main: #0f172a; /* Very Dark Slate Main text */
        --chat-text-muted: #475569; /* Medium Slate Muted text */
        --chat-text-light: #64748b; /* Muted Slate info text */
    }

    .chat-container {
        display: flex;
        height: calc(100vh - 130px);
        background-color: var(--chat-bg-main) !important;
        color: var(--chat-text-main) !important;
        border: 1px solid var(--slack-border) !important;
        border-radius: 12px;
        overflow: hidden;
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    /* Left Sidebar */
    .chat-sidebar {
        width: 260px;
        background-color: var(--slack-aubergine) !important;
        color: #ffffff !important;
        display: flex;
        flex-direction: column;
        border-right: 1px solid var(--slack-aubergine-hover) !important;
        flex-shrink: 0;
    }

    .chat-sidebar-header {
        padding: 16px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .chat-sidebar-header h5 {
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0;
        color: #ffffff !important;
        letter-spacing: -0.3px;
    }

    .chat-sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 12px 0;
    }

    .sidebar-section {
        margin-bottom: 20px;
    }

    .sidebar-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 4px 20px;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--slack-sidebar-text) !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .sidebar-section-header .add-btn {
        background: none;
        border: none;
        color: var(--slack-sidebar-text) !important;
        cursor: pointer;
        padding: 0;
        font-size: 0.95rem;
        transition: color 0.15s;
    }

    .sidebar-section-header .add-btn:hover {
        color: #ffffff !important;
    }

    .sidebar-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-item-link {
        display: flex;
        align-items: center;
        padding: 8px 20px;
        color: var(--slack-sidebar-text) !important;
        text-decoration: none;
        font-size: 0.88rem;
        transition: background 0.15s, color 0.15s;
        cursor: pointer;
        border-radius: 0;
    }

    .sidebar-item-link:hover {
        background-color: var(--slack-aubergine-hover) !important;
        color: #ffffff !important;
    }

    .sidebar-item-link.active {
        background-color: var(--slack-sidebar-active) !important;
        color: #ffffff !important;
    }

    .sidebar-item-icon {
        margin-right: 8px;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        color: inherit !important;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        background-color: transparent;
        border: 1.5px solid var(--slack-sidebar-text) !important;
        border-radius: 50%;
        margin-right: 8px;
        display: inline-block;
    }

    .status-dot.online {
        background-color: var(--slack-active-green) !important;
        border-color: var(--slack-active-green) !important;
    }

    /* Main Chat Stream */
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background-color: var(--chat-bg-main) !important;
        min-width: 0;
        position: relative;
    }

    .chat-header {
        height: 60px;
        background-color: #ffffff !important;
        border-bottom: 1px solid var(--slack-border) !important;
        padding: 10px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important;
    }

    .chat-header-info h6 {
        margin: 0;
        font-size: 0.98rem;
        font-weight: 700;
        color: var(--chat-text-main) !important;
    }

    .chat-header-info span {
        font-size: 0.75rem;
        color: var(--chat-text-light) !important;
    }

    .chat-messages-scroll {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        background-color: var(--chat-bg-main) !important;
    }

    /* Message Bubble styling - modern contrast cards */
    .message-item {
        display: flex;
        position: relative;
        padding: 12px 16px;
        background-color: var(--chat-bg-bubble) !important;
        border: 1px solid rgba(226, 232, 240, 0.8) !important;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
        transition: transform 0.15s, box-shadow 0.15s;
    }

    .message-item:hover {
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03) !important;
        transform: translateY(-1px);
    }

    .message-avatar {
        width: 36px;
        height: 36px;
        border-radius: 4px;
        background-color: #cbd5e1;
        color: #ffffff !important;
        font-weight: 700;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        flex-shrink: 0;
        text-transform: uppercase;
    }

    .message-content-wrapper {
        flex: 1;
        min-width: 0;
    }

    .message-meta {
        display: flex;
        align-items: baseline;
        margin-bottom: 4px;
        gap: 8px;
    }

    .message-sender {
        font-weight: 700;
        font-size: 0.88rem;
        color: var(--chat-text-main) !important;
    }

    .message-role {
        font-size: 0.7rem;
        color: #2563eb !important;
        background-color: #eff6ff !important;
        padding: 1px 6px;
        border-radius: 4px;
        font-weight: 600;
    }

    .message-time {
        font-size: 0.75rem;
        color: var(--chat-text-light) !important;
    }

    .message-text {
        font-size: 0.9rem;
        color: var(--chat-text-muted) !important;
        line-height: 1.5;
        word-break: break-word;
        white-space: pre-wrap;
    }

    /* Message Hover Actions overlay */
    .message-actions {
        position: absolute;
        right: 16px;
        top: -14px;
        background-color: #ffffff !important;
        border: 1px solid var(--slack-border) !important;
        border-radius: 6px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
        display: none;
        padding: 3px;
        z-index: 10;
        gap: 2px;
    }

    .message-item:hover .message-actions {
        display: flex;
    }

    .action-btn {
        background: none;
        border: none;
        color: var(--chat-text-muted) !important;
        cursor: pointer;
        padding: 4px 8px;
        font-size: 0.82rem;
        border-radius: 4px;
        transition: background 0.15s, color 0.15s;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .action-btn:hover {
        background-color: #f1f5f9 !important;
        color: var(--chat-text-main) !important;
    }

    .action-btn.delete-btn:hover {
        background-color: #fee2e2 !important;
        color: #ef4444 !important;
    }

    /* Thread reply trigger indicator */
    .thread-reply-indicator {
        display: inline-flex;
        align-items: center;
        margin-top: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--slack-sidebar-active) !important;
        cursor: pointer;
        border-radius: 4px;
        padding: 2px 6px;
        transition: background-color 0.15s;
    }

    .thread-reply-indicator:hover {
        background-color: #eff6ff !important;
        text-decoration: underline;
    }

    .thread-reply-indicator i {
        margin-right: 4px;
    }

    /* Chat Input Box */
    .chat-input-container {
        padding: 16px 24px;
        background-color: #ffffff !important;
        border-top: 1px solid var(--slack-border) !important;
        flex-shrink: 0;
    }

    .chat-input-box {
        border: 1px solid var(--slack-border) !important;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: border-color 0.15s;
        background-color: #ffffff !important;
    }

    .chat-input-box:focus-within {
        border-color: var(--slack-sidebar-active) !important;
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.15) !important;
    }

    .chat-textarea {
        border: none !important;
        resize: none;
        padding: 12px;
        font-size: 0.9rem;
        color: var(--chat-text-main) !important;
        background-color: #ffffff !important;
        outline: none;
        max-height: 120px;
        min-height: 44px;
    }

    .chat-input-toolbar {
        background-color: #f8fafc !important;
        border-top: 1px solid #e2e8f0 !important;
        padding: 8px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .toolbar-left {
        display: flex;
        gap: 6px;
    }

    .toolbar-btn {
        background: none;
        border: none;
        color: var(--chat-text-muted) !important;
        cursor: pointer;
        padding: 6px;
        font-size: 1.05rem;
        border-radius: 4px;
        transition: background 0.15s, color 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .toolbar-btn:hover {
        background-color: #cbd5e1 !important;
        color: var(--chat-text-main) !important;
    }

    .send-msg-btn {
        background-color: #22c55e !important;
        color: #ffffff !important;
        border: none;
        border-radius: 4px;
        padding: 6px 12px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.15s;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .send-msg-btn:hover {
        background-color: #16a34a !important;
    }

    .send-msg-btn:disabled {
        background-color: #cbd5e1 !important;
        color: #94a3b8 !important;
        cursor: not-allowed;
    }

    /* Attachments Preview Area */
    .attachments-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 8px 12px;
        background-color: #ffffff !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }

    .preview-chip {
        display: flex;
        align-items: center;
        background-color: #f1f5f9 !important;
        border: 1px solid var(--slack-border) !important;
        border-radius: 6px;
        padding: 4px 8px;
        font-size: 0.78rem;
        color: var(--chat-text-muted) !important;
        max-width: 200px;
    }

    .preview-chip-name {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-right: 6px;
    }

    .preview-chip-remove {
        cursor: pointer;
        color: #94a3b8 !important;
        font-size: 0.9rem;
    }

    .preview-chip-remove:hover {
        color: #ef4444 !important;
    }

    /* Inline attachments render */
    .message-attachments {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-top: 8px;
    }

    .attachment-card {
        display: flex;
        align-items: center;
        background-color: #f8fafc !important;
        border: 1px solid var(--slack-border) !important;
        border-radius: 6px;
        padding: 8px 12px;
        max-width: 320px;
        text-decoration: none;
        transition: background-color 0.15s;
    }

    .attachment-card:hover {
        background-color: #f1f5f9 !important;
    }

    .attachment-icon {
        font-size: 1.4rem;
        color: #3b82f6 !important;
        margin-right: 12px;
        display: flex;
        align-items: center;
    }

    .attachment-info {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .attachment-name {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--chat-text-main) !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .attachment-size {
        font-size: 0.72rem;
        color: var(--chat-text-light) !important;
    }

    .attachment-image-preview {
        max-width: 280px;
        max-height: 200px;
        border-radius: 6px;
        border: 1px solid var(--slack-border) !important;
        overflow: hidden;
        margin-top: 6px;
        cursor: pointer;
    }

    .attachment-image-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Thread Sidebar Pane */
    .chat-thread-sidebar {
        width: 340px;
        background-color: #ffffff !important;
        border-left: 1px solid var(--slack-border) !important;
        display: none;
        flex-direction: column;
        flex-shrink: 0;
    }

    .thread-header {
        height: 60px;
        background-color: #ffffff !important;
        border-bottom: 1px solid var(--slack-border) !important;
        padding: 10px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    .thread-header h6 {
        margin: 0;
        font-weight: 700;
        color: var(--chat-text-main) !important;
    }

    .thread-close-btn {
        background: none;
        border: none;
        color: var(--chat-text-muted) !important;
        cursor: pointer;
        font-size: 1.1rem;
        padding: 4px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .thread-close-btn:hover {
        background-color: #f1f5f9 !important;
        color: var(--chat-text-main) !important;
    }

    .thread-content-scroll {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        background-color: #ffffff !important;
    }

    /* Parent Message Highlight inside Thread Sidebar */
    .thread-parent-message {
        border-bottom: 1px solid var(--slack-border) !important;
        padding-bottom: 16px;
        margin-bottom: 4px;
    }

    .replies-divider {
        display: flex;
        align-items: center;
        font-size: 0.72rem;
        color: var(--chat-text-light) !important;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 8px 0;
    }

    .replies-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background-color: #e2e8f0;
        margin-left: 10px;
    }

    .thread-input-container {
        padding: 12px 20px 16px 20px;
        border-top: 1px solid var(--slack-border) !important;
        background-color: #ffffff !important;
    }

    /* Admin Settings Dashboard View */
    .admin-dashboard {
        flex: 1;
        display: none;
        flex-direction: column;
        background-color: #f8fafc !important;
        min-width: 0;
        overflow-y: auto;
    }

    .admin-header {
        height: 60px;
        background-color: #ffffff !important;
        border-bottom: 1px solid var(--slack-border) !important;
        padding: 10px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    .admin-header h6 {
        margin: 0;
        font-weight: 700;
        color: var(--chat-text-main) !important;
    }

    .admin-content {
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .admin-card {
        background-color: #ffffff !important;
        border: 1px solid var(--slack-border) !important;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
    }

    .admin-card-title {
        font-size: 0.92rem;
        font-weight: 700;
        color: var(--chat-text-main) !important;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Loading placeholder skeleton */
    .chat-loading-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex: 1;
        color: var(--chat-text-muted) !important;
        padding: 40px;
        text-align: center;
        background-color: var(--chat-bg-main) !important;
    }

    .chat-loading-placeholder i {
        font-size: 2.5rem;
        margin-bottom: 16px;
        color: #cbd5e1 !important;
    }

    .chat-loading-placeholder h5 {
        color: var(--chat-text-main) !important;
        font-weight: 700;
    }

    /* Modal customizations */
    .slack-modal .modal-content {
        border-radius: 8px;
        border: none;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        background-color: #ffffff !important;
        color: var(--chat-text-main) !important;
    }

    .slack-modal .modal-header {
        border-bottom: 1px solid var(--slack-border) !important;
        padding: 16px 20px;
        color: var(--chat-text-main) !important;
    }

    .slack-modal .modal-body {
        padding: 20px;
    }

    .slack-modal .modal-footer {
        border-top: 1px solid var(--slack-border) !important;
        padding: 12px 20px;
    }

    /* Custom form elements */
    .slack-form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--chat-text-muted) !important;
        margin-bottom: 6px;
    }

    .slack-form-control {
        border: 1px solid var(--slack-border) !important;
        border-radius: 6px;
        font-size: 0.88rem;
        padding: 8px 12px;
        color: var(--chat-text-main) !important;
        background-color: #ffffff !important;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .slack-form-control:focus {
        border-color: var(--slack-sidebar-active) !important;
        box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.15) !important;
        outline: none;
    }

    /* User item badges */
    .user-chip {
        display: inline-flex;
        align-items: center;
        background-color: #f1f5f9 !important;
        border-radius: 4px;
        padding: 2px 8px;
        font-size: 0.75rem;
        color: var(--chat-text-muted) !important;
        font-weight: 500;
        gap: 4px;
    }

    .user-chip-remove {
        cursor: pointer;
        color: #94a3b8 !important;
    }

    .user-chip-remove:hover {
        color: #ef4444 !important;
    }
</style>
