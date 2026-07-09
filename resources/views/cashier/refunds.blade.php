@extends('cashier.layout')

@section('title', 'Refund Requests - CCTV Express')

@section('content')
<style>
    .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .content-header h1 { margin: 0; }
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
    .stat-card { background: #1a1d2d; border-radius: 12px; padding: 16px; text-align: center; }
    .stat-card .number { font-size: 1.8rem; font-weight: bold; color: #60a5fa; }
    .stat-card .label { color: #94a3b8; font-size: 0.85rem; margin-top: 4px; }
    .stat-card.pending .number { color: #f59e0b; }
    .stat-card.approved .number { color: #10b981; }
    .stat-card.rejected .number { color: #ef4444; }
    .card { background: #1a1d2d; border-radius: 12px; padding: 20px; }
    .card-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
    .search-form { display: flex; gap: 10px; }
    .search-form input { padding: 10px; background: #2d3748; border: 1px solid #4a5568; color: #e2e8f0; border-radius: 6px; }
    .search-form button { padding: 10px 20px; background: #3b82f6; border: none; color: white; border-radius: 6px; cursor: pointer; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #2d3748; }
    .table th { color: #94a3b8; font-weight: 500; }
    .table tbody tr:hover { background: #2d3748; }
    .status { padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-approved { background: #dcfce7; color: #166534; }
    .status-rejected { background: #fee2e2; color: #991b1b; }
    .btn-create { padding: 10px 20px; background: #3b82f6; border: none; color: white; border-radius: 6px; cursor: pointer; }
    .btn-view { padding: 6px 12px; background: #3b82f6; border: none; color: white; border-radius: 4px; cursor: pointer; text-decoration: none; }
    .btn-process { padding: 6px 12px; background: #10b981; border: none; color: white; border-radius: 4px; cursor: pointer; }
    .btn-process:disabled { background: #4a5568; cursor: not-allowed; }

    /* Modal Styles */
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; }
    .modal.active { display: flex; align-items: center; justify-content: center; }
    .modal-content { background: #1a1d2d; border-radius: 16px; padding: 24px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .modal-header h2 { margin: 0; }
    .modal-close { background: none; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; }
    .modal-close:hover { color: #fff; }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; margin-bottom: 6px; color: #94a3b8; font-size: 0.9rem; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; background: #2d3748; border: 1px solid #4a5568; color: #e2e8f0; border-radius: 8px; font-size: 0.95rem; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #3b82f6; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .btn-submit { width: 100%; padding: 14px; background: #3b82f6; border: none; color: white; border-radius: 8px; cursor: pointer; font-size: 1rem; }
    .btn-submit:hover { background: #2563eb; }
    .transaction-items { max-height: 200px; overflow-y: auto; margin-bottom: 16px; }
    .transaction-item { display: flex; justify-content: space-between; padding: 10px; background: #2d3748; border-radius: 8px; margin-bottom: 8px; }
    .refund-product-row { display: flex; align-items: center; gap: 10px; padding: 10px; background: #2d3748; border-radius: 8px; margin-bottom: 8px; cursor: pointer; }
    .refund-product-row:hover { background: #3d4758; }
    .refund-product-row.selected { background: rgba(59, 130, 246, 0.3); border: 1px solid #3b82f6; }
    .refund-details { background: #2d3748; border-radius: 8px; padding: 16px; margin-top: 16px; }
    .refund-details-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
    .refund-details-row.total { font-size: 1.2rem; font-weight: bold; color: #10b981; }
    .payment-methods { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
    .payment-method { padding: 12px; background: #2d3748; border: 2px solid #4a5568; border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.2s; }
    .payment-method:hover { border-color: #60a5fa; }
    .payment-method.selected { border-color: #3b82f6; background: rgba(59, 130, 246, 0.15); }
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .form-row { grid-template-columns: 1fr; }
        .payment-methods { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<!-- Stats Section (REQ091) -->
<div class="stats-grid" id="stats-grid">
    <div class="stat-card">
        <div class="number" id="total-refunds">0</div>
        <div class="label">Total Refunds</div>
    </div>
    <div class="stat-card pending">
        <div class="number" id="pending-refunds">0</div>
        <div class="label">Pending</div>
    </div>
    <div class="stat-card approved">
        <div class="number" id="approved-refunds">0</div>
        <div class="label">Approved</div>
    </div>
    <div class="stat-card rejected">
        <div class="number" id="rejected-refunds">0</div>
        <div class="label">Rejected</div>
    </div>
</div>

<div class="content-header">
    <h1>Refund Requests</h1>
    <button class="btn-create" onclick="showCreateRefundModal()">
        <i class="fas fa-plus"></i> New Refund Request
    </button>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="{{ route('cashier.refunds') }}" class="search-form">
            <input type="text" name="search" placeholder="Search refunds..." value="{{ $search ?? '' }}">
            <button type="submit"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Refund ID</th>
                <th>Date</th>
                <th>Transaction ID</th>
                <th>Customer Name</th>
                <th>Quantity</th>
                <th>Amount</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($refunds as $refund)
                <tr>
                    <td>#{{ str_pad($refund->SalesReturnID, 6, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ \Carbon\Carbon::parse($refund->ReturnDate)->format('M d, Y') }}</td>
                    <td>#{{ str_pad($refund->SalesTransactionID, 6, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $refund->CustomerName ?? 'N/A' }}</td>
                    <td>{{ $refund->Quantity }}</td>
                    <td>₱{{ number_format($refund->RefundAmount ?? 0, 2) }}</td>
                    <td>{{ Str::limit($refund->Reason, 30) }}</td>
                    <td>
                        <span class="status status-{{ strtolower($refund->Status) }}">
                            {{ ucfirst($refund->Status) }}
                        </span>
                    </td>
                    <td>
                        <button class="btn-view" onclick="viewRefundDetails({{ $refund->SalesReturnID }})">
                            <i class="fas fa-eye"></i>
                        </button>
                        @if($refund->Status === 'approved')
                        <button class="btn-process" onclick="showProcessRefundModal({{ $refund->SalesReturnID }})">
                            <i class="fas fa-check"></i> Process
                        </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center">No refund requests found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Create Refund Modal (REQ103-105) -->
<div class="modal" id="create-refund-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-undo-alt"></i> New Refund Request</h2>
            <button class="modal-close" onclick="closeCreateRefundModal()">&times;</button>
        </div>
        <form id="create-refund-form">
            @csrf
            <div class="form-group">
                <label>Transaction ID (REQ104)</label>
                <input type="number" id="transaction-id" placeholder="Enter transaction ID" required onchange="loadTransactionDetails()">
            </div>

            <div id="transaction-details-section" style="display: none;">
                <div class="form-group">
                    <label>Transaction Info</label>
                    <div id="transaction-info" style="background: #2d3748; padding: 12px; border-radius: 8px; color: #94a3b8;"></div>
                </div>

                <div class="form-group">
                    <label>Select Product to Refund (REQ104)</label>
                    <div class="transaction-items" id="transaction-products"></div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Quantity (REQ104)</label>
                        <input type="number" id="refund-quantity" min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label>Calculated Refund Amount</label>
                        <div id="refund-amount-display" style="background: #2d3748; padding: 12px; border-radius: 8px; color: #10b981; font-weight: bold; font-size: 1.1rem;"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Reason of Return (REQ104)</label>
                    <textarea id="refund-reason" rows="3" placeholder="Enter reason for return" required></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Submit Refund Request
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Refund Details Modal (REQ106) -->
<div class="modal" id="view-refund-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-info-circle"></i> Refund Details</h2>
            <button class="modal-close" onclick="closeViewRefundModal()">&times;</button>
        </div>
        <div id="refund-details-content"></div>
    </div>
</div>

<!-- Process Refund Modal (REQ107-109) -->
<div class="modal" id="process-refund-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-check-circle"></i> Process Refund</h2>
            <button class="modal-close" onclick="closeProcessRefundModal()">&times;</button>
        </div>
        <form id="process-refund-form">
            @csrf
            <input type="hidden" id="process-refund-id">

            <div class="refund-details">
                <div class="refund-details-row">
                    <span>Refund Amount:</span>
                    <span id="process-amount" style="font-weight: bold; color: #10b981;"></span>
                </div>
            </div>

            <div class="form-group">
                <label>Select Refund Payment Method (REQ108)</label>
                <div class="payment-methods" id="refund-payment-methods">
                    <div class="payment-method selected" onclick="selectRefundPayment(this, 'cash')">
                        <i class="fas fa-money-bill-wave"></i><br>Cash
                    </div>
                    <div class="payment-method" onclick="selectRefundPayment(this, 'gcash')">
                        <i class="fas fa-mobile-alt"></i><br>GCash
                    </div>
                    <div class="payment-method" onclick="selectRefundPayment(this, 'bank')">
                        <i class="fas fa-university"></i><br>Bank
                    </div>
                    <div class="payment-method" onclick="selectRefundPayment(this, 'cheque')">
                        <i class="fas fa-money-check"></i><br>Cheque
                    </div>
                </div>
            </div>

            <div class="form-group" id="refund-account-section" style="display: none;">
                <label>Account Number (REQ100)</label>
                <input type="text" id="refund-account-number" placeholder="Enter account number">
            </div>

            <div class="form-group">
                <label>Refund Payment Amount (REQ109)</label>
                <input type="number" id="refund-payment-amount" step="0.01" min="0" required>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-check"></i> Process Refund
            </button>
        </form>
    </div>
</div>

<script>
let selectedProduct = null;
let selectedRefundPaymentMethod = 'cash';
let currentRefundAmount = 0;

// Load stats on page load (REQ091)
document.addEventListener('DOMContentLoaded', function() {
    loadRefundStats();
});

function loadRefundStats() {
    fetch('{{ route("cashier.stats") }}')
        .then(response => response.json())
        .then(data => {
            document.getElementById('total-refunds').textContent = data.total_refunds;
            document.getElementById('pending-refunds').textContent = data.pending_refunds;
            document.getElementById('approved-refunds').textContent = data.approved_refunds;
            document.getElementById('rejected-refunds').textContent = data.rejected_refunds;
        })
        .catch(err => console.error('Error loading stats:', err));
}

// Create Refund Modal Functions
function showCreateRefundModal() {
    document.getElementById('create-refund-modal').classList.add('active');
    document.getElementById('create-refund-form').reset();
    document.getElementById('transaction-details-section').style.display = 'none';
    selectedProduct = null;
}

function closeCreateRefundModal() {
    document.getElementById('create-refund-modal').classList.remove('active');
}

function loadTransactionDetails() {
    const transactionId = document.getElementById('transaction-id').value;
    if (!transactionId) return;

    fetch(`/cashier/refunds/${transactionId}/transaction`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const transaction = data.transaction;
                document.getElementById('transaction-info').innerHTML = `
                    <strong>Date:</strong> ${transaction.date}<br>
                    <strong>Customer:</strong> ${transaction.customer_name || 'N/A'}<br>
                    <strong>Total Amount:</strong> ₱${parseFloat(transaction.billing.amount).toFixed(2)}
                `;

                const productsContainer = document.getElementById('transaction-products');
                productsContainer.innerHTML = transaction.items.map(item => `
                    <div class="refund-product-row" onclick="selectRefundProduct(${item.ProductID}, '${item.ProductName}', ${item.UnitPrice}, ${item.Quantity})">
                        <div>
                            <strong>${item.ProductName}</strong><br>
                            <small>Unit Price: ₱${item.UnitPrice.toFixed(2)} | Qty: ${item.Quantity}</small>
                        </div>
                        <div style="text-align: right;">
                            <strong>₱${item.Total.toFixed(2)}</strong>
                        </div>
                    </div>
                `).join('');

                document.getElementById('transaction-details-section').style.display = 'block';
            } else {
                alert(data.message);
                document.getElementById('transaction-details-section').style.display = 'none';
            }
        })
        .catch(err => {
            alert('Error loading transaction');
            console.error(err);
        });
}

function selectRefundProduct(productId, productName, unitPrice, maxQty) {
    document.querySelectorAll('.refund-product-row').forEach(el => el.classList.remove('selected'));
    event.target.closest('.refund-product-row').classList.add('selected');

    selectedProduct = { productId, unitPrice, maxQty };
    updateRefundAmount();
}

function updateRefundAmount() {
    if (!selectedProduct) return;
    const qty = parseInt(document.getElementById('refund-quantity').value) || 1;
    const amount = selectedProduct.unitPrice * qty;
    currentRefundAmount = amount;
    document.getElementById('refund-amount-display').textContent = '₱' + amount.toFixed(2);
}

document.getElementById('refund-quantity').addEventListener('input', updateRefundAmount);

document.getElementById('create-refund-form').addEventListener('submit', function(e) {
    e.preventDefault();

    if (!selectedProduct) {
        alert('Please select a product to refund');
        return;
    }

    const qty = parseInt(document.getElementById('refund-quantity').value);
    if (qty > selectedProduct.maxQty) {
        alert('Quantity cannot exceed purchased amount');
        return;
    }

    fetch('{{ route("cashier.refunds.create") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            _token: '{{ csrf_token() }}',
            transaction_id: document.getElementById('transaction-id').value,
            product_id: selectedProduct.productId,
            quantity: qty,
            reason: document.getElementById('refund-reason').value,
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Refund request submitted successfully!');
            closeCreateRefundModal();
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(err => alert('Error submitting refund request'));
});

// View Refund Details (REQ106)
function viewRefundDetails(refundId) {
    fetch(`/cashier/refunds/${refundId}/details`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const r = data.refund;
                document.getElementById('refund-details-content').innerHTML = `
                    <div class="refund-details">
                        <div class="refund-details-row"><span>Refund ID:</span><span>#${String(r.id).padStart(6, '0')}</span></div>
                        <div class="refund-details-row"><span>Transaction ID:</span><span>#${String(r.transaction_id).padStart(6, '0')}</span></div>
                        <div class="refund-details-row"><span>Product:</span><span>${r.product_name}</span></div>
                        <div class="refund-details-row"><span>Quantity:</span><span>${r.quantity}</span></div>
                        <div class="refund-details-row"><span>Reason:</span><span>${r.reason}</span></div>
                        <div class="refund-details-row"><span>Return Date:</span><span>${r.return_date}</span></div>
                        <div class="refund-details-row"><span>Status:</span><span class="status status-${r.status.toLowerCase()}">${r.status}</span></div>
                        <div class="refund-details-row total"><span>Refund Amount:</span><span>₱${parseFloat(r.refund_amount).toFixed(2)}</span></div>
                        ${r.refund_method ? `<div class="refund-details-row"><span>Refund Method:</span><span>${r.refund_method}</span></div>` : ''}
                        ${r.refund_date ? `<div class="refund-details-row"><span>Refund Date:</span><span>${r.refund_date}</span></div>` : ''}
                    </div>
                `;
                document.getElementById('view-refund-modal').classList.add('active');
            } else {
                alert(data.message);
            }
        })
        .catch(err => alert('Error loading refund details'));
}

function closeViewRefundModal() {
    document.getElementById('view-refund-modal').classList.remove('active');
}

// Process Refund Functions (REQ107-109)
function showProcessRefundModal(refundId) {
    fetch(`/cashier/refunds/${refundId}/details`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('process-refund-id').value = refundId;
                currentRefundAmount = data.refund.refund_amount;
                document.getElementById('process-amount').textContent = '₱' + parseFloat(data.refund.refund_amount).toFixed(2);
                document.getElementById('refund-payment-amount').value = data.refund.refund_amount;
                document.getElementById('process-refund-modal').classList.add('active');
            } else {
                alert(data.message);
            }
        });
}

function closeProcessRefundModal() {
    document.getElementById('process-refund-modal').classList.remove('active');
}

function selectRefundPayment(element, method) {
    document.querySelectorAll('#refund-payment-methods .payment-method').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    selectedRefundPaymentMethod = method;

    const accountSection = document.getElementById('refund-account-section');
    if (method === 'cash') {
        accountSection.style.display = 'none';
    } else {
        accountSection.style.display = 'block';
    }
}

document.getElementById('process-refund-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const refundId = document.getElementById('process-refund-id').value;

    fetch(`/cashier/refunds/${refundId}/process`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            _token: '{{ csrf_token() }}',
            refund_method: selectedRefundPaymentMethod,
            refund_amount: document.getElementById('refund-payment-amount').value,
            account_number: document.getElementById('refund-account-number').value,
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Refund processed successfully!');
            closeProcessRefundModal();
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(err => alert('Error processing refund'));
});
</script>
@endsection