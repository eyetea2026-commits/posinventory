<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin OTP Verification - CCTV Express TACURONG</title>
    <link rel="stylesheet" href="{{ asset('Administrator/Login.css') }}">
</head>
<body>
    <div class="login-background">
        <section class="login-card">
            <div class="brand">
                <span class="brand-dot"></span>
                <span>CCTV Express TACURONG Administrator Portal</span>
            </div>
            <h1>Verify OTP</h1>
            <p>Enter the 6-digit OTP code sent to your email address.</p>

            @if(session('status'))
                <div class="status-message">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="error-message">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.otp.verify') }}">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}" />

                <div class="form-field">
                    <label for="otp">OTP Code</label>
                    <input id="otp" type="text" name="otp" required autofocus placeholder="Enter 6-digit OTP" maxlength="6">
                    @error('otp') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="button-grid">
                    <button type="submit" class="button">Verify OTP</button>
                </div>

                <div class="login-footer">
                    <a class="small-link" href="{{ route('admin.forgot') }}">&#8592; Back to Forgot Password</a>
                </div>
            </form>

            <div style="margin-top: 20px; text-align: center;">
                <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 12px;">Didn't receive the code?</p>
                <form method="POST" action="{{ route('admin.forgot.post') }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}" />
                    <button type="submit" class="button-secondary" style="min-height: 44px; padding: 0 18px; font-size: 0.9rem;">Resend OTP</button>
                </form>
            </div>
        </section>
    </div>
</body>
</html>