{{-- Shared Promo Code field markup. Included by the standalone create/edit
     pages and the Add/Edit Promo modals. Pass an optional $discount to
     pre-fill for editing (its absence means "Add" mode). Creating/editing a
     promo never selects a product here — that's a separate step in the
     "Apply Discount/Promo" tab, decided after the promo itself exists. --}}
<div class="form-grid-2">
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
</div>

<div class="form-grid-2">
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
</div>

<div class="form-group">
    <label for="Description">Promo Description</label>
    <textarea id="Description" name="Description" class="form-control" rows="3" maxlength="1000" placeholder="Optional details shown on the promo's details page">{{ old('Description', $discount->Description ?? null) }}</textarea>
    <span class="error" id="error-Description">@error('Description'){{ $message }}@enderror</span>
</div>

<div class="form-grid-2">
    <div class="form-group">
        <label for="StartDate">Start Date <span class="required">*</span></label>
        {{-- min is only enforced for a brand-new promo — editing an existing
             one must still allow its real, possibly-already-past Start Date
             (see DiscountController::validatePromo()). --}}
        <input type="date" id="StartDate" name="StartDate" class="form-control"
               value="{{ old('StartDate', isset($discount) && $discount->StartDate ? $discount->StartDate->format('Y-m-d') : null) }}"
               @if(!isset($discount)) min="{{ now()->toDateString() }}" @endif required>
        <span class="error" id="error-StartDate">@error('StartDate'){{ $message }}@enderror</span>
    </div>

    <div class="form-group">
        <label for="EndDate">End Date <span class="required">*</span></label>
        <input type="date" id="EndDate" name="EndDate" class="form-control"
               value="{{ old('EndDate', isset($discount) && $discount->EndDate ? $discount->EndDate->format('Y-m-d') : null) }}" required>
        <span class="error" id="error-EndDate">@error('EndDate'){{ $message }}@enderror</span>
    </div>
</div>

{{-- No manual Status field — a promo's Active / Inactive / Expired state is
     always computed from Start Date / End Date against today
     (Discount::getEffectiveStatusAttribute()), never admin-set. Products are
     assigned separately, in the "Apply Discount/Promo" tab. --}}

{{-- A 100% promo makes the item free, which is allowed but must be a
     deliberate choice -- every save button (both modals and both standalone
     pages) builds its confirm dialog through this so the warning is the same
     everywhere. Guarded because this partial can be included twice on one
     page (the Add and Edit modals). --}}
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
</script>
