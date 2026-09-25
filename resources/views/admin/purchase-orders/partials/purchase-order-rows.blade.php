{{-- Purchase Order list rows — shared by the full index page's initial
     render and store()'s fast JSON response (the latter returns just this
     partial instead of the whole re-rendered page, which is what actually
     made "Save Order" slow: fetch() used to follow the create redirect and
     scrape a full page render for this exact markup). Expects
     $purchaseOrders (a paginator or any Collection of PurchaseOrder with
     supplier/items.product loaded). --}}
@forelse($purchaseOrders as $order)
    <tr>
        <td>
            <span class="badge badge-primary">{{ $order->PONumber }}</span>
        </td>
        <td><strong>{{ $order->supplier?->SupplierName ?? 'Unknown' }}</strong></td>
        <td>{{ \Illuminate\Support\Carbon::parse($order->PurchaseDate)->format('M d, Y') }}</td>
        <td>{{ $order->items->count() }} items</td>
        <td>
            @php
                $badgeClass = match($order->Status) {
                    \App\Models\PurchaseOrder::STATUS_FULLY_RECEIVED => 'badge-success',
                    \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'badge-warning',
                    \App\Models\PurchaseOrder::STATUS_APPROVED => 'badge-info',
                    \App\Models\PurchaseOrder::STATUS_CANCELLED => 'badge-danger',
                    default => 'badge-secondary',
                };
            @endphp
            <span class="badge {{ $badgeClass }}">{{ \App\Models\PurchaseOrder::STATUS_LABELS[$order->Status] ?? ucfirst($order->Status) }}</span>
        </td>
        <td>
            <div class="actions-group">
                <a href="#" class="action-btn view" title="View Details" onclick="openViewPurchaseOrderModal(event, {{ $order->PurchaseOrderID }})">
                    <i class="fas fa-eye"></i>
                </a>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6">
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-shopping-cart"></i></div>
                <p class="empty-title">No Purchase Orders</p>
                <p class="empty-text">Purchase orders you create will appear here.</p>
            </div>
        </td>
    </tr>
@endforelse
