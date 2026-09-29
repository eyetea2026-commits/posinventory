{{-- Shared product field markup. Included by the standalone create page, the
     Add Product modal, the standalone edit page, and the Edit Product modal.
     Expects a $categories collection in scope; pass an optional $product to
     pre-fill for editing (its absence means "Add" mode — no Quantity field,
     all fields start blank). Error spans carry a predictable id="error-{field}"
     so modal JS can inject a 422 validation message into the same slot the
     @error directive fills on a real (non-AJAX) page submission. --}}
<div class="form-grid">
    <div class="form-group">
        <label class="form-label">Product Name <span class="required">*</span></label>
        <input type="text" name="ProductName" id="ProductName" class="form-input @error('ProductName') is-invalid @enderror"
               value="{{ old('ProductName', $product->ProductName ?? null) }}" required placeholder="e.g., Bullet Type Dome">
        <span class="form-error" id="error-ProductName">@error('ProductName'){{ $message }}@enderror</span>
        <span id="productNameDuplicateError" class="form-error" style="display: none;"></span>
    </div>

    <div class="form-group">
        <label class="form-label">Model Number <span class="required">*</span></label>
        <input type="text" name="Model" id="Model" class="form-input @error('Model') is-invalid @enderror"
               value="{{ old('Model', $product->Model ?? null) }}" required placeholder="e.g., GWHWY245367">
        <span class="form-error" id="error-Model">@error('Model'){{ $message }}@enderror</span>
        <span id="modelDuplicateError" class="form-error" style="display: none;"></span>
    </div>

    <div class="form-group full-width">
        <label class="form-label">Specifications / Description</label>
        <textarea name="Description" class="form-input @error('Description') is-invalid @enderror">{{ old('Description', $product->Description ?? null) }}</textarea>
        <span class="form-error" id="error-Description">@error('Description'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Category <span class="required">*</span></label>
        <select name="CategoryID" id="CategoryID" class="form-select @error('CategoryID') is-invalid @enderror" required>
            <option value="">Select Category</option>
            @foreach($categories as $category)
                <option value="{{ $category->CategoryID }}" {{ old('CategoryID', $product->CategoryID ?? null) == $category->CategoryID ? 'selected' : '' }}>
                    {{ $category->CategoryName }}
                </option>
            @endforeach
        </select>
        <span class="form-error" id="error-CategoryID">@error('CategoryID'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Brand</label>
        {{-- Populated client-side (see product-form-behavior.blade.php) from
             every option below, filtered down to whichever ones carry the
             currently-selected Category's data-category id. Brands are
             managed exclusively through Brand Management (Category module)
             now — this form only ever selects an existing Brand, it never
             creates one. --}}
        <select name="BrandID" id="BrandID" class="form-select @error('BrandID') is-invalid @enderror">
            <option value="">No Brand</option>
            @foreach($brands as $brandOption)
                <option value="{{ $brandOption->BrandID }}" data-category="{{ $brandOption->CategoryID }}"
                    {{ old('BrandID', $product->BrandID ?? null) == $brandOption->BrandID ? 'selected' : '' }}>
                    {{ $brandOption->BrandName }}
                </option>
            @endforeach
        </select>
        <span class="form-error" id="error-BrandID">@error('BrandID'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Reorder Threshold</label>
        <input type="number" name="ReorderThreshold" class="form-input @error('ReorderThreshold') is-invalid @enderror"
               value="{{ old('ReorderThreshold', $product->inventory?->ReorderThreshold ?? 10) }}" min="0" placeholder="e.g., 10">
        <span class="form-error" id="error-ReorderThreshold">@error('ReorderThreshold'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Cost Price (₱) <span class="required">*</span></label>
        <input type="text" name="CostPrice" id="CostPrice" class="form-input @error('CostPrice') is-invalid @enderror"
               value="{{ old('CostPrice', $product->CostPrice ?? null) }}" required placeholder="e.g., 12,000.00">
        <span class="form-error" id="error-CostPrice">@error('CostPrice'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Selling Price (₱)</label>
        <input type="text" name="Price" id="SellingPrice" class="form-input" value="{{ old('Price', $product->Price ?? '') }}">
        <span class="form-error" id="error-Price">@error('Price'){{ $message }}@enderror</span>
    </div>

    @if(isset($product))
        <div class="form-group">
            <label class="form-label">SKU</label>
            <input type="text" class="form-input" value="{{ $product->SKU }}" readonly disabled>
        </div>
    @endif

    <div class="form-group full-width">
        <label class="form-label">Product Barcode <span class="required">*</span></label>
        <div class="barcode-input-row">
            <input type="text" name="Barcode" id="Barcode" class="form-input @error('Barcode') is-invalid @enderror"
                   value="{{ old('Barcode', $product->Barcode ?? null) }}" required placeholder="Scan with a barcode reader, or type it manually" autocomplete="off">
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
                <label for="ProfitMargin">Profit Margin (%)</label>
                <input type="text" id="ProfitMargin" class="value-input" value="45.0" inputmode="decimal">
            </div>
        </div>
    </div>
</div>

@if(isset($product))
    {{-- Suppliers for this product: who supplies it, at what cost, and which
         one is preferred (used by the auto-reorder flow to pick a supplier
         automatically). Only shown in Edit mode — a product must exist
         before a supplier can be linked to it. Wired up by
         window.initProductSuppliersPanel() in product-form-behavior.blade.php,
         called once this markup is actually in the DOM (see that file and
         the Edit Product modal's openEditProductModal). --}}
    <div class="card glass-card" style="margin-top: 24px;">
        <div class="content-header" style="margin-bottom: 16px;">
            <h1 style="font-size: 1.25rem;">Suppliers for this Product</h1>
        </div>

        <table style="width:100%; border-collapse: collapse;" id="productSuppliersTable">
            <thead>
                <tr style="text-align:left; color:#94a3b8; font-size:0.8rem; text-transform:uppercase;">
                    <th style="padding:8px;">Supplier</th>
                    <th style="padding:8px;">Cost Price</th>
                    <th style="padding:8px;">Preferred</th>
                    <th style="padding:8px;">Actions</th>
                </tr>
            </thead>
            <tbody id="productSuppliersBody">
                <tr><td colspan="4" style="padding:12px; color:#94a3b8;">Loading...</td></tr>
            </tbody>
        </table>

        <div class="form-grid" style="margin-top:20px; align-items:end;">
            <div class="form-group">
                <label class="form-label" for="newSupplierId">Supplier</label>
                <select id="newSupplierId" class="form-select">
                    <option value="">Select Supplier</option>
                    @foreach(\App\Models\Supplier::orderBy('SupplierName')->get() as $s)
                        <option value="{{ $s->SupplierID }}">{{ $s->SupplierName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="newSupplierCost">Cost Price</label>
                <input type="number" id="newSupplierCost" class="form-input" min="0" step="0.01">
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-secondary" id="addProductSupplierBtn">
                    <i class="fas fa-plus"></i> Add / Update Supplier
                </button>
            </div>
        </div>
    </div>
@endif
