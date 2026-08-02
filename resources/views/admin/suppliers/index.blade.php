@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/Suppliers.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Supplier Profile Management</h1>
        <p>Manage supplier profiles - REQ079 to REQ083</p>
    </div>
@endsection

@section('content')
    <!-- REQ081: Search existing supplier profile -->
    <div class="card">
        <div class="toolbar">
            <div class="search-box">
                <i class="search-icon fas fa-search"></i>
                <form method="GET" action="{{ route('admin.suppliers.index') }}" class="w-full">
                    <input type="text" name="search" value="{{ $search }}" class="search-input" placeholder="Search suppliers by name or contact..." />
                </form>
            </div>
            <!-- REQ080: Create supplier profile -->
            <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Supplier
            </a>
        </div>

        @if(session('status'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                {{ session('status') }}
            </div>
        @endif

        <!-- REQ082: View supplier profile details -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Supplier Name</th>
                        <th>Contact Number</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="suppliersTbody">
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td>
                                <strong>{{ $supplier->SupplierName }}</strong>
                            </td>
                            <td>{{ $supplier->ContactNumber }}</td>
                            <td>{{ $supplier->Email }}</td>
                            <td>{{ $supplier->Address }}</td>
                            <td>
                                <span class="badge {{ $supplier->Status === 'inactive' ? 'badge-secondary' : 'badge-success' }}">{{ ucfirst($supplier->Status ?? 'active') }}</span>
                            </td>
                            <td>
                                <div class="actions-group">
                                    <button
                                        type="button"
                                        onclick="openSupplierHistoryModal({{ $supplier->SupplierID }}, '{{ addslashes($supplier->SupplierName) }}')"
                                        class="action-btn view"
                                        style="border: none; cursor: pointer;"
                                        title="View History"
                                    >
                                        <i class="fas fa-clock-rotate-left"></i>
                                    </button>
                                    <!-- REQ083: Update supplier profile details -->
                                    <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="action-btn edit" title="Edit" onclick="openEditSupplierModal(event, {{ $supplier->SupplierID }})">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-truck"></i></div>
                                    <p class="empty-title">No Suppliers Found</p>
                                    <p class="empty-text">Add your first supplier to get started.</p>
                                    <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary">Add Supplier</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($suppliers->hasPages())
            <div class="pagination">
                @if($suppliers->onFirstPage())
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a href="{{ $suppliers->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                @endif

                @foreach($suppliers->getUrlRange(1, $suppliers->lastPage()) as $page => $url)
                    <a href="{{ $url }}" class="pagination-link {{ $page == $suppliers->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach

                @if($suppliers->hasMorePages())
                    <a href="{{ $suppliers->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>

    @include('admin.partials.modal-styles')

    <!-- Edit Supplier Modal -->
    <div id="editSupplierModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editSupplierModalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="editSupplierModalTitle"><i class="fas fa-truck"></i> Edit Supplier</h2>
                <button type="button" class="modal-close" onclick="closeEditSupplierModal()" aria-label="Close">&times;</button>
            </div>

            <div id="editSupplierGeneralError" class="form-error-banner" style="display:none;" role="alert"></div>

            <form id="editSupplierForm">
                <div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>
            </form>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="editSupplierCancelBtn">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="editSupplierSubmitBtn">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>

    @include('admin.partials.ajax-modal-form')
    @include('admin.suppliers.partials.history-modal')

    <script>
        const EDIT_SUPPLIER_FIELD_IDS = ['SupplierName', 'ContactPerson', 'ContactNumber', 'Email', 'Address'];
        let editSupplierLastFocused = null;
        let currentEditSupplierId = null;

        function editSupplierIsSubmitting() {
            const btn = document.getElementById('editSupplierSubmitBtn');
            return btn ? btn.disabled : false;
        }

        function clearEditSupplierFieldErrors() {
            const form = document.getElementById('editSupplierForm');
            EDIT_SUPPLIER_FIELD_IDS.forEach(function (field) {
                const span = document.getElementById('error-' + field);
                if (span) span.textContent = '';
                const input = form.querySelector('[name="' + field + '"]');
                if (input) input.classList.remove('error');
            });
        }

        function showEditSupplierFieldErrors(errors) {
            const form = document.getElementById('editSupplierForm');
            clearEditSupplierFieldErrors();
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

        function showEditSupplierGeneralError(message) {
            const banner = document.getElementById('editSupplierGeneralError');
            banner.textContent = message;
            banner.style.display = 'flex';
        }

        function hideEditSupplierGeneralError() {
            const banner = document.getElementById('editSupplierGeneralError');
            banner.style.display = 'none';
            banner.textContent = '';
        }

        function resetEditSupplierSubmitButton() {
            const btn = document.getElementById('editSupplierSubmitBtn');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        }

        function refreshSuppliersTable(html) {
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const newTbody = parsed.querySelector('#suppliersTbody');
            const currentTbody = document.getElementById('suppliersTbody');
            if (newTbody && currentTbody) {
                currentTbody.innerHTML = newTbody.innerHTML;
            }
        }

        function handleEditSupplierModalKeydown(e) {
            const modal = document.getElementById('editSupplierModal');
            if (!modal.classList.contains('active')) return;

            if (e.key === 'Escape') {
                if (!editSupplierIsSubmitting()) closeEditSupplierModal();
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

        window.openEditSupplierModal = function (event, supplierId) {
            if (event) event.preventDefault();
            const modal = document.getElementById('editSupplierModal');
            const form = document.getElementById('editSupplierForm');

            editSupplierLastFocused = document.activeElement || editSupplierLastFocused;
            currentEditSupplierId = supplierId;
            form.innerHTML = '<div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>';
            hideEditSupplierGeneralError();
            resetEditSupplierSubmitButton();

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            void modal.offsetHeight;
            requestAnimationFrame(function () { modal.classList.add('active'); });
            document.addEventListener('keydown', handleEditSupplierModalKeydown);

            fetch('{{ url('admin/suppliers') }}/' + supplierId + '/edit', {
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
                    form.innerHTML = '<p class="form-error">Failed to load supplier for editing. Please try again.</p>';
                });
        };

        window.closeEditSupplierModal = function () {
            const modal = document.getElementById('editSupplierModal');
            modal.classList.remove('active');
            document.removeEventListener('keydown', handleEditSupplierModalKeydown);
            setTimeout(function () { modal.style.display = 'none'; }, 250);
            document.body.style.overflow = '';
            if (editSupplierLastFocused && typeof editSupplierLastFocused.focus === 'function') {
                editSupplierLastFocused.focus();
            }
        };

        document.getElementById('editSupplierModal').addEventListener('mousedown', function (e) {
            if (e.target === this && !editSupplierIsSubmitting()) {
                closeEditSupplierModal();
            }
        });

        document.getElementById('editSupplierCancelBtn').addEventListener('click', function () {
            closeEditSupplierModal();
        });

        document.getElementById('editSupplierSubmitBtn').addEventListener('click', function () {
            const form = document.getElementById('editSupplierForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            Swal.fire({
                title: 'Confirm Update',
                text: 'Are you sure you want to save the changes to this supplier?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                const submitBtn = document.getElementById('editSupplierSubmitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                clearEditSupplierFieldErrors();
                hideEditSupplierGeneralError();

                window.submitAjaxForm(form, '{{ url('admin/suppliers') }}/' + currentEditSupplierId, {
                    onFieldErrors: function (errors) {
                        showEditSupplierFieldErrors(errors);
                        resetEditSupplierSubmitButton();
                    },
                    onSuccess: function (html, message) {
                        refreshSuppliersTable(html);
                        closeEditSupplierModal();
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
                        showEditSupplierGeneralError(message);
                        resetEditSupplierSubmitButton();
                    }
                });
            });
        });

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

        // Live duplicate name/email check for the Edit Supplier modal —
        // delegated on document since #SupplierName/#Email are only in the
        // DOM once the modal's form fields have been fetched and injected.
        (function () {
            let checkTimer = null;

            document.addEventListener('input', function (e) {
                if (e.target.id !== 'SupplierName' && e.target.id !== 'Email') return;

                clearTimeout(checkTimer);
                checkTimer = setTimeout(function () {
                    const nameInput = document.getElementById('SupplierName');
                    const emailInput = document.getElementById('Email');
                    const nameError = document.getElementById('error-SupplierName');
                    const emailError = document.getElementById('error-Email');
                    if (!nameInput || !emailInput) return;

                    fetch('{{ route('admin.suppliers.check-name') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            SupplierName: nameInput.value,
                            Email: emailInput.value,
                            exclude_id: currentEditSupplierId,
                        }),
                    })
                        .then((r) => r.json())
                        .then((data) => {
                            if (nameError && data.name_value === nameInput.value) {
                                nameError.textContent = data.name ? 'A supplier with this name already exists.' : '';
                                nameInput.classList.toggle('error', data.name);
                            }
                            if (emailError && data.email_value === emailInput.value) {
                                emailError.textContent = data.email ? 'A supplier with this email already exists.' : '';
                                emailInput.classList.toggle('error', data.email);
                            }
                        });
                }, 400);
            });
        })();
    </script>
@endsection