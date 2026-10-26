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
    var categorySelect = form.querySelector('#CategoryID');
    var brandSelect = form.querySelector('#BrandID');

    var formChanged = false;
    var nameDuplicate = false;
    var modelDuplicate = false;
    var barcodeDuplicate = false;
    var duplicateCheckInFlight = 0;

    form.addEventListener('submit', function (e) { e.preventDefault(); });

    var DEFAULT_PROFIT_MARGIN = 0.45; // Store default for new products — mirrors Product::PROFIT_MARGIN server-side.

    window.attachMoneyInput(costPriceInput);
    window.attachMoneyInput(sellingPriceInput);

    // Brand is dependent on Category: every <option> in #BrandID already
    // carries the Brand's own Category as data-category — show only the
    // ones matching whatever Category is currently selected, and replace
    // the list with a single disabled "No brands available" option when
    // none match. The previously-selected BrandID is preserved across a
    // filter pass whenever it's still valid for the new Category (e.g. on
    // first load while editing a product), and cleared otherwise.
    function filterBrandOptionsByCategory(preserveBrandId) {
        if (!categorySelect || !brandSelect) return;
        var categoryId = categorySelect.value;
        var previousValue = preserveBrandId !== undefined ? String(preserveBrandId) : brandSelect.value;
        var options = Array.prototype.slice.call(brandSelect.querySelectorAll('option[data-category]'));

        var matching = options.filter(function (option) {
            return String(option.dataset.category) === String(categoryId);
        });

        brandSelect.innerHTML = '';

        var placeholder = document.createElement('option');
        placeholder.value = '';
        if (!categoryId) {
            placeholder.textContent = 'Select a category first';
        } else if (matching.length === 0) {
            placeholder.textContent = 'No brands available for this category.';
        } else {
            placeholder.textContent = 'No Brand';
        }
        brandSelect.appendChild(placeholder);

        matching.forEach(function (option) {
            brandSelect.appendChild(option);
        });

        var stillValid = matching.some(function (option) { return option.value === previousValue; });
        brandSelect.value = stillValid ? previousValue : '';
        brandSelect.disabled = matching.length === 0;
    }

    if (categorySelect && brandSelect) {
        // Preserve the full, unfiltered option list so re-filtering after a
        // later Category change can draw from every Brand again, not just
        // whatever the previous filter pass left behind.
        var allBrandOptions = Array.prototype.slice.call(brandSelect.querySelectorAll('option[data-category]'));
        var initialBrandId = brandSelect.value;

        categorySelect.addEventListener('change', function () {
            brandSelect.innerHTML = '';
            allBrandOptions.forEach(function (option) { brandSelect.appendChild(option.cloneNode(true)); });
            // A user-initiated Category change always starts the Brand
            // field fresh, rather than risking it land on whatever
            // arbitrary option the browser defaults a freshly-repopulated
            // <select> to.
            filterBrandOptionsByCategory('');
        });

        // Initial pass (Add mode: no category picked yet, so everything is
        // hidden behind "Select a category first"; Edit mode: filter down
        // to the product's own category and keep its current brand selected).
        filterBrandOptionsByCategory(initialBrandId);

        // Inline "+Add Brand": lets the user type a new Brand name without
        // leaving this form, scoped to whichever Category is currently
        // selected. Reuses the same admin.categories.brands.store endpoint
        // the Category module's own Brand Management panel calls (see
        // category-brands-behavior.blade.php) so duplicate-name validation
        // stays identical in both places.
        var addBrandBtn = form.querySelector('#addBrandBtn');
        var brandAddRow = form.querySelector('#brandAddRow');
        var newBrandNameInput = form.querySelector('#newBrandNameInput');
        var confirmAddBrandBtn = form.querySelector('#confirmAddBrandBtn');
        var cancelAddBrandBtn = form.querySelector('#cancelAddBrandBtn');
        var brandAddError = form.querySelector('#brandAddError');

        if (addBrandBtn && brandAddRow && newBrandNameInput && confirmAddBrandBtn && cancelAddBrandBtn && brandAddError) {
            addBrandBtn.addEventListener('click', function () {
                if (!categorySelect.value) {
                    brandAddError.textContent = 'Please select a Category first.';
                    brandAddError.style.display = '';
                    return;
                }
                brandAddError.style.display = 'none';
                brandAddRow.style.display = 'flex';
                newBrandNameInput.value = '';
                newBrandNameInput.focus();
            });

            cancelAddBrandBtn.addEventListener('click', function () {
                brandAddRow.style.display = 'none';
                brandAddError.style.display = 'none';
            });

            function submitNewBrand() {
                var brandName = newBrandNameInput.value.trim();
                brandAddError.style.display = 'none';
                if (!brandName) {
                    brandAddError.textContent = 'Please enter a brand name.';
                    brandAddError.style.display = '';
                    return;
                }

                window.confirmAction({ title: 'Add Brand', text: 'Add "' + brandName + '" as a new brand?' }).then(function (result) {
                if (!result.isConfirmed) return;

                confirmAddBrandBtn.disabled = true;

                fetch('{{ url('admin/categories') }}/' + categorySelect.value + '/brands', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ BrandName: brandName }),
                })
                    .then(async function (response) {
                        var data = await response.json();
                        if (!response.ok) {
                            brandAddError.textContent = (data.errors && data.errors.BrandName && data.errors.BrandName[0]) || 'Unable to add this brand.';
                            brandAddError.style.display = '';
                            return;
                        }

                        var option = document.createElement('option');
                        option.value = data.brand.BrandID;
                        option.textContent = data.brand.BrandName;
                        option.dataset.category = categorySelect.value;

                        // Persist into the cached full option list too, so a
                        // later Category change-and-change-back still finds
                        // this brand instead of losing it.
                        allBrandOptions.push(option.cloneNode(true));
                        brandSelect.appendChild(option);
                        filterBrandOptionsByCategory(String(data.brand.BrandID));

                        brandAddRow.style.display = 'none';
                        newBrandNameInput.value = '';
                    })
                    .catch(function () {
                        brandAddError.textContent = 'A network error occurred. Please try again.';
                        brandAddError.style.display = '';
                    })
                    .finally(function () {
                        confirmAddBrandBtn.disabled = false;
                    });
                });
            }

            confirmAddBrandBtn.addEventListener('click', submitNewBrand);
            newBrandNameInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitNewBrand();
                }
            });
        }
    }

    // Add mode only: lets the admin attach suppliers (just the supplier, no
    // cost price) to a product that doesn't exist yet. Held client-side as
    // Suppliers[][SupplierID] hidden inputs and only
    // actually linked once the whole Add Product form is submitted — see
    // ProductController::store() and the matching Add Category "Brands[]"
    // pattern this mirrors. No-ops entirely in Edit mode, where the
    // #pendingProductSuppliersBody element this checks for doesn't exist —
    // the AJAX supplier panel (window.initProductSuppliersPanel) takes over
    // there instead.
    var pendingSuppliersBody = form.querySelector('#pendingProductSuppliersBody');
    var pendingSupplierError = form.querySelector('#pendingSupplierError');
    var addSupplierBtn = form.querySelector('#addProductSupplierBtn');
    var newSupplierIdSelect = form.querySelector('#newSupplierId');
    if (pendingSuppliersBody && pendingSupplierError && addSupplierBtn && newSupplierIdSelect) {
        var pendingSuppliers = [];

        function renderPendingSuppliers() {
            window.hideLinkedSupplierOptions(newSupplierIdSelect, pendingSuppliers.map(function (entry) { return entry.supplierId; }));
            if (!pendingSuppliers.length) {
                pendingSuppliersBody.innerHTML = '<tr class="pending-suppliers-empty-row"><td colspan="2" style="padding:12px; color:#94a3b8;">No suppliers added yet.</td></tr>';
                return;
            }

            pendingSuppliersBody.innerHTML = '';
            pendingSuppliers.forEach(function (entry, index) {
                var row = document.createElement('tr');

                var supplierCell = document.createElement('td');
                supplierCell.style.padding = '8px';
                supplierCell.textContent = entry.name;
                row.appendChild(supplierCell);

                var actionsCell = document.createElement('td');
                actionsCell.style.padding = '8px';
                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-secondary';
                removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
                removeBtn.addEventListener('click', function () {
                    pendingSuppliers.splice(index, 1);
                    renderPendingSuppliers();
                });
                actionsCell.appendChild(removeBtn);
                row.appendChild(actionsCell);

                var supplierIdInput = document.createElement('input');
                supplierIdInput.type = 'hidden';
                supplierIdInput.name = 'Suppliers[][SupplierID]';
                supplierIdInput.value = entry.supplierId;
                row.appendChild(supplierIdInput);

                pendingSuppliersBody.appendChild(row);
            });
        }

        addSupplierBtn.addEventListener('click', function () {
            pendingSupplierError.style.display = 'none';

            var supplierId = newSupplierIdSelect.value;
            var selectedOption = newSupplierIdSelect.options[newSupplierIdSelect.selectedIndex];
            var supplierName = selectedOption ? selectedOption.textContent : '';
            if (!supplierId) {
                pendingSupplierError.textContent = 'Select a supplier.';
                pendingSupplierError.style.display = '';
                return;
            }

            var alreadyAdded = pendingSuppliers.some(function (entry) {
                return String(entry.supplierId) === String(supplierId);
            });

            if (!alreadyAdded) {
                pendingSuppliers.push({ supplierId: supplierId, name: supplierName });
            }

            newSupplierIdSelect.value = '';
            newSupplierIdSelect.dispatchEvent(new Event('change'));
            renderPendingSuppliers();
        });
    }

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
            if (purchaseUnitPriceInput && purchaseUnitPriceInput.value.trim() !== '') {
                purchaseUnitPriceInput.value = window.parseMoney(purchaseUnitPriceInput.value).toFixed(2);
            }
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

    // Unit of Measurement: the unit names in the labels follow the chosen
    // base/purchase units, and the price per purchase unit only shows (and
    // is only required) once selling by it is chosen. How many meters a roll
    // holds is entered at Stock Receiving, not here.
    var baseUnitSelect = form.querySelector('#UnitOfMeasure');
    var purchaseUnitSelect = form.querySelector('#PurchaseUnit');
    var sellsByPurchaseUnitSelect = form.querySelector('#SellsByPurchaseUnit');
    var purchaseUnitPriceInput = form.querySelector('#PurchaseUnitPrice');
    if (purchaseUnitPriceInput) window.attachMoneyInput(purchaseUnitPriceInput);

    // The Inventory Base Unit choices of the chosen category, default first:
    // WIRE products Meter & Roll / Meter / Roll, every other product Piece /
    // Box (no category yet: Piece / Box).
    var METER_AND_ROLL = @json(\App\Services\UnitOfMeasure::METER_AND_ROLL);
    function allowedUnits() {
        var option = categorySelect && categorySelect.selectedOptions[0];
        return option && option.dataset.units ? option.dataset.units.split(',') : ['piece', 'box'];
    }

    function syncUnitOfMeasureFields() {
        if (!baseUnitSelect || !purchaseUnitSelect) return;
        var allowed = allowedUnits();
        Array.prototype.forEach.call(baseUnitSelect.options, function (option) {
            option.hidden = option.disabled = allowed.indexOf(option.value) === -1;
        });
        if (allowed.indexOf(baseUnitSelect.value) === -1) baseUnitSelect.value = allowed[0];
        // "Meter & Roll" is Meter bought by the Roll (kept in step with the
        // Purchase Unit by the change listeners below).
        // What each base unit can be bought by: a meter by the roll, a piece
        // by the box; a roll or a box only by itself (None).
        var meterAndRoll = baseUnitSelect.value === METER_AND_ROLL;
        var baseUnit = meterAndRoll ? 'meter' : (baseUnitSelect.value || 'piece');
        var basePlural = (baseUnitSelect.selectedOptions[0] && baseUnitSelect.selectedOptions[0].dataset.plural) || baseUnit;
        var purchaseChoices = { meter: ['roll'], piece: ['box'] }[baseUnit] || [];
        Array.prototype.forEach.call(purchaseUnitSelect.options, function (option) {
            option.hidden = option.disabled = option.value !== '' && purchaseChoices.indexOf(option.value) === -1;
        });
        if (purchaseUnitSelect.selectedOptions[0] && purchaseUnitSelect.selectedOptions[0].disabled) purchaseUnitSelect.value = '';
        if (meterAndRoll && purchaseUnitSelect.value === '') purchaseUnitSelect.value = 'roll';
        var purchaseUnit = purchaseUnitSelect.value;
        var purchaseName = purchaseUnit || 'purchase unit';

        form.querySelectorAll('.uom-per-base').forEach(function (el) { el.textContent = 'per ' + baseUnit; });
        form.querySelectorAll('.uom-base-plural').forEach(function (el) { el.textContent = basePlural; });
        form.querySelectorAll('.uom-purchase-name').forEach(function (el) { el.textContent = purchaseName; });
        purchaseUnitSelect.options[0].textContent = 'None (bought by the ' + baseUnit + ')';
        sellsByPurchaseUnitSelect.options[0].textContent = 'Per ' + baseUnit;
        sellsByPurchaseUnitSelect.options[1].textContent = 'Per ' + baseUnit + ' and per ' + purchaseName;
        sellsByPurchaseUnitSelect.options[1].disabled = !purchaseUnit;
        if (!purchaseUnit) sellsByPurchaseUnitSelect.value = '0';

        var sellsByPurchase = !!purchaseUnit && sellsByPurchaseUnitSelect.value === '1';
        form.querySelectorAll('.form-group[data-uom-needs-selling]').forEach(function (group) {
            group.style.display = sellsByPurchase ? '' : 'none';
        });
        purchaseUnitPriceInput.required = sellsByPurchase;
    }

    // Keeps "Meter & Roll" and the Purchase Unit in step on a wire product:
    // picking Meter & Roll sets the Purchase Unit to Roll; picking Roll as
    // the Purchase Unit of a Meter product makes it Meter & Roll; picking
    // None (or plain Meter) takes the roll off again.
    if (baseUnitSelect && purchaseUnitSelect) {
        baseUnitSelect.addEventListener('change', function () {
            if (baseUnitSelect.value === METER_AND_ROLL) {
                purchaseUnitSelect.value = 'roll';
            } else if (baseUnitSelect.value === 'meter') {
                purchaseUnitSelect.value = '';
            }
        });
        purchaseUnitSelect.addEventListener('change', function () {
            var hasMeterAndRoll = Array.prototype.some.call(baseUnitSelect.options, function (option) { return option.value === METER_AND_ROLL && !option.hidden; });
            if (!hasMeterAndRoll) return;
            if (baseUnitSelect.value === 'meter' && purchaseUnitSelect.value === 'roll') {
                baseUnitSelect.value = METER_AND_ROLL;
            } else if (baseUnitSelect.value === METER_AND_ROLL && purchaseUnitSelect.value === '') {
                baseUnitSelect.value = 'meter';
            }
        });
    }

    [categorySelect, baseUnitSelect, purchaseUnitSelect, sellsByPurchaseUnitSelect].forEach(function (select) {
        if (select) select.addEventListener('change', syncUnitOfMeasureFields);
    });
    // The Add Product popup resets the form each time it opens; the values
    // change only after the "reset" event, so sync right after it.
    form.addEventListener('reset', function () { setTimeout(syncUnitOfMeasureFields, 0); });
    syncUnitOfMeasureFields();

    return {
        confirmSave: confirmSave,
        confirmCancel: confirmCancel,
        isChanged: function () { return formChanged; },
        markUnchanged: function () { formChanged = false; },
        resetSubmitButton: resetSubmitButton
    };
};

// A supplier already linked to the product isn't offered again in the
// Supplier box: its option is hidden (the searchable dropdown skips hidden
// options) and disabled. Clears the box if it was showing one of them.
window.hideLinkedSupplierOptions = function (select, supplierIds) {
    var linked = supplierIds.map(String);
    Array.prototype.forEach.call(select.options, function (option) {
        var isLinked = option.value !== '' && linked.indexOf(option.value) !== -1;
        option.hidden = isLinked;
        option.disabled = isLinked;
    });
    if (select.value && linked.indexOf(select.value) !== -1) {
        select.value = '';
    }
};

// Wires up the per-product suppliers panel (see the Edit-mode-only block in
// product-form-fields.blade.php). Safe to call even when that panel
// isn't in the DOM (Add mode) — it just no-ops. Called once the panel's
// markup actually exists: directly on page load for the standalone Edit
// Product page, and from openEditProductModal() after the modal's AJAX
// fetch injects the form fields (see products/index.blade.php).
// Pass the form the panel is in: the Add Product popup has a supplier box
// and button with the same ids, so looking them up page-wide picked the
// Add popup's empty box and said "Select a supplier." in Edit.
window.initProductSuppliersPanel = function (productId, root) {
    root = root || document;
    var body = root.querySelector('#productSuppliersBody');
    var addBtn = root.querySelector('#addProductSupplierBtn');
    var supplierSelect = root.querySelector('#newSupplierId');
    if (!body || !addBtn || !supplierSelect) return;

    function suppliersUrl() {
        return '{{ url('admin/products') }}/' + productId + '/suppliers';
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function renderSuppliers(suppliers) {
        window.hideLinkedSupplierOptions(supplierSelect, suppliers.map(function (ps) { return ps.SupplierID; }));
        if (!suppliers.length) {
            body.innerHTML = '<tr><td colspan="3" style="padding:12px; color:#94a3b8;">No suppliers linked yet.</td></tr>';
            return;
        }
        body.innerHTML = suppliers.map(function (ps) {
            return '<tr>' +
                '<td style="padding:8px;">' + escapeHtml(ps.supplier ? ps.supplier.SupplierName : 'Unknown') + '</td>' +
                '<td style="padding:8px;">' + (ps.IsPreferred
                    ? '<span style="color:#10b981;">&#9733; Preferred</span>'
                    : '<button type="button" class="btn btn-secondary" data-prefer-id="' + ps.ProductSupplierID + '">Make Preferred</button>') + '</td>' +
                '<td style="padding:8px;"><button type="button" class="btn btn-secondary" data-remove-id="' + ps.ProductSupplierID + '"><i class="fas fa-trash"></i></button></td>' +
            '</tr>';
        }).join('');
    }

    function loadSuppliers() {
        body.innerHTML = '<tr><td colspan="3" style="padding:12px; color:#94a3b8;">Loading...</td></tr>';
        fetch(suppliersUrl(), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { renderSuppliers(data.suppliers || []); })
            .catch(function () {
                body.innerHTML = '<tr><td colspan="3" style="padding:12px; color:#ef4444;">Failed to load suppliers.</td></tr>';
            });
    }

    // Delegated (rather than rebound per row) since renderSuppliers()
    // replaces body.innerHTML wholesale on every reload.
    body.onclick = function (e) {
        var preferBtn = e.target.closest('[data-prefer-id]');
        if (preferBtn) {
            window.confirmAction({ title: 'Make Preferred', text: 'Make this the preferred supplier for this product?' }).then(function (result) {
                if (!result.isConfirmed) return;
                fetch('{{ url('admin/product-suppliers') }}/' + preferBtn.dataset.preferId + '/prefer', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                }).then(loadSuppliers);
            });
            return;
        }
        var removeBtn = e.target.closest('[data-remove-id]');
        if (removeBtn) {
            window.confirmAction({ title: 'Remove Supplier', text: 'Remove this supplier from this product?', icon: 'warning', confirmText: 'Remove', confirmColor: '#ef4444' }).then(function (result) {
                if (!result.isConfirmed) return;
                fetch('{{ url('admin/product-suppliers') }}/' + removeBtn.dataset.removeId, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                }).then(loadSuppliers);
            });
            return;
        }
    };

    addBtn.onclick = function () {
        var supplierId = supplierSelect.value;
        if (!supplierId) {
            Swal.fire({ title: 'Missing info', text: 'Select a supplier.', icon: 'warning', confirmButtonColor: '#f59e0b' });
            return;
        }
        window.confirmAction({ title: 'Add Supplier', text: 'Add this supplier to this product?' }).then(function (confirmResult) {
        if (!confirmResult.isConfirmed) return;
        fetch(suppliersUrl(), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ SupplierID: supplierId }),
        }).then(function (r) {
            return r.json().then(function (data) { return { ok: r.ok, data: data }; });
        }).then(function (result) {
            if (!result.ok) {
                var message = (result.data && result.data.message) || 'Something went wrong. Please try again.';
                if (result.data && result.data.errors) {
                    var firstError = Object.values(result.data.errors)[0];
                    if (firstError && firstError[0]) message = firstError[0];
                }
                Swal.fire({ title: 'Error', text: message, icon: 'error', confirmButtonColor: '#ef4444' });
                return;
            }
            supplierSelect.value = '';
            supplierSelect.dispatchEvent(new Event('change'));
            loadSuppliers();
        });
        });
    };

    loadSuppliers();
};
</script>
