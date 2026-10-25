@extends('admin.layout')

@section('title', 'Damage Records - CCTV Express')

@section('header')
    <div class="header-title">
        <p style="margin: 0 0 4px; font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em;">Return / Damage</p>
        <h1>Damage</h1>
        <p>Track products damaged in transit or storage</p>
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
    .btn-secondary {
        background: rgba(148, 163, 184, 0.15);
        color: #cbd5e1;
        border: 1px solid rgba(148, 163, 184, 0.2);
    }
    .btn-secondary:hover {
        background: rgba(148, 163, 184, 0.25);
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
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 16px;
        padding: 20px 24px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.1);
    }
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }
    .kpi-card {
        background: rgba(10, 18, 35, 0.8);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(148, 163, 184, 0.1);
        border-radius: 16px;
        padding: 18px 20px;
    }
    .kpi-label {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin-bottom: 8px;
    }
    .kpi-value {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-primary);
    }
    .search-form {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        flex: 1;
        align-items: center;
    }
    .search-box-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        flex: 1;
        min-width: 220px;
        max-width: 380px;
        background: rgba(15, 23, 42, 0.8);
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 10px;
        transition: all 0.2s ease;
    }
    .search-box-wrapper:focus-within {
        border-color: rgba(59, 130, 246, 0.5);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    .search-box-wrapper .search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.9rem;
        pointer-events: none;
    }
    .search-input {
        flex: 1;
        width: 100%;
        padding: 12px 14px 12px 40px;
        background: transparent;
        border: none;
        color: var(--text-primary);
        font-size: 0.95rem;
    }
    .search-input::placeholder { color: #64748b; }
    .search-input:focus { outline: none; }
    .search-form select {
        padding: 12px 16px;
        background: rgba(15, 23, 42, 0.8);
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 10px;
        color: var(--text-primary);
        font-size: 0.95rem;
    }
    .search-form select:focus {
        outline: none;
        border-color: rgba(59, 130, 246, 0.5);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
    .table tbody tr:hover {
        background: rgba(59, 130, 246, 0.05);
    }
    .actions-group {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .description-cell {
        color: var(--text-muted);
        font-size: 0.9rem;
    }
    .empty-state {
        padding: 60px 20px;
        text-align: center;
    }
    .empty-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        background: rgba(239, 68, 68, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: #fca5a5;
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
    .badge-danger { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
    .badge-warning { background: rgba(251, 191, 36, 0.15); color: #fcd34d; }
    .badge-info { background: rgba(56, 189, 248, 0.15); color: #67e8f9; }
    .badge-success { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; }
    .badge-secondary { background: rgba(148, 163, 184, 0.15); color: #cbd5e1; }
    .pagination { display: flex; gap: 6px; justify-content: center; padding: 20px; }
    .pagination-link { padding: 8px 14px; border-radius: 8px; background: rgba(148,163,184,0.1); color: var(--text-primary); text-decoration: none; }
    .pagination-link.active { background: linear-gradient(135deg, #3b82f6, #10b981); color: white; }
    .pagination-link.disabled { opacity: 0.4; }

    @media (max-width: 1100px) {
        .kpi-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>
@include('admin.partials.modal-styles')

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

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Total Damage Records</div>
        <div class="kpi-value">{{ number_format($kpis['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Total Damage Cost</div>
        <div class="kpi-value">₱{{ number_format($kpis['total_cost'], 2) }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="{{ route('admin.damages.index') }}" id="damageFilterForm" class="search-form" autocomplete="off" data-live-search>
            <div class="search-box-wrapper">
                <i class="search-icon fas fa-search"></i>
                <input type="text" name="search" id="damageSearchInput" class="search-input" placeholder="Search products..." value="{{ $search ?? '' }}">
            </div>
            <select name="supplier_id" id="damageSupplierInput">
                <option value="">All Suppliers</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->SupplierID }}" {{ ($supplierId ?? '') == $supplier->SupplierID ? 'selected' : '' }}>{{ $supplier->SupplierName }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Supplier</th>
                    <th>Quantity</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="damagesTbody" data-live-region="damages-rows" data-live-topics="damages">
                @include('admin.damages.partials.rows', ['damagedProducts' => $damagedProducts])
            </tbody>
        </table>

        <div id="damagePaginationWrapper" data-live-region="damages-pagination" data-live-topics="damages">
            @include('admin.damages.partials.pagination', ['damagedProducts' => $damagedProducts])
        </div>
    </div>
</div>

<!-- Edit Damage Modal -->
<div id="editDamageModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editDamageModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="editDamageModalTitle"><i class="fa-solid fa-edit"></i> Edit Damage Record</h2>
            <button type="button" class="modal-close" onclick="closeEditDamageModal()" aria-label="Close">&times;</button>
        </div>

        <div id="editDamageGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="editDamageForm">
            <div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>
        </form>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="editDamageCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="editDamageSubmitBtn">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>

{{-- View Damage Details Modal --}}
<div id="viewDamageModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="viewDamageModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="viewDamageModalTitle"><i class="fa-solid fa-circle-info"></i> Damage Record Details</h2>
            <button type="button" class="modal-close" onclick="closeViewDamageModal()" aria-label="Close">&times;</button>
        </div>
        <div id="viewDamageBody">
            <p class="text-muted">Loading...</p>
        </div>
        <div class="modal-actions">
            <a id="printDamageRecordBtn" href="#" target="_blank" class="btn btn-secondary" style="display:none;">
                <i class="fa-solid fa-print"></i> Print Damage Record
            </a>
            <button type="button" class="btn btn-secondary" onclick="closeViewDamageModal()">Close</button>
        </div>
    </div>
</div>

@include('admin.partials.ajax-modal-form')

<script>
    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function viewDamageDetails(damageId) {
        const modal = document.getElementById('viewDamageModal');
        const body = document.getElementById('viewDamageBody');
        const printBtn = document.getElementById('printDamageRecordBtn');
        body.innerHTML = '<p class="text-muted">Loading...</p>';
        printBtn.style.display = 'none';
        modal.classList.add('active');
        modal.style.display = 'flex';

        fetch(`/admin/damages/${damageId}`, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                const d = data.damage, p = data.product, s = data.supplier, r = data.requestedBy, a = data.approvedBy;
                // Sections of label/value cards, three per row (shared .ui-kv-grid).
                const kv = (label, value, wide) => `<div${wide ? ' class="ui-kv-wide"' : ''}><span>${escapeHtml(label)}</span><span>${value}</span></div>`;
                const text = value => escapeHtml(value ?? 'N/A');
                const section = (title, items) => `<h4 class="ui-kv-title">${escapeHtml(title)}</h4><div class="ui-kv-grid">${items.join('')}</div>`;
                let html = '';
                html += section('Damage Record', [
                    kv('Damage Record Number', text(d.DamageNumber)),
                    kv('Current Status', text(d.Status)),
                    kv('Supplier Return Status', text(data.supplierReturnStatus)),
                    kv('Quantity Damaged', text(d.Quantity)),
                    kv('Damage Type', text(d.DamageType)),
                    kv('Source', text(d.SourceModule)),
                    kv('Date Recorded', text(d.DateRecorded)),
                    ...(d.ResolvedBy ? [kv('Resolved By', `${text(d.ResolvedBy)} on ${text(d.ResolvedDate)}`)] : []),
                ]);
                html += section('Product Information', [
                    kv('Product', text(p.ProductName)),
                    kv('Brand', text(p.Brand)),
                    kv('Category', text(p.Category)),
                    kv('Cost Price', window.formatPeso ? window.formatPeso(p.CostPrice ?? 0) : text(p.CostPrice)),
                    kv('Current Stock', text(p.CurrentStock ?? 0)),
                ]);
                const people = [];
                if (r) {
                    people.push(kv('Return Processed By', text(r.Name)), kv('Employee ID', text(r.EmployeeID)), kv('Role', text(r.Role)), kv('Request Date', text(r.RequestDate)));
                }
                if (a) people.push(kv('Approved By', text(a.Name)));
                if (people.length) html += section('People', people);
                const references = [];
                if (data.salesReturn) {
                    references.push(kv('Return', `#${text(data.salesReturn.SalesReturnID)} — Receipt ${text(data.salesReturn.ReceiptNumber)}`), kv('Reason for Return', text(data.salesReturn.Reason)));
                }
                if (s) references.push(kv('Supplier', text(s.SupplierName)), kv('Supplier Contact', `${text(s.ContactNumber)}${s.Email ? ' / ' + text(s.Email) : ''}`));
                if (data.purchaseOrder) references.push(kv('Purchase Order', text(data.purchaseOrder.PONumber)));
                if (data.stockAdjustment) {
                    references.push(kv('Stock Adjustment', `#${text(data.stockAdjustment.AdjustmentID)} — ${text(data.stockAdjustment.QuantityAdjust)} (${text(data.stockAdjustment.Reason)})`));
                }
                if (references.length) html += section('Supplier & References', references);
                const details = [kv('Description', text(d.Description), true)];
                if (d.InspectionNotes) details.push(kv('Inspection Notes', text(d.InspectionNotes)));
                if (d.WarehouseLocation) details.push(kv('Warehouse Location', text(d.WarehouseLocation)));
                if (d.Remarks) details.push(kv('Remarks', text(d.Remarks)));
                if (d.ImageUrl) details.push(kv('Photo', `<img src="${escapeHtml(d.ImageUrl)}" style="max-width:100%; border-radius:8px;" alt="Damage photo">`, true));
                html += section('Details', details);

                html += '<h4 class="ui-kv-title">Audit History</h4>';
                if (data.auditHistory && data.auditHistory.length) {
                    data.auditHistory.forEach(function (entry) {
                        html += `<p style="font-size:0.85rem; color:#94a3b8; margin:0 0 4px;">${escapeHtml(entry.DateRecorded)} — ${escapeHtml(entry.Description)}</p>`;
                    });
                } else {
                    html += '<p class="text-muted" style="font-size:0.85rem;">No audit history recorded.</p>';
                }

                body.innerHTML = html;
                printBtn.href = `/admin/damages/${damageId}/print`;
                printBtn.style.display = '';
            })
            .catch(() => { body.innerHTML = '<p class="text-muted">Failed to load details.</p>'; });
    }

    function closeViewDamageModal() {
        const modal = document.getElementById('viewDamageModal');
        modal.classList.remove('active');
        modal.style.display = 'none';
    }

    function confirmDelete(damageId) {
        Swal.fire({
            title: 'Confirm Delete',
            text: 'Are you sure you want to delete this damage record? This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm' + damageId).submit();
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

    // ---- Edit Damage modal ----
    const EDIT_DAMAGE_FIELD_IDS = ['ProductID', 'SupplierID', 'PurchaseOrderID', 'Quantity', 'DateRecorded', 'DamageType', 'Description', 'InspectionNotes', 'WarehouseLocation', 'Remarks'];
    let editDamageLastFocused = null;
    let currentEditDamageId = null;

    function editDamageIsSubmitting() {
        const btn = document.getElementById('editDamageSubmitBtn');
        return btn ? btn.disabled : false;
    }

    function clearEditDamageFieldErrors() {
        const form = document.getElementById('editDamageForm');
        EDIT_DAMAGE_FIELD_IDS.forEach(function (field) {
            const span = document.getElementById('error-' + field);
            if (span) span.textContent = '';
            const input = form.querySelector('[name="' + field + '"]');
            if (input) input.classList.remove('error');
        });
    }

    function showEditDamageFieldErrors(errors) {
        const form = document.getElementById('editDamageForm');
        clearEditDamageFieldErrors();
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

    function showEditDamageGeneralError(message) {
        const banner = document.getElementById('editDamageGeneralError');
        banner.textContent = message;
        banner.style.display = 'flex';
    }

    function hideEditDamageGeneralError() {
        const banner = document.getElementById('editDamageGeneralError');
        banner.style.display = 'none';
        banner.textContent = '';
    }

    function resetEditDamageSubmitButton() {
        const btn = document.getElementById('editDamageSubmitBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
    }

    function handleEditDamageModalKeydown(e) {
        const modal = document.getElementById('editDamageModal');
        if (!modal.classList.contains('active')) return;

        if (e.key === 'Escape') {
            if (!editDamageIsSubmitting()) closeEditDamageModal();
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

    window.openEditDamageModal = function (event, damageId) {
        if (event) event.preventDefault();
        const modal = document.getElementById('editDamageModal');
        const form = document.getElementById('editDamageForm');

        editDamageLastFocused = document.activeElement || editDamageLastFocused;
        currentEditDamageId = damageId;
        form.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>';
        hideEditDamageGeneralError();
        resetEditDamageSubmitButton();

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('active'); });
        document.addEventListener('keydown', handleEditDamageModalKeydown);

        fetch('{{ url('admin/damages') }}/' + damageId + '/edit', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(async function (r) {
                if (!r.ok) {
                    const data = await r.json().catch(function () { return {}; });
                    throw new Error(data.error || 'This record can no longer be edited.');
                }
                return r.json();
            })
            .then(function (data) {
                form.innerHTML = data.html;
                form.insertAdjacentHTML('beforeend', '<input type="hidden" name="_method" value="PUT">');
                const firstField = form.querySelector('input, textarea, select');
                if (firstField) firstField.focus();
            })
            .catch(function (err) {
                closeEditDamageModal();
                Swal.fire({
                    title: 'Cannot Edit',
                    text: err.message || 'Failed to load damage record for editing. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            });
    };

    window.closeEditDamageModal = function () {
        const modal = document.getElementById('editDamageModal');
        modal.classList.remove('active');
        document.removeEventListener('keydown', handleEditDamageModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        if (editDamageLastFocused && typeof editDamageLastFocused.focus === 'function') {
            editDamageLastFocused.focus();
        }
    };

    document.getElementById('editDamageModal').addEventListener('mousedown', function (e) {
        if (e.target === this && !editDamageIsSubmitting()) {
            closeEditDamageModal();
        }
    });

    document.getElementById('editDamageCancelBtn').addEventListener('click', function () {
        closeEditDamageModal();
    });

    document.getElementById('editDamageSubmitBtn').addEventListener('click', function () {
        const form = document.getElementById('editDamageForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Confirm Update',
            text: 'Are you sure you want to save the changes to this damage record?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            const submitBtn = document.getElementById('editDamageSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            clearEditDamageFieldErrors();
            hideEditDamageGeneralError();

            window.submitAjaxForm(form, '{{ url('admin/damages') }}/' + currentEditDamageId, {
                onFieldErrors: function (errors) {
                    showEditDamageFieldErrors(errors);
                    resetEditDamageSubmitButton();
                },
                onSuccess: function (html, message) {
                    refreshDamagesTable(html);
                    closeEditDamageModal();
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
                    showEditDamageGeneralError(message);
                    resetEditDamageSubmitButton();
                }
            });
        });
    });
</script>
@endsection
