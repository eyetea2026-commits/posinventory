@forelse($categories as $category)
    <tr>
        <td>{{ $categories->firstItem() + $loop->index }}</td>
        <td>
            <div class="category-name-cell">
                <strong>{{ $category->CategoryName }}</strong>
                @if(!empty($category->Description))
                    <div class="category-description">{{ $category->Description }}</div>
                @endif
            </div>
        </td>
        <td>
            <span class="badge badge-success">{{ $category->products_count }} products</span>
        </td>
        <td>
            <div class="actions-group">
                <a href="{{ route('admin.categories.edit', $category->CategoryID) }}" class="btn btn-sm btn-edit" onclick="openEditCategoryModal(event, {{ $category->CategoryID }})">
                    <i class="fa-solid fa-edit"></i> Edit
                </a>
                <form method="POST" action="{{ route('admin.categories.destroy', $category->CategoryID) }}" style="display:inline;" id="deleteForm{{ $category->CategoryID }}">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-sm btn-danger" title="Delete" onclick="confirmDelete({{ $category->CategoryID }})">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
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
