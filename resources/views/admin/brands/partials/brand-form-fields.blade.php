{{-- Shared Brand field markup. Included by the Add/Edit Brand modals. Pass
     an optional $brand to pre-fill for editing (its absence means "Add"
     mode), and $categories for the dropdown. --}}
<div class="form-group">
    <label for="BrandName">Brand Name <span class="required">*</span></label>
    <input type="text" id="BrandName" name="BrandName" class="form-control"
           value="{{ old('BrandName', $brand->BrandName ?? null) }}" required maxlength="100" placeholder="Enter brand name">
    <span class="error" id="error-BrandName">@error('BrandName'){{ $message }}@enderror</span>
</div>

<div class="form-group">
    <label for="CategoryID">Category <span class="required">*</span></label>
    <select id="CategoryID" name="CategoryID" class="form-control" required>
        <option value="">Select a category</option>
        @foreach($categories as $category)
            <option value="{{ $category->CategoryID }}" @selected(old('CategoryID', $brand->CategoryID ?? null) == $category->CategoryID)>
                {{ $category->CategoryName }}
            </option>
        @endforeach
    </select>
    <span class="error" id="error-CategoryID">@error('CategoryID'){{ $message }}@enderror</span>
</div>
