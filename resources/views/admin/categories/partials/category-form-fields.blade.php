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

{{-- Brands can only be assigned once the Category itself exists (a Brand
     always belongs to exactly one Category, so there's nothing to attach
     one to yet in Add mode) — this section only renders when editing. --}}
@if(!empty($category))
    <div class="form-group">
        <label>Brands in this Category</label>
        <div id="categoryBrandsList" class="brand-chip-list" data-category-id="{{ $category->CategoryID }}">
            @forelse($category->brands()->orderBy('BrandName')->get() as $brand)
                <span class="brand-chip" data-brand-id="{{ $brand->BrandID }}">
                    {{ $brand->BrandName }}
                    <button type="button" class="brand-chip-remove" onclick="window.removeCategoryBrandChip({{ $brand->BrandID }}, this)" title="Remove brand">&times;</button>
                </span>
            @empty
                <span class="brand-chip-empty" id="categoryBrandsEmptyHint">No brands assigned yet.</span>
            @endforelse
        </div>
        <div class="brand-add-row">
            <input type="text" id="newBrandNameInput" class="form-control" placeholder="Add a brand (e.g., Samsung)" maxlength="100">
            <button type="button" class="btn btn-secondary btn-sm" id="addBrandBtn" onclick="window.addCategoryBrandChip()">
                <i class="fas fa-plus"></i> Add
            </button>
        </div>
        <span class="error" id="error-BrandName"></span>
    </div>
@endif
