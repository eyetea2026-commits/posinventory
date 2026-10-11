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

    // Add mode only: lets the admin attach suppliers (with a per-supplier
    // Cost Price) to a product that doesn't exist yet. Held client-side as
    // Suppliers[][SupplierID]/Suppliers[][CostPrice] hidden inputs and only
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
    var newSupplierCostInput = form.querySelector('#newSupplierCost');

    if (pendingSuppliersBody && pendingSupplierError && addSupplierBtn && newSupplierIdSelect && newSupplierCostInput) {
        var pendingSuppliers = [];

        function renderPendingSuppliers() {
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
                costCell.textContent = '₱' + parseFloat(entry.cost).toFixed(2);
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
                supplierIdInput.name = 'Suppliers[][SupplierID]';
                supplierIdInput.value = entry.supplierId;
                row.appendChild(supplierIdInput);

                var costInput = document.createElement('input');
                costInput.type = 'hidden';
                costInput.name = 'Suppliers[][CostPrice]';
                costInput.value = entry.cost;
                row.appendChild(costInput);

                pendingSuppliersBody.appendChild(row);
            });
        }

        addSupplierBtn.addEventListener('click', function () {
            pendingSupplierError.style.display = 'none';

            var supplierId = newSupplierIdSelect.value;
            var selectedOption = newSupplierIdSelect.options[newSupplierIdSelect.selectedIndex];
            var supplierName = selectedOption ? selectedOption.textContent : '';
            var cost = newSupplierCostInput.value;

            if (!supplierId || !cost) {
                pendingSupplierError.textContent = 'Select a supplier and enter a cost price.';
                pendingSupplierError.style.display = '';
                return;
            }

            var existingEntry = pendingSuppliers.find(function (entry) {
                return String(entry.supplierId) === String(supplierId);
            });

            if (existingEntry) {
                existingEntry.cost = cost;
            } else {
                pendingSuppliers.push({ supplierId: supplierId, name: supplierName, cost: cost });
            }

            newSupplierIdSelect.value = '';
            newSupplierCostInput.value = '';
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

// Wires up the per-product suppliers panel (see the Edit-mode-only block in
// product-form-fields.blade.php). Safe to call even when that panel
// isn't in the DOM (Add mode) — it just no-ops. Called once the panel's
// markup actually exists: directly on page load for the standalone Edit
// Product page, and from openEditProductModal() after the modal's AJAX
// fetch injects the form fields (see products/index.blade.php).
window.initProductSuppliersPanel = function (productId) {
    var body = document.getElementById('productSuppliersBody');
    var addBtn = document.getElementById('addProductSupplierBtn');
    if (!body || !addBtn) return;

    function suppliersUrl() {
        return '{{ url('admin/products') }}/' + productId + '/suppliers';
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function renderSuppliers(suppliers) {
        if (!suppliers.length) {
            body.innerHTML = '<tr><td colspan="4" style="padding:12px; color:#94a3b8;">No suppliers linked yet.</td></tr>';
            return;
        }
        body.innerHTML = suppliers.map(function (ps) {
            return '<tr>' +
                '<td style="padding:8px;">' + escapeHtml(ps.supplier ? ps.supplier.SupplierName : 'Unknown') + '</td>' +
                '<td style="padding:8px;">₱' + parseFloat(ps.CostPrice).toFixed(2) + '</td>' +
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

    // Delegated (rather than rebound per row) since renderSuppliers()
    // replaces body.innerHTML wholesale on every reload.
    body.onclick = function (e) {
        var preferBtn = e.target.closest('[data-prefer-id]');
        if (preferBtn) {
            fetch('{{ url('admin/product-suppliers') }}/' + preferBtn.dataset.preferId + '/prefer', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            }).then(loadSuppliers);
            return;
        }
        var removeBtn = e.target.closest('[data-remove-id]');
        if (removeBtn) {
            fetch('{{ url('admin/product-suppliers') }}/' + removeBtn.dataset.removeId, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            }).then(loadSuppliers);
            return;
        }
    };

    addBtn.onclick = function () {
        var supplierId = document.getElementById('newSupplierId').value;
        var cost = document.getElementById('newSupplierCost').value;
        if (!supplierId || !cost) {
            Swal.fire({ title: 'Missing info', text: 'Select a supplier and enter a cost price.', icon: 'warning', confirmButtonColor: '#f59e0b' });
            return;
        }
        fetch(suppliersUrl(), {
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
                return;
            }
            document.getElementById('newSupplierCost').value = '';
            loadSuppliers();
        });
    };

    loadSuppliers();
};
</script>
