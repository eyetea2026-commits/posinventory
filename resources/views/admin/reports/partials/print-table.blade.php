{{-- Per-report-type table body, shared by the dompdf export (admin/reports/pdf.blade.php)
     and the in-browser Print Preview (admin/reports/print.blade.php) so the two surfaces
     never drift out of sync with each other. Expects: $type, $rows. --}}
@if($type === 'inventory')
    <table>
        <thead><tr><th>ID</th><th>Product</th><th>Quantity</th><th>Status</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->InventoryID }}</td>
                    <td>{{ $row->product?->ProductName ?? 'N/A' }}</td>
                    <td>{{ $row->Quantity }}</td>
                    <td>{{ $row->Status }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No inventory records found.</td></tr>
            @endforelse
        </tbody>
    </table>
@elseif($type === 'orders')
    <table>
        <thead><tr><th>ID</th><th>Date</th><th>Status</th><th>Supplier</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->PurchaseOrderID }}</td>
                    <td>{{ $row->PurchaseDate }}</td>
                    <td>{{ $row->Status }}</td>
                    <td>{{ $row->supplier?->SupplierName ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No purchase orders found.</td></tr>
            @endforelse
        </tbody>
    </table>
@elseif($type === 'returns')
    <table>
        <thead><tr><th>ID</th><th>Transaction ID</th><th>Product</th><th>Qty</th><th>Reason</th><th>Cashier</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->SalesReturnID }}</td>
                    <td>{{ $row->SalesTransactionID }}</td>
                    <td>{{ $row->product?->ProductName ?? 'N/A' }}</td>
                    <td>{{ $row->Quantity }}</td>
                    <td>{{ $row->Reason }}</td>
                    <td>{{ $row->CashierName }}</td>
                    <td>{{ $row->Status }}</td>
                    <td>{{ $row->ReturnDate }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No returns found.</td></tr>
            @endforelse
        </tbody>
    </table>
@elseif($type === 'damage')
    <table>
        <thead><tr><th>ID</th><th>Date</th><th>Product</th><th>Supplier</th><th>Qty</th><th>Damage Type</th><th>Status</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->DamageID }}</td>
                    <td>{{ optional($row->DateRecorded)->format('Y-m-d') }}</td>
                    <td>{{ $row->product?->ProductName ?? 'N/A' }}</td>
                    <td>{{ $row->supplier?->SupplierName ?? 'N/A' }}</td>
                    <td>{{ $row->Quantity }}</td>
                    <td>{{ \App\Models\DamagedProduct::DAMAGE_TYPES[$row->DamageType] ?? $row->DamageType }}</td>
                    <td>{{ $row->Status }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No damage records found.</td></tr>
            @endforelse
        </tbody>
    </table>
@elseif($type === 'supplier')
    <table>
        <thead><tr><th>ID</th><th>Supplier</th><th>Status</th><th>Total Orders</th><th>Total Amount</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->SupplierID }}</td>
                    <td>{{ $row->SupplierName }}</td>
                    <td>{{ $row->Status }}</td>
                    <td>{{ $row->TotalOrders }}</td>
                    <td>{{ number_format($row->TotalAmount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No suppliers found.</td></tr>
            @endforelse
        </tbody>
    </table>
    @php $grandTotal = $rows->sum('TotalAmount'); @endphp
    <p class="grand-total">Total Amount (all suppliers): {{ number_format($grandTotal, 2) }}</p>
@else
    <table>
        <thead><tr><th>ID</th><th>Date</th><th>Amount</th><th>Customer</th><th>Payment Method</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->BillingID }}</td>
                    <td>{{ $row->BillingDate }}</td>
                    <td>{{ number_format($row->BillingAmount, 2) }}</td>
                    <td>{{ $row->CustomerName ?? 'N/A' }}</td>
                    <td>{{ $row->payment?->PaymentMethod ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No sales records found.</td></tr>
            @endforelse
        </tbody>
    </table>
    @php $grandTotal = $rows->sum('BillingAmount'); @endphp
    <p class="grand-total">Total Amount: {{ number_format($grandTotal, 2) }}</p>
@endif
