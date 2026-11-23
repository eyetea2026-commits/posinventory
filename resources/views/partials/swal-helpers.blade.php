<style>
    .swal2-popup {
        border-radius: 14px !important;
        padding: 0.85rem 1.3rem 1.1rem !important;
        width: 440px !important;
        max-width: 92vw !important;
        font-family: inherit !important;
    }
    .swal2-popup .swal2-title { font-size: 1.15rem !important; margin: 0.2rem 0.2rem 0.3rem !important; padding: 0 !important; line-height: 1.3 !important; }
    .swal2-popup .swal2-html-container { font-size: 0.86rem !important; line-height: 1.5 !important; margin: 0.2rem 0.3rem 0.1rem !important; }
    .swal2-popup .swal2-icon { margin-top: 0.2rem !important; margin-bottom: 0.15rem !important; font-size: 11.2px !important; }
    .swal2-popup .swal2-icon.swal2-success { border-color: #16a34a !important; }
    .swal2-popup .swal2-icon.swal2-success [class^='swal2-success-line'] { background-color: #16a34a !important; }
    .swal2-popup .swal2-icon.swal2-success .swal2-success-ring { border-color: rgba(22, 163, 74, 0.35) !important; }
    .swal2-popup .swal2-actions { margin-top: 0.75rem !important; margin-bottom: 0 !important; gap: 10px !important; }
    .swal2-popup .swal2-styled {
        border-radius: 8px !important;
        padding: 0.45em 1.3em !important;
        min-height: 2.15em !important;
        font-weight: 600 !important;
        font-size: 0.88rem !important;
    }
    .swal2-popup .swal2-close { font-size: 1.4rem !important; top: 6px !important; right: 6px !important; }

    .swal2-container { transition-duration: 0.1s !important; }
    .swal2-popup.swal2-show { animation-duration: 0.15s !important; }
    .swal2-popup.swal2-hide { animation-duration: 0.1s !important; }
    .swal2-popup .swal2-icon,
    .swal2-popup .swal2-icon * { animation: none !important; }
    @media (prefers-reduced-motion: reduce) {
        .swal2-popup.swal2-show, .swal2-popup.swal2-hide { animation: none !important; }
    }

    @media (max-width: 640px) {
        .swal2-popup { width: 94vw !important; padding: 0.85rem 1.1rem 1rem !important; }
        .swal2-popup .swal2-title { font-size: 1.05rem !important; }
        .swal2-popup .swal2-icon { font-size: 10px !important; }
        .swal2-popup .swal2-actions { flex-wrap: wrap !important; }
        .swal2-popup .swal2-styled { flex: 1 1 auto !important; }
    }
</style>
<script>
    (function () {
        const SUCCESS_POPUP_MS = 1200;
        if (!window.Swal || Swal.__fastSuccess) return;
        const fire = Swal.fire.bind(Swal);
        Swal.fire = function (options) {
            if (options && typeof options === 'object' && options.icon === 'success' && options.timer) {
                options = Object.assign({}, options, { timer: Math.min(options.timer, SUCCESS_POPUP_MS) });
            }
            return fire.apply(Swal, [options].concat(Array.prototype.slice.call(arguments, 1)));
        };
        Swal.__fastSuccess = true;
    })();

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
            confirmButtonColor: '#10b981', timer: 1200, showConfirmButton: false,
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

    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form.js-confirm-submit');
        if (!form || form.dataset.confirmed === 'true' || e.defaultPrevented) return;

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

    document.addEventListener('click', function (e) {
        const link = e.target.closest('a.js-confirm-link');
        if (!link || !link.href || link.getAttribute('href') === '#') return;

        e.preventDefault();
        window.confirmAction({
            title: link.dataset.confirmTitle || 'Are you sure?',
            text: link.dataset.confirmText || '',
            icon: link.dataset.confirmIcon || 'question',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            const go = document.createElement('a');
            go.href = link.href;
            go.target = link.target;
            if (link.target === '_blank') go.rel = 'noopener';
            document.body.appendChild(go);
            go.click();
            go.remove();
        });
    });
</script>
