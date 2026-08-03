@extends('admin.layout')

@section('title', 'Promo Codes - CCTV Express')

@section('header')
    <div class="header-title">
        <h1>Promo Codes</h1>
        <p>Manage product-specific promotional discounts</p>
    </div>
@endsection

@section('content')
<style>
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
    }
    .btn-primary {
        background: linear-gradient(135deg, #3b82f6, #10b981);
        color: white;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }
    .btn-secondary {
        background: rgba(148, 163, 184, 0.15);
        color: #cbd5e1;
        border: 1px solid rgba(148, 163, 184, 0.2);
    }
    .btn-secondary:hover { background: rgba(148, 163, 184, 0.25); }
    .btn-sm { padding: 6px 12px; font-size: 0.85rem; border-radius: 8px; }
    .btn-danger {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }
    .btn-danger:hover { background: rgba(239, 68, 68, 0.25); }
    .card {
        background: rgba(10, 18, 35, 0.8);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(148, 163, 184, 0.1);
        border-radius: 20px;
        overflow: hidden;
    }
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        padding: 20px 24px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.1);
    }
    .search-box-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        flex: 1;
        min-width: 260px;
        max-width: 480px;
        background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(59, 130, 246, 0.15);
        border-radius: 12px;
        transition: all 0.3s ease;
    }
    .search-box-wrapper:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
    .search-box-wrapper .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.95rem; pointer-events: none; }
    .search-input { flex: 1; width: 100%; padding: 12px 16px 12px 44px; background: transparent; border: none; color: #f8fafc; font-size: 0.95rem; }
    .search-input::placeholder { color: #64748b; }
    .search-input:focus { outline: none; }
    .card-body { padding: 0; }
    .table { width: 100%; border-collapse: collapse; }
    .table th {
        background: rgba(15, 23, 42, 0.5);
        padding: 16px 20px;
        text-align: left;
        font-weight: 600;
        color: var(--text-muted);
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
    }
    .table td { padding: 16px 20px; border-bottom: 1px solid rgba(148, 163, 184, 0.08); white-space: nowrap; }
    .table tbody tr:hover { background: rgba(59, 130, 246, 0.05); }
    .actions-group { display: flex; gap: 8px; }
    .empty-state { padding: 60px 20px; text-align: center; }
    .empty-icon {
        width: 80px; height: 80px; margin: 0 auto 20px;
        background: rgba(59, 130, 246, 0.1); border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem; color: var(--primary);
    }
    .empty-title { font-size: 1.25rem; color: var(--text-primary); margin-bottom: 8px; }
    .empty-text { color: var(--text-muted); margin-bottom: 20px; }
    .alert { padding: 16px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
    .alert-success { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.3); }
    .alert-danger { background: rgba(239, 68, 68, 0.15); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); }
    .badge { display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
    .badge-success { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; }
    .badge-warning { background: rgba(245, 158, 11, 0.15); color: #fcd34d; }
    .badge-secondary { background: rgba(148, 163, 184, 0.15); color: #cbd5e1; }
    .rate-cell { font-weight: 700; color: var(--text-primary); }
    .pagination { display: flex; gap: 6px; justify-content: center; padding: 20px; }
    .pagination-link { padding: 8px 14px; border-radius: 8px; background: rgba(148,163,184,0.1); color: var(--text-primary); text-decoration: none; }
    .pagination-link.active { background: linear-gradient(135deg, #3b82f6, #10b981); color: white; }
    .pagination-link.disabled { opacity: 0.4; }

    /* Add/Edit Promo Modal */
    .form-group label { display: block; margin-bottom: 4px; font-weight: 600; color: #cbd5e1; font-size: 0.82rem; }
    .form-group label .required { color: #ef4444; }
    .form-group { margin-bottom: 16px; }
    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-control {
        width: 100%;
        padding: 9px 12px;
        background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 9px;
        color: #f8fafc;
        font-size: 0.85rem;
    }
    .form-control:focus { outline: none; border-color: var(--primary); }
    .form-control.error { border-color: #ef4444; }
    .form-control:disabled { opacity: 0.7; cursor: not-allowed; }
    .error { display: block; margin-top: 3px; color: #fca5a5; font-size: 0.72rem; }

    .modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.7); z-index: 900;
        align-items: center; justify-content: center; backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    }
    .modal-overlay.active { display: flex; }
    .modal-content {
        background: #0f172a; border: 1px solid #334155; border-radius: 20px; padding: 18px 22px;
        max-width: 640px; width: 92%; max-height: 88vh; overflow-y: auto;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        transform: scale(0.95) translateY(12px); opacity: 0;
        transition: transform 0.25s ease, opacity 0.25s ease;
    }
    .modal-overlay.active .modal-content { transform: scale(1) translateY(0); opacity: 1; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #334155; }
    .modal-header h2 { margin: 0; font-size: 1.05rem; color: #f8fafc; }
    .modal-close { width: 32px; height: 32px; background: #1e293b; border: none; border-radius: 8px; color: #94a3b8; font-size: 1.2rem; cursor: pointer; }
    .modal-close:hover { background: #334155; color: #fff; }
    .modal-actions { display: flex; justify-content: space-between; gap: 10px; margin-top: 14px; padding-top: 12px; border-top: 1px solid #334155; }
    .modal-actions .btn { padding: 10px 18px; font-size: 0.85rem; }
    .form-error-banner {
        display: flex; align-items: center; gap: 10px;
        background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5;
        padding: 10px 14px; border-radius: 10px; margin-bottom: 14px; font-size: 0.82rem;
    }
</style>

@if(session('success'))
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}</div>
@endif

<div class="card">
    <div class="card-header">
        <form method="GET" action="{{ route('admin.discounts.index') }}" id="discountFilterForm" class="search-box-wrapper" autocomplete="off">
            <i class="search-icon fas fa-search"></i>
            <input type="text" name="search" id="discountSearchInput" class="search-input" placeholder="Search by product, promo code, or name..." value="{{ $search ?? '' }}">
        </form>
        <a href="{{ route('admin.discounts.create') }}" class="btn btn-primary" onclick="openAddDiscountModal(event)" title="Add Promo Code">
            <i class="fa-solid fa-plus"></i> Add Promo Code
        </a>
    </div>
    <div class="card-body">
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Original Price</th>
                        <th>Discount %</th>
                        <th>Discounted Price</th>
                        <th>Promo Code</th>
                        <th>Promo Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="discountsTbody">
                    @include('admin.discounts.partials.rows', ['discounts' => $discounts])
                </tbody>
            </table>
        </div>
        <div id="discountPaginationWrapper">
            @include('admin.discounts.partials.pagination', ['discounts' => $discounts])
        </div>
    </div>
</div>

<!-- Add Promo Modal -->
<div id="addDiscountModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="addDiscountModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="addDiscountModalTitle"><i class="fa-solid fa-percent"></i> Add Promo Code</h2>
            <button type="button" class="modal-close" onclick="closeAddDiscountModal()" aria-label="Close">&times;</button>
        </div>

        <div id="addDiscountGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="addDiscountForm">
            {{-- Explicit discount=>null guards against $discount leaking in
                 from the @forelse($discounts as $discount) table loop above. --}}
            @include('admin.discounts.partials.discount-form-fields', ['discount' => null, 'products' => $products])
        </form>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="addDiscountCancelBtn"><i class="fas fa-times"></i> Cancel</button>
            <button type="button" class="btn btn-primary" id="addDiscountSubmitBtn"><i class="fas fa-save"></i> Save Promo</button>
        </div>
    </div>
</div>

<!-- Edit Promo Modal -->
<div id="editDiscountModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editDiscountModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="editDiscountModalTitle"><i class="fa-solid fa-percent"></i> Edit Promo Code</h2>
            <button type="button" class="modal-close" onclick="closeEditDiscountModal()" aria-label="Close">&times;</button>
        </div>

        <div id="editDiscountGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="editDiscountForm">
            <div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>
        </form>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="editDiscountCancelBtn"><i class="fas fa-times"></i> Cancel</button>
            <button type="button" class="btn btn-primary" id="editDiscountSubmitBtn"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </div>
</div>

@include('admin.partials.ajax-modal-form')
@include('admin.discounts.partials.discount-form-behavior')

<script>
    // Auto-show session messages
    @if(session('success'))
        toastSuccess('{{ session('success') }}');
    @endif
    @if(session('error'))
        toastError('{{ session('error') }}');
    @endif

    function deleteDiscount(discountId) {
        window.confirmDelete('Are you sure you want to delete this promo code? This action cannot be undone.').then(function (result) {
            if (result.isConfirmed) {
                document.getElementById('deleteDiscountForm' + discountId).submit();
            }
        });
    }

    // ---- Live search ----
    (function () {
        const filterForm = document.getElementById('discountFilterForm');
        const searchInput = document.getElementById('discountSearchInput');
        const tbody = document.getElementById('discountsTbody');
        const paginationWrapper = document.getElementById('discountPaginationWrapper');
        let debounceTimer = null;
        let currentController = null;

        function buildQuery(page) {
            const params = new URLSearchParams();
            const search = searchInput.value.trim();
            if (search) params.set('search', search);
            if (page > 1) params.set('page', page);
            return params.toString();
        }

        async function applyFilters(page = 1) {
            const query = buildQuery(page);
            const url = `${filterForm.action}${query ? '?' + query : ''}`;
            const fetchUrl = url + (query ? '&' : '?') + 'ajax=1';
            window.history.replaceState({}, '', url);

            if (currentController) currentController.abort();
            currentController = new AbortController();

            try {
                const response = await fetch(fetchUrl, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    signal: currentController.signal,
                });
                if (!response.ok) throw new Error(`Request failed (${response.status})`);
                const data = await response.json();
                tbody.innerHTML = data.rows || '';
                paginationWrapper.innerHTML = data.pagination || '';
                rebindPagination();
            } catch (err) {
                if (err.name === 'AbortError') return;
                tbody.innerHTML = `<tr><td colspan="10"><div class="empty-state"><div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div><p class="empty-title">Unable to load promo codes</p><p class="empty-text">Please try again.</p></div></td></tr>`;
                paginationWrapper.innerHTML = '';
            }
        }

        function rebindPagination() {
            paginationWrapper.querySelectorAll('a.pagination-link').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const page = new URL(this.href).searchParams.get('page') || 1;
                    applyFilters(page);
                });
            });
        }

        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () { applyFilters(1); }, 300);
        });
        filterForm.addEventListener('submit', function (e) { e.preventDefault(); applyFilters(1); });

        rebindPagination();
        window.refreshDiscountsTable = function () { applyFilters(1); };
    })();

    // ---- Add Promo modal ----
    const ADD_DISCOUNT_FIELD_IDS = ['ProductID', 'DiscountRate', 'Name', 'PromoCode', 'Description', 'StartDate', 'EndDate', 'Status'];
    let addDiscountLastFocused = null;

    function addDiscountIsSubmitting() {
        const btn = document.getElementById('addDiscountSubmitBtn');
        return btn ? btn.disabled : false;
    }

    function clearAddDiscountFieldErrors() {
        const form = document.getElementById('addDiscountForm');
        ADD_DISCOUNT_FIELD_IDS.forEach(function (field) {
            const span = form.querySelector('#error-' + field);
            if (span) span.textContent = '';
            const input = form.querySelector('[name="' + field + '"]');
            if (input) input.classList.remove('error');
        });
    }

    function showAddDiscountFieldErrors(errors) {
        const form = document.getElementById('addDiscountForm');
        clearAddDiscountFieldErrors();
        let firstInvalid = null;
        Object.keys(errors).forEach(function (field) {
            const span = form.querySelector('#error-' + field);
            if (span) span.textContent = errors[field][0];
            const input = form.querySelector('[name="' + field + '"]');
            if (input) {
                input.classList.add('error');
                if (!firstInvalid) firstInvalid = input;
            }
        });
        if (firstInvalid) firstInvalid.focus();
    }

    function showAddDiscountGeneralError(message) {
        const banner = document.getElementById('addDiscountGeneralError');
        banner.textContent = message;
        banner.style.display = 'flex';
    }

    function hideAddDiscountGeneralError() {
        const banner = document.getElementById('addDiscountGeneralError');
        banner.style.display = 'none';
        banner.textContent = '';
    }

    function resetAddDiscountSubmitButton() {
        const btn = document.getElementById('addDiscountSubmitBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Promo';
    }

    window.openAddDiscountModal = function (event) {
        if (event) event.preventDefault();
        const modal = document.getElementById('addDiscountModal');
        const form = document.getElementById('addDiscountForm');

        addDiscountLastFocused = document.activeElement;
        form.reset();
        delete form.dataset.selectedProductPrice;
        clearAddDiscountFieldErrors();
        hideAddDiscountGeneralError();
        resetAddDiscountSubmitButton();

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('active'); });

        const firstField = form.querySelector('input, textarea, select');
        if (firstField) firstField.focus();

        document.addEventListener('keydown', handleAddDiscountModalKeydown);
    };

    window.closeAddDiscountModal = function () {
        const modal = document.getElementById('addDiscountModal');
        modal.classList.remove('active');
        document.removeEventListener('keydown', handleAddDiscountModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        if (addDiscountLastFocused && typeof addDiscountLastFocused.focus === 'function') addDiscountLastFocused.focus();
    };

    function handleAddDiscountModalKeydown(e) {
        const modal = document.getElementById('addDiscountModal');
        if (!modal.classList.contains('active')) return;
        if (e.key === 'Escape') { if (!addDiscountIsSubmitting()) closeAddDiscountModal(); return; }
        if (e.key === 'Tab') {
            const focusable = modal.querySelectorAll('input, select, textarea, button, [href]');
            if (!focusable.length) return;
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    }

    document.getElementById('addDiscountModal').addEventListener('mousedown', function (e) {
        if (e.target === this && !addDiscountIsSubmitting()) closeAddDiscountModal();
    });
    document.getElementById('addDiscountForm').addEventListener('submit', function (e) { e.preventDefault(); });
    document.getElementById('addDiscountCancelBtn').addEventListener('click', function () { closeAddDiscountModal(); });

    document.getElementById('addDiscountSubmitBtn').addEventListener('click', function () {
        const form = document.getElementById('addDiscountForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        window.confirmAction({ title: 'Confirm Save', text: 'Create this promo code?' }).then(function (result) {
            if (!result.isConfirmed) return;

            const submitBtn = document.getElementById('addDiscountSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            clearAddDiscountFieldErrors();
            hideAddDiscountGeneralError();

            window.submitAjaxForm(form, '{{ route('admin.discounts.store') }}', {
                onFieldErrors: function (errors) {
                    showAddDiscountFieldErrors(errors);
                    resetAddDiscountSubmitButton();
                },
                onSuccess: function (html, message) {
                    window.refreshDiscountsTable();
                    closeAddDiscountModal();
                    toastSuccess(message);
                },
                onOtherError: function (message) {
                    showAddDiscountGeneralError(message);
                    resetAddDiscountSubmitButton();
                }
            });
        });
    });

    // ---- Edit Promo modal ----
    let editDiscountLastFocused = null;
    let currentEditDiscountId = null;

    function editDiscountIsSubmitting() {
        const btn = document.getElementById('editDiscountSubmitBtn');
        return btn ? btn.disabled : false;
    }

    function clearEditDiscountFieldErrors() {
        const form = document.getElementById('editDiscountForm');
        ADD_DISCOUNT_FIELD_IDS.forEach(function (field) {
            const span = form.querySelector('#error-' + field);
            if (span) span.textContent = '';
            const input = form.querySelector('[name="' + field + '"]');
            if (input) input.classList.remove('error');
        });
    }

    function showEditDiscountFieldErrors(errors) {
        const form = document.getElementById('editDiscountForm');
        clearEditDiscountFieldErrors();
        let firstInvalid = null;
        Object.keys(errors).forEach(function (field) {
            const span = form.querySelector('#error-' + field);
            if (span) span.textContent = errors[field][0];
            const input = form.querySelector('[name="' + field + '"]');
            if (input) {
                input.classList.add('error');
                if (!firstInvalid) firstInvalid = input;
            }
        });
        if (firstInvalid) firstInvalid.focus();
    }

    function showEditDiscountGeneralError(message) {
        const banner = document.getElementById('editDiscountGeneralError');
        banner.textContent = message;
        banner.style.display = 'flex';
    }

    function hideEditDiscountGeneralError() {
        const banner = document.getElementById('editDiscountGeneralError');
        banner.style.display = 'none';
        banner.textContent = '';
    }

    function resetEditDiscountSubmitButton() {
        const btn = document.getElementById('editDiscountSubmitBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
    }

    function handleEditDiscountModalKeydown(e) {
        const modal = document.getElementById('editDiscountModal');
        if (!modal.classList.contains('active')) return;
        if (e.key === 'Escape') { if (!editDiscountIsSubmitting()) closeEditDiscountModal(); return; }
        if (e.key === 'Tab') {
            const focusable = modal.querySelectorAll('input, select, textarea, button, [href]');
            if (!focusable.length) return;
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    }

    window.openEditDiscountModal = function (event, discountId) {
        if (event) event.preventDefault();
        const modal = document.getElementById('editDiscountModal');
        const form = document.getElementById('editDiscountForm');

        editDiscountLastFocused = document.activeElement || editDiscountLastFocused;
        currentEditDiscountId = discountId;
        form.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>';
        hideEditDiscountGeneralError();
        resetEditDiscountSubmitButton();

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('active'); });
        document.addEventListener('keydown', handleEditDiscountModalKeydown);

        fetch('{{ url('admin/discounts') }}/' + discountId + '/edit', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                form.innerHTML = data.html;
                form.insertAdjacentHTML('beforeend', '<input type="hidden" name="_method" value="PUT">');
                window.initDiscountFormPrice(form);
                const firstField = form.querySelector('input, textarea, select');
                if (firstField) firstField.focus();
            })
            .catch(function () {
                form.innerHTML = '<p class="error">Failed to load promo code for editing. Please try again.</p>';
            });
    };

    window.closeEditDiscountModal = function () {
        const modal = document.getElementById('editDiscountModal');
        modal.classList.remove('active');
        document.removeEventListener('keydown', handleEditDiscountModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        if (editDiscountLastFocused && typeof editDiscountLastFocused.focus === 'function') editDiscountLastFocused.focus();
    };

    document.getElementById('editDiscountModal').addEventListener('mousedown', function (e) {
        if (e.target === this && !editDiscountIsSubmitting()) closeEditDiscountModal();
    });
    document.getElementById('editDiscountCancelBtn').addEventListener('click', function () { closeEditDiscountModal(); });

    document.getElementById('editDiscountSubmitBtn').addEventListener('click', function () {
        const form = document.getElementById('editDiscountForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        window.confirmAction({ title: 'Confirm Update', text: 'Save changes to this promo code?' }).then(function (result) {
            if (!result.isConfirmed) return;

            const submitBtn = document.getElementById('editDiscountSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            clearEditDiscountFieldErrors();
            hideEditDiscountGeneralError();

            window.submitAjaxForm(form, '{{ url('admin/discounts') }}/' + currentEditDiscountId, {
                onFieldErrors: function (errors) {
                    showEditDiscountFieldErrors(errors);
                    resetEditDiscountSubmitButton();
                },
                onSuccess: function (html, message) {
                    window.refreshDiscountsTable();
                    closeEditDiscountModal();
                    toastSuccess(message);
                },
                onOtherError: function (message) {
                    showEditDiscountGeneralError(message);
                    resetEditDiscountSubmitButton();
                }
            });
        });
    });
</script>
@endsection
