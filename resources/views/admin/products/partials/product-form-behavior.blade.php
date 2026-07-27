{{-- Shared "Add Product" form behavior: HTML5 validity check, live
     name/model duplicate check (reuses the existing admin.products.check-name
     AJAX endpoint), live markup/profit calculation, and the "confirm before
     saving" dialog. Does NOT decide how the form is actually submitted —
     that's the caller's job via options.onConfirmedSubmit, so the same
     validation backs both the standalone create page (real form submit) and
     the Add Product modal (AJAX submit). --}}
<script>
window.initProductAddForm = function (formId, options) {
    options = options || {};
    var form = document.getElementById(formId);
    if (!form) return null;

    var submitBtn = options.submitBtn || null;
    var idleLabel = submitBtn ? submitBtn.innerHTML : '';
    var costPriceInput = form.querySelector('#CostPrice');
    var sellingPriceInput = form.querySelector('#SellingPrice');
    var markupPriceEl = form.querySelector('#markupPrice');
    var markupPercentEl = form.querySelector('#markupPercent');
    var profitMarginInput = form.querySelector('#ProfitMargin');
    var productNameInput = form.querySelector('#ProductName');
    var modelInput = form.querySelector('#Model');
    var barcodeInput = form.querySelector('#Barcode');
    var productNameErrorEl = form.querySelector('#productNameDuplicateError');
    var modelErrorEl = form.querySelector('#modelDuplicateError');
    var barcodeErrorEl = form.querySelector('#error-Barcode');

    var formChanged = false;
    var nameDuplicate = false;
    var modelDuplicate = false;
    var barcodeDuplicate = false;
    var duplicateCheckInFlight = 0;

    form.addEventListener('submit', function (e) { e.preventDefault(); });

    var DEFAULT_PROFIT_MARGIN = 0.45; // Store default for new products — mirrors Product::PROFIT_MARGIN server-side.

    window.attachMoneyInput(costPriceInput);
    window.attachMoneyInput(sellingPriceInput);

    function currentMarginFraction() {
        var margin = parseFloat(profitMarginInput.value);
        return isNaN(margin) ? DEFAULT_PROFIT_MARGIN : (margin / 100);
    }

    function updateMarkupDisplays(costPrice, sellingPrice) {
        var markupPrice = sellingPrice - costPrice;
        var markupPercent = costPrice > 0 ? ((markupPrice / costPrice) * 100) : 0;

        markupPriceEl.textContent = window.formatPeso(markupPrice);
        markupPriceEl.classList.toggle('negative', markupPrice < 0);

        markupPercentEl.textContent = markupPercent.toFixed(1) + '%';
        markupPercentEl.classList.toggle('negative', markupPercent < 0);
    }

    // Cost Price changed: re-derive Selling Price from whatever margin is
    // currently dialed in (not a hard-reset to 45%) — an admin who already
    // customized the margin expects it to stick when the cost changes.
    function recalcFromCost() {
        var costPrice = window.parseMoney(costPriceInput.value);
        var marginFraction = currentMarginFraction();
        var sellingPrice = (costPrice > 0 && marginFraction < 1) ? (costPrice / (1 - marginFraction)) : 0;
        sellingPriceInput.value = sellingPrice > 0 ? window.formatMoneyPlain(sellingPrice) : '';
        updateMarkupDisplays(costPrice, sellingPrice);
    }

    // Profit Margin edited by hand: re-derive Selling Price from Cost using
    // the newly typed margin.
    function recalcFromMargin() {
        var costPrice = window.parseMoney(costPriceInput.value);
        var marginFraction = currentMarginFraction();
        var sellingPrice = (costPrice > 0 && marginFraction < 1) ? (costPrice / (1 - marginFraction)) : 0;
        sellingPriceInput.value = sellingPrice > 0 ? window.formatMoneyPlain(sellingPrice) : '';
        profitMarginInput.classList.toggle('negative', marginFraction < 0);
        updateMarkupDisplays(costPrice, sellingPrice);
    }

    // Selling Price edited by hand: leave it exactly as typed and re-derive
    // the Profit Margin (and markup figures) from Cost + the typed price.
    function recalcFromSelling() {
        var costPrice = window.parseMoney(costPriceInput.value);
        var sellingPrice = window.parseMoney(sellingPriceInput.value);
        var marginPercent = sellingPrice > 0 ? (((sellingPrice - costPrice) / sellingPrice) * 100) : 0;
        profitMarginInput.value = marginPercent.toFixed(1);
        profitMarginInput.classList.toggle('negative', marginPercent < 0);
        updateMarkupDisplays(costPrice, sellingPrice);
    }

    costPriceInput.addEventListener('input', function () { formChanged = true; recalcFromCost(); });
    sellingPriceInput.addEventListener('input', function () { formChanged = true; recalcFromSelling(); });
    profitMarginInput.addEventListener('input', function () { formChanged = true; recalcFromMargin(); });

    form.querySelectorAll('input, select, textarea').forEach(function (input) {
        if (input === costPriceInput || input === sellingPriceInput || input === profitMarginInput) return;
        input.addEventListener('change', function () {
            formChanged = true;
        });
        input.addEventListener('input', function () {
            formChanged = true;
            if (input === productNameInput || input === modelInput || input === barcodeInput) {
                scheduleDuplicateCheck();
            }
        });
    });

    function setDuplicateError(element, input, message) {
        if (!element || !input) return;
        element.textContent = message || '';
        element.style.display = message ? 'block' : 'none';
        input.classList.toggle('is-invalid', !!message);
    }

    function scheduleDuplicateCheck() {
        nameDuplicate = false;
        modelDuplicate = false;
        barcodeDuplicate = false;
        setDuplicateError(productNameErrorEl, productNameInput, '');
        setDuplicateError(modelErrorEl, modelInput, '');
        setDuplicateError(barcodeErrorEl, barcodeInput, '');

        var name = (productNameInput.value || '').trim();
        var model = (modelInput.value || '').trim();
        var barcode = barcodeInput ? (barcodeInput.value || '').trim() : '';
        if (!name && !model && !barcode) return;

        var requestId = ++duplicateCheckInFlight;
        clearTimeout(form.__productDuplicateTimer);
        form.__productDuplicateTimer = setTimeout(function () {
            runDuplicateCheck(requestId, name, model, barcode);
        }, 350);
    }

    function runDuplicateCheck(requestId, name, model, barcode) {
        // When editing, the product's own unchanged name/model/barcode must
        // not be flagged as a "duplicate" of itself — exclude_id is set on
        // the form's dataset by the edit page/modal, absent entirely in Add
        // mode.
        var excludeId = form.dataset.excludeId || null;

        fetch('{{ route('admin.products.check-name') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ProductName: name, Model: model, Barcode: barcode, exclude_id: excludeId })
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (requestId !== duplicateCheckInFlight) return;
                nameDuplicate = !!(data && data.name);
                modelDuplicate = !!(data && data.model);
                barcodeDuplicate = !!(data && data.barcode);
                setDuplicateError(productNameErrorEl, productNameInput, nameDuplicate ? 'A product with this name already exists. Duplicate product names are not allowed.' : '');
                setDuplicateError(modelErrorEl, modelInput, modelDuplicate ? 'A product with this model number already exists. Duplicate model numbers are not allowed.' : '');
                setDuplicateError(barcodeErrorEl, barcodeInput, barcodeDuplicate ? 'This barcode is already assigned to another product.' : '');
            })
            .catch(function () {
                // Non-fatal — server-side check catches duplicates on submit.
            });
    }

    function resetSubmitButton() {
        if (!submitBtn) return;
        submitBtn.disabled = false;
        submitBtn.innerHTML = idleLabel;
    }

    function confirmSave() {
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        if (nameDuplicate || modelDuplicate || barcodeDuplicate) {
            var parts = [];
            if (nameDuplicate) parts.push('product name "' + (productNameInput.value || '').trim() + '"');
            if (modelDuplicate) parts.push('model number "' + (modelInput.value || '').trim() + '"');
            if (barcodeDuplicate) parts.push('barcode "' + (barcodeInput.value || '').trim() + '"');
            Swal.fire({
                title: 'Duplicate Product',
                html: 'A product with the ' + parts.join(' and ') + ' already exists. Please use a different value before saving.',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        Swal.fire({
            title: options.confirmTitle || 'Confirm Save',
            text: options.confirmText || 'Are you sure you want to save this product?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b'
        }).then(function (result) {
            if (!result.isConfirmed) {
                resetSubmitButton();
                return;
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = options.submittingLabel || '<span class="btn-spinner-sm"></span> Creating...';
            }
            // The server expects a plain numeric string (validated as
            // 'numeric'), not the comma-grouped display value.
            costPriceInput.value = window.parseMoney(costPriceInput.value).toFixed(2);
            sellingPriceInput.value = window.parseMoney(sellingPriceInput.value).toFixed(2);
            formChanged = false;
            if (options.onConfirmedSubmit) options.onConfirmedSubmit(resetSubmitButton);
        });
    }

    function confirmCancel() {
        if (options.onCancel) options.onCancel(formChanged);
    }

    // On load: if a Selling Price is already present (editing an existing
    // product), derive the Profit Margin display from it rather than
    // recomputing Selling Price from the default 45% — that would silently
    // clobber a product's real, possibly-customized margin every time the
    // edit form opens. Only seed Selling Price from Cost + the default
    // margin when there's nothing there yet (Add mode).
    if (window.parseMoney(sellingPriceInput.value) > 0) {
        recalcFromSelling();
    } else {
        recalcFromCost();
    }

    return {
        confirmSave: confirmSave,
        confirmCancel: confirmCancel,
        isChanged: function () { return formChanged; },
        markUnchanged: function () { formChanged = false; },
        resetSubmitButton: resetSubmitButton
    };
};
</script>
