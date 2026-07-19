@extends('admin.layout')

@push('styles')
    <link rel="stylesheet" href="{{ asset('Administrator/SalesReturns.css') }}">
@endpush

@section('header')
    <div class="header-title">
        <p style="margin: 0 0 4px; font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em;">Return / Approval</p>
        <h1>Approval</h1>
        <p>Review and approve return/refund/replacement requests from cashiers</p>
    </div>
@endsection

@section('content')
    <div class="status-tabs" style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px;">
        @php
            $tabs = [
                '' => ['label' => 'All', 'count' => array_sum($statusCounts)],
                'pending' => ['label' => 'Pending', 'count' => $statusCounts['pending']],
                'approved' => ['label' => 'Approved', 'count' => $statusCounts['approved']],
                'declined' => ['label' => 'Declined', 'count' => $statusCounts['declined']],
                'processed' => ['label' => 'Refunded', 'count' => $statusCounts['processed']],
            ];
        @endphp
        @foreach($tabs as $value => $tab)
            <a
                href="{{ route('admin.sales-returns.index', array_filter(['status' => $value, 'search' => $search, 'return_type' => $returnType])) }}"
                class="btn {{ ($status ?? '') === $value ? 'btn-primary' : 'btn-secondary' }}"
                style="text-decoration: none;"
            >
                {{ $tab['label'] }} <span style="opacity: 0.75;">({{ $tab['count'] }})</span>
            </a>
        @endforeach
    </div>

    <div class="card">
        <div class="toolbar">
            <form method="GET" action="{{ route('admin.sales-returns.index') }}" style="display: flex; gap: 12px; flex-wrap: wrap; flex: 1;">
                <div class="search-box">
                    <i class="search-icon fas fa-search"></i>
                    <input type="text" name="search" value="{{ $search }}" class="search-input" placeholder="Search returns..." />
                </div>
                <select name="status" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'declined' => 'Declined', 'processed' => 'Refunded'] as $value => $label)
                        <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="return_type" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="refund" {{ $returnType === 'refund' ? 'selected' : '' }}>Refund</option>
                    <option value="replacement" {{ $returnType === 'replacement' ? 'selected' : '' }}>Replacement</option>
                </select>
                <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
            </form>
        </div>

        @if(session('status'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                {{ session('status') }}
            </div>
        @endif

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Date Requested</th>
                        <th>Cashier Name</th>
                        <th>Receipt Number</th>
                        <th>Customer Name</th>
                        <th>Return Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returns as $return)
                        <tr>
                            <td>#{{ $return->SalesReturnID }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($return->ReturnDate)->format('M d, Y') }}</td>
                            <td>{{ $return->staff?->user?->name ?? 'N/A' }}</td>
                            <td>{{ $return->transaction ? 'RCT-' . str_pad($return->SalesTransactionID, 6, '0', STR_PAD_LEFT) : 'N/A' }}</td>
                            <td>{{ $return->CustomerName ?? $return->transaction?->CustomerName ?? 'N/A' }}</td>
                            <td>
                                <span class="badge {{ $return->ReturnType === 'replacement' ? 'badge-info' : 'badge-primary' }}">
                                    {{ ucfirst($return->ReturnType) }}
                                </span>
                            </td>
                            <td>
                                @if($return->Status === 'approved')
                                    <span class="badge badge-success">Approved</span>
                                @elseif($return->Status === 'declined')
                                    <span class="badge badge-danger">Declined</span>
                                @elseif($return->Status === 'processed')
                                    <span class="badge badge-secondary">{{ $return->ReturnType === 'replacement' ? 'Completed' : 'Refunded' }}</span>
                                @else
                                    <span class="badge badge-warning">Pending</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions-group">
                                    <button type="button" class="action-btn" title="View Details" onclick="viewReturnDetails({{ $return->SalesReturnID }})">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    @if($return->Status === 'pending')
                                        @php
                                            $approveConfirmMessage = $return->is_within_return_window === false
                                                ? "This request was made {$return->days_since_purchase} day(s) after purchase, outside the ".\App\Models\SalesReturn::RETURN_WINDOW_DAYS."-day return policy window. Approve anyway?"
                                                : 'Approve this return request?';
                                        @endphp
                                        <form method="POST" action="{{ route('admin.sales-returns.approve', $return) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from($approveConfirmMessage) }});">
                                            @csrf
                                            <button type="submit" class="action-btn" style="background: var(--success-light); color: var(--success);" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="action-btn delete" title="Decline" onclick="declineReturn({{ $return->SalesReturnID }})">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-undo-alt"></i></div>
                                    <p class="empty-title">No Return Requests</p>
                                    <p class="empty-text">Return requests will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($returns->hasPages())
            <div class="pagination">
                @if($returns->onFirstPage())
                    <span class="pagination-link disabled"><i class="fas fa-chevron-left"></i></span>
                @else
                    <a href="{{ $returns->previousPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-left"></i></a>
                @endif

                @foreach($returns->getUrlRange(1, $returns->lastPage()) as $page => $url)
                    <a href="{{ $url }}" class="pagination-link {{ $page == $returns->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                @endforeach

                @if($returns->hasMorePages())
                    <a href="{{ $returns->nextPageUrl() }}" class="pagination-link"><i class="fas fa-chevron-right"></i></a>
                @else
                    <span class="pagination-link disabled"><i class="fas fa-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>

    <!-- View Details Modal -->
    <div class="modal-overlay" id="detailsModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Return Request Details</h3>
                <button type="button" class="modal-close" onclick="closeDetailsModal()"><i class="fas fa-times"></i></button>
            </div>
            <div id="detailsBody">
                <p class="text-muted">Loading...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Decline Reason Modal -->
    <div class="modal-overlay" id="declineModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Decline Return Request</h3>
                <button type="button" class="modal-close" onclick="closeDeclineModal()"><i class="fas fa-times"></i></button>
            </div>
            <form id="declineForm" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">Decline Reason <span style="color: var(--danger);">*</span></label>
                    <textarea name="DeclineReason" class="form-textarea" required maxlength="255" placeholder="Explain why this request is being declined..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeclineModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Decline Request</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function statusLabel(status, returnType) {
    if (status === 'processed') {
        return returnType === 'replacement' ? 'Completed' : 'Refunded';
    }
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function viewReturnDetails(id) {
    const modal = document.getElementById('detailsModal');
    const body = document.getElementById('detailsBody');
    body.innerHTML = '<p class="text-muted">Loading...</p>';
    modal.classList.add('active');

    fetch(`/admin/sales-returns/${id}`, { headers: { 'Accept': 'application/json' } })
        .then(res => res.json())
        .then(data => {
            const t = data.transaction, p = data.product, r = data.return;
            // Customer name, reason text, and decline reason all originate as
            // free text from the cashier/admin request forms — escape every
            // server-supplied string before it goes into innerHTML so a
            // return submitted with an HTML/script payload in its Reason or
            // Customer Name can't execute in this admin session.
            body.innerHTML = `
                <h4>Transaction Information</h4>
                <p><strong>Receipt Number:</strong> ${escapeHtml(t.ReceiptNumber ?? 'N/A')}</p>
                <p><strong>Invoice Number:</strong> ${escapeHtml(t.InvoiceNumber ?? 'N/A')}</p>
                <p><strong>Transaction Date:</strong> ${escapeHtml(t.TransactionDate ?? 'N/A')}</p>
                <p><strong>Customer:</strong> ${escapeHtml(t.CustomerName ?? 'N/A')}</p>
                <p><strong>Cashier:</strong> ${escapeHtml(t.OriginalCashier ?? 'N/A')}</p>
                <hr style="border-color: var(--border); margin: 16px 0;">
                <h4>Product Information</h4>
                <p><strong>Product Name:</strong> ${escapeHtml(p.ProductName ?? 'N/A')}</p>
                <p><strong>Barcode/SKU:</strong> ${escapeHtml(p.Barcode ?? 'N/A')} / ${escapeHtml(p.SKU ?? 'N/A')}</p>
                <p><strong>Category:</strong> ${escapeHtml(p.Category ?? 'N/A')}</p>
                <p><strong>Quantity Purchased:</strong> ${escapeHtml(p.QuantityPurchased ?? 'N/A')}</p>
                <p><strong>Original Selling Price:</strong> ${window.formatPeso(p.SellingPrice ?? 0)}</p>
                <hr style="border-color: var(--border); margin: 16px 0;">
                <h4>Return Information</h4>
                <p><strong>Return Type:</strong> ${escapeHtml(r.ReturnType)}</p>
                <p><strong>Quantity Requested for Return:</strong> ${escapeHtml(r.Quantity)}</p>
                <p><strong>Total Refund Amount:</strong> ${window.formatPeso(r.TotalRefundAmount ?? 0)}</p>
                <p><strong>Reason for Return:</strong> ${escapeHtml(r.Reason)}</p>
                ${r.Remarks ? `<p><strong>Supporting Remarks:</strong> ${escapeHtml(r.Remarks)}</p>` : ''}
                <p><strong>Request Date:</strong> ${escapeHtml(r.ReturnDate)}</p>
                <p><strong>Current Status:</strong> ${escapeHtml(statusLabel(r.Status, r.ReturnType))}</p>
                <p><strong>Return Policy:</strong> ${r.DaysSincePurchase !== null
                    ? `${escapeHtml(r.DaysSincePurchase)} day(s) since purchase — ` + (r.EligibleForReturn
                        ? `<span style="color: var(--success);">within the ${escapeHtml(r.ReturnWindowDays)}-day window</span>`
                        : `<span style="color: var(--danger);">outside the ${escapeHtml(r.ReturnWindowDays)}-day window</span>`)
                    : 'N/A'}</p>
                ${r.DeclineReason ? `<p><strong>Decline Reason:</strong> ${escapeHtml(r.DeclineReason)}</p>` : ''}
                ${r.ApprovedBy ? `<p><strong>Approved/Declined By:</strong> ${escapeHtml(r.ApprovedBy)}</p>` : ''}
                ${r.ProcessedBy ? `<p><strong>Processed By:</strong> ${escapeHtml(r.ProcessedBy)}</p>` : ''}
                ${r.Replacement ? `<p><strong>Replacement:</strong> ${escapeHtml(r.Replacement.Quantity)} x ${escapeHtml(r.Replacement.ProductName)} (Slip ${escapeHtml(r.Replacement.SlipNumber)})</p>` : ''}
            `;
        })
        .catch(() => {
            body.innerHTML = '<p class="text-muted">Failed to load details.</p>';
        });
}

function closeDetailsModal() {
    document.getElementById('detailsModal').classList.remove('active');
}

function declineReturn(id) {
    document.getElementById('declineForm').action = `/admin/sales-returns/${id}/decline`;
    document.getElementById('declineModal').classList.add('active');
}

function closeDeclineModal() {
    document.getElementById('declineModal').classList.remove('active');
}
</script>
@endpush
