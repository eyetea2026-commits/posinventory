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
                <div class="lv2-speech-bubble" id="lv2SpeechBubble"><span id="lv2SpeechText"></span></div>

                <div class="lv2-character" id="lv2Character">
                    <img src="{{ asset('Images/Emoji.png') }}" alt="" class="lv2-character-img">
                    <span class="lv2-eye lv2-eye--left" id="lv2EyeLeft"></span>
                    <span class="lv2-eye lv2-eye--right" id="lv2EyeRight"></span>
                    <span class="lv2-mouth" id="lv2Mouth"></span>
                    <span class="lv2-typing-dots" id="lv2TypingDots"><i></i><i></i><i></i></span>
                </div>

                <div class="lv2-typebox" id="lv2TypeBox"><span id="lv2TypedText"></span><span class="lv2-caret"></span></div>
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

        // Animated login character — greets the user, "talks" the greeting,
        // then "types" it out again before settling into an idle loop.
        // Only runs when the illustration panel is actually on screen (it's
        // hidden below the 960px breakpoint), and is skipped entirely for
        // anyone who prefers reduced motion.
        (function () {
            const character = document.getElementById('lv2Character');
            if (!character || !window.matchMedia('(min-width: 960px)').matches) return;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            const eyeLeft = document.getElementById('lv2EyeLeft');
            const eyeRight = document.getElementById('lv2EyeRight');
            const mouth = document.getElementById('lv2Mouth');
            const typingDots = document.getElementById('lv2TypingDots');
            const speechBubble = document.getElementById('lv2SpeechBubble');
            const speechText = document.getElementById('lv2SpeechText');
            const typeBox = document.getElementById('lv2TypeBox');
            const typedText = document.getElementById('lv2TypedText');
            const GREETING = 'Hello, There!';

            let mouthTalkInterval = null;
            let blinkTimer = null;

            function startIdleAnimation() {
                character.classList.add('lv2-idle');
            }

            function blinkOnce() {
                eyeLeft.classList.add('lv2-blink');
                eyeRight.classList.add('lv2-blink');
                setTimeout(() => {
                    eyeLeft.classList.remove('lv2-blink');
                    eyeRight.classList.remove('lv2-blink');
                }, 140);
            }

            function lookAroundOnce() {
                const dir = Math.random() < 0.5 ? 'lv2-look-left' : 'lv2-look-right';
                eyeLeft.classList.add(dir);
                eyeRight.classList.add(dir);
                setTimeout(() => {
                    eyeLeft.classList.remove(dir);
                    eyeRight.classList.remove(dir);
                }, 900);
            }

            function startBlinking() {
                (function scheduleNext() {
                    const delay = 2400 + Math.random() * 3200;
                    blinkTimer = setTimeout(() => {
                        // Occasionally glance aside instead of blinking, so
                        // the face doesn't feel like it's on a robotic timer.
                        if (Math.random() < 0.3) {
                            lookAroundOnce();
                        } else {
                            blinkOnce();
                        }
                        scheduleNext();
                    }, delay);
                })();
            }

            function startMouthTalking() {
                mouth.classList.add('lv2-mouth--talking');
                let open = false;
                mouthTalkInterval = setInterval(() => {
                    open = !open;
                    mouth.classList.toggle('lv2-mouth--open', open);
                }, 130);
            }

            function stopMouthTalking() {
                clearInterval(mouthTalkInterval);
                mouthTalkInterval = null;
                mouth.classList.remove('lv2-mouth--talking', 'lv2-mouth--open');
            }

            function showSpeechBubble(text) {
                speechText.textContent = text;
                speechBubble.classList.add('lv2-visible');
            }

            function hideSpeechBubble() {
                speechBubble.classList.remove('lv2-visible');
            }

            function typeText(fullText, onDone) {
                typeBox.classList.add('lv2-visible');
                typedText.textContent = '';
                let i = 0;
                const interval = setInterval(() => {
                    i++;
                    typedText.textContent = fullText.slice(0, i);
                    if (i >= fullText.length) {
                        clearInterval(interval);
                        if (onDone) onDone();
                    }
                }, 90);
            }

            function startTyping() {
                typingDots.classList.add('lv2-visible');
                setTimeout(() => {
                    typingDots.classList.remove('lv2-visible');
                    typeText(GREETING);
                }, 900);
            }

            function speakGreeting() {
                showSpeechBubble(GREETING);
                startMouthTalking();

                // The visual "talking" duration is deliberately NOT driven by
                // the utterance's onend/onerror events: browsers commonly
                // reject speechSynthesis.speak() on a page that hasn't had a
                // user gesture yet (Chrome fires a "not-allowed" error within
                // ~1s of an autoplaying page load), which would otherwise cut
                // the mouth animation short whenever audio is blocked. Using
                // a fixed duration keeps the animation consistent regardless
                // of whether the browser actually lets the audio play.
                setTimeout(() => {
                    stopMouthTalking();
                    hideSpeechBubble();
                    startTyping();
                }, 1900);

                try {
                    if ('speechSynthesis' in window) {
                        const utterance = new SpeechSynthesisUtterance(GREETING);
                        utterance.lang = 'en-US';
                        utterance.rate = 0.9;
                        utterance.pitch = 1.1;
                        window.speechSynthesis.speak(utterance);
                    }
                } catch (e) {
                    // Best-effort audio only — the visual sequence above
                    // proceeds on its own fixed timer either way.
                }
            }

            function startGreeting() {
                startIdleAnimation();
                startBlinking();
                setTimeout(speakGreeting, 500);
            }

            startGreeting();
        })();
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
        .lv2-right {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .lv2-character {
            position: relative;
            width: 100%;
            aspect-ratio: 679 / 641;
        }
        .lv2-character.lv2-idle {
            animation: lv2-idle-bob 3.6s ease-in-out infinite;
        }
        .lv2-character-img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
        }
        @keyframes lv2-idle-bob {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        /* Eyes/mouth overlaid on the illustration — the source PNG is a
           flat, featureless face, so these are drawn fresh rather than
           animating anything already in the artwork. Positioned as % of
           the character box, which shares the image's exact aspect ratio,
           so they stay aligned with the face at any size. */
        .lv2-eye {
            position: absolute;
            left: 70.4%;
            top: 26.2%;
            width: 1.5%;
            height: 2%;
            background: #2b2b2b;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: transform 0.12s ease;
        }
        .lv2-eye--right { left: 74.1%; }
        .lv2-eye.lv2-blink { transform: translate(-50%, -50%) scaleY(0.12); }
        .lv2-eye.lv2-look-left { transform: translate(-150%, -50%); }
        .lv2-eye.lv2-look-right { transform: translate(50%, -50%); }

        .lv2-mouth {
            position: absolute;
            left: 72.1%;
            top: 32.8%;
            width: 4%;
            height: 0.9%;
            background: #9a5b56;
            border-radius: 999px;
            transform: translate(-50%, -50%);
            transition: height 0.12s ease, border-radius 0.12s ease;
        }
        .lv2-mouth--talking.lv2-mouth--open {
            height: 2.6%;
            border-radius: 40%;
        }

        .lv2-typing-dots {
            position: absolute;
            left: 36%;
            top: 53%;
            display: none;
            gap: 5px;
            padding: 5px 8px;
            background: rgba(20, 22, 30, 0.06);
            border-radius: 999px;
            transform: translate(-50%, -50%);
        }
        .lv2-typing-dots.lv2-visible { display: flex; }
        .lv2-typing-dots i {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #a91f23;
            animation: lv2-dot-pulse 1s ease-in-out infinite;
        }
        .lv2-typing-dots i:nth-child(2) { animation-delay: 0.15s; }
        .lv2-typing-dots i:nth-child(3) { animation-delay: 0.3s; }
        @keyframes lv2-dot-pulse {
            0%, 60%, 100% { opacity: 0.3; transform: translateY(0); }
            30% { opacity: 1; transform: translateY(-3px); }
        }

        .lv2-speech-bubble {
            visibility: hidden;
            opacity: 0;
            transform: translateY(6px) scale(0.96);
            transition: opacity 0.2s ease, transform 0.2s ease;
            background: #ffffff;
            border: 1px solid #eef0f2;
            box-shadow: 0 10px 26px rgba(20, 22, 30, 0.1);
            border-radius: 14px;
            padding: 10px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #16181d;
            margin-bottom: 14px;
        }
        .lv2-speech-bubble.lv2-visible {
            visibility: visible;
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .lv2-typebox {
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.2s ease;
            margin-top: 16px;
            padding: 10px 16px;
            background: #f7f5f0;
            border: 1px solid #eef0f2;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 600;
            color: #16181d;
            min-height: 1.4em;
        }
        .lv2-typebox.lv2-visible { visibility: visible; opacity: 1; }
        .lv2-caret {
            display: inline-block;
            width: 2px;
            height: 0.9em;
            margin-left: 2px;
            background: #a91f23;
            vertical-align: -0.15em;
            animation: lv2-caret-blink 0.8s step-end infinite;
        }
        @keyframes lv2-caret-blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
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
