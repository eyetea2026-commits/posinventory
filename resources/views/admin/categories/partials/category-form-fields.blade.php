<style>
    .category-form-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        grid-template-areas: "name desc desc" "serial desc desc" "brands brands brands";
        column-gap: 16px;
    }
    .category-form-grid .cat-name { grid-area: name; }
    .category-form-grid .cat-desc { grid-area: desc; }
    .category-form-grid .cat-desc textarea { height: calc(100% - 28px); min-height: 96px; }
    .category-form-grid .cat-serial { grid-area: serial; }
    .category-form-grid .cat-brands { grid-area: brands; }
    @media (max-width: 600px) {
        .category-form-grid { grid-template-columns: 1fr; grid-template-areas: "name" "desc" "serial" "brands"; }
    }
</style>
<div class="category-form-grid">
<div class="form-group cat-name">
    <label for="CategoryName">Category Name <span class="required">*</span></label>
    <input type="text" id="CategoryName" name="CategoryName" class="form-control"
           value="{{ old('CategoryName', $category->CategoryName ?? null) }}" required maxlength="100" placeholder="Enter category name">
    <span class="error" id="error-CategoryName">@error('CategoryName'){{ $message }}@enderror</span>
</div>

<div class="form-group cat-desc">
    <label for="Description">Description</label>
    <textarea id="Description" name="Description" class="form-control"
              rows="4">{{ old('Description', $category->Description ?? null) }}</textarea>
    <span class="error" id="error-Description">@error('Description'){{ $message }}@enderror</span>
</div>

<div class="form-group cat-serial">
    <input type="hidden" name="TracksSerialNumbers" value="0">
    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:normal;">
        <input type="checkbox" name="TracksSerialNumbers" value="1"
               {{ old('TracksSerialNumbers', isset($category) ? (int) $category->TracksSerialNumbers : 1) ? 'checked' : '' }}>
        Give each piece a serial number (its barcode sticker)
    </label>
    <small style="color:#94a3b8;">Untick for consumables and wires, which use the product barcode.</small>
</div>

<div class="form-group cat-brands">
    <label>Brands in this Category</label>
    <div class="brand-chip-list" data-category-id="{{ $category->CategoryID ?? '' }}">
        @if(!empty($category))
            @forelse($category->brands()->orderByDesc('BrandID')->get() as $brand)
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
</div>
