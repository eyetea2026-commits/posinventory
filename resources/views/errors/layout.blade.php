{{-- Shared look for the styled error pages (404, 500). Self-contained on
     purpose — no app layout, database or signed-in user — so it can show
     even when those are what broke. Each page sets: title, code, heading,
     message, and optional extra buttons in "actions". --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - CCTV Express Solution Tacurong</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: radial-gradient(circle at top, #111a2e 0%, #0b0f19 60%);
            color: #e2e8f0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .error-card {
            width: 100%;
            max-width: 460px;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 20px;
            padding: 36px 28px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.45);
        }
        .error-logo {
            display: block;
            margin: 0 auto 18px;
            width: 72px;
            height: 72px;
            border-radius: 16px;
            background: #fff;
            object-fit: contain;
            padding: 6px;
        }
        .error-code {
            display: inline-block;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #fca5a5;
            background: rgba(239, 68, 68, 0.12);
            border-radius: 999px;
            padding: 4px 12px;
            margin-bottom: 12px;
        }
        h1 { margin: 0 0 10px; font-size: 1.45rem; color: #f8fafc; }
        p { margin: 0 0 24px; color: #94a3b8; line-height: 1.55; font-size: 0.95rem; }
        .error-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .error-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .error-btn-primary { background: linear-gradient(135deg, #3b82f6, #10b981); color: #fff; }
        .error-btn-secondary { background: rgba(148, 163, 184, 0.1); color: #e2e8f0; border-color: rgba(148, 163, 184, 0.2); }
        .error-btn:hover { filter: brightness(1.1); }
    </style>
</head>
<body>
    <main class="error-card">
        <img src="{{ asset('Images/logo.png') }}" alt="CCTV Express Solution logo" class="error-logo">
        <div class="error-code">ERROR @yield('code')</div>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <div class="error-actions">
            @yield('actions')
            {{-- The home address sends a signed-in user to their own
                 Dashboard (Admin) or POS (Cashier), anyone else to Login. --}}
            <a href="{{ url('/') }}" class="error-btn @hasSection('actions') error-btn-secondary @else error-btn-primary @endif">Go to Home</a>
        </div>
    </main>
</body>
</html>
