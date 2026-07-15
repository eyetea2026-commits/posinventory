@extends('admin.layout')

@section('header')
    <div class="header-title">
        <h1>Product Management</h1>
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

    /* Search Bar */
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

    .search-input::placeholder {
        color: #64748b;
    }

    .search-input:focus {
        outline: none;
    }

    /* Filter Group */
    .filter-group {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .filter-select {
        padding: 12px 36px 12px 16px;
        background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(59, 130, 246, 0.15);
        border-radius: 12px;
        color: #f8fafc;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.3s ease;
        appearance: none;
        background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2212%22%20height%3D%2212%22%20fill%3D%22%2394a3b8%22%3E%3Cpath%20d%3D%22M6%208.5L1.5%204h9z%22%2F%3E%3C%2Fsvg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        min-width: 180px;
    }

    .filter-select option {
        background: #1e293b;
        color: #f8fafc;
    }

    .filter-select:hover {
        border-color: rgba(59, 130, 246, 0.3);
    }

    .filter-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    /* Buttons */
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

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--success));
        color: white;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
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

    .table tbody tr {
        transition: all 0.2s ease;
    }

    .table tbody tr:hover {
        background: rgba(59, 130, 246, 0.06);
    }

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

    .product-desc {
        color: #64748b;
        font-size: 0.8rem;
        line-height: 1.4;
        max-width: 320px;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
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

    /* Pricing */
    .pricing-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 160px;
    }

    .pricing-sell {
        color: #6ee7b7;
        font-weight: 700;
        font-size: 0.95rem;
    }

    .pricing-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        font-size: 0.78rem;
        color: #94a3b8;
    }

    .pricing-row .pricing-label {
        color: #64748b;
    }

    .pricing-row .pricing-value {
        color: #cbd5e1;
        font-weight: 500;
    }

    .pricing-profit {
        font-size: 0.78rem;
        font-weight: 600;
    }

    .pricing-profit.positive { color: #6ee7b7; }
    .pricing-profit.negative { color: #fca5a5; }

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

    .badge-in-stock {
        background: rgba(16, 185, 129, 0.15);
        color: #6ee7b7;
    }

    .badge-low-stock {
        background: rgba(245, 158, 11, 0.15);
        color: #fcd34d;
    }

    .badge-replenish {
        background: rgba(249, 115, 22, 0.15);
        color: #fb923c;
    }

    .badge-out-of-stock {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
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

    .action-btn.edit {
        background: rgba(16, 185, 129, 0.15);
        color: #6ee7b7;
    }
    .action-btn.edit:hover {
        background: rgba(16, 185, 129, 0.3);
        transform: scale(1.05);
    }

    .action-btn.delete {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
    }
    .action-btn.delete:hover {
        background: rgba(239, 68, 68, 0.3);
        transform: scale(1.05);
    }

    /* Empty state */
    .empty-state {
        padding: 60px 20px;
        text-align: center;
    }

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

    .empty-text {
        color: #64748b;
        margin-bottom: 20px;
    }

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
    .col-pricing { width: 200px; }
    .col-actions { width: 160px; text-align: right; }

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

        .search-box-wrapper {
            max-width: 100%;
            min-width: 100%;
        }

        .filter-group {
            flex-wrap: wrap;
            width: 100%;
        }

        .filter-select,
        .filter-group .btn {
            flex: 1;
            min-width: 140px;
        }

        .table th {
            font-size: 0.72rem;
            padding: 12px 10px;
        }

        .table td {
            padding: 14px 10px;
        }

        .product-name { font-size: 0.88rem; }
        .product-model { font-size: 0.78rem; }
        .product-desc { font-size: 0.75rem; max-width: 180px; }

        .col-actions { width: 130px; }

        .actions-group { gap: 6px; }
        .action-btn { width: 32px; height: 32px; font-size: 0.8rem; }
    }

    /* Add Product Modal */
    .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .form-group { margin-bottom: 0; }
    .form-group.full-width { grid-column: 1 / -1; }
    .form-label { display: block; margin-bottom: 4px; font-weight: 600; color: #cbd5e1; font-size: 0.82rem; }
    .form-label .required { color: #ef4444; }

    .form-input, .form-select {
        width: 100%;
        padding: 9px 12px;
        background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 9px;
        color: #f8fafc;
        font-size: 0.85rem;
        transition: all 0.2s ease;
    }
    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }
    .form-input.is-invalid, .form-select.is-invalid { border-color: rgba(239, 68, 68, 0.6); }
    .form-select {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 16px;
        padding-right: 32px;
    }
    textarea.form-input { min-height: 70px; resize: vertical; }
    .form-error { display: block; margin-top: 3px; color: #fca5a5; font-size: 0.72rem; }

    .barcode-input-row { display: flex; gap: 8px; align-items: stretch; }
    .barcode-input-row .form-input { flex: 1; min-width: 0; }
    .btn-scan-barcode {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 0 14px;
        border-radius: 9px;
        border: 1px solid rgba(139, 92, 246, 0.35);
        background: rgba(139, 92, 246, 0.15);
        color: #c4b5fd;
        font-weight: 600;
        font-size: 0.8rem;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .btn-scan-barcode:hover {
        background: rgba(139, 92, 246, 0.28);
        border-color: rgba(139, 92, 246, 0.55);
        transform: translateY(-1px);
    }
    .btn-scan-barcode:active { transform: translateY(0); }

    .computed-fields { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 4px; }
    .computed-field {
        padding: 10px;
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 10px;
        text-align: center;
    }
    .computed-field label { display: block; font-size: 0.68rem; color: #64748b; margin-bottom: 4px; text-transform: uppercase; }
    .computed-field .value { font-size: 0.95rem; font-weight: 700; color: #10b981; }
    .computed-field .value.negative { color: #ef4444; }

    .btn-spinner-sm {
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 2px solid transparent;
        border-top-color: currentColor;
        border-radius: 50%;
        animation: btn-spin-sm 0.8s linear infinite;
    }
    @keyframes btn-spin-sm { to { transform: rotate(360deg); } }

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

    @media (max-width: 600px) {
        .form-grid { grid-template-columns: 1fr; }
        .computed-fields { grid-template-columns: 1fr; }
    }
</style>

<!-- Search + Category Filter -->
<div class="card glass-card">
    <div class="toolbar">
        <form
            method="GET"
            action="{{ route('admin.products.index') }}"
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
                placeholder="Search by name, model, SKU, or barcode..."
            />
            <input type="hidden" name="category_id" id="categoryIdInput" value="{{ $categoryId }}" />
        </form>

        <div class="filter-group">
            <select id="categoryFilter" class="filter-select" aria-label="Filter by category">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option
                        value="{{ $category->CategoryID }}"
                        {{ (string) $categoryId === (string) $category->CategoryID ? 'selected' : '' }}
                    >
                        {{ $category->CategoryName }}
                    </option>
                @endforeach
            </select>
            <a href="{{ route('admin.products.create') }}" class="btn btn-primary" onclick="openAddProductModal(event)">
                <i class="fas fa-plus"></i> Add Product
            </a>
        </div>
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

    <!-- Product Table -->
    <div class="table-container" id="productTableContainer">
        <div class="table-loader" id="tableLoader">
            <div class="spinner"></div>
        </div>

        <table class="table" id="productTable">
            <thead>
                <tr>
                    <th class="col-product">Product Info</th>
                    <th class="col-category">Category</th>
                    <th class="col-pricing">Pricing</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="productTbody">
                @include('admin.products.partials.rows', ['products' => $products])
            </tbody>
        </table>
    </div>

    <div id="paginationWrapper">
        @if($products->hasPages())
            @include('admin.products.partials.pagination', ['products' => $products])
        @endif
    </div>
</div>

<script>
    (function () {        const filterForm = document.getElementById('filterForm');
        const searchInput = document.getElementById('searchInput');
        const categoryIdInput = document.getElementById('categoryIdInput');
        const categoryFilter = document.getElementById('categoryFilter');
        const tableLoader = document.getElementById('tableLoader');
        const tbody = document.getElementById('productTbody');
        const paginationWrapper = document.getElementById('paginationWrapper');

        let debounceTimer = null;
        let currentController = null;

        // Build URL query string from current filter state
        function buildQuery(page = 1) {
            const params = new URLSearchParams();
            const search = searchInput.value.trim();
            const categoryId = categoryIdInput.value;

            if (search) params.set('search', search);
            if (categoryId) params.set('category_id', categoryId);
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
                        <td colspan="4">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div>
                                <p class="empty-title">Unable to load products</p>
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

        // Category filter change — instant update
        categoryFilter.addEventListener('change', function () {
            categoryIdInput.value = this.value;
            applyFilters(1);
        });

        // Initial bindings
        rebindPagination();
        initTooltips();

        // Exposed so the Add Product modal can refresh the table using the
        // exact same AJAX path as search/filter/pagination, after a
        // successful create — no full page reload needed.
        window.refreshProductsTable = function () {
            applyFilters(1);
        };

        // Confirm delete (SweetAlert2)
        window.confirmDelete = function (productId) {
            Swal.fire({
                title: 'Confirm Delete',
                text: 'Are you sure you want to delete this product? This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteForm' + productId).submit();
                }
            });
        };

        // Auto-show session messages
        @if(session('status'))
            Swal.fire({
                title: 'Success',
                text: '{{ session('status') }}',
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
    })();
</script>

<!-- Add Product Modal -->
<div id="addProductModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="addProductModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="addProductModalTitle"><i class="fas fa-box-open"></i> Add New Product</h2>
            <button type="button" class="modal-close" onclick="closeAddProductModal()" aria-label="Close">&times;</button>
        </div>

        <div id="addProductGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="addProductForm">
            @include('admin.products.partials.product-form-fields', ['categories' => $categories])
        </form>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="addProductCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="addProductSubmitBtn">
                <i class="fas fa-save"></i> Create Product
            </button>
        </div>
    </div>
</div>

@include('admin.products.partials.product-form-behavior')
@include('admin.products.partials.barcode-scanner')

<script>
    window.initBarcodeScanner('addProductForm');

    const ADD_PRODUCT_FIELD_IDS = ['ProductName', 'Model', 'Description', 'CategoryID', 'CostPrice', 'ReorderThreshold', 'Barcode'];
    let addProductLastFocused = null;

    function addProductIsSubmitting() {
        const btn = document.getElementById('addProductSubmitBtn');
        return btn ? btn.disabled : false;
    }

    function clearAddProductFieldErrors() {
        const form = document.getElementById('addProductForm');
        ADD_PRODUCT_FIELD_IDS.forEach(function (field) {
            const span = document.getElementById('error-' + field);
            if (span) span.textContent = '';
            const input = form.querySelector('[name="' + field + '"]');
            if (input) input.classList.remove('is-invalid');
        });
    }

    function showAddProductFieldErrors(errors) {
        const form = document.getElementById('addProductForm');
        clearAddProductFieldErrors();
        let firstInvalid = null;
        Object.keys(errors).forEach(function (field) {
            const span = document.getElementById('error-' + field);
            if (span) span.textContent = errors[field][0];
            const input = form.querySelector('[name="' + field + '"]');
            if (input) {
                input.classList.add('is-invalid');
                if (!firstInvalid) firstInvalid = input;
            }
        });
        if (firstInvalid) firstInvalid.focus();
    }

    function showAddProductGeneralError(message) {
        const banner = document.getElementById('addProductGeneralError');
        banner.textContent = message;
        banner.style.display = 'flex';
    }

    function hideAddProductGeneralError() {
        const banner = document.getElementById('addProductGeneralError');
        banner.style.display = 'none';
        banner.textContent = '';
    }

    // Products' index() has its own AJAX branch that always returns
    // {rows, pagination} JSON for any request carrying these headers —
    // including the GET that `store()`'s redirect lands on. That means both
    // a genuine save and the server-side duplicate name/model rejection
    // (`back()->withErrors(...)`) end up producing the exact same JSON
    // shape, so we can't tell them apart from the response alone. Instead we
    // check whether the product we just submitted actually shows up in the
    // freshly-rendered rows — reusing that same response directly (no extra
    // round-trip) instead of the generic scrape-the-HTML helper the other
    // "Add X" modals use.
    function submitAddProductForm(resetSubmitButton) {
        const form = document.getElementById('addProductForm');
        clearAddProductFieldErrors();
        hideAddProductGeneralError();

        const submittedName = form.querySelector('#ProductName').value.trim();

        fetch('{{ route('admin.products.store') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new FormData(form)
        }).then(async function (response) {
            if (response.status === 422) {
                const data = await response.json();
                showAddProductFieldErrors(data.errors || {});
                resetSubmitButton();
                return;
            }

            const data = await response.json();
            const rowsHtml = data.rows || '';
            const escapedName = submittedName.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const created = submittedName.length > 0 && rowsHtml.includes(escapedName);

            if (created) {
                const tbody = document.getElementById('productTbody');
                const paginationWrapper = document.getElementById('paginationWrapper');
                if (tbody) tbody.innerHTML = rowsHtml;
                if (paginationWrapper) paginationWrapper.innerHTML = data.pagination || '';
                closeAddProductModal();
                Swal.fire({
                    title: 'Success',
                    text: 'Product added successfully.',
                    icon: 'success',
                    confirmButtonColor: '#10b981',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                showAddProductGeneralError('A product with this name or model number already exists. Please use a different value.');
                resetSubmitButton();
            }
        }).catch(function () {
            showAddProductGeneralError('A network error occurred. Please try again.');
            resetSubmitButton();
        });
    }

    const addProductForm = window.initProductAddForm('addProductForm', {
        submitBtn: document.getElementById('addProductSubmitBtn'),
        onConfirmedSubmit: submitAddProductForm,
        onCancel: function () {
            closeAddProductModal();
        }
    });

    document.getElementById('addProductSubmitBtn').addEventListener('click', () => addProductForm.confirmSave());
    document.getElementById('addProductCancelBtn').addEventListener('click', () => addProductForm.confirmCancel());

    function handleAddProductModalKeydown(e) {
        const modal = document.getElementById('addProductModal');
        if (!modal.classList.contains('active')) return;

        if (e.key === 'Escape') {
            if (!addProductIsSubmitting()) closeAddProductModal();
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

    window.openAddProductModal = function (event) {
        if (event) event.preventDefault();
        const modal = document.getElementById('addProductModal');
        const form = document.getElementById('addProductForm');

        addProductLastFocused = document.activeElement;
        form.reset();
        clearAddProductFieldErrors();
        hideAddProductGeneralError();
        addProductForm.markUnchanged();
        addProductForm.resetSubmitButton();

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () {
            modal.classList.add('active');
        });

        const firstField = form.querySelector('input, select');
        if (firstField) firstField.focus();

        document.addEventListener('keydown', handleAddProductModalKeydown);
    };

    window.closeAddProductModal = function () {
        const modal = document.getElementById('addProductModal');
        modal.classList.remove('active');
        document.removeEventListener('keydown', handleAddProductModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        if (addProductLastFocused && typeof addProductLastFocused.focus === 'function') {
            addProductLastFocused.focus();
        }
    };

    document.getElementById('addProductModal').addEventListener('mousedown', function (e) {
        if (e.target === this && !addProductIsSubmitting()) {
            closeAddProductModal();
        }
    });
</script>
@endsection
