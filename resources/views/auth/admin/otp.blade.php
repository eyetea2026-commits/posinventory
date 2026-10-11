<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin OTP Verification - CCTV Express TACURONG</title>
    <link rel="stylesheet" href="{{ asset('Administrator/Login.css') }}">
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
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, 0.26);
            background: rgba(15, 23, 42, 0.6);
            color: #f8fafc;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.15s ease;
        }
        .otp-bubble:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.18);
            transform: translateY(-2px);
        }
        .otp-bubble.filled {
            border-color: rgba(16, 185, 129, 0.5);
        }
        @media (max-width: 420px) {
            .otp-bubble-group { gap: 7px; }
            .otp-bubble { width: 40px; height: 50px; font-size: 1.25rem; }
        }
    </style>
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

            <div style="margin-top: 20px; text-align: center;">
                <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 12px;">Didn't receive the code?</p>
                <form method="POST" action="{{ route('admin.forgot.post') }}" style="display: inline;" id="resendOtpForm">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}" />
                    <button type="submit" class="button-secondary" id="resendOtpBtn" style="min-height: 44px; padding: 0 18px; font-size: 0.9rem;">Resend OTP</button>
                </form>
                <p id="resendCountdownText" style="color: #64748b; font-size: 0.82rem; margin-top: 8px; display: none;"></p>
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