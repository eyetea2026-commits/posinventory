{{-- One row per DiscountProduct pivot assignment (raw DB::table join in
     DiscountController::index()), not per promo — shared by the initial
     page load and the Tab 2 live-search/pagination AJAX response. --}}
@forelse($appliedAssignments as $row)
    @php
        $today = \Illuminate\Support\Carbon::now()->startOfDay();
        $endDate = $row->EndDate ? \Illuminate\Support\Carbon::parse($row->EndDate) : null;
        $startDate = $row->StartDate ? \Illuminate\Support\Carbon::parse($row->StartDate) : null;
        if ($endDate && $endDate->lt($today)) {
            $statusLabel = 'Expired'; $statusClass = 'badge-secondary';
        } elseif ($startDate && $startDate->gt($today)) {
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
        <td>{{ $row->ProductName }}</td>
        <td>{{ $row->SKU ?? '—' }}</td>
        <td>{{ $row->Name ?? '—' }} <code>({{ $row->PromoCode }})</code></td>
        <td>{{ $row->DiscountType === 'fixed' ? 'Fixed Amount' : 'Percentage' }}</td>
        <td class="rate-cell">{{ $row->DiscountType === 'fixed' ? '₱' . number_format($row->DiscountRate, 2) : number_format($row->DiscountRate, 2) . '%' }}</td>
        <td>{{ $startDate?->format('M d, Y') ?? '—' }}</td>
        <td>{{ $endDate?->format('M d, Y') ?? '—' }}</td>
        <td><span class="badge badge-dot {{ $statusClass }}">{{ $statusLabel }}</span></td>
        <td>
            <div class="actions-group">
                <button type="button" class="btn btn-sm btn-secondary" onclick="window.openPromoDetails({{ $row->DiscountID }})">
                    <i class="fa-solid fa-eye"></i> View Details
                </button>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="9">
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-tags"></i></div>
                <p class="empty-title">No promo is available for that product</p>
                <p class="empty-text">Select a promo above and assign it to one or more products.</p>
            </div>
        </td>
    </tr>
@endforelse
