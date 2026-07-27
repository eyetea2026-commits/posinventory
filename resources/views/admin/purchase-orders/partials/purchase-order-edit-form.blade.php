{{-- Header-only edit form for a draft/pending Purchase Order. Line items are
     locked in once a PO leaves draft/pending (receiving starts referencing
     the exact line IDs), so item corrections happen by cancelling and
     recreating while still in draft/pending, not through this form. --}}
<input type="hidden" name="_method" value="PUT">

<div class="form-grid">
    <div class="form-group">
        <label class="form-label" for="edit-SupplierID">Supplier <span style="color: var(--danger);">*</span></label>
        <select id="edit-SupplierID" name="SupplierID" class="form-select" required>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->SupplierID }}" {{ $purchaseOrder->SupplierID == $supplier->SupplierID ? 'selected' : '' }}>
                    {{ $supplier->SupplierName }}
                </option>
            @endforeach
        </select>
        <span class="form-error" id="error-edit-SupplierID"></span>
    </div>

    <div class="form-group">
        <label class="form-label" for="edit-PurchaseDate">Purchase Date <span style="color: var(--danger);">*</span></label>
        <input type="date" id="edit-PurchaseDate" name="PurchaseDate" class="form-input" value="{{ \Illuminate\Support\Carbon::parse($purchaseOrder->PurchaseDate)->toDateString() }}" required>
        <span class="form-error" id="error-edit-PurchaseDate"></span>
    </div>

    <div class="form-group">
        <label class="form-label" for="edit-ExpectedDeliveryDate">Expected Delivery Date</label>
        <input type="date" id="edit-ExpectedDeliveryDate" name="ExpectedDeliveryDate" class="form-input" value="{{ $purchaseOrder->ExpectedDeliveryDate ? \Illuminate\Support\Carbon::parse($purchaseOrder->ExpectedDeliveryDate)->toDateString() : '' }}">
        <span class="form-error" id="error-edit-ExpectedDeliveryDate"></span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="edit-Notes">Notes</label>
        <textarea id="edit-Notes" name="Notes" class="form-textarea">{{ $purchaseOrder->Notes }}</textarea>
        <span class="form-error" id="error-edit-Notes"></span>
    </div>
</div>
