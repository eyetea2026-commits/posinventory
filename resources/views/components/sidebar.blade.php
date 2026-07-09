{{--
    Admin sidebar navigation.
    Visual spec: fixed 240px, #0F172A background, #273449 active state,
    40px item height, 16px horizontal padding, 8px radius, Public Sans font.
    Mobile/tablet (< lg): off-canvas drawer, opened via the `sidebarOpen`
    Alpine state on <body> (see admin/layout.blade.php) and an overlay.
    Desktop (>= lg): a normal flex column in `.dashboard-container`'s row,
    sticky at the top of the viewport so it stays exactly one viewport
    tall (never grows with page content) while the page scrolls past it.
--}}
<aside
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="sidebar fixed inset-y-0 left-0 z-40 flex w-60 shrink-0 flex-col bg-[#0F172A] font-['Public_Sans',_Inter,_sans-serif] transition-transform duration-200 ease-in-out lg:sticky lg:top-0 lg:h-screen lg:z-auto"
>
    {{-- Brand / system name --}}
    <div class="flex h-[70px] shrink-0 items-center gap-3 border-b border-white/5 px-4">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-500/15 text-blue-400">
            <x-icon name="camera" class="h-5 w-5" />
        </div>
        <div class="min-w-0 leading-tight">
            <p class="truncate text-sm font-semibold text-white">POS Inventory System</p>
        </div>
        <button
            type="button"
            @click="sidebarOpen = false"
            class="ml-auto shrink-0 text-gray-500 transition-colors duration-200 hover:text-white lg:hidden"
        >
            <x-icon name="x" class="h-5 w-5" />
            <span class="sr-only">Close sidebar</span>
        </button>
    </div>

    {{-- Navigation --}}
    <nav class="sidebar-scroll flex-1 overflow-y-auto px-3 py-4">
        @foreach ($sections() as $section)
            @if ($section['label'])
                <p class="mb-2 mt-6 px-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-gray-500">
                    {{ $section['label'] }}
                </p>
            @endif

            <div class="flex flex-col gap-1">
                @foreach ($section['items'] as $item)
                    @php $isActive = request()->routeIs($item['pattern']); @endphp
                    <a
                        href="{{ route($item['route']) }}"
                        class="group flex h-10 items-center gap-3 rounded-lg px-4 text-sm transition-colors duration-200 {{ $isActive ? 'bg-[#273449] font-medium text-white' : 'text-gray-300 hover:bg-white/5' }}"
                    >
                        <x-icon
                            name="{{ $item['icon'] }}"
                            class="h-[18px] w-[18px] shrink-0 {{ $isActive ? 'text-white' : 'text-gray-400 group-hover:text-gray-200' }}"
                        />
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    {{-- Logout --}}
    <div class="shrink-0 border-t border-white/5 p-3">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="flex h-10 w-full items-center gap-3 rounded-lg px-4 text-sm text-gray-300 transition-colors duration-200 hover:bg-white/5"
            >
                <x-icon name="log-out" class="h-[18px] w-[18px] shrink-0 text-gray-400" />
                Logout
            </button>
        </form>
    </div>
</aside>
