{{-- Purchase Order list rows — shared by the full index page's initial
     render and store()'s fast JSON response (the latter returns just this
     partial instead of the whole re-rendered page, which is what actually
     made "Save Order" slow: fetch() used to follow the create redirect and
     scrape a full page render for this exact markup). Expects
     $purchaseOrders (a paginator or any Collection of PurchaseOrder with
     supplier/items.product loaded). Optional $isHistory switches the
     actions/empty state for the History tab. --}}
@php
    $isHistory = $isHistory ?? false;
@endphp
@forelse($purchaseOrders as $order)
    <tr>
        {{-- The order's own ID (matches its PO number), not a row counter. --}}
        <td>{{ $order->PurchaseOrderID }}</td>
        <td>
            <span class="badge badge-primary">{{ $order->PONumber }}</span>
        </td>
        <td><strong>{{ $order->supplier?->SupplierName ?? 'Unknown' }}</strong></td>
        <td>{{ \Illuminate\Support\Carbon::parse($order->PurchaseDate)->format('m/d/Y') }}</td>
        <td>
            <span class="badge badge-{{ \App\Models\PurchaseOrder::statusTone($order->Status) }}">{{ \App\Models\PurchaseOrder::STATUS_LABELS[$order->Status] ?? ucfirst($order->Status) }}</span>
        </td>
        {{-- How many different products this order is for (not their
             quantities): ordering 3 products shows "3 products". --}}
        @php $productsOrdered = $order->items->pluck('ProductID')->unique()->count(); @endphp
        <td><strong>{{ $productsOrdered }}</strong> {{ $productsOrdered === 1 ? 'product' : 'products' }}</td>
        <td>
            <div class="actions-group">
                <a href="#" class="action-btn view" title="View Details" onclick="openViewPurchaseOrderModal(event, {{ $order->PurchaseOrderID }})">
                    <i class="fas fa-eye"></i>
                </a>
                @if($isHistory)
                    {{-- Safe to repeat: printPreview() only changes anything
                         while a PO is still a Draft; for an already-printed PO
                         it just re-renders the document. --}}
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
