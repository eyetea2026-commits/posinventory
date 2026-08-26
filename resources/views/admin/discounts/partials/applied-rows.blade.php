{{-- One row per promo that has at least one product assigned (DiscountController::index()),
     not one row per product-promo assignment — the assigned products
     themselves only show in that promo's View Details popup. Shared by the
     initial page load and the Tab 2 live-search/pagination AJAX response. --}}
@forelse($appliedAssignments as $discount)
    @php
        $today = \Illuminate\Support\Carbon::now()->startOfDay();
        if ($discount->EndDate && $discount->EndDate->lt($today)) {
            $statusLabel = 'Expired'; $statusClass = 'badge-secondary';
        } elseif ($discount->StartDate && $discount->StartDate->gt($today)) {
            // Displayed as "Scheduled" here (Apply tab only) rather than the
            // "Inactive" label Discount::STATUS_LABELS uses elsewhere in the
            // app — a promo that hasn't started yet reads more clearly as
            // scheduled than inactive in this context.
            $statusLabel = 'Scheduled'; $statusClass = 'badge-warning';
        } else {
            $statusLabel = 'Active'; $statusClass = 'badge-success';
        }
    @endphp
    <tr>
        <td>{{ $discount->Name ?? '—' }} <code>({{ $discount->PromoCode }})</code></td>
        <td>{{ $discount->products_count }}</td>
        <td>{{ $discount->DiscountType === 'fixed' ? 'Fixed Amount' : 'Percentage' }}</td>
        <td class="rate-cell">{{ $discount->DiscountType === 'fixed' ? '₱' . number_format($discount->DiscountRate, 2) : number_format($discount->DiscountRate, 2) . '%' }}</td>
        <td>{{ $discount->StartDate?->format('M d, Y') ?? '—' }}</td>
        <td>{{ $discount->EndDate?->format('M d, Y') ?? '—' }}</td>
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
