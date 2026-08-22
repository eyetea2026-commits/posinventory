{{-- Promo table rows, shared by the initial page load and the live-search
     AJAX response (admin.discounts.index re-rendered into #discountsTbody).
     One row per promo (not per assigned product) — see the "Applied
     Discount/Promo List" in the Apply tab for the per-product view. --}}
@forelse($discounts as $discount)
    @php
        $statusClass = match($discount->effective_status) {
            \App\Models\Discount::STATUS_ACTIVE => 'badge-success',
            \App\Models\Discount::STATUS_EXPIRED => 'badge-secondary',
            default => 'badge-warning',
        };
        $assignedNames = $discount->products->pluck('ProductName');
    @endphp
    <tr>
        <td>{{ $discount->Name ?? '—' }}</td>
        <td><code>{{ $discount->PromoCode ?? '—' }}</code></td>
        <td>{{ $discount->DiscountType === 'fixed' ? 'Fixed Amount' : 'Percentage' }}</td>
        <td class="rate-cell">{{ $discount->DiscountType === 'fixed' ? '₱' . number_format($discount->DiscountRate, 2) : number_format($discount->DiscountRate, 2) . '%' }}</td>
        <td>{{ $discount->StartDate?->format('M d, Y') ?? '—' }}</td>
        <td>{{ $discount->EndDate?->format('M d, Y') ?? '—' }}</td>
        <td><span class="badge {{ $statusClass }}">{{ $discount->effective_status_label }}</span></td>
        <td>
            @if($assignedNames->isEmpty())
                <span style="color:#94a3b8;">— none yet —</span>
            @else
                <span title="{{ $assignedNames->implode(', ') }}">{{ $assignedNames->take(2)->implode(', ') }}{{ $assignedNames->count() > 2 ? ' +' . ($assignedNames->count() - 2) . ' more' : '' }}</span>
            @endif
        </td>
        <td>
            <div class="actions-group">
                <a href="{{ route('admin.discounts.show', $discount->DiscountID) }}" class="btn btn-sm btn-secondary" title="View">
                    <i class="fa-solid fa-eye"></i>
                </a>
                <a href="{{ route('admin.discounts.edit', $discount->DiscountID) }}" class="btn btn-sm btn-primary" onclick="openEditDiscountModal(event, {{ $discount->DiscountID }})" title="Edit">
                    <i class="fa-solid fa-edit"></i>
                </a>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="9">
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-percent"></i></div>
                <p class="empty-title">No Promo Codes Found</p>
                <p class="empty-text">Create your first promo code to get started.</p>
            </div>
        </td>
    </tr>
@endforelse
