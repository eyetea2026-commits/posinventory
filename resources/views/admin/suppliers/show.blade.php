@extends('admin.layout')

@section('header')
    <div class="header-title">
        <h1>Supplier History</h1>
        <p>{{ $supplier->SupplierName }}</p>
    </div>
@endsection

@section('content')
<style>
    .supplier-card {
        background: rgba(15, 23, 42, 0.75);
        border: 1px solid rgba(148, 163, 184, 0.12);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(12px);
        padding: 28px;
        margin: 0 auto 24px;
    }
    .section-title {
        color: #cbd5e1; font-size: 1rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.06em; margin: 0 0 16px; padding-bottom: 8px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.15);
    }
    .items-table { width: 100%; border-collapse: collapse; }
    .items-table th { text-align: left; padding: 8px 10px; font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; border-bottom: 1px solid rgba(148, 163, 184, 0.15); }
    .items-table td { padding: 10px; color: #f8fafc; font-size: 0.88rem; border-bottom: 1px solid rgba(148, 163, 184, 0.08); }
    .badge { display: inline-flex; padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
    .badge-success { background: rgba(16, 185, 129, 0.15); color: #6ee7b7; }
    .badge-warning { background: rgba(245, 158, 11, 0.15); color: #fcd34d; }
    .badge-danger { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
    .badge-info { background: rgba(59, 130, 246, 0.15); color: #93c5fd; }
    .badge-secondary { background: rgba(100, 116, 139, 0.2); color: #cbd5e1; }
    .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; }
    .btn-secondary { background: rgba(100, 116, 139, 0.2); color: #e2e8f0; }
    .action-btn { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; border: none; cursor: pointer; background: rgba(59,130,246,0.15); color: #93c5fd; }
    .action-btn:hover { background: rgba(59,130,246,0.25); }

    /* Search bar — matches Products/Inventory/Category */
    .search-box-wrapper {
        position: relative; display: flex; align-items: center; flex: 1;
        min-width: 260px; max-width: 420px; background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(59, 130, 246, 0.15); border-radius: 12px;
    }
    .search-box-wrapper:focus-within { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }
    .search-box-wrapper .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.95rem; pointer-events: none; }
    .search-input { flex: 1; width: 100%; padding: 12px 16px 12px 44px; background: transparent; border: none; color: #f8fafc; font-size: 0.95rem; }
    .search-input::placeholder { color: #64748b; }
    .search-input:focus { outline: none; }
    .table-loader { display: none; padding: 12px; text-align: center; color: #94a3b8; }
    .table-loader.active { display: block; }
    .pagination { display: flex; gap: 6px; justify-content: center; padding: 16px; flex-wrap: wrap; }
    .pagination-link {
        min-width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;
        padding: 0 10px; border-radius: 8px; background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(59, 130, 246, 0.15); color: #cbd5e1; text-decoration: none; font-size: 0.9rem;
    }
    .pagination-link.active { background: linear-gradient(135deg, #3b82f6, #10b981); color: white; border-color: transparent; }
    .pagination-link.disabled { opacity: 0.4; pointer-events: none; }

    /* View Details modal */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 900; align-items:center; justify-content:center; backdrop-filter: blur(6px); }
    .modal-overlay.active { display: flex; }
    .modal-content { background: #0f172a; border: 1px solid #334155; border-radius: 20px; padding: 20px 24px; width: 92%; max-width: 720px; max-height: 88vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #334155; }
    .modal-header h2 { margin: 0; font-size: 1.1rem; color: #f8fafc; }
    .modal-close { width: 32px; height: 32px; background: #1e293b; border: none; border-radius: 8px; color: #94a3b8; font-size: 1.2rem; cursor: pointer; }
    .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; padding-top: 12px; border-top: 1px solid #334155; }
</style>

<div class="supplier-card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
        <h2 class="section-title" style="margin:0; border:none; padding:0;">Transaction History</h2>
        <form method="GET" action="{{ route('admin.suppliers.show', $supplier) }}" id="historySearchForm" class="search-box-wrapper" autocomplete="off">
            <i class="search-icon fas fa-search"></i>
            <input type="text" name="search" id="historySearchInput" value="{{ $search ?? '' }}" class="search-input" placeholder="Search by PO number...">
        </form>
    </div>
    <div class="table-loader" id="historyTableLoader"><i class="fas fa-spinner fa-spin"></i></div>
    <div style="overflow-x:auto;">
        <table class="items-table">
            <thead>
                <tr>
                    <th>PO Number</th><th>Purchase Date</th><th>Supplier</th><th># Products</th>
                    <th>Total Qty</th><th>Total Amount</th><th>Status</th><th>Delivery Status</th><th>Received Date</th><th>Actions</th>
                </tr>
            </thead>
            <tbody id="historyTbody">
                @include('admin.suppliers.partials.history-rows', ['history' => $history, 'supplier' => $supplier])
            </tbody>
        </table>
    </div>
    <div id="historyPaginationWrapper">
        @include('admin.suppliers.partials.history-pagination', ['history' => $history])
    </div>
</div>

<!-- View Details Modal -->
<div id="historyDetailsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-file-invoice"></i> Purchase Order Details</h2>
            <button type="button" class="modal-close" onclick="closeSupplierHistoryModal()" aria-label="Close">&times;</button>
        </div>
        <div id="historyDetailsBody">
            <p style="color:#94a3b8;">Loading...</p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeSupplierHistoryModal()">Close</button>
        </div>
    </div>
</div>

<div style="margin-top:20px;">
    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Suppliers</a>
</div>

<script>
    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ---- Real-time debounced search over the transaction history table ----
    (function () {
        const filterForm = document.getElementById('historySearchForm');
        const searchInput = document.getElementById('historySearchInput');
        const tableLoader = document.getElementById('historyTableLoader');
        const tbody = document.getElementById('historyTbody');
        const paginationWrapper = document.getElementById('historyPaginationWrapper');

        let debounceTimer = null;
        let currentController = null;

        function buildQuery(page = 1) {
            const params = new URLSearchParams();
            const search = searchInput.value.trim();
            if (search) params.set('search', search);
            if (page > 1) params.set('page', page);
            return params.toString();
        }

        async function applyFilters(page = 1) {
            const query = buildQuery(page);
            const url = `${filterForm.action}${query ? '?' + query : ''}`;
            window.history.replaceState({}, '', url);
            tableLoader.classList.add('active');

            if (currentController) currentController.abort();
            currentController = new AbortController();

            try {
                const response = await fetch(url, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    signal: currentController.signal,
                });
                if (!response.ok) throw new Error(`Request failed (${response.status})`);
                const data = await response.json();
                tbody.innerHTML = data.rows || '';
                paginationWrapper.innerHTML = data.pagination || '';
                rebindPagination();
            } catch (err) {
                if (err.name === 'AbortError') return;
                tbody.innerHTML = `<tr><td colspan="10" style="text-align:center; color:#fca5a5;">Unable to load history. Please try again.</td></tr>`;
                paginationWrapper.innerHTML = '';
            } finally {
                tableLoader.classList.remove('active');
            }
        }

        function rebindPagination() {
            paginationWrapper.querySelectorAll('a.pagination-link').forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const url = new URL(this.href);
                    applyFilters(url.searchParams.get('page') || 1);
                });
            });
        }

        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => applyFilters(1), 300);
        });

        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            applyFilters(1);
        });

        rebindPagination();
    })();

    // ---- View Details modal ----
    function viewSupplierHistoryDetails(supplierId, purchaseOrderId) {
        const modal = document.getElementById('historyDetailsModal');
        const body = document.getElementById('historyDetailsBody');
        body.innerHTML = '<p style="color:#94a3b8;">Loading...</p>';
        modal.classList.add('active');

        fetch(`/admin/suppliers/${supplierId}/purchase-orders/${purchaseOrderId}`, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                const po = data.purchaseOrder, s = data.supplier, summary = data.summary, delivery = data.delivery;

                let html = '<h4>Purchase Order Information</h4>';
                html += `<p><strong>PO Number:</strong> ${escapeHtml(po.PONumber)}</p>`;
                html += `<p><strong>Purchase Date:</strong> ${escapeHtml(po.PurchaseDate)}</p>`;
                html += `<p><strong>Status:</strong> ${escapeHtml(po.Status)}</p>`;
                html += `<p><strong>Created By:</strong> ${escapeHtml(po.CreatedBy)}</p>`;
                html += `<p><strong>Supplier:</strong> ${escapeHtml(s.SupplierName)} (${escapeHtml(s.ContactNumber)} / ${escapeHtml(s.Email)})</p>`;

                html += '<hr><h4>Ordered Products</h4>';
                html += '<div style="overflow-x:auto;"><table class="items-table"><thead><tr><th>Product</th><th>Category</th><th>SKU</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Unit Cost</th><th>Subtotal</th></tr></thead><tbody>';
                (data.items || []).forEach(function (item) {
                    html += `<tr>
                        <td>${escapeHtml(item.ProductName)}</td>
                        <td>${escapeHtml(item.Category)}</td>
                        <td>${escapeHtml(item.SKU)}</td>
                        <td>${escapeHtml(item.OrderedQuantity)}</td>
                        <td>${escapeHtml(item.ReceivedQuantity)}</td>
                        <td>${escapeHtml(item.RemainingQuantity)}</td>
                        <td>${window.formatPeso ? window.formatPeso(item.UnitCost) : item.UnitCost}</td>
                        <td>${window.formatPeso ? window.formatPeso(item.Subtotal) : item.Subtotal}</td>
                    </tr>`;
                });
                html += '</tbody></table></div>';

                html += '<hr><h4>Order Summary</h4>';
                html += `<p><strong>Total Products:</strong> ${escapeHtml(summary.TotalProducts)}</p>`;
                html += `<p><strong>Total Quantity Ordered:</strong> ${escapeHtml(summary.TotalQuantityOrdered)}</p>`;
                html += `<p><strong>Total Purchase Amount:</strong> ${window.formatPeso ? window.formatPeso(summary.TotalPurchaseAmount) : summary.TotalPurchaseAmount}</p>`;
                if (po.Notes) html += `<p><strong>Notes / Remarks:</strong> ${escapeHtml(po.Notes)}</p>`;

                html += '<hr><h4>Delivery Information</h4>';
                html += `<p><strong>Expected Delivery:</strong> ${escapeHtml(delivery.ExpectedDeliveryDate ?? 'N/A')}</p>`;
                html += `<p><strong>Received Date:</strong> ${escapeHtml(delivery.ReceivedDate ?? 'Not yet received')}</p>`;
                html += `<p><strong>Receiving Status:</strong> ${escapeHtml(delivery.DeliveryStatus)}</p>`;

                if (data.auditHistory && data.auditHistory.length) {
                    html += '<hr><h4>Audit Information</h4>';
                    data.auditHistory.forEach(function (entry) {
                        html += `<p style="font-size:0.85rem; color:#94a3b8;">${escapeHtml(entry.DateRecorded)} — ${escapeHtml(entry.Description)}</p>`;
                    });
                }

                body.innerHTML = html;
            })
            .catch(() => { body.innerHTML = '<p style="color:#fca5a5;">Failed to load details.</p>'; });
    }

    function closeSupplierHistoryModal() {
        document.getElementById('historyDetailsModal').classList.remove('active');
    }

    document.getElementById('historyDetailsModal').addEventListener('mousedown', function (e) {
        if (e.target === this) closeSupplierHistoryModal();
    });
</script>
@endsection
