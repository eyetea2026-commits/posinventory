{{-- Type-specific report content, shared by the index page's live view and
     the reactive fetch (admin.reports.preview) triggered on every Report
     Type / date change, so both always show exactly the same data. --}}
@if($reportType === 'inventory')
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Inventory Items</h2>
                <p class="card-subtitle">Products with stock activity within the selected date range</p>
            </div>
        </div>
        <div class="table-container" style="max-height: 480px; overflow-y: auto;">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Product</th><th>Quantity</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($inventoryRows as $row)
                        <tr>
                            <td>{{ $row->InventoryID }}</td>
                            <td>{{ $row->product?->ProductName ?? 'N/A' }}</td>
                            <td>{{ number_format($row->Quantity) }}</td>
                            <td>{{ $row->Status }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="viewReportDetails('inventory', {{ $row->ProductID }})">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No reports or records found for the selected date range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Recent Stock Adjustments</h2>
                <p class="card-subtitle">Increases, decreases, and their reasons — including damages, losses, theft, and count corrections</p>
            </div>
        </div>
        <div class="table-container" style="max-height: 360px; overflow-y: auto;">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Date</th><th>Product</th><th>Adjustment</th><th>Reason</th></tr>
                </thead>
                <tbody>
                    @forelse($stockAdjustmentRows as $row)
                        <tr>
                            <td>{{ $row->AdjustmentID }}</td>
                            <td>{{ $row->Date }}</td>
                            <td>{{ $row->product?->ProductName ?? 'N/A' }}</td>
                            <td>{{ $row->QuantityAdjust >= 0 ? '+' : '' }}{{ $row->QuantityAdjust }}</td>
                            <td>{{ $row->Reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No reports or records found for the selected date range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@elseif($reportType === 'sales')
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Sales</h2>
                <p class="card-subtitle">Sales recorded within the selected date range</p>
            </div>
        </div>
        <div class="table-container" style="max-height: 480px; overflow-y: auto;">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Date</th><th>Amount</th><th>Customer</th><th>Payment Method</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($salesRows as $row)
                        <tr>
                            <td>{{ $row->BillingID }}</td>
                            <td>{{ $row->BillingDate }}</td>
                            <td class="text-success">₱{{ number_format($row->BillingAmount, 2) }}</td>
                            <td>{{ $row->CustomerName ?? 'Walk-in' }}</td>
                            <td>{{ $row->payment?->PaymentMethod ?? 'N/A' }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="viewReportDetails('sales', {{ $row->BillingID }})">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No reports or records found for the selected date range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@elseif($reportType === 'orders')
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Purchase Orders</h2>
                <p class="card-subtitle">Orders placed within the selected date range</p>
            </div>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr><th>PO Number</th><th>Date</th><th>Status</th><th>Supplier</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($orderRows as $row)
                        <tr>
                            <td><code>{{ $row->PONumber }}</code></td>
                            <td>{{ $row->PurchaseDate }}</td>
                            <td><span class="badge badge-info">{{ \App\Models\PurchaseOrder::STATUS_LABELS[$row->Status] ?? $row->Status }}</span></td>
                            <td>{{ $row->supplier?->SupplierName ?? 'N/A' }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="viewReportDetails('orders', {{ $row->PurchaseOrderID }})">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No reports or records found for the selected date range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@elseif($reportType === 'returns')
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Returns</h2>
                <p class="card-subtitle">Returns recorded within the selected date range</p>
            </div>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Product</th><th>Qty</th><th>Reason</th><th>Cashier</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($returnRows as $row)
                        <tr>
                            <td>{{ $row->SalesReturnID }}</td>
                            <td>{{ $row->product?->ProductName ?? 'N/A' }}</td>
                            <td>{{ $row->Quantity }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $row->Reason)) }}</td>
                            <td>{{ $row->CashierName }}</td>
                            <td><span class="badge badge-info">{{ ucfirst($row->Status) }}</span></td>
                            <td>{{ $row->ReturnDate }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="viewReportDetails('returns', {{ $row->SalesReturnID }})">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">No reports or records found for the selected date range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@elseif($reportType === 'damage')
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Damage Records</h2>
                <p class="card-subtitle">Damaged/lost/defective stock recorded within the selected date range</p>
            </div>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Date</th><th>Product</th><th>Supplier</th><th>Qty</th><th>Damage Type</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($damageRows as $row)
                        <tr>
                            <td>{{ $row->DamageID }}</td>
                            <td>{{ optional($row->DateRecorded)->format('Y-m-d') }}</td>
                            <td>{{ $row->product?->ProductName ?? 'N/A' }}</td>
                            <td>{{ $row->supplier?->SupplierName ?? 'N/A' }}</td>
                            <td>{{ $row->Quantity }}</td>
                            <td>{{ \App\Models\DamagedProduct::DAMAGE_TYPES[$row->DamageType] ?? $row->DamageType }}</td>
                            <td><span class="badge badge-info">{{ \App\Models\DamagedProduct::STATUS_LABELS[$row->Status] ?? $row->Status }}</span></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="viewReportDetails('damage', {{ $row->DamageID }})">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">No reports or records found for the selected date range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@elseif($reportType === 'supplier')
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Suppliers</h2>
                <p class="card-subtitle">Orders and spend per supplier within the selected date range</p>
            </div>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr><th>Supplier</th><th>Status</th><th>Total Orders</th><th>Total Amount</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($supplierRows as $row)
                        <tr>
                            <td><a href="{{ route('admin.suppliers.show', $row->SupplierID) }}">{{ $row->SupplierName }}</a></td>
                            <td><span class="badge {{ $row->Status === 'inactive' ? 'badge-secondary' : 'badge-success' }}">{{ ucfirst($row->Status ?? 'active') }}</span></td>
                            <td>{{ $row->TotalOrders }}</td>
                            <td>₱{{ number_format($row->TotalAmount, 2) }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="viewReportDetails('supplier', {{ $row->SupplierID }})">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No reports or records found for the selected date range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
