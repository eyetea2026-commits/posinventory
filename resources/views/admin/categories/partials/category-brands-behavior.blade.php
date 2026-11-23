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
            const normalized = brandName.toLowerCase();
            const alreadyPending = Array.from(list.querySelectorAll('.brand-chip[data-pending="1"]'))
                .some(function (chip) { return (chip.dataset.name || '').toLowerCase() === normalized; });
            if (alreadyPending) {
                errorEl.textContent = 'This brand already exists under the selected category.';
                return;
            }
            clearEmptyHint(list);
            list.prepend(makePendingBrandChip(brandName));
            input.value = '';
            return;
        }

        window.confirmAction({ title: 'Add Brand', text: 'Add "' + brandName + '" to this category?' }).then(function (result) {
        if (!result.isConfirmed) return;

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
                list.prepend(makeSavedBrandChip(data.brand));
                input.value = '';
            })
            .catch(function () {
                errorEl.textContent = 'A network error occurred. Please try again.';
            })
            .finally(function () {
                addBtn.disabled = false;
            });
        });
    };

    document.addEventListener('keydown', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('brand-name-input') && e.key === 'Enter') {
            e.preventDefault();
            const addBtn = e.target.closest('.form-group').querySelector('.brand-add-row button');
            if (addBtn) window.addCategoryBrandChip(addBtn);
        }
    });

    window.removePendingBrandChip = function (buttonEl) {
        const chip = buttonEl.closest('.brand-chip');
        const list = chip ? chip.closest('.brand-chip-list') : null;
        if (chip) chip.remove();
        if (list) showEmptyHintIfNeeded(list);
    };

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
