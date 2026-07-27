{{-- Glassmorphism "Supplier History" modal, opened from the Supplier List's
     "View History" action. Keeps the admin on the Supplier List instead of
     navigating to the standalone admin.suppliers.show page — reuses that
     same controller's existing AJAX contract ({rows, pagination} JSON) for
     both the initial fetch and the in-modal search, and the existing
     purchase-order-details JSON endpoint for the nested "View Details"
     drill-down. admin.partials.modal-styles is expected to already be
     included on the page (Suppliers index already does). --}}
<style>
    .supplier-history-table th { text-align: left; padding: 8px 10px; font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; border-bottom: 1px solid rgba(148, 163, 184, 0.15); }
    .supplier-history-table td { padding: 10px; color: #f8fafc; font-size: 0.88rem; border-bottom: 1px solid rgba(148, 163, 184, 0.08); }
    .supplier-history-table { width: 100%; border-collapse: collapse; }
</style>

<div id="supplierHistoryModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="supplierHistoryModalTitle" aria-hidden="true">
    <div class="modal-content modal-content-wide" style="max-width: 920px;">
        <div class="modal-header">
            <h2 id="supplierHistoryModalTitle"><i class="fas fa-clock-rotate-left"></i> Supplier History</h2>
            <button type="button" class="modal-close" onclick="closeSupplierHistoryModal()" aria-label="Close">&times;</button>
        </div>

        <div style="display:flex; justify-content:flex-end; margin-bottom:14px;">
            <div class="search-box-wrapper" style="position:relative; display:flex; align-items:center; min-width:220px; max-width:320px; background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(59, 130, 246, 0.15); border-radius: 12px;">
                <i class="search-icon fas fa-search" style="position:absolute; left:14px; color:#94a3b8; font-size:0.9rem;"></i>
                <input type="text" id="supplierHistorySearchInput" class="search-input" style="flex:1; width:100%; padding:10px 14px 10px 38px; background:transparent; border:none; color:#f8fafc; font-size:0.9rem;" placeholder="Search by PO number...">
            </div>
        </div>

        <div id="supplierHistoryTableLoader" style="display:none; padding:12px; text-align:center; color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i></div>
        <div style="overflow-x:auto;">
            <table class="supplier-history-table">
                <thead>
                    <tr>
                        <th>PO Number</th><th>Purchase Date</th><th>Supplier</th><th># Products</th>
                        <th>Total Qty</th><th>Total Amount</th><th>Status</th><th>Delivery Status</th><th>Received Date</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="supplierHistoryTbody"></tbody>
            </table>
        </div>
        <div id="supplierHistoryPaginationWrapper"></div>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeSupplierHistoryModal()">Close</button>
        </div>
    </div>
</div>

<!-- Nested "View Details" modal for a single Purchase Order -->
<div id="supplierHistoryDetailsModal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-file-invoice"></i> Purchase Order Details</h2>
            <button type="button" class="modal-close" onclick="closeSupplierHistoryDetailsModal()" aria-label="Close">&times;</button>
        </div>
        <div id="supplierHistoryDetailsBody">
            <p style="color:#94a3b8;">Loading...</p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeSupplierHistoryDetailsModal()">Close</button>
        </div>
    </div>
</div>

<script>
    const SUPPLIER_HISTORY_URL_TEMPLATE = '{{ route('admin.suppliers.show', ['supplier' => '__SUPPLIER_ID__']) }}';
    const SUPPLIER_PO_DETAILS_URL_TEMPLATE = '{{ route('admin.suppliers.purchase-order-details', ['supplier' => '__SUPPLIER_ID__', 'purchaseOrder' => '__PO_ID__']) }}';

    let currentSupplierHistoryId = null;
    let supplierHistoryLastFocused = null;
    let supplierHistoryDebounceTimer = null;
    let supplierHistoryController = null;

    function escapeSupplierHistoryHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function buildSupplierHistoryUrl(page) {
        const params = new URLSearchParams();
        const search = document.getElementById('supplierHistorySearchInput').value.trim();
        if (search) params.set('search', search);
        if (page > 1) params.set('page', page);
        const base = SUPPLIER_HISTORY_URL_TEMPLATE.replace('__SUPPLIER_ID__', currentSupplierHistoryId);
        const query = params.toString();
        return base + (query ? '?' + query : '');
    }

    async function refreshSupplierHistory(page) {
        page = page || 1;
        const loader = document.getElementById('supplierHistoryTableLoader');
        const tbody = document.getElementById('supplierHistoryTbody');
        const paginationWrapper = document.getElementById('supplierHistoryPaginationWrapper');

        loader.style.display = 'block';
        if (supplierHistoryController) supplierHistoryController.abort();
        supplierHistoryController = new AbortController();

        try {
            const response = await fetch(buildSupplierHistoryUrl(page), {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                signal: supplierHistoryController.signal,
            });
            if (!response.ok) throw new Error('Request failed (' + response.status + ')');
            const data = await response.json();
            tbody.innerHTML = data.rows || '';
            paginationWrapper.innerHTML = data.pagination || '';
            rebindSupplierHistoryPagination();
        } catch (err) {
            if (err.name === 'AbortError') return;
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; color:#fca5a5;">Unable to load history. Please try again.</td></tr>';
            paginationWrapper.innerHTML = '';
        } finally {
            loader.style.display = 'none';
        }
    }

    function rebindSupplierHistoryPagination() {
        document.getElementById('supplierHistoryPaginationWrapper').querySelectorAll('a.pagination-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const url = new URL(this.href);
                refreshSupplierHistory(url.searchParams.get('page') || 1);
            });
        });
    }

    document.getElementById('supplierHistorySearchInput').addEventListener('input', function () {
        clearTimeout(supplierHistoryDebounceTimer);
        supplierHistoryDebounceTimer = setTimeout(function () { refreshSupplierHistory(1); }, 300);
    });

    function supplierHistoryModalIsOpen() {
        return document.getElementById('supplierHistoryModal').classList.contains('active');
    }

    window.openSupplierHistoryModal = function (supplierId, supplierName) {
        currentSupplierHistoryId = supplierId;
        supplierHistoryLastFocused = document.activeElement;

        const modal = document.getElementById('supplierHistoryModal');
        const titleEl = document.getElementById('supplierHistoryModalTitle');
        titleEl.innerHTML = '<i class="fas fa-clock-rotate-left"></i> Supplier History &mdash; ' + escapeSupplierHistoryHtml(supplierName);
        document.getElementById('supplierHistorySearchInput').value = '';
        document.getElementById('supplierHistoryTbody').innerHTML = '';
        document.getElementById('supplierHistoryPaginationWrapper').innerHTML = '';

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void modal.offsetHeight;
        requestAnimationFrame(function () { modal.classList.add('active'); });
        document.addEventListener('keydown', handleSupplierHistoryModalKeydown);

        refreshSupplierHistory(1);
    };

    window.closeSupplierHistoryModal = function () {
        const modal = document.getElementById('supplierHistoryModal');
        modal.classList.remove('active');
        document.removeEventListener('keydown', handleSupplierHistoryModalKeydown);
        setTimeout(function () { modal.style.display = 'none'; }, 250);
        document.body.style.overflow = '';
        if (supplierHistoryLastFocused && typeof supplierHistoryLastFocused.focus === 'function') {
            supplierHistoryLastFocused.focus();
        }
    };

    function handleSupplierHistoryModalKeydown(e) {
        if (!supplierHistoryModalIsOpen()) return;

        if (e.key === 'Escape') {
            // If the nested View Details modal is open, close that first.
            if (document.getElementById('supplierHistoryDetailsModal').classList.contains('active')) {
                window.closeSupplierHistoryDetailsModal();
            } else {
                window.closeSupplierHistoryModal();
            }
            return;
        }

        if (e.key === 'Tab') {
            const modal = document.getElementById('supplierHistoryModal');
            const focusable = modal.querySelectorAll('input, select, textarea, button, [href]');
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }

    document.getElementById('supplierHistoryModal').addEventListener('mousedown', function (e) {
        if (e.target === this) window.closeSupplierHistoryModal();
    });

    // ---- Nested "View Details" modal ----
    window.viewSupplierHistoryDetails = function (supplierId, purchaseOrderId) {
        const modal = document.getElementById('supplierHistoryDetailsModal');
        const body = document.getElementById('supplierHistoryDetailsBody');
        body.innerHTML = '<p style="color:#94a3b8;">Loading...</p>';
        modal.style.display = 'flex';
        requestAnimationFrame(function () { modal.classList.add('active'); });

        const url = SUPPLIER_PO_DETAILS_URL_TEMPLATE.replace('__SUPPLIER_ID__', supplierId).replace('__PO_ID__', purchaseOrderId);

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                const po = data.purchaseOrder, s = data.supplier, summary = data.summary, delivery = data.delivery;
                const e = escapeSupplierHistoryHtml;

                let html = '<h4>Purchase Order Information</h4>';
                html += '<p><strong>PO Number:</strong> ' + e(po.PONumber) + '</p>';
                html += '<p><strong>Purchase Date:</strong> ' + e(po.PurchaseDate) + '</p>';
                html += '<p><strong>Status:</strong> ' + e(po.Status) + '</p>';
                html += '<p><strong>Created By:</strong> ' + e(po.CreatedBy) + '</p>';
                html += '<p><strong>Supplier:</strong> ' + e(s.SupplierName) + ' (' + e(s.ContactNumber) + ' / ' + e(s.Email) + ')</p>';

                html += '<hr><h4>Ordered Products</h4>';
                html += '<div style="overflow-x:auto;"><table class="supplier-history-table"><thead><tr><th>Product</th><th>Category</th><th>SKU</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Unit Cost</th><th>Subtotal</th></tr></thead><tbody>';
                (data.items || []).forEach(function (item) {
                    html += '<tr>'
                        + '<td>' + e(item.ProductName) + '</td>'
                        + '<td>' + e(item.Category) + '</td>'
                        + '<td>' + e(item.SKU) + '</td>'
                        + '<td>' + e(item.OrderedQuantity) + '</td>'
                        + '<td>' + e(item.ReceivedQuantity) + '</td>'
                        + '<td>' + e(item.RemainingQuantity) + '</td>'
                        + '<td>' + (window.formatPeso ? window.formatPeso(item.UnitCost) : item.UnitCost) + '</td>'
                        + '<td>' + (window.formatPeso ? window.formatPeso(item.Subtotal) : item.Subtotal) + '</td>'
                        + '</tr>';
                });
                html += '</tbody></table></div>';

                html += '<hr><h4>Order Summary</h4>';
                html += '<p><strong>Total Products:</strong> ' + e(summary.TotalProducts) + '</p>';
                html += '<p><strong>Total Quantity Ordered:</strong> ' + e(summary.TotalQuantityOrdered) + '</p>';
                html += '<p><strong>Total Purchase Amount:</strong> ' + (window.formatPeso ? window.formatPeso(summary.TotalPurchaseAmount) : summary.TotalPurchaseAmount) + '</p>';
                if (po.Notes) html += '<p><strong>Notes / Remarks:</strong> ' + e(po.Notes) + '</p>';

                html += '<hr><h4>Delivery Information</h4>';
                html += '<p><strong>Expected Delivery:</strong> ' + e(delivery.ExpectedDeliveryDate ?? 'N/A') + '</p>';
                html += '<p><strong>Received Date:</strong> ' + e(delivery.ReceivedDate ?? 'Not yet received') + '</p>';
                html += '<p><strong>Receiving Status:</strong> ' + e(delivery.DeliveryStatus) + '</p>';

                if (data.auditHistory && data.auditHistory.length) {
                    html += '<hr><h4>Audit Information</h4>';
                    data.auditHistory.forEach(function (entry) {
                        html += '<p style="font-size:0.85rem; color:#94a3b8;">' + e(entry.DateRecorded) + ' &mdash; ' + e(entry.Description) + '</p>';
                    });
                }

                body.innerHTML = html;
            })
            .catch(function () {
                body.innerHTML = '<p style="color:#fca5a5;">Failed to load details.</p>';
            });
    };

    window.closeSupplierHistoryDetailsModal = function () {
        const modal = document.getElementById('supplierHistoryDetailsModal');
        modal.classList.remove('active');
        setTimeout(function () { modal.style.display = 'none'; }, 250);
    };

    document.getElementById('supplierHistoryDetailsModal').addEventListener('mousedown', function (e) {
        if (e.target === this) window.closeSupplierHistoryDetailsModal();
    });
</script>
