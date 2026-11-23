<style>
    .form-grid-2.promo-form-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .promo-form-grid .promo-span-all { grid-column: 1 / -1; }
    @media (max-width: 899px) { .form-grid-2.promo-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 600px) { .form-grid-2.promo-form-grid { grid-template-columns: 1fr; } }
</style>
<div class="form-grid-2 promo-form-grid">
    <div class="form-group">
        <label for="DiscountRate">Discount Value <span class="required">*</span></label>
        <input type="number" id="DiscountRate" name="DiscountRate" class="form-control"
               value="{{ old('DiscountRate', $discount->DiscountRate ?? null) }}" required min="1" max="100" step="0.01"
               placeholder="e.g., 10">
        <span class="error" id="error-DiscountRate">@error('DiscountRate'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label for="DiscountType">Discount Type <span class="required">*</span></label>
        <select id="DiscountType" name="DiscountType" class="form-control" required>
            <option value="percentage" {{ old('DiscountType', $discount->DiscountType ?? 'percentage') == 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
            <option value="fixed" disabled title="Not available yet">Fixed Amount (coming soon)</option>
        </select>
        <span class="error" id="error-DiscountType">@error('DiscountType'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label for="PromoCode">Promo Code <span class="required">*</span></label>
        <input type="text" id="PromoCode" name="PromoCode" class="form-control" style="text-transform:uppercase;"
               value="{{ old('PromoCode', $discount->PromoCode ?? null) }}" required maxlength="30" placeholder="e.g., SUMMER25">
        <span class="error" id="error-PromoCode">@error('PromoCode'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label for="Name">Promo Name <span class="required">*</span></label>
        <input type="text" id="Name" name="Name" class="form-control"
               value="{{ old('Name', $discount->Name ?? null) }}" required maxlength="100" placeholder="e.g., Summer Sale">
        <span class="error" id="error-Name">@error('Name'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label for="StartDate">Start Date <span class="required">*</span></label>
        @php($earliestStartDate = isset($discount) && $discount->StartDate && $discount->StartDate->lt(today()) ? $discount->StartDate->format('Y-m-d') : now()->toDateString())
        <input type="date" id="StartDate" name="StartDate" class="form-control"
               value="{{ old('StartDate', isset($discount) && $discount->StartDate ? $discount->StartDate->format('Y-m-d') : null) }}"
               min="{{ $earliestStartDate }}" required>
        <span class="error" id="error-StartDate">@error('StartDate'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label for="EndDate">End Date <span class="required">*</span></label>
        <input type="date" id="EndDate" name="EndDate" class="form-control"
               value="{{ old('EndDate', isset($discount) && $discount->EndDate ? $discount->EndDate->format('Y-m-d') : null) }}" min="{{ now()->toDateString() }}" required>
        <span class="error" id="error-EndDate">@error('EndDate'){{ $message }}@enderror</span>
    </div>

    <div class="form-group promo-span-all">
        <label for="Description">Promo Description</label>
        <textarea id="Description" name="Description" class="form-control" rows="3" maxlength="1000" placeholder="Optional details shown on the promo's details page">{{ old('Description', $discount->Description ?? null) }}</textarea>
        <span class="error" id="error-Description">@error('Description'){{ $message }}@enderror</span>
    </div>
</div>

<script>
    if (!window.promoSaveConfirmOptions) {
        window.promoSaveConfirmOptions = function (form, baseOptions) {
            var rateInput = form.querySelector('[name="DiscountRate"]');
            var rate = rateInput ? parseFloat(rateInput.value) : NaN;
            if (rate >= 100) {
                return {
                    title: 'This Promo Makes Items Free',
                    text: 'A 100% discount means every product this promo is applied to will be sold for ₱0.00. Only continue if this is allowed by company policy.',
                    icon: 'warning',
                    confirmText: 'Yes, Save 100% Promo',
                    cancelText: 'Go Back',
                    confirmColor: '#f59e0b',
                };
            }
            return baseOptions;
        };
    }

    if (!window.attachPromoDuplicateCheck) {
        window.attachPromoDuplicateCheck = function (form, excludeId) {
            if (!form) return;
            var checks = [
                { field: 'Name', key: 'name', message: 'This promo name is already in use.' },
                { field: 'PromoCode', key: 'promo_code', message: 'This promo code is already in use.' },
            ];

            checks.forEach(function (check) {
                var input = form.querySelector('[name="' + check.field + '"]');
                var errorEl = form.querySelector('#error-' + check.field);
                if (!input || !errorEl) return;
                var timer = null;

                input.addEventListener('input', function () {
                    clearTimeout(timer);
                    var value = input.value.trim();
                    if (errorEl.dataset.liveDuplicate === '1') {
                        errorEl.textContent = '';
                        errorEl.dataset.liveDuplicate = '';
                    }
                    if (!value) return;

                    timer = setTimeout(function () {
                        var body = new FormData();
                        body.append(check.field, value);
                        if (excludeId) body.append('exclude_id', excludeId);

                        fetch('{{ route('admin.discounts.check-promo-code') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            body: body,
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (input.value.trim() !== value) return;
                                if (data[check.key]) {
                                    errorEl.textContent = check.message;
                                    errorEl.dataset.liveDuplicate = '1';
                                }
                            })
                            .catch(function () { });
                    }, 350);
                });
            });
        };
    }
</script>
