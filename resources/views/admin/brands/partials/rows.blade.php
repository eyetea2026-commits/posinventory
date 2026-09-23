@forelse($brands as $brand)
    <tr>
        <td>{{ $brand->BrandID }}</td>
        <td><strong>{{ $brand->BrandName }}</strong></td>
        <td>
            <span class="badge badge-warning">{{ $brand->category->CategoryName ?? 'N/A' }}</span>
        </td>
        <td>
            <span class="badge badge-success">{{ $brand->products()->count() }} products</span>
        </td>
        <td>
            <div class="actions-group">
                <button type="button" class="btn btn-sm btn-secondary" title="View Details" onclick="openViewBrandModal({{ $brand->BrandID }})">
                    <i class="fa-solid fa-eye"></i>
                </button>
                <a href="{{ route('admin.brands.edit', $brand->BrandID) }}" class="btn btn-sm btn-edit" onclick="openEditBrandModal(event, {{ $brand->BrandID }})">
                    <i class="fa-solid fa-edit"></i> Edit
                </a>
                <form method="POST" action="{{ route('admin.brands.destroy', $brand->BrandID) }}" style="display:inline;" id="deleteBrandForm{{ $brand->BrandID }}">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteBrand({{ $brand->BrandID }})" title="Delete">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="5">
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-tags"></i></div>
                <p class="empty-title">No Brands Found</p>
                <p class="empty-text">Create your first brand to get started.</p>
            </div>
        </td>
    </tr>
@endforelse
