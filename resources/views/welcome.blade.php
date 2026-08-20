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
                <div class="lv2-character" id="lv2Character">
                    <img src="{{ asset('Images/Emoji.png') }}" alt="" class="lv2-character-img">
                    <img src="{{ asset('Images/Emoji.png') }}" alt="" class="lv2-character-img lv2-hand-layer" id="lv2HandLayer">
                    <span class="lv2-eyebrow lv2-eyebrow--left"></span>
                    <span class="lv2-eyebrow lv2-eyebrow--right"></span>
                    <span class="lv2-eye lv2-eye--left" id="lv2EyeLeft"></span>
                    <span class="lv2-eye lv2-eye--right" id="lv2EyeRight"></span>
                    <span class="lv2-mouth" id="lv2Mouth"></span>
                    <div class="lv2-pos-screen" id="lv2PosScreen"><span id="lv2PosScreenText"></span></div>
                    <div class="lv2-speech-box" id="lv2TopBar" aria-live="polite">
                        <span id="lv2TopBarText"></span><span class="lv2-caret" id="lv2TopBarCaret"></span>
                    </div>
                </div>
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

        // Animated login character. Two independent things happen on page
        // load: (1) the character "speaks" the greeting, with its mouth
        // synced to the text revealing in the fixed top bar, and (2) the
        // character operates the POS terminal — pressing keys and ringing
        // up products on the little screen — on its own separate timeline
        // that has nothing to do with how long the greeting text takes.
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
            const handLayer = document.getElementById('lv2HandLayer');
            const topBar = document.getElementById('lv2TopBar');
            const topBarText = document.getElementById('lv2TopBarText');
            const posScreen = document.getElementById('lv2PosScreen');
            const posScreenText = document.getElementById('lv2PosScreenText');
            const GREETING = 'Hello, There! Welcome to CCTV Express Solution Tacurong';

            let mouthTalkTimer = null;
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

            // The ONLY animation tied to the text: the mouth opens/closes
            // for exactly as long as typeText() below is revealing the
            // greeting, then stops the instant it finishes.
            function startMouthTalking() {
                mouth.classList.add('lv2-mouth--talking');
                let open = false;
                mouthTalkTimer = setInterval(() => {
                    open = !open;
                    mouth.classList.toggle('lv2-mouth--open', open);
                }, 150);
            }

            function stopMouthTalking() {
                clearInterval(mouthTalkTimer);
                mouthTalkTimer = null;
                mouth.classList.remove('lv2-mouth--talking', 'lv2-mouth--open');
            }

            // Reveals the greeting character-by-character in the speech box
            // above the terminal / left of the character's head.
            function typeText(fullText, onDone) {
                topBar.classList.add('lv2-visible');
                topBarText.textContent = '';
                let i = 0;
                const interval = setInterval(() => {
                    i++;
                    topBarText.textContent = fullText.slice(0, i);
                    if (i >= fullText.length) {
                        clearInterval(interval);
                        if (onDone) onDone();
                    }
                }, 55);
            }

            function speakGreeting() {
                startMouthTalking();
                typeText(GREETING, stopMouthTalking);

                // Best-effort audio only. Chrome (and others) commonly
                // reject speechSynthesis.speak() with a "not-allowed" error
                // when the page hasn't had a prior user gesture, so the
                // visual sequence never waits on this succeeding — it's
                // paced entirely by typeText()'s own timer instead.
                try {
                    if ('speechSynthesis' in window) {
                        const utterance = new SpeechSynthesisUtterance(GREETING);
                        utterance.lang = 'en-US';
                        utterance.rate = 0.95;
                        utterance.pitch = 1.1;
                        window.speechSynthesis.speak(utterance);
                    }
                } catch (e) {
                    // Ignored — the visual sequence proceeds regardless.
                }
            }

            // Runs the POS ring-up on its own clock — a fixed number of key
            // presses, unrelated to the greeting text's length or speed, so
            // "typing" never lines up letter-for-letter with what's being
            // said. Ends with the sale total left showing on the screen.
            function operatePOS() {
                const RING_UP_STEPS = [
                    { at: 700, text: 'Scanning…' },
                    { at: 2200, text: 'CCTV Camera\n₱2,500.00' },
                    { at: 3700, text: 'HDD 1TB\n₱3,200.00' },
                    { at: 5300, text: 'TOTAL\n₱5,700.00' },
                ];
                const TAP_DURATION_MS = 6200;
                const TAP_INTERVAL_MS = 380;

                posScreen.classList.add('lv2-visible');

                RING_UP_STEPS.forEach((step) => {
                    setTimeout(() => { posScreenText.textContent = step.text; }, step.at);
                });

                let tapping = false;
                const tapTimer = setInterval(() => {
                    tapping = !tapping;
                    handLayer.classList.toggle('lv2-hand-tap', tapping);
                }, TAP_INTERVAL_MS);

                setTimeout(() => {
                    clearInterval(tapTimer);
                    handLayer.classList.remove('lv2-hand-tap');
                }, TAP_DURATION_MS);
            }

            function startGreeting() {
                startIdleAnimation();
                startBlinking();
                setTimeout(speakGreeting, 500);
                setTimeout(operatePOS, 500);
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

        /* A second copy of the same flat PNG, stacked exactly on top of the
           base layer and clipped down to just the pointing hand/forearm, so
           that region alone can rotate slightly — the base layer underneath
           keeps the rest of the character (and the hand's resting position)
           perfectly still. Coordinates are pixel-measured from the source
           image, expressed as % of the character box (which shares the
           image's exact aspect ratio). */
        .lv2-hand-layer {
            position: absolute;
            top: 0;
            left: 0;
            clip-path: polygon(54.5% 46.8%, 73.6% 46.8%, 73.6% 62.4%, 54.5% 62.4%);
            transform-origin: 73% 47%;
            transition: transform 0.14s ease;
        }
        .lv2-hand-layer.lv2-hand-tap {
            transform: rotate(-4deg) translate(-2px, 1px);
        }

        /* Eyes/eyebrows/mouth overlaid on the illustration — the source PNG
           is a flat, featureless face, so these are drawn fresh rather than
           animating anything already in the artwork. Positioned as % of
           the character box, which shares the image's exact aspect ratio,
           so they stay aligned with the face at any size. Styled as a
           friendlier, more expressive face (visible eyebrows, white-and-
           pupil eyes, a warm smile) per the reference look. */
        .lv2-eyebrow {
            position: absolute;
            left: 70.4%;
            top: 23.6%;
            width: 2.6%;
            height: 0.55%;
            background: #262626;
            border-radius: 999px;
            transform: translate(-50%, -50%) rotate(-6deg);
        }
        .lv2-eyebrow--right {
            left: 74.1%;
            transform: translate(-50%, -50%) rotate(6deg);
        }

        .lv2-eye {
            position: absolute;
            left: 70.4%;
            top: 26.4%;
            width: 2.3%;
            height: 2.6%;
            background: radial-gradient(circle, #262626 0% 38%, #fdfdfd 40% 100%);
            border: 1px solid rgba(20, 20, 20, 0.18);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: transform 0.12s ease;
        }
        .lv2-eye--right { left: 74.1%; }
        .lv2-eye.lv2-blink { transform: translate(-50%, -50%) scaleY(0.12); }
        .lv2-eye.lv2-look-left { transform: translate(-65%, -50%); }
        .lv2-eye.lv2-look-right { transform: translate(-35%, -50%); }

        /* Resting mouth: a solid, curved-bottom smile silhouette (a hollow
           outline doesn't read clearly at this element's small rendered
           size) — while talking, it grows into a rounder open-mouth shape. */
        .lv2-mouth {
            position: absolute;
            left: 72.1%;
            top: 33.6%;
            width: 4.6%;
            height: 1.15%;
            background: #7a3b30;
            border-radius: 0 0 60% 60% / 0 0 100% 100%;
            transform: translate(-50%, -50%);
            transition: height 0.12s ease, border-radius 0.12s ease, background-color 0.12s ease;
        }
        .lv2-mouth--talking.lv2-mouth--open {
            height: 2.6%;
            border-radius: 40%;
        }

        /* The small POS monitor screen in the illustration — coordinates
           pixel-measured from the source image, expressed as % of the
           character box so the overlay text stays glued to the screen at
           any size. Sale progress is driven entirely by operatePOS()'s own
           timers, independent of the greeting text/speech. */
        .lv2-pos-screen {
            position: absolute;
            left: 23%;
            top: 42.5%;
            width: 23%;
            height: 14.5%;
            display: none;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 0.62rem;
            font-weight: 800;
            line-height: 1.3;
            color: #0f1115;
            white-space: pre-line;
            letter-spacing: -0.01em;
        }
        .lv2-pos-screen.lv2-visible { display: flex; }

        /* Sits in the empty space above the POS terminal and left of the
           character's head — positioned as % of the character box (same
           system as the eyes/mouth/hand layer/POS screen) so it stays put
           there at any size. */
        .lv2-speech-box {
            position: absolute;
            left: 3%;
            top: 3%;
            width: 56%;
            z-index: 5;
            visibility: hidden;
            opacity: 0;
            transform: translateY(-6px) scale(0.97);
            transition: opacity 0.25s ease, transform 0.25s ease;
            background: #ffffff;
            border: 1px solid #eef0f2;
            box-shadow: 0 14px 34px rgba(20, 22, 30, 0.12);
            border-radius: 14px;
            padding: 12px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            line-height: 1.35;
            color: #16181d;
            text-align: left;
        }
        .lv2-speech-box.lv2-visible {
            visibility: visible;
            opacity: 1;
            transform: translateY(0) scale(1);
        }
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
