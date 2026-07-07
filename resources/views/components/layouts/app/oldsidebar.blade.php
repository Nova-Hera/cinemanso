<body class="min-h-screen bg-white dark:bg-zinc-800">
    <style>
        /* ===== Rail collapse sizing ===== */
        .app-sidebar {
            width: 18rem;
            transition: width 300ms ease;
            overflow: hidden;
            height: 100vh;
            position: sticky;
            top: 0;
            display: flex;
            flex-direction: column;
        }

        body.sidebar-collapsed .app-sidebar {
            width: 4.5rem; /* 72px rail */
        }

        /* ===== Scroll + footer pinning ===== */
        .sidebar-scroll-area {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-footer {
            flex: 0 0 auto;
        }

        /* ===== Animate text both ways ===== */
        .sidebar-fade {
            display: inline-block;
            overflow: hidden;
            white-space: nowrap;

            max-width: 24rem;
            opacity: 1;
            transform: translateX(0);

            transition:
                opacity 200ms ease,
                transform 200ms ease,
                max-width 300ms ease;
        }

        body.sidebar-collapsed .sidebar-fade {
            max-width: 0;
            opacity: 0;
            transform: translateX(-6px);
            pointer-events: none;
        }

        /* ===== Center everything in rail mode ===== */
        body.sidebar-collapsed .sidebar-center {
            justify-content: center !important;
        }

        /* Tight padding in collapsed mode */
        body.sidebar-collapsed .sidebar-pad {
            padding-left: .25rem !important;
            padding-right: .25rem !important;
        }

        /* ===== Fix: home button centering in rail mode ===== */
        .sidebar-nav-item {
            width: 100%;
        }

        body.sidebar-collapsed .sidebar-nav-item {
            justify-content: center !important;
            gap: 0 !important;
        }

        /* ===== “recém-acessados” label -> divider bar when collapsed ===== */
        .recent-divider {
            margin: .5rem 0 .25rem;
            padding: 0 .25rem;
        }

        .recent-divider-line {
            display: none;
            width: 100%;
            border-top: 1px solid rgb(228 228 231); /* zinc-200 */
        }

        .dark .recent-divider-line {
            border-top-color: rgb(63 63 70); /* zinc-700 */
        }

        body.sidebar-collapsed .recent-divider-line {
            display: block;
        }

        /* ===== REAL logo fix (x-app-logo already has text) =====
           The logo text is below because the component likely uses a column/grid.
           Force the component’s root container to be a horizontal flex row. */
        .app-logo-inline {
            display: inline-flex !important;
            align-items: center !important;
            flex-direction: row !important;
            gap: .5rem !important;
            white-space: nowrap !important;
        }

        /* Handle common internal wrappers */
        .app-logo-inline :where(div, span, a) {
            display: inline-flex !important;
            align-items: center !important;
            flex-direction: row !important;
            gap: .5rem !important;
        }

        .app-logo-inline svg,
        .app-logo-inline img {
            flex: 0 0 auto !important;
            display: block !important;
        }
    </style>

    <flux:sidebar
        sticky
        stashable
        class="app-sidebar border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
    >
        {{-- TOP BAR --}}
        <div class="sidebar-pad flex items-center gap-2 px-2 py-2 sidebar-center">
            <button
                id="sidebarToggle"
                type="button"
                class="inline-flex items-center justify-center rounded-md p-2 hover:bg-zinc-200/60 dark:hover:bg-zinc-800"
                aria-label="Toggle sidebar"
            >
                ☰
            </button>

            {{-- Logo fades out in collapsed mode (and fades back in) --}}
            <a href="{{ route('home') }}" class="flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <span class="sidebar-fade">
                    <x-app-logo class="app-logo-inline" />
                </span>
            </a>
        </div>

        {{-- SCROLLABLE MIDDLE --}}
        <div class="sidebar-scroll-area sidebar-pad px-2">
            <flux:navlist variant="outline">
                {{-- REMOVE THE HEADING COMPLETELY: no :heading attribute --}}
                <flux:navlist.group class="grid">
                    <flux:navlist.item
                        icon="home"
                        :href="route('home')"
                        :current="request()->routeIs('home')"
                        wire:navigate
                        class="sidebar-nav-item sidebar-center"
                    >
                        <span class="sidebar-fade">{{ __('Home') }}</span>
                    </flux:navlist.item>

                    {{-- “recém-acessados” after Home; becomes divider bar when collapsed --}}
                    <div class="recent-divider">
                        <span class="sidebar-fade text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            recém-acessados
                        </span>
                        <div class="recent-divider-line"></div>
                    </div>
                </flux:navlist.group>
            </flux:navlist>
        </div>

        {{-- FOOTER PINNED TO BOTTOM --}}
        <div class="sidebar-footer sidebar-pad px-2 py-2">

            {{-- Desktop User Menu pinned at bottom --}}
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <button
                    type="button"
                    class="mt-2 flex w-full items-center gap-2 rounded-md p-2 hover:bg-zinc-200/60 dark:hover:bg-zinc-800 sidebar-center"
                >
                    <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                        <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                            {{ auth()->check() ? auth()->user()->initials() : 'C' }}
                        </span>
                    </span>

                    {{-- Name/email now fade OUT and fade IN (animated both ways) --}}
                    <span class="sidebar-fade min-w-0 text-left">
                        <span class="block truncate text-sm font-semibold">
                            {{ auth()->check() ? auth()->user()->name : __('Convidado') }}
                        </span>
                        <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">
                            {{ auth()->check() ? auth()->user()->email : __('Email') }}
                        </span>
                    </span>
                </button>

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                        {{ auth()->check() ? auth()->user()->initials() : 'C' }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">
                                        {{ auth()->check() ? auth()->user()->name : __('Convidado') }}
                                    </span>
                                    <span class="truncate text-xs">
                                        {{ auth()->check() ? auth()->user()->email : __('Email') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>
                            {{ __('Configurações') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Sair') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </div>
    </flux:sidebar>

    {{-- Mobile header toggle --}}
    <flux:header class="lg:hidden">
        <button
            id="sidebarToggleMobile"
            type="button"
            class="inline-flex items-center justify-center rounded-md p-2 hover:bg-zinc-200/60 dark:hover:bg-zinc-800"
            aria-label="Toggle sidebar"
        >
            ☰
        </button>

        <flux:spacer />
    </flux:header>

    {{ $slot }}

    <script>
        (function () {
            const KEY = 'sidebarCollapsed';

            function setCollapsed(collapsed) {
                document.body.classList.toggle('sidebar-collapsed', collapsed);
                localStorage.setItem(KEY, collapsed ? '1' : '0');
            }

            function toggle() {
                setCollapsed(!document.body.classList.contains('sidebar-collapsed'));
            }

            setCollapsed(localStorage.getItem(KEY) === '1');

            document.getElementById('sidebarToggle')?.addEventListener('click', toggle);
            document.getElementById('sidebarToggleMobile')?.addEventListener('click', toggle);
        })();
    </script>
</body>
