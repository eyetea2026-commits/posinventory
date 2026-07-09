<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cashier Forgot Password - CCTV Express TACURONG</title>
    <link rel="stylesheet" href="{{ asset('Administrator/Login.css') }}">
</head>
<body>
    <div class="login-background">
        <section class="login-card">
            <div class="brand">
                <span class="brand-dot"></span>
                <span>CCTV Express TACURONG Cashier Portal</span>
            </div>
            <h1>Forgot Password</h1>
            <p>Enter your registered email address and we'll send you an OTP code to reset your password.</p>

            @if(session('status'))
                <div class="status-message">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="error-message">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('cashier.forgot.post') }}">
                @csrf

                <div class="form-field">
                    <label for="email">Registered Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="Enter your cashier email">
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="button-grid">
                    <button type="submit" class="button">Send OTP Code</button>
                </div>

                <div class="login-footer">
                    <a class="small-link" href="{{ route('cashier.login') }}">&#8592; Back to Login</a>
                </div>
            </form>
        </section>
    </div>
</body>
</html>