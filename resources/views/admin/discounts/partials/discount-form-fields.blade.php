{{-- Shared "Add Discount" field markup. Included by both the standalone
     create page and the Add Discount modal. --}}
<div class="form-group">
    <label for="DiscountRate">Discount Rate (%) <span class="required">*</span></label>
    <input type="number" id="DiscountRate" name="DiscountRate" class="form-control"
           value="{{ old('DiscountRate') }}" required min="0" max="100" step="0.01" placeholder="e.g., 10 for 10%">
    <span class="error" id="error-DiscountRate">@error('DiscountRate'){{ $message }}@enderror</span>
</div>
