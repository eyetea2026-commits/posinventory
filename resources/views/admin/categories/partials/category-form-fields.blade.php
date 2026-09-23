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

{{-- Brands panel: in Edit mode (a real CategoryID exists), adding/removing a
     brand chip is an immediate AJAX call against that category. In Add mode
     (no CategoryID yet), chips are held client-side only as "Brands[]"
     hidden inputs and only actually created once the whole Create Category
     form is submitted (see CategoryController::store()). Scoped via classes
     rather than ids since the Add and Edit forms both exist in the DOM at
     the same time. --}}
<div class="form-group">
    <label>Brands in this Category</label>
    <div class="brand-chip-list" data-category-id="{{ $category->CategoryID ?? '' }}">
        @if(!empty($category))
            @forelse($category->brands()->orderBy('BrandName')->get() as $brand)
                <span class="brand-chip" data-brand-id="{{ $brand->BrandID }}">
                    {{ $brand->BrandName }}
                    <button type="button" class="brand-chip-remove" onclick="window.removeCategoryBrandChip(this)" title="Remove brand">&times;</button>
                </span>
            @empty
                <span class="brand-chip-empty">No brands added yet.</span>
            @endforelse
        @else
            @foreach(old('Brands', []) as $oldBrandName)
                <span class="brand-chip" data-pending="1" data-name="{{ $oldBrandName }}">
                    {{ $oldBrandName }}
                    <input type="hidden" name="Brands[]" value="{{ $oldBrandName }}">
                    <button type="button" class="brand-chip-remove" onclick="window.removePendingBrandChip(this)" title="Remove brand">&times;</button>
                </span>
            @endforeach
            <span class="brand-chip-empty" @if(count(old('Brands', []))) style="display:none;" @endif>No brands added yet.</span>
        @endif
    </div>
    <div class="brand-add-row">
        <input type="text" class="form-control brand-name-input" placeholder="Add a brand (e.g., Samsung)" maxlength="100">
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.addCategoryBrandChip(this)">
            <i class="fas fa-plus"></i> Add
        </button>
    </div>
    <span class="error brand-name-error">@error('Brands'){{ $message }}@enderror</span>
</div>
