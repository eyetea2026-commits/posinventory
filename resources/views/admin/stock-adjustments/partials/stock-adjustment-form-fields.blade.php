{{-- Shared "New Stock Adjustment" field markup. Included by both the
     standalone create page and the Add Stock Adjustment modal. --}}
<div class="form-grid">
    @php
        // For re-showing the typed product name if the form re-renders
        // after a validation error (old('ProductID') is still the ID).
        $oldProduct = old('ProductID') ? $products->firstWhere('ProductID', (int) old('ProductID')) : null;
    @endphp
    <div class="form-group full-width">
        <label class="form-label" for="ProductSearch">Product <span style="color: var(--danger);">*</span></label>
        {{-- A single searchable field, not a plain <select>: typing filters
             the browser's own native datalist suggestions immediately, no
             separate search box alongside it. The datalist only carries
             display names (HTML datalists can't carry a separate id), so
             the matching ProductID is looked up client-side and kept in
             the hidden input actually submitted. --}}
        <input type="text" id="ProductSearch" class="form-input" list="productOptions" autocomplete="off"
               placeholder="Type to search product…" required
               value="{{ old('_ProductSearch', $oldProduct ? $oldProduct->ProductName . ' (' . $oldProduct->Model . ')' : '') }}">
        <datalist id="productOptions">
            @foreach($products as $product)
                <option data-product-id="{{ $product->ProductID }}" value="{{ $product->ProductName }} ({{ $product->Model }})"></option>
            @endforeach
        </datalist>
        <input type="hidden" id="ProductID" name="ProductID" value="{{ old('ProductID', $oldProduct->ProductID ?? '') }}">
        <span class="form-error" id="error-ProductID">@error('ProductID'){{ $message }}@enderror</span>
    </div>

    <script>
        (function () {
            var searchInput = document.getElementById('ProductSearch');
            var hiddenId = document.getElementById('ProductID');
            var datalist = document.getElementById('productOptions');
            if (!searchInput || !hiddenId || !datalist) return;

            searchInput.addEventListener('input', function () {
                var typed = searchInput.value;
                var options = datalist.querySelectorAll('option');
                var matched = null;
                for (var i = 0; i < options.length; i++) {
                    if (options[i].value === typed) { matched = options[i]; break; }
                }
                // Cleared, not left stale, when the typed text no longer
                // matches an actual product exactly — prevents submitting
                // whatever the last valid selection happened to be.
                hiddenId.value = matched ? matched.dataset.productId : '';
            });
        })();
    </script>

    <div class="form-group">
        <label class="form-label" for="QuantityAdjust">Quantity Adjusted <span style="color: var(--danger);">*</span></label>
        <input type="number" id="QuantityAdjust" name="QuantityAdjust" class="form-input" value="{{ old('QuantityAdjust') }}" required placeholder="e.g., 5 or -5">
        <small style="display:block; color: var(--danger); margin-top: 4px;">(Use -5 to subtract quantity, or 5 to add quantity.)</small>
        <span class="form-error" id="error-QuantityAdjust">@error('QuantityAdjust'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="Date">Date <span style="color: var(--danger);">*</span></label>
        <input type="date" id="Date" name="Date" class="form-input" value="{{ old('Date', now()->toDateString()) }}" required>
        <span class="form-error" id="error-Date">@error('Date'){{ $message }}@enderror</span>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="Reason">Reason <span style="color: var(--danger);">*</span></label>
        <select id="Reason" name="Reason" class="form-select" required onchange="document.getElementById('damaged-reason-hint').style.display = this.value === '{{ \App\Models\StockAdjustment::REASON_DAMAGED }}' ? 'block' : 'none';">
            <option value="">Select Reason</option>
            @foreach(\App\Models\StockAdjustment::REASONS as $reasonOption)
                <option value="{{ $reasonOption }}" {{ old('Reason') === $reasonOption ? 'selected' : '' }}>{{ $reasonOption }}</option>
            @endforeach
        </select>
        <span class="form-error" id="error-Reason">@error('Reason'){{ $message }}@enderror</span>
        <p id="damaged-reason-hint" class="form-hint" style="margin-top:6px; color: var(--text-secondary); font-size:0.85rem; display: {{ old('Reason') === \App\Models\StockAdjustment::REASON_DAMAGED ? 'block' : 'none' }};">
            A decrease with this reason automatically creates a Damage record for supplier return.
        </p>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="Remarks">Remarks (Optional)</label>
        <textarea id="Remarks" name="Remarks" class="form-textarea" placeholder="Additional details...">{{ old('Remarks') }}</textarea>
        <span class="form-error" id="error-Remarks">@error('Remarks'){{ $message }}@enderror</span>
    </div>
</div>
