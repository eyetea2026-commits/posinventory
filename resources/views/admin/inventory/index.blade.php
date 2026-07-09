@extends('admin.layout')

@section('header')
    <div class="header-title">
        <h1>Inventory</h1>
        <p>Monitor stock levels, sales velocity, and product availability</p>
    </div>
@endsection

@section('content')
<style>
    :root {
        --glass-bg: rgba(15, 23, 42, 0.75);
        --glass-border: rgba(148, 163, 184, 0.12);
        --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        --primary: #3b82f6;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
    }

    .glass-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        box-shadow: var(--glass-shadow);
        backdrop-filter: blur(12px);
        overflow: hidden;
    }

    /* Toolbar */
    .toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        padding: 22px 26px;
        border-bottom: 1px solid var(--glass-border);
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

    .search-box-wrapper:focus-within {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    .search-box-wrapper .search-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.95rem;
        line-height: 1;
        pointer-events: none;
    }

    .search-input {
        flex: 1;
        width: 100%;
        padding: 12px 16px 12px 44px;
        background: transparent;
        border: none;
        color: #f8fafc;
        font-size: 0.95rem;
        line-height: 1.4;
        transition: all 0.3s ease;
    }

    .search-input::placeholder { color: #64748b; }
    .search-input:focus { outline: none; }

    .filter-group {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
        font-size: 0.95rem;
    }

    .btn-secondary {
        background: rgba(100, 116, 139, 0.2);
        color: #e2e8f0;
    }

    .btn-secondary:hover {
        background: rgba(100, 116, 139, 0.35);
        color: #f8fafc;
    }

    /* Status pills row */
    .status-pills {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        padding: 18px 26px;
        border-bottom: 1px solid var(--glass-border);
        background: rgba(15, 23, 42, 0.4);
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 16px;
        background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 999px;
        color: #cbd5e1;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .status-pill:hover {
        background: rgba(59, 130, 246, 0.12);
        border-color: rgba(59, 130, 246, 0.4);
        color: #f8fafc;
    }

    .status-pill.active {
        background: linear-gradient(135deg, var(--primary), var(--success));
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35);
    }

    .status-pill i { font-size: 0.8rem; }

    .pill-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        padding: 0 8px;
        background: rgba(15, 23, 42, 0.45);
        color: #f8fafc;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .status-pill.active .pill-count {
        background: rgba(255, 255, 255, 0.25);
        color: white;
    }

    /* Table */
    .table-container {
        overflow-x: auto;
        position: relative;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th {
        position: sticky;
        top: 0;
        background: rgba(15, 23, 42, 0.95);
        padding: 16px 20px;
        text-align: left;
        font-weight: 600;
        color: #94a3b8;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
        z-index: 10;
        border-bottom: 1px solid var(--glass-border);
    }

    .table td {
        padding: 18px 20px;
        border-bottom: 1px solid var(--glass-border);
        vertical-align: middle;
    }

    .table tbody tr { transition: all 0.2s ease; }
    .table tbody tr:hover { background: rgba(59, 130, 246, 0.06); }

    /* Product Info */
    .product-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 220px;
    }

    .product-name {
        font-weight: 700;
        color: #f8fafc;
        font-size: 0.95rem;
        line-height: 1.3;
    }

    .product-model {
        color: #94a3b8;
        font-size: 0.85rem;
        font-weight: 500;
    }

    /* Category */
    .category-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: rgba(59, 130, 246, 0.12);
        color: #93c5fd;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 500;
        white-space: nowrap;
    }

    /* Stock */
    .stock-info {
        display: flex;
        flex-direction: column;
        gap: 6px;
        align-items: flex-start;
    }

    .stock-quantity {
        color: #f8fafc;
        font-weight: 700;
        font-size: 0.95rem;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .badge i { font-size: 0.75rem; }

    .badge-in-stock { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; }
    .badge-low-stock { background: rgba(245, 158, 11, 0.15); color: #fcd34d; }
    .badge-replenish { background: rgba(249, 115, 22, 0.15); color: #fb923c; }
    .badge-out-of-stock { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }

    /* Threshold */
    .threshold-value {
        color: #f8fafc;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .threshold-unit {
        color: #94a3b8;
        font-size: 0.8rem;
        font-weight: 500;
        margin-left: 2px;
    }

    /* Velocity */
    .velocity-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 110px;
    }

    .velocity-number {
        color: #f8fafc;
        font-weight: 700;
        font-size: 0.95rem;
    }

    .velocity-number.velocity-fast { color: #6ee7b7; }
    .velocity-number.velocity-slow { color: #fb923c; }
    .velocity-number.velocity-normal { color: #f8fafc; }

    .velocity-label {
        color: #94a3b8;
        font-size: 0.78rem;
        font-weight: 500;
    }

    /* Actions */
    .actions-group {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .action-btn {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        font-size: 0.9rem;
    }

    .action-btn.view {
        background: rgba(59, 130, 246, 0.15);
        color: #93c5fd;
    }
    .action-btn.view:hover {
        background: rgba(59, 130, 246, 0.3);
        transform: scale(1.05);
    }

    /* Empty state */
    .empty-state { padding: 60px 20px; text-align: center; }

    .empty-icon {
        width: 90px;
        height: 90px;
        margin: 0 auto 20px;
        background: rgba(59, 130, 246, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.4rem;
        color: var(--primary);
    }

    .empty-title {
        font-size: 1.25rem;
        color: #e2e8f0;
        margin-bottom: 8px;
        font-weight: 600;
    }

    .empty-text { color: #64748b; margin-bottom: 20px; }

    /* Pagination */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        padding: 20px;
        border-top: 1px solid var(--glass-border);
        flex-wrap: wrap;
    }

    .pagination-link {
        padding: 8px 14px;
        background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 10px;
        color: #e2e8f0;
        text-decoration: none;
        transition: all 0.2s ease;
        font-size: 0.9rem;
    }

    .pagination-link:hover {
        background: rgba(59, 130, 246, 0.15);
        border-color: rgba(59, 130, 246, 0.3);
    }

    .pagination-link.active {
        background: linear-gradient(135deg, var(--primary), var(--success));
        color: white;
        border-color: transparent;
    }

    .pagination-link.disabled {
        opacity: 0.5;
        pointer-events: none;
    }

    /* Alerts */
    .alert {
        padding: 16px 20px;
        border-radius: 12px;
        margin: 20px 24px;
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

    /* Loading overlay */
    .table-loader {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(2px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 5;
    }

    .table-loader.active { display: flex; }

    .spinner {
        width: 38px;
        height: 38px;
        border: 3px solid rgba(59, 130, 246, 0.2);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* Column widths */
    .col-product { min-width: 240px; }
    .col-category { width: 150px; }
    .col-stock { width: 160px; }
    .col-threshold { width: 150px; }
    .col-velocity { width: 140px; }
    .col-actions { width: 90px; text-align: right; }

    /* Responsive */
    @media (max-width: 992px) {
        .col-product { min-width: 200px; }
    }

    @media (max-width: 768px) {
        .toolbar {
            flex-direction: column;
            align-items: stretch;
            padding: 18px;
        }

        .search-box-wrapper { max-width: 100%; min-width: 100%; }

        .status-pills { padding: 14px 18px; }

        .status-pill { font-size: 0.78rem; padding: 7px 12px; }

        .table th { font-size: 0.72rem; padding: 12px 10px; }
        .table td { padding: 14px 10px; }

        .product-name { font-size: 0.88rem; }
        .product-model { font-size: 0.78rem; }

        .col-actions { width: 70px; }
        .action-btn { width: 32px; height: 32px; font-size: 0.8rem; }
    }
</style>

<!-- Search + Status Reset -->
<div class="card glass-card">
    <div class="toolbar">
        <form
            method="GET"
            action="{{ route('admin.inventory.index') }}"
            id="filterForm"
            class="search-box-wrapper"
            autocomplete="off"
        >
            <i class="search-icon fas fa-search"></i>
            <input
                type="text"
                name="search"
                id="searchInput"
                value="{{ $search }}"
                class="search-input"
                placeholder="Search by name, model, SKU, barcode, or category..."
            />
            <input type="hidden" name="status" id="statusInput" value="{{ $status ?? '' }}" />
        </form>

        <div class="filter-group">
            <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary">
                <i class="fas fa-undo"></i> Reset
            </a>
        </div>
    </div>

    <!-- Status pills (REQ045) -->
    <div class="status-pills" id="statusPills">
        <button type="button" class="status-pill {{ ($status === null || $status === '') ? 'active' : '' }}" data-status="">
            All
            <span class="pill-count">{{ $counts['all'] ?? 0 }}</span>
        </button>
        <button type="button" class="status-pill available {{ $status === 'available' ? 'active' : '' }}" data-status="available">
            <i class="fas fa-check-circle"></i> Available
            <span class="pill-count">{{ $counts['available'] ?? 0 }}</span>
        </button>
        <button type="button" class="status-pill low-stock {{ $status === 'low-stock' ? 'active' : '' }}" data-status="low-stock">
            <i class="fas fa-exclamation-triangle"></i> Low Stock
            <span class="pill-count">{{ $counts['low-stock'] ?? 0 }}</span>
        </button>
        <button type="button" class="status-pill fast-moving {{ $status === 'fast-moving' ? 'active' : '' }}" data-status="fast-moving">
            <i class="fas fa-bolt"></i> Fast-Moving
            <span class="pill-count">{{ $counts['fast-moving'] ?? 0 }}</span>
        </button>
        <button type="button" class="status-pill slow-moving {{ $status === 'slow-moving' ? 'active' : '' }}" data-status="slow-moving">
            <i class="fas fa-hourglass-half"></i> Slow-Moving
            <span class="pill-count">{{ $counts['slow-moving'] ?? 0 }}</span>
        </button>
        <button type="button" class="status-pill out-of-stock {{ $status === 'out-of-stock' ? 'active' : '' }}" data-status="out-of-stock">
            <i class="fas fa-times-circle"></i> Out of Stock
            <span class="pill-count">{{ $counts['out-of-stock'] ?? 0 }}</span>
        </button>
    </div>

    @if(session('status'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            {{ session('status') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            {{ session('error') }}
        </div>
    @endif

    <!-- Inventory Table -->
    <div class="table-container" id="inventoryTableContainer">
        <div class="table-loader" id="tableLoader">
            <div class="spinner"></div>
        </div>

        <table class="table" id="inventoryTable">
            <thead>
                <tr>
                    <th class="col-product">Product Info</th>
                    <th class="col-category">Category</th>
                    <th class="col-stock">Current Stock</th>
                    <th class="col-threshold">Reorder Threshold</th>
                    <th class="col-velocity">30-Day Sales</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="inventoryTbody">
                @include('admin.inventory.partials.rows', ['products' => $products])
            </tbody>
        </table>
    </div>

    <div id="paginationWrapper">
        @if($products->hasPages())
            @include('admin.inventory.partials.pagination', ['products' => $products])
        @endif
    </div>
</div>

<script>
    (function () {
        const filterForm = document.getElementById('filterForm');
        const searchInput = document.getElementById('searchInput');
        const statusInput = document.getElementById('statusInput');
        const tableLoader = document.getElementById('tableLoader');
        const tbody = document.getElementById('inventoryTbody');
        const paginationWrapper = document.getElementById('paginationWrapper');
        const statusPills = document.getElementById('statusPills');

        let debounceTimer = null;
        let currentController = null;

        // Build URL query string from current filter state
        function buildQuery(page = 1) {
            const params = new URLSearchParams();
            const search = searchInput.value.trim();
            const status = statusInput.value;

            if (search) params.set('search', search);
            if (status) params.set('status', status);
            if (page > 1) params.set('page', page);

            return params.toString();
        }

        // Apply filters via AJAX and update table + pagination
        async function applyFilters(page = 1) {
            const query = buildQuery(page);
            const url = `${filterForm.action}${query ? '?' + query : ''}`;

            // Sync URL bar (no scroll)
            window.history.replaceState({}, '', url);

            // Show loader
            tableLoader.classList.add('active');

            // Cancel previous in-flight request
            if (currentController) {
                currentController.abort();
            }
            currentController = new AbortController();

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    signal: currentController.signal,
                });

                if (!response.ok) {
                    throw new Error(`Request failed (${response.status})`);
                }

                const data = await response.json();
                tbody.innerHTML = data.rows || '';
                paginationWrapper.innerHTML = data.pagination || '';
                rebindPagination();
            } catch (err) {
                if (err.name === 'AbortError') return;
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div>
                                <p class="empty-title">Unable to load inventory</p>
                                <p class="empty-text">Please try again.</p>
                            </div>
                        </td>
                    </tr>`;
                paginationWrapper.innerHTML = '';
            } finally {
                tableLoader.classList.remove('active');
                initTooltips();
            }
        }

        // Re-bind click handlers for newly rendered pagination links
        function rebindPagination() {
            paginationWrapper.querySelectorAll('a.pagination-link').forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const url = new URL(this.href);
                    const page = url.searchParams.get('page') || 1;
                    applyFilters(page);
                });
            });
        }

        // Initialize Bootstrap tooltips (if Bootstrap JS is loaded)
        function initTooltips() {
            const tooltipTriggerList = [].slice.call(
                document.querySelectorAll('[data-bs-toggle="tooltip"]')
            );
            if (window.bootstrap && window.bootstrap.Tooltip) {
                tooltipTriggerList.forEach(el => new window.bootstrap.Tooltip(el));
            }
        }

        // Debounced search
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => applyFilters(1), 300);
        });

        // Form submit (Enter key) — prevent full reload
        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            applyFilters(1);
        });

        // Status pill click handlers
        statusPills.querySelectorAll('.status-pill').forEach(pill => {
            pill.addEventListener('click', function () {
                const newStatus = this.dataset.status || '';
                if ((statusInput.value || '') === newStatus) {
                    return; // already active, no-op
                }
                // Update visual active state
                statusPills.querySelectorAll('.status-pill').forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                // Sync hidden input and refetch
                statusInput.value = newStatus;
                applyFilters(1);
            });
        });

        // Initial bindings
        rebindPagination();
        initTooltips();
    })();
</script>
@endsection
