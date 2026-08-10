<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Login</title>
    <link rel="stylesheet" href="{{ asset('Administrator/Login.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-background">
        <section class="login-card">
            <div class="login-logo">
                <img src="{{ asset('Images/logo.png') }}" alt="CCTV Express Tacurong logo">
            </div>
            <p>Enter your credentials to access the system.</p>

            @if(session('status'))
                <div class="status-message">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="error-message">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="login-form">
                @csrf

                <div class="form-field">
                    <label for="username">Username</label>
                    <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="off">
                    <div id="role-badge" class="role-badge" hidden>
                        <i class="fa-solid fa-user-check"></i>
                        <span id="role-badge-text"></span>
                    </div>
                    @error('username') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-field password-field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password">
                    <span class="toggle-password" onclick="showPassword('password')">
                        <i class="fa-regular fa-eye" id="password-icon"></i>
                    </span>
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="checkbox-field">
                    <input id="remember" type="checkbox" name="remember">
                    <label for="remember">Remember me</label>
                </div>

                <div class="button-grid">
                    <button type="submit" class="button">Sign In</button>
                </div>

                <div class="login-footer">
                    <a id="forgot-link" class="forgot-link" href="{{ route('admin.forgot') }}" hidden>Forgot Password?</a>
                    <span id="cashier-note" class="forgot-disabled" hidden>
                        <i class="fa-solid fa-lock" style="margin-right: 5px;"></i>
                        Cashiers: please contact your Administrator for a password reset
                    </span>
                </div>
            </form>
        </section>
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
        /* The logo is the page's primary branding element now that the
           "CCTV Express Tacurong" text label and "System Login" heading
           have been removed — sized and spaced accordingly. */
        .login-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 32px;
        }
        .login-logo img {
            width: 136px;
            height: 136px;
            border-radius: 28px;
            background: #fff;
            padding: 12px;
            box-shadow: 0 14px 36px rgba(0, 0, 0, 0.4);
            object-fit: contain;
        }
        /* Pushes Username/Password (and everything after) down as one block,
           without touching the internal gaps Login.css already defines
           between the fields, Remember Me, and Sign In. */
        .login-form {
            margin-top: 16px;
        }
        .forgot-disabled {
            margin-top: 6px;
            font-size: 13px;
            color: #6b7280;
            display: flex;
            align-items: center;
        }
        @media (max-width: 680px) {
            .login-logo img {
                width: 112px;
                height: 112px;
                border-radius: 24px;
            }
        }
    </style>
</body>
</html>
