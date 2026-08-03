{{-- Shared Promo Code form behavior: product-select -> original price
     auto-fill, live discounted-price calculation. Scoped via
     `.closest('form')` on the element that triggered the event rather than
     document-wide getElementById, since the Add and Edit modals can both
     have a #ProductID/#OriginalPriceDisplay/etc. in the DOM at once (the
     Add modal's fields are always present; the Edit modal's are injected
     when opened) — only the form the admin is actually typing in should
     update. --}}
<script>
    window.onDiscountProductChange = function (selectEl) {
        const form = selectEl.closest('form');
        if (!form) return;

        const option = selectEl.options[selectEl.selectedIndex];
        const price = option ? parseFloat(option.dataset.price || '0') : 0;
        form.dataset.selectedProductPrice = option && option.value ? price : '';

        const priceDisplay = form.querySelector('#OriginalPriceDisplay');
        if (priceDisplay) {
            priceDisplay.value = option && option.value ? window.formatPeso(price) : '—';
        }

        window.recalcDiscountedPrice(selectEl);
    };

    window.recalcDiscountedPrice = function (triggerEl) {
        const form = triggerEl.closest('form');
        if (!form) return;

        const priceRaw = form.dataset.selectedProductPrice;
        const discountedDisplay = form.querySelector('#DiscountedPriceDisplay');
        const rateInput = form.querySelector('#DiscountRate');
        if (!discountedDisplay || !rateInput) return;

        if (priceRaw === '' || priceRaw === undefined) {
            discountedDisplay.value = '—';
            return;
        }

        const price = parseFloat(priceRaw);
        const rate = parseFloat(rateInput.value);
        if (isNaN(rate) || rate < 0) {
            discountedDisplay.value = '—';
            return;
        }

        discountedDisplay.value = window.formatPeso(price * (1 - (rate / 100)));
    };

    // Initialize dataset.selectedProductPrice for any form that already has
    // a product pre-selected on render (the Edit modal, once its fields are
    // injected — see openEditDiscountModal in discounts/index.blade.php).
    window.initDiscountFormPrice = function (form) {
        const select = form.querySelector('#ProductID');
        if (select && select.value) {
            window.onDiscountProductChange(select);
        }
    };
</script>
