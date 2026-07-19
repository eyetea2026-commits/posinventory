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
    @include('admin.partials.modal-styles')

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

            @include('admin.stock-adjustments.partials.stock-adjustment-form-fields')

            <div class="modal-footer" style="border-top: 1px solid var(--border); margin-top: 8px;">
                <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Adjustment
                </button>
            </div>
        </form>
    </div>
@endsection
