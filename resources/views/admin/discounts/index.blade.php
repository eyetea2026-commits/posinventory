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

    /* Apply tab — a small "Select a Promo Discount" combo box card →
       a compact searchable multi-select ("Products") for applying that
       promo to several products at once → Applied Discount/Promo List
       is the main-page focus, below both. */
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

    /* Compact searchable multi-select ("combobox") for applying a promo to
       several products in one action — replaces any full product catalog. */
    .product-picker { position: relative; }
    .product-picker-control {
        display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
        min-height: 44px; padding: 6px 10px; background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 10px; cursor: text;
    }
    .product-picker-control:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
    .product-chip {
        display: inline-flex; align-items: center; gap: 6px; background: rgba(59, 130, 246, 0.15);
        color: #bfdbfe; border-radius: 8px; padding: 4px 8px; font-size: 0.82rem; white-space: nowrap;
    }
    .product-chip button { background: none; border: none; color: #93c5fd; cursor: pointer; font-size: 0.9rem; line-height: 1; padding: 0; }
    .product-picker-input { flex: 1; min-width: 140px; background: transparent; border: none; color: #f8fafc; font-size: 0.88rem; padding: 6px 2px; }
    .product-picker-input:focus { outline: none; }
    .product-picker-dropdown {
        position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 20;
        background: #0f172a; border: 1px solid #334155; border-radius: 10px;
        max-height: 260px; overflow-y: auto; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.45); display: none;
    }
    .product-picker-dropdown.open { display: block; }
    .product-picker-option { padding: 9px 12px; cursor: pointer; font-size: 0.85rem; color: #e2e8f0; border-bottom: 1px solid rgba(148, 163, 184, 0.08); }
    .product-picker-option:last-child { border-bottom: none; }
    .product-picker-option:hover { background: rgba(59, 130, 246, 0.12); }
    .product-picker-option .opt-meta { color: #94a3b8; font-size: 0.76rem; }
    .product-picker-empty { padding: 12px; color: #94a3b8; font-size: 0.82rem; text-align: center; }

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
    <div class="card" style="max-width:420px;">
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

    <div class="card" style="margin-top:20px;">
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

<!-- Apply Discount/Promo popup (multi-select product picker) -->
<div id="applyProductsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="applyProductsModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="applyProductsModalTitle"><i class="fa-solid fa-tags"></i> Apply Discount/Promo</h2>
            <button type="button" class="modal-close" onclick="closeApplyModal()" aria-label="Close">&times;</button>
        </div>

        <div class="form-group">
            <label>Promo</label>
            <div id="applyModalPromoLine" style="font-weight:700; color:#f8fafc; padding:6px 0 2px;"></div>
        </div>

        <div class="form-group">
            <div class="product-picker" id="productPicker">
                <label class="picker-label">Products</label>
                <div class="product-picker-control" id="productPickerControl">
                    <span id="productChips"></span>
                    <input type="text" id="productPickerInput" class="product-picker-input" placeholder="Select product(s)…" autocomplete="off">
                </div>
                <div class="product-picker-dropdown" id="productPickerDropdown"></div>
            </div>
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
    // Auto-show session messages
    @if(session('success'))
        toastSuccess('{{ session('success') }}');
    @endif
    @if(session('error'))
        toastError('{{ session('error') }}');
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
             method's comment for why. --}}
        const DISCOUNT_PRODUCT_MAP = @json($discountProductMap);

        {{-- Everything the View Details modal needs to render without a
             second request. --}}
        const DISCOUNT_META = @json($discountMeta);

        const promoSelect = document.getElementById('applyPromoSelect');
        const pickerInput = document.getElementById('productPickerInput');
        const pickerChips = document.getElementById('productChips');
        const pickerDropdown = document.getElementById('productPickerDropdown');
        const assignBtn = document.getElementById('assignSelectedBtn');
        const appliedTbody = document.getElementById('appliedAssignmentsTbody');
        const appliedPaginationWrapper = document.getElementById('appliedPaginationWrapper');

        let selectedProducts = []; // pending chips: [{id, name, sku, category}]
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

        function renderChips() {
            pickerChips.innerHTML = selectedProducts.map(function (p) {
                return '<span class="product-chip">' + escapeHtmlLocal(p.name) +
                    '<button type="button" data-remove-chip="' + p.id + '" aria-label="Remove">&times;</button></span>';
            }).join('');
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
            renderChips();
            updateAssignButtonState();
            pickerInput.value = '';
            closeDropdown();

            const modal = document.getElementById('applyProductsModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            void modal.offsetHeight;
            requestAnimationFrame(function () { modal.classList.add('active'); });
            pickerInput.focus();
        };

        window.closeApplyModal = function () {
            const modal = document.getElementById('applyProductsModal');
            modal.classList.remove('active');
            setTimeout(function () { modal.style.display = 'none'; }, 250);
            document.body.style.overflow = '';
            closeDropdown();
            promoSelect.value = '';
            selectedProducts = [];
            renderChips();
        };

        document.getElementById('applyProductsModal').addEventListener('mousedown', function (e) {
            if (e.target === this) window.closeApplyModal();
        });

        function closeDropdown() {
            pickerDropdown.classList.remove('open');
            pickerDropdown.innerHTML = '';
        }

        function renderDropdown(products) {
            const promoId = currentPromoId();
            const assignedIds = assignedIdsFor(promoId);
            const selectedIds = selectedProducts.map(function (p) { return String(p.id); });
            const available = products.filter(function (p) {
                return !assignedIds.includes(String(p.id)) && !selectedIds.includes(String(p.id));
            });

            if (!available.length) {
                pickerDropdown.innerHTML = '<div class="product-picker-empty">No matching products.</div>';
            } else {
                pickerDropdown.innerHTML = available.map(function (p) {
                    return '<div class="product-picker-option" data-option-id="' + p.id + '">' +
                        escapeHtmlLocal(p.name) +
                        '<div class="opt-meta">SKU: ' + escapeHtmlLocal(p.sku || '—') + ' &middot; ' + escapeHtmlLocal(p.category || '—') +
                        ' &middot; ₱' + Number(p.price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</div></div>';
                }).join('');
            }
            pickerDropdown.classList.add('open');
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
                renderDropdown(lastResults);
            } catch (err) {
                if (err.name === 'AbortError') return;
                pickerDropdown.innerHTML = '<div class="product-picker-empty">Unable to load products.</div>';
                pickerDropdown.classList.add('open');
            }
        }

        pickerInput.addEventListener('focus', function () {
            if (!currentPromoId()) return;
            searchProducts(pickerInput.value.trim());
        });

        pickerInput.addEventListener('input', function () {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(function () { searchProducts(pickerInput.value.trim()); }, 250);
        });

        pickerDropdown.addEventListener('click', function (e) {
            const option = e.target.closest('[data-option-id]');
            if (!option) return;
            const id = option.dataset.optionId;
            const product = lastResults.find(function (p) { return String(p.id) === String(id); });
            if (!product) return;

            selectedProducts.push(product);
            renderChips();
            updateAssignButtonState();
            pickerInput.value = '';
            pickerInput.focus();
            renderDropdown(lastResults);
        });

        pickerChips.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-remove-chip]');
            if (!btn) return;
            const id = btn.dataset.removeChip;
            selectedProducts = selectedProducts.filter(function (p) { return String(p.id) !== String(id); });
            renderChips();
            updateAssignButtonState();
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('#productPicker')) closeDropdown();
        });

        window.assignSelectedProducts = function () {
            const discountId = currentPromoId();
            if (!discountId || !selectedProducts.length) return;
            const productIds = selectedProducts.map(function (p) { return p.id; });

            window.confirmAction({ title: 'Apply Promo', text: 'Apply this promo to ' + productIds.length + ' product(s)?' }).then(function (result) {
                if (!result.isConfirmed) return;

                assignBtn.disabled = true;
                assignBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';

                fetch('{{ url('admin/discounts') }}/' + discountId + '/products', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ product_ids: productIds }),
                })
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
                    window.location.reload();
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
