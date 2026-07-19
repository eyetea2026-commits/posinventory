@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/StockReceiving.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Stock Receiving</h1>
        <p>Restock products when new supplies are received - REQ037 to REQ040</p>
    </div>
@endsection

@section('content')
    @include('admin.partials.modal-styles')

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