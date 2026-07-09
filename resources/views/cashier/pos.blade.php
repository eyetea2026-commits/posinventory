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

    .product-type-tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .product-type-tab { padding: 10px 16px; background: #2d3748; border: none; color: #e2e8f0; border-radius: 8px; cursor: pointer; font-size: 0.9rem; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
    .product-type-tab:hover { background: #4a5568; }
    .product-type-tab.active { background: linear-gradient(135deg, #3b82f6, #10b981); }

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

    .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; flex: 1; overflow-y: auto; padding-right: 8px; }
    .products-grid::-webkit-scrollbar { width: 6px; }
    .products-grid::-webkit-scrollbar-track { background: #1a1d2d; }
    .products-grid::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 3px; }

    .product-card { background: #2d3748; border-radius: 12px; padding: 16px; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; }
    .product-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.3); border-color: #3b82f6; }
    .product-card.out-of-stock { opacity: 0.5; cursor: not-allowed; }
    .product-card h3 { margin: 0 0 8px; font-size: 0.95rem; color: #f8fafc; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .product-card p { margin: 0; color: #94a3b8; font-size: 0.8rem; }
    .product-card .stock { font-size: 0.75rem; color: #94a3b8; margin: 8px 0; }
    .product-card .stock.low { color: #f59e0b; }
    .product-card .stock.out { color: #ef4444; }
    .product-card .price { color: #60a5fa; font-size: 1.1rem; font-weight: bold; }

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
    .cart-item-price { text-align: right; min-width: 80px; }
    .cart-item-price .item-total { font-weight: bold; color: #60a5fa; font-size: 1rem; }
    .cart-item-price .item-price { font-size: 0.75rem; color: #94a3b8; }
    .remove-btn { background: none; border: none; color: #ef4444; cursor: pointer; padding: 4px; font-size: 1rem; margin-left: 8px; }
    .remove-btn:hover { color: #dc2626; }

    .cart-summary { background: #2d3748; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.95rem; }
    .summary-row.total { font-size: 1.4rem; font-weight: bold; border-top: 2px solid #4a5568; padding-top: 12px; margin-top: 12px; color: #10b981; }

    .form-group { margin-bottom: 14px; }
    .form-group label { display: block; margin-bottom: 6px; color: #94a3b8; font-size: 0.9rem; }
    .form-group input, .form-group select { width: 100%; padding: 12px; background: #2d3748; border: 1px solid #4a5568; color: #e2e8f0; border-radius: 8px; font-size: 0.95rem; }
    .form-group input:focus { outline: none; border-color: #3b82f6; }
    .change-display { padding: 12px; background: #1a1d2d; border: 1px solid #4a5568; color: #34d399; border-radius: 8px; font-size: 0.95rem; font-weight: 600; }

    .payment-methods { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 14px; }
    .payment-method { padding: 8px 10px; background: #2d3748; border: 2px solid #4a5568; border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.2s; display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 8px; }
    .payment-method:hover { border-color: #60a5fa; }
    .payment-method.selected { border-color: #3b82f6; background: rgba(59, 130, 246, 0.15); }
    .payment-method i { font-size: 1rem; }
    .payment-method span { font-size: 0.8rem; }

    .btn-checkout { width: 100%; padding: 16px; background: linear-gradient(135deg, #10b981, #059669); border: none; color: white; font-size: 1.1rem; font-weight: bold; border-radius: 12px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px; }
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
        .products-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
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
        <i class="fas fa-user"></i>
        <span>Cashier:</span>
        <strong>{{ auth()->user()->name }}</strong>
    </div>
</div>

<div class="pos-grid">
    <div class="pos-products">
        <div class="product-type-tabs">
            <button class="product-type-tab active" onclick="setProductType('individual')">
                <i class="fas fa-box"></i> Individual
            </button>
            <button class="product-type-tab" onclick="setProductType('customized')">
                <i class="fas fa-tools"></i> Customized
            </button>
            <button class="product-type-tab" onclick="setProductType('package')">
                <i class="fas fa-gift"></i> Package
            </button>
        </div>

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
            <label>Apply Discount (%)</label>
            <input type="number" id="discount-rate" placeholder="0" min="0" max="100" value="0" onchange="updateTotals()">
        </div>

        <div class="form-group">
            <label>Payment Method</label>
            <div class="payment-methods">
                <div class="payment-method selected" onclick="selectPayment(this, 'cash')">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Cash</span>
                </div>
                <div class="payment-method" onclick="selectPayment(this, 'gcash')">
                    <i class="fas fa-mobile-alt"></i>
                    <span>GCash</span>
                </div>
                <div class="payment-method" onclick="selectPayment(this, 'bank')">
                    <i class="fas fa-university"></i>
                    <span>Bank</span>
                </div>
                <div class="payment-method" onclick="selectPayment(this, 'cheque')">
                    <i class="fas fa-money-check"></i>
                    <span>Cheque</span>
                </div>
            </div>
        </div>

        <div class="form-group" id="account-number-group" style="display: none;">
            <label id="account-label">Account Number</label>
            <input type="text" id="account-number" placeholder="Enter account number...">
        </div>

        <div class="form-group">
            <label>Payment Amount</label>
            <input type="number" id="payment-amount" placeholder="0.00" min="0" step="0.01" oninput="calculateChange()">
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

    let cart = [];
    let currentProductType = 'individual';
    let selectedPaymentMethod = 'cash';
    let currentTotal = 0;

    function setProductType(type) {
        currentProductType = type;
        document.querySelectorAll('.product-type-tab').forEach(tab => tab.classList.remove('active'));
        event.target.closest('.product-type-tab').classList.add('active');
    }

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

    function removeFromCart(id) {
        cart = cart.filter(item => item.id !== id);
        renderCart();
    }

    function clearCart() {
        if (cart.length > 0 && confirm('Are you sure you want to clear the cart?')) {
            cart = [];
            renderCart();
            document.getElementById('customer-name').value = '';
            document.getElementById('discount-rate').value = '0';
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
                        <p>₱${item.price.toFixed(2)} each</p>
                    </div>
                    <div class="cart-item-qty">
                        <button class="qty-btn" onclick="updateQty(${item.id}, -1)" ${item.qty <= 1 ? 'disabled' : ''}>-</button>
                        <span>${item.qty}</span>
                        <button class="qty-btn" onclick="updateQty(${item.id}, 1)" ${item.qty >= item.stock ? 'disabled' : ''}>+</button>
                    </div>
                    <div class="cart-item-price">
                        <div class="item-total">₱${itemTotal.toFixed(2)}</div>
                        <button class="remove-btn" onclick="removeFromCart(${item.id})" title="Remove">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        updateTotals();
    }

    function updateTotals() {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const discountRate = parseFloat(document.getElementById('discount-rate').value) || 0;
        const discountAmount = subtotal * (discountRate / 100);
        const vatAmount = (subtotal - discountAmount) * 0.12;
        currentTotal = subtotal - discountAmount + vatAmount;

        document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
        document.getElementById('vat').textContent = '₱' + vatAmount.toFixed(2);
        document.getElementById('discount').textContent = '₱' + discountAmount.toFixed(2);
        document.getElementById('total').textContent = '₱' + currentTotal.toFixed(2);

        calculateChange();
    }

    function selectPayment(element, method) {
        document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        selectedPaymentMethod = method;

        const accountGroup = document.getElementById('account-number-group');
        const accountLabel = document.getElementById('account-label');

        if (method === 'cash') {
            accountGroup.style.display = 'none';
        } else {
            accountGroup.style.display = 'block';
            accountLabel.textContent = method === 'gcash' ? 'GCash Number' :
                method === 'bank' ? 'Bank Account Number' : 'Cheque Number';
        }
    }

    function calculateChange() {
        const payment = parseFloat(document.getElementById('payment-amount').value) || 0;
        const change = Math.max(0, payment - currentTotal);
        document.getElementById('change-amount').textContent = '₱' + change.toFixed(2);
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

        if (selectedPaymentMethod === 'cash') {
            const payment = parseFloat(document.getElementById('payment-amount').value);
            if (!payment || payment < currentTotal) {
                alert('Please enter sufficient payment amount!');
                return;
            }
        }

        const data = {
            _token: '{{ csrf_token() }}',
            customer_name: document.getElementById('customer-name').value,
            items: cart,
            discount_rate: document.getElementById('discount-rate').value || 0,
            payment_method: selectedPaymentMethod,
            account_number: document.getElementById('account-number').value,
            payment_amount: document.getElementById('payment-amount').value,
        };

        checkoutBtn.disabled = true;

        fetch('{{ route("cashier.process-sale") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message with receipt option (REQ102)
                const receiptUrl = '/cashier/receipt/' + data.receipt_number;
                const printWindow = window.open(receiptUrl, '_blank', 'width=400,height=600');
                if (printWindow) {
                    printWindow.focus();
                } else {
                    alert('Sale completed successfully!\nReceipt: ' + data.receipt_number + '\n\nPlease allow popups to print receipts.');
                }

                // Reload so the product grid reflects the stock the sale just
                // deducted — it's rendered server-side once at page load, so
                // without this the displayed "Stock: X" (and the cart's own
                // stock-limit checks) stay stale until a manual refresh.
                setTimeout(() => window.location.reload(), 300);
            } else {
                alert('Error: ' + data.message);
                checkoutBtn.disabled = false;
            }
        })
        .catch(error => {
            alert('Error processing sale: ' + error.message);
            checkoutBtn.disabled = false;
        });
    }
</script>
@endsection