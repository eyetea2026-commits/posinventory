<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cashier - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('Administrator/Dashboard.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    @include('partials.swal-helpers')
    @include('partials.currency-js')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
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

        /* Matches the Admin sidebar's visual spec exactly (see
           resources/views/components/sidebar.blade.php): #0F172A
           background, #273449 active state, 40px item height, 16px
           horizontal padding, 8px radius, Public Sans font -- kept as
           plain CSS here (rather than pulling in Tailwind/Alpine, which
           power only the Admin sidebar component) since the existing
           vanilla-JS mobile-drawer toggle below already works. */
        .pos-sidebar {
            width: 240px;
            background: #0F172A;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            transition: left 0.3s ease;
            font-family: 'Public Sans', Inter, sans-serif;
        }

        .pos-main { flex: 1; padding: 24px; overflow-y: auto; }

        .pos-sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            flex-shrink: 0;
        }
        .pos-sidebar-logo-box {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 8px;
            background: #fff;
            padding: 6px;
            flex-shrink: 0;
        }
        .pos-sidebar-logo { display: block; width: 100%; height: 100%; object-fit: contain; }
        .pos-sidebar-brand-text { min-width: 0; }
        .pos-brand-name { color: #fff; margin: 0; font-size: 1rem; font-weight: 700; line-height: 1.2; }
        .pos-brand-sub { color: #9ca3af; margin: 2px 0 0; font-size: 0.75rem; font-weight: 500; line-height: 1.2; }
        .pos-brand-role { color: #6b7280; margin: 2px 0 0; font-size: 0.7rem; line-height: 1.2; }

        .pos-sidebar nav {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 16px 12px;
            flex: 1;
            overflow-y: auto;
        }

        .pos-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            height: 40px;
            padding: 0 16px;
            color: #d1d5db;
            text-decoration: none;
            border-radius: 8px;
            transition: background-color 0.2s ease;
            font-weight: 400;
            font-size: 0.875rem;
        }

        .pos-nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .pos-nav-item.active {
            background: #273449;
            color: #fff;
            font-weight: 500;
        }

        .pos-nav-item i { width: 18px; text-align: center; color: #9ca3af; }
        .pos-nav-item.active i, .pos-nav-item:hover i { color: #e5e7eb; }

        .pos-sidebar-footer {
            flex-shrink: 0;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding: 12px;
        }
        .pos-logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            height: 40px;
            width: 100%;
            padding: 0 16px;
            border-radius: 8px;
            border: none;
            background: none;
            color: #d1d5db;
            font-size: 0.875rem;
            font-family: inherit;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .pos-logout-btn:hover { background: rgba(255, 255, 255, 0.05); }
        .pos-logout-btn i { width: 18px; text-align: center; color: #9ca3af; }

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
            background: #0F172A;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            justify-content: space-between;
            align-items: center;
            font-family: 'Public Sans', Inter, sans-serif;
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
            font-weight: 600;
            color: #fff;
            font-size: 0.875rem;
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
                left: -240px;
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
        <span class="mobile-brand">CCTV Express Solution Tacurong</span>
        @include('cashier.partials.notification-bell')
    </div>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="pos-container">
        <aside class="pos-sidebar">
            <div class="pos-sidebar-brand">
                <div class="pos-sidebar-logo-box">
                    <img src="{{ asset('Images/logo.png') }}" alt="CCTV Express Solution logo" class="pos-sidebar-logo">
                </div>
                <div class="pos-sidebar-brand-text">
                    <h2 class="pos-brand-name">CCTV Express</h2>
                    <p class="pos-brand-sub">Solution Tacurong</p>
                    <p class="pos-brand-role">Cashier Portal</p>
                </div>
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

            <div class="pos-sidebar-footer">
                <form method="POST" action="{{ route('cashier.logout') }}" class="js-confirm-submit" data-confirm-title="Log Out" data-confirm-text="Are you sure you want to log out?" data-confirm-icon="question" data-confirm-color="#10b981">
                    @csrf
                    <button type="submit" class="pos-logout-btn">
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
            flex-direction: column;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 10px;
            width: 320px;
            /* Shrinks to fit a narrow/resized viewport instead of
               overflowing past the screen edge and clipping its own
               content or the "Mark all read" / "View all" controls. */
            max-width: calc(100vw - 24px);
            background: rgba(26, 29, 45, 0.72);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            z-index: 1100;
            text-align: left;
        }
        .notif-dropdown.active { display: flex; }
        .notif-dropdown-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid rgba(148, 163, 184, 0.1); font-weight: 600; flex-shrink: 0; }
        .notif-dropdown-header button { background: none; border: none; color: #94a3b8; font-size: 0.75rem; cursor: pointer; }
        {{-- Only the notification list itself scrolls -- the header and
             "View all notifications" footer link stay pinned in view, and
             the list never grows the dropdown past a fixed height no
             matter how many unread notifications there are. --}}
        .notif-items { max-height: 320px; overflow-y: auto; }
        .notif-item { display: block; padding: 10px 16px; text-decoration: none; color: #e2e8f0; border-bottom: 1px solid rgba(148, 163, 184, 0.08); font-size: 0.85rem; }
        .notif-item:hover { background: rgba(59, 130, 246, 0.1); }
        .notif-item small { color: #94a3b8; }
        .notif-empty { padding: 20px 16px; color: #94a3b8; font-size: 0.85rem; text-align: center; }
        .notif-view-all { display: block; text-align: center; padding: 10px; color: #94a3b8; font-size: 0.8rem; text-decoration: none; flex-shrink: 0; }
    </style>
</body>
</html>