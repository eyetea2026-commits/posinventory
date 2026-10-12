@forelse($categories as $category)
    <tr class="{{ $category->IsActive ? '' : 'is-inactive' }}">
        <td>{{ $categories->firstItem() + $loop->index }}</td>
        <td>
            <div class="category-name-cell">
                <strong>{{ $category->CategoryName }}</strong>
                @unless($category->IsActive)
                    <span class="badge badge-secondary category-status-badge">Deactivated</span>
                @endunless
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
                {{-- Categories are deactivated, never deleted (their products,
                     brands and history stay intact). --}}
                <form method="POST" action="{{ route('admin.categories.toggle-status', $category->CategoryID) }}" style="display:inline;" id="statusForm{{ $category->CategoryID }}">
                    @csrf
                    @method('PATCH')
                    <button type="button"
                            class="btn btn-sm {{ $category->IsActive ? 'btn-danger' : 'btn-success' }}"
                            title="{{ $category->IsActive ? 'Deactivate' : 'Activate' }}"
                            onclick="confirmToggleCategoryStatus({{ $category->CategoryID }}, {{ $category->IsActive ? 'true' : 'false' }})">
                        <i class="fa-solid {{ $category->IsActive ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                        {{ $category->IsActive ? 'Deactivate' : 'Activate' }}
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
