{{-- Shared "Record Stock Receiving" field markup. Included by both the
     standalone create page and the Record Receipt modal. --}}
<div class="form-grid">
    <div class="form-group full-width">
        <label class="form-label" for="ProductID">Product <span class="required">*</span></label>
        <select id="ProductID" name="ProductID" class="form-select" required>
            <option value="">Select Product</option>
            @foreach($products as $product)
                <option value="{{ $product->ProductID }}" {{ old('ProductID') == $product->ProductID ? 'selected' : '' }}>
                    {{ $product->ProductName }} - {{ $product->Model }}
                </option>
            @endforeach
        </select>
        <span class="form-error" id="error-ProductID">@error('ProductID'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="SupplierID">Supplier <span class="required">*</span></label>
        <select id="SupplierID" name="SupplierID" class="form-select" required>
            <option value="">Select Supplier</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->SupplierID }}" {{ old('SupplierID') == $supplier->SupplierID ? 'selected' : '' }}>
                    {{ $supplier->SupplierName }}
                </option>
            @endforeach
        </select>
        <span class="form-error" id="error-SupplierID">@error('SupplierID'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="Quantity">Quantity Received <span class="required">*</span></label>
        <input type="number" id="Quantity" name="Quantity" class="form-input"
               value="{{ old('Quantity') }}" required min="1">
        <span class="form-error" id="error-Quantity">@error('Quantity'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="ReceiptNumber">Receipt Number <span class="required">*</span></label>
        <input type="text" id="ReceiptNumber" name="ReceiptNumber" class="form-input"
               value="{{ old('ReceiptNumber') }}" required maxlength="50" placeholder="e.g., SUP-2026-0001">
        <span class="form-error" id="error-ReceiptNumber">@error('ReceiptNumber'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="DateReceived">Date Received <span class="required">*</span></label>
        <input type="date" id="DateReceived" name="DateReceived" class="form-input"
               value="{{ old('DateReceived', date('Y-m-d')) }}" required>
        <span class="form-error" id="error-DateReceived">@error('DateReceived'){{ $message }}@enderror</span>
    </div>
</div>
