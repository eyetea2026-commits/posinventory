@extends('admin.layout')

@section('title', 'Brands - CCTV Express')

@section('header')
    <div class="header-title">
        <h1>Brand Management</h1>
        <p>Manage brands and the category each one belongs to</p>
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
        background: rgba(148, 163, 184, 0.12);
        color: #cbd5e1;
        border: 1px solid rgba(148, 163, 184, 0.2);
    }
    .btn-secondary:hover {
        background: rgba(148, 163, 184, 0.2);
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
    .btn-edit {
        background: rgba(59, 130, 246, 0.15);
        color: #93c5fd;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }
    .btn-edit:hover {
        background: rgba(59, 130, 246, 0.25);
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
    .filters-group {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        flex: 1;
    }
    .search-box-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        flex: 1;
        min-width: 220px;
        max-width: 420px;
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
    }
    .search-input::placeholder {
        color: #64748b;
    }
    .search-input:focus {
        outline: none;
    }
    .category-filter-select {
        padding: 11px 14px;
        background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(59, 130, 246, 0.15);
        border-radius: 12px;
        color: #f8fafc;
        font-size: 0.9rem;
        min-width: 180px;
    }
    .table-loader {
        display: none;
        padding: 16px;
        text-align: center;
        color: var(--text-muted);
    }
    .table-loader.active {
        display: block;
    }
    .pagination {
        display: flex;
        gap: 6px;
        justify-content: center;
        padding: 16px;
        flex-wrap: wrap;
    }
    .pagination-link {
        min-width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 10px;
        border-radius: 8px;
        background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(59, 130, 246, 0.15);
        color: #cbd5e1;
        text-decoration: none;
        font-size: 0.9rem;
    }
    .pagination-link.active {
        background: linear-gradient(135deg, #3b82f6, #10b981);
        color: white;
        border-color: transparent;
    }
    .pagination-link.disabled {
        opacity: 0.4;
        pointer-events: none;
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
    .badge-success {
        background: rgba(16, 185, 129, 0.15);
        color: #6ee7b7;
    }
    .badge-warning {
        background: rgba(245, 158, 11, 0.15);
        color: #fcd34d;
    }
    .badge-danger {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
    }

    /* Add/Edit Brand Modal */
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
    .detail-row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid rgba(148, 163, 184, 0.08); }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { color: var(--text-muted); font-size: 0.85rem; }
    .detail-value { color: #f8fafc; font-weight: 600; text-align: right; }
    .product-chip-list { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; max-width: 60%; }
    .product-chip {
        background: rgba(59, 130, 246, 0.12);
        color: #93c5fd;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 0.78rem;
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
        <form method="GET" action="{{ route('admin.brands.index') }}" id="filterForm" class="filters-group" autocomplete="off">
            <div class="search-box-wrapper">
                <i class="search-icon fas fa-search"></i>
                <input
                    type="text"
                    name="search"
                    id="searchInput"
                    value="{{ $search ?? '' }}"
                    class="search-input"
                    placeholder="Search brands or category..."
                />
            </div>
            <select name="category_id" id="categoryFilterSelect" class="category-filter-select">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->CategoryID }}" @selected(($categoryId ?? null) == $category->CategoryID)>
                        {{ $category->CategoryName }}
                    </option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('admin.brands.create') }}" class="btn btn-primary" onclick="openAddBrandModal(event)" title="Add Brand">
            <i class="fa-solid fa-plus"></i> Add Brand
        </a>
    </div>
    <div class="card-body">
        <div class="table-loader" id="tableLoader"><i class="fas fa-spinner fa-spin"></i></div>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Brand Name</th>
                    <th>Category</th>
                    <th>Products Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="brandsTbody">
                @include('admin.brands.partials.rows')
            </tbody>
        </table>
        <div id="paginationWrapper">
            @include('admin.brands.partials.pagination')
        </div>
    </div>
</div>

<!-- Add Brand Modal -->
<div id="addBrandModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="addBrandModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="addBrandModalTitle"><i class="fa-solid fa-tag"></i> Add New Brand</h2>
            <button type="button" class="modal-close" onclick="closeAddBrandModal()" aria-label="Close">&times;</button>
        </div>

        <div id="addBrandGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="addBrandForm">
            {{-- Explicit brand=>null guards against $brand leaking in from
                 the @forelse($brands as $brand) table loop above. --}}
            @include('admin.brands.partials.brand-form-fields', ['brand' => null])
        </form>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="addBrandCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="addBrandSubmitBtn">
                <i class="fas fa-save"></i> Save Brand
            </button>
        </div>
    </div>
</div>

<!-- Edit Brand Modal -->
<div id="editBrandModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editBrandModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="editBrandModalTitle"><i class="fa-solid fa-tag"></i> Edit Brand</h2>
            <button type="button" class="modal-close" onclick="closeEditBrandModal()" aria-label="Close">&times;</button>
        </div>

        <div id="editBrandGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="editBrandForm">
            <div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>
        </form>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="editBrandCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="editBrandSubmitBtn">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- View Brand Details Modal -->
<div id="viewBrandModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="viewBrandModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="viewBrandModalTitle"><i class="fa-solid fa-circle-info"></i> Brand Details</h2>
            <button type="button" class="modal-close" onclick="closeViewBrandModal()" aria-label="Close">&times;</button>
        </div>
        <div id="viewBrandBody">
            <p class="empty-text">Loading...</p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeViewBrandModal()">Close</button>
        </div>
    </div>
</div>

@include('admin.partials.ajax-modal-form')

<script>
    function escapeHtmlBrand(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function confirmDeleteBrand(brandId) {
        Swal.fire({
            title: 'Confirm Delete',
            text: 'Are you sure you want to delete this brand? This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteBrandForm' + brandId).submit();
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

    // ---- View Brand modal ----
    window.openViewBrandModal = function (brandId) {
        const modal = document.getElementById('viewBrandModal');
        const body = document.getElementById('viewBrandBody');
        body.innerHTML = '<p class="empty-text">Loading...</p>';
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('active'); });

        fetch('{{ url('admin/brands') }}/' + brandId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const b = data.brand;
                const products = (b.Products || []).map(function (p) {
                    return '<span class="product-chip">' + escapeHtmlBrand(p) + '</span>';
                }).join('') || '<span class="detail-value" style="text-align:left;">No products yet.</span>';

                body.innerHTML = `
                    <div class="detail-row">
                        <span class="detail-label">Brand Name</span>
                        <span class="detail-value">${escapeHtmlBrand(b.BrandName)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Category</span>
                        <span class="detail-value">${escapeHtmlBrand(b.CategoryName)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Products Using This Brand</span>
                        <span class="detail-value">${b.ProductCount}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Products</span>
                        <span class="product-chip-list">${products}</span>
                    </div>
                `;
            })
            .catch(function () {
                body.innerHTML = '<p class="empty-text">Failed to load brand details.</p>';
            });
    };

    window.closeViewBrandModal = function () {
        const modal = document.getElementById('viewBrandModal');
        modal.classList.remove('active');
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
    };

    document.getElementById('viewBrandModal').addEventListener('mousedown', function (e) {
        if (e.target === this) window.closeViewBrandModal();
    });

    function refreshBrandsTable(html) {
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const newTbody = parsed.querySelector('#brandsTbody');
        const currentTbody = document.getElementById('brandsTbody');
        if (newTbody && currentTbody) {
            currentTbody.innerHTML = newTbody.innerHTML;
        }
        const newPagination = parsed.querySelector('#paginationWrapper');
        const currentPagination = document.getElementById('paginationWrapper');
        if (newPagination && currentPagination) {
            currentPagination.innerHTML = newPagination.innerHTML;
        }
    }

    // ---- Real-time debounced search + category filter ----
    (function () {
        const filterForm = document.getElementById('filterForm');
        const searchInput = document.getElementById('searchInput');
        const categorySelect = document.getElementById('categoryFilterSelect');
        const tableLoader = document.getElementById('tableLoader');
        const tbody = document.getElementById('brandsTbody');
        const paginationWrapper = document.getElementById('paginationWrapper');

        let debounceTimer = null;
        let currentController = null;

        function buildQuery(page = 1) {
            const params = new URLSearchParams();
            const search = searchInput.value.trim();
            if (search) params.set('search', search);
            if (categorySelect.value) params.set('category_id', categorySelect.value);
            if (page > 1) params.set('page', page);
            return params.toString();
        }

        async function applyFilters(page = 1) {
            const query = buildQuery(page);
            const url = `${filterForm.action}${query ? '?' + query : ''}`;
            const fetchUrl = url + (query ? '&' : '?') + 'ajax=1';
            window.history.replaceState({}, '', url);
            tableLoader.classList.add('active');

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
                tbody.innerHTML = `
                    <tr><td colspan="5">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div>
                            <p class="empty-title">Unable to load brands</p>
                            <p class="empty-text">Please try again.</p>
                        </div>
                    </td></tr>`;
                paginationWrapper.innerHTML = '';
            } finally {
                tableLoader.classList.remove('active');
            }
        }

        function rebindPagination() {
            paginationWrapper.querySelectorAll('a.pagination-link').forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const url = new URL(this.href);
                    applyFilters(url.searchParams.get('page') || 1);
                });
            });
        }

        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => applyFilters(1), 300);
        });

        categorySelect.addEventListener('change', function () {
            applyFilters(1);
        });

        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            applyFilters(1);
        });

        rebindPagination();
    })();

    // ---- Add Brand modal ----
    const ADD_BRAND_FIELD_IDS = ['BrandName', 'CategoryID'];
    let addBrandLastFocused = null;

    function addBrandIsSubmitting() {
        const btn = document.getElementById('addBrandSubmitBtn');
        return btn ? btn.disabled : false;
    }

    function clearAddBrandFieldErrors() {
        const form = document.getElementById('addBrandForm');
        ADD_BRAND_FIELD_IDS.forEach(function (field) {
            const span = document.getElementById('error-' + field);
            if (span) span.textContent = '';
            const input = form.querySelector('[name="' + field + '"]');
            if (input) input.classList.remove('error');
        });
    }

    function showAddBrandFieldErrors(errors) {
        const form = document.getElementById('addBrandForm');
        clearAddBrandFieldErrors();
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

    function showAddBrandGeneralError(message) {
        const banner = document.getElementById('addBrandGeneralError');
        banner.textContent = message;
        banner.style.display = 'flex';
    }

    function hideAddBrandGeneralError() {
        const banner = document.getElementById('addBrandGeneralError');
        banner.style.display = 'none';
        banner.textContent = '';
    }

    function resetAddBrandSubmitButton() {
        const btn = document.getElementById('addBrandSubmitBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Brand';
    }

    window.openAddBrandModal = function (event) {
        if (event) event.preventDefault();
        const modal = document.getElementById('addBrandModal');
        const form = document.getElementById('addBrandForm');

        addBrandLastFocused = document.activeElement;
        form.reset();
        clearAddBrandFieldErrors();
        hideAddBrandGeneralError();
        resetAddBrandSubmitButton();

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('active'); });

        const firstField = form.querySelector('input, textarea, select');
        if (firstField) firstField.focus();

        document.addEventListener('keydown', handleAddBrandModalKeydown);
    };

    window.closeAddBrandModal = function () {
        const modal = document.getElementById('addBrandModal');
        modal.classList.remove('active');
        document.removeEventListener('keydown', handleAddBrandModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        if (addBrandLastFocused && typeof addBrandLastFocused.focus === 'function') {
            addBrandLastFocused.focus();
        }
    };

    function handleAddBrandModalKeydown(e) {
        if (e.key === 'Escape' && !addBrandIsSubmitting()) closeAddBrandModal();
    }

    document.getElementById('addBrandModal').addEventListener('mousedown', function (e) {
        if (e.target === this && !addBrandIsSubmitting()) closeAddBrandModal();
    });

    document.getElementById('addBrandForm').addEventListener('submit', function (e) { e.preventDefault(); });

    document.getElementById('addBrandCancelBtn').addEventListener('click', function () {
        closeAddBrandModal();
    });

    document.getElementById('addBrandSubmitBtn').addEventListener('click', function () {
        const form = document.getElementById('addBrandForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Confirm Save',
            text: 'Are you sure you want to save this brand?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            const submitBtn = document.getElementById('addBrandSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            clearAddBrandFieldErrors();
            hideAddBrandGeneralError();

            window.submitAjaxForm(form, '{{ route('admin.brands.store') }}', {
                onFieldErrors: function (errors) {
                    showAddBrandFieldErrors(errors);
                    resetAddBrandSubmitButton();
                },
                onSuccess: function (html, message) {
                    refreshBrandsTable(html);
                    closeAddBrandModal();
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
                    showAddBrandGeneralError(message);
                    resetAddBrandSubmitButton();
                }
            });
        });
    });

    // ---- Edit Brand modal ----
    let editBrandLastFocused = null;
    let currentEditBrandId = null;

    function editBrandIsSubmitting() {
        const btn = document.getElementById('editBrandSubmitBtn');
        return btn ? btn.disabled : false;
    }

    function clearEditBrandFieldErrors() {
        const form = document.getElementById('editBrandForm');
        ADD_BRAND_FIELD_IDS.forEach(function (field) {
            const span = document.getElementById('error-' + field);
            if (span) span.textContent = '';
            const input = form.querySelector('[name="' + field + '"]');
            if (input) input.classList.remove('error');
        });
    }

    function showEditBrandFieldErrors(errors) {
        const form = document.getElementById('editBrandForm');
        clearEditBrandFieldErrors();
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

    function showEditBrandGeneralError(message) {
        const banner = document.getElementById('editBrandGeneralError');
        banner.textContent = message;
        banner.style.display = 'flex';
    }

    function hideEditBrandGeneralError() {
        const banner = document.getElementById('editBrandGeneralError');
        banner.style.display = 'none';
        banner.textContent = '';
    }

    function resetEditBrandSubmitButton() {
        const btn = document.getElementById('editBrandSubmitBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
    }

    function handleEditBrandModalKeydown(e) {
        if (e.key === 'Escape' && !editBrandIsSubmitting()) closeEditBrandModal();
    }

    window.openEditBrandModal = function (event, brandId) {
        if (event) event.preventDefault();
        const modal = document.getElementById('editBrandModal');
        const form = document.getElementById('editBrandForm');

        editBrandLastFocused = document.activeElement || editBrandLastFocused;
        currentEditBrandId = brandId;
        form.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>';
        hideEditBrandGeneralError();
        resetEditBrandSubmitButton();

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('active'); });
        document.addEventListener('keydown', handleEditBrandModalKeydown);

        fetch('{{ url('admin/brands') }}/' + brandId + '/edit', {
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
                form.innerHTML = '<p class="error">Failed to load brand for editing. Please try again.</p>';
            });
    };

    window.closeEditBrandModal = function () {
        const modal = document.getElementById('editBrandModal');
        modal.classList.remove('active');
        document.removeEventListener('keydown', handleEditBrandModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        if (editBrandLastFocused && typeof editBrandLastFocused.focus === 'function') {
            editBrandLastFocused.focus();
        }
    };

    document.getElementById('editBrandModal').addEventListener('mousedown', function (e) {
        if (e.target === this && !editBrandIsSubmitting()) closeEditBrandModal();
    });

    document.getElementById('editBrandCancelBtn').addEventListener('click', function () {
        closeEditBrandModal();
    });

    document.getElementById('editBrandSubmitBtn').addEventListener('click', function () {
        const form = document.getElementById('editBrandForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Confirm Update',
            text: 'Are you sure you want to save the changes to this brand?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            const submitBtn = document.getElementById('editBrandSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            clearEditBrandFieldErrors();
            hideEditBrandGeneralError();

            window.submitAjaxForm(form, '{{ url('admin/brands') }}/' + currentEditBrandId, {
                onFieldErrors: function (errors) {
                    showEditBrandFieldErrors(errors);
                    resetEditBrandSubmitButton();
                },
                onSuccess: function (html, message) {
                    refreshBrandsTable(html);
                    closeEditBrandModal();
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
                    showEditBrandGeneralError(message);
                    resetEditBrandSubmitButton();
                }
            });
        });
    });
</script>
@endsection
