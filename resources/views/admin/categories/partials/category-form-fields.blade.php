{{-- Shared Category field markup. Included by the standalone create/edit
     pages and the Add/Edit Category modals. Pass an optional $category to
     pre-fill for editing (its absence means "Add" mode). --}}
<div class="form-group">
    <label for="CategoryName">Category Name <span class="required">*</span></label>
    <input type="text" id="CategoryName" name="CategoryName" class="form-control"
           value="{{ old('CategoryName', $category->CategoryName ?? null) }}" required maxlength="100" placeholder="Enter category name">
    <span class="error" id="error-CategoryName">@error('CategoryName'){{ $message }}@enderror</span>
</div>

<div class="form-group">
    <label for="Description">Description</label>
    <textarea id="Description" name="Description" class="form-control"
              rows="4">{{ old('Description', $category->Description ?? null) }}</textarea>
    <span class="error" id="error-Description">@error('Description'){{ $message }}@enderror</span>
</div>
