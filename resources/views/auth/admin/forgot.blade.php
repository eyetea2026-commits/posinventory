<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Forgot Password - CCTV Express TACURONG</title>
    <link rel="stylesheet" href="{{ asset('Administrator/Login.css') }}">
</head>
<body>
    <div class="login-background">
        <section class="login-card">
            <div class="brand">
                <span class="brand-dot"></span>
                <span>CCTV Express TACURONG Administrator Portal</span>
            </div>
            <h1>Forgot Password</h1>
            <p>Enter your registered email address and we'll send an OTP code to reset your password.</p>

            @if(session('status'))
                <div class="status-message">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="error-message">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.forgot.post') }}">
                @csrf

                @if($adminEmail)
                    <div class="form-field" id="maskedEmailField">
                        <label for="email_display">Registered Email</label>
                        <input id="email_display" type="text" value="{{ $maskedAdminEmail }}" disabled>
                        <input type="hidden" id="email_hidden" name="email" value="{{ $adminEmail }}">
                        <a href="#" class="small-link" id="useDifferentEmailLink">Not you? Enter a different email</a>
                    </div>
                    <div class="form-field" id="manualEmailField" style="display: none;">
                        <label for="email_manual">Registered Email</label>
                        <input id="email_manual" type="email" name="email" value="{{ old('email') }}" disabled placeholder="Enter your admin email">
                        @error('email') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <script>
                        document.getElementById('useDifferentEmailLink').addEventListener('click', function (e) {
                            e.preventDefault();
                            document.getElementById('maskedEmailField').style.display = 'none';
                            document.getElementById('email_hidden').disabled = true;

                            var manualField = document.getElementById('manualEmailField');
                            var manualInput = document.getElementById('email_manual');
                            manualField.style.display = '';
                            manualInput.disabled = false;
                            manualInput.required = true;
                            manualInput.focus();
                        });
                    </script>
                @else
                    <div class="form-field">
                        <label for="email">Registered Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="Enter your admin email">
                        @error('email') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                @endif

                <div class="button-grid">
                    <button type="submit" class="button">Send OTP Code</button>
                </div>

                <div class="login-footer">
                    <a class="small-link" href="{{ route('welcome') }}">&#8592; Back to Login</a>
                </div>
            </form>
        </section>
    </div>
</body>
</html>