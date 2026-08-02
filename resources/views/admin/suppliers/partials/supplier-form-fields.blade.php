{{-- Shared Supplier field markup. Included by the standalone edit page and
     the Edit Supplier modal. Pass an optional $supplier to pre-fill. --}}
<div class="form-grid">
    <div class="form-group full-width">
        <label class="form-label">Supplier Name <span class="required">*</span></label>
        <input type="text" name="SupplierName" id="SupplierName" class="form-input" value="{{ old('SupplierName', $supplier->SupplierName ?? null) }}" required maxlength="150">
        <span class="form-error" id="error-SupplierName">@error('SupplierName'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Contact Person</label>
        <input type="text" name="ContactPerson" class="form-input" value="{{ old('ContactPerson', $supplier->ContactPerson ?? null) }}" maxlength="150">
        <span class="form-error" id="error-ContactPerson">@error('ContactPerson'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Contact Number <span class="required">*</span></label>
        <input type="text" name="ContactNumber" class="form-input" value="{{ old('ContactNumber', $supplier->ContactNumber ?? null) }}" required maxlength="50">
        <span class="form-error" id="error-ContactNumber">@error('ContactNumber'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label">Email <span class="required">*</span></label>
        <input type="email" name="Email" id="Email" class="form-input" value="{{ old('Email', $supplier->Email ?? null) }}" required maxlength="150">
        <span class="form-error" id="error-Email">@error('Email'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label">Address <span class="required">*</span></label>
        <textarea name="Address" class="form-textarea" required>{{ old('Address', $supplier->Address ?? null) }}</textarea>
        <span class="form-error" id="error-Address">@error('Address'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label">Status</label>
        <select name="Status" class="form-select">
            <option value="active" {{ old('Status', $supplier->Status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ old('Status', $supplier->Status ?? 'active') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
        <span class="form-error" id="error-Status">@error('Status'){{ $message }}@enderror</span>
    </div>
</div>
