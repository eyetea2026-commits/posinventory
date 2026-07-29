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
            {{-- Status filtering already lives in the tabs above (All/Pending/
                 Approved/Declined/Refunded) — the "All Statuses" dropdown was
                 a redundant second control for the same thing, so it's gone.
                 The status tabs' links carry $search/$returnType forward via
                 array_filter() above, so removing this control doesn't lose
                 any filter state. --}}
            <form method="GET" action="{{ route('admin.sales-returns.index') }}" style="display: flex; gap: 12px; flex-wrap: wrap; flex: 1;">
                @if($status)
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <div class="search-box">
                    <i class="search-icon fas fa-search"></i>
                    <input type="text" name="search" value="{{ $search }}" class="search-input" placeholder="Search returns..." />
                </div>
                <select name="return_type" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="refund" {{ $returnType === 'refund' ? 'selected' : '' }}>Refund</option>
                    <option value="replacement" {{ $returnType === 'replacement' ? 'selected' : '' }}>Replacement</option>
                </select>
                {{-- No visible Filter button: Return Type already auto-submits on
                     change, and the search box still submits on Enter via this
                     visually hidden submit control (removing it entirely would
                     also disable the browser's native Enter-to-submit behavior). --}}
                <button type="submit" aria-hidden="true" tabindex="-1" style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0;">Search</button>
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
                            <td>{{ $return->staff?->user?->full_name ?? 'N/A' }}</td>
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
                                        @if($return->is_within_return_window === false)
                                            <button type="button" class="action-btn" style="background: var(--bg-hover); color: var(--text-secondary); cursor: not-allowed;" disabled title="Outside the {{ \App\Models\SalesReturn::RETURN_WINDOW_DAYS }}-day return window — {{ $return->days_since_purchase }} day(s) since purchase. Cannot be approved.">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        @else
                                            <form method="POST" action="{{ route('admin.sales-returns.approve', $return) }}" onsubmit="return confirm('Approve this return request?');">
                                                @csrf
                                                <button type="submit" class="action-btn" style="background: var(--success-light); color: var(--success);" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        @endif
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
            const t = data.transaction, items = data.items || [], r = data.return, rb = data.requestedBy;
            // Customer name, reason text, and decline reason all originate as
            // free text from the cashier/admin request forms — escape every
            // server-supplied string before it goes into innerHTML so a
            // return submitted with an HTML/script payload in its Reason or
            // Customer Name can't execute in this admin session.
            const itemsHtml = items.map(item => `
                <div style="display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid var(--border);">
                    <div>
                        <strong>${escapeHtml(item.ProductName ?? 'N/A')}</strong> x${escapeHtml(item.Quantity)}<br>
                        <small style="color: var(--text-secondary);">Barcode/SKU: ${escapeHtml(item.Barcode ?? 'N/A')} / ${escapeHtml(item.SKU ?? 'N/A')} | Category: ${escapeHtml(item.Category ?? 'N/A')}</small><br>
                        <small style="color: var(--text-secondary);">Reason: ${escapeHtml(item.Reason ?? 'N/A')} | Unit Price: ${window.formatPeso(item.UnitPrice ?? 0)}</small>
                    </div>
                    <div style="white-space:nowrap; font-weight:600;">${window.formatPeso(item.LineTotal ?? 0)}</div>
                </div>
            `).join('');

            body.innerHTML = `
                ${rb ? `
                <h4>Requested By</h4>
                <p><strong>Cashier Name:</strong> ${escapeHtml(rb.Name)}</p>
                <p><strong>Employee ID:</strong> ${escapeHtml(rb.EmployeeID)}</p>
                <p><strong>Role:</strong> ${escapeHtml(rb.Role)}</p>
                <p><strong>Request Date:</strong> ${escapeHtml(rb.RequestDate ?? 'N/A')}</p>
                <hr style="border-color: var(--border); margin: 16px 0;">
                ` : ''}
                <h4>Transaction Information</h4>
                <p><strong>Receipt Number:</strong> ${escapeHtml(t.ReceiptNumber ?? 'N/A')}</p>
                <p><strong>Invoice Number:</strong> ${escapeHtml(t.InvoiceNumber ?? 'N/A')}</p>
                <p><strong>Transaction Date:</strong> ${escapeHtml(t.TransactionDate ?? 'N/A')}</p>
                <p><strong>Customer:</strong> ${escapeHtml(t.CustomerName ?? 'N/A')}</p>
                <p><strong>Cashier:</strong> ${escapeHtml(t.OriginalCashier ?? 'N/A')}</p>
                <hr style="border-color: var(--border); margin: 16px 0;">
                <h4>Returned Product(s)</h4>
                ${itemsHtml || '<p class="text-muted">No items found.</p>'}
                <hr style="border-color: var(--border); margin: 16px 0;">
                <h4>Return Information</h4>
                <p><strong>Return Type:</strong> ${escapeHtml(r.ReturnType)}</p>
                <p><strong>Total Refund Amount:</strong> ${window.formatPeso(r.TotalRefundAmount ?? 0)}</p>
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
