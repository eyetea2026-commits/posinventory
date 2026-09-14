<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Response Recorded</title>
</head>
<body>
    <div class="sec-page">
        <div class="sec-card">
            <span class="sec-badge sec-badge--{{ $status === 'confirmed' ? 'ok' : 'warn' }}">
                {{ $status === 'confirmed' ? 'CONFIRMED' : 'REPORTED' }}
            </span>
            <h1>{{ $status === 'confirmed' ? 'Thanks for confirming' : 'Thanks for letting us know' }}</h1>
            <p class="sec-sub">
                @if($status === 'confirmed')
                    We've marked this login as recognized. No further action is needed.
                @else
                    We've marked this login as not recognized and ended that session. The rest of the admin team has been notified to help review your account's security.
                @endif
            </p>
        </div>
    </div>

    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0; min-height: 100%;
            font-family: 'Segoe UI', Inter, system-ui, -apple-system, sans-serif;
            background: linear-gradient(180deg, #020617 0%, #090f1e 100%);
            color: #e2e8f0;
        }
        .sec-page { display: grid; place-items: center; min-height: 100vh; padding: 32px 16px; }
        .sec-card {
            width: 100%; max-width: 480px;
            background: #1a1d2d;
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.4);
            text-align: center;
        }
        .sec-badge {
            display: inline-block; color: #fff;
            padding: 4px 14px; border-radius: 999px; font-size: 12px;
            font-weight: 700; letter-spacing: 0.03em;
        }
        .sec-badge--ok { background: #10b981; }
        .sec-badge--warn { background: #ef4444; }
        .sec-card h1 { margin: 14px 0 6px; font-size: 1.4rem; color: #f8fafc; }
        .sec-sub { margin: 0; font-size: 0.9rem; color: #94a3b8; line-height: 1.5; }
    </style>
</body>
</html>
