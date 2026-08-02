{{-- Damage table rows, shared by the initial page load and the live-search
     AJAX response (admin.damages.index re-rendered into #damagesTbody). --}}
@forelse($damagedProducts as $damage)
    <tr>
        <td>{{ $damage->DamageID }}</td>
        <td>{{ \Carbon\Carbon::parse($damage->DateRecorded)->format('M d, Y') }}</td>
        <td>
            <strong>{{ $damage->product->ProductName ?? 'N/A' }}</strong>
            @if($damage->SalesReturnID)
                <br><span class="badge badge-info">From Return #{{ $damage->SalesReturnID }}</span>
            @endif
        </td>
        <td>{{ $damage->supplier->SupplierName ?? 'N/A' }}</td>
        <td>{{ $damage->PurchaseOrderID ? '#' . $damage->PurchaseOrderID : '-' }}</td>
        <td><span class="badge badge-danger">{{ $damage->Quantity }}</span></td>
        <td class="description-cell">{{ \App\Models\DamagedProduct::DAMAGE_TYPES[$damage->DamageType] ?? $damage->DamageType }}</td>
        <td>
            @if($damage->Status === 'pending')
                <span class="badge badge-warning">Pending</span>
            @elseif($damage->Status === 'for_supplier_return')
                <span class="badge badge-info">Pending Supplier Return</span>
            @elseif($damage->Status === 'returned_to_supplier')
                <span class="badge badge-success">Returned to Supplier</span>
            @elseif($damage->Status === 'replacement_received')
                <span class="badge badge-success">Replacement Received</span>
            @elseif($damage->Status === 'cancelled')
                <span class="badge badge-secondary">Cancelled</span>
            @else
                <span class="badge badge-secondary">Disposed</span>
            @endif
        </td>
        <td>
            <div class="actions-group">
                <button type="button" class="btn btn-sm btn-secondary" title="View Details" onclick="viewDamageDetails({{ $damage->DamageID }})">
                    <i class="fa-solid fa-eye"></i>
                </button>
                @if($damage->Status === 'pending')
                    <a href="{{ route('admin.damages.edit', $damage->DamageID) }}" class="btn btn-sm btn-primary" onclick="openEditDamageModal(event, {{ $damage->DamageID }})">
                        <i class="fa-solid fa-edit"></i>
                    </a>
                    <form method="POST" action="{{ route('admin.damages.mark-supplier-return', $damage->DamageID) }}" class="js-confirm-submit" data-confirm-title="Mark for Supplier Return" data-confirm-text="Mark this record for supplier return?">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-secondary" title="Mark for Supplier Return"><i class="fa-solid fa-truck"></i></button>
                    </form>
                    <form method="POST" action="{{ route('admin.damages.dispose', $damage->DamageID) }}" class="js-confirm-submit" data-confirm-title="Dispose" data-confirm-text="Mark this record as disposed?">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-secondary" title="Dispose"><i class="fa-solid fa-trash-can"></i></button>
                    </form>
                    <form method="POST" action="{{ route('admin.damages.destroy', $damage->DamageID) }}" style="display:inline;" id="deleteForm{{ $damage->DamageID }}">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete({{ $damage->DamageID }})">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                @elseif($damage->Status === 'for_supplier_return')
                    <form method="POST" action="{{ route('admin.damages.confirm-supplier-return', $damage->DamageID) }}" class="js-confirm-submit" data-confirm-title="Confirm Returned" data-confirm-text="Confirm this item was returned to the supplier?">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-secondary" title="Confirm Returned"><i class="fa-solid fa-check"></i> Confirm Returned</button>
                    </form>
                    <form method="POST" action="{{ route('admin.damages.cancel', $damage->DamageID) }}" class="js-confirm-submit" data-confirm-title="Cancel Supplier Return" data-confirm-text="Cancel this supplier return and restore the quantity to inventory?" data-confirm-icon="warning" data-confirm-color="#f59e0b">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-secondary" title="Cancel"><i class="fa-solid fa-rotate-left"></i> Cancel</button>
                    </form>
                    <form method="POST" action="{{ route('admin.damages.dispose', $damage->DamageID) }}" class="js-confirm-submit" data-confirm-title="Dispose" data-confirm-text="Mark this record as disposed?">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-secondary" title="Dispose"><i class="fa-solid fa-trash-can"></i></button>
                    </form>
                @elseif($damage->Status === 'returned_to_supplier')
                    <form method="POST" action="{{ route('admin.damages.receive-replacement', $damage->DamageID) }}" class="js-confirm-submit" data-confirm-title="Receive Replacement" data-confirm-text="Confirm the supplier sent a replacement and increase inventory?">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-secondary" title="Receive Replacement"><i class="fa-solid fa-box"></i> Receive Replacement</button>
                    </form>
                @else
                    <span class="text-muted">-</span>
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="9">
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-box-open"></i></div>
                <p class="empty-title">No Damage Records Found</p>
                <p class="empty-text">Record your first damaged product to get started.</p>
            </div>
        </td>
    </tr>
@endforelse
