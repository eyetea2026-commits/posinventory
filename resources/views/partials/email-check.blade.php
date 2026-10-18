{{-- Live "valid email" check while typing. Include once on a page, then call
     window.attachEmailCheck(input, messageEl?) for each email box. While the
     box holds something that isn't a valid email address it turns red and
     shows "Please enter a valid email address." under it; an empty box is
     left alone (required fields are still checked on submit). The same
     message is set as the box's validity, so the form can't be submitted
     with an invalid address. The server still validates the address too. --}}
<style>
    input.email-invalid {
        border-color: #ef4444 !important;
        background: rgba(239, 68, 68, 0.18) !important;
    }
    .email-check-message {
        display: block;
        margin-top: 4px;
        color: #f87171;
        font-size: 0.8rem;
    }
    .email-check-message:empty { display: none; }
</style>
<script>
    (function () {
        const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        const MESSAGE = 'Please enter a valid email address.';

        window.attachEmailCheck = function (input, messageEl) {
            if (!input || input.dataset.emailCheckAttached) return;
            input.dataset.emailCheckAttached = '1';

            if (!messageEl) {
                messageEl = document.createElement('span');
                messageEl.className = 'email-check-message';
                messageEl.setAttribute('aria-live', 'polite');
                input.insertAdjacentElement('afterend', messageEl);
            }

            function isInvalid() {
                const value = input.value.trim();
                return value !== '' && !EMAIL_PATTERN.test(value);
            }

            function check() {
                const invalid = isInvalid();
                input.classList.toggle('email-invalid', invalid);
                input.setAttribute('aria-invalid', invalid ? 'true' : 'false');
                input.setCustomValidity(invalid ? MESSAGE : '');
                messageEl.textContent = invalid ? MESSAGE : '';
            }

            input.addEventListener('input', check);
            input.addEventListener('blur', check);
            // A value already in the box (e.g. kept after a failed submit)
            // still blocks submitting, but the message waits for typing so it
            // doesn't repeat the server's own error shown under the box.
            input.setCustomValidity(isInvalid() ? MESSAGE : '');
        };
    })();
</script>
