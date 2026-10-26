{{-- "Cancel Remaining Items" for a partially received order: when the
     supplier won't deliver the rest, the owner cancels what's still owed on
     the ticked products, with a reason. The order is Closed once nothing is
     owed; until then it stays open for the other products. Used in the
     Stock Receiving "Delivery Details" pop-up and the Purchase Order page.
     Expects $purchaseOrder with items.product loaded. --}}
@if($purchaseOrder->Status === \App\Models\PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
    <form method="POST" action="{{ route('admin.purchase-orders.cancel-remaining', $purchaseOrder) }}" class="cancel-remaining-form js-confirm-submit"
          data-confirm-title="Cancel Remaining Items" data-confirm-text="Cancel what the supplier still owes on the ticked products? They will no longer be expected." data-confirm-icon="warning" data-confirm-color="#ef4444">
        @csrf
        <div class="section-title">Cancel Remaining Items</div>
        <p class="cancel-remaining-hint">If the supplier won't deliver the rest, cancel it here. The order closes once nothing is owed.</p>
        <div class="cancel-remaining-lines">
            @foreach($purchaseOrder->items as $item)
                @if($item->remaining_quantity > 0)
                    <label>
                        <input type="checkbox" name="items[]" value="{{ $item->PurchaseOrderItemID }}" checked>
                        <span>{{ $item->product?->ProductName ?? 'Unknown' }} <small>{{ $item->remaining_quantity }} still owed</small></span>
                    </label>
                @endif
            @endforeach
        </div>
        <div class="cancel-remaining-row">
            <input type="text" name="reason" class="form-input" maxlength="255" required placeholder="Reason, e.g. Supplier out of stock">
            <button type="submit" class="btn btn-danger"><i class="fas fa-ban"></i> Cancel Remaining Items</button>
        </div>
    </form>
    <style>
        .cancel-remaining-form { margin-top: 16px; padding: 12px 14px; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; background: rgba(239, 68, 68, 0.06); }
        .cancel-remaining-form .section-title { margin-top: 0; }
        .cancel-remaining-hint { margin: 0 0 10px; color: #94a3b8; font-size: 0.82rem; }
        .cancel-remaining-lines { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(220px, 100%), 1fr)); gap: 6px 14px; margin-bottom: 10px; }
        .cancel-remaining-lines label { display: flex; align-items: flex-start; gap: 8px; cursor: pointer; color: #e2e8f0; font-size: 0.88rem; }
        .cancel-remaining-lines input { margin-top: 3px; accent-color: #ef4444; }
        .cancel-remaining-lines small { display: block; color: #fca5a5; }
        .cancel-remaining-row { display: flex; gap: 10px; flex-wrap: wrap; }
        .cancel-remaining-row .form-input { flex: 1 1 240px; min-width: 0; }
    </style>
@endif
