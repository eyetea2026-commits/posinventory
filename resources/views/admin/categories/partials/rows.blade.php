@forelse($categories as $category)
    <tr>
        <td>{{ $category->CategoryID }}</td>
        <td>
            <div class="category-name-cell">
                <strong>{{ $category->CategoryName }}</strong>
                @if(!empty($category->Description))
                    <div class="category-description">{{ $category->Description }}</div>
                @endif
            </div>
        </td>
        <td>
            <span class="badge badge-success">{{ $category->products->count() }} products</span>
        </td>
        <td>
            <div class="actions-group">
                <a href="{{ route('admin.categories.edit', $category->CategoryID) }}" class="btn btn-sm btn-edit" onclick="openEditCategoryModal(event, {{ $category->CategoryID }})">
                    <i class="fa-solid fa-edit"></i> Edit
                </a>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="4">
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-tags"></i></div>
                <p class="empty-title">No Categories Found</p>
                <p class="empty-text">Create your first category to get started.</p>
            </div>
        </td>
    </tr>
@endforelse
