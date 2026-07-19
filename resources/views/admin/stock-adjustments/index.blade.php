@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/StockAdjustment.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Stock Adjustments</h1>
        <p>Adjust product quantities - REQ041 to REQ043</p>
    </div>
@endsection

@section('content')
    @include('admin.partials.modal-styles')

    <div class="card">
        <div class="toolbar">
            <div class="search-box">
                <i class="search-icon fas fa-search"></i>
                <form method="GET" action="{{ route('admin.stock-adjustments.index') }}" class="w-full">
                    <input type="text" name="search" value="{{ $search }}" class="search-input" placeholder="Search adjustments..." />
                </form>
            </div>
            <!-- REQ041: Adjust product for adjustment -->
            <a href="{{ route('admin.stock-adjustments.create') }}" class="btn btn-primary" onclick="openAdjustmentModal(event)">
                <i class="fas fa-plus"></i> New Adjustment
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
                        <th>Date</th>
                        <th>Product</th>
                        <!-- REQ042: Select existing product for adjustment -->
                        <!-- REQ043: Increase or decrease quantity -->
                        <th>Adjustment</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody id="adjustmentsTbody">
                    @forelse($adjustments as $adjustment)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($adjustment->Date)->format('M d, Y') }}</td>
                            <td><strong>{{ $adjustment->product?->ProductName ?? 'Unknown' }}</strong></td>
                            <td>
                                @if($adjustment->QuantityAdjust > 0)
                                    <span class="badge badge-success">+{{ $adjustment->QuantityAdjust }}</span>
                                @else
                                    <span class="badge badge-danger">{{ $adjustment->QuantityAdjust }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-info">{{ $adjustment->Reason }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-sliders-h"></i></div>
                                    <p class="empty-title">No Stock Adjustments</p>
                                    <p class="empty-text">Create your first stock adjustment to get started.</p>
                                    <a href="{{ route('admin.stock-adjustments.create') }}" class="btn btn-primary">New Adjustment</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($adjustments->hasPages())
            <div class="pagination">
                @if($adjustments->onFirstPage())
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a href="{{ $adjustments->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                @endif

                @foreach($adjustments->getUrlRange(1, $adjustments->lastPage()) as $page => $url)
                    <a href="{{ $url }}" class="pagination-link {{ $page == $adjustments->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach

                @if($adjustments->hasMorePages())
                    <a href="{{ $adjustments->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>

    <!-- New Stock Adjustment Modal -->
    <div id="addAdjustmentModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="addAdjustmentModalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="addAdjustmentModalTitle"><i class="fas fa-sliders-h"></i> New Stock Adjustment</h2>
                <button type="button" class="modal-close" onclick="closeAdjustmentModal()" aria-label="Close">&times;</button>
            </div>

            <div id="addAdjustmentGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

            <form id="addAdjustmentForm">
                @include('admin.stock-adjustments.partials.stock-adjustment-form-fields')
            </form>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="addAdjustmentCancelBtn">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="addAdjustmentSubmitBtn">
                    <i class="fas fa-save"></i> Save Adjustment
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

        // ---- New Stock Adjustment modal ----
        const ADD_ADJUSTMENT_FIELD_IDS = ['ProductID', 'QuantityAdjust', 'Date', 'Reason'];
        let addAdjustmentLastFocused = null;

        function addAdjustmentIsSubmitting() {
            const btn = document.getElementById('addAdjustmentSubmitBtn');
            return btn ? btn.disabled : false;
        }

        function clearAddAdjustmentFieldErrors() {
            const form = document.getElementById('addAdjustmentForm');
            ADD_ADJUSTMENT_FIELD_IDS.forEach(function (field) {
                const span = document.getElementById('error-' + field);
                if (span) span.textContent = '';
                const input = form.querySelector('[name="' + field + '"]');
                if (input) input.classList.remove('error');
            });
        }

        function showAddAdjustmentFieldErrors(errors) {
            const form = document.getElementById('addAdjustmentForm');
            clearAddAdjustmentFieldErrors();
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

        function showAddAdjustmentGeneralError(message) {
            const banner = document.getElementById('addAdjustmentGeneralError');
            banner.textContent = message;
            banner.style.display = 'flex';
        }

        function hideAddAdjustmentGeneralError() {
            const banner = document.getElementById('addAdjustmentGeneralError');
            banner.style.display = 'none';
            banner.textContent = '';
        }

        function refreshAdjustmentsTable(html) {
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const newTbody = parsed.querySelector('#adjustmentsTbody');
            const currentTbody = document.getElementById('adjustmentsTbody');
            if (newTbody && currentTbody) {
                currentTbody.innerHTML = newTbody.innerHTML;
            }
        }

        function resetAddAdjustmentSubmitButton() {
            const btn = document.getElementById('addAdjustmentSubmitBtn');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Adjustment';
        }

        window.openAdjustmentModal = function (event) {
            if (event) event.preventDefault();
            const modal = document.getElementById('addAdjustmentModal');
            const form = document.getElementById('addAdjustmentForm');

            addAdjustmentLastFocused = document.activeElement;
            form.reset();
            clearAddAdjustmentFieldErrors();
            hideAddAdjustmentGeneralError();
            resetAddAdjustmentSubmitButton();

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            void modal.offsetHeight;
            requestAnimationFrame(function () {
                modal.classList.add('active');
            });

            const firstField = form.querySelector('input, textarea, select');
            if (firstField) firstField.focus();

            document.addEventListener('keydown', handleAddAdjustmentModalKeydown);
        };

        window.closeAdjustmentModal = function () {
            const modal = document.getElementById('addAdjustmentModal');
            modal.classList.remove('active');
            document.removeEventListener('keydown', handleAddAdjustmentModalKeydown);
            setTimeout(function () { modal.style.display = 'none'; }, 250);
            document.body.style.overflow = '';
            if (addAdjustmentLastFocused && typeof addAdjustmentLastFocused.focus === 'function') {
                addAdjustmentLastFocused.focus();
            }
        };

        function handleAddAdjustmentModalKeydown(e) {
            const modal = document.getElementById('addAdjustmentModal');
            if (!modal.classList.contains('active')) return;

            if (e.key === 'Escape') {
                if (!addAdjustmentIsSubmitting()) closeAdjustmentModal();
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

        document.getElementById('addAdjustmentModal').addEventListener('mousedown', function (e) {
            if (e.target === this && !addAdjustmentIsSubmitting()) {
                closeAdjustmentModal();
            }
        });

        document.getElementById('addAdjustmentForm').addEventListener('submit', function (e) { e.preventDefault(); });

        document.getElementById('addAdjustmentCancelBtn').addEventListener('click', function () {
            closeAdjustmentModal();
        });

        document.getElementById('addAdjustmentSubmitBtn').addEventListener('click', function () {
            const form = document.getElementById('addAdjustmentForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            Swal.fire({
                title: 'Confirm Save',
                text: 'Are you sure you want to save this stock adjustment?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                const submitBtn = document.getElementById('addAdjustmentSubmitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                clearAddAdjustmentFieldErrors();
                hideAddAdjustmentGeneralError();

                window.submitAjaxForm(form, '{{ route('admin.stock-adjustments.store') }}', {
                    onFieldErrors: function (errors) {
                        showAddAdjustmentFieldErrors(errors);
                        resetAddAdjustmentSubmitButton();
                    },
                    onSuccess: function (html, message) {
                        refreshAdjustmentsTable(html);
                        closeAdjustmentModal();
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
                        showAddAdjustmentGeneralError(message);
                        resetAddAdjustmentSubmitButton();
                    }
                });
            });
        });
    </script>
@endsection