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

    /* Apply tab layout */
    .apply-layout { display: grid; grid-template-columns: 320px 1fr; gap: 20px; align-items: start; }
    @media (max-width: 900px) { .apply-layout { grid-template-columns: 1fr; } }
    .apply-picker-body { padding: 18px; }
    .apply-assigned-list { margin-top: 16px; display: flex; flex-direction: column; gap: 8px; }
    .apply-assigned-chip {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(148, 163, 184, 0.12);
        border-radius: 10px; padding: 8px 12px; font-size: 0.85rem; color: #cbd5e1;
    }
    .apply-assigned-chip .remove-chip { background: none; border: none; color: #fca5a5; cursor: pointer; font-size: 0.9rem; }
    .apply-product-table th:first-child, .apply-product-table td:first-child { width: 40px; white-space: normal; }
    .apply-product-table input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }
    tr[data-product-row].is-checked { background: rgba(16, 185, 129, 0.06); }

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
    <div class="apply-layout">
        <div class="card">
            <div class="card-header"><h3 style="margin:0; font-size:1rem; color:#f8fafc;">Select a Promo</h3></div>
            <div class="apply-picker-body">
                <select id="applyPromoSelect" class="form-control" onchange="window.onApplyPromoChange()">
                    <option value="">Choose a promo…</option>
                    @foreach($allDiscounts as $d)
                        <option value="{{ $d->DiscountID }}">{{ $d->Name }} ({{ $d->PromoCode }})</option>
                    @endforeach
                </select>
                <div id="applyPromoAssignedList" class="apply-assigned-list">
                    <p class="empty-text" style="margin:0; padding:0;">Select a promo to see its currently assigned products.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <form method="GET" id="applyProductFilterForm" class="search-box-wrapper" autocomplete="off" onsubmit="return false;">
                    <i class="search-icon fas fa-search"></i>
                    <input type="text" id="applyProductSearchInput" class="search-input" placeholder="Search products by name or SKU...">
                </form>
                <button type="button" id="assignSelectedBtn" class="btn btn-primary" disabled onclick="window.assignSelectedProducts()">
                    <i class="fa-solid fa-check"></i> Assign Selected
                </button>
            </div>
            <div class="card-body">
                <div style="overflow-x:auto;">
                    <table class="table apply-product-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody id="applyProductsTbody">
                            @include('admin.discounts.partials.apply-product-rows', ['products' => $products])
                        </tbody>
                    </table>
                </div>
                <div id="applyProductPaginationWrapper">
                    @include('admin.discounts.partials.pagination', ['discounts' => $products])
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:20px;">
        <div class="card-header"><h3 style="margin:0; font-size:1rem; color:#f8fafc;">Applied Discount/Promo List</h3></div>
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
        {{-- {id, name} pairs (not just ids) so the assigned-products list can
             show real names even for products outside the current page/search
             of the picker table on the right. --}}
        const DISCOUNT_PRODUCT_MAP = @json($allDiscounts->mapWithKeys(function ($d) {
            return [$d->DiscountID => $d->products()->get(['Product.ProductID', 'Product.ProductName'])->map(function ($p) {
                return ['id' => $p->ProductID, 'name' => $p->ProductName];
            })];
        }));

        const promoSelect = document.getElementById('applyPromoSelect');
        const assignedListEl = document.getElementById('applyPromoAssignedList');
        const productSearchInput = document.getElementById('applyProductSearchInput');
        const productsTbody = document.getElementById('applyProductsTbody');
        const productsPaginationWrapper = document.getElementById('applyProductPaginationWrapper');
        const assignBtn = document.getElementById('assignSelectedBtn');
        const appliedTbody = document.getElementById('appliedAssignmentsTbody');
        const appliedPaginationWrapper = document.getElementById('appliedPaginationWrapper');

        let productDebounce = null;
        let productController = null;

        function currentPromoId() { return promoSelect.value; }

        function renderAssignedList() {
            const id = currentPromoId();
            if (!id) {
                assignedListEl.innerHTML = '<p class="empty-text" style="margin:0; padding:0;">Select a promo to see its currently assigned products.</p>';
                return;
            }
            const assigned = DISCOUNT_PRODUCT_MAP[id] || [];
            if (!assigned.length) {
                assignedListEl.innerHTML = '<p class="empty-text" style="margin:0; padding:0;">No products assigned to this promo yet.</p>';
                return;
            }
            assignedListEl.innerHTML = assigned.map(function (p) {
                return '<div class="apply-assigned-chip"><span>' + p.name + '</span></div>';
            }).join('');
        }

        function applyCheckedState() {
            const id = currentPromoId();
            const productIds = (id && DISCOUNT_PRODUCT_MAP[id]) ? DISCOUNT_PRODUCT_MAP[id].map(function (p) { return String(p.id); }) : [];
            productsTbody.querySelectorAll('tr[data-product-row]').forEach(function (row) {
                const checkbox = row.querySelector('.apply-product-checkbox');
                const isAssigned = productIds.includes(row.dataset.productId);
                checkbox.checked = isAssigned;
                checkbox.disabled = isAssigned;
                row.classList.toggle('is-checked', isAssigned);
            });
            updateAssignButtonState();
        }

        window.onApplyPromoChange = function () {
            renderAssignedList();
            applyCheckedState();
        };

        window.onApplyProductCheckboxChange = function () {
            document.querySelectorAll('tr[data-product-row]').forEach(function (row) {
                const checkbox = row.querySelector('.apply-product-checkbox');
                row.classList.toggle('is-checked', checkbox.checked);
            });
            updateAssignButtonState();
        };

        function updateAssignButtonState() {
            const hasPromo = !!currentPromoId();
            const hasSelection = !!productsTbody.querySelector('.apply-product-checkbox:checked:not(:disabled)');
            assignBtn.disabled = !(hasPromo && hasSelection);
        }

        function buildProductQuery(page) {
            const params = new URLSearchParams();
            const search = productSearchInput.value.trim();
            if (search) params.set('product_search', search);
            if (page > 1) params.set('product_page', page);
            params.set('ajax_products', '1');
            return params.toString();
        }

        async function loadProducts(page = 1) {
            if (productController) productController.abort();
            productController = new AbortController();
            const url = '{{ route('admin.discounts.index') }}?' + buildProductQuery(page);

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    signal: productController.signal,
                });
                if (!response.ok) throw new Error('Request failed');
                const data = await response.json();
                productsTbody.innerHTML = data.rows || '';
                productsPaginationWrapper.innerHTML = data.pagination || '';
                rebindProductPagination();
                applyCheckedState();
                renderAssignedList();
            } catch (err) {
                if (err.name === 'AbortError') return;
                productsTbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><p class="empty-text">Unable to load products.</p></div></td></tr>';
            }
        }

        function rebindProductPagination() {
            productsPaginationWrapper.querySelectorAll('a.pagination-link').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    loadProducts(this.dataset.page || 1);
                });
            });
        }

        productSearchInput.addEventListener('input', function () {
            clearTimeout(productDebounce);
            productDebounce = setTimeout(function () { loadProducts(1); }, 300);
        });

        rebindProductPagination();

        window.assignSelectedProducts = function () {
            const discountId = currentPromoId();
            if (!discountId) return;
            const productIds = Array.from(productsTbody.querySelectorAll('.apply-product-checkbox:checked:not(:disabled)')).map(function (cb) { return cb.value; });
            if (!productIds.length) return;

            window.confirmAction({ title: 'Assign Promo', text: 'Assign the selected promo to ' + productIds.length + ' product(s)?' }).then(function (result) {
                if (!result.isConfirmed) return;

                assignBtn.disabled = true;
                assignBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';

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
                            toastError(data.message || 'Failed to assign promo.');
                            return;
                        }
                        if (!DISCOUNT_PRODUCT_MAP[discountId]) DISCOUNT_PRODUCT_MAP[discountId] = [];
                        const newlyAssigned = data.assigned.map(function (pid) {
                            const row = productsTbody.querySelector('tr[data-product-row][data-product-id="' + pid + '"]');
                            return { id: pid, name: row ? row.dataset.productName : ('Product #' + pid) };
                        });
                        DISCOUNT_PRODUCT_MAP[discountId] = DISCOUNT_PRODUCT_MAP[discountId].concat(newlyAssigned);
                        toastSuccess(data.message);
                        applyCheckedState();
                        renderAssignedList();
                        reloadAppliedList();
                    })
                    .catch(function () { toastError('Failed to assign promo. Please try again.'); })
                    .finally(function () {
                        assignBtn.innerHTML = '<i class="fa-solid fa-check"></i> Assign Selected';
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

        window.detachAppliedProduct = function (discountId, productId) {
            window.confirmAction({ title: 'Remove Promo', text: 'Remove this promo from this product?' }).then(function (result) {
                if (!result.isConfirmed) return;

                fetch('{{ url('admin/discounts') }}/' + discountId + '/products/' + productId, {
                    method: 'DELETE',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                })
                    .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                    .then(function ({ ok, data }) {
                        if (!ok || !data.success) {
                            toastError(data.message || 'Failed to remove promo.');
                            return;
                        }
                        if (DISCOUNT_PRODUCT_MAP[discountId]) {
                            DISCOUNT_PRODUCT_MAP[discountId] = DISCOUNT_PRODUCT_MAP[discountId].filter(function (p) { return String(p.id) !== String(productId); });
                        }
                        toastSuccess('Promo removed from product.');
                        applyCheckedState();
                        renderAssignedList();
                        reloadAppliedList();
                    })
                    .catch(function () { toastError('Failed to remove promo. Please try again.'); });
            });
        };

        applyCheckedState();
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
