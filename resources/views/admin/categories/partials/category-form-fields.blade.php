{{-- Shared "Add Category" field markup. Included by both the standalone
     create page and the Add Category modal. --}}
<div class="form-group">
    <label for="CategoryName">Category Name <span class="required">*</span></label>
    <input type="text" id="CategoryName" name="CategoryName" class="form-control"
           value="{{ old('CategoryName') }}" required maxlength="100" placeholder="Enter category name">
    <span class="error" id="error-CategoryName">@error('CategoryName'){{ $message }}@enderror</span>
</div>

<div class="form-group">
    <label for="Description">Description</label>
    <textarea id="Description" name="Description" class="form-control"
              rows="4">{{ old('Description') }}</textarea>
    <span class="error" id="error-Description">@error('Description'){{ $message }}@enderror</span>
</div>
