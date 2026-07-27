@forelse($history as $row)
    @php $po = $row->purchaseOrder; @endphp
    <tr>
        <td><code>{{ $po->PONumber }}</code></td>
        <td>{{ \Illuminate\Support\Carbon::parse($po->PurchaseDate)->format('M d, Y') }}</td>
        <td>{{ $supplier->SupplierName }}</td>
        <td>{{ $row->productsOrderedCount }}</td>
        <td>{{ $row->totalQuantityOrdered }}</td>
        <td>₱{{ number_format($row->totalPurchaseAmount, 2) }}</td>
        <td>
            @php
                $badgeClass = match($po->Status) {
                    \App\Models\PurchaseOrder::STATUS_FULLY_RECEIVED => 'badge-success',
                    \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'badge-warning',
                    \App\Models\PurchaseOrder::STATUS_APPROVED => 'badge-info',
                    \App\Models\PurchaseOrder::STATUS_CANCELLED => 'badge-danger',
                    default => 'badge-secondary',
                };
            @endphp
            <span class="badge {{ $badgeClass }}">{{ \App\Models\PurchaseOrder::STATUS_LABELS[$po->Status] ?? ucfirst($po->Status) }}</span>
        </td>
        <td>
            @php
                $deliveryClass = match($row->deliveryStatus) {
                    'On Time' => 'badge-success',
                    'Late' => 'badge-danger',
                    'Partial' => 'badge-warning',
                    'Cancelled' => 'badge-secondary',
                    default => 'badge-info',
                };
            @endphp
            <span class="badge {{ $deliveryClass }}">{{ $row->deliveryStatus }}</span>
        </td>
        <td>{{ $row->receivedDate ? \Illuminate\Support\Carbon::parse($row->receivedDate)->format('M d, Y') : '—' }}</td>
        <td>
            <button type="button" class="action-btn view" title="View Details" onclick="viewSupplierHistoryDetails({{ $supplier->SupplierID }}, {{ $po->PurchaseOrderID }})">
                <i class="fas fa-eye"></i>
            </button>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="10">
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-file-invoice"></i></div>
                <p class="empty-title">No Transactions Found</p>
                <p class="empty-text">
                    @if(request('search'))
                        No purchase orders match your search.
                    @else
                        This supplier has no purchase order history yet.
                    @endif
                </p>
            </div>
        </td>
    </tr>
@endforelse
