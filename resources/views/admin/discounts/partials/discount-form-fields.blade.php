{{-- Shared Discount field markup. Included by the standalone create/edit
     pages and the Add/Edit Discount modals. Pass an optional $discount to
     pre-fill for editing (its absence means "Add" mode). --}}
<div class="form-group">
    <label for="Name">Promo Name <span class="text-optional">(optional)</span></label>
    <input type="text" id="Name" name="Name" class="form-control"
           value="{{ old('Name', $discount->Name ?? null) }}" maxlength="100" placeholder="e.g., Summer Sale">
    <span class="error" id="error-Name">@error('Name'){{ $message }}@enderror</span>
</div>

<div class="form-group">
    <label for="DiscountRate">Discount Rate (%) <span class="required">*</span></label>
    <input type="number" id="DiscountRate" name="DiscountRate" class="form-control"
           value="{{ old('DiscountRate', $discount->DiscountRate ?? null) }}" required min="0" max="100" step="0.01" placeholder="e.g., 10 for 10%">
    <span class="error" id="error-DiscountRate">@error('DiscountRate'){{ $message }}@enderror</span>
</div>
