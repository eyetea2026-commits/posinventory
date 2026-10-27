{{-- Shared product field markup. Included by the standalone create page, the
     Add Product modal, the standalone edit page, and the Edit Product modal.
     Expects a $categories collection in scope; pass an optional $product to
     pre-fill for editing (its absence means "Add" mode — no Quantity field,
     all fields start blank). Error spans carry a predictable id="error-{field}"
     so modal JS can inject a 422 validation message into the same slot the
     @error directive fills on a real (non-AJAX) page submission. --}}
{{-- The Product Photo down the left; beside it, three per row: Name /
     Category / Cost Price, Model / Brand / Reorder Threshold, then the
     Specifications, the Barcode (and SKU when editing) and the returns
     option across. Below: Unit of Measurement (with the Selling Price),
     Pricing Calculations, and the product's Suppliers. Two per row on
     tablets, one on phones. --}}
<style>
    .form-grid.product-form-grid { grid-template-columns: minmax(170px, 0.85fr) repeat(3, minmax(0, 1fr)); }
    .form-grid.product-form-grid .product-photo-field { grid-column: 1; grid-row: 1 / span 5; }
    .form-grid.product-form-grid .product-span-2 { grid-column: span 2; }
    .form-grid.product-form-grid .product-span-3 { grid-column: span 3; }
    .product-photo-field .product-cell { flex-direction: column; align-items: stretch; gap: 10px; }
    .product-photo-field .product-thumb.thumb-large { width: 100%; height: auto; aspect-ratio: 1; max-width: 200px; }
    .product-photo-field input[type="file"] { font-size: 0.78rem; padding: 8px; }
    .non-returnable-toggle { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 10px; background: rgba(245, 158, 11, 0.08); cursor: pointer; }
    .non-returnable-toggle input { margin-top: 3px; width: 18px; height: 18px; flex-shrink: 0; accent-color: #f59e0b; }
    .non-returnable-toggle strong { display: block; color: #fcd34d; font-size: 0.9rem; }
    .non-returnable-toggle small { display: block; color: #94a3b8; font-size: 0.78rem; margin-top: 2px; }
    .product-form-grid textarea[name="Description"] { min-height: 96px; }
    .product-form-grid .barcode-input-row input { min-width: 0; }
    .uom-section { border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 12px; padding: 12px 14px; background: rgba(59, 130, 246, 0.06); }
    .uom-section .uom-title { color: #93c5fd; margin-bottom: 8px; }
    .uom-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px 16px; }
    .uom-grid .form-group { margin-bottom: 0; }
    .uom-per-base, .uom-unit-note { color: #94a3b8; font-weight: 400; font-size: 0.8em; }
    .uom-price-input { display: flex; align-items: center; gap: 8px; }
    .uom-price-input input { flex: 1; min-width: 0; text-align: right; }
    .uom-price-input .uom-currency { color: #e2e8f0; font-size: 1.15rem; font-weight: 700; }
    .uom-price-input .uom-per-base { color: #cbd5e1; font-size: 0.88rem; white-space: nowrap; }
    .uom-grid-options { margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(148, 163, 184, 0.15); }
    .uom-fraction-toggle { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid rgba(148, 163, 184, 0.3); border-radius: 10px; cursor: pointer; }
    .uom-fraction-toggle input { margin-top: 3px; width: 18px; height: 18px; flex-shrink: 0; accent-color: #3b82f6; }
    .uom-fraction-toggle strong { display: block; color: #e2e8f0; font-size: 0.9rem; }
    .uom-fraction-toggle small { display: block; color: #94a3b8; font-size: 0.78rem; margin-top: 2px; }
    .product-suppliers-table { width: 100%; border-collapse: collapse; }
    .product-suppliers-table th { padding: 8px; text-align: left; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; }
    .product-suppliers-table tfoot td { padding: 12px 8px 4px; border-top: 1px solid rgba(148, 163, 184, 0.15); vertical-align: middle; }
    .product-suppliers-table tfoot .btn { white-space: nowrap; }
    @media (max-width: 899px) {
        .form-grid.product-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .form-grid.product-form-grid .product-photo-field { grid-column: 1 / -1; grid-row: auto; }
        .form-grid.product-form-grid .product-span-3 { grid-column: 1 / -1; }
        .uom-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 600px) {
        .form-grid.product-form-grid { grid-template-columns: 1fr; }
        .form-grid.product-form-grid .product-span-2 { grid-column: auto; }
        .uom-grid { grid-template-columns: 1fr; }
    }
</style>
@php
    $baseUnit = old('UnitOfMeasure', $product->UnitOfMeasure ?? \App\Services\UnitOfMeasure::DEFAULT) ?: \App\Services\UnitOfMeasure::DEFAULT;
    $purchaseUnit = old('PurchaseUnit', $product->PurchaseUnit ?? '') ?: '';
    // Counted by the meter and bought by the roll shows as "Meter & Roll".
    if ($baseUnit === \App\Services\UnitOfMeasure::METER_AND_ROLL || ($baseUnit === 'meter' && $purchaseUnit === 'roll')) {
        [$baseChoice, $baseUnit, $purchaseUnit] = [\App\Services\UnitOfMeasure::METER_AND_ROLL, 'meter', 'roll'];
    } else {
        $baseChoice = $baseUnit;
    }
@endphp
<div class="form-grid product-form-grid">
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

    <div class="form-group">
        <label class="form-label">Product Name <span class="required">*</span></label>
        <input type="text" name="ProductName" id="ProductName" class="form-input @error('ProductName') is-invalid @enderror"
               value="{{ old('ProductName', $product->ProductName ?? null) }}" required placeholder="e.g., Bullet Type Dome">
        <span class="form-error" id="error-ProductName">@error('ProductName'){{ $message }}@enderror</span>
        <span id="productNameDuplicateError" class="form-error" style="display: none;"></span>
    </div>

    <div class="form-group">
        <label class="form-label">Category <span class="required">*</span></label>
        <select name="CategoryID" id="CategoryID" class="form-select @error('CategoryID') is-invalid @enderror" required>
            <option value="">Select Category</option>
            @foreach($categories as $category)
                {{-- Deactivated categories aren't offered, except the one a product already belongs to. --}}
                @continue(! $category->IsActive && old('CategoryID', $product->CategoryID ?? null) != $category->CategoryID)
                {{-- data-units: its Inventory Base Unit choices, default first
                     (WIRE: Meter & Roll / Meter / Roll, others: Piece / Box). --}}
                <option value="{{ $category->CategoryID }}" data-units="{{ implode(',', \App\Services\UnitOfMeasure::baseChoicesFor($category->CategoryName)) }}" {{ old('CategoryID', $product->CategoryID ?? null) == $category->CategoryID ? 'selected' : '' }}>
                    {{ $category->CategoryName }}
                </option>
            @endforeach
        </select>
        <span class="form-error" id="error-CategoryID">@error('CategoryID'){{ $message }}@enderror</span>
    </div>

    {{-- Prices: up to 5 whole-peso digits (99,999.99); letters can't be typed. --}}
    <div class="form-group">
        <label class="form-label">Cost Price (₱) <span class="uom-per-base">per {{ $baseUnit }}</span> <span class="required">*</span></label>
        <input type="text" name="CostPrice" id="CostPrice" class="form-input @error('CostPrice') is-invalid @enderror"
               value="{{ old('CostPrice', $product->CostPrice ?? null) }}" required placeholder="e.g., 12,000.00">
        <span class="form-error" id="error-CostPrice">@error('CostPrice'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Model Number <span class="required">*</span></label>
        <input type="text" name="Model" id="Model" class="form-input @error('Model') is-invalid @enderror"
               value="{{ old('Model', $product->Model ?? null) }}" required placeholder="e.g., GWHWY245367">
        <span class="form-error" id="error-Model">@error('Model'){{ $message }}@enderror</span>
        <span id="modelDuplicateError" class="form-error" style="display: none;"></span>
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

    {{-- Up to 3 digits (999), counted in the base unit. --}}
    <div class="form-group">
        <label class="form-label" for="ReorderThreshold">Reorder Threshold <span class="uom-unit-note">(<span class="uom-base-plural">{{ \App\Services\UnitOfMeasure::plural($baseUnit) }}</span>)</span></label>
        <input type="number" name="ReorderThreshold" id="ReorderThreshold" class="form-input @error('ReorderThreshold') is-invalid @enderror"
               value="{{ old('ReorderThreshold', $product->inventory?->ReorderThreshold ?? 10) }}" min="0" max="999" step="1" data-max-digits="3" placeholder="e.g., 10">
        <span class="form-error" id="error-ReorderThreshold">@error('ReorderThreshold'){{ $message }}@enderror</span>
    </div>

    <div class="form-group product-span-3">
        <label class="form-label">Specifications / Description</label>
        <textarea name="Description" class="form-input @error('Description') is-invalid @enderror">{{ old('Description', $product->Description ?? null) }}</textarea>
        <span class="form-error" id="error-Description">@error('Description'){{ $message }}@enderror</span>
    </div>

    <div class="form-group {{ isset($product) ? 'product-span-2' : 'product-span-3' }}">
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

    @if(isset($product))
        <div class="form-group">
            <label class="form-label">SKU</label>
            <input type="text" class="form-input" value="{{ $product->SKU }}" readonly disabled>
        </div>
    @endif

    {{-- Cut, crimped or used items (consumables, wires, fiber connectors:
         RJ45, coaxial cable...) can't be returned once sold; the Cashier's
         return request won't offer them. --}}
    <div class="form-group product-span-3 product-returns-field">
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

    {{-- Unit of Measurement, read across like "Meter & Roll | Roll | ₱15 per
         meter": stock is kept in the base unit (meters, pieces), bought by
         the purchase unit (roll, box) - how many meters each roll holds is
         entered at Stock Receiving - and it's sold by the base unit at the
         Selling Price. Below: decimals (1.5 meters). --}}
    <div class="form-group full-width uom-section" data-uom-section>
        <label class="form-label uom-title"><i class="fas fa-ruler-combined"></i> Unit of Measurement</label>
        <div class="uom-grid">
            <div class="form-group">
                <label class="form-label" for="UnitOfMeasure">Inventory Base Unit <span class="required">*</span></label>
                <select name="UnitOfMeasure" id="UnitOfMeasure" class="form-select @error('UnitOfMeasure') is-invalid @enderror" required>
                    @foreach(\App\Services\UnitOfMeasure::UNITS as $unit)
                        <option value="{{ $unit }}" data-plural="{{ \App\Services\UnitOfMeasure::plural($unit) }}" {{ $baseChoice === $unit ? 'selected' : '' }}>{{ ucfirst($unit) }}</option>
                    @endforeach
                    <option value="{{ \App\Services\UnitOfMeasure::METER_AND_ROLL }}" data-plural="meters" {{ $baseChoice === \App\Services\UnitOfMeasure::METER_AND_ROLL ? 'selected' : '' }}>{{ \App\Services\UnitOfMeasure::METER_AND_ROLL_LABEL }}</option>
                </select>
                <span class="form-error" id="error-UnitOfMeasure">@error('UnitOfMeasure'){{ $message }}@enderror</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="PurchaseUnit">Purchase Unit</label>
                <select name="PurchaseUnit" id="PurchaseUnit" class="form-select @error('PurchaseUnit') is-invalid @enderror">
                    <option value="">None (bought by the {{ $baseUnit }})</option>
                    @foreach(\App\Services\UnitOfMeasure::UNITS as $unit)
                        <option value="{{ $unit }}" {{ $purchaseUnit === $unit ? 'selected' : '' }}>{{ ucfirst($unit) }}</option>
                    @endforeach
                </select>
                <span class="form-error" id="error-PurchaseUnit">@error('PurchaseUnit'){{ $message }}@enderror</span>
            </div>

            {{-- Up to 5 whole-peso digits (99,999.99). --}}
            <div class="form-group">
                <label class="form-label" for="SellingPrice">Selling Price</label>
                <div class="uom-price-input">
                    <span class="uom-currency">₱</span>
                    <input type="text" name="Price" id="SellingPrice" class="form-input @error('Price') is-invalid @enderror" value="{{ old('Price', $product->Price ?? '') }}">
                    <span class="uom-per-base">per {{ $baseUnit }}</span>
                </div>
                <span class="form-error" id="error-Price">@error('Price'){{ $message }}@enderror</span>
            </div>
        </div>

        <div class="uom-grid uom-grid-options">
            <div class="form-group">
                <input type="hidden" name="AllowsFractionalQuantity" value="0">
                <label class="uom-fraction-toggle">
                    <input type="checkbox" name="AllowsFractionalQuantity" id="AllowsFractionalQuantity" value="1"
                           {{ old('AllowsFractionalQuantity', $product->AllowsFractionalQuantity ?? false) ? 'checked' : '' }}>
                    <span>
                        <strong>Allow decimal quantities</strong>
                        <small>Sell part of a unit, e.g. 1.5 <span class="uom-base-plural">{{ \App\Services\UnitOfMeasure::plural($baseUnit) }}</span>.</small>
                    </span>
                </label>
                <span class="form-error" id="error-AllowsFractionalQuantity">@error('AllowsFractionalQuantity'){{ $message }}@enderror</span>
            </div>
        </div>
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

{{-- Suppliers for this product: who supplies it, at what cost, and which
     one is preferred (used by the auto-reorder flow to pick a supplier
     automatically). The supplier is picked in the table's last row (the
     searchable dropdown under Supplier, the Add Supplier button under
     Actions).
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

    <table class="product-suppliers-table" id="{{ isset($product) ? 'productSuppliersTable' : 'pendingProductSuppliersTable' }}">
        <thead>
            <tr>
                <th>Supplier</th>
                @if(isset($product))
                    <th>Preferred</th>
                @endif
                <th>Actions</th>
            </tr>
        </thead>
        @if(isset($product))
            <tbody id="productSuppliersBody">
                <tr><td colspan="3" style="padding:12px; color:#94a3b8;">Loading...</td></tr>
            </tbody>
        @else
            <tbody id="pendingProductSuppliersBody">
                <tr class="pending-suppliers-empty-row"><td colspan="2" style="padding:12px; color:#94a3b8;">No suppliers added yet.</td></tr>
            </tbody>
        @endif
        <tfoot>
            <tr>
                <td colspan="{{ isset($product) ? 2 : 1 }}">
                    <select id="newSupplierId" class="form-select" aria-label="Supplier">
                        <option value="">Select Supplier</option>
                        @foreach(\App\Models\Supplier::orderBy('SupplierName')->get() as $s)
                            <option value="{{ $s->SupplierID }}">{{ $s->SupplierName }}</option>
                        @endforeach
                    </select>
                </td>
                <td style="width: 1%;">
                    <button type="button" class="btn btn-secondary" id="addProductSupplierBtn">
                        <i class="fas fa-plus"></i> Add Supplier
                    </button>
                </td>
            </tr>
        </tfoot>
    </table>
    @unless(isset($product))
        <span class="form-error" id="pendingSupplierError" style="display:none;"></span>
    @endunless
</div>
