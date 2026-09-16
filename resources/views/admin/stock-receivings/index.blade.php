@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/StockReceiving.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Stock Receiving</h1>
        <p>Restock products when new supplies are received</p>
    </div>
@endsection

@section('content')
    @include('admin.partials.modal-styles')

    <style>
        /* View Details Modal — mirrors the read-only detail-row/items-table
           styling used by the Purchase Order module's own View Details
           modal (admin/purchase-orders/index.blade.php), duplicated here per
           the same established convention. */
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
            gap: 16px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            color: #94a3b8;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            flex: 0 0 150px;
        }
        .detail-value {
            color: #f8fafc;
            font-size: 0.9rem;
            font-weight: 500;
            text-align: right;
            flex: 1;
        }
        .section-title {
            color: #cbd5e1;
            font-size: 0.95rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 20px 0 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.15);
        }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th {
            text-align: left; padding: 10px 12px; font-size: 0.72rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;
            border-bottom: 1px solid rgba(148, 163, 184, 0.15);
        }
        .items-table td {
            padding: 10px 12px; color: #f8fafc; font-size: 0.85rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.08);
        }
        .items-table tbody tr:last-child td { border-bottom: none; }

        /* Pending/Completed tabs — same design as the Discount module's
           tabs (admin/discounts/index.blade.php). */
        .tabs-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
        .chart-toggle-group { display: inline-flex; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(148, 163, 184, 0.15); border-radius: 12px; padding: 4px; gap: 4px; }
        .chart-toggle-btn {
            padding: 9px 18px; border-radius: 9px; border: none; background: transparent; color: #94a3b8;
            font-weight: 600; font-size: 0.88rem; cursor: pointer; transition: all 0.2s ease;
        }
        .chart-toggle-btn.active { background: linear-gradient(135deg, #3b82f6, #10b981); color: white; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
    </style>

    <div class="card">
        <div class="toolbar">
            <div class="search-box">
                <i class="search-icon fas fa-search"></i>
                <form method="GET" action="{{ route('admin.stock-receivings.index') }}" class="w-full">
                    <input type="text" name="search" value="{{ $search }}" class="search-input" placeholder="Search received products or suppliers..." />
                </form>
            </div>
            <!-- REQ037: Restock product -->
            <a href="{{ route('admin.stock-receivings.create') }}" class="btn btn-primary" onclick="openReceivingModal(event)">
                <i class="fas fa-plus"></i> Record Receipt
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

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Receipt Number</th>
                        <th>Product</th>
                        <!-- REQ038 & REQ039: Select existing product and supplier -->
                        <th>Supplier</th>
                        <th>Quantity</th>
                        <th>Date Received</th>
                    </tr>
                </thead>
                <tbody id="receivingsTbody">
                    @forelse($receivings as $receipt)
                        <tr>
                            <td>
                                <span class="badge badge-info">{{ $receipt->ReceiptNumber }}</span>
                            </td>
                            <td><strong>{{ $receipt->product?->ProductName ?? 'Unknown' }}</strong></td>
                            <td>{{ $receipt->supplier?->SupplierName ?? 'Unknown' }}</td>
                            <td>{{ number_format($receipt->Quantity) }} units</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($receipt->DateReceived)->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-clipboard-list"></i></div>
                                    <p class="empty-title">No Stock Receivings</p>
                                    <p class="empty-text">Record your first stock receipt to get started.</p>
                                    <a href="{{ route('admin.stock-receivings.create') }}" class="btn btn-primary">Record Receipt</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($receivings->hasPages())
            <div class="pagination">
                @if($receivings->onFirstPage())
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a href="{{ $receivings->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                @endif

                @foreach($receivings->getUrlRange(1, $receivings->lastPage()) as $page => $url)
                    <a href="{{ $url }}" class="pagination-link {{ $page == $receivings->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach

                @if($receivings->hasMorePages())
                    <a href="{{ $receivings->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>

    {{-- Purchase Orders sent to receiving via Print (see
         PurchaseOrderController::printPreview()) land here, split by their
         StockReceivingBatch's own Status — both tabs are rendered up front
         and just toggled client-side, same as the Discount module. --}}
    <div class="card mt-4">
        <div class="tabs-header">
            <h2 style="margin:0;">Purchase Order Deliveries</h2>
            <div class="chart-toggle-group">
                <button type="button" class="chart-toggle-btn active" data-tab="pending" onclick="switchReceivingTab('pending')">
                    Pending / Expected Delivery ({{ $pendingBatches->count() }})
                </button>
                <button type="button" class="chart-toggle-btn" data-tab="completed" onclick="switchReceivingTab('completed')">
                    Completed ({{ $completedBatches->count() }})
                </button>
            </div>
        </div>

        <div id="tab-pending" class="tab-panel active">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Supplier</th>
                            <th>Items</th>
                            <th>Expected Delivery</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingBatches as $batch)
                            <tr>
                                <td><span class="badge badge-primary">{{ $batch->purchaseOrder->PONumber }}</span></td>
                                <td><strong>{{ $batch->purchaseOrder->supplier?->SupplierName ?? 'Unknown' }}</strong></td>
                                <td>{{ $batch->purchaseOrder->items->count() }} items</td>
                                <td>{{ $batch->purchaseOrder->ExpectedDeliveryDate ? \Illuminate\Support\Carbon::parse($batch->purchaseOrder->ExpectedDeliveryDate)->format('M d, Y') : 'Not set' }}</td>
                                <td>
                                    <div class="actions-group">
                                        <a href="#" class="action-btn view" title="View Details" onclick="openBatchDetailsModal(event, {{ $batch->StockReceivingBatchID }})">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-icon"><i class="fas fa-truck"></i></div>
                                        <p class="empty-title">No Pending Deliveries</p>
                                        <p class="empty-text">Purchase Orders sent to receiving (Print inside View Details) will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-completed" class="tab-panel">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>PO Number</th>
                            <th>Supplier</th>
                            <th>Items</th>
                            <th>Completed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($completedBatches as $batch)
                            <tr>
                                <td><span class="badge badge-primary">{{ $batch->purchaseOrder->PONumber }}</span></td>
                                <td><strong>{{ $batch->purchaseOrder->supplier?->SupplierName ?? 'Unknown' }}</strong></td>
                                <td>{{ $batch->purchaseOrder->items->count() }} items</td>
                                <td>{{ $batch->CompletedAt?->format('M d, Y g:i A') ?? 'N/A' }}</td>
                                <td>
                                    <div class="actions-group">
                                        <a href="#" class="action-btn view" title="View Details" onclick="openBatchDetailsModal(event, {{ $batch->StockReceivingBatchID }})">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-icon"><i class="fas fa-box-open"></i></div>
                                        <p class="empty-title">No Completed Deliveries</p>
                                        <p class="empty-text">Deliveries appear here once "Add to Inventory" succeeds.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Purchase Order Delivery — View Details Modal -->
    <div id="batchDetailsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="batchDetailsModalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="batchDetailsModalTitle"><i class="fas fa-eye"></i> Delivery Details</h2>
                <button type="button" class="modal-close" onclick="closeBatchDetailsModal()" aria-label="Close">&times;</button>
            </div>

            <div id="batchDetailsGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

            <div id="batchDetailsBody">
                <div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="batchDetailsCloseBtn">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <!-- Record Stock Receiving Modal -->
    <div id="addReceivingModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="addReceivingModalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="addReceivingModalTitle"><i class="fas fa-clipboard-list"></i> Record Stock Receiving</h2>
                <button type="button" class="modal-close" onclick="closeReceivingModal()" aria-label="Close">&times;</button>
            </div>

            <div id="addReceivingGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

            <form id="addReceivingForm">
                @include('admin.stock-receivings.partials.stock-receiving-form-fields')
            </form>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="addReceivingCancelBtn">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="addReceivingSubmitBtn">
                    <i class="fas fa-save"></i> Record Receipt
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

        // ---- Pending / Completed tabs ----
        window.switchReceivingTab = function (tab) {
            document.querySelectorAll('.chart-toggle-btn[data-tab]').forEach(function (btn) {
                btn.classList.toggle('active', btn.dataset.tab === tab);
            });
            document.getElementById('tab-pending').classList.toggle('active', tab === 'pending');
            document.getElementById('tab-completed').classList.toggle('active', tab === 'completed');
        };

        // ---- Purchase Order Delivery — View Details modal ----
        let batchDetailsLastFocused = null;

        function showBatchDetailsGeneralError(message) {
            const banner = document.getElementById('batchDetailsGeneralError');
            banner.textContent = message;
            banner.style.display = 'flex';
        }

        function hideBatchDetailsGeneralError() {
            const banner = document.getElementById('batchDetailsGeneralError');
            banner.style.display = 'none';
            banner.textContent = '';
        }

        function handleBatchDetailsModalKeydown(e) {
            const modal = document.getElementById('batchDetailsModal');
            if (!modal.classList.contains('active')) return;
            if (e.key === 'Escape') closeBatchDetailsModal();
        }

        window.openBatchDetailsModal = function (event, batchId) {
            if (event) event.preventDefault();
            const modal = document.getElementById('batchDetailsModal');
            if (modal.classList.contains('active')) return; // prevent duplicate modals

            const body = document.getElementById('batchDetailsBody');

            batchDetailsLastFocused = document.activeElement;
            body.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>';
            hideBatchDetailsGeneralError();

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            void modal.offsetHeight;
            requestAnimationFrame(function () { modal.classList.add('active'); });
            document.addEventListener('keydown', handleBatchDetailsModalKeydown);

            fetch('{{ url('admin/stock-receivings/batches') }}/' + batchId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) { body.innerHTML = data.html; })
                .catch(function () {
                    body.innerHTML = '<p class="form-error">Failed to load this delivery. Please try again.</p>';
                });
        };

        window.closeBatchDetailsModal = function () {
            const modal = document.getElementById('batchDetailsModal');
            modal.classList.remove('active');
            document.removeEventListener('keydown', handleBatchDetailsModalKeydown);
            setTimeout(function () { modal.style.display = 'none'; }, 250);
            document.body.style.overflow = '';
            if (batchDetailsLastFocused && typeof batchDetailsLastFocused.focus === 'function') {
                batchDetailsLastFocused.focus();
            }
        };

        document.getElementById('batchDetailsModal').addEventListener('mousedown', function (e) {
            if (e.target === this) closeBatchDetailsModal();
        });
        document.getElementById('batchDetailsCloseBtn').addEventListener('click', closeBatchDetailsModal);

        // Add to Inventory — a real (non-AJAX) form submission: the backend
        // redirects back to this same page with a flash message either way
        // (see StockReceivingController::addToInventory()), which the
        // "Auto-show session messages" block above already turns into a
        // Swal, and the reloaded page's Pending/Completed tabs reflect the
        // outcome directly — no extra client-side state to keep in sync.
        window.submitAddToInventory = function (batchId) {
            const form = document.getElementById('batchReceivingForm');
            if (!form || !form.checkValidity()) {
                if (form) form.reportValidity();
                return;
            }

            Swal.fire({
                title: 'Add to Inventory',
                text: 'This will add the received quantities to Inventory and mark this delivery Completed. Continue?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Add to Inventory',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                const btn = document.getElementById('addToInventoryBtn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                }

                form.submit();
            });
        };

        // ---- Record Stock Receiving modal ----
        const ADD_RECEIVING_FIELD_IDS = ['ProductID', 'SupplierID', 'Quantity', 'ReceiptNumber', 'DateReceived'];
        let addReceivingLastFocused = null;

        function addReceivingIsSubmitting() {
            const btn = document.getElementById('addReceivingSubmitBtn');
            return btn ? btn.disabled : false;
        }

        function clearAddReceivingFieldErrors() {
            const form = document.getElementById('addReceivingForm');
            ADD_RECEIVING_FIELD_IDS.forEach(function (field) {
                const span = document.getElementById('error-' + field);
                if (span) span.textContent = '';
                const input = form.querySelector('[name="' + field + '"]');
                if (input) input.classList.remove('error');
            });
        }

        function showAddReceivingFieldErrors(errors) {
            const form = document.getElementById('addReceivingForm');
            clearAddReceivingFieldErrors();
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

        function showAddReceivingGeneralError(message) {
            const banner = document.getElementById('addReceivingGeneralError');
            banner.textContent = message;
            banner.style.display = 'flex';
        }

        function hideAddReceivingGeneralError() {
            const banner = document.getElementById('addReceivingGeneralError');
            banner.style.display = 'none';
            banner.textContent = '';
        }

        function refreshReceivingsTable(html) {
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const newTbody = parsed.querySelector('#receivingsTbody');
            const currentTbody = document.getElementById('receivingsTbody');
            if (newTbody && currentTbody) {
                currentTbody.innerHTML = newTbody.innerHTML;
            }
        }

        function resetAddReceivingSubmitButton() {
            const btn = document.getElementById('addReceivingSubmitBtn');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Record Receipt';
        }

        window.openReceivingModal = function (event) {
            if (event) event.preventDefault();
            const modal = document.getElementById('addReceivingModal');
            const form = document.getElementById('addReceivingForm');

            addReceivingLastFocused = document.activeElement;
            form.reset();
            clearAddReceivingFieldErrors();
            hideAddReceivingGeneralError();
            resetAddReceivingSubmitButton();

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            void modal.offsetHeight;
            requestAnimationFrame(function () {
                modal.classList.add('active');
            });

            const firstField = form.querySelector('input, textarea, select');
            if (firstField) firstField.focus();

            document.addEventListener('keydown', handleAddReceivingModalKeydown);
        };

        window.closeReceivingModal = function () {
            const modal = document.getElementById('addReceivingModal');
            modal.classList.remove('active');
            document.removeEventListener('keydown', handleAddReceivingModalKeydown);
            setTimeout(function () { modal.style.display = 'none'; }, 250);
            document.body.style.overflow = '';
            if (addReceivingLastFocused && typeof addReceivingLastFocused.focus === 'function') {
                addReceivingLastFocused.focus();
            }
        };

        function handleAddReceivingModalKeydown(e) {
            const modal = document.getElementById('addReceivingModal');
            if (!modal.classList.contains('active')) return;

            if (e.key === 'Escape') {
                if (!addReceivingIsSubmitting()) closeReceivingModal();
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

        document.getElementById('addReceivingModal').addEventListener('mousedown', function (e) {
            if (e.target === this && !addReceivingIsSubmitting()) {
                closeReceivingModal();
            }
        });

        document.getElementById('addReceivingForm').addEventListener('submit', function (e) { e.preventDefault(); });

        document.getElementById('addReceivingCancelBtn').addEventListener('click', function () {
            closeReceivingModal();
        });

        document.getElementById('addReceivingSubmitBtn').addEventListener('click', function () {
            const form = document.getElementById('addReceivingForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            Swal.fire({
                title: 'Confirm Save',
                text: 'Are you sure you want to record this stock receiving?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                const submitBtn = document.getElementById('addReceivingSubmitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                clearAddReceivingFieldErrors();
                hideAddReceivingGeneralError();

                window.submitAjaxForm(form, '{{ route('admin.stock-receivings.store') }}', {
                    onFieldErrors: function (errors) {
                        showAddReceivingFieldErrors(errors);
                        resetAddReceivingSubmitButton();
                    },
                    onSuccess: function (html, message) {
                        refreshReceivingsTable(html);
                        closeReceivingModal();
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
                        showAddReceivingGeneralError(message);
                        resetAddReceivingSubmitButton();
                    }
                });
            });
        });
    </script>
@endsection