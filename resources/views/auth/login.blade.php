<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Web-Based Sales and Inventory System</title>
    <link rel="stylesheet" href="{{ asset('Administrator/Login.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <div class="login-background">
        <section class="login-card">
            <div class="brand">
                <span class="brand-dot"></span>
                <span>CCTV Express TACURONG</span>
            </div>
            <h1>System Login</h1>
            <p>Enter your credentials to access the system.</p>

            @if(session('status'))
                <div class="status-message">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="error-message">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-field">
                    <label for="username">Username</label>
                    <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus>
                    <div id="role-indicator" class="role-indicator"></div>
                    @error('username')
                    <span class="field-error">{{ $message }}</span>
                @else
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                @enderror
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
                    <a class="forgot-link" id="forgot-password-link" href="{{ route('admin.forgot') }}" style="display: none;">Forgot Password?</a>
                    <span class="forgot-disabled" id="forgot-disabled" style="display: none; color: #888; font-size: 14px;">
                        <i class="fa-solid fa-lock" style="margin-right: 5px;"></i>
                        Password reset available for Administrators only
                    </span>
                </div>
            </form>
        </section>
    </div>

    <script>
        // CSRF token setup
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function showPassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');

            // Show password with smooth transition
            field.type = 'text';
            icon.style.transition = 'opacity 0.2s ease';
            icon.style.opacity = '0.5';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');

            // Auto-hide after 2 seconds with smooth transition
            setTimeout(() => {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                icon.style.opacity = '1';
            }, 2000);
        }

        // Check user role when username is entered
        const usernameInput = document.getElementById('username');
        const roleIndicator = document.getElementById('role-indicator');
        const forgotLink = document.getElementById('forgot-password-link');
        const forgotDisabled = document.getElementById('forgot-disabled');

        let roleCheckTimeout = null;

        usernameInput.addEventListener('blur', checkUserRole);
        usernameInput.addEventListener('input', function() {
            // Clear previous timeout
            clearTimeout(roleCheckTimeout);
            // Reset the role indicator when user starts typing
            roleIndicator.innerHTML = '';
            forgotLink.style.display = 'none';
            forgotDisabled.style.display = 'none';
        });

        function checkUserRole() {
            const username = usernameInput.value.trim();

            if (!username) {
                roleIndicator.innerHTML = '';
                forgotLink.style.display = 'none';
                forgotDisabled.style.display = 'none';
                return;
            }

            // Debounce the check
            roleCheckTimeout = setTimeout(() => {
                fetch('{{ route("check.user.role") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ username: username })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        if (data.isAdmin) {
                            // Show Forgot Password link for Admin
                            roleIndicator.innerHTML = '<span class="role-badge admin"><i class="fa-solid fa-user-shield"></i> Administrator</span>';
                            forgotLink.style.display = 'inline';
                            forgotDisabled.style.display = 'none';
                        } else if (data.isCashier) {
                            // Show message for Cashier - password reset not available
                            roleIndicator.innerHTML = '<span class="role-badge cashier"><i class="fa-solid fa-user"></i> Cashier</span>';
                            forgotLink.style.display = 'none';
                            forgotDisabled.style.display = 'inline';
                        }
                    } else {
                        // No user found
                        roleIndicator.innerHTML = '<span class="role-badge not-found"><i class="fa-solid fa-circle-exclamation"></i> ' + data.message + '</span>';
                        forgotLink.style.display = 'none';
                        forgotDisabled.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error checking user role:', error);
                    roleIndicator.innerHTML = '';
                    forgotLink.style.display = 'none';
                    forgotDisabled.style.display = 'none';
                });
            }, 500);
        }
    </script>

    <style>
        .role-indicator {
            margin-top: 5px;
            min-height: 24px;
        }
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .role-badge.admin {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        .role-badge.cashier {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        .role-badge.not-found {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .forgot-disabled {
            font-size: 13px;
            color: #6b7280;
            display: inline-flex;
            align-items: center;
        }
    </style>
</body>
</html>