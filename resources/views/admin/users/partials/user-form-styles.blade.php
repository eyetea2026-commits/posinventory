{{-- Shared "Add User" form field styling. Included by both the standalone
     create page and the Add User modal so there is one source of truth. --}}
<style>
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .form-group { margin-bottom: 0; }
    .form-group.full-width { grid-column: 1 / -1; }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #cbd5e1;
        font-size: 0.9rem;
    }

    .form-label .required { color: #ef4444; }

    .form-input, .form-select {
        width: 100%;
        padding: 14px 16px;
        background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 12px;
        color: #f8fafc;
        font-size: 0.95rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .form-input:hover, .form-select:hover {
        border-color: rgba(59, 130, 246, 0.35);
    }

    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    .form-input.error, .form-select.error { border-color: #ef4444; }

    .form-error {
        display: block;
        margin-top: 6px;
        font-size: 0.8rem;
        color: #fca5a5;
    }

    .spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid transparent;
        border-top-color: currentColor;
        border-radius: 50%;
        animation: user-form-spin 0.8s linear infinite;
    }

    @keyframes user-form-spin { to { transform: rotate(360deg); } }

    @media (max-width: 600px) {
        .form-grid { grid-template-columns: 1fr; }
    }
</style>
