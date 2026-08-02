@extends('admin.layout')

@section('title', 'Categories - CCTV Express')

@section('header')
    <div class="header-title">
        <h1>Product Categories</h1>
        <p>Organize products into categories</p>
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
    /* Search Bar — matches Products/Inventory */
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
    }
    .search-input::placeholder {
        color: #64748b;
    }
    .search-input:focus {
        outline: none;
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
    .category-name-cell {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }
    .category-description {
        font-size: 0.85rem;
        color: var(--text-muted);
        line-height: 1.45;
        word-break: break-word;
        white-space: normal;
    }

    /* Add Category Modal */
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
    textarea.form-control { min-height: 90px; resize: vertical; }
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
        <form method="GET" action="{{ route('admin.categories.index') }}" id="filterForm" class="search-box-wrapper" autocomplete="off">
            <i class="search-icon fas fa-search"></i>
            <input
                type="text"
                name="search"
                id="searchInput"
                value="{{ $search ?? '' }}"
                class="search-input"
                placeholder="Search categories by name..."
            />
        </form>
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary" onclick="openAddCategoryModal(event)" title="Create Category">
            <i class="fa-solid fa-plus"></i> Create Category
        </a>
    </div>
    <div class="card-body">
        <div class="table-loader" id="tableLoader"><i class="fas fa-spinner fa-spin"></i></div>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Products Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="categoriesTbody">
                @include('admin.categories.partials.rows')
            </tbody>
        </table>
        <div id="paginationWrapper">
            @include('admin.categories.partials.pagination')
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div id="addCategoryModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="addCategoryModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="addCategoryModalTitle"><i class="fa-solid fa-folder-plus"></i> Create New Category</h2>
            <button type="button" class="modal-close" onclick="closeAddCategoryModal()" aria-label="Close">&times;</button>
        </div>

        <div id="addCategoryGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="addCategoryForm">
            {{-- Explicit category=>null guards against $category leaking in
                 from the @forelse($categories as $category) table loop above
                 (Blade @include merges parent scope for any key not passed
                 here) — without this, the Add modal would render pre-filled
                 with whichever category was last in the table. --}}
            @include('admin.categories.partials.category-form-fields', ['category' => null])
        </form>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="addCategoryCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="addCategorySubmitBtn">
                <i class="fas fa-save"></i> Save Category
            </button>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editCategoryModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editCategoryModalTitle" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="editCategoryModalTitle"><i class="fa-solid fa-folder-open"></i> Edit Category</h2>
            <button type="button" class="modal-close" onclick="closeEditCategoryModal()" aria-label="Close">&times;</button>
        </div>

        <div id="editCategoryGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

        <form id="editCategoryForm">
            <div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>
        </form>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="editCategoryCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-primary" id="editCategorySubmitBtn">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
</div>

@include('admin.partials.ajax-modal-form')

<script>
    function confirmDelete(categoryId) {
        Swal.fire({
            title: 'Confirm Delete',
            text: 'Are you sure you want to delete this category? This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm' + categoryId).submit();
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

    // ---- Add Category modal ----
    const ADD_CATEGORY_FIELD_IDS = ['CategoryName', 'Description'];
    let addCategoryLastFocused = null;
    let addCategoryFormChanged = false;

    function addCategoryIsSubmitting() {
        const btn = document.getElementById('addCategorySubmitBtn');
        return btn ? btn.disabled : false;
    }

    function clearAddCategoryFieldErrors() {
        const form = document.getElementById('addCategoryForm');
        ADD_CATEGORY_FIELD_IDS.forEach(function (field) {
            const span = document.getElementById('error-' + field);
            if (span) span.textContent = '';
            const input = form.querySelector('[name="' + field + '"]');
            if (input) input.classList.remove('error');
        });
    }

    function showAddCategoryFieldErrors(errors) {
        const form = document.getElementById('addCategoryForm');
        clearAddCategoryFieldErrors();
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

    function showAddCategoryGeneralError(message) {
        const banner = document.getElementById('addCategoryGeneralError');
        banner.textContent = message;
        banner.style.display = 'flex';
    }

    function hideAddCategoryGeneralError() {
        const banner = document.getElementById('addCategoryGeneralError');
        banner.style.display = 'none';
        banner.textContent = '';
    }

    function refreshCategoriesTable(html) {
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const newTbody = parsed.querySelector('#categoriesTbody');
        const currentTbody = document.getElementById('categoriesTbody');
        if (newTbody && currentTbody) {
            currentTbody.innerHTML = newTbody.innerHTML;
        }
        const newPagination = parsed.querySelector('#paginationWrapper');
        const currentPagination = document.getElementById('paginationWrapper');
        if (newPagination && currentPagination) {
            currentPagination.innerHTML = newPagination.innerHTML;
        }
    }

    // ---- Real-time debounced search (matches Products/Inventory) ----
    (function () {
        const filterForm = document.getElementById('filterForm');
        const searchInput = document.getElementById('searchInput');
        const tableLoader = document.getElementById('tableLoader');
        const tbody = document.getElementById('categoriesTbody');
        const paginationWrapper = document.getElementById('paginationWrapper');

        let debounceTimer = null;
        let currentController = null;

        function buildQuery(page = 1) {
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
                    <tr><td colspan="4">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div>
                            <p class="empty-title">Unable to load categories</p>
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

        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            applyFilters(1);
        });

        rebindPagination();
    })();

    function resetAddCategorySubmitButton() {
        const btn = document.getElementById('addCategorySubmitBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Category';
    }

    window.openAddCategoryModal = function (event) {
        if (event) event.preventDefault();
        const modal = document.getElementById('addCategoryModal');
        const form = document.getElementById('addCategoryForm');

        addCategoryLastFocused = document.activeElement;
        form.reset();
        clearAddCategoryFieldErrors();
        hideAddCategoryGeneralError();
        resetAddCategorySubmitButton();
        addCategoryFormChanged = false;

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () {
            modal.classList.add('active');
        });

        const firstField = form.querySelector('input, textarea, select');
        if (firstField) firstField.focus();

        document.addEventListener('keydown', handleAddCategoryModalKeydown);
    };

    window.closeAddCategoryModal = function () {
        const modal = document.getElementById('addCategoryModal');
        modal.classList.remove('active');
        document.removeEventListener('keydown', handleAddCategoryModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        if (addCategoryLastFocused && typeof addCategoryLastFocused.focus === 'function') {
            addCategoryLastFocused.focus();
        }
    };

    function handleAddCategoryModalKeydown(e) {
        const modal = document.getElementById('addCategoryModal');
        if (!modal.classList.contains('active')) return;

        if (e.key === 'Escape') {
            if (!addCategoryIsSubmitting()) closeAddCategoryModal();
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

    document.getElementById('addCategoryModal').addEventListener('mousedown', function (e) {
        if (e.target === this && !addCategoryIsSubmitting()) {
            closeAddCategoryModal();
        }
    });

    document.getElementById('addCategoryForm').addEventListener('submit', function (e) { e.preventDefault(); });
    document.getElementById('addCategoryForm').querySelectorAll('input, textarea').forEach(function (input) {
        input.addEventListener('input', function () { addCategoryFormChanged = true; });
    });

    document.getElementById('addCategoryCancelBtn').addEventListener('click', function () {
        closeAddCategoryModal();
    });

    document.getElementById('addCategorySubmitBtn').addEventListener('click', function () {
        const form = document.getElementById('addCategoryForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Confirm Save',
            text: 'Are you sure you want to save this category?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            const submitBtn = document.getElementById('addCategorySubmitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            clearAddCategoryFieldErrors();
            hideAddCategoryGeneralError();

            window.submitAjaxForm(form, '{{ route('admin.categories.store') }}', {
                onFieldErrors: function (errors) {
                    showAddCategoryFieldErrors(errors);
                    resetAddCategorySubmitButton();
                },
                onSuccess: function (html, message) {
                    refreshCategoriesTable(html);
                    closeAddCategoryModal();
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
                    showAddCategoryGeneralError(message);
                    resetAddCategorySubmitButton();
                }
            });
        });
    });

    // ---- Edit Category modal ----
    let editCategoryLastFocused = null;
    let currentEditCategoryId = null;

    function editCategoryIsSubmitting() {
        const btn = document.getElementById('editCategorySubmitBtn');
        return btn ? btn.disabled : false;
    }

    function clearEditCategoryFieldErrors() {
        const form = document.getElementById('editCategoryForm');
        ADD_CATEGORY_FIELD_IDS.forEach(function (field) {
            const span = document.getElementById('error-' + field);
            if (span) span.textContent = '';
            const input = form.querySelector('[name="' + field + '"]');
            if (input) input.classList.remove('error');
        });
    }

    function showEditCategoryFieldErrors(errors) {
        const form = document.getElementById('editCategoryForm');
        clearEditCategoryFieldErrors();
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

    function showEditCategoryGeneralError(message) {
        const banner = document.getElementById('editCategoryGeneralError');
        banner.textContent = message;
        banner.style.display = 'flex';
    }

    function hideEditCategoryGeneralError() {
        const banner = document.getElementById('editCategoryGeneralError');
        banner.style.display = 'none';
        banner.textContent = '';
    }

    function resetEditCategorySubmitButton() {
        const btn = document.getElementById('editCategorySubmitBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
    }

    function handleEditCategoryModalKeydown(e) {
        const modal = document.getElementById('editCategoryModal');
        if (!modal.classList.contains('active')) return;

        if (e.key === 'Escape') {
            if (!editCategoryIsSubmitting()) closeEditCategoryModal();
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

    window.openEditCategoryModal = function (event, categoryId) {
        if (event) event.preventDefault();
        const modal = document.getElementById('editCategoryModal');
        const form = document.getElementById('editCategoryForm');

        editCategoryLastFocused = document.activeElement || editCategoryLastFocused;
        currentEditCategoryId = categoryId;
        form.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>';
        hideEditCategoryGeneralError();
        resetEditCategorySubmitButton();

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('active'); });
        document.addEventListener('keydown', handleEditCategoryModalKeydown);

        fetch('{{ url('admin/categories') }}/' + categoryId + '/edit', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                form.innerHTML = data.html;
                // submitAjaxForm always POSTs — this hidden field is Laravel's
                // standard method-spoofing so it still routes to update().
                form.insertAdjacentHTML('beforeend', '<input type="hidden" name="_method" value="PUT">');
                const firstField = form.querySelector('input, textarea, select');
                if (firstField) firstField.focus();
            })
            .catch(function () {
                form.innerHTML = '<p class="error">Failed to load category for editing. Please try again.</p>';
            });
    };

    window.closeEditCategoryModal = function () {
        const modal = document.getElementById('editCategoryModal');
        modal.classList.remove('active');
        document.removeEventListener('keydown', handleEditCategoryModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        if (editCategoryLastFocused && typeof editCategoryLastFocused.focus === 'function') {
            editCategoryLastFocused.focus();
        }
    };

    document.getElementById('editCategoryModal').addEventListener('mousedown', function (e) {
        if (e.target === this && !editCategoryIsSubmitting()) {
            closeEditCategoryModal();
        }
    });

    document.getElementById('editCategoryCancelBtn').addEventListener('click', function () {
        closeEditCategoryModal();
    });

    document.getElementById('editCategorySubmitBtn').addEventListener('click', function () {
        const form = document.getElementById('editCategoryForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Confirm Update',
            text: 'Are you sure you want to save the changes to this category?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            const submitBtn = document.getElementById('editCategorySubmitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            clearEditCategoryFieldErrors();
            hideEditCategoryGeneralError();

            window.submitAjaxForm(form, '{{ url('admin/categories') }}/' + currentEditCategoryId, {
                onFieldErrors: function (errors) {
                    showEditCategoryFieldErrors(errors);
                    resetEditCategorySubmitButton();
                },
                onSuccess: function (html, message) {
                    refreshCategoriesTable(html);
                    closeEditCategoryModal();
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
                    showEditCategoryGeneralError(message);
                    resetEditCategorySubmitButton();
                }
            });
        });
    });

    // Live duplicate-name check (mirrors the Products/Users pattern) —
    // delegated on document since the Add and Edit modals' #CategoryName
    // fields aren't necessarily both present/static in the DOM at once.
    (function () {
        let checkTimer = null;

        document.addEventListener('input', function (e) {
            if (e.target.id !== 'CategoryName') return;
            const input = e.target;
            const errorEl = document.getElementById('error-CategoryName');
            if (!errorEl) return;

            clearTimeout(checkTimer);
            checkTimer = setTimeout(function () {
                fetch('{{ route('admin.categories.check-name') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        CategoryName: input.value,
                        exclude_id: currentEditCategoryId,
                    }),
                })
                    .then((r) => r.json())
                    .then((data) => {
                        if (data.name_value !== input.value) return; // stale response
                        if (data.name) {
                            errorEl.textContent = 'A category with this name already exists.';
                            input.classList.add('error');
                        } else {
                            errorEl.textContent = '';
                            input.classList.remove('error');
                        }
                    });
            }, 400);
        });
    })();
</script>
@endsection