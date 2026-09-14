<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Account Access</title>
</head>
<body>
    <div class="sec-page">
        <div class="sec-card">
            <span class="sec-badge">SECURITY ALERT</span>
            <h1>Was this you?</h1>
            <p class="sec-sub">Your Admin account was used to access the system. Review the details below and confirm whether this was you.</p>

            <div class="sec-details">
                <div class="sec-row"><span>Admin</span><strong>{{ $event->user?->full_name ?? $event->user?->name }}</strong></div>
                <div class="sec-row"><span>Date</span><strong>{{ $event->LoginAt->format('F j, Y') }}</strong></div>
                <div class="sec-row"><span>Time</span><strong>{{ $event->LoginAt->format('g:i A') }}</strong></div>
                <div class="sec-row"><span>IP Address</span><strong>{{ $event->IPAddress ?? 'Unavailable' }}</strong></div>
                <div class="sec-row"><span>Device/Browser</span><strong>{{ $event->DeviceSummary ?? 'Unavailable' }}</strong></div>
            </div>

            @if(!$event->isPending())
                <div class="sec-resolved">
                    This login was already marked
                    <strong>{{ $event->ConfirmationStatus === 'confirmed' ? 'Yes, This Was Me' : 'No, This Was Not Me' }}</strong>
                    on {{ $event->ConfirmedAt?->format('F j, Y g:i A') }}. No further action is needed.
                </div>
            @else
                <div class="sec-actions">
                    <form method="POST" action="{{ $confirmUrl }}">
                        @csrf
                        <button type="submit" class="sec-btn sec-btn--yes" {{ $intent === 'deny' ? '' : 'autofocus' }}>Yes, This Was Me</button>
                    </form>
                    <form method="POST" action="{{ $denyUrl }}">
                        @csrf
                        <button type="submit" class="sec-btn sec-btn--no" {{ $intent === 'deny' ? 'autofocus' : '' }}>No, This Was Not Me</button>
                    </form>
                </div>
            @endif
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
        }
        .sec-badge {
            display: inline-block; background: #d97706; color: #fff;
            padding: 4px 14px; border-radius: 999px; font-size: 12px;
            font-weight: 700; letter-spacing: 0.03em;
        }
        .sec-card h1 { margin: 14px 0 6px; font-size: 1.4rem; color: #f8fafc; }
        .sec-sub { margin: 0 0 20px; font-size: 0.9rem; color: #94a3b8; line-height: 1.5; }
        .sec-details { background: #0f172a; border-radius: 12px; padding: 16px; margin-bottom: 20px; }
        .sec-row { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; font-size: 0.88rem; }
        .sec-row span { color: #94a3b8; }
        .sec-row strong { color: #e2e8f0; text-align: right; }
        .sec-actions { display: flex; flex-direction: column; gap: 10px; }
        .sec-btn {
            width: 100%; border: none; border-radius: 12px; padding: 13px 0;
            font-size: 0.95rem; font-weight: 700; cursor: pointer;
        }
        .sec-btn--yes { background: #10b981; color: #fff; }
        .sec-btn--yes:hover { background: #0ea371; }
        .sec-btn--no { background: #ef4444; color: #fff; }
        .sec-btn--no:hover { background: #dc2626; }
        .sec-resolved { background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 10px; padding: 14px 16px; font-size: 0.88rem; line-height: 1.5; }
    </style>
</body>
</html>
