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
    .qty-btn { width: 28px; height: 28px; background: #4a5568; border: none; color: white; border-radius: 6px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
    .qty-btn:hover { background: #3b82f6; }
    .qty-btn:disabled { opacity: 0.5; cursor: not-allowed; }
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
    .remove-btn:hover { color: #dc2626; }

    .cart-summary { background: #2d3748; border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; flex-shrink: 0; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.82rem; }
    .summary-row.total { font-size: 1.05rem; font-weight: bold; border-top: 1px solid #4a5568; padding-top: 7px; margin-top: 7px; margin-bottom: 0; color: #10b981; }

    .form-group { margin-bottom: 9px; }
    .form-group label { display: block; margin-bottom: 3px; color: #94a3b8; font-size: 0.76rem; }
    .form-group input, .form-group select { width: 100%; padding: 8px 10px; background: #2d3748; border: 1px solid #4a5568; color: #e2e8f0; border-radius: 8px; font-size: 0.85rem; }
    .form-group input:focus { outline: none; border-color: #3b82f6; }
    .change-display { padding: 8px 10px; background: #1a1d2d; border: 1px solid #4a5568; color: #34d399; border-radius: 8px; font-size: 0.85rem; font-weight: 600; }

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
            <div class="summary-row">
                <span>Discount</span>
                <span id="discount">₱0.00</span>
            </div>
            <div class="summary-row total">
                <span>Total</span>
                <span id="total">₱0.00</span>
            </div>
        </div>

        <div class="form-group">
            <label>Apply Discount</label>
            <select id="discount-select" onchange="updateTotals()">
                <option value="" selected>No Discount</option>
                @foreach($discounts as $discount)
                    @php
                        $rateLabel = rtrim(rtrim(number_format($discount->DiscountRate, 2), '0'), '.') . '%';
                    @endphp
                    <option value="{{ $discount->DiscountID }}" data-rate="{{ $discount->DiscountRate }}">
                        {{ $discount->Name ? "{$rateLabel} — {$discount->Name}" : $rateLabel }}
                    </option>
                @endforeach
            </select>
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

    // Poll for discount changes so a policy an admin creates/updates/deletes
    // mid-shift shows up here without the cashier reloading the page.
    // Pauses while the tab is hidden to avoid pointless requests.
    function refreshDiscounts() {
        if (document.hidden) return;

        fetch('{{ route('cashier.pos.discounts') }}', { headers: { 'Accept': 'application/json' } })
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('discount-select');
                const previousValue = select.value;
                select.innerHTML = '';

                const noDiscountOption = document.createElement('option');
                noDiscountOption.value = '';
                noDiscountOption.textContent = 'No Discount';
                select.appendChild(noDiscountOption);

                (data.discounts || []).forEach(discount => {
                    const option = document.createElement('option');
                    option.value = discount.DiscountID;
                    option.dataset.rate = discount.DiscountRate;
                    const rate = parseFloat(discount.DiscountRate);
                    const rateLabel = (rate % 1 === 0 ? rate : rate.toFixed(2)) + '%';
                    option.textContent = discount.Name ? `${rateLabel} — ${discount.Name}` : rateLabel;
                    select.appendChild(option);
                });
                // Keep the cashier's current selection if it still exists;
                // otherwise fall back to "No Discount".
                const stillExists = Array.from(select.options).some(o => o.value === previousValue);
                select.value = stillExists ? previousValue : '';
                updateTotals();
            })
            .catch(() => {
                // Non-fatal — keep showing the last known discount list.
            });
    }
    setInterval(refreshDiscounts, 20000);

    let cart = [];
    let selectedPaymentMethod = 'cash';
    let currentTotal = 0;

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
                            alert('Product out of stock!');
                        }
                    } else {
                        alert('Product not found!');
                    }
                })
                .catch(() => {
                    alert('Product not found!');
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
                alert('Maximum stock reached!');
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
            alert('Only ' + item.stock + ' unit(s) of "' + item.name + '" in stock.');
        }

        item.qty = qty;
        renderCart();
    }

    function removeFromCart(id) {
        cart = cart.filter(item => item.id !== id);
        renderCart();
    }

    function clearCart() {
        if (cart.length > 0 && confirm('Are you sure you want to clear the cart?')) {
            cart = [];
            renderCart();
            document.getElementById('customer-name').value = '';
            document.getElementById('discount-select').value = '';
        }
    }

    function renderCart() {
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
            container.innerHTML += `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <h4>${item.name}</h4>
                        <p>${window.formatPeso(item.price)} each</p>
                    </div>
                    <div class="cart-item-qty">
                        <button class="qty-btn" onclick="updateQty(${item.id}, -1)" ${item.qty <= 1 ? 'disabled' : ''}>-</button>
                        <input type="number" class="qty-input" value="${item.qty}" min="1" max="${item.stock}"
                               onchange="setQty(${item.id}, this.value)"
                               onkeydown="if (event.key === 'Enter') this.blur();">
                        <button class="qty-btn" onclick="updateQty(${item.id}, 1)" ${item.qty >= item.stock ? 'disabled' : ''}>+</button>
                    </div>
                    <div class="cart-item-price">
                        <div class="item-total">${window.formatPeso(itemTotal)}</div>
                        <button class="remove-btn" onclick="removeFromCart(${item.id})" title="Remove">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        updateTotals();
    }

    // Rounds to the nearest cent — matches the server-side computation
    // exactly, so a payment equal to what's displayed here is never rejected
    // by the backend's own sufficiency check due to residual floating-point
    // error further down the same formula.
    function roundMoney(value) {
        return Math.round((value + Number.EPSILON) * 100) / 100;
    }

    function updateTotals() {
        const subtotal = roundMoney(cart.reduce((sum, item) => sum + (item.price * item.qty), 0));
        const discountSelect = document.getElementById('discount-select');
        const hasDiscount = discountSelect.value !== '';
        const selectedOption = discountSelect.options[discountSelect.selectedIndex];
        const discountRate = hasDiscount && selectedOption ? (parseFloat(selectedOption.dataset.rate) || 0) : 0;
        const discountAmount = roundMoney(subtotal * (discountRate / 100));
        const vatAmount = roundMoney((subtotal - discountAmount) * 0.12);
        currentTotal = roundMoney(subtotal - discountAmount + vatAmount);

        document.getElementById('subtotal').textContent = window.formatPeso(subtotal);
        document.getElementById('vat').textContent = window.formatPeso(vatAmount);
        document.getElementById('discount').textContent = window.formatPeso(discountAmount);
        document.getElementById('discount').closest('.summary-row').style.display = hasDiscount ? 'flex' : 'none';
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

        const accountGroup = document.getElementById('account-number-group');

        if (method === 'cash') {
            accountGroup.style.display = 'none';
        } else {
            accountGroup.style.display = 'block';
        }
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

    function processCheckout() {
        const checkoutBtn = document.getElementById('checkout-btn');

        // Guard against double-submit (double-click / double-tap): the sale
        // request has no server-side idempotency check, so two near-simultaneous
        // submits create two separate transactions and deduct stock twice.
        if (checkoutBtn.disabled) {
            return;
        }

        if (cart.length === 0) {
            alert('Please add products to the cart!');
            return;
        }

        // Sufficiency applies to every payment method, not just cash — the
        // Payment Amount field is always shown and always recorded as what
        // was actually collected, so an under-amount GCash/bank/cheque entry
        // is just as wrong as an under-amount cash one.
        const payment = window.parseMoney(document.getElementById('payment-amount').value);
        if (!payment || payment < currentTotal) {
            alert('Please enter sufficient payment amount!');
            return;
        }

        const data = {
            _token: '{{ csrf_token() }}',
            customer_name: document.getElementById('customer-name').value,
            items: cart,
            discount_id: document.getElementById('discount-select').value || null,
            payment_method: selectedPaymentMethod,
            account_number: document.getElementById('account-number').value,
            payment_amount: window.parseMoney(document.getElementById('payment-amount').value),
        };

        checkoutBtn.disabled = true;

        // window.open() only counts as a genuine user gesture (and so only
        // reliably bypasses popup blockers) when it's called synchronously
        // inside the click handler itself — not after an awaited fetch
        // response, which is what silently got blocked before. So the
        // window is opened here, immediately, with a placeholder page,
        // then redirected to the real receipt once the sale actually
        // succeeds. This is what makes the receipt genuinely pop up
        // automatically instead of needing a manual click.
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
                }

                // Reload so the product grid reflects the stock the sale just
                // deducted — it's rendered server-side once at page load, so
                // without this the displayed "Stock: X" (and the cart's own
                // stock-limit checks) stay stale until a manual refresh. The
                // receipt is in its own separate popup window, so reloading
                // this tab doesn't affect it.
                window.location.reload();
            } else {
                if (receiptWindow && !receiptWindow.closed) receiptWindow.close();
                alert('Error: ' + data.message);
                checkoutBtn.disabled = false;
            }
        })
        .catch(error => {
            if (receiptWindow && !receiptWindow.closed) receiptWindow.close();
            alert('Error processing sale: ' + error.message);
            checkoutBtn.disabled = false;
        });
    }
</script>
@endsection