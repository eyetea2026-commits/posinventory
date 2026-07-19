<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cashier - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('Administrator/Dashboard.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @include('partials.currency-js')
    <style>
        :root {
            --primary: #3b82f6;
            --success: #10b981;
            --danger: #ef4444;
            --bg-dark: #1a1d2d;
            --bg-hover: #2d3748;
            --border: #2d3748;
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
        }

        body { background: #050816; color: #e2e8f0; margin: 0; }
        .pos-container { display: flex; height: 100vh; }

        .pos-sidebar {
            width: 260px;
            background: rgba(10, 18, 35, 0.95);
            padding: 24px 16px;
            border-right: 1px solid rgba(148, 163, 184, 0.1);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            transition: left 0.3s ease;
        }

        .pos-main { flex: 1; padding: 24px; overflow-y: auto; }

        .pos-sidebar-brand { padding-bottom: 20px; border-bottom: 1px solid rgba(148, 163, 184, 0.1); margin-bottom: 20px; }
        .pos-sidebar-brand h2 { color: #60a5fa; margin: 0; font-size: 1.2rem; font-weight: 700; }
        .pos-sidebar-brand p { color: #94a3b8; margin: 5px 0 0; font-size: 0.85rem; }

        .pos-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 6px;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .pos-nav-item:hover {
            background: rgba(59, 130, 246, 0.1);
            color: #e2e8f0;
            transform: translateX(4px);
        }

        .pos-nav-item.active {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .pos-nav-item i { width: 20px; text-align: center; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }

        .w-full { width: 100%; }

        /* Mobile styles */
        .mobile-header {
            display: none;
            background: rgba(10, 18, 35, 0.95);
            padding: 16px 20px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            justify-content: space-between;
            align-items: center;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: #e2e8f0;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 8px;
        }

        .mobile-brand {
            font-weight: 700;
            color: #60a5fa;
            font-size: 1.1rem;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1049;
        }

        @media (max-width: 1024px) {
            .mobile-header {
                display: flex;
            }

            .pos-container {
                padding-top: 60px;
            }

            .pos-sidebar {
                position: fixed;
                left: -260px;
                top: 0;
                bottom: 0;
                z-index: 1050;
            }

            .pos-sidebar.active {
                left: 0;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .pos-main {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="mobile-header">
        <button class="menu-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <span class="mobile-brand">CCTV Express</span>
        @include('cashier.partials.notification-bell')
    </div>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="pos-container">
        <aside class="pos-sidebar">
            <div class="pos-sidebar-brand">
                <h2>CCTV Express</h2>
                <p>Cashier Portal</p>
            </div>

            <nav>
                <a href="{{ route('cashier.pos') }}" class="pos-nav-item {{ request()->routeIs('cashier.pos') ? 'active' : '' }}">
                    <i class="fas fa-cash-register"></i> POS Panel
                </a>
                <a href="{{ route('cashier.transactions') }}" class="pos-nav-item {{ request()->routeIs('cashier.transactions') ? 'active' : '' }}">
                    <i class="fas fa-history"></i> Transactions
                </a>
                <a href="{{ route('cashier.refunds') }}" class="pos-nav-item {{ request()->routeIs('cashier.refunds') ? 'active' : '' }}">
                    <i class="fas fa-undo-alt"></i> Refund Requests
                </a>
            </nav>

            <div style="margin-top: auto; padding-top: 20px; border-top: 1px solid rgba(148, 163, 184, 0.1);">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger w-full">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </aside>

        <main class="pos-main">
            @yield('content')
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.pos-sidebar').classList.toggle('active');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        }

        function toggleNotifDropdown(event) {
            event.stopPropagation();
            const dropdown = event.currentTarget.closest('.notif-bell-wrap').querySelector('.notif-dropdown');
            const wasActive = dropdown.classList.contains('active');
            document.querySelectorAll('.notif-dropdown.active').forEach(d => d.classList.remove('active'));
            if (!wasActive) {
                dropdown.classList.add('active');
            }
        }

        document.addEventListener('click', function(event) {
            if (!event.target.closest('.notif-bell-wrap')) {
                document.querySelectorAll('.notif-dropdown.active').forEach(d => d.classList.remove('active'));
            }
        });

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

        .notif-bell-wrap { position: relative; display: inline-flex; align-items: center; }
        .notif-bell-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.05rem;
            cursor: pointer;
            padding: 4px;
        }
        .notif-bell-btn:hover { color: #e2e8f0; }
        .notif-badge {
            position: absolute;
            top: -2px;
            right: -4px;
            background: #ef4444;
            color: #fff;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 10px;
            line-height: 1.3;
        }
        .notif-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 10px;
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
            background: #1a1d2d;
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            z-index: 1100;
            text-align: left;
        }
        .notif-dropdown.active { display: block; }
        .notif-dropdown-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid rgba(148, 163, 184, 0.1); font-weight: 600; }
        .notif-dropdown-header button { background: none; border: none; color: #94a3b8; font-size: 0.75rem; cursor: pointer; }
        .notif-item { display: block; padding: 10px 16px; text-decoration: none; color: #e2e8f0; border-bottom: 1px solid rgba(148, 163, 184, 0.08); font-size: 0.85rem; }
        .notif-item:hover { background: rgba(59, 130, 246, 0.1); }
        .notif-item small { color: #94a3b8; }
        .notif-empty { padding: 20px 16px; color: #94a3b8; font-size: 0.85rem; text-align: center; }
        .notif-view-all { display: block; text-align: center; padding: 10px; color: #94a3b8; font-size: 0.8rem; text-decoration: none; }
    </style>
</body>
</html>