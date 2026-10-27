<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <title>Admin OTP Verification - CCTV Express TACURONG</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2">
    <link rel="stylesheet" href="{{ asset('Administrator/Login.css') }}?v=2">
    <style>
        .otp-bubble-group {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 6px;
        }
        .otp-bubble {
            width: 48px;
            height: 56px;
            border-radius: 14px;
            border: 1px solid transparent;
            background: #2d3748;
            color: #e2e8f0;
            font-family: inherit;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .otp-bubble:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }
        .otp-bubble.filled {
            border-color: rgba(59, 130, 246, 0.6);
        }
        .otp-resend {
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid rgba(148, 163, 184, 0.1);
            text-align: center;
        }
        .otp-resend p {
            margin: 0 0 12px;
            font-size: 0.88rem;
        }
        .otp-resend .button-secondary {
            width: auto;
            min-height: 40px;
            padding: 0 18px;
            font-size: 0.9rem;
        }
        .otp-resend-countdown {
            display: none;
            margin: 8px 0 0;
            color: #64748b;
            font-size: 0.82rem;
        }
        @media (max-width: 420px) {
            .otp-bubble-group { gap: 7px; }
            .otp-bubble { width: 40px; height: 50px; font-size: 1.25rem; }
        }
    </style>
    @include('partials.responsive-ui')
</head>
<body>
    <div class="login-background">
        <section class="login-card">
            <div class="brand">
                <span class="brand-dot"></span>
                <span>CCTV Express TACURONG Administrator Portal</span>
            </div>
            <h1>Verify OTP</h1>
            <p>Enter the 6-digit OTP code sent to your email address. The code expires in {{ $otpExpiryMinutes ?? 5 }} minutes.</p>

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
                    <label>OTP Code</label>
                    <div class="otp-bubble-group" id="otpBubbleGroup">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" class="otp-bubble" maxlength="1" data-otp-index="0" autofocus>
                        <input type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" class="otp-bubble" maxlength="1" data-otp-index="1">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" class="otp-bubble" maxlength="1" data-otp-index="2">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" class="otp-bubble" maxlength="1" data-otp-index="3">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" class="otp-bubble" maxlength="1" data-otp-index="4">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" class="otp-bubble" maxlength="1" data-otp-index="5">
                    </div>
                    <input type="hidden" id="otp" name="otp" required>
                    @error('otp') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="button-grid">
                    <button type="submit" class="button">Verify OTP</button>
                </div>

                <div class="login-footer">
                    <a class="small-link" href="{{ route('admin.forgot') }}">&#8592; Back to Forgot Password</a>
                </div>
            </form>

            <div class="otp-resend">
                <p>Didn't receive the code?</p>
                <form method="POST" action="{{ route('admin.forgot.post') }}" style="display: inline;" id="resendOtpForm">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}" />
                    <button type="submit" class="button-secondary" id="resendOtpBtn">Resend OTP</button>
                </form>
                <p id="resendCountdownText" class="otp-resend-countdown"></p>
            </div>
        </section>
    </div>

    <script>
        (function () {
            const bubbles = Array.from(document.querySelectorAll('.otp-bubble'));
            const hiddenOtp = document.getElementById('otp');
            if (!bubbles.length || !hiddenOtp) return;

            function syncHiddenField() {
                hiddenOtp.value = bubbles.map(function (b) { return b.value; }).join('');
            }

            function fillFrom(index, digits) {
                for (let i = 0; i < digits.length && index + i < bubbles.length; i++) {
                    bubbles[index + i].value = digits[i];
                    bubbles[index + i].classList.toggle('filled', !!digits[i]);
                }
                syncHiddenField();
                const nextIndex = Math.min(index + digits.length, bubbles.length - 1);
                bubbles[nextIndex].focus();
                if (index + digits.length >= bubbles.length) {
                    bubbles[bubbles.length - 1].select();
                }
            }

            bubbles.forEach(function (bubble, index) {
                bubble.addEventListener('input', function () {
                    const digitsOnly = bubble.value.replace(/\D/g, '');

                    if (digitsOnly.length > 1) {
                        // A full code was typed/autofilled into one box (common on
                        // mobile with SMS/email autofill suggestions) — spread it
                        // across the remaining boxes instead of truncating it.
                        fillFrom(index, digitsOnly);
                        return;
                    }

                    bubble.value = digitsOnly;
                    bubble.classList.toggle('filled', !!digitsOnly);
                    syncHiddenField();

                    if (digitsOnly && index < bubbles.length - 1) {
                        bubbles[index + 1].focus();
                    }
                });

                bubble.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && !bubble.value && index > 0) {
                        bubbles[index - 1].focus();
                        bubbles[index - 1].value = '';
                        bubbles[index - 1].classList.remove('filled');
                        syncHiddenField();
                    } else if (e.key === 'ArrowLeft' && index > 0) {
                        bubbles[index - 1].focus();
                    } else if (e.key === 'ArrowRight' && index < bubbles.length - 1) {
                        bubbles[index + 1].focus();
                    }
                });

                bubble.addEventListener('paste', function (e) {
                    e.preventDefault();
                    const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                    if (pasted) fillFrom(0, pasted);
                });

                bubble.addEventListener('focus', function () {
                    bubble.select();
                });
            });

            bubbles[0].focus();

            const sentAt = @json($sentAt);
            const cooldownSeconds = @json($resendCooldownSeconds ?? 45);
            const btn = document.getElementById('resendOtpBtn');
            const countdownText = document.getElementById('resendCountdownText');
            if (!sentAt || !btn) return;

            const sentAtMs = new Date(sentAt).getTime();

            function tick() {
                const elapsedSeconds = Math.floor((Date.now() - sentAtMs) / 1000);
                const remaining = cooldownSeconds - elapsedSeconds;

                if (remaining > 0) {
                    btn.disabled = true;
                    countdownText.style.display = 'block';
                    countdownText.textContent = 'You can resend the code in ' + remaining + 's';
                    setTimeout(tick, 1000);
                } else {
                    btn.disabled = false;
                    countdownText.style.display = 'none';
                }
            }

            tick();
        })();
    </script>
</body>
</html>