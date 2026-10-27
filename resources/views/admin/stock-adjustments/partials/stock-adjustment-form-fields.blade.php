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
    .adjustment-serials-table { width: 100%; border-collapse: collapse; border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 10px; }
    .adjustment-serials-table th { padding: 8px 10px; text-align: left; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; }
    .adjustment-serials-table tbody td { padding: 8px 10px; border-top: 1px solid rgba(148, 163, 184, 0.12); color: #e2e8f0; }
    .adjustment-serials-table tbody code { font-size: 0.88rem; color: #fecaca; }
    .adjustment-serials-table .adjustment-serial-empty td { color: #94a3b8; font-size: 0.88rem; }
    .adjustment-serials-table tfoot td { padding: 10px; border-top: 1px solid rgba(148, 163, 184, 0.2); vertical-align: middle; }
    .adjustment-serials-table tfoot .btn { white-space: nowrap; }
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

    {{-- Serial-numbered products: record the exact pieces that were damaged
         or lost, one row each, picked from the searchable dropdown (typing
         or scanning the sticker into its search box finds it) and added
         with Add Serial, like the product form's suppliers. The quantity
         follows the number recorded. --}}
    <div class="form-group full-width" id="adjustment-serials-group" style="display: none;">
        <label class="form-label" for="SerialNumberSelect">Serial Numbers <span style="color: var(--danger);">*</span><span id="adjustment-serial-count" class="adjustment-serial-count"></span></label>
        <table class="adjustment-serials-table">
            <thead>
                <tr><th>Serial Number</th><th style="width: 1%;">Actions</th></tr>
            </thead>
            <tbody id="adjustment-serial-rows"></tbody>
            <tfoot>
                <tr>
                    <td>
                        <select id="SerialNumberSelect" class="form-select" aria-label="Serial Number">
                            <option value="">Select Serial Number</option>
                        </select>
                    </td>
                    <td>
                        <button type="button" class="btn btn-secondary" id="addSerialNumberBtn">
                            <i class="fas fa-plus"></i> Add Serial
                        </button>
                    </td>
                </tr>
            </tfoot>
        </table>
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
        var serialSelect = document.getElementById('SerialNumberSelect');
        var serialRows = document.getElementById('adjustment-serial-rows');
        var addSerialBtn = document.getElementById('addSerialNumberBtn');
        var serialCount = document.getElementById('adjustment-serial-count');
        var serialError = document.getElementById('error-SerialNumbers');
        if (!productSelect || !quantityInput || !serialsGroup) return;

        var damagedReason = @json(\App\Models\StockAdjustment::REASON_DAMAGED);
        // In-stock serials of the chosen product, and the ones recorded.
        var available = [];
        var recorded = @json(array_values((array) old('SerialNumbers', [])));

        window.syncStockAdjustmentReason = function (select) {
            var damagedHint = document.getElementById('damaged-reason-hint');
            if (damagedHint) damagedHint.style.display = select.value === damagedReason ? 'block' : 'none';
        };

        function selectedSerials() {
            var option = productSelect.options[productSelect.selectedIndex];
            if (!option || !option.dataset.serials) return null;
            try { return JSON.parse(option.dataset.serials); } catch (e) { return null; }
        }

        // One row per recorded serial (with the SerialNumbers[] value sent);
        // the dropdown offers only the ones not recorded yet. The quantity
        // of a serial-numbered product is the number recorded.
        function renderSerials() {
            serialRows.innerHTML = '';
            recorded.forEach(function (serial, index) {
                var row = document.createElement('tr');
                var serialCell = document.createElement('td');
                var code = document.createElement('code');
                code.textContent = serial;
                serialCell.appendChild(code);
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'SerialNumbers[]';
                hidden.value = serial;
                serialCell.appendChild(hidden);
                var actionsCell = document.createElement('td');
                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-secondary';
                removeBtn.title = 'Remove ' + serial;
                removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
                removeBtn.addEventListener('click', function () {
                    recorded.splice(index, 1);
                    renderSerials();
                });
                actionsCell.appendChild(removeBtn);
                row.appendChild(serialCell);
                row.appendChild(actionsCell);
                serialRows.appendChild(row);
            });
            if (!recorded.length) {
                serialRows.innerHTML = '<tr class="adjustment-serial-empty"><td colspan="2">No serial numbers added yet.</td></tr>';
            }

            serialSelect.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select Serial Number';
            serialSelect.appendChild(placeholder);
            available.filter(function (serial) { return recorded.indexOf(serial) === -1; }).forEach(function (serial) {
                var option = document.createElement('option');
                option.value = serial;
                option.textContent = serial;
                serialSelect.appendChild(option);
            });

            var count = recorded.length;
            serialCount.textContent = count + ' of ' + available.length + ' added';
            quantityInput.value = count > 0 ? count : '';
            serialSelect.setCustomValidity(count > 0 ? '' : 'Add the serial number of each piece being removed.');
        }

        addSerialBtn.addEventListener('click', function () {
            var serial = serialSelect.value;
            if (serialError) serialError.textContent = '';
            if (!serial) {
                if (serialError) serialError.textContent = 'Select a serial number to add.';
                return;
            }
            recorded.push(serial);
            renderSerials();
        });

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

            if (serialError) serialError.textContent = '';
            if (serials) {
                // Kept only if they belong to this product (a re-shown form).
                available = serials;
                recorded = recorded.filter(function (serial) { return serials.indexOf(serial) !== -1; });
                serialsGroup.style.display = '';
                quantityInput.readOnly = true;
                quantityInput.placeholder = 'Add serial numbers';
                renderSerials();
            } else {
                available = [];
                recorded = [];
                serialRows.innerHTML = '';
                serialsGroup.style.display = 'none';
                serialSelect.setCustomValidity('');
                if (quantityInput.readOnly) quantityInput.value = '';
                quantityInput.readOnly = false;
                quantityInput.placeholder = 'e.g., 5';
            }
        }

        productSelect.addEventListener('change', syncProduct);
        if (productSelect.form) {
            productSelect.form.addEventListener('reset', function () { setTimeout(syncProduct, 0); });
        }

        syncProduct();
        var reason = document.getElementById('Reason');
        if (reason && reason.value) window.syncStockAdjustmentReason(reason);
    })();
</script>
