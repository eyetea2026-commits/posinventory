{{-- Shared product field markup. Included by the standalone create page, the
     Add Product modal, the standalone edit page, and the Edit Product modal.
     Expects a $categories collection in scope; pass an optional $product to
     pre-fill for editing (its absence means "Add" mode — no Quantity field,
     all fields start blank). Error spans carry a predictable id="error-{field}"
     so modal JS can inject a 422 validation message into the same slot the
     @error directive fills on a real (non-AJAX) page submission. --}}
{{-- Three per row in the pop-up: Name / Model / Category; Brand / Cost /
     Selling Price; Reorder Threshold / (SKU) / Barcode; Pricing Calculations;
     Description and the returns option (two wide) beside the Photo. Two per
     row on tablets, one on phones. --}}
<style>
    .form-grid.product-form-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .form-grid.product-form-grid .product-span-2 { grid-column: span 2; }
    /* Photo beside both Description and the returns option. */
    .form-grid.product-form-grid .product-photo-field { grid-row: span 2; }
    .non-returnable-toggle { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 10px; background: rgba(245, 158, 11, 0.08); cursor: pointer; }
    .non-returnable-toggle input { margin-top: 3px; width: 18px; height: 18px; flex-shrink: 0; accent-color: #f59e0b; }
    .non-returnable-toggle strong { display: block; color: #fcd34d; font-size: 0.9rem; }
    .non-returnable-toggle small { display: block; color: #94a3b8; font-size: 0.78rem; margin-top: 2px; }
    .product-form-grid textarea[name="Description"] { min-height: 132px; }
    .product-photo-field .product-cell { flex-wrap: wrap; align-items: flex-start; gap: 10px; }
    .product-photo-field .product-cell > div { flex: 1 1 150px; }
    .product-form-grid .barcode-input-row input { min-width: 0; }
    @media (max-width: 899px) {
        .form-grid.product-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .form-grid.product-form-grid .product-photo-field { grid-row: auto; }
    }
    @media (max-width: 600px) {
        .form-grid.product-form-grid { grid-template-columns: 1fr; }
        .form-grid.product-form-grid .product-span-2 { grid-column: auto; }
    }
</style>
<div class="form-grid product-form-grid">
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

    <div class="form-group">
        <label class="form-label">Category <span class="required">*</span></label>
        <select name="CategoryID" id="CategoryID" class="form-select @error('CategoryID') is-invalid @enderror" required>
            <option value="">Select Category</option>
            @foreach($categories as $category)
                {{-- Deactivated categories aren't offered, except the one a product already belongs to. --}}
                @continue(! $category->IsActive && old('CategoryID', $product->CategoryID ?? null) != $category->CategoryID)
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
             currently-selected Category's data-category id. The "+" button
             lets the user type a new Brand name and create it on the fly
             (scoped to the currently-selected Category), reusing the same
             admin.categories.brands.store endpoint the Category module's
             Brand Management panel already uses — see
             category-brands-behavior.blade.php for that original pattern. --}}
        <div class="brand-select-row">
            <select name="BrandID" id="BrandID" class="form-select @error('BrandID') is-invalid @enderror">
                <option value="">No Brand</option>
                @foreach($brands as $brandOption)
                    <option value="{{ $brandOption->BrandID }}" data-category="{{ $brandOption->CategoryID }}"
                        {{ old('BrandID', $product->BrandID ?? null) == $brandOption->BrandID ? 'selected' : '' }}>
                        {{ $brandOption->BrandName }}
                    </option>
                @endforeach
            </select>
            <button type="button" class="btn-add-brand" id="addBrandBtn" title="Add a new brand">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        <div class="brand-add-row" id="brandAddRow" style="display:none;">
            <input type="text" class="form-input brand-name-input-inline" id="newBrandNameInput" placeholder="New brand name" maxlength="100">
            <button type="button" class="btn btn-secondary" id="confirmAddBrandBtn">Add</button>
            <button type="button" class="btn-cancel-add-brand" id="cancelAddBrandBtn">Cancel</button>
        </div>
        <span class="form-error" id="brandAddError" style="display:none;"></span>
        <span class="form-error" id="error-BrandID">@error('BrandID'){{ $message }}@enderror</span>
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

    <div class="form-group">
        <label class="form-label">Reorder Threshold</label>
        <input type="number" name="ReorderThreshold" class="form-input @error('ReorderThreshold') is-invalid @enderror"
               value="{{ old('ReorderThreshold', $product->inventory?->ReorderThreshold ?? 10) }}" min="0" placeholder="e.g., 10">
        <span class="form-error" id="error-ReorderThreshold">@error('ReorderThreshold'){{ $message }}@enderror</span>
    </div>

    @if(isset($product))
        <div class="form-group">
            <label class="form-label">SKU</label>
            <input type="text" class="form-input" value="{{ $product->SKU }}" readonly disabled>
        </div>
    @endif

    <div class="form-group{{ isset($product) ? '' : ' product-span-2' }}">
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

    <div class="form-group product-span-2">
        <label class="form-label">Specifications / Description</label>
        <textarea name="Description" class="form-input @error('Description') is-invalid @enderror">{{ old('Description', $product->Description ?? null) }}</textarea>
        <span class="form-error" id="error-Description">@error('Description'){{ $message }}@enderror</span>
    </div>

    <div class="form-group product-photo-field">
        <label class="form-label">Product Photo</label>
        <div class="product-cell">
            <span class="product-thumb thumb-large" data-photo-preview>
                @if(isset($product) && $product->image_url)
                    <img src="{{ $product->image_url }}" alt="{{ $product->ProductName }}">
                @else
                    <i class="fas fa-box" aria-hidden="true"></i>
                @endif
            </span>
            <div style="display:flex; flex-direction:column; gap:8px; min-width:0;">
                <input type="file" name="Image" accept="image/jpeg,image/png,image/webp" class="form-input @error('Image') is-invalid @enderror"
                       onchange="(function (input) { const box = input.closest('.form-group').querySelector('[data-photo-preview]'); const file = input.files && input.files[0]; if (!file) return; box.innerHTML = ''; const img = document.createElement('img'); img.src = URL.createObjectURL(file); box.appendChild(img); const remove = input.closest('.form-group').querySelector('input[name=RemoveImage]'); if (remove) remove.checked = false; })(this)">
                <small style="color:#94a3b8;">JPG, PNG or WebP, up to 5 MB. It's saved shrunk to a small file automatically.</small>
                @if(isset($product) && $product->ImagePath)
                    <label style="display:flex; align-items:center; gap:6px; color:#cbd5e1; font-size:0.85rem;">
                        <input type="checkbox" name="RemoveImage" value="1"> Remove photo
                    </label>
                @endif
                <span class="form-error" id="error-Image">@error('Image'){{ $message }}@enderror</span>
            </div>
        </div>
    </div>

    {{-- Cut, crimped or used items (consumables, wires, fiber connectors:
         RJ45, coaxial cable...) can't be returned once sold; the Cashier's
         return request won't offer them. --}}
    <div class="form-group product-span-2 product-returns-field">
        <input type="hidden" name="NonReturnable" value="0">
        <label class="non-returnable-toggle">
            <input type="checkbox" name="NonReturnable" value="1"
                   {{ old('NonReturnable', isset($product) && ! $product->IsReturnable ? 1 : 0) ? 'checked' : '' }}>
            <span>
                <strong>Not returnable / refundable</strong>
                <small>For items that can't be taken back once sold, like consumables, wires and fiber connectors (RJ45, coaxial cable).</small>
            </span>
        </label>
    </div>
</div>

{{-- Suppliers for this product: who supplies it, at what cost, and which
     one is preferred (used by the auto-reorder flow to pick a supplier
     automatically).
     Edit mode (a real ProductID exists): add/remove is an immediate AJAX
     call, wired up by window.initProductSuppliersPanel() in
     product-form-behavior.blade.php, called once this markup is actually in
     the DOM (see that file and the Edit Product modal's openEditProductModal).
     Add mode (no ProductID yet): a supplier can't be linked to a product
     that doesn't exist, so chosen suppliers are held client-side as
     "Suppliers[][SupplierID]" hidden inputs and are only actually linked
     once the whole Add Product form is submitted (see
     ProductController::store()) — same pattern as the Add Category form's
     pending "Brands[]" list. Only the supplier is chosen -- no cost price
     is asked for here; the product's own Cost Price is used. --}}
<div class="card glass-card" style="margin-top: 24px;">
    <div class="content-header" style="margin-bottom: 16px;">
        <h1 style="font-size: 1.25rem;">Suppliers for this Product</h1>
    </div>

    @if(isset($product))
        <table style="width:100%; border-collapse: collapse;" id="productSuppliersTable">
            <thead>
                <tr style="text-align:left; color:#94a3b8; font-size:0.8rem; text-transform:uppercase;">
                    <th style="padding:8px;">Supplier</th>
                    <th style="padding:8px;">Preferred</th>
                    <th style="padding:8px;">Actions</th>
                </tr>
            </thead>
            <tbody id="productSuppliersBody">
                <tr><td colspan="3" style="padding:12px; color:#94a3b8;">Loading...</td></tr>
            </tbody>
        </table>
    @else
        <table style="width:100%; border-collapse: collapse;" id="pendingProductSuppliersTable">
            <thead>
                <tr style="text-align:left; color:#94a3b8; font-size:0.8rem; text-transform:uppercase;">
                    <th style="padding:8px;">Supplier</th>
                    <th style="padding:8px;">Actions</th>
                </tr>
            </thead>
            <tbody id="pendingProductSuppliersBody">
                <tr class="pending-suppliers-empty-row"><td colspan="2" style="padding:12px; color:#94a3b8;">No suppliers added yet.</td></tr>
            </tbody>
        </table>
        <span class="form-error" id="pendingSupplierError" style="display:none;"></span>
    @endif

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
                <button type="button" class="btn btn-secondary" id="addProductSupplierBtn">
                    <i class="fas fa-plus"></i> Add Supplier
                </button>
            </div>
        </div>
    </div>
