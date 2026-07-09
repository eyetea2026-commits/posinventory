@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/StockAdjustment.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <h1>New Stock Adjustment</h1>
        <p>Increase or decrease a product's quantity with a recorded reason</p>
    </div>
@endsection

@section('content')
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Adjustment Details</h2>
                <p class="card-subtitle">Fields marked with an asterisk are required</p>
            </div>
            <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <form method="POST" action="{{ route('admin.stock-adjustments.store') }}" id="adjustmentForm">
            @csrf

            <div class="form-grid">
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
                    <label class="form-label">Quantity Adjusted <span style="color: var(--danger);">*</span></label>
                    <input type="number" name="QuantityAdjust" class="form-input" value="{{ old('QuantityAdjust') }}" required placeholder="e.g., 5 or -5">
                    @error('QuantityAdjust') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Date <span style="color: var(--danger);">*</span></label>
                    <input type="date" name="Date" class="form-input" value="{{ old('Date', now()->toDateString()) }}" required>
                    @error('Date') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Reason <span style="color: var(--danger);">*</span></label>
                    <textarea name="Reason" class="form-textarea" required>{{ old('Reason') }}</textarea>
                    @error('Reason') <span class="form-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="modal-footer" style="border-top: 1px solid var(--border); margin-top: 8px;">
                <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Adjustment
                </button>
            </div>
        </form>
    </div>
@endsection
