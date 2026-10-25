{{-- Shared responsive layer for every page: Administrator, Cashier, the
     sign-in / password pages and the error pages. Included at the END of
     each page's <head>, after the page's own stylesheets.

     1. Design tokens: a fluid type scale (clamp) and a 4px spacing scale.
     2. Typography: readable sizes on 280px phones up to 1440px+ screens.
     3. Space: tighter page gutters and card padding on small screens; no
        whole-page sideways scrolling; wide tables scroll inside their box.
     4. Pop-ups: every pop-up (.modal-overlay > .modal / .modal-content, the
        Cashier .modal > .modal-content, the POS approval box) fits every
        screen: width min(92vw, its own max), height up to 90dvh with the
        content scrolling and the title + buttons kept in view, safe areas
        on notched phones, stacked full-width buttons on phones, 44px tap
        targets. The script at the bottom adds the shared behaviour: close
        on Esc or a click on the dimmed background, background scroll lock,
        and keeping a focused field visible above the on-screen keyboard.

     Breakpoints (mobile-first): <360 extra small (JioPhone 2, folded Z
     Fold), 360-399 small phones, 400-599 large phones, 600-899 foldables /
     small tablets, 900-1199 tablets, 1200+ desktops / smart displays, plus
     short-landscape rules (max-height: 500px / 600px). --}}
<style>
    /* ---------- 1. Tokens ---------- */
    :root {
        --space-1: 4px;
        --space-2: 8px;
        --space-3: 12px;
        --space-4: 16px;
        --space-5: 20px;
        --space-6: 24px;
        --space-8: 32px;

        --text-caption: clamp(0.75rem, 0.72rem + 0.12vw, 0.8125rem);
        --text-body: clamp(0.875rem, 0.85rem + 0.15vw, 1rem);
        --text-subheading: clamp(1rem, 0.95rem + 0.3vw, 1.15rem);
        --text-heading: clamp(1.15rem, 1rem + 0.8vw, 1.5rem);
        --text-title: clamp(1.4rem, 1.1rem + 1.6vw, 2.5rem);

        --tap-size: 44px;
        --page-gutter: clamp(12px, 3.2vw, 40px);
        --modal-gutter: clamp(8px, 3vw, 24px);

        --safe-top: env(safe-area-inset-top, 0px);
        --safe-right: env(safe-area-inset-right, 0px);
        --safe-bottom: env(safe-area-inset-bottom, 0px);
        --safe-left: env(safe-area-inset-left, 0px);
    }

    /* ---------- 2. Typography ---------- */
    html {
        -webkit-text-size-adjust: 100%;
        text-size-adjust: 100%;
    }
    html body {
        line-height: 1.5;
        overflow-wrap: break-word;
    }
    html body :is(h1, h2, h3, h4) {
        line-height: 1.25;
        overflow-wrap: break-word;
    }
    /* Page titles ("Product Management") scale with the screen. */
    html body .header-title h1 {
        font-size: var(--text-title);
    }
    html body .header-title p {
        font-size: var(--text-body);
    }
    html body :is(.modal-header h2, .modal-header h3, .modal-title) {
        font-size: var(--text-heading);
    }
    /* Long names, emails and codes wrap instead of pushing the layout. */
    html body :is(td, th, .detail-item span, .modal-content p) {
        overflow-wrap: anywhere;
    }
    html body :is(img, video, canvas) {
        max-width: 100%;
    }

    /* ---------- 3. Space ---------- */
    html, html body {
        max-width: 100%;
        overflow-x: clip;   /* last-resort guard against sideways page scroll */
    }
    html body .main-content {
        padding: var(--space-6) var(--page-gutter);
        padding-left: max(var(--page-gutter), var(--safe-left));
        padding-right: max(var(--page-gutter), var(--safe-right));
        gap: clamp(16px, 2.4vw, 28px);
    }
    html body .dashboard-header {
        gap: var(--space-3) var(--space-6);
        padding-bottom: clamp(12px, 2vw, 24px);
    }
    html body .header-title h1 {
        margin-bottom: var(--space-1);
    }
    /* Wide tables scroll sideways inside their own box. */
    html body :is(.table-container, .table-wrapper, .table-responsive, .card-body) {
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    /* A dropdown is never wider than its box (long supplier names). */
    html body :is(select, input, textarea) {
        max-width: 100%;
    }
    /* Search boxes, chart period buttons and filters may shrink and wrap. */
    html body :is(.search-box, .search-box-wrapper, .search-input, .search-form) {
        min-width: 0;
        max-width: 100%;
    }
    html body :is(.chart-toggle-group, .filters, .filter-group, .header-actions) {
        flex-wrap: wrap;
        max-width: 100%;
    }
    html body .chart-toggle-group > * {
        flex: 1 1 auto;
    }
    html body :is(.stat-card, .kpi-card, .chart-card) {
        min-width: 0;
    }

    /* Phones: tighter cards so content gets the room. */
    @media (max-width: 599px) {
        html body :is(.card, .content-card, .table-card, .filter-card, .section-card, .chart-card, .stat-card, .kpi-card, .welcome-section) {
            border-radius: 14px;
        }
        html body :is(.card-header, .card-body, .filters-section, .filter-section, .content-card, .chart-card, .welcome-section) {
            padding-left: var(--space-3);
            padding-right: var(--space-3);
        }
        /* Cards and their toolbars each added 18-24px; nested, that left
           phones with half the width for the actual content. */
        html body :is(.card, .toolbar, .stat-card, .kpi-card, .ops-tab-panel) {
            padding: var(--space-3);
        }
        html body .card :is(.toolbar, .card-header) {
            padding-left: 0;
            padding-right: 0;
        }
        /* Page header: title and the bell / profile share one row. */
        html body .dashboard-header {
            flex-direction: row;
            align-items: center;
            flex-wrap: wrap;
        }
        html body .dashboard-header .header-title {
            flex: 1 1 180px;
            min-width: 0;
        }
    }
    /* Summary cards two per row on phones (one per row below 360px). */
    @media (min-width: 360px) and (max-width: 599px) {
        html body :is(.stats-grid, .kpi-grid) {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: var(--space-3);
        }
        html body :is(.stats-grid, .kpi-grid) > :is(.stat-card, .kpi-card) {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-2);
        }
        html body :is(.stats-grid, .kpi-grid) .stat-icon {
            width: 36px;
            height: 36px;
            font-size: 1rem;
        }
        html body :is(.stats-grid, .kpi-grid) :is(.stat-value, .kpi-value) {
            font-size: var(--text-heading);
        }
    }
    @media (max-width: 599px) {
        html body .main-content {
            padding-top: var(--space-4);
            padding-bottom: calc(var(--space-6) + var(--safe-bottom));
        }
        /* 16px stops iPhones zooming the page when a field is tapped. */
        html body :is(input:not([type=checkbox]):not([type=radio]), select, textarea) {
            font-size: max(16px, 1em);
        }
    }
    @media (max-width: 359px) {
        html body .main-content {
            padding-left: max(var(--space-2), var(--safe-left));
            padding-right: max(var(--space-2), var(--safe-right));
        }
    }

    /* Touch screens: every button and field is at least 44px tall. */
    @media (pointer: coarse) {
        html body :is(button, .btn, [role=button], select, input:not([type=checkbox]):not([type=radio]):not([type=hidden]):not([type=range])) {
            min-height: var(--tap-size);
        }
        html body :is(.action-btn, .modal-close, .approval-close, .header-icon-btn, .qty-btn, .remove-btn, .icon-btn) {
            min-width: var(--tap-size);
            min-height: var(--tap-size);
        }
        html body :is(input[type=checkbox], input[type=radio]) {
            min-width: 20px;
            min-height: 20px;
        }
    }

    /* ---------- 4. Pop-ups ---------- */
    /* The dimmed background: room for notches, scrolls if the box is taller. */
    html body :is(.modal-overlay, .modal:has(> .modal-content), .approval-overlay) {
        padding: max(var(--modal-gutter), var(--safe-top)) max(var(--modal-gutter), var(--safe-right)) max(var(--modal-gutter), var(--safe-bottom)) max(var(--modal-gutter), var(--safe-left));
        overflow-y: auto;
        overscroll-behavior: contain;
        box-sizing: border-box;
    }
    /* The pop-up box itself: never taller than 90% of the screen, with the
       content scrolling inside. Each box keeps its own width and max width
       (e.g. 560px, 760px); the script trims any box that would still be
       wider than the screen. */
    html body :is(.modal-overlay > .modal, .modal-overlay > .modal-content, .modal > .modal-content, .approval-overlay > .approval-box) {
        box-sizing: border-box;
        max-width: calc(100vw - 2 * var(--modal-gutter));
        /* 90% of the screen, minus the backdrop's padding and notch areas. */
        max-height: min(90vh, calc(100vh - 2 * var(--modal-gutter) - var(--safe-top) - var(--safe-bottom))) !important;
        max-height: min(90dvh, calc(100dvh - 2 * var(--modal-gutter) - var(--safe-top) - var(--safe-bottom))) !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
        /* auto margins keep the top reachable if the box is still taller. */
        margin: auto !important;
    }
    /* Opening animation (fade + scale). */
    @keyframes ui-modal-in {
        from { opacity: 0; transform: translateY(10px) scale(0.97); }
        to { opacity: 1; transform: none; }
    }
    html body :is(.modal-overlay.active > .modal, .modal-overlay.active > .modal-content, .modal.active > .modal-content, .approval-overlay.active > *) {
        animation: ui-modal-in 0.18s ease-out;
    }
    /* Header and buttons stay in view while a long pop-up scrolls (the
       script adds .ui-modal-scrolls and the box's own colour/padding). */
    html body .ui-modal-scrolls .modal-header {
        position: sticky;
        top: calc(-1 * var(--ui-pad-top, 0px));
        margin-top: calc(-1 * var(--ui-pad-top, 0px));
        padding-top: var(--ui-pad-top, 0px);
        background: var(--ui-modal-bg, #0f172a);
        z-index: 5;
    }
    html body .ui-modal-scrolls :is(.modal-actions, .modal-footer):last-child {
        position: sticky;
        bottom: calc(-1 * var(--ui-pad-bottom, 0px));
        margin-bottom: calc(-1 * var(--ui-pad-bottom, 0px));
        padding-bottom: var(--ui-pad-bottom, 0px);
        background: var(--ui-modal-bg, #0f172a);
        z-index: 5;
    }
    html body .modal-header {
        gap: var(--space-3);
    }
    html body .modal-header :is(h2, h3, .modal-title) {
        min-width: 0;
    }
    html body :is(.modal-actions, .modal-footer) {
        flex-wrap: wrap;
        gap: var(--space-2) var(--space-3);
    }
    /* A scrolled-to field stays clear of the sticky header/buttons. */
    html body .ui-modal-scrolls :is(input, select, textarea) {
        scroll-margin: 72px 0 88px;
    }

    /* Phones: buttons stacked and full width (main action on top). */
    @media (max-width: 479px) {
        html body :is(.modal-actions, .modal-footer) {
            flex-direction: column-reverse;
            align-items: stretch;
        }
        html body :is(.modal-actions, .modal-footer) > :is(button, a, .btn) {
            width: 100%;
            justify-content: center;
            margin: 0;
        }
        html body :is(.modal-overlay > .modal, .modal-overlay > .modal-content, .modal > .modal-content) {
            border-radius: 16px;
        }
        html body .modal-content .form-grid {
            grid-template-columns: 1fr;
        }
    }
    /* Extra-small screens (JioPhone 2, folded Galaxy Z Fold): nearly full
       screen with reduced padding. */
    @media (max-width: 359px) {
        :root { --modal-gutter: 4px; }
        html body :is(.modal-overlay > .modal, .modal-overlay > .modal-content, .modal > .modal-content, .approval-overlay > *) {
            width: 100% !important;
            max-width: 100% !important;
            max-height: calc(100vh - 2 * var(--modal-gutter) - var(--safe-top) - var(--safe-bottom)) !important;
            max-height: calc(100dvh - 2 * var(--modal-gutter) - var(--safe-top) - var(--safe-bottom)) !important;
            padding-left: 12px !important;
            padding-right: 12px !important;
            border-radius: 12px;
        }
    }
    /* Short landscape screens (phones sideways, Nest Hub Max, folded
       foldables): use almost all of the height, smaller header gaps. */
    @media (max-height: 500px) {
        :root { --modal-gutter: 6px; }
        html body :is(.modal-overlay > .modal, .modal-overlay > .modal-content, .modal > .modal-content, .approval-overlay > *) {
            max-height: calc(100vh - 2 * var(--modal-gutter) - var(--safe-top) - var(--safe-bottom)) !important;
            max-height: calc(100dvh - 2 * var(--modal-gutter) - var(--safe-top) - var(--safe-bottom)) !important;
        }
        html body .modal-header {
            margin-bottom: var(--space-2);
            padding-bottom: var(--space-2);
        }
    }
    @media (min-height: 501px) and (max-height: 600px) and (orientation: landscape) {
        html body :is(.modal-overlay > .modal, .modal-overlay > .modal-content, .modal > .modal-content) {
            max-height: 94vh !important;
            max-height: 94dvh !important;
        }
    }

    /* While a pop-up is open the page behind it doesn't scroll. */
    html.ui-modal-open,
    html.ui-modal-open body {
        overflow: hidden !important;
    }

    /* SweetAlert pop-ups (confirmations, success, errors) follow the same rules. */
    html body .swal2-container {
        padding: max(var(--modal-gutter), var(--safe-top)) max(var(--modal-gutter), var(--safe-right)) max(var(--modal-gutter), var(--safe-bottom)) max(var(--modal-gutter), var(--safe-left)) !important;
    }
    html body .swal2-popup {
        max-width: calc(100vw - 2 * var(--modal-gutter)) !important;
        max-height: 90dvh;
        overflow-y: auto;
    }
    @media (pointer: coarse) {
        html body .swal2-actions button {
            min-height: var(--tap-size);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        html body :is(.modal-overlay, .modal, .approval-overlay) > * {
            animation: none !important;
        }
    }
</style>
<script>
    /**
     * Shared pop-up behaviour (all pop-up styles, Administrator and Cashier):
     * - Esc or a click on the dimmed background closes the top pop-up, by
     *   clicking its own close (X) button so its normal clean-up still runs.
     * - The page behind an open pop-up is locked from scrolling.
     * - A long pop-up keeps its header and buttons in view while scrolling.
     * - A field focused while the on-screen keyboard opens is scrolled into view.
     */
    (function () {
        if (window.__responsiveUi) return;
        window.__responsiveUi = true;

        const OVERLAYS = '.modal-overlay, .modal, .approval-overlay';
        const BOXES = ':scope > .modal, :scope > .modal-content, :scope > .approval-box';

        function isOverlay(el) {
            if (!el.matches(OVERLAYS)) return false;
            // A .modal inside a .modal-overlay is the box, not a backdrop.
            return !(el.classList.contains('modal') && el.parentElement && el.parentElement.closest('.modal-overlay'));
        }

        function isOpen(el) {
            const style = getComputedStyle(el);
            if (style.display === 'none' || el.getClientRects().length === 0) return false;
            // Marked open (still fading in counts as open).
            if (el.classList.contains('active') || el.classList.contains('show')) return true;
            return style.visibility !== 'hidden' && Number(style.opacity) > 0.01;
        }

        function openOverlays() {
            return Array.from(document.querySelectorAll(OVERLAYS)).filter(function (el) { return isOverlay(el) && isOpen(el); });
        }

        function topOverlay() {
            const open = openOverlays();
            if (!open.length) return null;
            return open.reduce(function (top, el) {
                const z = parseInt(getComputedStyle(el).zIndex, 10) || 0;
                const topZ = parseInt(getComputedStyle(top).zIndex, 10) || 0;
                return z >= topZ ? el : top;
            });
        }

        function closeOverlay(overlay) {
            const button = overlay.querySelector('.modal-close, .approval-close, [data-modal-close]');
            if (button) {
                button.click();
            } else {
                overlay.classList.remove('active', 'show', 'open');
            }
        }

        function sweetAlertOpen() {
            return !!(window.Swal && window.Swal.isVisible && window.Swal.isVisible());
        }

        // Esc: SweetAlert handles its own; otherwise close the top pop-up.
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' || sweetAlertOpen()) return;
            const overlay = topOverlay();
            if (overlay) {
                e.preventDefault();
                closeOverlay(overlay);
            }
        });

        // A click that starts and ends on the dimmed background closes it
        // (not a text selection dragged out of the box).
        let pressedOn = null;
        document.addEventListener('mousedown', function (e) { pressedOn = e.target; }, true);
        document.addEventListener('click', function (e) {
            const target = e.target;
            if (target !== pressedOn || !(target instanceof Element) || !isOverlay(target) || !isOpen(target)) return;
            if (sweetAlertOpen() || target.hasAttribute('data-static')) return;
            closeOverlay(target);
        });

        // Scroll lock + sticky header/buttons, re-checked whenever a pop-up
        // opens, closes or changes size.
        function boxOf(overlay) {
            return overlay.querySelector(BOXES);
        }

        function refresh() {
            const open = openOverlays();
            document.documentElement.classList.toggle('ui-modal-open', open.length > 0);
            open.forEach(function (overlay) {
                const box = boxOf(overlay);
                if (!box) return;
                // Never wider than the screen, whatever the box's own width.
                const room = document.documentElement.clientWidth - 2 * (parseFloat(getComputedStyle(overlay).paddingLeft) || 8);
                if (box.getBoundingClientRect().width > room + 1) {
                    box.style.maxWidth = room + 'px';
                    box.style.width = room + 'px';
                }
                const style = getComputedStyle(box);
                const scrolls = box.scrollHeight > box.clientHeight + 2;
                box.classList.toggle('ui-modal-scrolls', scrolls);
                if (!scrolls) return;
                let background = style.backgroundColor;
                const alpha = background.match(/rgba\([^)]*,\s*([\d.]+)\)/);
                if (background === 'transparent' || (alpha && Number(alpha[1]) < 0.9)) background = '#0f172a';
                box.style.setProperty('--ui-modal-bg', background);
                box.style.setProperty('--ui-pad-top', style.paddingTop);
                box.style.setProperty('--ui-pad-bottom', style.paddingBottom);
            });
        }

        let queued = false;
        let settleTimer = null;
        // Checked now, and once more after a pop-up's opening animation.
        function queueRefreshAndSettle() {
            queueRefresh();
            clearTimeout(settleTimer);
            settleTimer = setTimeout(queueRefresh, 350);
        }
        function queueRefresh() {
            if (queued) return;
            queued = true;
            requestAnimationFrame(function () {
                queued = false;
                refresh();
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            new MutationObserver(queueRefreshAndSettle).observe(document.body, {
                subtree: true, childList: true, attributes: true, attributeFilter: ['class', 'style', 'hidden'],
            });
            window.addEventListener('resize', queueRefresh);
            // Pop-ups that fade in are only "open" once the fade finishes.
            document.addEventListener('transitionend', queueRefresh, true);
            document.addEventListener('animationend', queueRefresh, true);
            queueRefresh();
        });

        // On-screen keyboard: keep the focused field visible inside a pop-up.
        document.addEventListener('focusin', function (e) {
            const field = e.target;
            if (!(field instanceof HTMLElement) || !field.matches('input, select, textarea')) return;
            if (!field.closest(OVERLAYS) || window.innerWidth > 899) return;
            setTimeout(function () { field.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }, 300);
        });
    })();
</script>
