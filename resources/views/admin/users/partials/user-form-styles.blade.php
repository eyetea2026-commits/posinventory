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

    .form-select {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 20px;
        padding-right: 40px;
    }

    .form-error {
        display: block;
        margin-top: 6px;
        font-size: 0.8rem;
        color: #fca5a5;
    }

    .protected-notice {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 18px;
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 12px;
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
