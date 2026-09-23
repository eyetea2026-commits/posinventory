{{-- Shared "Brands in this Category" panel behavior — used by both the Edit
     Category modal (index.blade.php) and the standalone edit page, since
     both render the same category-form-fields partial. Each add/remove is
     its own immediate request against the category's own brand list (not
     bundled into the Category Save button) — the Brand and Category
     records are independent, so there is nothing to "confirm" beyond the
     action itself. --}}
<script>
    function categoryBrandsListEl() {
        return document.getElementById('categoryBrandsList');
    }

    function makeBrandChip(brand) {
        const chip = document.createElement('span');
        chip.className = 'brand-chip';
        chip.dataset.brandId = brand.BrandID;
        chip.textContent = brand.BrandName + ' ';
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'brand-chip-remove';
        removeBtn.title = 'Remove brand';
        removeBtn.innerHTML = '&times;';
        removeBtn.onclick = function () { window.removeCategoryBrandChip(brand.BrandID, removeBtn); };
        chip.appendChild(removeBtn);
        return chip;
    }

    window.addCategoryBrandChip = function () {
        const list = categoryBrandsListEl();
        const input = document.getElementById('newBrandNameInput');
        const errorEl = document.getElementById('error-BrandName');
        if (!list || !input) return;

        const brandName = input.value.trim();
        errorEl.textContent = '';
        if (!brandName) {
            errorEl.textContent = 'Please enter a brand name.';
            return;
        }

        const categoryId = list.dataset.categoryId;
        const addBtn = document.getElementById('addBrandBtn');
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
                const emptyHint = document.getElementById('categoryBrandsEmptyHint');
                if (emptyHint) emptyHint.remove();
                list.appendChild(makeBrandChip(data.brand));
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
        if (e.target && e.target.id === 'newBrandNameInput' && e.key === 'Enter') {
            e.preventDefault();
            window.addCategoryBrandChip();
        }
    });

    window.removeCategoryBrandChip = function (brandId, buttonEl) {
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
                    const chip = buttonEl.closest('.brand-chip');
                    const list = categoryBrandsListEl();
                    if (chip) chip.remove();
                    if (list && !list.querySelector('.brand-chip')) {
                        const hint = document.createElement('span');
                        hint.className = 'brand-chip-empty';
                        hint.id = 'categoryBrandsEmptyHint';
                        hint.textContent = 'No brands assigned yet.';
                        list.appendChild(hint);
                    }
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
