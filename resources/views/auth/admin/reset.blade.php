<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Reset Password - CCTV Express TACURONG</title>
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
            <h1>Reset Password</h1>
            <p>Create a new password for your admin account.</p>

            @if(session('status'))
                <div class="status-message">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="error-message">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.password.reset') }}">
                @csrf
                <input type="hidden" name="email" value="{{ request('email') ?? old('email') }}" />

                <div class="form-field password-field">
                    <label for="password">New Password</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password" placeholder="Enter new password">
                    <span class="toggle-password" onclick="showPassword('password')">
                        <i class="fa-regular fa-eye" id="password-icon"></i>
                    </span>
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-field password-field">
                    <label for="password_confirmation">Confirm New Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Confirm new password">
                    <span class="toggle-password" onclick="showPassword('password_confirmation')">
                        <i class="fa-regular fa-eye" id="password_confirmation-icon"></i>
                    </span>
                </div>

                <div class="button-grid">
                    <button type="submit" class="button">Reset Password</button>
                </div>

                <div class="login-footer">
                    <a class="small-link" href="{{ route('login') }}">&#8592; Back to Login</a>
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