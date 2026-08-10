{{-- Per-report-type table body, shared by the dompdf export (admin/reports/pdf.blade.php)
     and the in-browser Print Preview (admin/reports/print.blade.php) so the two surfaces
     never drift out of sync with each other. Expects: $type, $rows.
     Alignment convention used throughout: text left, dates/quantities centered
     (.col-date/.col-qty), currency right (.col-money), row/line totals right + bold
     (.col-total) — matches the house alignment rules used elsewhere in the app. --}}
@if($type === 'inventory')
    <table>
        <thead>
            <tr>
                <th>Product</th><th>SKU / Barcode</th><th>Category</th><th>Supplier</th>
                <th class="col-qty">Current Stock</th><th class="col-qty">Reorder Level</th>
                <th class="col-money">Cost Price</th><th class="col-money">Selling Price</th>
                <th class="col-money">Stock Value</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                @php($supplierName = $row->product?->resolveReorderSupplier()?->supplier?->SupplierName)
                <tr>
                    <td>{{ $row->product?->ProductName ?? 'N/A' }}</td>
                    <td>{{ $row->product?->SKU ?: ($row->product?->Barcode ?: '—') }}</td>
                    <td>{{ $row->product?->category?->CategoryName ?? 'Uncategorized' }}</td>
                    <td>{{ $supplierName ?? 'N/A' }}</td>
                    <td class="col-qty">{{ number_format($row->Quantity) }}</td>
                    <td class="col-qty">{{ number_format($row->ReorderThreshold ?? 0) }}</td>
                    <td class="col-money">₱{{ number_format($row->product?->CostPrice ?? 0, 2) }}</td>
                    <td class="col-money">₱{{ number_format($row->product?->Price ?? 0, 2) }}</td>
                    <td class="col-money col-total">₱{{ number_format($row->Quantity * (float) ($row->product?->CostPrice ?? 0), 2) }}</td>
                    <td>{{ $row->Status }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="no-records"><strong>NO RECORDS FOUND</strong>No inventory records match the selected report criteria.</td></tr>
            @endforelse
        </tbody>
    </table>
@elseif($type === 'orders')
    <table>
        <thead>
            <tr>
                <th>PO Number</th><th class="col-date">Date</th><th>Supplier</th><th>Product</th>
                <th class="col-qty">Qty</th><th class="col-money">Unit Price</th><th class="col-money">Subtotal</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td><code>{{ $row->PONumber }}</code></td>
                    <td class="col-date">{{ \Illuminate\Support\Carbon::parse($row->PurchaseDate)->format('m/d/Y') }}</td>
                    <td>{{ $row->SupplierName }}</td>
                    <td>{{ $row->ProductName }}</td>
                    <td class="col-qty">{{ number_format($row->Quantity) }}</td>
                    <td class="col-money">₱{{ number_format($row->UnitPrice, 2) }}</td>
                    <td class="col-money col-total">₱{{ number_format($row->Subtotal, 2) }}</td>
                    <td>{{ \App\Models\PurchaseOrder::STATUS_LABELS[$row->Status] ?? ucfirst($row->Status) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="no-records"><strong>NO RECORDS FOUND</strong>No purchase orders match the selected report criteria.</td></tr>
            @endforelse
        </tbody>
    </table>
@elseif($type === 'returns')
    <table>
        <thead>
            <tr>
                <th>Return Ref.</th><th class="col-date">Date</th><th>Invoice</th><th>Customer</th><th>Product</th>
                <th class="col-qty">Qty</th><th>Reason</th><th>Type</th><th>Status</th><th>Processed By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td><code>RET-{{ str_pad($row->SalesReturnID, 6, '0', STR_PAD_LEFT) }}</code></td>
                    <td class="col-date">{{ \Illuminate\Support\Carbon::parse($row->ReturnDate)->format('m/d/Y') }}</td>
                    <td>{{ $row->ReceiptNumber }}</td>
                    <td>{{ $row->CustomerName }}</td>
                    <td>{{ $row->product?->ProductName ?? 'N/A' }}</td>
                    <td class="col-qty">{{ number_format($row->Quantity) }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $row->Reason)) }}</td>
                    <td>{{ ucfirst($row->ReturnType ?? 'refund') }}</td>
                    <td>{{ ucfirst($row->Status) }}</td>
                    <td>{{ $row->ProcessedByName }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="no-records"><strong>NO RECORDS FOUND</strong>No returns match the selected report criteria.</td></tr>
            @endforelse
        </tbody>
    </table>
@elseif($type === 'damage')
    <table>
        <thead>
            <tr>
                <th>Reference</th><th class="col-date">Date</th><th>Product</th><th>Supplier</th>
                <th class="col-qty">Qty</th><th>Damage Type</th><th>Reason</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td><code>DMG-{{ str_pad($row->DamageID, 6, '0', STR_PAD_LEFT) }}</code></td>
                    <td class="col-date">{{ optional($row->DateRecorded)->format('m/d/Y') }}</td>
                    <td>{{ $row->product?->ProductName ?? 'N/A' }}</td>
                    <td>{{ $row->supplier?->SupplierName ?? 'N/A' }}</td>
                    <td class="col-qty">{{ number_format($row->Quantity) }}</td>
                    <td>{{ \App\Models\DamagedProduct::DAMAGE_TYPES[$row->DamageType] ?? $row->DamageType }}</td>
                    <td>{{ $row->Description ?: '—' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $row->Status)) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="no-records"><strong>NO RECORDS FOUND</strong>No damage records match the selected report criteria.</td></tr>
            @endforelse
        </tbody>
    </table>
@elseif($type === 'supplier')
    <table>
        <thead>
            <tr>
                <th>Supplier Name</th><th>Contact Person</th><th>Contact Number</th><th>Email</th><th>Address</th>
                <th class="col-qty">Total POs</th><th class="col-money">Total Purchases</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row->SupplierName }}</td>
                    <td>{{ $row->ContactPerson ?: '—' }}</td>
                    <td>{{ $row->ContactNumber ?: '—' }}</td>
                    <td>{{ $row->Email ?: '—' }}</td>
                    <td>{{ $row->Address ?: '—' }}</td>
                    <td class="col-qty">{{ number_format($row->TotalOrders) }}</td>
                    <td class="col-money col-total">₱{{ number_format($row->TotalAmount, 2) }}</td>
                    <td>{{ ucfirst($row->Status) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="no-records"><strong>NO RECORDS FOUND</strong>No suppliers match the selected report criteria.</td></tr>
            @endforelse
        </tbody>
    </table>
@else
    <table>
        <thead>
            <tr>
                <th>Invoice No</th><th class="col-date">Date</th><th>Customer</th><th>Payment</th><th>Product</th>
                <th class="col-qty">Qty</th><th class="col-money">Unit Price</th><th class="col-money">Discount</th>
                <th class="col-money">VAT</th><th class="col-money">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td><code>{{ $row->ReceiptNumber }}</code></td>
                    <td class="col-date">{{ \Illuminate\Support\Carbon::parse($row->BillingDate)->format('m/d/Y') }}</td>
                    <td>{{ $row->CustomerName }}</td>
                    <td>{{ ucfirst($row->PaymentMethod ?? 'N/A') }}</td>
                    <td>{{ $row->ProductName }}</td>
                    <td class="col-qty">{{ number_format($row->Quantity) }}</td>
                    <td class="col-money">₱{{ number_format($row->UnitPrice, 2) }}</td>
                    <td class="col-money">{{ $row->Discount !== null ? '₱' . number_format($row->Discount, 2) : '—' }}</td>
                    <td class="col-money">{{ $row->VatAmount !== null ? '₱' . number_format($row->VatAmount, 2) : '—' }}</td>
                    <td class="col-money col-total">₱{{ number_format($row->ItemTotal, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="no-records"><strong>NO RECORDS FOUND</strong>No sales records match the selected report criteria.</td></tr>
            @endforelse
        </tbody>
    </table>
@endif

<div class="report-summary">
    <div class="report-summary-title">Report Summary</div>
    @foreach(\App\Services\ReportSummaryBuilder::forType($type, $rows) as $entry)
        <div class="summary-row">
            <span>{{ $entry['label'] }}</span>
            <span>{{ \App\Services\ReportSummaryBuilder::formatValue($entry) }}</span>
        </div>
    @endforeach
</div>
