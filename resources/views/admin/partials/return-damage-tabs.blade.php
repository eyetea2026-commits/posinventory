{{-- Return & Damage is one module (one sidebar item): Return Approval and
     Damage are its two tabs, in the same toggle style as Discounts & Promos.
     A damage record mostly starts as an approved return (Factory Defect /
     Damaged Product), so the admin follows it from one tab to the next. --}}
<style>
    .rd-tabs { display: inline-flex; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(148, 163, 184, 0.15); border-radius: 12px; padding: 4px; gap: 4px; margin-bottom: 20px; flex-wrap: wrap; }
    .rd-tab { display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 9px; color: #94a3b8; font-weight: 600; font-size: 0.88rem; text-decoration: none; transition: all 0.2s ease; white-space: nowrap; }
    .rd-tab:hover { color: #e2e8f0; }
    .rd-tab.active { background: linear-gradient(135deg, #3b82f6, #10b981); color: #fff; }
</style>
<nav class="rd-tabs" aria-label="Return & Damage">
    <a href="{{ route('admin.sales-returns.index') }}" class="rd-tab {{ request()->routeIs('admin.sales-returns.*') ? 'active' : '' }}" @if(request()->routeIs('admin.sales-returns.*')) aria-current="page" @endif>
        <i class="fa-solid fa-rotate-left"></i> Return Approval
    </a>
    <a href="{{ route('admin.damages.index') }}" class="rd-tab {{ request()->routeIs('admin.damages.*') ? 'active' : '' }}" @if(request()->routeIs('admin.damages.*')) aria-current="page" @endif>
        <i class="fa-solid fa-triangle-exclamation"></i> Damage
    </a>
</nav>
