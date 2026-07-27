{{-- Shared Damage field markup. Included by the standalone create/edit
     pages and the Add/Edit Damage modals. Pass an optional $damage to
     pre-fill for editing (its absence means "Add" mode). --}}
<div class="form-grid">
    <div class="form-group full-width">
        <label class="form-label" for="ProductID">Product <span class="required">*</span></label>
        <select id="ProductID" name="ProductID" class="form-select" required onchange="onDamageProductChange(this)">
            <option value="">Select Product</option>
            @foreach($products as $product)
                @php $resolvedSupplier = $product->resolveReorderSupplier(); @endphp
                <option
                    value="{{ $product->ProductID }}"
                    data-sku="{{ $product->SKU ?? 'N/A' }}"
                    data-category="{{ $product->category?->CategoryName ?? 'Uncategorized' }}"
                    data-cost="{{ $product->CostPrice ?? 0 }}"
                    data-stock="{{ $product->inventory?->Quantity ?? 0 }}"
                    data-resolved-supplier="{{ $resolvedSupplier?->SupplierID }}"
                    {{ old('ProductID', $damage->ProductID ?? null) == $product->ProductID ? 'selected' : '' }}
                >
                    {{ $product->ProductName }} - {{ $product->Model }}
                    @if($product->inventory)
                        (Stock: {{ $product->inventory->Quantity }})
                    @else
                        (No Stock)
                    @endif
                </option>
            @endforeach
        </select>
        <span class="form-error" id="error-ProductID">@error('ProductID'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width" id="damageProductContext" style="display:none; background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.2); border-radius: 10px; padding: 12px 16px;">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px; font-size: 0.85rem; color: var(--text-secondary, #94a3b8);">
            <div>SKU<br><strong id="damageCtxSku" style="color: var(--text-primary, #f8fafc);"></strong></div>
            <div>Category<br><strong id="damageCtxCategory" style="color: var(--text-primary, #f8fafc);"></strong></div>
            <div>Cost Price<br><strong id="damageCtxCost" style="color: var(--text-primary, #f8fafc);"></strong></div>
            <div>Current Stock<br><strong id="damageCtxStock" style="color: var(--text-primary, #f8fafc);"></strong></div>
        </div>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="SupplierID">Supplier <span class="required">*</span></label>
        <select id="SupplierID" name="SupplierID" class="form-select" required>
            <option value="">Select Supplier</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->SupplierID }}" {{ old('SupplierID', $damage->SupplierID ?? null) == $supplier->SupplierID ? 'selected' : '' }}>
                    {{ $supplier->SupplierName }}
                </option>
            @endforeach
        </select>
        <span class="form-error" id="error-SupplierID">@error('SupplierID'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="PurchaseOrderID">Purchase Order <span style="color: var(--text-muted); font-weight: 400;">(optional)</span></label>
        <select id="PurchaseOrderID" name="PurchaseOrderID" class="form-select">
            <option value="">None / Not linked to a PO</option>
            @foreach($purchaseOrders as $po)
                <option value="{{ $po->PurchaseOrderID }}" {{ old('PurchaseOrderID', $damage->PurchaseOrderID ?? null) == $po->PurchaseOrderID ? 'selected' : '' }}>
                    PO #{{ $po->PurchaseOrderID }} &mdash; {{ $po->supplier?->SupplierName }}
                </option>
            @endforeach
        </select>
        <span class="form-error" id="error-PurchaseOrderID">@error('PurchaseOrderID'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="Quantity">Quantity Damaged <span class="required">*</span></label>
        <input type="number" id="Quantity" name="Quantity" class="form-input"
               value="{{ old('Quantity', $damage->Quantity ?? null) }}" required min="1">
        <span class="form-error" id="error-Quantity">@error('Quantity'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="DateRecorded">Date Recorded <span class="required">*</span></label>
        <input type="date" id="DateRecorded" name="DateRecorded" class="form-input"
               value="{{ old('DateRecorded', isset($damage) ? optional($damage->DateRecorded)->format('Y-m-d') : date('Y-m-d')) }}" required>
        <span class="form-error" id="error-DateRecorded">@error('DateRecorded'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="DamageType">Damage Type / Reason <span class="required">*</span></label>
        <select id="DamageType" name="DamageType" class="form-select" required>
            <option value="">Select Damage Type</option>
            @foreach(\App\Models\DamagedProduct::DAMAGE_TYPES as $value => $label)
                <option value="{{ $value }}" {{ old('DamageType', $damage->DamageType ?? null) === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <span class="form-error" id="error-DamageType">@error('DamageType'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="Description">Damage Description <span class="required">*</span></label>
        <textarea id="Description" name="Description" class="form-input" rows="3"
                  required maxlength="500" placeholder="Describe the damage...">{{ old('Description', $damage->Description ?? null) }}</textarea>
        <span class="form-error" id="error-Description">@error('Description'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="InspectionNotes">Inspection Notes</label>
        <textarea id="InspectionNotes" name="InspectionNotes" class="form-input" rows="3"
                  maxlength="1000" placeholder="Optional inspection findings...">{{ old('InspectionNotes', $damage->InspectionNotes ?? null) }}</textarea>
        <span class="form-error" id="error-InspectionNotes">@error('InspectionNotes'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="WarehouseLocation">Warehouse Location</label>
        <input type="text" id="WarehouseLocation" name="WarehouseLocation" class="form-input"
               value="{{ old('WarehouseLocation', $damage->WarehouseLocation ?? null) }}" maxlength="100" placeholder="e.g. Aisle 3, Shelf B">
        <span class="form-error" id="error-WarehouseLocation">@error('WarehouseLocation'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="Remarks">Remarks</label>
        <textarea id="Remarks" name="Remarks" class="form-input" rows="2"
                  maxlength="500" placeholder="Optional remarks...">{{ old('Remarks', $damage->Remarks ?? null) }}</textarea>
        <span class="form-error" id="error-Remarks">@error('Remarks'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="Image">Photo <span style="color: var(--text-muted); font-weight: 400;">(optional)</span></label>
        <input type="file" id="Image" name="Image" class="form-input" accept="image/*">
        <span class="form-error" id="error-Image">@error('Image'){{ $message }}@enderror</span>
        @if(isset($damage) && $damage->ImagePath)
            <p style="margin-top:6px; font-size:0.85rem; color: var(--text-secondary);">
                Current: <a href="{{ \Illuminate\Support\Facades\Storage::url($damage->ImagePath) }}" target="_blank">view existing photo</a> (uploading a new one replaces it)
            </p>
        @endif
    </div>
</div>

<script>
    // Auto-populates read-only context and, when a resolvable supplier
    // exists for the product, pre-selects it — the admin only needs to
    // verify before saving, not hunt down SKU/category/supplier by hand.
    function onDamageProductChange(select) {
        const option = select.options[select.selectedIndex];
        const context = document.getElementById('damageProductContext');
        if (!option || !option.value) {
            context.style.display = 'none';
            return;
        }

        context.style.display = 'block';
        document.getElementById('damageCtxSku').textContent = option.dataset.sku || 'N/A';
        document.getElementById('damageCtxCategory').textContent = option.dataset.category || 'Uncategorized';
        document.getElementById('damageCtxCost').textContent = window.formatPeso ? window.formatPeso(option.dataset.cost) : ('₱' + parseFloat(option.dataset.cost || 0).toFixed(2));
        document.getElementById('damageCtxStock').textContent = option.dataset.stock || 0;

        const supplierSelect = document.getElementById('SupplierID');
        if (supplierSelect && option.dataset.resolvedSupplier) {
            supplierSelect.value = option.dataset.resolvedSupplier;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const productSelect = document.getElementById('ProductID');
        if (productSelect && productSelect.value) {
            onDamageProductChange(productSelect);
        }
    });
</script>
