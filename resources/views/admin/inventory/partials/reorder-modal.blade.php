{{-- Glassmorphism "Create Purchase Order" modal, opened from an Inventory
     row/detail page's "Create Purchase Order" button. Keeps the admin on
     the Inventory page instead of navigating to a separate Purchase Order
     page — the product/supplier fields are fetched over AJAX from
     PurchaseOrderController::createFromReorder() and injected into the
     modal body, then submitted to storeFromReorder() the same way. --}}
@include('admin.partials.modal-styles')

<div id="reorderPurchaseOrderModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="reorderPurchaseOrderModalTitle" aria-hidden="true">
    <div class="modal-content modal-content-wide">
        <div class="modal-header">
            <h2 id="reorderPurchaseOrderModalTitle"><i class="fas fa-cart-plus"></i> Create Purchase Order</h2>
            <button type="button" class="modal-close" onclick="closeReorderModal()" aria-label="Close">&times;</button>
        </div>

        <div id="reorderPurchaseOrderGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="reorderPurchaseOrderForm">
            <div id="reorderPurchaseOrderBody" style="min-height:160px;">
                <div style="display:flex; align-items:center; justify-content:center; min-height:160px; color:#94a3b8;">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
            </div>
        </form>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="reorderPurchaseOrderCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="reorderPurchaseOrderSubmitBtn" disabled>
                <i class="fas fa-save"></i> Create Purchase Order
            </button>
        </div>
    </div>
</div>

@include('admin.purchase-orders.partials.reorder-supplier-preview-script')
@include('admin.partials.ajax-modal-form')

<script>
    const REORDER_FETCH_URL_TEMPLATE = '{{ route('admin.purchase-orders.create-from-reorder', ['product' => '__PRODUCT_ID__']) }}';
    const REORDER_STORE_URL_TEMPLATE = '{{ route('admin.purchase-orders.store-from-reorder', ['product' => '__PRODUCT_ID__']) }}';
    const REORDER_FIELD_IDS = ['SupplierID', 'OrderQuantity', 'Remarks'];
    let reorderModalLastFocused = null;

    function reorderModalIsSubmitting() {
        const btn = document.getElementById('reorderPurchaseOrderSubmitBtn');
        return btn ? btn.dataset.submitting === 'true' : false;
    }

    function clearReorderFieldErrors() {
        const form = document.getElementById('reorderPurchaseOrderForm');
        REORDER_FIELD_IDS.forEach(function (field) {
            const span = document.getElementById('error-' + field);
            if (span) span.textContent = '';
            const input = form.querySelector('[name="' + field + '"]');
            if (input) input.classList.remove('error');
        });
    }

    function showReorderFieldErrors(errors) {
        const form = document.getElementById('reorderPurchaseOrderForm');
        clearReorderFieldErrors();
        let firstInvalid = null;
        Object.keys(errors).forEach(function (field) {
            const span = document.getElementById('error-' + field);
            if (span) span.textContent = errors[field][0];
            const input = form.querySelector('[name="' + field + '"]');
            if (input) {
                input.classList.add('error');
                if (!firstInvalid) firstInvalid = input;
            }
        });
        if (firstInvalid) firstInvalid.focus();
    }

    function showReorderGeneralError(message) {
        const banner = document.getElementById('reorderPurchaseOrderGeneralError');
        banner.textContent = message;
        banner.style.display = 'flex';
    }

    function hideReorderGeneralError() {
        const banner = document.getElementById('reorderPurchaseOrderGeneralError');
        banner.style.display = 'none';
        banner.textContent = '';
    }

    function resetReorderSubmitButton() {
        const btn = document.getElementById('reorderPurchaseOrderSubmitBtn');
        btn.dataset.submitting = 'false';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Create Purchase Order';
    }

    // Product/Supplier information must be loaded before the order can be
    // saved — the Submit button stays disabled until the AJAX fetch below
    // resolves successfully.
    window.openReorderModal = function (productId) {
        const modal = document.getElementById('reorderPurchaseOrderModal');
        const body = document.getElementById('reorderPurchaseOrderBody');
        const submitBtn = document.getElementById('reorderPurchaseOrderSubmitBtn');
        const form = document.getElementById('reorderPurchaseOrderForm');

        reorderModalLastFocused = document.activeElement;
        hideReorderGeneralError();
        clearReorderFieldErrors();
        resetReorderSubmitButton();
        submitBtn.disabled = true;
        form.dataset.productId = productId;
        body.innerHTML = '<div style="text-align:center;padding:30px;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>';

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('active'); });
        document.addEventListener('keydown', handleReorderModalKeydown);

        fetch(REORDER_FETCH_URL_TEMPLATE.replace('__PRODUCT_ID__', productId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                body.innerHTML = data.html;
                const titleEl = document.getElementById('reorderPurchaseOrderModalTitle');
                if (titleEl) {
                    titleEl.innerHTML = '<i class="fas fa-cart-plus"></i> Create Purchase Order &mdash; ' + data.productName;
                }
                submitBtn.disabled = false;
            })
            .catch(function () {
                body.innerHTML = '<p style="color:#fca5a5;padding:20px;text-align:center;">Failed to load product details. Please try again.</p>';
            });
    };

    window.closeReorderModal = function () {
        const modal = document.getElementById('reorderPurchaseOrderModal');
        modal.classList.remove('active');
        document.removeEventListener('keydown', handleReorderModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        if (reorderModalLastFocused && typeof reorderModalLastFocused.focus === 'function') {
            reorderModalLastFocused.focus();
        }
    };

    function handleReorderModalKeydown(e) {
        const modal = document.getElementById('reorderPurchaseOrderModal');
        if (!modal.classList.contains('active')) return;

        if (e.key === 'Escape') {
            if (!reorderModalIsSubmitting()) window.closeReorderModal();
            return;
        }

        if (e.key === 'Tab') {
            const focusable = modal.querySelectorAll('input, select, textarea, button, [href]');
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }

    document.getElementById('reorderPurchaseOrderModal').addEventListener('mousedown', function (e) {
        if (e.target === this && !reorderModalIsSubmitting()) {
            window.closeReorderModal();
        }
    });

    document.getElementById('reorderPurchaseOrderForm').addEventListener('submit', function (e) { e.preventDefault(); });

    document.getElementById('reorderPurchaseOrderCancelBtn').addEventListener('click', function () {
        if (!reorderModalIsSubmitting()) window.closeReorderModal();
    });

    document.getElementById('reorderPurchaseOrderSubmitBtn').addEventListener('click', function () {
        const form = document.getElementById('reorderPurchaseOrderForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Confirm Save',
            text: 'Are you sure you want to create this purchase order?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            const submitBtn = document.getElementById('reorderPurchaseOrderSubmitBtn');
            submitBtn.dataset.submitting = 'true';
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            clearReorderFieldErrors();
            hideReorderGeneralError();

            const storeUrl = REORDER_STORE_URL_TEMPLATE.replace('__PRODUCT_ID__', form.dataset.productId);

            window.submitAjaxForm(form, storeUrl, {
                onFieldErrors: function (errors) {
                    showReorderFieldErrors(errors);
                    resetReorderSubmitButton();
                },
                onSuccess: function (html, message) {
                    window.closeReorderModal();
                    Swal.fire({
                        title: 'Success',
                        text: message,
                        icon: 'success',
                        confirmButtonColor: '#10b981',
                        timer: 2000,
                        showConfirmButton: false
                    });
                },
                onOtherError: function (message) {
                    showReorderGeneralError(message);
                    resetReorderSubmitButton();
                }
            });
        });
    });
</script>
