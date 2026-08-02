@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/Suppliers.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Add Supplier</h1>
        <p>Register a new supplier for purchase orders and stock receiving</p>
    </div>
@endsection

@section('content')
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Supplier Details</h2>
                <p class="card-subtitle">Fields marked with an asterisk are required</p>
            </div>
            <a href="{{ route('admin.suppliers.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <form method="POST" action="{{ route('admin.suppliers.store') }}" id="supplierForm">
            @csrf

            <div class="form-grid">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Supplier Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="SupplierName" id="SupplierName" class="form-input" value="{{ old('SupplierName') }}" required>
                    <span class="form-error" id="error-SupplierName">@error('SupplierName'){{ $message }}@enderror</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="ContactPerson" class="form-input" value="{{ old('ContactPerson') }}">
                    @error('ContactPerson') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Contact Number <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="ContactNumber" class="form-input" value="{{ old('ContactNumber') }}" required>
                    @error('ContactNumber') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Email <span style="color: var(--danger);">*</span></label>
                    <input type="email" name="Email" id="Email" class="form-input" value="{{ old('Email') }}" required>
                    <span class="form-error" id="error-Email">@error('Email'){{ $message }}@enderror</span>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Address <span style="color: var(--danger);">*</span></label>
                    <textarea name="Address" class="form-textarea" required>{{ old('Address') }}</textarea>
                    @error('Address') <span class="form-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="modal-footer" style="border-top: 1px solid var(--border); margin-top: 8px;">
                <a href="{{ route('admin.suppliers.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Supplier
                </button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const nameInput = document.getElementById('SupplierName');
            const emailInput = document.getElementById('Email');
            const nameError = document.getElementById('error-SupplierName');
            const emailError = document.getElementById('error-Email');
            let timer = null;

            function checkDuplicate() {
                fetch('{{ route('admin.suppliers.check-name') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ SupplierName: nameInput.value, Email: emailInput.value }),
                })
                    .then((r) => r.json())
                    .then((data) => {
                        if (data.name_value === nameInput.value) {
                            nameError.textContent = data.name ? 'A supplier with this name already exists.' : '';
                            nameInput.classList.toggle('error', data.name);
                        }
                        if (data.email_value === emailInput.value) {
                            emailError.textContent = data.email ? 'A supplier with this email already exists.' : '';
                            emailInput.classList.toggle('error', data.email);
                        }
                    });
            }

            [nameInput, emailInput].forEach((el) => el.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(checkDuplicate, 400);
            }));
        })();
    </script>
@endsection
