@extends('admin.layout')

@section('header')
    <div class="header-title">
        <h1>Product Management</h1>
    </div>
@endsection

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<style>
    :root {
        --glass-bg: rgba(15, 23, 42, 0.7);
        --glass-border: rgba(148, 163, 184, 0.1);
        --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        --primary: #3b82f6;
        --success: #10b981;
    }

    .glass-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        box-shadow: var(--glass-shadow);
        backdrop-filter: blur(10px);
        padding: 32px;
        max-width: 800px;
        margin: 0 auto;
    }

    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .content-header h1 {
        margin: 0;
        font-size: 1.75rem;
        color: var(--text-primary);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #cbd5e1;
        font-size: 0.9rem;
    }

    .form-label .required {
        color: #ef4444;
    }

    .form-input, .form-select {
        width: 100%;
        padding: 14px 16px;
        background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 12px;
        color: #f8fafc;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    .form-input.is-invalid, .form-select.is-invalid {
        border-color: rgba(239, 68, 68, 0.5);
    }

    .form-select {
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 20px;
        padding-right: 40px;
    }

    textarea.form-input {
        min-height: 100px;
        resize: vertical;
    }

    .form-error {
        display: block;
        margin-top: 8px;
        color: #fca5a5;
        font-size: 0.85rem;
    }

    /* Computed Fields */
    .computed-fields {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-top: 8px;
    }

    .computed-field {
        padding: 16px;
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid rgba(59, 130, 246, 0.2);
        border-radius: 12px;
        text-align: center;
    }

    .computed-field label {
        display: block;
        font-size: 0.75rem;
        color: #64748b;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter: 0.05em;
    }

    .computed-field .value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #10b981;
    }

    .computed-field .value.negative {
        color: #ef4444;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid var(--glass-border);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 14px 28px;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        font-size: 0.95rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--success));
        color: white;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }

    .btn-secondary {
        background: rgba(148, 163, 184, 0.15);
        color: #e2e8f0;
        border: 1px solid rgba(148, 163, 184, 0.2);
    }

    .btn-secondary:hover {
        background: rgba(148, 163, 184, 0.25);
    }

    .alert {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .alert-danger {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .computed-fields {
            grid-template-columns: 1fr;
        }

        .form-actions {
            flex-direction: column;
        }

        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="content-header">
    <h1>Edit Product</h1>
    <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Products
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <i class="fas fa-circle-exclamation"></i>
        Please fix the errors below.
    </div>
@endif

<div class="card glass-card">
    <form method="POST" action="{{ route('admin.products.update', $product) }}" id="productForm">
        @csrf
        @method('PUT')

        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Product Name <span class="required">*</span></label>
                <input type="text" name="ProductName" class="form-input @error('ProductName') is-invalid @enderror"
                       value="{{ old('ProductName', $product->ProductName) }}" required placeholder="e.g., Bullet Type Dome">
                @error('ProductName') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Model Number <span class="required">*</span></label>
                <input type="text" name="Model" class="form-input @error('Model') is-invalid @enderror"
                       value="{{ old('Model', $product->Model) }}" required placeholder="e.g., GWHWY245367">
                @error('Model') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group full-width">
                <label class="form-label">Specifications / Description</label>
                <textarea name="Description" class="form-input @error('Description') is-invalid @enderror">{{ old('Description', $product->Description) }}</textarea>
                @error('Description') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Category <span class="required">*</span></label>
                <select name="CategoryID" class="form-select @error('CategoryID') is-invalid @enderror" required>
                    <option value="">Select Category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->CategoryID }}" {{ old('CategoryID', $product->CategoryID) == $category->CategoryID ? 'selected' : '' }}>
                            {{ $category->CategoryName }}
                        </option>
                    @endforeach
                </select>
                @error('CategoryID') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Current Stock <span class="required">*</span></label>
                <input type="number" name="Quantity" class="form-input @error('Quantity') is-invalid @enderror"
                       value="{{ old('Quantity', $product->inventory?->Quantity ?? 0) }}" min="0" required placeholder="e.g., 100">
                @error('Quantity') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Cost Price (₱) <span class="required">*</span></label>
                <input type="number" name="CostPrice" id="CostPrice" class="form-input @error('CostPrice') is-invalid @enderror"
                       value="{{ old('CostPrice', $product->CostPrice ?? 0) }}" step="0.01" min="0.01" required placeholder="e.g., 12000">
                @error('CostPrice') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Selling Price (₱) — auto-calculated at 45% margin</label>
                <input type="number" id="SellingPrice" class="form-input" value="{{ $product->Price }}" step="0.01" readonly tabindex="-1">
                @error('Price') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Reorder Threshold</label>
                <input type="number" name="ReorderThreshold" class="form-input @error('ReorderThreshold') is-invalid @enderror"
                       value="{{ old('ReorderThreshold', $product->inventory?->ReorderThreshold ?? 10) }}" min="0" placeholder="e.g., 10">
                @error('ReorderThreshold') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group full-width">
                <label class="form-label">Pricing Calculations</label>
                <div class="computed-fields">
                    <div class="computed-field">
                        <label>Markup Price</label>
                        <div class="value" id="markupPrice">₱0.00</div>
                    </div>
                    <div class="computed-field">
                        <label>Markup %</label>
                        <div class="value" id="markupPercent">0%</div>
                    </div>
                    <div class="computed-field">
                        <label>Profit Margin</label>
                        <div class="value" id="profitMargin">0%</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="button" class="btn btn-primary" onclick="confirmUpdate()">
                <i class="fas fa-save"></i> Update Product
            </button>
        </div>
    </form>
</div>

<script>
    const costPriceInput = document.getElementById('CostPrice');
    const sellingPriceInput = document.getElementById('SellingPrice');
    const markupPriceEl = document.getElementById('markupPrice');
    const markupPercentEl = document.getElementById('markupPercent');
    const profitMarginEl = document.getElementById('profitMargin');

    const PROFIT_MARGIN = 0.45; // Store policy — mirrors Product::PROFIT_MARGIN server-side.

    function calculatePrices() {
        const costPrice = parseFloat(costPriceInput.value) || 0;
        const sellingPrice = costPrice > 0 ? (costPrice / (1 - PROFIT_MARGIN)) : 0;
        sellingPriceInput.value = sellingPrice > 0 ? sellingPrice.toFixed(2) : '';

        const markupPrice = sellingPrice - costPrice;
        const markupPercent = costPrice > 0 ? ((markupPrice / costPrice) * 100) : 0;
        const profitMargin = sellingPrice > 0 ? ((markupPrice / sellingPrice) * 100) : 0;

        markupPriceEl.textContent = '₱' + markupPrice.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        markupPriceEl.classList.toggle('negative', markupPrice < 0);

        markupPercentEl.textContent = markupPercent.toFixed(1) + '%';
        markupPercentEl.classList.toggle('negative', markupPercent < 0);

        profitMarginEl.textContent = profitMargin.toFixed(1) + '%';
        profitMarginEl.classList.toggle('negative', profitMargin < 0);
    }

    costPriceInput.addEventListener('input', calculatePrices);

    // Initial calculation
    calculatePrices();

    function confirmUpdate() {
        const form = document.getElementById('productForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        Swal.fire({
            title: 'Confirm Update',
            text: 'Are you sure you want to save the changes to this product?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

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
</script>
@endsection