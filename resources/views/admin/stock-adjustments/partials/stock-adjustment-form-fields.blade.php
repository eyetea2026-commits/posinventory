{{-- Shared "New Stock Adjustment" field markup. Included by both the
     standalone create page and the Add Stock Adjustment modal.
     Product across the full width (then its serial numbers, for products
     that have them); Quantity / Date / Reason on one row; Remarks across
     the full width. An adjustment only takes stock out (Damaged or Lost):
     the quantity is entered as a plain number and stock only comes in
     through Stock Receiving. --}}
<style>
    .form-grid.adjustment-form-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    @media (max-width: 899px) { .form-grid.adjustment-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 600px) { .form-grid.adjustment-form-grid { grid-template-columns: 1fr; } }
    .adjustment-serial-count { margin-left: 6px; font-size: 0.75rem; font-weight: 600; color: #34d399; }
    .adjustment-serial-search { position: relative; }
    .adjustment-serial-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
    .adjustment-serial-search .form-input { padding-left: 34px; }
    .adjustment-serial-list {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); gap: 6px;
        max-height: 168px; overflow-y: auto; margin-top: 8px; padding: 8px;
        border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 10px;
    }
    .adjustment-serial {
        display: flex; align-items: center; gap: 6px; padding: 6px 8px; border-radius: 8px; cursor: pointer;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.82rem; color: #cbd5e1;
        background: rgba(148, 163, 184, 0.08); border: 1px solid transparent;
    }
    .adjustment-serial:has(input:checked) { border-color: #ef4444; background: rgba(239, 68, 68, 0.12); color: #fecaca; }
    .adjustment-serial input { margin: 0; accent-color: #ef4444; }
    .adjustment-serial-empty { grid-column: 1 / -1; color: #94a3b8; font-size: 0.85rem; padding: 4px; }
</style>
<div class="form-grid adjustment-form-grid">
    <div class="form-group full-width">
        <label class="form-label" for="ProductID">Product <span style="color: var(--danger);">*</span></label>
        {{-- A plain <select>: the shared searchable dropdown gives it a
             search box inside the list. Only products with stock are
             listed — nothing else can be damaged or lost. --}}
        <select id="ProductID" name="ProductID" class="form-select" required>
            <option value="">Select Product</option>
            @foreach($products as $product)
                <option value="{{ $product->ProductID }}"
                        data-unit-plural="{{ \App\Services\UnitOfMeasure::plural($product->baseUnit()) }}"
                        data-fractional="{{ $product->AllowsFractionalQuantity ? 1 : 0 }}"
                        data-stock="{{ $product->inventory?->Quantity ?? 0 }}"
                        data-serials="{{ $product->tracksSerialNumbers() ? $product->serials->pluck('SerialNumber')->toJson() : '' }}"
                        {{ (string) old('ProductID') === (string) $product->ProductID ? 'selected' : '' }}>{{ $product->ProductName }}{{ $product->Model ? ' ('.$product->Model.')' : '' }} — {{ $product->formatQuantity($product->inventory?->Quantity ?? 0) }} in stock</option>
            @endforeach
        </select>
        <span class="form-error" id="error-ProductID">@error('ProductID'){{ $message }}@enderror</span>
    </div>

    {{-- Serial-numbered products: tick (or scan) the exact pieces that were
         damaged or lost; the quantity follows the number ticked. --}}
    <div class="form-group full-width" id="adjustment-serials-group" style="display: none;">
        <label class="form-label" for="SerialSearch">Serial Numbers <span style="color: var(--danger);">*</span><span id="adjustment-serial-count" class="adjustment-serial-count"></span></label>
        <div class="adjustment-serial-search">
            <i class="fas fa-barcode"></i>
            <input type="text" id="SerialSearch" class="form-input" placeholder="Scan or type a serial number..." autocomplete="off">
        </div>
        <div id="adjustment-serial-list" class="adjustment-serial-list"></div>
        <span class="form-error" id="error-SerialNumbers">@error('SerialNumbers'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="QuantityAdjust">Quantity to Remove <span id="quantity-adjust-unit" style="color: var(--text-secondary); font-weight: 400;"></span> <span style="color: var(--danger);">*</span></label>
        <input type="number" id="QuantityAdjust" name="QuantityAdjust" class="form-input" value="{{ old('QuantityAdjust') }}" required min="1" step="1" placeholder="e.g., 5">
        <span class="form-error" id="error-QuantityAdjust">@error('QuantityAdjust'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="Date">Date <span style="color: var(--danger);">*</span></label>
        <input type="date" id="Date" name="Date" class="form-input" value="{{ old('Date', now()->toDateString()) }}" required>
        <span class="form-error" id="error-Date">@error('Date'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label class="form-label" for="Reason">Reason <span style="color: var(--danger);">*</span></label>
        <select id="Reason" name="Reason" class="form-select" required onchange="window.syncStockAdjustmentReason && window.syncStockAdjustmentReason(this);">
            <option value="">Select Reason</option>
            @foreach(\App\Models\StockAdjustment::REASONS as $reasonOption)
                <option value="{{ $reasonOption }}" {{ old('Reason') === $reasonOption ? 'selected' : '' }}>{{ $reasonOption }}</option>
            @endforeach
        </select>
        <span class="form-error" id="error-Reason">@error('Reason'){{ $message }}@enderror</span>
        <p id="damaged-reason-hint" class="form-hint" style="margin-top:6px; color: var(--text-secondary); font-size:0.85rem; display: {{ old('Reason') === \App\Models\StockAdjustment::REASON_DAMAGED ? 'block' : 'none' }};">
            Damaged stock automatically creates a Damage record for supplier return.
        </p>
    </div>

    <div class="form-group full-width">
        <label class="form-label" for="Remarks">Remarks (Optional)</label>
        <textarea id="Remarks" name="Remarks" class="form-textarea" placeholder="Additional details...">{{ old('Remarks') }}</textarea>
        <span class="form-error" id="error-Remarks">@error('Remarks'){{ $message }}@enderror</span>
    </div>
</div>

<script>
    (function () {
        var productSelect = document.getElementById('ProductID');
        var quantityInput = document.getElementById('QuantityAdjust');
        var unitLabel = document.getElementById('quantity-adjust-unit');
        var serialsGroup = document.getElementById('adjustment-serials-group');
        var serialSearch = document.getElementById('SerialSearch');
        var serialList = document.getElementById('adjustment-serial-list');
        var serialCount = document.getElementById('adjustment-serial-count');
        var serialError = document.getElementById('error-SerialNumbers');
        if (!productSelect || !quantityInput || !serialsGroup) return;

        var damagedReason = @json(\App\Models\StockAdjustment::REASON_DAMAGED);
        var ticked = @json(array_values((array) old('SerialNumbers', [])));

        window.syncStockAdjustmentReason = function (select) {
            var damagedHint = document.getElementById('damaged-reason-hint');
            if (damagedHint) damagedHint.style.display = select.value === damagedReason ? 'block' : 'none';
        };

        function selectedSerials() {
            var option = productSelect.options[productSelect.selectedIndex];
            if (!option || !option.dataset.serials) return null;
            try { return JSON.parse(option.dataset.serials); } catch (e) { return null; }
        }

        function tickedBoxes() {
            return serialList.querySelectorAll('input[type="checkbox"]:checked');
        }

        // The quantity of a serial-numbered product is the number ticked.
        function updateSerialCount() {
            var count = tickedBoxes().length;
            var total = serialList.querySelectorAll('input[type="checkbox"]').length;
            serialCount.textContent = count + ' of ' + total + ' selected';
            quantityInput.value = count > 0 ? count : '';
            serialSearch.setCustomValidity(count > 0 ? '' : 'Tick the serial number of each piece being removed.');
        }

        function filterSerials() {
            var query = serialSearch.value.trim().toUpperCase();
            var shown = 0;
            serialList.querySelectorAll('.adjustment-serial').forEach(function (label) {
                var match = !query || label.dataset.serial.indexOf(query) !== -1;
                label.style.display = match ? '' : 'none';
                if (match) shown++;
            });
            var empty = serialList.querySelector('.adjustment-serial-empty');
            if (empty) empty.style.display = shown ? 'none' : '';
        }

        function renderSerials(serials) {
            serialList.innerHTML = '';
            serials.forEach(function (serial) {
                var label = document.createElement('label');
                label.className = 'adjustment-serial';
                label.dataset.serial = serial;
                var box = document.createElement('input');
                box.type = 'checkbox';
                box.name = 'SerialNumbers[]';
                box.value = serial;
                box.checked = ticked.indexOf(serial) !== -1;
                box.addEventListener('change', updateSerialCount);
                label.appendChild(box);
                label.appendChild(document.createTextNode(serial));
                serialList.appendChild(label);
            });
            var empty = document.createElement('div');
            empty.className = 'adjustment-serial-empty';
            empty.textContent = 'No matching serial number.';
            empty.style.display = 'none';
            serialList.appendChild(empty);
            ticked = [];
        }

        function syncProduct() {
            var option = productSelect.options[productSelect.selectedIndex];
            var hasProduct = !!(option && option.value);
            var fractional = hasProduct && option.dataset.fractional === '1';
            var serials = hasProduct ? selectedSerials() : null;

            // In the product's base unit; decimals only where allowed.
            unitLabel.textContent = hasProduct ? '(' + option.dataset.unitPlural + ')' : '';
            quantityInput.step = fractional ? '0.01' : '1';
            quantityInput.min = fractional ? '0.01' : '1';
            if (hasProduct) quantityInput.max = option.dataset.stock; else quantityInput.removeAttribute('max');

            serialSearch.value = '';
            if (serialError) serialError.textContent = '';
            if (serials) {
                renderSerials(serials);
                serialsGroup.style.display = '';
                quantityInput.readOnly = true;
                quantityInput.placeholder = 'Tick serial numbers';
                updateSerialCount();
            } else {
                serialList.innerHTML = '';
                serialsGroup.style.display = 'none';
                serialSearch.setCustomValidity('');
                if (quantityInput.readOnly) quantityInput.value = '';
                quantityInput.readOnly = false;
                quantityInput.placeholder = 'e.g., 5';
            }
        }

        serialSearch.addEventListener('input', function () {
            if (serialError) serialError.textContent = '';
            filterSerials();
        });

        // A scanned sticker (or a typed number + Enter) ticks that piece.
        serialSearch.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var code = serialSearch.value.trim().toUpperCase();
            if (!code) return;
            var label = serialList.querySelector('.adjustment-serial[data-serial="' + code.replace(/"/g, '') + '"]');
            if (!label) {
                if (serialError) serialError.textContent = code + ' is not an in-stock serial number of this product.';
                return;
            }
            label.querySelector('input').checked = true;
            serialSearch.value = '';
            filterSerials();
            updateSerialCount();
        });

        productSelect.addEventListener('change', syncProduct);
        if (productSelect.form) {
            productSelect.form.addEventListener('reset', function () { setTimeout(syncProduct, 0); });
        }

        syncProduct();
        var reason = document.getElementById('Reason');
        if (reason && reason.value) window.syncStockAdjustmentReason(reason);
    })();
</script>
