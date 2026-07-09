@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/SalesReturns.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>Create Sales Return</h1>
        <p>Record a returned item against a past transaction</p>
    </div>
@endsection

@section('content')
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Return Details</h2>
                <p class="card-subtitle">Fields marked with an asterisk are required</p>
            </div>
            <a href="{{ route('admin.sales-returns.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <form method="POST" action="{{ route('admin.sales-returns.store') }}" id="salesReturnForm">
            @csrf

            <div class="form-grid">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Sales Transaction <span style="color: var(--danger);">*</span></label>
                    <select name="SalesTransactionID" class="form-select" required>
                        <option value="">Select Transaction</option>
                        @foreach($transactions as $transaction)
                            <option value="{{ $transaction->SalesTransactionID }}" {{ old('SalesTransactionID') == $transaction->SalesTransactionID ? 'selected' : '' }}>
                                #{{ $transaction->SalesTransactionID }} &mdash; {{ \Illuminate\Support\Carbon::parse($transaction->SalesTransactionDate)->format('M d, Y') }}
                            </option>
                        @endforeach
                    </select>
                    @error('SalesTransactionID') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Product <span style="color: var(--danger);">*</span></label>
                    <select name="ProductID" class="form-select" required>
                        <option value="">Select Product</option>
                        @foreach($products as $product)
                            <option value="{{ $product->ProductID }}" {{ old('ProductID') == $product->ProductID ? 'selected' : '' }}>
                                {{ $product->ProductName }} ({{ $product->Model }})
                            </option>
                        @endforeach
                    </select>
                    @error('ProductID') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Quantity <span style="color: var(--danger);">*</span></label>
                    <input type="number" name="Quantity" class="form-input" value="{{ old('Quantity') }}" min="1" required>
                    @error('Quantity') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Return Date <span style="color: var(--danger);">*</span></label>
                    <input type="date" name="ReturnDate" class="form-input" value="{{ old('ReturnDate', now()->toDateString()) }}" required>
                    @error('ReturnDate') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Reason <span style="color: var(--danger);">*</span></label>
                    <textarea name="Reason" class="form-textarea" required>{{ old('Reason') }}</textarea>
                    @error('Reason') <span class="form-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="modal-footer" style="border-top: 1px solid var(--border); margin-top: 8px;">
                <a href="{{ route('admin.sales-returns.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Submit Return
                </button>
            </div>
        </form>
    </div>
@endsection
