<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="lv2-page">
        <div class="lv2-frame">
            <div class="lv2-left">
                <div class="lv2-heading">
                    <h1>CCTV Express Tacurong</h1>
                    <p>POS &amp; Inventory Management System</p>
                </div>

                <section class="lv2-card">
                    <h2>Login</h2>

                    @if(session('status'))
                        <div class="lv2-alert lv2-alert--success">{{ session('status') }}</div>
                    @endif

                    @if($errors->any())
                        <div class="lv2-alert lv2-alert--error">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('login.post') }}">
                        @csrf

                        <div class="lv2-field">
                            <i class="fa-solid fa-user"></i>
                            <label for="username" class="sr-only">Username</label>
                            <input id="username" type="text" name="username" value="{{ old('username') }}" placeholder="Username" required autofocus autocomplete="off">
                        </div>
                        <div id="role-badge" class="role-badge" hidden>
                            <i class="fa-solid fa-user-check"></i>
                            <span id="role-badge-text"></span>
                        </div>
                        @error('username') <span class="lv2-field-error">{{ $message }}</span> @enderror

                        <div class="lv2-field">
                            <i class="fa-solid fa-lock"></i>
                            <label for="password" class="sr-only">Password</label>
                            <input id="password" type="password" name="password" placeholder="Password" required autocomplete="current-password">
                            <span class="toggle-password" onclick="showPassword('password')">
                                <i class="fa-regular fa-eye" id="password-icon"></i>
                            </span>
                        </div>
                        @error('password') <span class="lv2-field-error">{{ $message }}</span> @enderror

                        <div class="lv2-remember">
                            <span>Remember Me</span>
                            <label class="lv2-switch">
                                <input id="remember" type="checkbox" name="remember">
                                <span class="lv2-slider"></span>
                            </label>
                        </div>

                        <button type="submit" class="lv2-submit">Log in</button>

                        <div class="lv2-footer">
                            <a id="forgot-link" class="lv2-forgot-link" href="{{ route('admin.forgot') }}" hidden>Forgot Password?</a>
                            <span id="cashier-note" class="lv2-cashier-note" hidden>
                                <i class="fa-solid fa-lock"></i>
                                Cashiers: please contact your Administrator for a password reset
                            </span>
                        </div>
                    </form>
                </section>
            </div>

            <div class="lv2-right" aria-hidden="true">
                <img src="{{ asset('Images/Emoji.png') }}" alt="">
            </div>
        </div>
    </div>

    <script>
        (function () {
            const usernameField = document.getElementById('username');
            const badge = document.getElementById('role-badge');
            const badgeText = document.getElementById('role-badge-text');
            const forgotLink = document.getElementById('forgot-link');
            const cashierNote = document.getElementById('cashier-note');
            const lookupUrl = @json(route('login.role-lookup'));

            let debounceTimer = null;
            let currentController = null;

            function applyRole(role) {
                if (role === 'admin') {
                    badge.hidden = false;
                    badge.className = 'role-badge role-badge--admin';
                    badgeText.textContent = 'Administrator';
                    forgotLink.hidden = false;
                    cashierNote.hidden = true;
                } else if (role === 'cashier') {
                    badge.hidden = false;
                    badge.className = 'role-badge role-badge--cashier';
                    badgeText.textContent = 'Cashier';
                    forgotLink.hidden = true;
                    cashierNote.hidden = false;
                } else {
                    badge.hidden = true;
                    forgotLink.hidden = true;
                    cashierNote.hidden = true;
                }
            }

            function checkRole(username) {
                if (username.length < 3) {
                    applyRole(null);
                    return;
                }

                if (currentController) {
                    currentController.abort();
                }
                currentController = new AbortController();

                fetch(`${lookupUrl}?username=${encodeURIComponent(username)}`, { signal: currentController.signal })
                    .then((response) => response.json())
                    .then((data) => applyRole(data.role))
                    .catch(() => {});
            }

            usernameField.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                const value = usernameField.value.trim();
                debounceTimer = setTimeout(() => checkRole(value), 350);
            });

            if (usernameField.value.trim()) {
                checkRole(usernameField.value.trim());
            }
        })();

        function showPassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');

            field.type = 'text';
            icon.style.transition = 'opacity 0.2s ease';
            icon.style.opacity = '0.5';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');

            setTimeout(() => {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                icon.style.opacity = '1';
            }, 2000);
        }
    </script>

    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            min-height: 100%;
            font-family: 'Segoe UI', Inter, system-ui, -apple-system, sans-serif;
            background: #eef1f4;
            color: #1f2937;
        }
        .sr-only {
            position: absolute;
            width: 1px; height: 1px;
            padding: 0; margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .lv2-page {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            place-items: center;
            min-height: 100vh;
            padding: 32px;
        }
        .lv2-frame {
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center;
            gap: 40px;
            width: min(100%, 1080px);
            padding: 40px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 28px;
            box-shadow: 0 24px 60px rgba(20, 22, 30, 0.08);
        }
        .lv2-heading h1 {
            margin: 0 0 2px;
            font-size: 1.5rem;
            font-weight: 700;
            color: #16181d;
        }
        .lv2-heading p {
            margin: 0 0 18px;
            font-size: 0.88rem;
            color: #6b7280;
        }
        .lv2-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 14px 34px rgba(20, 22, 30, 0.1);
            border: 1px solid #eef0f2;
            max-width: 360px;
        }
        .lv2-card h2 {
            margin: 0 0 16px;
            font-size: 1.3rem;
            font-weight: 700;
            color: #16181d;
        }
        .lv2-field {
            position: relative;
            display: flex;
            align-items: center;
            background: #f2f3f5;
            border-radius: 14px;
            padding: 0 16px;
            margin-bottom: 14px;
        }
        .lv2-field i:first-child {
            color: #9aa0a8;
            font-size: 0.95rem;
            margin-right: 12px;
        }
        .lv2-field input {
            flex: 1;
            border: none;
            background: transparent;
            outline: none;
            padding: 14px 0;
            font-size: 0.95rem;
            color: #1f2937;
        }
        .lv2-field input::placeholder {
            color: #9aa0a8;
        }
        .lv2-field.lv2-field--focus,
        .lv2-field:focus-within {
            box-shadow: 0 0 0 3px rgba(185, 28, 28, 0.14);
        }
        .toggle-password {
            cursor: pointer;
            color: #9aa0a8;
            padding-left: 10px;
            display: flex;
            align-items: center;
        }
        .toggle-password:hover { color: #6b7280; }
        .lv2-field-error {
            display: block;
            color: #b91c1c;
            font-size: 0.82rem;
            margin: -8px 0 14px 4px;
        }
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 9999px;
            font-size: 0.78rem;
            font-weight: 600;
            margin: -6px 0 14px 4px;
        }
        .role-badge.role-badge--admin[hidden],
        .role-badge.role-badge--cashier[hidden],
        .role-badge[hidden] {
            display: none;
        }
        .role-badge--admin {
            background: rgba(16, 185, 129, 0.12);
            color: #0f9d6e;
        }
        .role-badge--cashier {
            background: rgba(37, 99, 235, 0.12);
            color: #2563eb;
        }
        .lv2-remember {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 2px 0 18px;
            font-size: 0.86rem;
            color: #374151;
        }
        .lv2-switch {
            position: relative;
            display: inline-block;
            width: 36px;
            height: 20px;
        }
        .lv2-switch input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            cursor: pointer;
        }
        .lv2-slider {
            position: absolute;
            inset: 0;
            background: #d5d8dc;
            border-radius: 9999px;
            transition: background-color 0.2s ease;
        }
        .lv2-slider::before {
            content: "";
            position: absolute;
            width: 14px;
            height: 14px;
            left: 3px;
            top: 3px;
            background: #ffffff;
            border-radius: 50%;
            transition: transform 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.25);
        }
        .lv2-switch input:checked + .lv2-slider {
            background: #b91c1c;
        }
        .lv2-switch input:checked + .lv2-slider::before {
            transform: translateX(16px);
        }
        .lv2-submit {
            width: 100%;
            border: none;
            border-radius: 14px;
            padding: 12px 0;
            background: #a91f23;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 12px 26px rgba(169, 31, 35, 0.32);
            transition: transform 0.15s ease, background-color 0.15s ease;
        }
        .lv2-submit:hover { background: #931a1d; transform: translateY(-1px); }
        .lv2-submit:active { transform: translateY(0); }
        .lv2-footer {
            margin-top: 16px;
            text-align: center;
            font-size: 0.88rem;
        }
        .lv2-forgot-link {
            color: #a91f23;
            text-decoration: none;
            font-weight: 600;
        }
        .lv2-forgot-link:hover { text-decoration: underline; }
        .lv2-cashier-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: #6b7280;
            font-size: 0.82rem;
        }
        .lv2-forgot-link[hidden],
        .lv2-cashier-note[hidden] {
            display: none;
        }
        .lv2-alert {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 0.88rem;
        }
        .lv2-alert--success {
            background: rgba(16, 185, 129, 0.1);
            color: #0f9d6e;
        }
        .lv2-alert--error {
            background: rgba(185, 28, 28, 0.08);
            color: #b91c1c;
        }
        .lv2-right img {
            width: 100%;
            height: auto;
            display: block;
            object-fit: contain;
        }
        @media (max-width: 960px) {
            .lv2-frame {
                grid-template-columns: 1fr;
                padding: 16px;
            }
            .lv2-right { display: none; }
        }
        @media (max-width: 480px) {
            .lv2-page { padding: 16px; }
            .lv2-frame { padding: 8px; }
            .lv2-card { padding: 24px; }
            .lv2-heading h1 { font-size: 1.4rem; }
        }
    </style>
</body>
</html>
