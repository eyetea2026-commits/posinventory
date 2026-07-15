@extends('cashier.layout')

@section('title', 'Refund Requests - CCTV Express')

@section('content')
<style>
    .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
    .content-header h1 { margin: 0; }
    .stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 15px; margin-bottom: 20px; }
    .stat-card { background: #1a1d2d; border-radius: 12px; padding: 16px; text-align: center; }
    .stat-card .number { font-size: 1.8rem; font-weight: bold; color: #60a5fa; }
    .stat-card .label { color: #94a3b8; font-size: 0.85rem; margin-top: 4px; }
    .stat-card.pending .number { color: #f59e0b; }
    .stat-card.approved .number { color: #10b981; }
    .stat-card.declined .number { color: #ef4444; }
    .stat-card.processed .number { color: #93c5fd; }
    .stat-card.awaiting .number { color: #34d399; }
    .card { background: #1a1d2d; border-radius: 12px; padding: 20px; }
    .card-header { display: flex; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
    .search-form { display: flex; gap: 10px; flex-wrap: wrap; }
    .search-form input, .search-form select { padding: 10px; background: #2d3748; border: 1px solid #4a5568; color: #e2e8f0; border-radius: 6px; }
    .search-form button { padding: 10px 20px; background: #3b82f6; border: none; color: white; border-radius: 6px; cursor: pointer; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #2d3748; }
    .table th { color: #94a3b8; font-weight: 500; }
    .table tbody tr:hover { background: #2d3748; }
    .status { padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-approved { background: #dcfce7; color: #166534; }
    .status-declined { background: #fee2e2; color: #991b1b; }
    .status-processed { background: #dbeafe; color: #1e40af; }
    .type-badge { padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; background: #2d3748; color: #cbd5e1; }
    .btn-create { padding: 10px 20px; background: #3b82f6; border: none; color: white; border-radius: 6px; cursor: pointer; }
    .btn-view { padding: 6px 12px; background: #3b82f6; border: none; color: white; border-radius: 4px; cursor: pointer; text-decoration: none; }
    .btn-process { padding: 6px 12px; background: #10b981; border: none; color: white; border-radius: 4px; cursor: pointer; }
    .btn-process:disabled { background: #4a5568; cursor: not-allowed; }

    /* Modal Styles */
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; }
    .modal.active { display: flex; align-items: center; justify-content: center; }
    .modal-content { background: #1a1d2d; border-radius: 16px; padding: 24px; width: 90%; max-width: 640px; max-height: 90vh; overflow-y: auto; }
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
    .search-tabs { display: flex; gap: 8px; margin-bottom: 12px; }
    .search-tab { flex: 1; padding: 10px; background: #2d3748; border: 1px solid #4a5568; border-radius: 8px; text-align: center; cursor: pointer; color: #cbd5e1; }
    .search-tab.active { background: rgba(59, 130, 246, 0.2); border-color: #3b82f6; color: #fff; }
    .type-radio-group { display: flex; gap: 16px; }
    .type-radio-group label { display: flex; align-items: center; gap: 6px; color: #e2e8f0; }
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .form-row { grid-template-columns: 1fr; }
        .payment-methods { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<div class="stats-grid" id="stats-grid">
    <div class="stat-card">
        <div class="number" id="total-refunds">0</div>
        <div class="label">Total</div>
    </div>
    <div class="stat-card pending">
        <div class="number" id="pending-refunds">0</div>
        <div class="label">Pending</div>
    </div>
    <div class="stat-card approved">
        <div class="number" id="approved-refunds">0</div>
        <div class="label">Approved</div>
    </div>
    <div class="stat-card declined">
        <div class="number" id="declined-refunds">0</div>
        <div class="label">Declined</div>
    </div>
    <div class="stat-card processed">
        <div class="number" id="processed-refunds">0</div>
        <div class="label">Refunded/Completed</div>
    </div>
    <div class="stat-card awaiting">
        <div class="number" id="awaiting-action">0</div>
        <div class="label">Awaiting Your Action</div>
    </div>
</div>

<div class="content-header">
    <h1>Return / Refund Requests</h1>
    <button class="btn-create" onclick="showCreateRefundModal()">
        <i class="fas fa-plus"></i> New Return Request
    </button>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" action="{{ route('cashier.refunds') }}" class="search-form">
            <input type="text" name="search" placeholder="Search returns..." value="{{ $search ?? '' }}">
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="pending" {{ ($status ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="approved" {{ ($status ?? '') === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="declined" {{ ($status ?? '') === 'declined' ? 'selected' : '' }}>Declined</option>
                <option value="processed" {{ ($status ?? '') === 'processed' ? 'selected' : '' }}>Refunded/Completed</option>
            </select>
            <select name="return_type" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="refund" {{ ($returnType ?? '') === 'refund' ? 'selected' : '' }}>Refund</option>
                <option value="replacement" {{ ($returnType ?? '') === 'replacement' ? 'selected' : '' }}>Replacement</option>
            </select>
            <button type="submit"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Request ID</th>
                <th>Date</th>
                <th>Transaction</th>
                <th>Customer</th>
                <th>Type</th>
                <th>Quantity</th>
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
                    <td><span class="type-badge">{{ ucfirst($refund->ReturnType) }}</span></td>
                    <td>{{ $refund->Quantity }}</td>
                    <td>{{ Str::limit($refund->Reason, 30) }}</td>
                    <td>
                        <span class="status status-{{ strtolower($refund->Status) }}">
                            @if($refund->Status === 'processed')
                                {{ $refund->ReturnType === 'replacement' ? 'Completed' : 'Refunded' }}
                            @else
                                {{ ucfirst($refund->Status) }}
                            @endif
                        </span>
                    </td>
                    <td>
                        <button class="btn-view" onclick="viewRefundDetails({{ $refund->SalesReturnID }})">
                            <i class="fas fa-eye"></i>
                        </button>
                        @if($refund->Status === 'approved' && $refund->ReturnType === 'refund')
                        <button class="btn-process" onclick="showProcessRefundModal({{ $refund->SalesReturnID }})">
                            <i class="fas fa-check"></i> Process Refund
                        </button>
                        @elseif($refund->Status === 'approved' && $refund->ReturnType === 'replacement')
                        <button class="btn-process" onclick="showProcessReplacementModal({{ $refund->SalesReturnID }}, {{ $refund->Quantity }})">
                            <i class="fas fa-exchange-alt"></i> Process Replacement
                        </button>
                        @elseif($refund->Status === 'processed' && $refund->ReturnType === 'replacement')
                        <a class="btn-view" href="{{ route('cashier.refunds.slip', $refund) }}" target="_blank">
                            <i class="fas fa-print"></i> Slip
                        </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center">No return requests found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Create Return Request Modal -->
<div class="modal" id="create-refund-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-undo-alt"></i> New Return Request</h2>
            <button class="modal-close" onclick="closeCreateRefundModal()">&times;</button>
        </div>

        <div class="search-tabs">
            <div class="search-tab active" data-mode="receipt" onclick="setSearchMode('receipt')">Receipt #</div>
            <div class="search-tab" data-mode="invoice" onclick="setSearchMode('invoice')">Invoice #</div>
            <div class="search-tab" data-mode="customer" onclick="setSearchMode('customer')">Customer Name</div>
            <div class="search-tab" data-mode="barcode" onclick="setSearchMode('barcode')">Barcode</div>
        </div>
        <div class="form-group">
            <label id="search-label">Receipt Number</label>
            <div style="display:flex; gap:8px;">
                <input type="text" id="transaction-search-input" placeholder="e.g. RCT-000001" style="flex:1;">
                <button type="button" class="btn-submit" style="width:auto; padding:12px 20px;" onclick="searchTransaction()">Search</button>
            </div>
        </div>

        <div id="match-picker" style="display:none;" class="form-group">
            <label>Multiple matches found — select one:</label>
            <div id="match-list"></div>
        </div>

        <form id="create-refund-form">
            @csrf
            <div id="transaction-details-section" style="display: none;">
                <div class="form-group">
                    <label>Transaction Info (auto-filled)</label>
                    <div id="transaction-info" style="background: #2d3748; padding: 12px; border-radius: 8px; color: #94a3b8;"></div>
                </div>

                <div class="form-group">
                    <label>Select Product to Return</label>
                    <div class="transaction-items" id="transaction-products"></div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Quantity to Return</label>
                        <input type="number" id="refund-quantity" min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label>Calculated Amount</label>
                        <div id="refund-amount-display" style="background: #2d3748; padding: 12px; border-radius: 8px; color: #10b981; font-weight: bold; font-size: 1.1rem;"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Return Type</label>
                    <div class="type-radio-group">
                        <label><input type="radio" name="return_type" value="refund" checked> Refund</label>
                        <label><input type="radio" name="return_type" value="replacement"> Replacement</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Reason for Return</label>
                    <select id="reason-code">
                        <option value="factory_defect">Factory Defect</option>
                        <option value="damaged_product">Damaged Product</option>
                        <option value="wrong_item">Wrong Item</option>
                        <option value="expired_product">Expired Product</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group" id="reason-remarks-group" style="display:none;">
                    <label>Remarks</label>
                    <textarea id="reason-remarks" rows="2" placeholder="Please specify..."></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Submit Return Request
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Refund Details Modal -->
<div class="modal" id="view-refund-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-info-circle"></i> Return Details</h2>
            <button class="modal-close" onclick="closeViewRefundModal()">&times;</button>
        </div>
        <div id="refund-details-content"></div>
    </div>
</div>

<!-- Process Refund Modal -->
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
                <label>Select Refund Method</label>
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
                <label>Reference Number</label>
                <input type="text" id="refund-account-number" placeholder="Enter reference number">
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-check"></i> Confirm Refund
            </button>
        </form>
    </div>
</div>

<!-- Process Replacement Modal -->
<div class="modal" id="process-replacement-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-exchange-alt"></i> Process Replacement</h2>
            <button class="modal-close" onclick="closeProcessReplacementModal()">&times;</button>
        </div>
        <div class="form-group">
            <label>Search Replacement Item</label>
            <input type="text" id="replacement-search-input" placeholder="Search by name, SKU, or barcode..." oninput="searchReplacementInventory()">
        </div>
        <div class="transaction-items" id="replacement-product-list"></div>

        <form id="process-replacement-form">
            @csrf
            <input type="hidden" id="process-replacement-id">
            <input type="hidden" id="selected-replacement-product-id">
            <div class="form-row">
                <div class="form-group">
                    <label>Replacement Quantity</label>
                    <input type="number" id="replacement-quantity" min="1" value="1">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" id="replacement-notes" placeholder="Optional notes">
                </div>
            </div>
            <button type="submit" class="btn-submit">
                <i class="fas fa-check"></i> Confirm Replacement
            </button>
        </form>
    </div>
</div>

<script>
let selectedProduct = null;
let selectedRefundPaymentMethod = 'cash';
let currentRefundAmount = 0;
let searchMode = 'receipt';
let currentReplacementCategoryId = null;
let selectedReplacementProduct = null;
let currentMaxReplacementQty = 1;

// Customer names, return reasons, and product/category text all originate
// as free text from request forms — escape every server-supplied string
// before it goes into innerHTML so a value containing an HTML/script
// payload can't execute in this cashier session.
function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

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
            document.getElementById('declined-refunds').textContent = data.declined_refunds;
            document.getElementById('processed-refunds').textContent = data.processed_refunds;
            document.getElementById('awaiting-action').textContent = data.awaiting_action;
        })
        .catch(err => console.error('Error loading stats:', err));
}

function showCreateRefundModal() {
    document.getElementById('create-refund-modal').classList.add('active');
    document.getElementById('create-refund-form').reset();
    document.getElementById('transaction-details-section').style.display = 'none';
    document.getElementById('match-picker').style.display = 'none';
    document.getElementById('transaction-search-input').value = '';
    selectedProduct = null;
}

function closeCreateRefundModal() {
    document.getElementById('create-refund-modal').classList.remove('active');
}

const searchLabels = { receipt: 'Receipt Number', invoice: 'Invoice Number', customer: 'Customer Name', barcode: 'Product Barcode' };
const searchPlaceholders = { receipt: 'e.g. RCT-000001', invoice: 'e.g. INV-000001', customer: 'e.g. Juan Dela Cruz', barcode: 'Scan or type barcode' };

function setSearchMode(mode) {
    searchMode = mode;
    document.querySelectorAll('.search-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.search-tab[data-mode="${mode}"]`).classList.add('active');
    document.getElementById('search-label').textContent = searchLabels[mode];
    document.getElementById('transaction-search-input').placeholder = searchPlaceholders[mode];
}

function searchTransaction() {
    const q = document.getElementById('transaction-search-input').value.trim();
    if (!q) return;

    fetch(`{{ route('cashier.refunds.search') }}?mode=${searchMode}&q=${encodeURIComponent(q)}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message);
                return;
            }

            if (data.multiple) {
                showMatchPicker(data.matches);
            } else {
                document.getElementById('match-picker').style.display = 'none';
                populateTransactionDetails(data.transaction);
            }
        })
        .catch(() => alert('Error searching for transaction.'));
}

function showMatchPicker(matches) {
    const picker = document.getElementById('match-picker');
    const list = document.getElementById('match-list');
    list.innerHTML = matches.map(m => `
        <div class="refund-product-row" onclick='selectMatch(${Number(m.SalesTransactionID)})'>
            <div><strong>${escapeHtml(m.ReceiptNumber)}</strong> — ${escapeHtml(m.CustomerName ?? 'N/A')}</div>
            <div>${escapeHtml(m.TransactionDate ?? '')}</div>
        </div>
    `).join('');
    picker.style.display = 'block';
    document.getElementById('transaction-details-section').style.display = 'none';
}

function selectMatch(transactionId) {
    fetch(`/cashier/refunds/${transactionId}/transaction`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('match-picker').style.display = 'none';
                populateTransactionDetails(data.transaction);
            }
        });
}

function populateTransactionDetails(transaction) {
    document.getElementById('transaction-info').innerHTML = `
        <strong>Customer:</strong> ${escapeHtml(transaction.CustomerName || 'N/A')}<br>
        <strong>Receipt Number:</strong> ${escapeHtml(transaction.ReceiptNumber)}<br>
        <strong>Invoice Number:</strong> ${escapeHtml(transaction.InvoiceNumber)}<br>
        <strong>Transaction Date:</strong> ${escapeHtml(transaction.TransactionDate)}<br>
        <strong>Payment Method:</strong> ${escapeHtml(transaction.PaymentMethod || 'N/A')}<br>
        <strong>Original Cashier:</strong> ${escapeHtml(transaction.OriginalCashier || 'N/A')}<br>
        <strong>Original Transaction ID:</strong> #${Number(transaction.OriginalTransactionID)}
    `;

    document.getElementById('create-refund-form').dataset.transactionId = transaction.SalesTransactionID;

    const productsContainer = document.getElementById('transaction-products');
    productsContainer.innerHTML = transaction.items.map(item => `
        <div class="refund-product-row" onclick='selectRefundProduct(${Number(item.ProductID)}, ${Number(item.UnitPrice)}, ${Number(item.RemainingReturnableQty)}, ${item.CategoryID ?? "null"})'>
            <div>
                <strong>${escapeHtml(item.ProductName)}</strong><br>
                <small>Barcode: ${escapeHtml(item.Barcode ?? 'N/A')} | SKU: ${escapeHtml(item.SKU ?? 'N/A')} | Category: ${escapeHtml(item.Category ?? 'N/A')}</small><br>
                <small>Qty Purchased: ${Number(item.QuantityPurchased)} | Unit Price: ₱${Number(item.UnitPrice).toFixed(2)} | Returnable: ${Number(item.RemainingReturnableQty)}</small>
            </div>
            <div style="text-align: right;">
                <strong>₱${Number(item.TotalPrice).toFixed(2)}</strong>
            </div>
        </div>
    `).join('');

    document.getElementById('transaction-details-section').style.display = 'block';
}

function selectRefundProduct(productId, unitPrice, maxQty, categoryId) {
    document.querySelectorAll('#transaction-products .refund-product-row').forEach(el => el.classList.remove('selected'));
    event.target.closest('.refund-product-row').classList.add('selected');

    selectedProduct = { productId, unitPrice, maxQty, categoryId };
    document.getElementById('refund-quantity').max = maxQty;
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

document.getElementById('reason-code').addEventListener('change', function() {
    document.getElementById('reason-remarks-group').style.display = this.value === 'other' ? 'block' : 'none';
});

document.getElementById('create-refund-form').addEventListener('submit', function(e) {
    e.preventDefault();

    if (!selectedProduct) {
        alert('Please select a product to return');
        return;
    }

    const qty = parseInt(document.getElementById('refund-quantity').value);
    if (qty > selectedProduct.maxQty) {
        alert('Quantity cannot exceed the returnable amount');
        return;
    }

    const transactionId = this.dataset.transactionId;
    const returnType = document.querySelector('input[name="return_type"]:checked').value;

    fetch('{{ route("cashier.refunds.create") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            _token: '{{ csrf_token() }}',
            transaction_id: transactionId,
            product_id: selectedProduct.productId,
            quantity: qty,
            return_type: returnType,
            reason_code: document.getElementById('reason-code').value,
            reason_remarks: document.getElementById('reason-remarks').value,
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Return request submitted successfully! Awaiting admin approval.');
            closeCreateRefundModal();
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(() => alert('Error submitting return request'));
});

function viewRefundDetails(refundId) {
    fetch(`/cashier/refunds/${refundId}/details`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const r = data.refund;
                document.getElementById('refund-details-content').innerHTML = `
                    <div class="refund-details">
                        <div class="refund-details-row"><span>Request ID:</span><span>#${String(Number(r.id)).padStart(6, '0')}</span></div>
                        <div class="refund-details-row"><span>Transaction ID:</span><span>#${String(Number(r.transaction_id)).padStart(6, '0')}</span></div>
                        <div class="refund-details-row"><span>Product:</span><span>${escapeHtml(r.product_name)}</span></div>
                        <div class="refund-details-row"><span>Quantity:</span><span>${Number(r.quantity)}</span></div>
                        <div class="refund-details-row"><span>Return Type:</span><span>${escapeHtml(r.return_type)}</span></div>
                        <div class="refund-details-row"><span>Reason:</span><span>${escapeHtml(r.reason)}</span></div>
                        <div class="refund-details-row"><span>Return Date:</span><span>${escapeHtml(r.return_date)}</span></div>
                        <div class="refund-details-row"><span>Status:</span><span class="status status-${escapeHtml(r.status.toLowerCase())}">${escapeHtml(r.status)}</span></div>
                        ${r.decline_reason ? `<div class="refund-details-row"><span>Decline Reason:</span><span>${escapeHtml(r.decline_reason)}</span></div>` : ''}
                        ${r.return_type === 'refund' ? `<div class="refund-details-row total"><span>Refund Amount:</span><span>₱${parseFloat(r.refund_amount).toFixed(2)}</span></div>` : ''}
                        ${r.refund_method ? `<div class="refund-details-row"><span>Refund Method:</span><span>${escapeHtml(r.refund_method)}</span></div>` : ''}
                        ${r.refund_date ? `<div class="refund-details-row"><span>Refund Date:</span><span>${escapeHtml(r.refund_date)}</span></div>` : ''}
                        ${r.replacement ? `<div class="refund-details-row"><span>Replacement:</span><span>${Number(r.replacement.quantity)} x ${escapeHtml(r.replacement.product_name)} (Slip ${escapeHtml(r.replacement.slip_number)})</span></div>` : ''}
                    </div>
                `;
                document.getElementById('view-refund-modal').classList.add('active');
            } else {
                alert(data.message);
            }
        })
        .catch(() => alert('Error loading return details'));
}

function closeViewRefundModal() {
    document.getElementById('view-refund-modal').classList.remove('active');
}

function showProcessRefundModal(refundId) {
    fetch(`/cashier/refunds/${refundId}/details`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('process-refund-id').value = refundId;
                currentRefundAmount = data.refund.refund_amount;
                document.getElementById('process-amount').textContent = '₱' + parseFloat(data.refund.refund_amount).toFixed(2);
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
    accountSection.style.display = method === 'cash' ? 'none' : 'block';
}

document.getElementById('process-refund-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const refundId = document.getElementById('process-refund-id').value;

    fetch(`/cashier/refunds/${refundId}/process-refund`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            _token: '{{ csrf_token() }}',
            refund_method: selectedRefundPaymentMethod,
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
    .catch(() => alert('Error processing refund'));
});

function showProcessReplacementModal(refundId, maxQty) {
    document.getElementById('process-replacement-id').value = refundId;
    currentMaxReplacementQty = maxQty;
    document.getElementById('replacement-quantity').max = maxQty;
    document.getElementById('replacement-quantity').value = 1;
    document.getElementById('replacement-search-input').value = '';
    document.getElementById('replacement-product-list').innerHTML = '';
    selectedReplacementProduct = null;
    document.getElementById('process-replacement-modal').classList.add('active');
}

function closeProcessReplacementModal() {
    document.getElementById('process-replacement-modal').classList.remove('active');
}

let replacementSearchTimer = null;
function searchReplacementInventory() {
    clearTimeout(replacementSearchTimer);
    replacementSearchTimer = setTimeout(() => {
        const q = document.getElementById('replacement-search-input').value.trim();
        fetch(`{{ route('cashier.replacement-inventory.search') }}?q=${encodeURIComponent(q)}`)
            .then(response => response.json())
            .then(data => {
                const list = document.getElementById('replacement-product-list');
                list.innerHTML = data.products.map(p => `
                    <div class="refund-product-row" onclick='selectReplacementProduct(${Number(p.ProductID)}, "${escapeHtml(p.ProductName).replace(/"/g, '&quot;')}", ${Number(p.Stock)})'>
                        <div>
                            <strong>${escapeHtml(p.ProductName)}</strong><br>
                            <small>SKU: ${escapeHtml(p.SKU ?? 'N/A')} | Barcode: ${escapeHtml(p.Barcode ?? 'N/A')}</small>
                        </div>
                        <div style="text-align:right;">Stock: ${Number(p.Stock)}</div>
                    </div>
                `).join('');
            });
    }, 300);
}

function selectReplacementProduct(productId, name, stock) {
    document.querySelectorAll('#replacement-product-list .refund-product-row').forEach(el => el.classList.remove('selected'));
    event.target.closest('.refund-product-row').classList.add('selected');
    selectedReplacementProduct = { productId, name, stock };
}

document.getElementById('process-replacement-form').addEventListener('submit', function(e) {
    e.preventDefault();

    if (!selectedReplacementProduct) {
        alert('Please select a replacement product.');
        return;
    }

    const qty = parseInt(document.getElementById('replacement-quantity').value);
    if (qty > selectedReplacementProduct.stock) {
        alert('Insufficient stock for the selected replacement item.');
        return;
    }
    if (qty > currentMaxReplacementQty) {
        alert('Replacement quantity cannot exceed the approved return quantity.');
        return;
    }

    const refundId = document.getElementById('process-replacement-id').value;

    fetch(`/cashier/refunds/${refundId}/process-replacement`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            _token: '{{ csrf_token() }}',
            replacement_product_id: selectedReplacementProduct.productId,
            quantity: qty,
            notes: document.getElementById('replacement-notes').value,
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Replacement processed successfully! Slip: ' + data.slip_number);
            closeProcessReplacementModal();
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(() => alert('Error processing replacement'));
});
</script>
@endsection
