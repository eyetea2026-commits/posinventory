{{-- Shared "Create Purchase Order (from reorder)" field markup. Included by
     both the standalone create-from-reorder page and, via AJAX-fetched JSON,
     the glassmorphism modal opened from the Inventory module — so both
     present identical read-only Product/Supplier sections plus the editable
     Order Quantity / Remarks fields. Expects: $product, $quantity,
     $threshold, $suggestedQuantity, $resolvedSupplier, $knownSuppliers,
     $supplierState, $allSuppliers. --}}
<style>
    .reorder-section { margin-bottom: 28px; }
    .reorder-section h3 {
        margin: 0 0 16px;
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--text-primary, #f8fafc);
    }
    .reorder-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
    .reorder-detail-item {
        padding: 12px;
        background: var(--bg-hover, rgba(30, 41, 59, 0.6));
        border: 1px solid var(--border, rgba(148, 163, 184, 0.2));
        border-radius: 10px;
    }
    .reorder-detail-item label {
        display: block;
        font-size: 0.7rem;
        color: var(--text-secondary, #94a3b8);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 4px;
    }
    .reorder-detail-item span {
        font-weight: 600;
        color: var(--text-primary, #f8fafc);
        font-size: 0.95rem;
    }
    .reorder-warning {
        background: rgba(251, 191, 36, 0.1);
        border: 1px solid rgba(251, 191, 36, 0.3);
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 16px;
        color: #fbbf24;
        font-size: 0.9rem;
    }
</style>

<div class="reorder-section">
    <h3><i class="fas fa-box"></i> Product Information</h3>
    <div class="reorder-detail-grid">
        <div class="reorder-detail-item">
            <label>Product Name</label>
            <span>{{ $product->ProductName }}</span>
        </div>
        <div class="reorder-detail-item">
            <label>Category</label>
            <span>{{ $product->category?->CategoryName ?? 'N/A' }}</span>
        </div>
        <div class="reorder-detail-item">
            <label>Brand</label>
            <span>{{ $product->brand?->BrandName ?? 'N/A' }}</span>
        </div>
        <div class="reorder-detail-item">
            <label>Current Stock</label>
            <span>{{ $quantity }}</span>
        </div>
        <div class="reorder-detail-item">
            <label>Reorder Level</label>
            <span>{{ $threshold }}</span>
        </div>
        <div class="reorder-detail-item">
            <label>Cost Price</label>
            <span>{{ number_format($resolvedSupplier?->CostPrice ?? $product->CostPrice, 2) }}</span>
        </div>
        <div class="reorder-detail-item">
            <label>Suggested Reorder Quantity</label>
            <span>{{ $suggestedQuantity }}</span>
        </div>
    </div>
</div>

<div class="reorder-section">
    <h3><i class="fas fa-truck"></i> Supplier Information</h3>

    @if($supplierState === 'none')
        <div class="reorder-warning">
            <i class="fas fa-exclamation-triangle"></i>
            No supplier is assigned to this product yet. Select a supplier below to continue — it will be assigned to this product.
        </div>
        <div class="form-grid">
            <div class="form-group full-width">
                <label class="form-label" for="SupplierID">Supplier <span style="color: var(--danger);">*</span></label>
                <select id="SupplierID" name="SupplierID" class="form-select" required onchange="onReorderSupplierChange(this)">
                    <option value="">Select Supplier</option>
                    @foreach($allSuppliers as $supplier)
                        <option
                            value="{{ $supplier->SupplierID }}"
                            data-contact-person="{{ $supplier->ContactPerson }}"
                            data-contact-number="{{ $supplier->ContactNumber }}"
                            data-email="{{ $supplier->Email }}"
                            data-address="{{ $supplier->Address }}"
                            {{ old('SupplierID') == $supplier->SupplierID ? 'selected' : '' }}
                        >{{ $supplier->SupplierName }}</option>
                    @endforeach
                </select>
                <span class="form-error" id="error-SupplierID">@error('SupplierID'){{ $message }}@enderror</span>
            </div>
        </div>
    @elseif($supplierState === 'ambiguous')
        <div class="reorder-warning">
            <i class="fas fa-exclamation-triangle"></i>
            Multiple suppliers are on record for this product with none marked preferred. Choose one below.
        </div>
        <div class="form-grid">
            <div class="form-group full-width">
                <label class="form-label" for="SupplierID">Supplier <span style="color: var(--danger);">*</span></label>
                <select id="SupplierID" name="SupplierID" class="form-select" required onchange="onReorderSupplierChange(this)">
                    <option value="">Select Supplier</option>
                    @foreach($knownSuppliers as $productSupplier)
                        <option
                            value="{{ $productSupplier->supplier->SupplierID }}"
                            data-contact-person="{{ $productSupplier->supplier->ContactPerson }}"
                            data-contact-number="{{ $productSupplier->supplier->ContactNumber }}"
                            data-email="{{ $productSupplier->supplier->Email }}"
                            data-address="{{ $productSupplier->supplier->Address }}"
                            {{ old('SupplierID') == $productSupplier->supplier->SupplierID ? 'selected' : '' }}
                        >{{ $productSupplier->supplier->SupplierName }}</option>
                    @endforeach
                </select>
                <span class="form-error" id="error-SupplierID">@error('SupplierID'){{ $message }}@enderror</span>
            </div>
        </div>
    @else
        <input type="hidden" name="SupplierID" value="{{ $resolvedSupplier->SupplierID }}">
        <div class="reorder-detail-grid">
            <div class="reorder-detail-item">
                <label>Supplier Name</label>
                <span>{{ $resolvedSupplier->supplier->SupplierName }}</span>
            </div>
            <div class="reorder-detail-item">
                <label>Contact Person</label>
                <span>{{ $resolvedSupplier->supplier->ContactPerson ?? 'N/A' }}</span>
            </div>
            <div class="reorder-detail-item">
                <label>Contact Number</label>
                <span>{{ $resolvedSupplier->supplier->ContactNumber ?? 'N/A' }}</span>
            </div>
            <div class="reorder-detail-item">
                <label>Email Address</label>
                <span>{{ $resolvedSupplier->supplier->Email ?? 'N/A' }}</span>
            </div>
            <div class="reorder-detail-item">
                <label>Company Address</label>
                <span>{{ $resolvedSupplier->supplier->Address ?? 'N/A' }}</span>
            </div>
        </div>
    @endif

    <div id="reorderSupplierContactPreview" class="reorder-detail-grid" style="margin-top:12px; display:none;">
        <div class="reorder-detail-item">
            <label>Contact Person</label>
            <span id="previewContactPerson"></span>
        </div>
        <div class="reorder-detail-item">
            <label>Contact Number</label>
            <span id="previewContactNumber"></span>
        </div>
        <div class="reorder-detail-item">
            <label>Email Address</label>
            <span id="previewEmail"></span>
        </div>
        <div class="reorder-detail-item">
            <label>Company Address</label>
            <span id="previewAddress"></span>
        </div>
    </div>
</div>

<div class="reorder-section">
    <h3><i class="fas fa-clipboard-list"></i> Purchase Information</h3>
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label" for="OrderQuantity">Order Quantity <span style="color: var(--danger);">*</span></label>
            <input type="number" id="OrderQuantity" name="OrderQuantity" class="form-input" min="1" value="{{ old('OrderQuantity', $suggestedQuantity) }}" required>
            <span class="form-error" id="error-OrderQuantity">@error('OrderQuantity'){{ $message }}@enderror</span>
        </div>
        <div class="form-group full-width">
            <label class="form-label" for="Remarks">Remarks</label>
            <textarea id="Remarks" name="Remarks" class="form-textarea" placeholder="Optional remarks...">{{ old('Remarks') }}</textarea>
            <span class="form-error" id="error-Remarks">@error('Remarks'){{ $message }}@enderror</span>
        </div>
    </div>
</div>
