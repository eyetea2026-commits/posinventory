@php
    $isHistory = $isHistory ?? false;
@endphp
@forelse($purchaseOrders as $order)
    <tr>
        <td>{{ $order->PurchaseOrderID }}</td>
        <td>
            <span class="badge badge-primary">{{ $order->PONumber }}</span>
        </td>
        <td><strong>{{ $order->supplier?->SupplierName ?? 'Unknown' }}</strong></td>
        <td>{{ \Illuminate\Support\Carbon::parse($order->PurchaseDate)->format('m/d/Y') }}</td>
        <td>
            <span class="badge badge-{{ \App\Models\PurchaseOrder::statusTone($order->Status) }}">{{ \App\Models\PurchaseOrder::STATUS_LABELS[$order->Status] ?? ucfirst($order->Status) }}</span>
        </td>
        @php $productsOrdered = $order->items->pluck('ProductID')->unique()->count(); @endphp
        <td><strong>{{ $productsOrdered }}</strong> {{ $productsOrdered === 1 ? 'product' : 'products' }}</td>
        <td>
            <div class="actions-group">
                <a href="#" class="action-btn view" title="View Details" onclick="openViewPurchaseOrderModal(event, {{ $order->PurchaseOrderID }})">
                    <i class="fas fa-eye"></i>
                </a>
                @if($isHistory)
                    <a href="{{ route('admin.purchase-orders.print', $order) }}" target="_blank" class="action-btn print js-confirm-link" data-confirm-title="Reprint Purchase Order" data-confirm-text="Open this purchase order for printing?" title="Reprint">
                        <i class="fas fa-print"></i>
                    </a>
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7">
            <div class="empty-state">
                <div class="empty-icon"><i class="fas {{ $isHistory ? 'fa-clock-rotate-left' : 'fa-shopping-cart' }}"></i></div>
                <p class="empty-title">{{ $isHistory ? 'No Printed Orders Yet' : 'No Purchase Orders' }}</p>
                <p class="empty-text">{{ $isHistory ? 'Purchase orders appear here once they have been printed and sent to Stock Receiving.' : 'Purchase orders you create will appear here.' }}</p>
            </div>
        </td>
    </tr>
@endforelse
