@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/PurchaseOrder.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Purchase Orders</h1>
        <p>Manage purchase orders - REQ052 to REQ060</p>
    </div>
@endsection

@section('content')
    @include('admin.partials.modal-styles')

    <div class="card">
        <div class="toolbar">
            <form method="GET" action="{{ route('admin.purchase-orders.index') }}" id="purchaseOrderFilterForm" style="display:flex; gap:10px; flex-wrap:wrap; flex:1;">
                <div class="search-box">
                    <i class="search-icon fas fa-search"></i>
                    <input type="text" name="search" value="{{ $search }}" class="search-input" placeholder="Search by PO number or keyword..." />
                </div>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-input" style="max-width:160px;" title="From date">
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-input" style="max-width:160px;" title="To date">
                <select name="category_id" class="form-select" style="max-width:180px;">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->CategoryID }}" {{ (string) $categoryId === (string) $category->CategoryID ? 'selected' : '' }}>{{ $category->CategoryName }}</option>
                    @endforeach
                </select>
                {{-- No visible Filter button: the date/category fields apply as soon as
                     they change, and the search box still submits on Enter via this
                     visually hidden submit control (removing it entirely would also
                     disable the browser's native Enter-to-submit behavior). --}}
                <button type="submit" aria-hidden="true" tabindex="-1" style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0;">Search</button>
            </form>
            <!-- REQ053: Create purchase order -->
            <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary" onclick="openPurchaseOrderModal(event)">
                <i class="fas fa-plus"></i> New Order
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">
                <i class="fas fa-circle-exclamation"></i>
                {{ session('error') }}
            </div>
        @endif

        <!-- REQ058: Search purchase order & REQ059: View purchase order details -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Supplier</th>
                        <!-- REQ054: Select existing supplier profile -->
                        <th>Order Date</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="purchaseOrdersTbody">
                    @forelse($purchaseOrders as $order)
                        <tr>
                            <td>
                                <span class="badge badge-primary">{{ $order->PONumber }}</span>
                            </td>
                            <td><strong>{{ $order->supplier?->SupplierName ?? 'Unknown' }}</strong></td>
                            <td>{{ \Illuminate\Support\Carbon::parse($order->PurchaseDate)->format('M d, Y') }}</td>
                            <td>{{ $order->items->count() }} items</td>
                            <td>
                                @php
                                    $badgeClass = match($order->Status) {
                                        \App\Models\PurchaseOrder::STATUS_FULLY_RECEIVED => 'badge-success',
                                        \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'badge-warning',
                                        \App\Models\PurchaseOrder::STATUS_APPROVED => 'badge-info',
                                        \App\Models\PurchaseOrder::STATUS_CANCELLED => 'badge-danger',
                                        default => 'badge-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ \App\Models\PurchaseOrder::STATUS_LABELS[$order->Status] ?? ucfirst($order->Status) }}</span>
                            </td>
                            <td>
                                <div class="actions-group">
                                    <!-- REQ059: View purchase order details -->
                                    <a href="{{ route('admin.purchase-orders.show', $order) }}" class="action-btn view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if(in_array($order->Status, \App\Models\PurchaseOrder::EDITABLE_STATUSES, true))
                                        <a href="#" class="action-btn edit" title="Edit" onclick="openEditPurchaseOrderModal(event, {{ $order->PurchaseOrderID }})">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('admin.purchase-orders.export', $order) }}" class="action-btn" title="Export PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-shopping-cart"></i></div>
                                    <p class="empty-title">No Purchase Orders</p>
                                    <p class="empty-text">Create your first purchase order to get started.</p>
                                    <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary">New Order</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($purchaseOrders->hasPages())
            <div class="pagination">
                @if($purchaseOrders->onFirstPage())
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a href="{{ $purchaseOrders->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                @endif

                @foreach($purchaseOrders->getUrlRange(1, $purchaseOrders->lastPage()) as $page => $url)
                    <a href="{{ $url }}" class="pagination-link {{ $page == $purchaseOrders->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach

                @if($purchaseOrders->hasMorePages())
                    <a href="{{ $purchaseOrders->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>

    <!-- Create Purchase Order Modal -->
    <div id="addPurchaseOrderModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="addPurchaseOrderModalTitle" aria-hidden="true">
        <div class="modal-content modal-content-wide">
            <div class="modal-header">
                <h2 id="addPurchaseOrderModalTitle"><i class="fas fa-cart-plus"></i> Create Purchase Order</h2>
                <button type="button" class="modal-close" onclick="closePurchaseOrderModal()" aria-label="Close">&times;</button>
            </div>

            <div id="addPurchaseOrderGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

            <form id="addPurchaseOrderForm">
                @include('admin.purchase-orders.partials.purchase-order-form-fields')
            </form>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="addPurchaseOrderCancelBtn">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="addPurchaseOrderSubmitBtn">
                    <i class="fas fa-save"></i> Save Order
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Purchase Order Modal -->
    <div id="editPurchaseOrderModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editPurchaseOrderModalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="editPurchaseOrderModalTitle"><i class="fas fa-edit"></i> Edit Purchase Order</h2>
                <button type="button" class="modal-close" onclick="closeEditPurchaseOrderModal()" aria-label="Close">&times;</button>
            </div>

            <div id="editPurchaseOrderGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

            <form id="editPurchaseOrderForm">
                <div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>
            </form>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="editPurchaseOrderCancelBtn">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="editPurchaseOrderSubmitBtn">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>

    @include('admin.partials.ajax-modal-form')

    <script>
        // With the Filter button removed, the Start Date / End Date / Category
        // fields apply immediately on change instead of waiting for a click.
        (function () {
            const filterForm = document.getElementById('purchaseOrderFilterForm');
            ['input[name="date_from"]', 'input[name="date_to"]', 'select[name="category_id"]'].forEach(function (selector) {
                const field = filterForm.querySelector(selector);
                if (field) field.addEventListener('change', function () { filterForm.submit(); });
            });
        })();

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

        // ---- Create Purchase Order modal ----
        const ADD_PO_FIELD_IDS = ['SupplierID', 'PurchaseDate', 'ExpectedDeliveryDate', 'Status'];
        let addPurchaseOrderLastFocused = null;

        function addPurchaseOrderIsSubmitting() {
            const btn = document.getElementById('addPurchaseOrderSubmitBtn');
            return btn ? btn.disabled : false;
        }

        function clearAddPurchaseOrderFieldErrors() {
            const form = document.getElementById('addPurchaseOrderForm');
            ADD_PO_FIELD_IDS.forEach(function (field) {
                const span = document.getElementById('error-' + field);
                if (span) span.textContent = '';
                const input = form.querySelector('[name="' + field + '"]');
                if (input) input.classList.remove('error');
            });
            const productsError = document.getElementById('error-products');
            if (productsError) productsError.textContent = '';
        }

        function showAddPurchaseOrderFieldErrors(errors) {
            const form = document.getElementById('addPurchaseOrderForm');
            clearAddPurchaseOrderFieldErrors();
            let firstInvalid = null;
            let productsMessages = [];

            Object.keys(errors).forEach(function (field) {
                if (ADD_PO_FIELD_IDS.indexOf(field) !== -1) {
                    const span = document.getElementById('error-' + field);
                    if (span) span.textContent = errors[field][0];
                    const input = form.querySelector('[name="' + field + '"]');
                    if (input) {
                        input.classList.add('error');
                        if (!firstInvalid) firstInvalid = input;
                    }
                } else {
                    // Dynamic "products.*.product_id" / "products.*.quantity" / "products"
                    // rows have no stable per-row DOM target — surface these under
                    // the Order Items section instead.
                    productsMessages.push(errors[field][0]);
                }
            });

            if (productsMessages.length) {
                const productsError = document.getElementById('error-products');
                if (productsError) productsError.textContent = productsMessages[0];
            }

            if (firstInvalid) firstInvalid.focus();
        }

        function showAddPurchaseOrderGeneralError(message) {
            const banner = document.getElementById('addPurchaseOrderGeneralError');
            banner.textContent = message;
            banner.style.display = 'flex';
        }

        function hideAddPurchaseOrderGeneralError() {
            const banner = document.getElementById('addPurchaseOrderGeneralError');
            banner.style.display = 'none';
            banner.textContent = '';
        }

        function refreshPurchaseOrdersTable(html) {
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const newTbody = parsed.querySelector('#purchaseOrdersTbody');
            const currentTbody = document.getElementById('purchaseOrdersTbody');
            if (newTbody && currentTbody) {
                currentTbody.innerHTML = newTbody.innerHTML;
            }
        }

        function resetAddPurchaseOrderSubmitButton() {
            const btn = document.getElementById('addPurchaseOrderSubmitBtn');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Order';
        }

        window.openPurchaseOrderModal = function (event) {
            if (event) event.preventDefault();
            const modal = document.getElementById('addPurchaseOrderModal');
            const form = document.getElementById('addPurchaseOrderForm');

            addPurchaseOrderLastFocused = document.activeElement;
            form.reset();
            clearAddPurchaseOrderFieldErrors();
            hideAddPurchaseOrderGeneralError();
            resetAddPurchaseOrderSubmitButton();
            if (typeof resetOrderItems === 'function') resetOrderItems();

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            void modal.offsetHeight;
            requestAnimationFrame(function () {
                modal.classList.add('active');
            });

            const firstField = form.querySelector('input, textarea, select');
            if (firstField) firstField.focus();

            document.addEventListener('keydown', handleAddPurchaseOrderModalKeydown);
        };

        window.closePurchaseOrderModal = function () {
            const modal = document.getElementById('addPurchaseOrderModal');
            modal.classList.remove('active');
            document.removeEventListener('keydown', handleAddPurchaseOrderModalKeydown);
            setTimeout(function () { modal.style.display = 'none'; }, 250);
            document.body.style.overflow = '';
            if (addPurchaseOrderLastFocused && typeof addPurchaseOrderLastFocused.focus === 'function') {
                addPurchaseOrderLastFocused.focus();
            }
        };

        function handleAddPurchaseOrderModalKeydown(e) {
            const modal = document.getElementById('addPurchaseOrderModal');
            if (!modal.classList.contains('active')) return;

            if (e.key === 'Escape') {
                if (!addPurchaseOrderIsSubmitting()) closePurchaseOrderModal();
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

        document.getElementById('addPurchaseOrderModal').addEventListener('mousedown', function (e) {
            if (e.target === this && !addPurchaseOrderIsSubmitting()) {
                closePurchaseOrderModal();
            }
        });

        document.getElementById('addPurchaseOrderForm').addEventListener('submit', function (e) { e.preventDefault(); });

        document.getElementById('addPurchaseOrderCancelBtn').addEventListener('click', function () {
            closePurchaseOrderModal();
        });

        document.getElementById('addPurchaseOrderSubmitBtn').addEventListener('click', function () {
            const form = document.getElementById('addPurchaseOrderForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            Swal.fire({
                title: 'Confirm Save',
                text: 'Are you sure you want to create this purchase order?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                const submitBtn = document.getElementById('addPurchaseOrderSubmitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                clearAddPurchaseOrderFieldErrors();
                hideAddPurchaseOrderGeneralError();

                window.submitAjaxForm(form, '{{ route('admin.purchase-orders.store') }}', {
                    onFieldErrors: function (errors) {
                        showAddPurchaseOrderFieldErrors(errors);
                        resetAddPurchaseOrderSubmitButton();
                    },
                    onSuccess: function (html, message) {
                        refreshPurchaseOrdersTable(html);
                        closePurchaseOrderModal();
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
                        showAddPurchaseOrderGeneralError(message);
                        resetAddPurchaseOrderSubmitButton();
                    }
                });
            });
        });

        // ---- Edit Purchase Order modal ----
        const EDIT_PO_FIELD_IDS = ['SupplierID', 'PurchaseDate', 'ExpectedDeliveryDate', 'Notes'];
        let editPurchaseOrderLastFocused = null;
        let currentEditPurchaseOrderId = null;

        function editPurchaseOrderIsSubmitting() {
            const btn = document.getElementById('editPurchaseOrderSubmitBtn');
            return btn ? btn.disabled : false;
        }

        function clearEditPurchaseOrderFieldErrors() {
            const form = document.getElementById('editPurchaseOrderForm');
            EDIT_PO_FIELD_IDS.forEach(function (field) {
                const span = document.getElementById('error-edit-' + field);
                if (span) span.textContent = '';
                const input = form.querySelector('[name="' + field + '"]');
                if (input) input.classList.remove('error');
            });
        }

        function showEditPurchaseOrderFieldErrors(errors) {
            clearEditPurchaseOrderFieldErrors();
            const form = document.getElementById('editPurchaseOrderForm');
            let firstInvalid = null;
            Object.keys(errors).forEach(function (field) {
                const span = document.getElementById('error-edit-' + field);
                if (span) span.textContent = errors[field][0];
                const input = form.querySelector('[name="' + field + '"]');
                if (input) {
                    input.classList.add('error');
                    if (!firstInvalid) firstInvalid = input;
                }
            });
            if (firstInvalid) firstInvalid.focus();
        }

        function showEditPurchaseOrderGeneralError(message) {
            const banner = document.getElementById('editPurchaseOrderGeneralError');
            banner.textContent = message;
            banner.style.display = 'flex';
        }

        function hideEditPurchaseOrderGeneralError() {
            const banner = document.getElementById('editPurchaseOrderGeneralError');
            banner.style.display = 'none';
            banner.textContent = '';
        }

        function resetEditPurchaseOrderSubmitButton() {
            const btn = document.getElementById('editPurchaseOrderSubmitBtn');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        }

        window.openEditPurchaseOrderModal = function (event, purchaseOrderId) {
            if (event) event.preventDefault();
            const modal = document.getElementById('editPurchaseOrderModal');
            const form = document.getElementById('editPurchaseOrderForm');

            editPurchaseOrderLastFocused = document.activeElement;
            currentEditPurchaseOrderId = purchaseOrderId;
            form.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>';
            hideEditPurchaseOrderGeneralError();
            resetEditPurchaseOrderSubmitButton();

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            void modal.offsetHeight;
            requestAnimationFrame(function () { modal.classList.add('active'); });
            document.addEventListener('keydown', handleEditPurchaseOrderModalKeydown);

            fetch('{{ url('admin/purchase-orders') }}/' + purchaseOrderId + '/edit', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    form.innerHTML = data.html;
                    const firstField = form.querySelector('input, select, textarea');
                    if (firstField) firstField.focus();
                })
                .catch(function () {
                    form.innerHTML = '<p class="form-error">Failed to load this purchase order for editing. Please try again.</p>';
                });
        };

        window.closeEditPurchaseOrderModal = function () {
            const modal = document.getElementById('editPurchaseOrderModal');
            modal.classList.remove('active');
            document.removeEventListener('keydown', handleEditPurchaseOrderModalKeydown);
            setTimeout(function () { modal.style.display = 'none'; }, 250);
            document.body.style.overflow = '';
            if (editPurchaseOrderLastFocused && typeof editPurchaseOrderLastFocused.focus === 'function') {
                editPurchaseOrderLastFocused.focus();
            }
        };

        function handleEditPurchaseOrderModalKeydown(e) {
            const modal = document.getElementById('editPurchaseOrderModal');
            if (!modal.classList.contains('active')) return;

            if (e.key === 'Escape') {
                if (!editPurchaseOrderIsSubmitting()) closeEditPurchaseOrderModal();
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

        document.getElementById('editPurchaseOrderModal').addEventListener('mousedown', function (e) {
            if (e.target === this && !editPurchaseOrderIsSubmitting()) {
                closeEditPurchaseOrderModal();
            }
        });

        document.getElementById('editPurchaseOrderForm').addEventListener('submit', function (e) { e.preventDefault(); });

        document.getElementById('editPurchaseOrderCancelBtn').addEventListener('click', function () {
            closeEditPurchaseOrderModal();
        });

        document.getElementById('editPurchaseOrderSubmitBtn').addEventListener('click', function () {
            const form = document.getElementById('editPurchaseOrderForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            Swal.fire({
                title: 'Confirm Changes',
                text: 'Are you sure you want to save the changes to this purchase order?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                const submitBtn = document.getElementById('editPurchaseOrderSubmitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                clearEditPurchaseOrderFieldErrors();
                hideEditPurchaseOrderGeneralError();

                window.submitAjaxForm(form, '{{ url('admin/purchase-orders') }}/' + currentEditPurchaseOrderId, {
                    onFieldErrors: function (errors) {
                        showEditPurchaseOrderFieldErrors(errors);
                        resetEditPurchaseOrderSubmitButton();
                    },
                    onSuccess: function (html, message) {
                        refreshPurchaseOrdersTable(html);
                        closeEditPurchaseOrderModal();
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
                        showEditPurchaseOrderGeneralError(message);
                        resetEditPurchaseOrderSubmitButton();
                    }
                });
            });
        });
    </script>
@endsection