<style>
    /* Custom Scrollbar for containers */
    .scrollable-y::-webkit-scrollbar {
        width: 6px;
    }
    .scrollable-y::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.03);
        border-radius: 4px;
    }
    .scrollable-y::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.12);
        border-radius: 4px;
    }
    .scrollable-y::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 0, 0, 0.24);
    }

    /* Small badge overrides */
    .small-badge {
        font-size: 0.7rem;
        padding: 0.25em 0.5em;
        font-weight: 600;
    }

    /* Staff cards styling */
    .staff-card {
        border-radius: 10px;
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
    }
    .staff-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
        border-color: var(--bs-primary) !important;
    }
    .staff-card.active-selected {
        border-color: var(--bs-primary) !important;
        background-color: rgba(13, 110, 253, 0.04) !important;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.08) !important;
    }

    /* Drag handles and cursor styling */
    .drag-handle {
        cursor: grab;
        color: #adb5bd;
    }
    .drag-handle:active {
        cursor: grabbing;
    }

    /* Dropzones dotted borders */
    .routing-dropzone {
        border-color: #dee2e6 !important;
        background-color: #fff;
    }
    .routing-dropzone.dragover {
        border-color: var(--bs-primary) !important;
        background-color: rgba(13, 110, 253, 0.05) !important;
        transform: scale(1.02);
    }

    /* Active connection entries list item */
    .routing-item {
        transition: all 0.2s ease;
        border-radius: 8px;
    }
    .routing-item:hover {
        background-color: rgba(0, 0, 0, 0.02) !important;
        border-color: rgba(0, 0, 0, 0.1) !important;
    }
</style>
