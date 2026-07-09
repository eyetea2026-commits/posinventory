@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/PurchaseOrder.css') }}">
    <style>
        .order-item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 16px;
            align-items: end;
            padding: 16px;
            background: var(--bg-hover);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 12px;
        }
        .order-items-section {
            border-top: 1px solid var(--border);
            padding-top: 20px;
            margin-top: 8px;
        }
        .order-items-section h3 {
            margin: 0 0 16px;
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        @media (max-width: 700px) {
            .order-item-row { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@section('header')
    <div class="header-title">
        <h1>Create Purchase Order</h1>
        <p>Order stock from a supplier ahead of receiving it</p>
    </div>
@endsection

@section('content')
    <div class="card" style="max-width: 900px; margin: 0 auto;">
        <div class="card-header">
            <div>
                <h2 class="card-title">Order Details</h2>
                <p class="card-subtitle">Fields marked with an asterisk are required</p>
            </div>
            <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <form method="POST" action="{{ route('admin.purchase-orders.store') }}" id="purchaseOrderForm">
            @csrf

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Supplier <span style="color: var(--danger);">*</span></label>
                    <select name="SupplierID" class="form-select" required>
                        <option value="">Select Supplier</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->SupplierID }}" {{ old('SupplierID') == $supplier->SupplierID ? 'selected' : '' }}>
                                {{ $supplier->SupplierName }}
                            </option>
                        @endforeach
                    </select>
                    @error('SupplierID') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Purchase Date <span style="color: var(--danger);">*</span></label>
                    <input type="date" name="PurchaseDate" class="form-input" value="{{ old('PurchaseDate', now()->toDateString()) }}" required>
                    @error('PurchaseDate') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Status <span style="color: var(--danger);">*</span></label>
                    <select name="Status" class="form-select" required>
                        <option value="pending" {{ old('Status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ old('Status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    </select>
                    @error('Status') <span class="form-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="order-items-section">
                <h3>Order Items</h3>
                @error('products')
                    <span class="form-error" style="display: block; margin-bottom: 12px;">{{ $message }}</span>
                @enderror

                <div id="order-items"></div>

                <template id="order-item-template">
                    <div class="order-item-row">
                        <div class="form-group">
                            <label class="form-label">Product</label>
                            <select name="products[][product_id]" class="form-select" required>
                                <option value="">Select Product</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->ProductID }}">{{ $product->ProductName }} ({{ $product->Model }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="products[][quantity]" min="1" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Unit Price</label>
                            <input type="number" name="products[][unit_price]" step="0.01" min="0" class="form-input" required>
                        </div>
                        <button type="button" onclick="removeOrderItem(this)" class="btn btn-danger btn-icon" title="Remove item">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </template>

                <button type="button" onclick="addOrderItem()" class="btn btn-secondary">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>

            <div class="modal-footer" style="border-top: 1px solid var(--border); margin-top: 24px;">
                <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Order
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    function addOrderItem() {
        const template = document.querySelector('#order-item-template');
        const container = document.querySelector('#order-items');
        const clone = template.content.cloneNode(true);
        container.appendChild(clone);
    }

    function removeOrderItem(button) {
        const row = button.closest('.order-item-row');
        if (document.querySelectorAll('.order-item-row').length > 1) {
            row?.remove();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        addOrderItem();
    });
</script>
@endpush
