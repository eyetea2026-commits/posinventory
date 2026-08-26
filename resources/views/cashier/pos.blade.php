@extends('cashier.layout')

@section('title', 'POS Panel - CCTV Express')

@section('content')
<style>
    .pos-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
    .pos-header h1 { margin: 0; font-size: 1.5rem; }
    .pos-header .cashier-info { display: flex; align-items: center; gap: 10px; color: #94a3b8; }
    .pos-header .pos-datetime { display: flex; align-items: center; gap: 8px; color: #94a3b8; font-size: 0.9rem; }
    .pos-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px; height: calc(100vh - 140px); }
    .pos-products { background: #1a1d2d; border-radius: 16px; padding: 20px; display: flex; flex-direction: column; overflow: hidden; }
    .pos-cart { background: #1a1d2d; border-radius: 16px; padding: 20px; display: flex; flex-direction: column; overflow-y: auto; }

    .search-bar { display: flex; gap: 10px; margin-bottom: 16px; }
    .search-bar input { flex: 1; padding: 14px 18px; background: #2d3748; border: 1px solid #4a5568; color: #e2e8f0; border-radius: 10px; font-size: 0.95rem; }
    .search-bar input:focus { outline: none; border-color: #3b82f6; }
    .search-bar button { padding: 14px 20px; background: #3b82f6; border: none; color: white; border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
    .search-bar button:hover { background: #2563eb; transform: translateY(-1px); }

    .barcode-scanner { display: flex; gap: 10px; margin-bottom: 16px; }
    .barcode-scanner input { flex: 1; padding: 14px 18px; background: #2d3748; border: 1px solid #4a5568; color: #e2e8f0; border-radius: 10px; }
    .barcode-scanner input:focus { outline: none; border-color: #8b5cf6; }
    .barcode-scanner button { padding: 14px 20px; background: #8b5cf6; border: none; color: white; border-radius: 10px; cursor: pointer; font-weight: 600; }
    .barcode-scanner button:hover { background: #7c3aed; }

    .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(154px, 1fr)); gap: 10px; flex: 1; overflow-y: auto; padding-right: 8px; align-content: start; }
    .products-grid::-webkit-scrollbar { width: 6px; }
    .products-grid::-webkit-scrollbar-track { background: #1a1d2d; }
    .products-grid::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 3px; }

    .product-card { background: #2d3748; border-radius: 11px; padding: 13px; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; }
    .product-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.3); border-color: #3b82f6; }
    .product-card.out-of-stock { opacity: 0.5; cursor: not-allowed; }
    .product-card h3 { margin: 0 0 4px; font-size: 0.88rem; line-height: 1.25; color: #f8fafc; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .product-card p { margin: 0; color: #94a3b8; font-size: 0.76rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .product-card .stock { font-size: 0.72rem; color: #94a3b8; margin: 6px 0; }
    .product-card .stock.low { color: #f59e0b; }
    .product-card .stock.out { color: #ef4444; }
    .product-card .price { color: #60a5fa; font-size: 1rem; font-weight: bold; }

    .cart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
    .cart-header h2 { margin: 0; font-size: 1.2rem; }
    .clear-cart-btn { padding: 8px 16px; background: #ef4444; border: none; color: white; border-radius: 8px; cursor: pointer; font-size: 0.85rem; }
    .clear-cart-btn:hover { background: #dc2626; }

    .cart-items { flex: 1 1 auto; min-height: 140px; overflow-y: auto; margin-bottom: 16px; padding-right: 8px; }
    .cart-items::-webkit-scrollbar { width: 6px; }
    .cart-items::-webkit-scrollbar-track { background: #1a1d2d; }
    .cart-items::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 3px; }

    .cart-item { display: flex; align-items: center; padding: 14px; background: #2d3748; border-radius: 12px; margin-bottom: 10px; gap: 12px; }
    .cart-item-info { flex: 1; min-width: 0; }
    .cart-item-info h4 { margin: 0; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cart-item-info p { margin: 4px 0 0; color: #94a3b8; font-size: 0.8rem; }
    .cart-item-qty { display: flex; align-items: center; gap: 8px; background: #1a1d2d; border-radius: 8px; padding: 4px; }
    .qty-btn { width: 28px; height: 28px; background: #4a5568; border: none; color: white; border-radius: 6px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s; user-select: none; -webkit-user-select: none; -webkit-touch-callout: none; touch-action: manipulation; }
    .qty-btn:hover { background: #3b82f6; }
    .qty-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .qty-btn.long-pressing { background: #3b82f6; }
    .qty-input {
        width: 40px;
        height: 28px;
        background: #0f1420;
        border: 1px solid #4a5568;
        color: #f1f5f9;
        border-radius: 6px;
        text-align: center;
        font-size: 0.95rem;
        font-weight: 600;
        -moz-appearance: textfield;
    }
    .qty-input:focus { outline: none; border-color: #3b82f6; }
    .qty-input::-webkit-outer-spin-button,
    .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

    .cart-item-price { text-align: right; min-width: 80px; }
    .cart-item-price .item-total { font-weight: bold; color: #60a5fa; font-size: 1rem; }
    .cart-item-price .item-price { font-size: 0.75rem; color: #94a3b8; }
    .remove-btn { background: none; border: none; color: #ef4444; cursor: pointer; padding: 4px; font-size: 1rem; margin-left: 8px; }

    .cart-item-promo-row { flex-basis: 100%; margin-top: 8px; }
    .apply-promo-btn {
        background: none; border: 1px dashed #4a5568; color: #93c5fd; cursor: pointer;
        padding: 4px 10px; font-size: 0.72rem; border-radius: 6px; transition: all 0.2s;
    }
    .apply-promo-btn:hover { border-color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
    .promo-applied-chip {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(16, 185, 129, 0.15); color: #6ee7b7;
        padding: 4px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: 600;
    }
    .promo-applied-chip button {
        background: none; border: none; color: #6ee7b7; cursor: pointer; padding: 0; font-size: 0.85rem; line-height: 1;
    }
    .applied-promo-badge { font-size: 0.7rem; color: #6ee7b7; font-weight: 400; }
    .remove-btn:hover { color: #dc2626; }

    .cart-summary { background: #2d3748; border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; flex-shrink: 0; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.82rem; }
    .summary-row.total { font-size: 1.05rem; font-weight: bold; border-top: 1px solid #4a5568; padding-top: 7px; margin-top: 7px; margin-bottom: 0; color: #10b981; }

    .form-group { margin-bottom: 9px; }
    .form-group label { display: block; margin-bottom: 3px; color: #94a3b8; font-size: 0.76rem; }
    .form-group input, .form-group select { width: 100%; padding: 8px 10px; background: #2d3748; border: 1px solid #4a5568; color: #e2e8f0; border-radius: 8px; font-size: 0.85rem; }
    .form-group input:focus { outline: none; border-color: #3b82f6; }
    .change-display { padding: 8px 10px; background: #1a1d2d; border: 1px solid #4a5568; color: #34d399; border-radius: 8px; font-size: 0.85rem; font-weight: 600; }

    .payment-details-panel { background: #1a1d2d; border: 1px solid #4a5568; border-radius: 10px; padding: 12px; margin-bottom: 14px; }
    .payment-details-panel.confirmed { border-color: #34d399; }
    .payment-details-panel .form-group:last-of-type { margin-bottom: 0; }
    .payment-details-header { color: #cbd5e1; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
    .verify-checkbox-label { display: flex; align-items: flex-start; gap: 8px; color: #cbd5e1; font-size: 0.78rem; margin: 10px 0; cursor: pointer; line-height: 1.3; }
    .verify-checkbox-label input[type="checkbox"] { margin-top: 2px; width: 15px; height: 15px; flex-shrink: 0; accent-color: #3b82f6; cursor: pointer; }
    .btn-verify-payment { width: 100%; padding: 9px; background: #3b82f6; border: none; color: white; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.82rem; transition: background 0.2s ease; }
    .btn-verify-payment:hover { background: #2563eb; }
    .payment-details-panel.confirmed .btn-verify-payment { display: none; }
    .payment-verified-badge { display: flex; align-items: center; gap: 6px; color: #34d399; font-size: 0.8rem; font-weight: 600; padding: 8px; background: rgba(52, 211, 153, 0.1); border-radius: 8px; margin-top: 4px; }

    .payment-dropdown { position: relative; margin-bottom: 14px; }
    .payment-dropdown-trigger { width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 8px 10px; background: #2d3748; border: 2px solid #4a5568; border-radius: 8px; color: #e2e8f0; cursor: pointer; font-size: 0.85rem; font-family: inherit; transition: border-color 0.2s ease; }
    .payment-dropdown-trigger:hover { border-color: #60a5fa; }
    .payment-dropdown.open .payment-dropdown-trigger { border-color: #3b82f6; }
    .payment-dropdown-selected { display: flex; align-items: center; gap: 10px; }
    .payment-dropdown-selected i { width: 16px; text-align: center; }
    .payment-dropdown-chevron { font-size: 0.72rem; color: #94a3b8; transition: transform 0.2s ease; }
    .payment-dropdown.open .payment-dropdown-chevron { transform: rotate(180deg); }
    .payment-dropdown-menu { display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #2d3748; border: 1px solid #4a5568; border-radius: 8px; overflow: hidden; z-index: 20; box-shadow: 0 12px 28px rgba(0,0,0,0.4); }
    .payment-dropdown.open .payment-dropdown-menu { display: block; }
    .payment-dropdown-option { display: flex; align-items: center; gap: 10px; padding: 10px 14px; font-size: 0.85rem; cursor: pointer; transition: background 0.15s ease; }
    .payment-dropdown-option:hover { background: rgba(59, 130, 246, 0.12); }
    .payment-dropdown-option.selected { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
    .payment-dropdown-option i { width: 16px; text-align: center; }

    .btn-checkout { width: 100%; padding: 12px; background: linear-gradient(135deg, #10b981, #059669); border: none; color: white; font-size: 0.95rem; font-weight: bold; border-radius: 12px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .btn-checkout:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }
    .btn-checkout:disabled { background: #4a5568; cursor: not-allowed; transform: none; box-shadow: none; }

    .empty-cart { text-align: center; padding: 40px; color: #64748b; }
    .empty-cart i { font-size: 3rem; margin-bottom: 16px; }

    @media (max-width: 1200px) {
        .pos-grid { grid-template-columns: 1fr; height: auto; }
        .pos-products, .pos-cart { max-height: 500px; }
    }

    @media (max-width: 768px) {
        .pos-header { flex-direction: column; align-items: flex-start; }
        .products-grid { grid-template-columns: repeat(auto-fill, minmax(125px, 1fr)); }
    }
</style>

<div class="pos-header">
    <div>
        <h1><i class="fas fa-cash-register"></i> POS Panel</h1>
    </div>
    <div class="pos-datetime">
        <i class="fas fa-clock"></i>
        <span id="posCurrentDate">{{ now()->format('l, F j, Y') }}</span>
        &middot;
        <span id="posCurrentTime">{{ now()->format('h:i A') }}</span>
    </div>
    <div class="cashier-info">
        @include('cashier.partials.notification-bell')
        <i class="fas fa-user"></i>
        <span>Cashier:</span>
        <strong>{{ auth()->user()->full_name }}</strong>
    </div>
</div>

<div class="pos-grid">
    <div class="pos-products">
        <div class="barcode-scanner">
            <input type="text" id="barcode-input" placeholder="Scan or enter barcode..." onkeypress="handleBarcode(event)">
            <button onclick="scanBarcode()"><i class="fas fa-barcode"></i> Scan</button>
        </div>

        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Search products by name or model..." onkeyup="searchProducts()">
        </div>

        <div class="products-grid" id="products-grid">
            @forelse($products as $product)
                @php
                    $stock = $product->inventory?->Quantity ?? 0;
                    $stockClass = $stock <= 0 ? 'out-of-stock' : ($stock <= 10 ? 'low' : '');
                    $stockText = $stock <= 0 ? 'Out of Stock' : ($stock <= 10 ? 'Low Stock: ' . $stock : 'Stock: ' . $stock);
                @endphp
                <div class="product-card {{ $stock <= 0 ? 'out-of-stock' : '' }}"
                     onclick="{{ $stock > 0 ? 'addToCart(' . $product->ProductID . ', ' . json_encode($product->ProductName) . ', ' . $product->Price . ', ' . $stock . ')' : '' }}">
                    <h3>{{ $product->ProductName }}</h3>
                    <p>{{ $product->Model }}</p>
                    <p class="stock {{ $stockClass }}">{{ $stockText }}</p>
                    <div class="price">₱{{ number_format($product->Price, 2) }}</div>
                </div>
            @empty
                <div class="empty-cart" style="grid-column: 1/-1;">
                    <i class="fas fa-box-open"></i>
                    <p>No products available</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="pos-cart">
        <div class="cart-header">
            <h2><i class="fas fa-shopping-cart"></i> Current Sale</h2>
            <button class="clear-cart-btn" onclick="clearCart()">
                <i class="fas fa-trash"></i> Clear
            </button>
        </div>

        <div class="form-group">
            <label>Customer Name (Optional)</label>
            <input type="text" id="customer-name" placeholder="Enter customer name...">
        </div>

        <div class="cart-items" id="cart-items">
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <p>Cart is empty</p>
                <p>Click products to add</p>
            </div>
        </div>

        <div class="cart-summary">
            <div class="summary-row">
                <span>Subtotal</span>
                <span id="subtotal">₱0.00</span>
            </div>
            <div class="summary-row">
                <span>VAT (12%)</span>
                <span id="vat">₱0.00</span>
            </div>
            <div class="summary-row" id="discount-summary-row" style="display:none;">
                <span>Discount <span id="applied-promo-badge" class="applied-promo-badge"></span></span>
                <span id="discount">-₱0.00</span>
            </div>
            <div class="summary-row total">
                <span>Total</span>
                <span id="total">₱0.00</span>
            </div>
        </div>

        <div class="form-group">
            <label>Payment Method</label>
            <div class="payment-dropdown" id="paymentDropdown">
                <button type="button" class="payment-dropdown-trigger" id="paymentDropdownTrigger" onclick="togglePaymentDropdown()">
                    <span class="payment-dropdown-selected" id="paymentDropdownSelected">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Cash</span>
                    </span>
                    <i class="fas fa-chevron-down payment-dropdown-chevron"></i>
                </button>
                <div class="payment-dropdown-menu" id="paymentDropdownMenu">
                    <div class="payment-dropdown-option selected" onclick="selectPayment(this, 'cash')">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Cash</span>
                    </div>
                    <div class="payment-dropdown-option" onclick="selectPayment(this, 'gcash')">
                        <i class="fas fa-mobile-alt"></i>
                        <span>GCash</span>
                    </div>
                    <div class="payment-dropdown-option" onclick="selectPayment(this, 'bank')">
                        <i class="fas fa-university"></i>
                        <span>Bank Transfer</span>
                    </div>
                    <div class="payment-dropdown-option" onclick="selectPayment(this, 'cheque')">
                        <i class="fas fa-money-check"></i>
                        <span>Cheque</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group" id="account-number-group" style="display: none;">
            <label id="account-label">Reference Number</label>
            <input type="text" id="account-number" placeholder="Enter reference number...">
        </div>

        <div class="payment-details-panel" id="cheque-details-group" style="display: none;">
            <div class="payment-details-header"><i class="fas fa-money-check"></i> Cheque Details</div>
            <div class="form-group">
                <label>Cheque Number</label>
                <input type="text" id="cheque-number" placeholder="e.g., 0001234">
            </div>
            <div class="form-group">
                <label>Bank Name</label>
                <input type="text" id="cheque-bank-name" placeholder="e.g., BDO">
            </div>
            <div class="form-group">
                <label>Account Name / Issuer</label>
                <input type="text" id="cheque-account-name" placeholder="Name on the cheque">
            </div>
            <div class="form-group">
                <label>Cheque Date</label>
                <input type="date" id="cheque-date">
            </div>
            <div class="form-group">
                <label>Remarks (optional)</label>
                <input type="text" id="cheque-remarks" placeholder="Optional notes">
            </div>
            <label class="verify-checkbox-label">
                <input type="checkbox" id="cheque-verified"> I have verified the cheque details.
            </label>
            <button type="button" class="btn-verify-payment" id="confirm-cheque-btn" onclick="confirmChequeDetails()">
                Confirm Cheque Payment
            </button>
            <div class="payment-verified-badge" id="cheque-verified-badge" style="display:none;">
                <i class="fas fa-check-circle"></i> Cheque details confirmed
            </div>
        </div>

        <div class="payment-details-panel" id="bank-details-group" style="display: none;">
            <div class="payment-details-header"><i class="fas fa-university"></i> Bank Transfer Details</div>
            <div class="form-group">
                <label>Bank / Financial Institution</label>
                <input type="text" id="bank-name" placeholder="e.g., BPI">
            </div>
            <div class="form-group">
                <label>Reference Number</label>
                <input type="text" id="bank-reference" placeholder="Transaction reference number">
            </div>
            <div class="form-group">
                <label>Account Name / Sender</label>
                <input type="text" id="bank-account-name" placeholder="Sender's account name">
            </div>
            <div class="form-group">
                <label>Transfer Date</label>
                <input type="date" id="bank-date">
            </div>
            <div class="form-group">
                <label>Transfer Time</label>
                <input type="time" id="bank-time">
            </div>
            <div class="form-group">
                <label>Remarks (optional)</label>
                <input type="text" id="bank-remarks" placeholder="Optional notes">
            </div>
            <label class="verify-checkbox-label">
                <input type="checkbox" id="bank-verified"> I have verified the bank transfer details.
            </label>
            <button type="button" class="btn-verify-payment" id="confirm-bank-btn" onclick="confirmBankTransferDetails()">
                Confirm Bank Transfer
            </button>
            <div class="payment-verified-badge" id="bank-verified-badge" style="display:none;">
                <i class="fas fa-check-circle"></i> Bank transfer details confirmed
            </div>
        </div>

        <div class="form-group">
            <label>Payment Amount</label>
            <input type="text" id="payment-amount" placeholder="0.00" oninput="calculateChange()">
        </div>

        <div class="form-group">
            <label>Change</label>
            <div class="change-display" id="change-amount">₱0.00</div>
        </div>

        <button class="btn-checkout" onclick="processCheckout()" id="checkout-btn" disabled>
            <i class="fas fa-check-circle"></i> Complete Sale
        </button>
    </div>
</div>

{{-- Fallback receipt viewer — shown automatically in-page whenever the
     preferred pop-up receipt window can't open (browser popup blocker,
     an extension, or a policy that blocks window.open() entirely). Every
     sale still gets its receipt displayed immediately either way; this
     path just doesn't depend on popups being allowed at all. --}}
<div id="receiptFallbackOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:#1a1d2d; border-radius:16px; width:min(420px, 92vw); max-height:90vh; display:flex; flex-direction:column; overflow:hidden;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid rgba(148,163,184,0.15);">
            <strong style="color:#f8fafc;"><i class="fas fa-receipt"></i> Sale Complete — Receipt</strong>
        </div>
        <iframe id="receiptFallbackFrame" style="border:0; width:100%; flex:1; min-height:420px; background:#fff;"></iframe>
        <div style="display:flex; gap:10px; padding:14px 20px; border-top:1px solid rgba(148,163,184,0.15);">
            <button type="button" class="btn-checkout" style="flex:1;" onclick="printReceiptFallback()">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <button type="button" class="clear-cart-btn" style="flex:0 0 auto;" onclick="closeReceiptFallbackAndReset()">
                <i class="fas fa-check"></i> New Sale
            </button>
        </div>
    </div>
</div>

<script>
    window.attachMoneyInput(document.getElementById('payment-amount'));

    // Live clock — ticks every second so the header date/time never goes
    // stale while the POS panel is left open, without needing a reload.
    (function () {
        const dateEl = document.getElementById('posCurrentDate');
        const timeEl = document.getElementById('posCurrentTime');
        if (!dateEl && !timeEl) return;

        function tick() {
            const now = new Date();
            if (dateEl) {
                dateEl.textContent = now.toLocaleDateString('en-US', {
                    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                });
            }
            if (timeEl) {
                timeEl.textContent = now.toLocaleTimeString('en-US', {
                    hour: '2-digit', minute: '2-digit', hour12: true,
                });
            }
        }

        tick();
        setInterval(tick, 1000);
    })();

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ProductID => { discount_id, code, name, rate } for every product that
    // currently has an active, applicable promo — built server-side
    // (CashierAuthController::pos()) so the cart only ever shows "Apply
    // Promo" where one genuinely exists, instead of unconditionally on
    // every item and letting the cashier discover eligibility by guessing
    // a code. JSON-encodes object keys as strings, so items are looked up
    // by String(item.id) below.
    const PRODUCT_PROMO_MAP = @json($productPromoMap);

    let cart = [];
    // At most one promo applies per checkout, but it can be tied to several
    // products — { discountId, productIds: [...], code, name, rate }. Every
    // cart line whose product id is in productIds gets discounted.
    let appliedPromo = null;
    let selectedPaymentMethod = 'cash';
    let currentTotal = 0;
    // Cheque/Bank Transfer require the cashier to explicitly confirm their
    // payment details (a separate step from the final Complete Sale
    // confirm) before Complete Sale is allowed to proceed. Cash/GCash have
    // no such gate — this stays true/irrelevant for them.
    let paymentDetailsConfirmed = false;

    function handleBarcode(event) {
        if (event.key === 'Enter') {
            scanBarcode();
        }
    }

    function scanBarcode() {
        const barcode = document.getElementById('barcode-input').value.trim();
        if (barcode) {
            fetch(`/api/products/barcode/${barcode}`)
                .then(response => response.json())
                .then(data => {
                    if (data.product) {
                        const stock = data.product.inventory?.Quantity || 0;
                        if (stock > 0) {
                            addToCart(data.product.ProductID, data.product.ProductName, data.product.Price, stock);
                        } else {
                            toastWarning('Product out of stock!');
                        }
                    } else {
                        toastError('Product not found!');
                    }
                })
                .catch(() => {
                    toastError('Product not found!');
                });
            document.getElementById('barcode-input').value = '';
        }
    }

    function searchProducts() {
        const search = document.getElementById('search-input').value.toLowerCase();
        document.querySelectorAll('.product-card').forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(search) ? 'block' : 'none';
        });
    }

    function addToCart(id, name, price, stock) {
        const existingItem = cart.find(item => item.id === id);
        if (existingItem) {
            if (existingItem.qty < stock) {
                existingItem.qty++;
            } else {
                toastWarning('Maximum stock reached!');
                return;
            }
        } else {
            cart.push({ id, name, price, qty: 1, stock });
        }
        renderCart();
    }

    function updateQty(id, change) {
        const item = cart.find(item => item.id === id);
        if (item) {
            const newQty = item.qty + change;
            if (newQty > 0 && newQty <= item.stock) {
                item.qty = newQty;
                renderCart();
            } else if (newQty <= 0) {
                removeFromCart(id);
            }
        }
    }

    // Typed directly into the quantity box — clamp to [1, stock] rather than
    // rejecting out-of-range input outright, so a typo like "500" against 20
    // in stock still lands somewhere useful instead of just bouncing back to
    // whatever the field held before.
    function setQty(id, rawValue) {
        const item = cart.find(item => item.id === id);
        if (!item) return;

        let qty = parseInt(rawValue, 10);
        if (isNaN(qty) || qty < 1) {
            qty = 1;
        } else if (qty > item.stock) {
            qty = item.stock;
            toastWarning('Only ' + item.stock + ' unit(s) of "' + item.name + '" in stock.');
        }

        item.qty = qty;
        renderCart();
    }

    function removeFromCart(id) {
        cart = cart.filter(item => item.id !== id);
        renderCart();
    }

    function clearCart() {
        if (cart.length === 0) return;

        window.confirmAction({
            title: 'Clear Cart',
            text: 'Are you sure you want to clear the cart?',
            icon: 'warning',
            confirmText: 'Clear',
            confirmColor: '#ef4444',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            cart = [];
            appliedPromo = null;
            renderCart();
            document.getElementById('customer-name').value = '';
        });
    }

    // If every one of the promo's assigned products has been removed from
    // the cart since it was applied, the promo no longer has anything to
    // discount — clear it up front so both the per-item badges and the
    // summary totals built below stay consistent within this same render
    // pass, rather than only catching it a render later. Removing just one
    // of several assigned products still in the cart leaves the promo
    // applied to whichever ones remain.
    function syncAppliedPromo() {
        if (appliedPromo && !cart.some((i) => appliedPromo.productIds.includes(i.id))) {
            appliedPromo = null;
        }
    }

    function renderCart() {
        syncAppliedPromo();
        const container = document.getElementById('cart-items');

        if (cart.length === 0) {
            container.innerHTML = `
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Cart is empty</p>
                    <p>Click products to add</p>
                </div>
            `;
            document.getElementById('checkout-btn').disabled = true;
            updateTotals();
            return;
        }

        document.getElementById('checkout-btn').disabled = false;
        container.innerHTML = '';

        cart.forEach(item => {
            const itemTotal = item.price * item.qty;
            const isPromoItem = appliedPromo && appliedPromo.productIds.includes(item.id);
            let promoRowHtml;
            if (isPromoItem) {
                promoRowHtml = `
                    <div class="cart-item-promo-row">
                        <span class="promo-applied-chip">
                            <i class="fas fa-tag"></i> ${escapeHtml(appliedPromo.code)} (-${appliedPromo.rate}%)
                            <button type="button" onclick="removePromo()" title="Remove promo">&times;</button>
                        </span>
                    </div>
                `;
            } else if (!appliedPromo && PRODUCT_PROMO_MAP[item.id]) {
                promoRowHtml = `
                    <div class="cart-item-promo-row">
                        <button type="button" class="apply-promo-btn" onclick="applyPromoToItem(${item.id})">
                            <i class="fas fa-tag"></i> Apply Promo
                        </button>
                    </div>
                `;
            } else {
                // No active promo assigned to this product (or one is
                // already applied elsewhere in the cart) — nothing to show,
                // not even a disabled button.
                promoRowHtml = '';
            }

            container.innerHTML += `
                <div class="cart-item" style="flex-wrap: wrap;">
                    <div class="cart-item-info">
                        <h4>${escapeHtml(item.name)}</h4>
                        <p>${window.formatPeso(item.price)} each</p>
                    </div>
                    <div class="cart-item-qty">
                        <button class="qty-btn" data-item-id="${item.id}" data-step="-1" ${item.qty <= 1 ? 'disabled' : ''}>-</button>
                        <input type="number" class="qty-input" value="${item.qty}" min="1" max="${item.stock}"
                               onchange="setQty(${item.id}, this.value)"
                               onkeydown="if (event.key === 'Enter') this.blur();">
                        <button class="qty-btn" data-item-id="${item.id}" data-step="1" ${item.qty >= item.stock ? 'disabled' : ''}>+</button>
                    </div>
                    <div class="cart-item-price">
                        <div class="item-total">${window.formatPeso(itemTotal)}</div>
                        <button class="remove-btn" onclick="removeFromCart(${item.id})" title="Remove">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    ${promoRowHtml}
                </div>
            `;
        });

        updateTotals();
    }

    // The button only ever renders when PRODUCT_PROMO_MAP already names the
    // one promo this product is eligible for (see renderCart()), so this
    // confirms and applies that specific promo directly — no blind code
    // entry. The server call still re-validates everything (active,
    // not expired, product actually assigned) from the DiscountID/code
    // alone before trusting anything back from it.
    function applyPromoToItem(productId) {
        const item = cart.find((i) => i.id === productId);
        const promo = PRODUCT_PROMO_MAP[productId];
        if (!item || !promo) return;

        window.confirmAction({
            title: 'Apply Promo',
            text: `Apply "${promo.name}" (${promo.code}, -${promo.rate}%) to "${item.name}"?`,
            confirmText: 'Apply',
            confirmColor: '#10b981',
        }).then((result) => {
            if (!result.isConfirmed) return;

            fetch('{{ route('cashier.pos.apply-promo') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ promo_code: promo.code, product_id: productId }),
            })
                .then((r) => r.json().then((data) => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok || !data.success) {
                        toastError(data.message || 'Unable to apply this promo.', 'Invalid Promo');
                        return;
                    }

                    appliedPromo = {
                        discountId: data.discount_id,
                        productIds: data.applicable_product_ids,
                        code: data.promo_code,
                        name: data.promo_name,
                        rate: data.discount_rate,
                    };
                    renderCart();
                    toastSuccess(`Promo "${data.promo_code}" applied to ${item.name}.`);
                })
                .catch(() => toastError('Error applying promo code. Please try again.'));
        });
    }

    function removePromo() {
        appliedPromo = null;
        renderCart();
    }

    // Holding down a qty +/- button repeats it instead of requiring one tap
    // per unit. Delegated on the (stable) cart container rather than bound
    // per-button, because renderCart() tears down and rebuilds every button
    // on each quantity change — a listener on the button itself would be
    // destroyed mid-press. The eventual "click" after a long-press release
    // is swallowed so the button doesn't apply one extra step on top of
    // whatever the repeat loop already did.
    (function setupQtyLongPress() {
        const container = document.getElementById('cart-items');
        const LONG_PRESS_DELAY = 450; // ms held before repeating starts
        const REPEAT_INTERVAL = 120;  // ms between repeats while held

        let pressTimer = null;
        let repeatTimer = null;
        let longPressActive = false;

        function qtyBtnFrom(target) {
            const btn = target.closest && target.closest('.qty-btn');
            return (btn && container.contains(btn) && !btn.disabled) ? btn : null;
        }

        function applyStep(id, delta) {
            const item = cart.find(i => i.id === id);
            if (!item) return false;
            const newQty = item.qty + delta;
            if (newQty < 1 || newQty > item.stock) return false;
            updateQty(id, delta);
            return true;
        }

        function stopPress() {
            clearTimeout(pressTimer);
            clearInterval(repeatTimer);
            pressTimer = null;
            repeatTimer = null;
        }

        function startPress(btn) {
            const id = parseInt(btn.dataset.itemId, 10);
            const delta = parseInt(btn.dataset.step, 10);
            longPressActive = false;

            pressTimer = setTimeout(() => {
                longPressActive = true;
                if (!applyStep(id, delta)) { stopPress(); return; }
                repeatTimer = setInterval(() => {
                    if (!applyStep(id, delta)) stopPress();
                }, REPEAT_INTERVAL);
            }, LONG_PRESS_DELAY);
        }

        container.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            const btn = qtyBtnFrom(e.target);
            if (btn) startPress(btn);
        });

        container.addEventListener('touchstart', function (e) {
            const btn = qtyBtnFrom(e.target);
            if (btn) startPress(btn);
        }, { passive: true });

        container.addEventListener('click', function (e) {
            const btn = qtyBtnFrom(e.target);
            if (!btn) return;
            if (longPressActive) {
                // Repeat loop already stepped this during the hold — the
                // trailing click from mouseup/touchend must not step again.
                longPressActive = false;
                return;
            }
            applyStep(parseInt(btn.dataset.itemId, 10), parseInt(btn.dataset.step, 10));
        });

        container.addEventListener('contextmenu', function (e) {
            if (qtyBtnFrom(e.target)) e.preventDefault();
        });

        ['mouseup', 'mouseleave', 'touchend', 'touchcancel'].forEach(function (evt) {
            document.addEventListener(evt, stopPress);
        });
    })();

    // Rounds to the nearest cent — matches the server-side computation
    // exactly, so a payment equal to what's displayed here is never rejected
    // by the backend's own sufficiency check due to residual floating-point
    // error further down the same formula.
    function roundMoney(value) {
        return Math.round((value + Number.EPSILON) * 100) / 100;
    }

    function updateTotals() {
        const subtotal = roundMoney(cart.reduce((sum, item) => sum + (item.price * item.qty), 0));

        // A promo only ever discounts the lines for its assigned products
        // that are actually in the cart, never the whole cart — matches how
        // processSale() computes it server-side.
        let discountAmount = 0;
        if (appliedPromo) {
            const promoLinesSubtotal = cart
                .filter((i) => appliedPromo.productIds.includes(i.id))
                .reduce((sum, i) => sum + (i.price * i.qty), 0);
            discountAmount = roundMoney(promoLinesSubtotal * (appliedPromo.rate / 100));
        }

        const vatAmount = roundMoney((subtotal - discountAmount) * 0.12);
        currentTotal = roundMoney(subtotal - discountAmount + vatAmount);

        document.getElementById('subtotal').textContent = window.formatPeso(subtotal);
        document.getElementById('vat').textContent = window.formatPeso(vatAmount);
        document.getElementById('discount').textContent = '-' + window.formatPeso(discountAmount);
        document.getElementById('discount-summary-row').style.display = appliedPromo ? 'flex' : 'none';
        document.getElementById('applied-promo-badge').textContent = appliedPromo ? `(${appliedPromo.code})` : '';
        document.getElementById('total').textContent = window.formatPeso(currentTotal);

        calculateChange();
    }

    function togglePaymentDropdown() {
        document.getElementById('paymentDropdown').classList.toggle('open');
    }

    function selectPayment(element, method) {
        document.querySelectorAll('.payment-dropdown-option').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        selectedPaymentMethod = method;

        // Mirror the chosen option's icon + label onto the closed trigger.
        document.getElementById('paymentDropdownSelected').innerHTML = element.innerHTML;
        document.getElementById('paymentDropdown').classList.remove('open');

        document.getElementById('account-number-group').style.display = method === 'gcash' ? 'block' : 'none';
        document.getElementById('cheque-details-group').style.display = method === 'cheque' ? 'block' : 'none';
        document.getElementById('bank-details-group').style.display = method === 'bank' ? 'block' : 'none';

        // Switching methods always invalidates any prior "details confirmed"
        // state — the fields it referred to are no longer the active ones.
        resetPaymentDetailsConfirmation();

        const today = new Date().toISOString().slice(0, 10);
        if (method === 'cheque' && !document.getElementById('cheque-date').value) {
            document.getElementById('cheque-date').value = today;
        }
        if (method === 'bank' && !document.getElementById('bank-date').value) {
            document.getElementById('bank-date').value = today;
        }
    }

    function resetPaymentDetailsConfirmation() {
        paymentDetailsConfirmed = false;
        ['cheque-details-group', 'bank-details-group'].forEach(function (panelId) {
            const panel = document.getElementById(panelId);
            panel.classList.remove('confirmed');
            panel.querySelectorAll('input').forEach(function (input) { input.disabled = false; });
        });
        document.getElementById('cheque-verified-badge').style.display = 'none';
        document.getElementById('bank-verified-badge').style.display = 'none';
    }

    function confirmChequeDetails() {
        const fields = {
            'Cheque Number': document.getElementById('cheque-number').value.trim(),
            'Bank Name': document.getElementById('cheque-bank-name').value.trim(),
            'Account Name / Issuer': document.getElementById('cheque-account-name').value.trim(),
            'Cheque Date': document.getElementById('cheque-date').value,
        };
        const missing = Object.keys(fields).filter(function (label) { return !fields[label]; });
        if (missing.length) {
            toastWarning('Please fill in: ' + missing.join(', '));
            return;
        }
        if (!document.getElementById('cheque-verified').checked) {
            toastWarning('Please check "I have verified the cheque details" before confirming.');
            return;
        }

        window.confirmAction({
            title: 'Confirm Cheque Payment',
            text: 'Please verify the cheque details before recording this payment.',
            icon: 'question',
            confirmText: 'Confirm Payment',
            cancelText: 'Cancel',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            paymentDetailsConfirmed = true;
            const panel = document.getElementById('cheque-details-group');
            panel.classList.add('confirmed');
            panel.querySelectorAll('input').forEach(function (input) { input.disabled = true; });
            document.getElementById('cheque-verified-badge').style.display = 'flex';
        });
    }

    function confirmBankTransferDetails() {
        const fields = {
            'Bank / Financial Institution': document.getElementById('bank-name').value.trim(),
            'Reference Number': document.getElementById('bank-reference').value.trim(),
            'Account Name / Sender': document.getElementById('bank-account-name').value.trim(),
            'Transfer Date': document.getElementById('bank-date').value,
            'Transfer Time': document.getElementById('bank-time').value,
        };
        const missing = Object.keys(fields).filter(function (label) { return !fields[label]; });
        if (missing.length) {
            toastWarning('Please fill in: ' + missing.join(', '));
            return;
        }
        if (!document.getElementById('bank-verified').checked) {
            toastWarning('Please check "I have verified the bank transfer details" before confirming.');
            return;
        }

        window.confirmAction({
            title: 'Confirm Bank Transfer',
            text: 'Please verify the transfer details before recording this payment.',
            icon: 'question',
            confirmText: 'Confirm Payment',
            cancelText: 'Cancel',
        }).then(function (result) {
            if (!result.isConfirmed) return;
            paymentDetailsConfirmed = true;
            const panel = document.getElementById('bank-details-group');
            panel.classList.add('confirmed');
            panel.querySelectorAll('input').forEach(function (input) { input.disabled = true; });
            document.getElementById('bank-verified-badge').style.display = 'flex';
        });
    }

    document.addEventListener('click', function (e) {
        const dropdown = document.getElementById('paymentDropdown');
        if (dropdown && !dropdown.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    function calculateChange() {
        const payment = window.parseMoney(document.getElementById('payment-amount').value);
        const change = Math.max(0, payment - currentTotal);
        document.getElementById('change-amount').textContent = window.formatPeso(change);
    }

    function showReceiptFallback(receiptUrl) {
        document.getElementById('receiptFallbackFrame').src = receiptUrl;
        document.getElementById('receiptFallbackOverlay').style.display = 'flex';
    }

    function printReceiptFallback() {
        const frame = document.getElementById('receiptFallbackFrame');
        if (frame.contentWindow) frame.contentWindow.print();
    }

    function closeReceiptFallbackAndReset() {
        document.getElementById('receiptFallbackOverlay').style.display = 'none';
        document.getElementById('receiptFallbackFrame').src = 'about:blank';
        window.location.reload();
    }

    function processCheckout() {
        const checkoutBtn = document.getElementById('checkout-btn');

        // Guard against double-submit (double-click / double-tap): the disabled
        // button catches the common case client-side; the server also holds a
        // per-cashier Cache::lock for the rare cases this can't (a second
        // browser tab, a fast double-tap before disable() takes effect).
        if (checkoutBtn.disabled) {
            return;
        }

        if (cart.length === 0) {
            toastWarning('Please add products to the cart!');
            return;
        }

        // Sufficiency applies to every payment method, not just cash — the
        // Payment Amount field is always shown and always recorded as what
        // was actually collected, so an under-amount GCash/bank/cheque entry
        // is just as wrong as an under-amount cash one.
        const payment = window.parseMoney(document.getElementById('payment-amount').value);
        if (!payment || payment < currentTotal) {
            toastWarning('Please enter sufficient payment amount!');
            return;
        }

        // Cheque/Bank Transfer must go through their own "Confirm Cheque
        // Payment"/"Confirm Bank Transfer" step first — Cash/GCash have no
        // such gate and proceed straight through, unchanged.
        if ((selectedPaymentMethod === 'cheque' || selectedPaymentMethod === 'bank') && !paymentDetailsConfirmed) {
            const label = selectedPaymentMethod === 'cheque' ? 'cheque' : 'bank transfer';
            toastWarning('Please confirm the ' + label + ' details first.');
            return;
        }

        const data = {
            _token: '{{ csrf_token() }}',
            customer_name: document.getElementById('customer-name').value,
            items: cart,
            discount_id: appliedPromo ? appliedPromo.discountId : null,
            payment_method: selectedPaymentMethod,
            payment_amount: window.parseMoney(document.getElementById('payment-amount').value),
            reference_number: null,
            bank_name: null,
            account_name: null,
            payment_date: null,
            payment_time: null,
            remarks: null,
        };

        if (selectedPaymentMethod === 'gcash') {
            data.reference_number = document.getElementById('account-number').value.trim() || null;
        } else if (selectedPaymentMethod === 'cheque') {
            data.reference_number = document.getElementById('cheque-number').value.trim();
            data.bank_name = document.getElementById('cheque-bank-name').value.trim();
            data.account_name = document.getElementById('cheque-account-name').value.trim();
            data.payment_date = document.getElementById('cheque-date').value;
            data.remarks = document.getElementById('cheque-remarks').value.trim() || null;
        } else if (selectedPaymentMethod === 'bank') {
            data.reference_number = document.getElementById('bank-reference').value.trim();
            data.bank_name = document.getElementById('bank-name').value.trim();
            data.account_name = document.getElementById('bank-account-name').value.trim();
            data.payment_date = document.getElementById('bank-date').value;
            data.payment_time = document.getElementById('bank-time').value;
            data.remarks = document.getElementById('bank-remarks').value.trim() || null;
        }

        checkoutBtn.disabled = true;

        window.confirmAction({
            title: 'Complete Sale',
            text: 'Charge ' + window.formatPeso(currentTotal) + ' and complete this transaction?',
            icon: 'question',
            confirmText: 'Yes, Complete Sale',
            cancelText: 'No, Go Back',
            confirmColor: '#10b981',
        }).then(function (result) {
            if (!result.isConfirmed) {
                checkoutBtn.disabled = false;
                return;
            }

            // Only now — after the cashier has actually clicked "Complete
            // Sale" in the confirm dialog, not when they first clicked the
            // checkout button — does a receipt window get opened. It's
            // still opened synchronously inside this click's own handler
            // (SweetAlert2 resolves the confirm button's click synchronously,
            // before any network round-trip), which is what keeps it counting
            // as a genuine user gesture for the popup blocker; waiting until
            // after the fetch() response below would arrive too late for
            // that and get silently blocked. If the popup still doesn't open
            // (blocked by a stricter blocker or extension), the receipt
            // falls back to the in-page viewer once the sale finishes.
            const receiptWindow = window.open('', '_blank', 'width=400,height=600');
            if (receiptWindow) {
                // No <head> tag here on purpose — some local dev tooling scans
                // outgoing HTML for literal "<head>" text to inject a logger
                // script, which doesn't know this one is just JS string content
                // rather than a real tag, and ends up corrupting this script
                // block. A bare <body> is all a placeholder needs anyway.
                receiptWindow.document.write('<body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;color:#666;">Processing receipt&hellip;</body>');
                receiptWindow.document.title = 'Receipt';
            }

            fetch('{{ route("cashier.process-sale") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const receiptUrl = '{{ url('cashier/receipt') }}/' + data.receipt_number;

                    if (receiptWindow && !receiptWindow.closed) {
                        receiptWindow.location.href = receiptUrl;
                        receiptWindow.focus();

                        // Reload so the product grid reflects the stock the sale
                        // just deducted — it's rendered server-side once at page
                        // load, so without this the displayed "Stock: X" (and the
                        // cart's own stock-limit checks) stay stale until a manual
                        // refresh. The receipt is in its own separate popup
                        // window, so reloading this tab doesn't affect it.
                        window.location.reload();
                    } else {
                        // Popup was blocked (or got closed) — the sale still went
                        // through, so the receipt must still appear automatically
                        // without depending on popup permission. Shown in-page
                        // instead; the POS only resets once the cashier
                        // acknowledges it via "New Sale", per the same "receipt
                        // first, reset after" order the popup path already
                        // follows in practice.
                        showReceiptFallback(receiptUrl);
                    }
                } else {
                    if (receiptWindow && !receiptWindow.closed) receiptWindow.close();
                    toastError(data.message, 'Error');
                    checkoutBtn.disabled = false;
                    // Re-open the cheque/bank fields for editing — a failure
                    // here (e.g. a duplicate reference number) usually means
                    // something in those details needs to change before
                    // retrying, and they were locked read-only on confirm.
                    if (selectedPaymentMethod === 'cheque' || selectedPaymentMethod === 'bank') {
                        resetPaymentDetailsConfirmation();
                    }
                }
            })
            .catch(error => {
                if (receiptWindow && !receiptWindow.closed) receiptWindow.close();
                toastError(error.message, 'Error Processing Sale');
                checkoutBtn.disabled = false;
                if (selectedPaymentMethod === 'cheque' || selectedPaymentMethod === 'bank') {
                    resetPaymentDetailsConfirmation();
                }
            });
        });
    }
</script>
@endsection