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
@elseif($reportType === 'stock_adjustment')
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Stock Adjustments</h2>
                <p class="card-subtitle">Increases, decreases, and their reasons — including damages, losses, theft, and count corrections</p>
            </div>
        </div>
        <div class="table-container" style="max-height: 480px; overflow-y: auto;">
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
@elseif($reportType === 'reorder')
    <div class="card mt-4">
        <div class="card-header">
            <div>
                <h2 class="card-title">Products Needing Reorder</h2>
                <p class="card-subtitle">Live snapshot of products at or below their reorder threshold</p>
            </div>
        </div>
        <div class="table-container" style="max-height: 480px; overflow-y: auto;">
            <table class="table">
                <thead>
                    <tr><th>Product</th><th>Category</th><th>Current Stock</th><th>Reorder Threshold</th><th>Suggested Qty</th><th>Preferred Supplier</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($reorderRows as $row)
                        @php($supplierName = $row->product?->resolveReorderSupplier()?->supplier?->SupplierName)
                        <tr>
                            <td>{{ $row->product?->ProductName ?? 'N/A' }}</td>
                            <td>{{ $row->product?->category?->CategoryName ?? 'Uncategorized' }}</td>
                            <td>{{ number_format($row->Quantity) }}</td>
                            <td>{{ number_format($row->ReorderThreshold ?? 0) }}</td>
                            <td>{{ number_format(\App\Http\Controllers\Admin\PurchaseOrderController::suggestedReorderQuantity((int) $row->Quantity, (int) ($row->ReorderThreshold ?? 50))) }}</td>
                            <td>{{ $supplierName ?? 'N/A' }}</td>
                            <td>
                                @if($row->product)
                                    <a href="{{ route('admin.purchase-orders.create-from-reorder', $row->product->ProductID) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-cart-plus"></i> Create PO
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No products are currently at or below their reorder threshold.</td></tr>
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
                            <td>{{ $row->payment?->PaymentMethod ? ucfirst($row->payment->PaymentMethod) : 'N/A' }}</td>
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
@endif
