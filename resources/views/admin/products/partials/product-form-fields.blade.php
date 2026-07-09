{{-- Shared "Add Product" field markup. Included by both the standalone
     create page and the Add Product modal. Expects a $categories collection
     in scope. Error spans carry a predictable id="error-{field}" so the
     modal's JS can inject a 422 validation message into the same slot the
     @error directive fills on a real (non-AJAX) page submission. --}}
<div class="form-grid">
    <div class="form-group">
        <label class="form-label">Product Name <span class="required">*</span></label>
        <input type="text" name="ProductName" id="ProductName" class="form-input @error('ProductName') is-invalid @enderror"
               value="{{ old('ProductName') }}" required placeholder="e.g., Bullet Type Dome">
        <span class="form-error" id="error-ProductName">@error('ProductName'){{ $message }}@enderror</span>
        <span id="productNameDuplicateError" class="form-error" style="display: none;"></span>
    </div>

    <div class="form-group">
        <label class="form-label">Model Number <span class="required">*</span></label>
        <input type="text" name="Model" id="Model" class="form-input @error('Model') is-invalid @enderror"
               value="{{ old('Model') }}" required placeholder="e.g., GWHWY245367">
        <span class="form-error" id="error-Model">@error('Model'){{ $message }}@enderror</span>
        <span id="modelDuplicateError" class="form-error" style="display: none;"></span>
    </div>

    <div class="form-group full-width">
        <label class="form-label">Specifications / Description</label>
        <textarea name="Description" class="form-input @error('Description') is-invalid @enderror">{{ old('Description') }}</textarea>
        <span class="form-error" id="error-Description">@error('Description'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Category <span class="required">*</span></label>
        <select name="CategoryID" class="form-select @error('CategoryID') is-invalid @enderror" required>
            <option value="">Select Category</option>
            @foreach($categories as $category)
                <option value="{{ $category->CategoryID }}" {{ old('CategoryID') == $category->CategoryID ? 'selected' : '' }}>
                    {{ $category->CategoryName }}
                </option>
            @endforeach
        </select>
        <span class="form-error" id="error-CategoryID">@error('CategoryID'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Cost Price (₱) <span class="required">*</span></label>
        <input type="number" name="CostPrice" id="CostPrice" class="form-input @error('CostPrice') is-invalid @enderror"
               value="{{ old('CostPrice') }}" step="0.01" min="0" required placeholder="e.g., 12000">
        <span class="form-error" id="error-CostPrice">@error('CostPrice'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Selling Price (₱) <span class="required">*</span></label>
        <input type="number" name="Price" id="SellingPrice" class="form-input @error('Price') is-invalid @enderror"
               value="{{ old('Price') }}" step="0.01" min="0" required placeholder="e.g., 13000">
        <span class="form-error" id="error-Price">@error('Price'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Reorder Threshold</label>
        <input type="number" name="ReorderThreshold" class="form-input @error('ReorderThreshold') is-invalid @enderror"
               value="{{ old('ReorderThreshold', 10) }}" min="0" placeholder="e.g., 10">
        <span class="form-error" id="error-ReorderThreshold">@error('ReorderThreshold'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label">Product Barcode <span class="required">*</span></label>
        <div class="barcode-input-row">
            <input type="text" name="Barcode" id="Barcode" class="form-input @error('Barcode') is-invalid @enderror"
                   value="{{ old('Barcode') }}" required placeholder="Scan with a barcode reader, or type it manually" autocomplete="off">
            <button type="button" class="btn-scan-barcode" id="scanBarcodeBtn">
                <i class="fas fa-barcode"></i> Scan Barcode
            </button>
        </div>
        <span class="form-error" id="error-Barcode">@error('Barcode'){{ $message }}@enderror</span>
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
