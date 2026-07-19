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
        grid-template-columns: repeat(5, 1fr);
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
    .recent-list {
        margin-bottom: 20px;
    }
    .recent-list-header {
        padding: 16px 24px;
        font-weight: 600;
        color: var(--text-muted);
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .recent-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 24px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.06);
        font-size: 0.9rem;
    }
    .recent-item:last-child { border-bottom: none; }
    .search-form {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        flex: 1;
    }
    .search-form input, .search-form select {
        padding: 12px 16px;
        background: rgba(15, 23, 42, 0.8);
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 10px;
        color: var(--text-primary);
        font-size: 0.95rem;
    }
    .search-form input[type="text"] {
        flex: 1;
        min-width: 180px;
        max-width: 320px;
    }
    .search-form input[type="date"] {
        color-scheme: dark;
    }
    .search-form input:focus, .search-form select:focus {
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
    .export-links {
        display: flex;
        gap: 8px;
        margin-left: auto;
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
        <div class="kpi-label">Pending Supplier Return</div>
        <div class="kpi-value">{{ number_format($kpis['pending_supplier_return']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Total Damage Cost</div>
        <div class="kpi-value">₱{{ number_format($kpis['total_cost'], 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Returned to Supplier</div>
        <div class="kpi-value">{{ number_format($kpis['returned_to_supplier']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Disposed Items</div>
        <div class="kpi-value">{{ number_format($kpis['disposed']) }}</div>
    </div>
</div>

<div class="card recent-list">
    <div class="recent-list-header">Recently Added</div>
    @forelse($recentlyAdded as $recent)
        <div class="recent-item">
            <span>{{ $recent->product?->ProductName ?? 'N/A' }} &mdash; {{ $recent->Quantity }} units</span>
            <span class="text-muted">{{ \Carbon\Carbon::parse($recent->DateRecorded)->format('M d, Y') }}</span>
        </div>
    @empty
        <div class="recent-item"><span class="text-muted">No damage records yet.</span></div>
    @endforelse
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="{{ route('admin.damages.index') }}" class="search-form">
            <input type="text" name="search" placeholder="Search products..." value="{{ $search ?? '' }}">
            <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}" title="From Date">
            <input type="date" name="date_to" value="{{ $dateTo ?? '' }}" title="To Date">
            <select name="status">
                <option value="">All Statuses</option>
                <option value="pending" {{ ($status ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="for_supplier_return" {{ ($status ?? '') === 'for_supplier_return' ? 'selected' : '' }}>Pending Supplier Return</option>
                <option value="returned_to_supplier" {{ ($status ?? '') === 'returned_to_supplier' ? 'selected' : '' }}>Returned to Supplier</option>
                <option value="replacement_received" {{ ($status ?? '') === 'replacement_received' ? 'selected' : '' }}>Replacement Received</option>
                <option value="disposed" {{ ($status ?? '') === 'disposed' ? 'selected' : '' }}>Disposed</option>
                <option value="cancelled" {{ ($status ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
            <select name="supplier_id">
                <option value="">All Suppliers</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->SupplierID }}" {{ ($supplierId ?? '') == $supplier->SupplierID ? 'selected' : '' }}>{{ $supplier->SupplierName }}</option>
                @endforeach
            </select>
            <button type="submit"><i class="fa-solid fa-search"></i></button>
            <div class="export-links">
                <a href="{{ route('admin.damages.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="btn btn-sm btn-secondary"><i class="fa-solid fa-file-csv"></i> CSV</a>
                <a href="{{ route('admin.damages.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="btn btn-sm btn-secondary"><i class="fa-solid fa-file-pdf"></i> PDF</a>
                <a href="{{ route('admin.damages.export', array_merge(request()->query(), ['format' => 'excel'])) }}" class="btn btn-sm btn-secondary"><i class="fa-solid fa-file-excel"></i> Excel</a>
                <button type="button" class="btn btn-sm btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
            </div>
        </form>
        <a href="{{ route('admin.damages.create') }}" class="btn btn-primary no-print" title="Record Damage" onclick="openDamageModal(event)">
            <i class="fa-solid fa-plus"></i> Record Damage
        </a>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Supplier</th>
                    <th>PO#</th>
                    <th>Quantity</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th class="no-print">Actions</th>
                </tr>
            </thead>
            <tbody id="damagesTbody">
                @forelse($damagedProducts as $damage)
                    <tr>
                        <td>{{ $damage->DamageID }}</td>
                        <td>{{ \Carbon\Carbon::parse($damage->DateRecorded)->format('M d, Y') }}</td>
                        <td>
                            <strong>{{ $damage->product->ProductName ?? 'N/A' }}</strong>
                            @if($damage->SalesReturnID)
                                <br><span class="badge badge-info">From Return #{{ $damage->SalesReturnID }}</span>
                            @endif
                        </td>
                        <td>{{ $damage->supplier->SupplierName ?? 'N/A' }}</td>
                        <td>{{ $damage->PurchaseOrderID ? '#' . $damage->PurchaseOrderID : '-' }}</td>
                        <td><span class="badge badge-danger">{{ $damage->Quantity }}</span></td>
                        <td class="description-cell">{{ \App\Models\DamagedProduct::DAMAGE_TYPES[$damage->DamageType] ?? $damage->DamageType }}</td>
                        <td>
                            @if($damage->Status === 'pending')
                                <span class="badge badge-warning">Pending</span>
                            @elseif($damage->Status === 'for_supplier_return')
                                <span class="badge badge-info">Pending Supplier Return</span>
                            @elseif($damage->Status === 'returned_to_supplier')
                                <span class="badge badge-success">Returned to Supplier</span>
                            @elseif($damage->Status === 'replacement_received')
                                <span class="badge badge-success">Replacement Received</span>
                            @elseif($damage->Status === 'cancelled')
                                <span class="badge badge-secondary">Cancelled</span>
                            @else
                                <span class="badge badge-secondary">Disposed</span>
                            @endif
                        </td>
                        <td class="no-print">
                            <div class="actions-group">
                                @if($damage->Status === 'pending')
                                    <a href="{{ route('admin.damages.edit', $damage->DamageID) }}" class="btn btn-sm btn-primary">
                                        <i class="fa-solid fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.damages.mark-supplier-return', $damage->DamageID) }}" onsubmit="return confirm('Mark this record for supplier return?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Mark for Supplier Return"><i class="fa-solid fa-truck"></i></button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.damages.dispose', $damage->DamageID) }}" onsubmit="return confirm('Mark this record as disposed?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Dispose"><i class="fa-solid fa-trash-can"></i></button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.damages.destroy', $damage->DamageID) }}" style="display:inline;" id="deleteForm{{ $damage->DamageID }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete({{ $damage->DamageID }})">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                @elseif($damage->Status === 'for_supplier_return')
                                    <form method="POST" action="{{ route('admin.damages.confirm-supplier-return', $damage->DamageID) }}" onsubmit="return confirm('Confirm this item was returned to the supplier?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Confirm Returned"><i class="fa-solid fa-check"></i> Confirm Returned</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.damages.cancel', $damage->DamageID) }}" onsubmit="return confirm('Cancel this supplier return and restore the quantity to inventory?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Cancel"><i class="fa-solid fa-rotate-left"></i> Cancel</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.damages.dispose', $damage->DamageID) }}" onsubmit="return confirm('Mark this record as disposed?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Dispose"><i class="fa-solid fa-trash-can"></i></button>
                                    </form>
                                @elseif($damage->Status === 'returned_to_supplier')
                                    <form method="POST" action="{{ route('admin.damages.receive-replacement', $damage->DamageID) }}" onsubmit="return confirm('Confirm the supplier sent a replacement and increase inventory?');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Receive Replacement"><i class="fa-solid fa-box"></i> Receive Replacement</button>
                                    </form>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fa-solid fa-box-open"></i></div>
                                <p class="empty-title">No Damage Records Found</p>
                                <p class="empty-text">Record your first damaged product to get started.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($damagedProducts->hasPages())
            <div class="pagination">
                @if($damagedProducts->onFirstPage())
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a href="{{ $damagedProducts->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                @endif

                @foreach($damagedProducts->getUrlRange(1, $damagedProducts->lastPage()) as $page => $url)
                    <a href="{{ $url }}" class="pagination-link {{ $page == $damagedProducts->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach

                @if($damagedProducts->hasMorePages())
                    <a href="{{ $damagedProducts->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>
</div>

<!-- Record Damage Modal -->
<div id="addDamageModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="addDamageModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="addDamageModalTitle"><i class="fa-solid fa-box-open"></i> Record Damage</h2>
            <button type="button" class="modal-close" onclick="closeDamageModal()" aria-label="Close">&times;</button>
        </div>

        <div id="addDamageGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="addDamageForm">
            @include('admin.damages.partials.damage-form-fields')
        </form>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="addDamageCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="addDamageSubmitBtn">
                <i class="fas fa-save"></i> Record Damage
            </button>
        </div>
    </div>
</div>

@include('admin.partials.ajax-modal-form')

<script>
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

    // ---- Record Damage modal ----
    const ADD_DAMAGE_FIELD_IDS = ['ProductID', 'SupplierID', 'PurchaseOrderID', 'Quantity', 'DateRecorded', 'DamageType', 'Description', 'InspectionNotes', 'WarehouseLocation', 'Remarks'];
    let addDamageLastFocused = null;

    function addDamageIsSubmitting() {
        const btn = document.getElementById('addDamageSubmitBtn');
        return btn ? btn.disabled : false;
    }

    function clearAddDamageFieldErrors() {
        const form = document.getElementById('addDamageForm');
        ADD_DAMAGE_FIELD_IDS.forEach(function (field) {
            const span = document.getElementById('error-' + field);
            if (span) span.textContent = '';
            const input = form.querySelector('[name="' + field + '"]');
            if (input) input.classList.remove('error');
        });
    }

    function showAddDamageFieldErrors(errors) {
        const form = document.getElementById('addDamageForm');
        clearAddDamageFieldErrors();
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

    function showAddDamageGeneralError(message) {
        const banner = document.getElementById('addDamageGeneralError');
        banner.textContent = message;
        banner.style.display = 'flex';
    }

    function hideAddDamageGeneralError() {
        const banner = document.getElementById('addDamageGeneralError');
        banner.style.display = 'none';
        banner.textContent = '';
    }

    function refreshDamagesTable(html) {
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const newTbody = parsed.querySelector('#damagesTbody');
        const currentTbody = document.getElementById('damagesTbody');
        if (newTbody && currentTbody) {
            currentTbody.innerHTML = newTbody.innerHTML;
        }
    }

    function resetAddDamageSubmitButton() {
        const btn = document.getElementById('addDamageSubmitBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Record Damage';
    }

    window.openDamageModal = function (event) {
        if (event) event.preventDefault();
        const modal = document.getElementById('addDamageModal');
        const form = document.getElementById('addDamageForm');

        addDamageLastFocused = document.activeElement;
        form.reset();
        clearAddDamageFieldErrors();
        hideAddDamageGeneralError();
        resetAddDamageSubmitButton();

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () {
            modal.classList.add('active');
        });

        const firstField = form.querySelector('input, textarea, select');
        if (firstField) firstField.focus();

        document.addEventListener('keydown', handleAddDamageModalKeydown);
    };

    window.closeDamageModal = function () {
        const modal = document.getElementById('addDamageModal');
        modal.classList.remove('active');
        document.removeEventListener('keydown', handleAddDamageModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        if (addDamageLastFocused && typeof addDamageLastFocused.focus === 'function') {
            addDamageLastFocused.focus();
        }
    };

    function handleAddDamageModalKeydown(e) {
        const modal = document.getElementById('addDamageModal');
        if (!modal.classList.contains('active')) return;

        if (e.key === 'Escape') {
            if (!addDamageIsSubmitting()) closeDamageModal();
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

    document.getElementById('addDamageModal').addEventListener('mousedown', function (e) {
        if (e.target === this && !addDamageIsSubmitting()) {
            closeDamageModal();
        }
    });

    document.getElementById('addDamageForm').addEventListener('submit', function (e) { e.preventDefault(); });

    document.getElementById('addDamageCancelBtn').addEventListener('click', function () {
        closeDamageModal();
    });

    document.getElementById('addDamageSubmitBtn').addEventListener('click', function () {
        const form = document.getElementById('addDamageForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Confirm Save',
            text: 'Are you sure you want to record this damaged product?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            const submitBtn = document.getElementById('addDamageSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            clearAddDamageFieldErrors();
            hideAddDamageGeneralError();

            window.submitAjaxForm(form, '{{ route('admin.damages.store') }}', {
                onFieldErrors: function (errors) {
                    showAddDamageFieldErrors(errors);
                    resetAddDamageSubmitButton();
                },
                onSuccess: function (html, message) {
                    refreshDamagesTable(html);
                    closeDamageModal();
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
                    showAddDamageGeneralError(message);
                    resetAddDamageSubmitButton();
                }
            });
        });
    });
</script>

<style media="print">
    .no-print, .search-form, .header-actions, .sidebar { display: none !important; }
</style>
@endsection
