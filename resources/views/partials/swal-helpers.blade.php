{{-- Shared SweetAlert2 house style + convenience helpers, included by both
     admin.layout and cashier.layout right after the SweetAlert2 script tag.
     Replaces raw alert()/confirm() calls system-wide with a single
     consistent look (compact height, medium width, rounded corners) instead
     of every call site hand-rolling its own Swal.fire({...}) options. --}}
<style>
    .swal-compact.swal2-popup {
        border-radius: 16px;
        padding: 1.1rem 1.4rem 1.5rem;
        width: 420px;
        font-family: inherit;
    }
    .swal-compact .swal2-title { font-size: 1.15rem; margin-top: 0.35rem; padding: 0; }
    .swal-compact .swal2-html-container { font-size: 0.92rem; margin: 0.4rem 0.5rem 0.4rem; }
    .swal-compact .swal2-icon { margin-top: 0.6rem; margin-bottom: 0.4rem; width: 3.4em; height: 3.4em; }
    .swal-compact .swal2-actions { margin-top: 1rem; gap: 10px; }
    .swal-compact .swal2-styled { border-radius: 10px; padding: 0.55em 1.4em; font-weight: 600; }
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
