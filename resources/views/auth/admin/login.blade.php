<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrator Login</title>
    <link rel="stylesheet" href="{{ asset('Administrator/Login.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-background">
        <section class="login-card">
            <div class="brand">
                <span class="brand-dot"></span>
                <span>CCTV Express TACURONG Administrator Portal</span>
            </div>
            <h1>Admin Login</h1>
            <p>Enter your administrator credentials to access the dashboard.</p>

            @if(session('status'))
                <div class="status-message">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="error-message">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.login.post') }}">
                @csrf

                <div class="form-field">
                    <label for="username">Username</label>
                    <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus>
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
                    <a class="forgot-link" href="{{ route('admin.forgot') }}">Forgot Password?</a>
                    <a class="small-link" href="{{ route('welcome') }}">&#8592; Back to role selection</a>
                </div>
            </form>
        </section>
    </div>

    <script>
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
    </script>
</body>
</html>
