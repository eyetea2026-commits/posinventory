@extends('admin.layout')

@section('title', 'Discounts - CCTV Express')

@section('header')
    <div class="header-title">
        <h1>Discount Policies</h1>
        <p>Manage discount rates applied at checkout</p>
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
    .btn-sm {
        padding: 6px 12px;
        font-size: 0.85rem;
        border-radius: 8px;
    }
    .btn-danger {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }
    .btn-danger:hover {
        background: rgba(239, 68, 68, 0.25);
    }
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
    .search-form {
        display: flex;
        gap: 12px;
        flex: 1;
        max-width: 480px;
    }
    .search-form input {
        flex: 1;
        max-width: 400px;
        padding: 12px 16px;
        background: rgba(15, 23, 42, 0.8);
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 10px;
        color: var(--text-primary);
        font-size: 0.95rem;
    }
    .search-form input:focus {
        outline: none;
        border-color: rgba(59, 130, 246, 0.5);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    .search-form button {
        padding: 12px 20px;
        background: linear-gradient(135deg, #3b82f6, #10b981);
        border: none;
        border-radius: 10px;
        color: white;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .search-form button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }
    .card-body {
        padding: 0;
    }
    .table {
        width: 100%;
        border-collapse: collapse;
    }
    .table th {
        background: rgba(15, 23, 42, 0.5);
        padding: 16px 20px;
        text-align: left;
        font-weight: 600;
        color: var(--text-muted);
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .table td {
        padding: 16px 20px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.08);
    }
    .table tbody tr {
        transition: all 0.2s ease;
    }
    .table tbody tr:hover {
        background: rgba(59, 130, 246, 0.05);
    }
    .actions-group {
        display: flex;
        gap: 8px;
    }
    .empty-state {
        padding: 60px 20px;
        text-align: center;
    }
    .empty-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        background: rgba(59, 130, 246, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: var(--primary);
    }
    .empty-title {
        font-size: 1.25rem;
        color: var(--text-primary);
        margin-bottom: 8px;
    }
    .empty-text {
        color: var(--text-muted);
        margin-bottom: 20px;
    }
    .alert {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .alert-success {
        background: rgba(16, 185, 129, 0.15);
        color: #6ee7b7;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }
    .alert-danger {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .badge-info {
        background: rgba(59, 130, 246, 0.15);
        color: #93c5fd;
    }
    .rate-cell {
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--text-primary);
    }

    /* Add Discount Modal */
    .form-group label { display: block; margin-bottom: 4px; font-weight: 600; color: #cbd5e1; font-size: 0.82rem; }
    .form-group label .required { color: #ef4444; }
    .form-group { margin-bottom: 16px; }
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
    .form-text { display: block; margin-top: 4px; color: #94a3b8; font-size: 0.75rem; }
    .error { display: block; margin-top: 3px; color: #fca5a5; font-size: 0.72rem; }

    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        z-index: 900;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
    }
    .modal-overlay.active { display: flex; }
    .modal-content {
        background: #0f172a;
        border: 1px solid #334155;
        border-radius: 20px;
        padding: 18px 22px;
        max-width: 600px;
        width: 92%;
        max-height: 88vh;
        overflow-y: auto;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        transform: scale(0.95) translateY(12px);
        opacity: 0;
        transition: transform 0.25s ease, opacity 0.25s ease;
    }
    .modal-overlay.active .modal-content { transform: scale(1) translateY(0); opacity: 1; }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #334155;
    }
    .modal-header h2 { margin: 0; font-size: 1.05rem; color: #f8fafc; }
    .modal-close {
        width: 32px; height: 32px;
        background: #1e293b;
        border: none;
        border-radius: 8px;
        color: #94a3b8;
        font-size: 1.2rem;
        cursor: pointer;
    }
    .modal-close:hover { background: #334155; color: #fff; }
    .modal-actions {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid #334155;
    }
    .modal-actions .btn { padding: 10px 18px; font-size: 0.85rem; }
    .form-error-banner {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(239, 68, 68, 0.12);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #fca5a5;
        padding: 10px 14px;
        border-radius: 10px;
        margin-bottom: 14px;
        font-size: 0.82rem;
    }
</style>

@if(session('success'))
    <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i>
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation"></i>
        {{ session('error') }}
    </div>
@endif

<div class="card">
    <div class="card-header">
        <form method="GET" action="{{ route('admin.discounts.index') }}" class="search-form">
            <input type="text" name="search" placeholder="Search discount rates..." value="{{ $search ?? '' }}">
            <button type="submit"><i class="fa-solid fa-search"></i></button>
        </form>
        <a href="{{ route('admin.discounts.create') }}" class="btn btn-primary" onclick="openAddDiscountModal(event)" title="Add Discount">
            <i class="fa-solid fa-plus"></i> Add Discount
        </a>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Discount Rate</th>
                    <th>Used In Transactions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="discountsTbody">
                @forelse($discounts as $discount)
                    <tr>
                        <td>{{ $discount->DiscountID }}</td>
                        <td class="rate-cell">{{ $discount->DiscountRate }}%</td>
                        <td>
                            <span class="badge badge-info">{{ $discount->billings->count() }} transactions</span>
                        </td>
                        <td>
                            <div class="actions-group">
                                <a href="{{ route('admin.discounts.edit', $discount->DiscountID) }}" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-edit"></i> Edit
                                </a>
                                <form method="POST" action="{{ route('admin.discounts.destroy', $discount->DiscountID) }}" style="display:inline;" id="deleteForm{{ $discount->DiscountID }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete({{ $discount->DiscountID }})">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fa-solid fa-percent"></i></div>
                                <p class="empty-title">No Discount Policies Found</p>
                                <p class="empty-text">Create your first discount policy to get started.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Add Discount Modal -->
<div id="addDiscountModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="addDiscountModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="addDiscountModalTitle"><i class="fa-solid fa-percent"></i> Add New Discount</h2>
            <button type="button" class="modal-close" onclick="closeAddDiscountModal()" aria-label="Close">&times;</button>
        </div>

        <div id="addDiscountGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="addDiscountForm">
            @include('admin.discounts.partials.discount-form-fields')
        </form>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="addDiscountCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="addDiscountSubmitBtn">
                <i class="fas fa-save"></i> Save Discount
            </button>
        </div>
    </div>
</div>

@include('admin.partials.ajax-modal-form')

<script>
    function confirmDelete(discountId) {
        Swal.fire({
            title: 'Confirm Delete',
            text: 'Are you sure you want to delete this discount policy? This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm' + discountId).submit();
            }
        });
    }

    // Auto-show session messages
    @if(session('success'))
        Swal.fire({
            title: 'Success',
            text: '{{ session('success') }}',
            icon: 'success',
            confirmButtonColor: '#10b981',
            timer: 3000,
            timerProgressBar: true
        });
    @endif
    @if(session('error'))
        Swal.fire({
            title: 'Error',
            text: '{{ session('error') }}',
            icon: 'error',
            confirmButtonColor: '#ef4444'
        });
    @endif

    // ---- Add Discount modal ----
    const ADD_DISCOUNT_FIELD_IDS = ['DiscountRate'];
    let addDiscountLastFocused = null;

    function addDiscountIsSubmitting() {
        const btn = document.getElementById('addDiscountSubmitBtn');
        return btn ? btn.disabled : false;
    }

    function clearAddDiscountFieldErrors() {
        const form = document.getElementById('addDiscountForm');
        ADD_DISCOUNT_FIELD_IDS.forEach(function (field) {
            const span = document.getElementById('error-' + field);
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

    function refreshDiscountsTable(html) {
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const newTbody = parsed.querySelector('#discountsTbody');
        const currentTbody = document.getElementById('discountsTbody');
        if (newTbody && currentTbody) {
            currentTbody.innerHTML = newTbody.innerHTML;
        }
    }

    function resetAddDiscountSubmitButton() {
        const btn = document.getElementById('addDiscountSubmitBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Discount';
    }

    window.openAddDiscountModal = function (event) {
        if (event) event.preventDefault();
        const modal = document.getElementById('addDiscountModal');
        const form = document.getElementById('addDiscountForm');

        addDiscountLastFocused = document.activeElement;
        form.reset();
        clearAddDiscountFieldErrors();
        hideAddDiscountGeneralError();
        resetAddDiscountSubmitButton();

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () {
            modal.classList.add('active');
        });

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
        if (addDiscountLastFocused && typeof addDiscountLastFocused.focus === 'function') {
            addDiscountLastFocused.focus();
        }
    };

    function handleAddDiscountModalKeydown(e) {
        const modal = document.getElementById('addDiscountModal');
        if (!modal.classList.contains('active')) return;

        if (e.key === 'Escape') {
            if (!addDiscountIsSubmitting()) closeAddDiscountModal();
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

    document.getElementById('addDiscountModal').addEventListener('mousedown', function (e) {
        if (e.target === this && !addDiscountIsSubmitting()) {
            closeAddDiscountModal();
        }
    });

    document.getElementById('addDiscountForm').addEventListener('submit', function (e) { e.preventDefault(); });

    document.getElementById('addDiscountCancelBtn').addEventListener('click', function () {
        closeAddDiscountModal();
    });

    document.getElementById('addDiscountSubmitBtn').addEventListener('click', function () {
        const form = document.getElementById('addDiscountForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Confirm Save',
            text: 'Are you sure you want to create this discount policy?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        }).then(function (result) {
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
                    refreshDiscountsTable(html);
                    closeAddDiscountModal();
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
                    showAddDiscountGeneralError(message);
                    resetAddDiscountSubmitButton();
                }
            });
        });
    });
</script>
@endsection
