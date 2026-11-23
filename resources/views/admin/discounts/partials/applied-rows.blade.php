@forelse($appliedAssignments as $discount)
    @php
        $statusLabel = $discount->effective_status_label;
        $statusClass = match ($discount->effective_status) {
            \App\Models\Discount::STATUS_ACTIVE => 'badge-success',
            \App\Models\Discount::STATUS_EXPIRED => 'badge-secondary',
            default => 'badge-warning',
        };
    @endphp
    <tr>
        <td>
            <div class="promo-name-cell">
                <span><strong>{{ $discount->Name ?? '—' }}</strong> <code>({{ $discount->PromoCode }})</code></span>
                @if(!empty($discount->Description))
                    <div class="promo-description">{{ $discount->Description }}</div>
                @endif
            </div>
        </td>
        <td>{{ $discount->products_count }}</td>
        <td>{{ $discount->DiscountType === 'fixed' ? 'Fixed Amount' : 'Percentage' }}</td>
        <td class="rate-cell">{{ $discount->DiscountType === 'fixed' ? '₱' . number_format($discount->DiscountRate, 2) : number_format($discount->DiscountRate, 2) . '%' }}</td>
        <td>{{ $discount->StartDate?->format('m/d/Y') ?? '—' }}</td>
        <td>{{ $discount->EndDate?->format('m/d/Y') ?? '—' }}</td>
        <td><span class="badge badge-dot {{ $statusClass }}">{{ $statusLabel }}</span></td>
        <td>
            <div class="actions-group">
                <button type="button" class="btn btn-sm btn-secondary" onclick="window.openPromoDetails({{ $discount->DiscountID }})">
                    <i class="fa-solid fa-eye"></i> View Details
                </button>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="8">
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-tags"></i></div>
                <p class="empty-title">No promo is currently applied</p>
                <p class="empty-text">Select a promo above and assign it to one or more products.</p>
            </div>
        </td>
    </tr>
@endforelse
