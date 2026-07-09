@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/Suppliers.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Edit Supplier</h1>
        <p>Update this supplier's contact details</p>
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

        <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}" id="supplierForm">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Supplier Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="SupplierName" class="form-input" value="{{ old('SupplierName', $supplier->SupplierName) }}" required>
                    @error('SupplierName') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Contact Number <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="ContactNumber" class="form-input" value="{{ old('ContactNumber', $supplier->ContactNumber) }}" required>
                    @error('ContactNumber') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Email <span style="color: var(--danger);">*</span></label>
                    <input type="email" name="Email" class="form-input" value="{{ old('Email', $supplier->Email) }}" required>
                    @error('Email') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Address <span style="color: var(--danger);">*</span></label>
                    <textarea name="Address" class="form-textarea" required>{{ old('Address', $supplier->Address) }}</textarea>
                    @error('Address') <span class="form-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="modal-footer" style="border-top: 1px solid var(--border); margin-top: 8px;">
                <a href="{{ route('admin.suppliers.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Supplier
                </button>
            </div>
        </form>
    </div>
@endsection
