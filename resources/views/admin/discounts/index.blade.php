@extends('admin.layout')

@section('title', 'Discounts & Promos - CCTV Express')

@section('header')
    <div class="header-title">
        <h1>Discounts &amp; Promos</h1>
        <p>Create promo codes, then apply them to specific products</p>
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
    .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
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
        min-width: 220px;
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
    .pagination-link { padding: 8px 14px; border-radius: 8px; background: rgba(148,163,184,0.1); color: var(--text-primary); text-decoration: none; cursor: pointer; }
    .pagination-link.active { background: linear-gradient(135deg, #3b82f6, #10b981); color: white; }
    .pagination-link.disabled { opacity: 0.4; pointer-events: none; }

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
    .modal-content.modal-content-wide { max-width: 780px; }
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

    /* Tabs */
    .tabs-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
    .chart-toggle-group { display: inline-flex; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(148, 163, 184, 0.15); border-radius: 12px; padding: 4px; gap: 4px; }
    .chart-toggle-btn {
        padding: 9px 18px; border-radius: 9px; border: none; background: transparent; color: #94a3b8;
        font-weight: 600; font-size: 0.88rem; cursor: pointer; transition: all 0.2s ease;
    }
    .chart-toggle-btn.active { background: linear-gradient(135deg, #3b82f6, #10b981); color: white; }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* Apply tab — a small "Select a Promo Discount" combo box card sits
       beside the (much wider) Applied Discount/Promo List; picking a
       promo opens the Apply Discount/Promo popup for product selection. */
    .apply-tab-layout { display: grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: stretch; }
    @media (max-width: 900px) { .apply-tab-layout { grid-template-columns: 1fr; } }
    .apply-picker-body { padding: 16px 18px; }
    .picker-label {
        display: block; font-size: 0.78rem; color: #94a3b8; text-transform: uppercase;
        letter-spacing: 0.05em; margin-bottom: 6px;
    }

    /* Reused by the View Details modal's Promo Details section. */
    .apply-promo-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; }
    .apply-promo-detail-grid .detail-mini label {
        display: block; font-size: 0.68rem; color: #94a3b8; text-transform: uppercase;
        letter-spacing: 0.05em; margin-bottom: 3px;
    }
    .apply-promo-detail-grid .detail-mini span { font-weight: 600; color: #e2e8f0; font-size: 0.88rem; }

    /* Apply Discount/Promo popup — wide/tall card, checkbox list for
       selecting several products in one action. */
    .modal-content-tall { max-height: 92vh; }
    .modal-selected-count { margin: 8px 0 4px; font-size: 0.8rem; color: #94a3b8; }
    .modal-product-list {
        max-height: 420px; overflow-y: auto;
        border: 1px solid rgba(148, 163, 184, 0.12); border-radius: 12px;
    }
    .modal-product-row {
        display: flex; align-items: center; gap: 12px; padding: 11px 14px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.08); cursor: pointer; margin: 0;
    }
    .modal-product-row:last-child { border-bottom: none; }
    .modal-product-row:hover { background: rgba(59, 130, 246, 0.06); }
    .modal-product-row.is-checked { background: rgba(16, 185, 129, 0.08); }
    .modal-product-row.is-disabled { opacity: 0.55; cursor: default; }
    .modal-product-row input[type="checkbox"] { width: 17px; height: 17px; cursor: pointer; flex-shrink: 0; }
    .modal-product-row .mp-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1; }
    .modal-product-row .mp-name {
        font-weight: 600; color: #f1f5f9; font-size: 0.92rem;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .modal-product-row .mp-meta {
        font-size: 0.79rem; color: #94a3b8;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .modal-product-row .mp-price { font-weight: 600; color: #e2e8f0; font-size: 0.87rem; flex-shrink: 0; }
    .product-picker-empty { padding: 14px; color: #94a3b8; font-size: 0.85rem; text-align: center; }

    /* Cleaner dot-prefixed status badge, scoped to the Apply tab only. */
    .badge-dot::before { content: '●'; font-size: 0.7em; margin-right: 5px; }

    /* Promo Details ("View Details") modal section headers. */
    .section-title {
        color: #cbd5e1; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
        margin: 0 0 10px;
    }

    /* History modal */
    .history-tabs { display: flex; gap: 8px; margin-bottom: 16px; }
    .history-tab-btn {
        padding: 8px 16px; border-radius: 9px; border: 1px solid rgba(148, 163, 184, 0.15);
        background: rgba(15, 23, 42, 0.6); color: #94a3b8; font-weight: 600; font-size: 0.85rem; cursor: pointer;
    }
    .history-tab-btn.active { background: linear-gradient(135deg, #3b82f6, #10b981); color: white; border-color: transparent; }
    .history-panel { display: none; }
    .history-panel.active { display: block; }
</style>

@if(session('success'))
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}</div>
@endif

<div class="tabs-header">
    <div class="chart-toggle-group">
        <button type="button" class="chart-toggle-btn active" data-tab="create" onclick="switchDiscountTab('create')">
            <i class="fa-solid fa-plus"></i> Create Discount/Promo
        </button>
        <button type="button" class="chart-toggle-btn" data-tab="apply" onclick="switchDiscountTab('apply')">
            <i class="fa-solid fa-tags"></i> Apply Discount/Promo
        </button>
    </div>
    <button type="button" class="btn btn-secondary" onclick="openHistoryModal()">
        <i class="fa-solid fa-clock-rotate-left"></i> History
    </button>
</div>

{{-- ===================== TAB 1 — CREATE ===================== --}}
<div id="tab-create" class="tab-panel active">
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
                            <th>Name</th>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Assigned Products</th>
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
</div>

{{-- ===================== TAB 2 — APPLY ===================== --}}
<div id="tab-apply" class="tab-panel">
    <div class="apply-tab-layout">
        <div class="card">
            <div class="card-header"><h3 style="margin:0; font-size:0.95rem; color:#f8fafc;">Select a Promo Discount</h3></div>
            <div class="apply-picker-body">
                <label class="picker-label">Choose Promo Discount</label>
                <select id="applyPromoSelect" class="form-control" onchange="window.onApplyPromoChange()">
                    <option value="">Choose a promo…</option>
                    @foreach($allDiscounts as $d)
                        <option value="{{ $d->DiscountID }}">{{ $d->Name }} ({{ $d->PromoCode }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 style="margin:0; font-size:1.1rem; color:#f8fafc;">Applied Discount/Promo List</h3></div>
            <div class="card-body">
                <div style="overflow-x:auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Product SKU</th>
                                <th>Applied Discount/Promo</th>
                                <th>Discount Type</th>
                                <th>Discount Value</th>
                                <th>Start Date</th>
                                <th>Expiration Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="appliedAssignmentsTbody">
                            @include('admin.discounts.partials.applied-rows', ['appliedAssignments' => $appliedAssignments])
                        </tbody>
                    </table>
                </div>
                <div id="appliedPaginationWrapper">
                    @include('admin.discounts.partials.pagination', ['discounts' => $appliedAssignments])
                </div>
            </div>
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
            @include('admin.discounts.partials.discount-form-fields', ['discount' => null])
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

<!-- Apply Discount/Promo popup (checkbox multi-select product picker) -->
<div id="applyProductsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="applyProductsModalTitle" aria-hidden="true">
    <div class="modal-content modal-content-wide modal-content-tall">
        <div class="modal-header">
            <h2 id="applyProductsModalTitle"><i class="fa-solid fa-tags"></i> Apply Discount/Promo</h2>
            <button type="button" class="modal-close" onclick="closeApplyModal()" aria-label="Close">&times;</button>
        </div>

        <div class="form-group">
            <label>Promo</label>
            <div id="applyModalPromoLine" style="font-weight:700; color:#f8fafc; padding:6px 0 2px;"></div>
        </div>

        <div class="form-group">
            <label class="picker-label">Products</label>
            <input type="text" id="productPickerInput" class="form-control" placeholder="Search product by name or SKU…" autocomplete="off">
            <div id="modalSelectedCount" class="modal-selected-count"></div>
            <div class="modal-product-list" id="modalProductList"></div>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeApplyModal()">Cancel</button>
            <button type="button" id="assignSelectedBtn" class="btn btn-primary" disabled onclick="window.assignSelectedProducts()">
                <i class="fa-solid fa-check"></i> Apply Discount/Promo
            </button>
        </div>
    </div>
</div>

<!-- History Modal -->
<div id="historyModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="historyModalTitle" aria-hidden="true">
    <div class="modal-content modal-content-wide">
        <div class="modal-header">
            <h2 id="historyModalTitle"><i class="fa-solid fa-clock-rotate-left"></i> Discount/Promo History</h2>
            <button type="button" class="modal-close" onclick="closeHistoryModal()" aria-label="Close">&times;</button>
        </div>

        <div class="history-tabs">
            <button type="button" class="history-tab-btn active" data-history-tab="expired" onclick="switchHistoryTab('expired')">Expired</button>
            <button type="button" class="history-tab-btn" data-history-tab="used" onclick="switchHistoryTab('used')">Used</button>
        </div>

        <div id="historyPanelExpired" class="history-panel active">
            <div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
        <div id="historyPanelUsed" class="history-panel">
            <div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
    </div>
</div>

<!-- Promo Details Modal (Apply tab's "View Details") -->
<div id="promoDetailsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="promoDetailsModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="promoDetailsModalTitle"><i class="fa-solid fa-tags"></i> Promo Details</h2>
            <button type="button" class="modal-close" onclick="closePromoDetails()" aria-label="Close">&times;</button>
        </div>
        <div id="promoDetailsBody"></div>
        <div class="modal-actions" style="justify-content:flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closePromoDetails()">Close</button>
        </div>
    </div>
</div>

@include('admin.partials.ajax-modal-form')

<script>
    // Auto-show session messages — rendered as a literal Swal.fire({title:
    // 'Success'|'Error', text: '...'}) call, not the toastSuccess()/
    // toastError() helpers, because submitAjaxForm() (ajax-modal-form.blade.php)
    // scrapes the redirected page's HTML for exactly this literal pattern to
    // tell a successful save from a failed one — same convention as
    // Category/Damage/Product. Using the helpers here (which pass the
    // message through as a JS variable, not a literal string) made every
    // save on this page silently fall through to submitAjaxForm's generic
    // "Unable to save" message regardless of whether it actually succeeded.
    @if(session('success'))
        Swal.fire({
            title: 'Success',
            text: '{{ session('success') }}',
            icon: 'success',
            confirmButtonColor: '#10b981',
            timer: 2500,
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

    // ---- Tabs ----
    window.switchDiscountTab = function (tab) {
        document.querySelectorAll('.chart-toggle-btn[data-tab]').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });
        document.getElementById('tab-create').classList.toggle('active', tab === 'create');
        document.getElementById('tab-apply').classList.toggle('active', tab === 'apply');
    };

    // ---- Live search (Tab 1 promo list) ----
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
                if (window.updateApplyTabData) window.updateApplyTabData(data);
            } catch (err) {
                if (err.name === 'AbortError') return;
                tbody.innerHTML = `<tr><td colspan="9"><div class="empty-state"><div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div><p class="empty-title">Unable to load promo codes</p><p class="empty-text">Please try again.</p></div></td></tr>`;
                paginationWrapper.innerHTML = '';
            }
        }

        function rebindPagination() {
            paginationWrapper.querySelectorAll('a.pagination-link').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    applyFilters(this.dataset.page || 1);
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

    // ---- Tab 2: Apply Discount/Promo ----
    (function () {
        {{-- {id, name, sku, category} objects per promo — the source of
             truth for "already assigned" (excluded from the picker) and for
             the View Details modal's Applied Products table. Built in
             DiscountController::index() rather than inline here — see that
             method's comment for why. `let`, not `const`: refreshed in
             place by updateApplyTabData() whenever Tab 1's Add/Edit popup
             creates or edits a promo, so nothing here goes stale without a
             page reload. --}}
        let DISCOUNT_PRODUCT_MAP = @json($discountProductMap);

        {{-- Everything the View Details modal needs to render without a
             second request. --}}
        let DISCOUNT_META = @json($discountMeta);

        const promoSelect = document.getElementById('applyPromoSelect');
        const pickerInput = document.getElementById('productPickerInput');
        const selectedCountEl = document.getElementById('modalSelectedCount');
        const modalProductList = document.getElementById('modalProductList');
        const assignBtn = document.getElementById('assignSelectedBtn');
        const appliedTbody = document.getElementById('appliedAssignmentsTbody');
        const appliedPaginationWrapper = document.getElementById('appliedPaginationWrapper');

        let selectedProducts = []; // checked products: [{id, name, sku, category}]
        let searchDebounce = null;
        let searchController = null;
        let lastResults = [];

        function currentPromoId() { return promoSelect.value; }

        function escapeHtmlLocal(str) {
            return String(str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function assignedIdsFor(promoId) {
            return (promoId && DISCOUNT_PRODUCT_MAP[promoId]) ? DISCOUNT_PRODUCT_MAP[promoId].map(function (p) { return String(p.id); }) : [];
        }

        // Called after Tab 1's promo table refreshes (including right after
        // the Add/Edit popup saves) so a brand-new promo appears in the
        // "Choose Promo Discount" dropdown, and an edited one's name/code/
        // dates/status update everywhere they're shown — without a full
        // page reload.
        window.updateApplyTabData = function (data) {
            if (!data || !data.allDiscounts) return;

            DISCOUNT_META = data.discountMeta || {};
            DISCOUNT_PRODUCT_MAP = data.discountProductMap || {};

            const previouslySelected = promoSelect.value;
            const stillExists = data.allDiscounts.some(function (d) { return String(d.id) === String(previouslySelected); });

            promoSelect.innerHTML = '<option value="">Choose a promo…</option>' + data.allDiscounts.map(function (d) {
                return '<option value="' + d.id + '">' + escapeHtmlLocal(d.name) + ' (' + escapeHtmlLocal(d.code) + ')</option>';
            }).join('');
            promoSelect.value = stillExists ? previouslySelected : '';
        };

        function updateSelectedCount() {
            selectedCountEl.textContent = selectedProducts.length
                ? selectedProducts.length + ' product' + (selectedProducts.length === 1 ? '' : 's') + ' selected'
                : '';
        }

        function updateAssignButtonState() {
            assignBtn.disabled = !(currentPromoId() && selectedProducts.length > 0);
        }

        // Choosing a promo from the small "Select a Promo Discount" combo
        // box opens the Apply Discount/Promo popup — product selection
        // never sits permanently on the main page.
        window.onApplyPromoChange = function () {
            if (currentPromoId()) window.openApplyModal();
        };

        window.openApplyModal = function () {
            const id = currentPromoId();
            if (!id) return;
            const meta = DISCOUNT_META[id];
            document.getElementById('applyModalPromoLine').textContent = meta ? (meta.name + ' (' + meta.code + ')') : '';

            selectedProducts = [];
            updateSelectedCount();
            updateAssignButtonState();
            pickerInput.value = '';

            const modal = document.getElementById('applyProductsModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            void modal.offsetHeight;
            requestAnimationFrame(function () { modal.classList.add('active'); });
            pickerInput.focus();

            searchProducts('');
        };

        window.closeApplyModal = function () {
            const modal = document.getElementById('applyProductsModal');
            modal.classList.remove('active');
            setTimeout(function () { modal.style.display = 'none'; }, 250);
            document.body.style.overflow = '';
            promoSelect.value = '';
            selectedProducts = [];
            updateSelectedCount();
        };

        document.getElementById('applyProductsModal').addEventListener('mousedown', function (e) {
            if (e.target === this) window.closeApplyModal();
        });

        function renderProductList(products) {
            const promoId = currentPromoId();
            const assignedIds = assignedIdsFor(promoId);
            const selectedIds = selectedProducts.map(function (p) { return String(p.id); });

            if (!products.length) {
                modalProductList.innerHTML = '<div class="product-picker-empty">No matching products.</div>';
                return;
            }

            modalProductList.innerHTML = products.map(function (p) {
                const isAssigned = assignedIds.includes(String(p.id));
                const isChecked = selectedIds.includes(String(p.id));
                return `
                    <label class="modal-product-row ${isChecked ? 'is-checked' : ''} ${isAssigned ? 'is-disabled' : ''}">
                        <input type="checkbox" data-product-id="${p.id}" ${isChecked ? 'checked' : ''} ${isAssigned ? 'disabled' : ''}>
                        <span class="mp-info">
                            <span class="mp-name">${escapeHtmlLocal(p.name)}</span>
                            <span class="mp-meta">SKU: ${escapeHtmlLocal(p.sku || '—')} &middot; ${escapeHtmlLocal(p.category || '—')}${isAssigned ? ' &middot; already applied' : ''}</span>
                        </span>
                        <span class="mp-price">₱${Number(p.price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                    </label>
                `;
            }).join('');
        }

        async function searchProducts(query) {
            if (searchController) searchController.abort();
            searchController = new AbortController();
            const url = '{{ route('admin.discounts.index') }}?ajax_products=1' + (query ? '&product_search=' + encodeURIComponent(query) : '');

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    signal: searchController.signal,
                });
                if (!response.ok) throw new Error('Request failed');
                const data = await response.json();
                lastResults = data.products || [];
                renderProductList(lastResults);
            } catch (err) {
                if (err.name === 'AbortError') return;
                modalProductList.innerHTML = '<div class="product-picker-empty">Unable to load products.</div>';
            }
        }

        pickerInput.addEventListener('input', function () {
            clearTimeout(searchDebounce);
            const query = pickerInput.value.trim();
            searchDebounce = setTimeout(function () { searchProducts(query); }, 250);
        });

        modalProductList.addEventListener('change', function (e) {
            const checkbox = e.target.closest('input[type="checkbox"][data-product-id]');
            if (!checkbox) return;
            const id = checkbox.dataset.productId;
            const row = checkbox.closest('.modal-product-row');

            if (checkbox.checked) {
                const product = lastResults.find(function (p) { return String(p.id) === String(id); });
                if (product && !selectedProducts.some(function (p) { return String(p.id) === String(id); })) {
                    selectedProducts.push(product);
                }
            } else {
                selectedProducts = selectedProducts.filter(function (p) { return String(p.id) !== String(id); });
            }

            row.classList.toggle('is-checked', checkbox.checked);
            updateSelectedCount();
            updateAssignButtonState();
        });

        window.assignSelectedProducts = function () {
            const discountId = currentPromoId();
            if (!discountId || !selectedProducts.length) return;
            const productIds = selectedProducts.map(function (p) { return p.id; });

            window.confirmAction({ title: 'Apply Promo', text: 'Apply this promo to ' + productIds.length + ' product(s)?' }).then(function (result) {
                if (!result.isConfirmed) return;

                assignBtn.disabled = true;
                assignBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';

                // Building the request is wrapped separately from sending
                // it: a synchronous error here (e.g. a bad selector) used to
                // throw before fetch() ever ran, leaving the button stuck on
                // "Applying..." forever with no request ever reaching the
                // server and no error shown — this is what actually happened
                // when the CSRF header used to be read from a <meta> tag
                // that doesn't exist on this page.
                let request;
                try {
                    request = fetch('{{ url('admin/discounts') }}/' + discountId + '/products', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({ product_ids: productIds }),
                    });
                } catch (err) {
                    toastError('Failed to apply promo. Please try again.');
                    assignBtn.innerHTML = '<i class="fa-solid fa-check"></i> Apply Discount/Promo';
                    updateAssignButtonState();
                    return;
                }

                request
                    .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                    .then(function ({ ok, data }) {
                        if (!ok || !data.success) {
                            toastError(data.message || 'Failed to apply promo.');
                            return;
                        }
                        if (!DISCOUNT_PRODUCT_MAP[discountId]) DISCOUNT_PRODUCT_MAP[discountId] = [];
                        const newlyAssigned = selectedProducts.filter(function (p) {
                            return data.assigned.map(String).includes(String(p.id));
                        });
                        DISCOUNT_PRODUCT_MAP[discountId] = DISCOUNT_PRODUCT_MAP[discountId].concat(newlyAssigned);
                        toastSuccess(data.message);
                        window.closeApplyModal();
                        reloadAppliedList();
                    })
                    .catch(function () { toastError('Failed to apply promo. Please try again.'); })
                    .finally(function () {
                        assignBtn.innerHTML = '<i class="fa-solid fa-check"></i> Apply Discount/Promo';
                        updateAssignButtonState();
                    });
            });
        };

        // ---- Applied Discount/Promo List ----
        let appliedController = null;

        async function reloadAppliedList(page = 1) {
            if (appliedController) appliedController.abort();
            appliedController = new AbortController();
            const url = '{{ route('admin.discounts.index') }}?ajax_applied=1' + (page > 1 ? '&applied_page=' + page : '');

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    signal: appliedController.signal,
                });
                if (!response.ok) throw new Error('Request failed');
                const data = await response.json();
                appliedTbody.innerHTML = data.rows || '';
                appliedPaginationWrapper.innerHTML = data.pagination || '';
                rebindAppliedPagination();
            } catch (err) {
                if (err.name === 'AbortError') return;
            }
        }

        function rebindAppliedPagination() {
            appliedPaginationWrapper.querySelectorAll('a.pagination-link').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    reloadAppliedList(this.dataset.page || 1);
                });
            });
        }

        rebindAppliedPagination();

        // ---- View Details modal ----
        window.openPromoDetails = function (discountId) {
            const meta = DISCOUNT_META[discountId];
            const products = DISCOUNT_PRODUCT_MAP[discountId] || [];
            if (!meta) return;

            const productsRows = products.length
                ? products.map(function (p) {
                    return '<tr><td>' + escapeHtmlLocal(p.name) + '</td><td>' + escapeHtmlLocal(p.sku || '—') + '</td><td>' + escapeHtmlLocal(p.category || '—') + '</td></tr>';
                }).join('')
                : '<tr><td colspan="3"><div class="empty-state"><p class="empty-text">No products assigned to this promo.</p></div></td></tr>';

            document.getElementById('promoDetailsBody').innerHTML = `
                <h3 style="margin:0 0 14px; font-size:1.1rem; color:#f8fafc;">${escapeHtmlLocal(meta.name)} (${escapeHtmlLocal(meta.code)})</h3>
                <h3 class="section-title">Promo Details</h3>
                <div class="apply-promo-detail-grid" style="margin-bottom:18px;">
                    <div class="detail-mini"><label>Discount Type</label><span>${escapeHtmlLocal(meta.typeLabel)}</span></div>
                    <div class="detail-mini"><label>Discount Value</label><span>${escapeHtmlLocal(meta.valueLabel)}</span></div>
                    <div class="detail-mini"><label>Start Date</label><span>${escapeHtmlLocal(meta.start)}</span></div>
                    <div class="detail-mini"><label>Expiration Date</label><span>${escapeHtmlLocal(meta.end)}</span></div>
                    <div class="detail-mini"><label>Status</label><span class="badge badge-dot ${meta.statusClass}">${escapeHtmlLocal(meta.statusLabel)}</span></div>
                </div>
                <h3 class="section-title">Applied Product${products.length === 1 ? '' : 's'}</h3>
                <div style="overflow-x:auto;">
                    <table class="table">
                        <thead><tr><th>Product Name</th><th>SKU</th><th>Category</th></tr></thead>
                        <tbody>${productsRows}</tbody>
                    </table>
                </div>
            `;

            const modal = document.getElementById('promoDetailsModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            void modal.offsetHeight;
            requestAnimationFrame(function () { modal.classList.add('active'); });
        };

        window.closePromoDetails = function () {
            const modal = document.getElementById('promoDetailsModal');
            modal.classList.remove('active');
            setTimeout(function () { modal.style.display = 'none'; }, 250);
            document.body.style.overflow = '';
        };

        document.getElementById('promoDetailsModal').addEventListener('mousedown', function (e) {
            if (e.target === this) window.closePromoDetails();
        });

        updateAssignButtonState();
    })();

    // ---- History modal ----
    window.openHistoryModal = function () {
        const modal = document.getElementById('historyModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('active'); });
        loadHistory();
    };

    window.closeHistoryModal = function () {
        const modal = document.getElementById('historyModal');
        modal.classList.remove('active');
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
    };

    document.getElementById('historyModal').addEventListener('mousedown', function (e) {
        if (e.target === this) closeHistoryModal();
    });

    window.switchHistoryTab = function (tab) {
        document.querySelectorAll('.history-tab-btn[data-history-tab]').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.historyTab === tab);
        });
        document.getElementById('historyPanelExpired').classList.toggle('active', tab === 'expired');
        document.getElementById('historyPanelUsed').classList.toggle('active', tab === 'used');
    };

    function loadHistory(expiredPage, usedPage) {
        const params = new URLSearchParams();
        if (expiredPage) params.set('expired_page', expiredPage);
        if (usedPage) params.set('used_page', usedPage);
        const url = '{{ route('admin.discounts.history') }}' + (params.toString() ? '?' + params.toString() : '');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                document.getElementById('historyPanelExpired').innerHTML = data.expiredHtml;
                document.getElementById('historyPanelUsed').innerHTML = data.usedHtml;
                bindHistoryPagination();
            })
            .catch(function () {
                document.getElementById('historyPanelExpired').innerHTML = '<p class="empty-text">Failed to load history.</p>';
                document.getElementById('historyPanelUsed').innerHTML = '<p class="empty-text">Failed to load history.</p>';
            });
    }

    function bindHistoryPagination() {
        document.querySelectorAll('#historyModal .pagination-link[data-history-page]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const which = this.dataset.historyPage;
                const page = this.dataset.page;
                if (which === 'expired') loadHistory(page, null);
                else loadHistory(null, page);
            });
        });
    }

    // Escape closes whichever of these three read-mostly popups is open —
    // matches the Add/Edit Promo modals' existing Escape-to-close behavior.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (document.getElementById('applyProductsModal').classList.contains('active')) { window.closeApplyModal(); return; }
        if (document.getElementById('historyModal').classList.contains('active')) { closeHistoryModal(); return; }
        if (document.getElementById('promoDetailsModal').classList.contains('active')) { window.closePromoDetails(); return; }
    });

    // ---- Add Promo modal ----
    const ADD_DISCOUNT_FIELD_IDS = ['DiscountRate', 'DiscountType', 'Name', 'PromoCode', 'Description', 'StartDate', 'EndDate'];
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
