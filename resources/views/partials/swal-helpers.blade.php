{{-- Shared SweetAlert2 house style + convenience helpers, included by both
     admin.layout and cashier.layout right after the SweetAlert2 script tag.
     Replaces raw alert()/confirm() calls system-wide with a single
     consistent look (slim, compact, rounded corners) instead of every call
     site hand-rolling its own Swal.fire({...}) options.

     Deliberately UNSCOPED (plain .swal2-popup, not .swal-compact.swal2-popup)
     — an audit found ~90 raw Swal.fire({...}) call sites across 20+ admin
     views that never passed customClass:{popup:'swal-compact'} at all, so a
     class-scoped rule would only have styled a small fraction of the
     system's dialogs. Targeting the base SweetAlert2 class directly
     guarantees every dialog gets the same compact design without having to
     touch every call site by hand, and automatically covers any future one
     too. The .swal-compact class (still passed by the shared helpers below)
     is harmless now — kept only for call sites that reference it. --}}
<style>
    /* SweetAlert2 v11 ships its own default width/padding as a plain
       (unlayered) `.swal2-popup { width: 28em; padding: 1.5em; }` rule.
       Overriding CSS custom properties (--swal2-width etc, its documented
       theming mechanism) still loses to that same plain rule for the
       properties it doesn't itself set via variables, and even a
       higher-specificity plain selector didn't reliably win against it in
       testing — so !important is used here deliberately, scoped tightly to
       just the handful of box-model properties this needs to change. */
    .swal2-popup {
        border-radius: 14px !important;
        padding: 0.65rem 1.1rem 0.85rem !important;
        width: 380px !important;
        max-width: 92vw !important;
        font-family: inherit !important;
    }
    .swal2-popup .swal2-title { font-size: 1rem !important; margin: 0.1rem 0.3rem 0.15rem !important; padding: 0 !important; }
    .swal2-popup .swal2-html-container { font-size: 0.85rem !important; margin: 0.15rem 0.4rem 0.1rem !important; }
    .swal2-popup .swal2-icon { margin-top: 0.25rem !important; margin-bottom: 0.15rem !important; width: 2.4em !important; height: 2.4em !important; }
    .swal2-popup .swal2-actions { margin-top: 0.5rem !important; margin-bottom: 0 !important; gap: 8px !important; }
    .swal2-popup .swal2-styled {
        border-radius: 8px !important;
        padding: 0.32em 1.1em !important;
        min-height: 2.1em !important;
        font-weight: 600 !important;
        font-size: 0.88rem !important;
    }
    .swal2-popup .swal2-close { font-size: 1.3rem !important; top: 4px !important; right: 4px !important; }

    @media (max-width: 480px) {
        .swal2-popup { width: 92vw !important; padding: 0.55rem 0.85rem 0.75rem !important; }
    }
</style>
<script>
    // window.confirmAction({ title, text, icon, confirmText, cancelText, confirmColor }) -> Promise<SweetAlertResult>
    window.confirmAction = function (options) {
        options = options || {};
        return Swal.fire({
            title: options.title || 'Are you sure?',
            text: options.text || '',
            icon: options.icon || 'question',
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Yes',
            cancelButtonText: options.cancelText || 'No',
            confirmButtonColor: options.confirmColor || '#10b981',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            customClass: { popup: 'swal-compact' },
        });
    };

    // window.confirmDelete(text) -> Promise<SweetAlertResult> — pre-styled danger variant of confirmAction.
    window.confirmDelete = function (text) {
        return window.confirmAction({
            title: 'Confirm Delete',
            text: text || 'This action cannot be undone.',
            icon: 'warning',
            confirmText: 'Delete',
            confirmColor: '#ef4444',
        });
    };

    window.toastSuccess = function (text, title) {
        return Swal.fire({
            title: title || 'Success', text: text || '', icon: 'success',
            confirmButtonColor: '#10b981', timer: 2500, showConfirmButton: false,
            customClass: { popup: 'swal-compact' },
        });
    };

    window.toastError = function (text, title) {
        return Swal.fire({
            title: title || 'Error', text: text || '', icon: 'error',
            confirmButtonColor: '#ef4444',
            customClass: { popup: 'swal-compact' },
        });
    };

    window.toastWarning = function (text, title) {
        return Swal.fire({
            title: title || 'Warning', text: text || '', icon: 'warning',
            confirmButtonColor: '#f59e0b',
            customClass: { popup: 'swal-compact' },
        });
    };

    // Drop-in replacement for <form onsubmit="return confirm('...')">:
    //   <form method="POST" action="..." class="js-confirm-submit"
    //         data-confirm-title="..." data-confirm-text="..."
    //         data-confirm-icon="warning" data-confirm-color="#ef4444">
    // Delegated on document (not bound per-form) so it keeps working on
    // rows injected later by an AJAX table refresh — a listener attached
    // directly to a form/button would be lost the moment that markup gets
    // replaced via innerHTML.
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form.js-confirm-submit');
        if (!form || form.dataset.confirmed === 'true') return;

        e.preventDefault();
        window.confirmAction({
            title: form.dataset.confirmTitle || 'Are you sure?',
            text: form.dataset.confirmText || '',
            icon: form.dataset.confirmIcon || 'question',
            confirmColor: form.dataset.confirmColor || '#10b981',
        }).then(function (result) {
            if (result.isConfirmed) {
                form.dataset.confirmed = 'true';
                form.submit();
            }
        });
    });
</script>
