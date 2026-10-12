{{-- Searchable dropdowns for every <select> on admin and cashier pages.
     Clicking (or pressing Enter / Space / Alt+Down / Down on) a select opens
     this panel instead of the browser's native list: a search box at the
     top, then the select's own options, filtered as you type. Picking an
     option sets the real <select>'s value and fires its normal "input" and
     "change" events, so every existing filter, form, and onchange handler
     keeps working unchanged. The panel is rebuilt from the select's current
     options each time it opens, so options added or replaced by JavaScript
     (e.g. brands that follow the chosen category) always show up, and
     selects injected later by AJAX modals work too (event delegation).
     Opt out on a specific select with data-native-select. --}}
<style>
    .ss-panel {
        position: fixed; z-index: 2147483000; display: flex; flex-direction: column;
        background: #1e293b; border: 1px solid rgba(148, 163, 184, 0.3); border-radius: 10px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45); overflow: hidden; font-family: inherit;
    }
    .ss-search-wrap { position: relative; padding: 8px; border-bottom: 1px solid rgba(148, 163, 184, 0.18); background: #1e293b; }
    .ss-search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.8rem; pointer-events: none; }
    .ss-search {
        width: 100%; box-sizing: border-box; padding: 8px 10px 8px 30px; border-radius: 8px;
        border: 1px solid rgba(148, 163, 184, 0.3); background: #0f172a; color: #f1f5f9;
        font-size: 0.88rem; outline: none; font-family: inherit;
    }
    .ss-search:focus { border-color: #3b82f6; }
    .ss-search::placeholder { color: #64748b; }
    .ss-list { list-style: none; margin: 0; padding: 4px 0; overflow-y: auto; }
    .ss-option { padding: 8px 12px; color: #e2e8f0; font-size: 0.88rem; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ss-option.ss-selected { color: #93c5fd; font-weight: 600; }
    .ss-option.ss-active { background: #3b82f6; color: #fff; }
    .ss-option.ss-disabled { color: #64748b; cursor: not-allowed; }
    .ss-group { padding: 8px 12px 4px; color: #94a3b8; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
    .ss-empty { padding: 10px 12px; color: #94a3b8; font-size: 0.85rem; }
    .ss-list::-webkit-scrollbar { width: 6px; }
    .ss-list::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }
</style>
<script>
    (function () {
        if (window.__searchableSelect) return;
        window.__searchableSelect = true;

        let panel = null;      // the open panel element
        let current = null;    // the <select> it belongs to
        let items = [];        // visible option rows: { el, option }
        let activeIndex = -1;

        function eligible(el) {
            return el instanceof HTMLSelectElement && !el.multiple && !(el.size > 1)
                && !el.disabled && !el.hasAttribute('data-native-select');
        }

        function close(returnFocus) {
            if (!panel) return;
            panel.remove();
            const select = current;
            panel = null; current = null; items = []; activeIndex = -1;
            if (returnFocus && select) select.focus({ preventScroll: true });
        }

        function choose(option) {
            if (!option || option.disabled) return;
            const select = current;
            const changed = select.value !== option.value || select.selectedIndex !== option.index;
            select.selectedIndex = option.index;
            close(true);
            if (changed) {
                select.dispatchEvent(new Event('input', { bubbles: true }));
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        function setActive(index) {
            if (activeIndex >= 0 && items[activeIndex]) items[activeIndex].el.classList.remove('ss-active');
            activeIndex = index;
            const item = items[activeIndex];
            if (item) {
                item.el.classList.add('ss-active');
                item.el.scrollIntoView({ block: 'nearest' });
            }
        }

        function moveActive(step) {
            if (!items.length) return;
            let i = activeIndex;
            for (let n = 0; n < items.length; n++) {
                i = (i + step + items.length) % items.length;
                if (!items[i].option.disabled) { setActive(i); return; }
            }
        }

        function render(list, query) {
            list.innerHTML = '';
            items = []; activeIndex = -1;
            const q = query.trim().toLowerCase();
            let lastGroup = null;
            Array.from(current.options).forEach(function (option) {
                if (option.hidden) return;
                const text = option.text;
                if (q && !text.toLowerCase().includes(q)) return;
                const group = option.parentElement && option.parentElement.tagName === 'OPTGROUP' ? option.parentElement : null;
                if (group && group !== lastGroup) {
                    const header = document.createElement('li');
                    header.className = 'ss-group';
                    header.textContent = group.label;
                    list.appendChild(header);
                }
                lastGroup = group;
                const li = document.createElement('li');
                li.className = 'ss-option';
                li.textContent = text || ' ';
                li.title = text;
                if (option.disabled) li.classList.add('ss-disabled');
                if (option.index === current.selectedIndex) li.classList.add('ss-selected');
                const index = items.length;
                li.addEventListener('mousemove', function () { if (activeIndex !== index && !option.disabled) setActive(index); });
                li.addEventListener('mousedown', function (e) { e.preventDefault(); });
                li.addEventListener('click', function () { choose(option); });
                list.appendChild(li);
                items.push({ el: li, option: option });
            });
            if (!items.length) {
                const empty = document.createElement('li');
                empty.className = 'ss-empty';
                empty.textContent = 'No results found';
                list.appendChild(empty);
                return;
            }
            // Highlight the current selection when it's visible, else the first match.
            const selected = items.findIndex(function (it) { return it.option.index === current.selectedIndex; });
            if (selected >= 0 && !q) setActive(selected); else moveActive(1);
        }

        function position() {
            if (!panel || !current) return;
            const rect = current.getBoundingClientRect();
            const width = Math.max(rect.width, 200);
            const spaceBelow = window.innerHeight - rect.bottom - 8;
            const spaceAbove = rect.top - 8;
            const openUp = spaceBelow < 220 && spaceAbove > spaceBelow;
            const maxHeight = Math.max(160, Math.min(360, openUp ? spaceAbove : spaceBelow));
            panel.style.width = width + 'px';
            panel.style.maxHeight = maxHeight + 'px';
            panel.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)) + 'px';
            if (openUp) { panel.style.top = ''; panel.style.bottom = (window.innerHeight - rect.top + 4) + 'px'; }
            else { panel.style.bottom = ''; panel.style.top = (rect.bottom + 4) + 'px'; }
        }

        function open(select) {
            close(false);
            current = select;
            panel = document.createElement('div');
            panel.className = 'ss-panel';
            panel.setAttribute('role', 'listbox');
            panel.innerHTML = '<div class="ss-search-wrap"><i class="fas fa-search ss-search-icon"></i>'
                + '<input type="text" class="ss-search" placeholder="Search..." autocomplete="off" aria-label="Search options"></div>'
                + '<ul class="ss-list"></ul>';
            const search = panel.querySelector('.ss-search');
            const list = panel.querySelector('.ss-list');
            document.body.appendChild(panel);
            render(list, '');
            position();

            search.addEventListener('input', function () { render(list, search.value); });
            search.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') { e.preventDefault(); moveActive(1); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); moveActive(-1); }
                else if (e.key === 'Enter') { e.preventDefault(); if (items[activeIndex]) choose(items[activeIndex].option); }
                else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(true); }
                else if (e.key === 'Tab') { close(false); }
            });
            search.focus({ preventScroll: true });
        }

        // Open our panel instead of the native list.
        document.addEventListener('mousedown', function (e) {
            const select = e.target instanceof Element ? e.target.closest('select') : null;
            if (select && eligible(select) && e.button === 0) {
                e.preventDefault();
                if (current === select) { close(true); return; }
                select.focus({ preventScroll: true });
                open(select);
                return;
            }
            if (panel && !panel.contains(e.target)) close(false);
        }, true);

        document.addEventListener('keydown', function (e) {
            const select = e.target;
            if (!eligible(select) || panel) return;
            const opens = e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown' || e.key === 'F4' || (e.altKey && e.key === 'ArrowDown');
            if (opens) { e.preventDefault(); open(select); }
        }, true);

        // Keep the panel attached to its select; close it if the select goes away.
        window.addEventListener('resize', function () { close(false); });
        document.addEventListener('scroll', function (e) {
            if (!panel) return;
            if (panel.contains(e.target)) return;
            if (!document.body.contains(current)) { close(false); return; }
            position();
        }, true);
    })();
</script>
