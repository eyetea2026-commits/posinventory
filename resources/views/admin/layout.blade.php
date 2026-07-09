<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin - ' . config('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('Administrator/Dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('Administrator/AdminModules.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    {{-- Tailwind + Alpine.js power the sidebar component only; every other
         admin page keeps its existing plain-CSS styling untouched. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    <style>
        /* Sidebar + content are a flex row: the sidebar (fixed on mobile,
           sticky/h-screen on desktop) never grows with page content, and
           main-content fills the remaining width instead of using a
           margin-left offset. */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        .main-content {
            flex: 1 1 0%;
            min-width: 0;
        }
        /* Header controls: notifications, profile */
        .dashboard-header {
            flex-wrap: wrap;
            row-gap: 12px;
        }
        .header-controls {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            row-gap: 10px;
            gap: 14px;
            margin-left: auto;
            max-width: 100%;
        }
        .header-icon-btn {
            position: relative;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: none;
            background: rgba(148, 163, 184, 0.08);
            color: #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .header-icon-btn:hover {
            background: rgba(59, 130, 246, 0.15);
            color: #93c5fd;
        }
        .header-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
        .header-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 280px;
            background: #0F172A;
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 14px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45);
            z-index: 50;
            overflow: hidden;
        }
        .header-dropdown-header {
            padding: 14px 16px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        }
        .header-dropdown a.header-dropdown-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 16px;
            color: #e2e8f0;
            text-decoration: none;
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.06);
        }
        .header-dropdown a.header-dropdown-item:last-child { border-bottom: none; }
        .header-dropdown a.header-dropdown-item:hover { background: rgba(59, 130, 246, 0.1); }
        .header-dropdown-empty {
            padding: 20px 16px;
            text-align: center;
            color: #64748b;
            font-size: 0.85rem;
        }
        .header-profile-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            border: none;
            background: transparent;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 10px;
        }
        .header-profile-btn:hover { background: rgba(148, 163, 184, 0.08); }
        .header-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .header-profile-name {
            text-align: left;
            line-height: 1.2;
            max-width: 140px;
            overflow: hidden;
        }
        .header-profile-name strong {
            display: block;
            font-size: 0.85rem;
            color: #f8fafc;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .header-profile-name span {
            font-size: 0.72rem;
            color: #94a3b8;
            text-transform: capitalize;
        }
        .header-profile-dropdown form button {
            width: 100%;
            text-align: left;
            border: none;
            background: transparent;
            color: #f87171;
            padding: 12px 16px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .header-profile-dropdown form button:hover { background: rgba(239, 68, 68, 0.1); }
        @media (max-width: 420px) {
            .header-controls { gap: 8px; }
            .header-profile-name { display: none; }
        }
        /* SweetAlert2 Custom Styles - Smaller Dialogs */
        .swal2-popup {
            width: 28em !important;
            max-width: 90% !important;
            padding: 1.5em !important;
            border-radius: 16px !important;
        }

        .swal2-title {
            font-size: 1.25rem !important;
            margin-bottom: 0.5em !important;
        }

        .swal2-content {
            font-size: 0.9rem !important;
        }

        .swal2-actions {
            gap: 8px !important;
            margin-top: 1em !important;
        }

        .swal2-actions .swal2-styled {
            padding: 10px 20px !important;
            border-radius: 8px !important;
            font-size: 0.85rem !important;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(15, 23, 42, 0.5);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.3);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(59, 130, 246, 0.5);
        }
    </style>
</head>
<body x-data="{ sidebarOpen: false }">
    {{-- Mobile/tablet top bar: hamburger button to open the sidebar drawer --}}
    <div class="hidden items-center gap-3 border-b border-white/5 bg-[#0F172A] px-4 py-3 max-lg:flex">
        <button type="button" @click="sidebarOpen = true" class="text-gray-300 hover:text-white">
            <x-icon name="menu" class="h-6 w-6" />
            <span class="sr-only">Open sidebar</span>
        </button>
        <span class="text-sm font-semibold text-white">POS Inventory System</span>
    </div>

    {{-- Overlay behind the drawer on mobile/tablet --}}
    <div
        x-cloak
        x-show="sidebarOpen"
        x-transition.opacity
        @click="sidebarOpen = false"
        class="fixed inset-0 z-30 bg-black/50 lg:hidden"
    ></div>

    <div class="dashboard-container">
        <x-sidebar />

        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-title">
                    @hasSection('header')
                        @yield('header')
                    @else
                        <h1>Administrator Dashboard</h1>
                        <p>Manage your inventory and sales system</p>
                    @endif
                </div>
                @hasSection('header-actions')
                    <div class="header-actions">
                        @yield('header-actions')
                    </div>
                @endif

                <div class="header-controls" x-data="{ notifOpen: false, profileOpen: false }">
                    <div style="position: relative;" @click.outside="notifOpen = false">
                        <button type="button" class="header-icon-btn" @click="notifOpen = !notifOpen; profileOpen = false">
                            <i class="fas fa-bell"></i>
                            @php $__notifTotal = $headerPendingPurchaseOrders + $headerPendingReturns + $headerOutOfStockCount; @endphp
                            @if($__notifTotal > 0)
                                <span class="header-badge">{{ $__notifTotal > 99 ? '99+' : $__notifTotal }}</span>
                            @endif
                        </button>
                        <div class="header-dropdown" x-show="notifOpen" x-cloak>
                            <div class="header-dropdown-header">Notifications</div>
                            @if($__notifTotal === 0)
                                <div class="header-dropdown-empty">You're all caught up.</div>
                            @else
                                @if($headerOutOfStockCount > 0)
                                    <a href="{{ route('admin.inventory.index') }}" class="header-dropdown-item">
                                        <span><i class="fas fa-triangle-exclamation" style="color:#f87171;"></i> Out of stock products</span>
                                        <span class="badge badge-danger" style="background:rgba(239,68,68,.15);color:#f87171;border-radius:8px;padding:2px 8px;font-size:.75rem;">{{ $headerOutOfStockCount }}</span>
                                    </a>
                                @endif
                                @if($headerPendingPurchaseOrders > 0)
                                    <a href="{{ route('admin.purchase-orders.index') }}" class="header-dropdown-item">
                                        <span><i class="fas fa-cart-shopping" style="color:#fbbf24;"></i> Pending purchase orders</span>
                                        <span class="badge" style="background:rgba(251,191,36,.15);color:#fbbf24;border-radius:8px;padding:2px 8px;font-size:.75rem;">{{ $headerPendingPurchaseOrders }}</span>
                                    </a>
                                @endif
                                @if($headerPendingReturns > 0)
                                    <a href="{{ route('admin.sales-returns.index') }}" class="header-dropdown-item">
                                        <span><i class="fas fa-rotate-left" style="color:#60a5fa;"></i> Pending return approvals</span>
                                        <span class="badge" style="background:rgba(59,130,246,.15);color:#60a5fa;border-radius:8px;padding:2px 8px;font-size:.75rem;">{{ $headerPendingReturns }}</span>
                                    </a>
                                @endif
                            @endif
                        </div>
                    </div>

                    <div style="position: relative;" @click.outside="profileOpen = false">
                        <button type="button" class="header-profile-btn" @click="profileOpen = !profileOpen; notifOpen = false">
                            <span class="header-avatar">{{ auth()->user()->initials ?: 'A' }}</span>
                            <span class="header-profile-name">
                                <strong>{{ auth()->user()->name ?? 'Admin' }}</strong>
                                <span>{{ auth()->user()->role?->role_name ?? 'Administrator' }}</span>
                            </span>
                        </button>
                        <div class="header-dropdown header-profile-dropdown" x-show="profileOpen" x-cloak>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"><i class="fas fa-right-from-bracket"></i> Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <section class="dashboard-content">
                @yield('content')
            </section>
        </main>
    </div>

    <script>
        // Toast notifications
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Auto-show session messages as toasts
        @if(session('status'))
            showToast('{{ session('status') }}', 'success');
        @endif
        @if(session('error'))
            showToast('{{ session('error') }}', 'error');
        @endif
    </script>

    <style>
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 14px 20px;
            background: #1a1d2d;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transform: translateX(120%);
            transition: transform 0.3s ease;
            z-index: 9999;
            border-left: 4px solid #10b981;
        }
        .toast.show { transform: translateX(0); }
        .toast-success { border-color: #10b981; }
        .toast-success i { color: #10b981; }
        .toast-error { border-color: #ef4444; }
        .toast-error i { color: #ef4444; }
    </style>
    @stack('scripts')
</body>
</html>