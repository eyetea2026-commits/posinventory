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
            <div class="search-box">
                <i class="search-icon fas fa-search"></i>
                <form method="GET" action="{{ route('admin.purchase-orders.index') }}" class="w-full">
                    <input type="text" name="search" value="{{ $search }}" class="search-input" placeholder="Search by purchase number or keyword..." />
                </form>
            </div>
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
                                <span class="badge badge-primary">#{{ $order->PurchaseOrderID }}</span>
                            </td>
                            <td><strong>{{ $order->supplier?->SupplierName ?? 'Unknown' }}</strong></td>
                            <td>{{ \Illuminate\Support\Carbon::parse($order->PurchaseDate)->format('M d, Y') }}</td>
                            <td>{{ $order->items->count() }} items</td>
                            <td>
                                @if($order->Status === 'completed')
                                    <span class="badge badge-success">Completed</span>
                                @elseif($order->Status === 'pending')
                                    <span class="badge badge-warning">Pending</span>
                                @else
                                    <span class="badge badge-secondary">{{ ucfirst($order->Status) }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions-group">
                                    <!-- REQ059: View purchase order details -->
                                    <a href="{{ route('admin.purchase-orders.show', $order) }}" class="action-btn view" title="View Details">
                                        <i class="fas fa-eye"></i>
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

    @include('admin.partials.ajax-modal-form')

    <script>
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
    </script>
@endsection