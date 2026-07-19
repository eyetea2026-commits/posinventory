{{-- Shared "New Stock Adjustment" field markup. Included by both the
     standalone create page and the Add Stock Adjustment modal. --}}
<div class="form-grid">
    <div class="form-group full-width">
        <label class="form-label" for="ProductID">Product <span style="color: var(--danger);">*</span></label>
        <select id="ProductID" name="ProductID" class="form-select" required>
            <option value="">Select Product</option>
            @foreach($products as $product)
                <option value="{{ $product->ProductID }}" {{ old('ProductID') == $product->ProductID ? 'selected' : '' }}>
                    {{ $product->ProductName }} ({{ $product->Model }})
                </option>
            @endforeach
        </select>
        <span class="form-error" id="error-ProductID">@error('ProductID'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="QuantityAdjust">Quantity Adjusted <span style="color: var(--danger);">*</span></label>
        <input type="number" id="QuantityAdjust" name="QuantityAdjust" class="form-input" value="{{ old('QuantityAdjust') }}" required placeholder="e.g., 5 or -5">
        <span class="form-error" id="error-QuantityAdjust">@error('QuantityAdjust'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="Date">Date <span style="color: var(--danger);">*</span></label>
        <input type="date" id="Date" name="Date" class="form-input" value="{{ old('Date', now()->toDateString()) }}" required>
        <span class="form-error" id="error-Date">@error('Date'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="Reason">Reason <span style="color: var(--danger);">*</span></label>
        <textarea id="Reason" name="Reason" class="form-textarea" required>{{ old('Reason') }}</textarea>
        <span class="form-error" id="error-Reason">@error('Reason'){{ $message }}@enderror</span>
    </div>
</div>
