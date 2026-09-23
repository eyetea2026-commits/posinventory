{{-- Shared "Brands in this Category" panel behavior — used by the Add
     Category modal, the Edit Category modal (index.blade.php), and the
     standalone create/edit pages, since all of them render the same
     category-form-fields partial. Scoped via the closest .form-group
     rather than fixed element ids, because the Add and Edit forms can both
     be present in the DOM at the same time.

     Edit mode (a real CategoryID exists): add/remove is an immediate AJAX
     call against that category's brands.
     Add mode (no CategoryID yet — data-category-id=""): a brand can't be
     attached to a category that doesn't exist, so chips are held
     client-side only as "Brands[]" hidden inputs and are only actually
     created once the whole Create Category form is submitted (see
     CategoryController::store()). --}}
<script>
    function brandPanelParts(triggerEl) {
        const formGroup = triggerEl.closest('.form-group');
        return {
            formGroup: formGroup,
            list: formGroup.querySelector('.brand-chip-list'),
            input: formGroup.querySelector('.brand-name-input'),
            errorEl: formGroup.querySelector('.brand-name-error'),
            addBtn: formGroup.querySelector('.brand-add-row button'),
        };
    }

    function clearEmptyHint(list) {
        const hint = list.querySelector('.brand-chip-empty');
        if (hint) hint.style.display = 'none';
    }

    function showEmptyHintIfNeeded(list) {
        if (list.querySelector('.brand-chip')) return;
        let hint = list.querySelector('.brand-chip-empty');
        if (!hint) {
            hint = document.createElement('span');
            hint.className = 'brand-chip-empty';
            hint.textContent = 'No brands added yet.';
            list.appendChild(hint);
        }
        hint.style.display = '';
    }

    function makeSavedBrandChip(brand) {
        const chip = document.createElement('span');
        chip.className = 'brand-chip';
        chip.dataset.brandId = brand.BrandID;
        chip.textContent = brand.BrandName + ' ';
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'brand-chip-remove';
        removeBtn.title = 'Remove brand';
        removeBtn.innerHTML = '&times;';
        removeBtn.onclick = function () { window.removeCategoryBrandChip(removeBtn); };
        chip.appendChild(removeBtn);
        return chip;
    }

    function makePendingBrandChip(brandName) {
        const chip = document.createElement('span');
        chip.className = 'brand-chip';
        chip.dataset.pending = '1';
        chip.dataset.name = brandName;
        chip.textContent = brandName + ' ';
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'Brands[]';
        hidden.value = brandName;
        chip.appendChild(hidden);
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'brand-chip-remove';
        removeBtn.title = 'Remove brand';
        removeBtn.innerHTML = '&times;';
        removeBtn.onclick = function () { window.removePendingBrandChip(removeBtn); };
        chip.appendChild(removeBtn);
        return chip;
    }

    window.addCategoryBrandChip = function (triggerEl) {
        const { list, input, errorEl, addBtn } = brandPanelParts(triggerEl);
        if (!list || !input) return;

        const brandName = input.value.trim();
        errorEl.textContent = '';
        if (!brandName) {
            errorEl.textContent = 'Please enter a brand name.';
            return;
        }

        const categoryId = list.dataset.categoryId;

        if (!categoryId) {
            // Add mode: purely client-side, de-duplicated case-insensitively
            // against whatever's already been added to this pending list.
            const normalized = brandName.toLowerCase();
            const alreadyPending = Array.from(list.querySelectorAll('.brand-chip[data-pending="1"]'))
                .some(function (chip) { return (chip.dataset.name || '').toLowerCase() === normalized; });
            if (alreadyPending) {
                errorEl.textContent = 'This brand already exists under the selected category.';
                return;
            }
            clearEmptyHint(list);
            list.appendChild(makePendingBrandChip(brandName));
            input.value = '';
            return;
        }

        addBtn.disabled = true;

        fetch('{{ url('admin/categories') }}/' + categoryId + '/brands', {
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
                const data = await response.json();
                if (!response.ok) {
                    errorEl.textContent = (data.errors && data.errors.BrandName && data.errors.BrandName[0]) || 'Unable to add this brand.';
                    return;
                }
                clearEmptyHint(list);
                list.appendChild(makeSavedBrandChip(data.brand));
                input.value = '';
            })
            .catch(function () {
                errorEl.textContent = 'A network error occurred. Please try again.';
            })
            .finally(function () {
                addBtn.disabled = false;
            });
    };

    document.addEventListener('keydown', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('brand-name-input') && e.key === 'Enter') {
            e.preventDefault();
            const addBtn = e.target.closest('.form-group').querySelector('.brand-add-row button');
            if (addBtn) window.addCategoryBrandChip(addBtn);
        }
    });

    // Add mode only: no server call, just drop the chip and its hidden input.
    window.removePendingBrandChip = function (buttonEl) {
        const chip = buttonEl.closest('.brand-chip');
        const list = chip ? chip.closest('.brand-chip-list') : null;
        if (chip) chip.remove();
        if (list) showEmptyHintIfNeeded(list);
    };

    // Edit mode only: the brand already exists in the database.
    window.removeCategoryBrandChip = function (buttonEl) {
        const chip = buttonEl.closest('.brand-chip');
        const brandId = chip ? chip.dataset.brandId : null;
        if (!brandId) return;

        Swal.fire({
            title: 'Remove Brand',
            text: 'Are you sure you want to remove this brand?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Remove',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
        }).then(function (result) {
            if (!result.isConfirmed) return;

            fetch('{{ url('admin/brands') }}/' + brandId, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
                .then(async function (response) {
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        Swal.fire({
                            title: 'Cannot Remove Brand',
                            text: data.message || 'Unable to remove this brand.',
                            icon: 'error',
                            confirmButtonColor: '#ef4444',
                        });
                        return;
                    }
                    const list = chip.closest('.brand-chip-list');
                    chip.remove();
                    if (list) showEmptyHintIfNeeded(list);
                })
                .catch(function () {
                    Swal.fire({
                        title: 'Error',
                        text: 'A network error occurred. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#ef4444',
                    });
                });
        });
    };
</script>
