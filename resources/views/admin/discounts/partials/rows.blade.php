{{-- Promo table rows, shared by the initial page load and the live-search
     AJAX response (admin.discounts.index re-rendered into #discountsTbody). --}}
@forelse($discounts as $discount)
    @php
        $statusClass = match($discount->effective_status) {
            \App\Models\Discount::STATUS_ACTIVE => 'badge-success',
            \App\Models\Discount::STATUS_EXPIRED => 'badge-secondary',
            default => 'badge-warning',
        };
    @endphp
    <tr>
        <td>{{ $discount->product?->ProductName ?? 'N/A' }}</td>
        <td>{{ $discount->product ? '₱' . number_format($discount->product->Price, 2) : '—' }}</td>
        <td class="rate-cell">{{ number_format($discount->DiscountRate, 2) }}%</td>
        <td>{{ $discount->product ? '₱' . number_format($discount->discounted_price, 2) : '—' }}</td>
        <td><code>{{ $discount->PromoCode ?? '—' }}</code></td>
        <td>{{ $discount->Name ?? '—' }}</td>
        <td>{{ $discount->StartDate?->format('M d, Y') ?? '—' }}</td>
        <td>{{ $discount->EndDate?->format('M d, Y') ?? '—' }}</td>
        <td><span class="badge {{ $statusClass }}">{{ $discount->effective_status_label }}</span></td>
        <td>
            <div class="actions-group">
                <a href="{{ route('admin.discounts.show', $discount->DiscountID) }}" class="btn btn-sm btn-secondary" title="View">
                    <i class="fa-solid fa-eye"></i>
                </a>
                <a href="{{ route('admin.discounts.edit', $discount->DiscountID) }}" class="btn btn-sm btn-primary" onclick="openEditDiscountModal(event, {{ $discount->DiscountID }})" title="Edit">
                    <i class="fa-solid fa-edit"></i>
                </a>
                @if($discount->effective_status !== \App\Models\Discount::STATUS_EXPIRED)
                    @if($discount->Status === \App\Models\Discount::STATUS_ACTIVE)
                        <form method="POST" action="{{ route('admin.discounts.deactivate', $discount->DiscountID) }}" class="js-confirm-submit" data-confirm-title="Deactivate Promo" data-confirm-text="Deactivate this promo code?" data-confirm-icon="warning" data-confirm-color="#f59e0b" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-secondary" title="Deactivate"><i class="fa-solid fa-toggle-off"></i></button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.discounts.activate', $discount->DiscountID) }}" class="js-confirm-submit" data-confirm-title="Activate Promo" data-confirm-text="Activate this promo code?" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-secondary" title="Activate"><i class="fa-solid fa-toggle-on"></i></button>
                        </form>
                    @endif
                @endif
                <button type="button" class="btn btn-sm btn-danger" title="Delete" onclick="deleteDiscount({{ $discount->DiscountID }})">
                    <i class="fa-solid fa-trash"></i>
                </button>
                <form method="POST" action="{{ route('admin.discounts.destroy', $discount->DiscountID) }}" id="deleteDiscountForm{{ $discount->DiscountID }}" style="display:none;">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="10">
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-percent"></i></div>
                <p class="empty-title">No Promo Codes Found</p>
                <p class="empty-text">Create your first product promo code to get started.</p>
            </div>
        </td>
    </tr>
@endforelse
