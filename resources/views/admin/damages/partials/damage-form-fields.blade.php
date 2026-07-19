{{-- Shared "Record Damage" field markup. Included by both the standalone
     create page and the Add Damage Record modal. --}}
<div class="form-grid">
    <div class="form-group full-width">
        <label class="form-label" for="ProductID">Product <span class="required">*</span></label>
        <select id="ProductID" name="ProductID" class="form-select" required>
            <option value="">Select Product</option>
            @foreach($products as $product)
                <option value="{{ $product->ProductID }}" {{ old('ProductID') == $product->ProductID ? 'selected' : '' }}>
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

    <div class="form-group full-width">
        <label class="form-label" for="PurchaseOrderID">Purchase Order <span style="color: var(--text-muted); font-weight: 400;">(optional)</span></label>
        <select id="PurchaseOrderID" name="PurchaseOrderID" class="form-select">
            <option value="">None / Not linked to a PO</option>
            @foreach($purchaseOrders as $po)
                <option value="{{ $po->PurchaseOrderID }}" {{ old('PurchaseOrderID') == $po->PurchaseOrderID ? 'selected' : '' }}>
                    PO #{{ $po->PurchaseOrderID }} &mdash; {{ $po->supplier?->SupplierName }}
                </option>
            @endforeach
        </select>
        <span class="form-error" id="error-PurchaseOrderID">@error('PurchaseOrderID'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="Quantity">Quantity Damaged <span class="required">*</span></label>
        <input type="number" id="Quantity" name="Quantity" class="form-input"
               value="{{ old('Quantity') }}" required min="1">
        <span class="form-error" id="error-Quantity">@error('Quantity'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="DateRecorded">Date Recorded <span class="required">*</span></label>
        <input type="date" id="DateRecorded" name="DateRecorded" class="form-input"
               value="{{ old('DateRecorded', date('Y-m-d')) }}" required>
        <span class="form-error" id="error-DateRecorded">@error('DateRecorded'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="DamageType">Damage Type / Reason <span class="required">*</span></label>
        <select id="DamageType" name="DamageType" class="form-select" required>
            <option value="">Select Damage Type</option>
            @foreach(\App\Models\DamagedProduct::DAMAGE_TYPES as $value => $label)
                <option value="{{ $value }}" {{ old('DamageType') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        <span class="form-error" id="error-DamageType">@error('DamageType'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="Description">Damage Description <span class="required">*</span></label>
        <textarea id="Description" name="Description" class="form-input" rows="3"
                  required maxlength="500" placeholder="Describe the damage...">{{ old('Description') }}</textarea>
        <span class="form-error" id="error-Description">@error('Description'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="InspectionNotes">Inspection Notes</label>
        <textarea id="InspectionNotes" name="InspectionNotes" class="form-input" rows="3"
                  maxlength="1000" placeholder="Optional inspection findings...">{{ old('InspectionNotes') }}</textarea>
        <span class="form-error" id="error-InspectionNotes">@error('InspectionNotes'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="WarehouseLocation">Warehouse Location</label>
        <input type="text" id="WarehouseLocation" name="WarehouseLocation" class="form-input"
               value="{{ old('WarehouseLocation') }}" maxlength="100" placeholder="e.g. Aisle 3, Shelf B">
        <span class="form-error" id="error-WarehouseLocation">@error('WarehouseLocation'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="Remarks">Remarks</label>
        <textarea id="Remarks" name="Remarks" class="form-input" rows="2"
                  maxlength="500" placeholder="Optional remarks...">{{ old('Remarks') }}</textarea>
        <span class="form-error" id="error-Remarks">@error('Remarks'){{ $message }}@enderror</span>
    </div>
</div>
