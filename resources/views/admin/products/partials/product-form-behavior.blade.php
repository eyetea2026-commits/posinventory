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

    var DEFAULT_PROFIT_MARGIN = 0.45;

    var MAX_PRICE = 99999.99;
    window.attachMoneyInput(costPriceInput, { maxIntegerDigits: 5 });
    window.attachMoneyInput(sellingPriceInput, { maxIntegerDigits: 5 });
    var sellingPriceErrorEl = form.querySelector('#error-Price');

    function checkSellingPriceLimit(sellingPrice) {
        if (!sellingPriceErrorEl) return;
        var tooHigh = sellingPrice > MAX_PRICE;
        sellingPriceErrorEl.textContent = tooHigh ? 'The Selling Price can only have up to 5 digits (₱99,999.99). Lower the Profit Margin or the Cost Price.' : '';
        sellingPriceInput.classList.toggle('is-invalid', tooHigh);
    }

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
        var allBrandOptions = Array.prototype.slice.call(brandSelect.querySelectorAll('option[data-category]'));
        var initialBrandId = brandSelect.value;

        categorySelect.addEventListener('change', function () {
            brandSelect.innerHTML = '';
            allBrandOptions.forEach(function (option) { brandSelect.appendChild(option.cloneNode(true)); });
            filterBrandOptionsByCategory('');
        });

        filterBrandOptionsByCategory(initialBrandId);

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

    var pendingSuppliersBody = form.querySelector('#pendingProductSuppliersBody');
    var pendingSupplierError = form.querySelector('#pendingSupplierError');
    var addSupplierBtn = form.querySelector('#addProductSupplierBtn');
    var newSupplierIdSelect = form.querySelector('#newSupplierId');
    var newSupplierCostInput = form.querySelector('#newSupplierCost');
    if (pendingSuppliersBody && pendingSupplierError && addSupplierBtn && newSupplierIdSelect) {
        var pendingSuppliers = [];

        function renderPendingSuppliers() {
            window.hideLinkedSupplierOptions(newSupplierIdSelect, pendingSuppliers.map(function (entry) { return entry.supplierId; }));
            if (!pendingSuppliers.length) {
                pendingSuppliersBody.innerHTML = '<tr class="pending-suppliers-empty-row"><td colspan="3" style="padding:12px; color:#94a3b8;">No suppliers added yet.</td></tr>';
                return;
            }

            pendingSuppliersBody.innerHTML = '';
            pendingSuppliers.forEach(function (entry, index) {
                var row = document.createElement('tr');

                var supplierCell = document.createElement('td');
                supplierCell.style.padding = '8px';
                supplierCell.textContent = entry.name;
                row.appendChild(supplierCell);

                var costCell = document.createElement('td');
                costCell.style.padding = '8px';
                costCell.textContent = entry.cost !== null ? '\u20B1' + Number(entry.cost).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : "Product's Cost Price";
                row.appendChild(costCell);

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
                supplierIdInput.name = 'Suppliers[' + index + '][SupplierID]';
                supplierIdInput.value = entry.supplierId;
                row.appendChild(supplierIdInput);

                if (entry.cost !== null) {
                    var costInput = document.createElement('input');
                    costInput.type = 'hidden';
                    costInput.name = 'Suppliers[' + index + '][CostPrice]';
                    costInput.value = entry.cost;
                    row.appendChild(costInput);
                }

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

            var cost = newSupplierCostInput && newSupplierCostInput.value.trim() !== '' ? window.parseMoney(newSupplierCostInput.value) : null;
            if (cost !== null && !(cost > 0 && cost <= 99999.99)) {
                pendingSupplierError.textContent = 'Enter a Cost Price from 0.01 to 99,999.99, or leave it blank.';
                pendingSupplierError.style.display = '';
                return;
            }

            if (!alreadyAdded) {
                pendingSuppliers.push({ supplierId: supplierId, name: supplierName, cost: cost });
            }

            if (newSupplierCostInput) newSupplierCostInput.value = '';
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
        checkSellingPriceLimit(sellingPrice);
        var markupPrice = sellingPrice - costPrice;
        var markupPercent = costPrice > 0 ? ((markupPrice / costPrice) * 100) : 0;

        markupPriceEl.textContent = window.formatPeso(markupPrice);
        markupPriceEl.classList.toggle('negative', markupPrice < 0);

        markupPercentEl.textContent = markupPercent.toFixed(1) + '%';
        markupPercentEl.classList.toggle('negative', markupPercent < 0);
    }

    function recalcFromCost() {
        var costPrice = window.parseMoney(costPriceInput.value);
        var marginFraction = currentMarginFraction();
        var sellingPrice = (costPrice > 0 && marginFraction < 1) ? (costPrice / (1 - marginFraction)) : 0;
        sellingPriceInput.value = sellingPrice > 0 ? window.formatMoneyPlain(sellingPrice) : '';
        updateMarkupDisplays(costPrice, sellingPrice);
    }

    function recalcFromMargin() {
        var costPrice = window.parseMoney(costPriceInput.value);
        var marginFraction = currentMarginFraction();
        var sellingPrice = (costPrice > 0 && marginFraction < 1) ? (costPrice / (1 - marginFraction)) : 0;
        sellingPriceInput.value = sellingPrice > 0 ? window.formatMoneyPlain(sellingPrice) : '';
        profitMarginInput.classList.toggle('negative', marginFraction < 0);
        updateMarkupDisplays(costPrice, sellingPrice);
    }

    function recalcFromSelling() {
        var costPrice = window.parseMoney(costPriceInput.value);
        var sellingPrice = window.parseMoney(sellingPriceInput.value);
        var marginPercent = sellingPrice > 0 ? (((sellingPrice - costPrice) / sellingPrice) * 100) : 0;
        profitMarginInput.value = marginPercent.toFixed(1);
        profitMarginInput.classList.toggle('negative', marginPercent < 0);
        updateMarkupDisplays(costPrice, sellingPrice);
    }

    costPriceInput.addEventListener('input', function () { formChanged = true; recalcFromCost(); });
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
            costPriceInput.value = window.parseMoney(costPriceInput.value).toFixed(2);
            sellingPriceInput.value = window.parseMoney(sellingPriceInput.value).toFixed(2);
            formChanged = false;
            if (options.onConfirmedSubmit) options.onConfirmedSubmit(resetSubmitButton);
        });
    }

    function confirmCancel() {
        if (options.onCancel) options.onCancel(formChanged);
    }

    recalcFromMargin();

    var baseUnitSelect = form.querySelector('#UnitOfMeasure');
    var purchaseUnitSelect = form.querySelector('#PurchaseUnit');

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
        var meterAndRoll = baseUnitSelect.value === METER_AND_ROLL;
        var baseUnit = meterAndRoll ? 'meter' : (baseUnitSelect.value || 'piece');
        var basePlural = (baseUnitSelect.selectedOptions[0] && baseUnitSelect.selectedOptions[0].dataset.plural) || baseUnit;
        var purchaseChoices = { meter: ['roll'], piece: ['box'] }[baseUnit] || [];
        Array.prototype.forEach.call(purchaseUnitSelect.options, function (option) {
            option.hidden = option.disabled = option.value !== '' && purchaseChoices.indexOf(option.value) === -1;
        });
        if (purchaseUnitSelect.selectedOptions[0] && purchaseUnitSelect.selectedOptions[0].disabled) purchaseUnitSelect.value = '';
        if (meterAndRoll && purchaseUnitSelect.value === '') purchaseUnitSelect.value = 'roll';

        form.querySelectorAll('.uom-per-base').forEach(function (el) { el.textContent = 'per ' + baseUnit; });
        form.querySelectorAll('.uom-base-plural').forEach(function (el) { el.textContent = basePlural; });
        purchaseUnitSelect.options[0].textContent = 'None (bought by the ' + baseUnit + ')';
    }

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

    [categorySelect, baseUnitSelect, purchaseUnitSelect].forEach(function (select) {
        if (select) select.addEventListener('change', syncUnitOfMeasureFields);
    });
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

window.initProductSuppliersPanel = function (productId, root) {
    root = root || document;
    var body = root.querySelector('#productSuppliersBody');
    var addBtn = root.querySelector('#addProductSupplierBtn');
    var supplierSelect = root.querySelector('#newSupplierId');
    var costInput = root.querySelector('#newSupplierCost');
    if (!body || !addBtn || !supplierSelect) return;

    function moneyValue(value) {
        return value == null || value === '' ? '' : Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function saveSupplier(supplierId, cost) {
        return fetch(suppliersUrl(), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ SupplierID: supplierId, CostPrice: cost }),
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
            }
            return result;
        });
    }

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
            body.innerHTML = '<tr><td colspan="4" style="padding:12px; color:#94a3b8;">No suppliers linked yet.</td></tr>';
            return;
        }
        body.innerHTML = suppliers.map(function (ps) {
            return '<tr>' +
                '<td style="padding:8px;">' + escapeHtml(ps.supplier ? ps.supplier.SupplierName : 'Unknown') + '</td>' +
                '<td style="padding:8px;"><input type="text" class="form-input supplier-cost-input" data-money-digits="5" inputmode="decimal" data-supplier-id="' + ps.SupplierID + '" value="' + escapeHtml(moneyValue(ps.CostPrice)) + '" placeholder="Product\'s Cost Price" aria-label="Cost Price"></td>' +
                '<td style="padding:8px;">' + (ps.IsPreferred
                    ? '<span style="color:#10b981;">&#9733; Preferred</span>'
                    : '<button type="button" class="btn btn-secondary" data-prefer-id="' + ps.ProductSupplierID + '">Make Preferred</button>') + '</td>' +
                '<td style="padding:8px;"><button type="button" class="btn btn-secondary" data-remove-id="' + ps.ProductSupplierID + '"><i class="fas fa-trash"></i></button></td>' +
            '</tr>';
        }).join('');
    }

    function loadSuppliers() {
        body.innerHTML = '<tr><td colspan="4" style="padding:12px; color:#94a3b8;">Loading...</td></tr>';
        fetch(suppliersUrl(), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { renderSuppliers(data.suppliers || []); })
            .catch(function () {
                body.innerHTML = '<tr><td colspan="4" style="padding:12px; color:#ef4444;">Failed to load suppliers.</td></tr>';
            });
    }

    body.onchange = function (e) {
        var input = e.target.closest('.supplier-cost-input');
        if (!input) return;
        var cost = input.value.trim() === '' ? null : window.parseMoney(input.value);
        if (cost === null) return;
        saveSupplier(input.dataset.supplierId, cost).then(function (result) {
            if (result.ok) {
                input.value = moneyValue(cost);
                if (window.toastSuccess) window.toastSuccess('Supplier price saved.');
            }
        });
    };

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
        var cost = costInput && costInput.value.trim() !== '' ? window.parseMoney(costInput.value) : null;
        window.confirmAction({ title: 'Add Supplier', text: 'Add this supplier to this product?' }).then(function (confirmResult) {
        if (!confirmResult.isConfirmed) return;
        saveSupplier(supplierId, cost).then(function (result) {
            if (!result.ok) {
                return;
            }
            if (costInput) costInput.value = '';
            supplierSelect.value = '';
            supplierSelect.dispatchEvent(new Event('change'));
            loadSuppliers();
        });
        });
    };

    loadSuppliers();
};
</script>
