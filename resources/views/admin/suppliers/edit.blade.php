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

            @include('admin.suppliers.partials.supplier-form-fields', ['supplier' => $supplier])

            <div class="modal-footer" style="border-top: 1px solid var(--border); margin-top: 8px;">
                <a href="{{ route('admin.suppliers.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Supplier
                </button>
            </div>
        </form>
    </div>
@endsection
