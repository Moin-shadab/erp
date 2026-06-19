{{-- Email Accounts Manager Custom CSS --}}
<style>
    .accounts-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background-color: #ffffff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02), 0 1px 2px rgba(0,0,0,0.01);
    }
    .badge-user {
        font-size: 0.75rem;
        padding: 4px 8px;
        background-color: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin: 2px;
    }
    .user-select-list {
        max-height: 180px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 10px;
    }
    .advanced-settings-section {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 15px;
    }
    .selected-users-chips-container {
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        background: #f8fafc !important;
        min-height: 42px;
        transition: border-color 0.15s;
    }
    .selected-users-chips-container:focus-within {
        border-color: #3b82f6 !important;
    }
    #user-dropdown-list {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.05) !important;
    }
    #user-dropdown-list li:hover {
        background-color: #f1f5f9;
    }
</style>
