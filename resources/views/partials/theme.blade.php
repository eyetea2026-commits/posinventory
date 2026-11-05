{{-- Light Mode and Dark Mode for the admin and cashier screens.

     The screens are designed dark, with their colors written straight into
     each page's CSS. Light Mode is drawn from those same styles: every
     stylesheet and <style> block on the page (including ones added later by
     live updates) gets a matching light rule, and inline style="" colors are
     swapped in place:
       - dark surfaces become white and light gray, light text becomes dark
         text, gray borders and shadows are softened;
       - accent colors (blue buttons, green/red/amber badges) keep their
         color, and text on a solid accent background stays as it is;
       - light accent text (#93c5fd on dark) is darkened so it reads on white.
     Each light rule has the same selector and specificity as the rule it
     comes from (the theme check sits in :where()) and is placed right after
     that stylesheet, so the pages' own cascade decides exactly as in Dark
     Mode. Dark Mode is the original styling, so nothing is converted while
     it is on. The choice is remembered per browser (localStorage
     "cctv-theme").

     Include it last in <head>, after every stylesheet, so the page never
     shows dark before turning light. Toggle buttons: any element with
     data-theme-toggle; .theme-label-light / .theme-label-dark inside it show
     the mode it switches to. Pages listening for the "themechange" event
     can redraw what CSS can't reach (the dashboard charts). --}}
<style>
    html[data-theme="light"][data-theme-pending] body { opacity: 0 !important; }
    html[data-theme="light"] .theme-label-light,
    html:not([data-theme="light"]) .theme-label-dark { display: none !important; }
</style>
<script>
    (function () {
        if (window.__themeEngine) return;
        window.__themeEngine = true;

        var STORAGE_KEY = 'cctv-theme';
        var THEME_CHECK = ':where([data-theme="light"])';
        var root = document.documentElement;

        function savedTheme() {
            try {
                return localStorage.getItem(STORAGE_KEY) === 'light' ? 'light' : 'dark';
            } catch (e) {
                return 'dark';
            }
        }

        // ---------- colors ----------

        function rgbToHsl(r, g, b) {
            r /= 255; g /= 255; b /= 255;
            var max = Math.max(r, g, b), min = Math.min(r, g, b), l = (max + min) / 2, h = 0, s = 0;
            if (max !== min) {
                var d = max - min;
                s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                h = max === r ? (g - b) / d + (g < b ? 6 : 0) : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
                h /= 6;
            }
            return [h, s, l];
        }

        function hslToRgb(h, s, l) {
            if (s === 0) return [l * 255, l * 255, l * 255];
            function hue(p, q, t) {
                if (t < 0) t += 1;
                if (t > 1) t -= 1;
                if (t < 1 / 6) return p + (q - p) * 6 * t;
                if (t < 1 / 2) return q;
                if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
                return p;
            }
            var q = l < 0.5 ? l * (1 + s) : l + s - l * s, p = 2 * l - q;
            return [hue(p, q, h + 1 / 3) * 255, hue(p, q, h) * 255, hue(p, q, h - 1 / 3) * 255];
        }

        // Dark surface lightness -> light surface lightness. The page itself
        // (#020617) becomes light gray, cards and inputs (#0a1223) white, and
        // the lighter dark grays (table headers, hovers) light grays again.
        var SURFACE = [[0, 0.93], [0.05, 0.95], [0.085, 1], [0.12, 0.972], [0.16, 0.952], [0.25, 0.91], [0.35, 0.86], [0.5, 0.78]];

        function surfaceLightness(l) {
            for (var i = 1; i < SURFACE.length; i++) {
                if (l <= SURFACE[i][0]) {
                    var a = SURFACE[i - 1], b = SURFACE[i];
                    return a[1] + (b[1] - a[1]) * (l - a[0]) / (b[0] - a[0]);
                }
            }
            return SURFACE[SURFACE.length - 1][1];
        }

        function isNeutral(r, g, b) {
            return (Math.max(r, g, b) - Math.min(r, g, b)) / 255 < 0.2;
        }

        // One color, for the role it plays: bg (surface), text, border or
        // shadow. Returns [r, g, b, alpha] with alpha as written (or null).
        function mapColor(rgb, alpha, role, keepText) {
            var r = rgb[0], g = rgb[1], b = rgb[2];
            var hsl = rgbToHsl(r, g, b), h = hsl[0], s = hsl[1], l = hsl[2];
            var neutral = isNeutral(r, g, b);
            var l2 = l, s2 = s;

            var opacity = alpha === null ? 1 : parseFloat(alpha);
            if (isNaN(opacity)) opacity = 1;

            if (role === 'bg') {
                if (neutral) {
                    // Solid white surfaces (the pop-up dialogs) are already
                    // light; only a faint white wash (lightening a dark
                    // card) turns into a faint dark one.
                    if (l <= 0.5) l2 = surfaceLightness(l);
                    else if (opacity < 0.5) l2 = 1 - l;
                } else if (l < 0.3) {
                    l2 = 0.93 - 0.2 * l;
                    s2 = Math.min(s, 0.7);
                }
            } else if (role === 'text') {
                if (keepText) return null;
                if (neutral) {
                    // Light text turns dark; text that is dark already sits
                    // on a light surface and stays.
                    if (l >= 0.45) {
                        l2 = 1 - l;
                        if (l2 > 0.45) l2 = 0.45 + (l2 - 0.45) * 0.5;
                    }
                } else if (l > 0.55) {
                    l2 = h > 0.1 && h < 0.18 ? 0.34 : 0.42;
                }
            } else if (role === 'border') {
                if (neutral) {
                    l2 = 1 - l;
                } else if (l > 0.7) {
                    l2 = 0.55;
                } else if (l < 0.25) {
                    l2 = 0.8;
                }
            } else if (role === 'shadow') {
                if (!neutral) return null;
                var numeric = alpha === null ? 1 : parseFloat(alpha);
                if (isNaN(numeric) || alpha !== null && /var\(/.test(alpha)) return null;
                return [r, g, b, String(Math.round(numeric * 0.35 * 1000) / 1000)];
            }

            if (l2 === l && s2 === s) return null;
            var out = hslToRgb(h, s2, Math.max(0, Math.min(1, l2)));
            return [out[0], out[1], out[2], alpha];
        }

        function formatColor(c) {
            var rgb = Math.round(c[0]) + ' ' + Math.round(c[1]) + ' ' + Math.round(c[2]);
            return c[3] === null ? 'rgb(' + rgb + ')' : 'rgb(' + rgb + ' / ' + c[3] + ')';
        }

        function parseHex(hex) {
            if (hex.length <= 4) hex = hex.replace(/./g, function (c) { return c + c; });
            var n = [0, 2, 4, 6].map(function (i) { return parseInt(hex.substr(i, 2), 16); });
            return { rgb: n.slice(0, 3), alpha: hex.length === 8 ? String(Math.round(n[3] / 255 * 1000) / 1000) : null };
        }

        function parseRgbArgs(args) {
            var alpha = null, channels = args;
            var slash = args.indexOf('/');
            if (slash !== -1) {
                channels = args.slice(0, slash);
                alpha = args.slice(slash + 1).trim();
            }
            var parts = channels.split(/[\s,]+/).filter(Boolean);
            if (slash === -1 && parts.length === 4) alpha = parts.pop();
            if (parts.length !== 3) return null;
            var rgb = parts.map(function (p) {
                return /%$/.test(p) ? parseFloat(p) * 2.55 : parseFloat(p);
            });
            if (rgb.some(isNaN)) return null;
            return { rgb: rgb, alpha: alpha };
        }

        var COLOR_TOKEN = /#([0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})(?![\w-])|rgba?\(([^()]*(?:\([^()]*\)[^()]*)*)\)|(^|[^\w-])(white|black)(?![\w-])/g;

        // Every color in a CSS value (gradients and shadows have several).
        function transformValue(value, role, keepText) {
            var urls = [];
            var safe = value.replace(/url\([^)]*\)/g, function (u) { urls.push(u); return '\u0000' + (urls.length - 1) + '\u0000'; });
            var changed = false;
            var result = safe.replace(COLOR_TOKEN, function (match, hex, rgbArgs, before, named) {
                var parsed = hex ? parseHex(hex) : rgbArgs !== undefined ? parseRgbArgs(rgbArgs)
                    : { rgb: named === 'white' ? [255, 255, 255] : [0, 0, 0], alpha: null };
                if (!parsed) return match;
                var mapped = mapColor(parsed.rgb, parsed.alpha, role, keepText);
                if (!mapped) return match;
                changed = true;
                return (before || '') + formatColor(mapped);
            });
            if (!changed) return value;
            return result.replace(/\u0000(\d+)\u0000/g, function (m, i) { return urls[i]; });
        }

        // Which role a property's colors play. Custom properties are judged
        // by name: --bg-card is a surface, --text-muted text, --primary an
        // accent (left alone).
        function roleOf(prop) {
            if (prop.indexOf('--') === 0) {
                if (/^--tw-/.test(prop)) {
                    if (/gradient-(from|via|to)/.test(prop)) return 'bg';
                    if (/ring(-offset)?-color/.test(prop)) return 'border';
                    if (/shadow-color/.test(prop)) return 'shadow';
                    return null;
                }
                if (/shadow/.test(prop)) return 'shadow';
                if (/border|outline|divider/.test(prop)) return 'border';
                if (/text|link|muted|heading|fg|foreground/.test(prop)) return 'text';
                if (/bg|background|card|surface|input|hover|glass|overlay|panel|sidebar|backdrop/.test(prop)) return 'bg';
                return null;
            }
            if (/^background/.test(prop)) return 'bg';
            if (prop === 'color' || /^(fill|stroke|caret-color|text-decoration-color|column-rule-color|-webkit-text-fill-color|-webkit-text-stroke-color|text-emphasis-color)$/.test(prop)) return 'text';
            if (/^(border|outline)/.test(prop) && /color|^border$|^outline$|^border-(top|right|bottom|left|block|inline)$/.test(prop)) return 'border';
            if (/shadow$/.test(prop) || prop === 'filter' || prop === 'backdrop-filter') return 'shadow';
            return null;
        }

        var ACCENT_VAR = /var\(--(primary|primary-hover|success|danger|warning|info)\)/;

        // A solid accent background (a blue button, a red badge): its text
        // keeps its color, usually white.
        function hasSolidAccentBackground(style) {
            var bg = [style.getPropertyValue('background-color'), style.getPropertyValue('background-image'), style.getPropertyValue('background')].join(' ');
            if (ACCENT_VAR.test(bg)) return true;
            var solid = false;
            bg.replace(COLOR_TOKEN, function (match, hex, rgbArgs, before, named) {
                var parsed = hex ? parseHex(hex) : rgbArgs !== undefined ? parseRgbArgs(rgbArgs) : null;
                if (!parsed) return match;
                var alpha = parsed.alpha === null ? 1 : parseFloat(parsed.alpha);
                var l = rgbToHsl(parsed.rgb[0], parsed.rgb[1], parsed.rgb[2])[2];
                if (!isNeutral(parsed.rgb[0], parsed.rgb[1], parsed.rgb[2]) && l >= 0.3 && l <= 0.8 && (isNaN(alpha) || alpha >= 0.6)) solid = true;
                return match;
            });
            return solid;
        }

        // ---------- stylesheets ----------

        var processedSheets = typeof WeakSet === 'function' ? new WeakSet() : null;

        function isGenerated(node) {
            return !!(node && node.nodeType === 1 && node.hasAttribute('data-theme-generated'));
        }

        // Same selector, same specificity, only matching in Light Mode.
        function lightSelector(selectorText) {
            var parts = [], depth = 0, start = 0;
            for (var i = 0; i < selectorText.length; i++) {
                var ch = selectorText[i];
                if (ch === '(' || ch === '[') depth++;
                else if (ch === ')' || ch === ']') depth--;
                else if (ch === ',' && depth === 0) {
                    parts.push(selectorText.slice(start, i));
                    start = i + 1;
                }
            }
            parts.push(selectorText.slice(start));
            return parts.map(function (sel) {
                sel = sel.trim();
                if (/^:root(?![\w-])/.test(sel)) return ':root' + THEME_CHECK + sel.slice(5);
                if (/^html(?![\w-])/.test(sel)) return 'html' + THEME_CHECK + sel.slice(4);
                return ':where(html[data-theme="light"]) ' + sel;
            }).join(',');
        }

        function lightDeclarations(style) {
            var keepText = hasSolidAccentBackground(style);
            var out = [];
            for (var i = 0; i < style.length; i++) {
                var prop = style[i];
                var value = style.getPropertyValue(prop);
                if (!value) continue;
                var important = style.getPropertyPriority(prop) ? ' !important' : '';
                if (prop === 'color-scheme') {
                    if (/dark/.test(value)) out.push('color-scheme: light' + important);
                    continue;
                }
                var role = roleOf(prop);
                if (!role) continue;
                var light = transformValue(value, role, keepText);
                if (light !== value) out.push(prop + ': ' + light + important);
            }
            return out.join('; ');
        }

        function lightRules(rules) {
            var css = '';
            for (var i = 0; i < rules.length; i++) {
                var rule = rules[i];
                try {
                    if (rule.selectorText !== undefined && rule.style) {
                        var declarations = lightDeclarations(rule.style);
                        if (declarations) css += lightSelector(rule.selectorText) + ' { ' + declarations + ' }\n';
                    } else if (rule.media && rule.cssRules) {
                        var inMedia = lightRules(rule.cssRules);
                        if (inMedia) css += '@media ' + rule.media.mediaText + ' {\n' + inMedia + '}\n';
                    } else if (rule.conditionText !== undefined && rule.cssRules) {
                        var inSupports = lightRules(rule.cssRules);
                        if (inSupports) css += '@supports ' + rule.conditionText + ' {\n' + inSupports + '}\n';
                    } else if (rule.styleSheet) {
                        css += lightRules(safeRules(rule.styleSheet));
                    }
                } catch (e) { /* one odd rule never stops the rest */ }
            }
            return css;
        }

        function safeRules(sheet) {
            try {
                return sheet.cssRules || [];
            } catch (e) {
                return []; // another site's stylesheet (Font Awesome): no page colors there
            }
        }

        // The light rules go in a style element right after the stylesheet
        // they come from.
        function processSheet(sheet) {
            if (!sheet || isGenerated(sheet.ownerNode)) return;
            if (processedSheets) {
                if (processedSheets.has(sheet)) return;
                processedSheets.add(sheet);
            }
            var css = lightRules(safeRules(sheet));
            if (!css) return;
            var light = document.createElement('style');
            light.setAttribute('data-theme-generated', '');
            light.textContent = css;
            var owner = sheet.ownerNode;
            if (owner && owner.parentNode) {
                owner.parentNode.insertBefore(light, owner.nextSibling);
            } else {
                document.head.appendChild(light);
            }
        }

        function processAllSheets() {
            Array.prototype.slice.call(document.styleSheets).forEach(processSheet);
        }

        function processStyleNode(node) {
            if (isGenerated(node)) return;
            if (node.sheet) {
                processSheet(node.sheet);
            } else if (node.tagName === 'LINK') {
                node.addEventListener('load', function () { processSheet(node.sheet); });
            }
        }

        // ---------- inline style="" ----------

        var inlineApplied = typeof WeakMap === 'function' ? new WeakMap() : null;
        var inlineOriginal = typeof WeakMap === 'function' ? new WeakMap() : null;

        function processElement(el) {
            if (!inlineApplied || !el.style || !el.style.length) return;
            var applied = inlineApplied.get(el) || {};
            var original = inlineOriginal.get(el) || {};
            var keepText = hasSolidAccentBackground(el.style);
            var props = [];
            for (var i = 0; i < el.style.length; i++) props.push(el.style[i]);
            props.forEach(function (prop) {
                // Inline custom properties are set by scripts from colors
                // already on screen (--ui-modal-bg), so they're light already.
                if (prop.indexOf('--') === 0) return;
                var value = el.style.getPropertyValue(prop);
                if (!value || applied[prop] === value) return;
                var role = roleOf(prop);
                if (!role) return;
                var light = transformValue(value, role, keepText);
                if (light === value) return;
                var priority = el.style.getPropertyPriority(prop);
                original[prop] = { value: value, priority: priority };
                el.style.setProperty(prop, light, priority);
                applied[prop] = el.style.getPropertyValue(prop);
            });
            inlineApplied.set(el, applied);
            inlineOriginal.set(el, original);
        }

        function processTree(node) {
            if (node.nodeType !== 1) return;
            if (node.tagName === 'STYLE' || (node.tagName === 'LINK' && /stylesheet/i.test(node.rel))) {
                processStyleNode(node);
                return;
            }
            if (node.hasAttribute('style')) processElement(node);
            node.querySelectorAll('style, link[rel~="stylesheet"]').forEach(processStyleNode);
            node.querySelectorAll('[style]').forEach(processElement);
        }

        function restoreInlineStyles() {
            if (!inlineOriginal) return;
            document.querySelectorAll('[style]').forEach(function (el) {
                var original = inlineOriginal.get(el);
                if (!original) return;
                Object.keys(original).forEach(function (prop) {
                    el.style.setProperty(prop, original[prop].value, original[prop].priority);
                });
                inlineOriginal.delete(el);
                inlineApplied.delete(el);
            });
        }

        var observer = null;

        function watchPage() {
            if (observer || typeof MutationObserver !== 'function') return;
            observer = new MutationObserver(function (mutations) {
                if (root.getAttribute('data-theme') !== 'light') return;
                mutations.forEach(function (m) {
                    if (m.type === 'attributes') {
                        processElement(m.target);
                    } else {
                        m.addedNodes.forEach(processTree);
                    }
                });
            });
            observer.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] });
        }

        function convertPage() {
            processAllSheets();
            if (document.body) processTree(document.body);
            watchPage();
        }

        // ---------- switching ----------

        function apply(theme) {
            root.setAttribute('data-theme', theme);
            if (theme === 'light') {
                convertPage();
            } else {
                restoreInlineStyles();
            }
        }

        window.getTheme = function () {
            return root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
        };

        window.setTheme = function (theme) {
            theme = theme === 'light' ? 'light' : 'dark';
            try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) { /* private window: this page only */ }
            apply(theme);
            document.dispatchEvent(new CustomEvent('themechange', { detail: { theme: theme } }));
        };

        document.addEventListener('click', function (e) {
            var toggle = e.target.closest && e.target.closest('[data-theme-toggle]');
            if (!toggle) return;
            e.preventDefault();
            window.setTheme(window.getTheme() === 'light' ? 'dark' : 'light');
        });

        var theme = savedTheme();
        root.setAttribute('data-theme', theme);
        if (theme === 'light') {
            // Hidden until the whole page is converted, never longer than 3s.
            root.setAttribute('data-theme-pending', '');
            var reveal = function () { root.removeAttribute('data-theme-pending'); };
            setTimeout(reveal, 3000);
            try {
                processAllSheets();
                watchPage();
            } catch (e) { reveal(); }
            document.addEventListener('DOMContentLoaded', function () {
                try { convertPage(); } finally { reveal(); }
            });
        }
    })();
</script>
