<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    <script>if(localStorage.getItem('sidebarCollapsed')==='1'){document.documentElement.classList.add('sidebar-collapsed-init')}</script>
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
    <style>
        html { height: 100%; }
        body { height: 100%; overflow: hidden; }
        *:has(> [data-flux-main]) { height: 100%; }
        [data-flux-main] { overflow-y: auto; }

        .app-sidebar {
            width: 18rem;
            transition: width 300ms ease;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        body.sidebar-collapsed .app-sidebar,
        html.sidebar-collapsed-init .app-sidebar {
            width: 4.5rem;
        }

        html.sidebar-collapsed-init .sidebar-fade {
            max-width: 0 !important;
            opacity: 0 !important;
            transform: translateX(-6px) !important;
            pointer-events: none !important;
            transition: none !important;
        }

        html.sidebar-collapsed-init .sidebar-center {
            justify-content: center !important;
        }

        html.sidebar-collapsed-init .sidebar-pad {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        html.sidebar-collapsed-init .sidebar-nav-item {
            width: 2.5rem !important;
            height: 2.5rem !important;
            justify-self: center !important;
            margin-left: auto !important;
            margin-right: auto !important;
            padding: 0 !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 0 !important;
            line-height: 1 !important;
            text-align: center !important;
            transition: none !important;
        }

        html.sidebar-collapsed-init .sidebar-nav-item svg {
            display: block !important;
        }

        html.sidebar-collapsed-init .sidebar-nav-item [data-content] {
            display: none !important;
        }

        html.sidebar-collapsed-init .toggle-icon {
            transform: rotate(180deg);
            transition: none !important;
        }

        html.sidebar-collapsed-init .recent-divider-line {
            display: block;
            margin: 0 auto;
            width: 50%;
        }

        .sidebar-scroll-area {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-footer {
            flex: 0 0 auto;
        }

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

        body.sidebar-collapsed .sidebar-center {
            justify-content: center !important;
        }

        body.sidebar-collapsed .sidebar-pad {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .sidebar-nav-item {
            width: 100%;
            transition: all 200ms ease;
        }

        body.sidebar-collapsed .sidebar-nav-item {
            width: 2.5rem !important;
            height: 2.5rem !important;
            justify-self: center !important;
            margin-left: auto !important;
            margin-right: auto !important;
            padding: 0 !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 0 !important;
            line-height: 1 !important;
            text-align: center !important;
        }
        body.sidebar-collapsed .sidebar-nav-item svg {
            display: block !important;
        }

        body.sidebar-collapsed .sidebar-nav-item [data-content] {
            display: none !important;
        }

        /* ── Mobile overlay (viewport < 768px) ───────────── */
        .sidebar-mobile-backdrop {
            display: none;
        }

        @media (max-width: 767px) {
            .app-sidebar {
                display: none !important;
            }

            body.sidebar-mobile-open .app-sidebar {
                display: flex !important;
                flex-direction: column !important;
                position: fixed !important;
                left: 0 !important;
                top: 0 !important;
                width: 4.5rem !important;
                height: 100dvh !important;
                z-index: 50 !important;
                overflow: hidden !important;
            }

            body.sidebar-mobile-open .sidebar-mobile-backdrop {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.35);
                z-index: 40;
            }

            #sidebarToggle {
                display: none !important;
            }

            .sidebar-fade {
                max-width: 0 !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }

            .sidebar-nav-item [data-content] {
                display: none !important;
            }

            .sidebar-nav-item {
                width: 2.5rem !important;
                height: 2.5rem !important;
                justify-self: center !important;
                margin-left: auto !important;
                margin-right: auto !important;
                padding: 0 !important;
                justify-content: center !important;
                align-items: center !important;
                gap: 0 !important;
            }

            .sidebar-pad {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            .sidebar-center {
                justify-content: center !important;
            }
        }

        .toggle-icon {
            transition: transform 300ms ease;
        }

        body.sidebar-collapsed .toggle-icon {
            transform: rotate(180deg);
        }

        .recent-divider {
            margin: .5rem 0 .25rem;
            padding: 0 .25rem;
        }

        .recent-divider-line {
            display: none;
            width: 100%;
            border-top: 1px solid rgb(228 228 231);
        }

        .dark .recent-divider-line {
            border-top-color: rgb(63 63 70);
        }

        body.sidebar-collapsed .recent-divider-line {
            display: block;
            margin: 0 auto;
            width: 50%;
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: .5rem;
            min-width: 0;
        }

        .brand-row :is(svg, img) {
            flex: 0 0 auto;
        }

        .brand-row .brand-text {
            min-width: 0;
        }

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

        [x-cloak] { display: none !important; }
    </style>

    {{-- Top header bar --}}
    <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="md:hidden" icon="bars-2" inset="left" />

        <a href="{{ route('home') }}" class="ms-2 me-5 flex items-center space-x-2 rtl:space-x-reverse lg:ms-0" wire:navigate>
            <x-app-logo />
        </a>

        <flux:spacer />

        <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
            <flux:tooltip :content="__('Search')" position="bottom">
                <flux:navbar.item as="button" class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" :label="__('Search')" onclick="Livewire.dispatch('open-search')" />
            </flux:tooltip>
        </flux:navbar>

        <flux:dropdown position="top" align="end">
            <button type="button" class="cursor-pointer rounded-lg p-1 hover:bg-zinc-200/60 dark:hover:bg-zinc-800">
                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                    @auth
                        <img src="{{ asset('storage/' . (auth()->user()->profile_picture ?: 'default-profile.png')) }}" alt="{{ auth()->user()->name }}" class="h-full w-full object-cover rounded-lg" />
                    @else
                        <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white text-xs font-semibold">C</span>
                    @endauth
                </span>
            </button>
            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                @auth
                                    <img src="{{ asset('storage/' . (auth()->user()->profile_picture ?: 'default-profile.png')) }}" alt="{{ auth()->user()->name }}" class="h-full w-full object-cover rounded-lg" />
                                @else
                                    <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">C</span>
                                @endauth
                            </span>
                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()->check() ? auth()->user()->name : __('Convidado') }}</span>
                                <span class="truncate text-xs">{{ auth()->check() ? auth()->user()->email : __('') }}</span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>
                <flux:menu.separator />
                @auth
                <flux:menu.radio.group>
                    <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Configurações') }}</flux:menu.item>
                </flux:menu.radio.group>
                <flux:menu.separator />
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                        Sair
                    </flux:menu.item>
                </form>
                @else
                <flux:menu.item :href="route('register')" icon="user-plus" wire:navigate class="w-full">
                    Registrar
                </flux:menu.item>
                @endauth
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{-- Left sidebar --}}
    <flux:sidebar
        sticky
        class="app-sidebar border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
    >
        <div class="sidebar-pad flex items-center gap-2 px-2 py-2 sidebar-center">
            <button
                id="sidebarToggle"
                type="button"
                class="inline-flex items-center justify-center rounded-md p-2 text-zinc-500 hover:bg-zinc-200/60 dark:text-zinc-400 dark:hover:bg-zinc-800"
                aria-label="Toggle sidebar"
            >
                <svg class="toggle-icon h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </button>

            <a href="{{ route('home') }}" class="brand-row min-w-0" wire:navigate>
                <span class="sidebar-fade brand-row min-w-0">
                    <x-app-logo />
                </span>
            </a>
        </div>

        <div class="sidebar-scroll-area sidebar-pad px-2">
            <flux:navlist variant="outline">
                <flux:navlist.group class="grid">
                    <flux:navlist.item
                        icon="home"
                        :href="route('home')"
                        :current="request()->routeIs('home')"
                        wire:navigate
                        class="sidebar-nav-item"
                    >
                        <span class="sidebar-fade">Home</span>
                    </flux:navlist.item>

                    @if(auth()->check())
                    <flux:navlist.item
                        icon="plus-circle"
                        href="#"
                        onclick="event.preventDefault(); Livewire.dispatch('open-new-movie')"
                        class="sidebar-nav-item"
                    >
                        <span class="sidebar-fade">Novo Filme</span>
                    </flux:navlist.item>
                    @endif

                    <div class="recent-divider">
                        <span class="sidebar-fade text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            Recém-acessados
                        </span>
                        <div class="recent-divider-line"></div>
                    </div>

                    @php
                        $recentItems = session('recent_items', []);
                        $movieIds = array_column(array_filter($recentItems, fn($i) => $i['type'] === 'movie'), 'id');
                        $userIds  = array_column(array_filter($recentItems, fn($i) => $i['type'] === 'user'),  'id');
                        $moviesMap = \App\Models\Movie::whereIn('id', $movieIds)->get()->keyBy('id');
                        $usersMap  = \App\Models\User::whereIn('id', $userIds)->get()->keyBy('id');
                    @endphp
                    @foreach ($recentItems as $item)
                        @if ($item['type'] === 'movie' && isset($moviesMap[$item['id']]))
                            @php $rm = $moviesMap[$item['id']]; @endphp
                            <flux:navlist.item icon="film" :href="route('movies.show', $rm->slug)"
                                :current="request()->routeIs('movies.show') && request()->route('movie')?->id === $rm->id"
                                wire:navigate class="sidebar-nav-item">
                                <span class="sidebar-fade">{{ $rm->title }}</span>
                            </flux:navlist.item>
                        @elseif ($item['type'] === 'user' && isset($usersMap[$item['id']]))
                            @php $ru = $usersMap[$item['id']]; @endphp
                            <flux:navlist.item icon="user" :href="route('users.show', $ru->username)"
                                :current="request()->routeIs('users.show') && request()->route('user')?->id === $ru->id"
                                wire:navigate class="sidebar-nav-item">
                                <span class="sidebar-fade">{{ $ru->name }}</span>
                            </flux:navlist.item>
                        @endif
                    @endforeach
                </flux:navlist.group>
            </flux:navlist>
        </div>

        <div class="sidebar-footer sidebar-pad px-2 py-2">
            <flux:dropdown position="top" align="end">
                <button
                    type="button"
                    class="sidebar-nav-item mt-2 flex items-center gap-2 rounded-md p-2 hover:bg-zinc-200/60 dark:hover:bg-zinc-800"
                >
                    <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                        @auth
                            <img src="{{ asset('storage/' . (auth()->user()->profile_picture ?: 'default-profile.png')) }}" alt="{{ auth()->user()->name }}" class="h-full w-full object-cover rounded-lg" />
                        @else
                            <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">C</span>
                        @endauth
                    </span>
                    <span class="sidebar-fade min-w-0 text-left">
                        <span class="block truncate text-sm font-semibold">
                            {{ auth()->check() ? auth()->user()->name : 'Convidado' }}
                        </span>
                        <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">
                            {{ auth()->check() ? auth()->user()->email : 'convidado@email.com' }}
                        </span>
                    </span>
                </button>

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    @auth
                                        <img src="{{ asset('storage/' . (auth()->user()->profile_picture ?: 'default-profile.png')) }}" alt="{{ auth()->user()->name }}" class="h-full w-full object-cover rounded-lg" />
                                    @else
                                        <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">C</span>
                                    @endauth
                                </span>
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">
                                        {{ auth()->check() ? auth()->user()->name : __('Convidado') }}
                                    </span>
                                    <span class="truncate text-xs">
                                        {{ auth()->check() ? auth()->user()->email : __('') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    @auth
                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Configurações') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Sair
                        </flux:menu.item>
                    </form>
                    @else
                    <flux:menu.item :href="route('register')" icon="user-plus" wire:navigate class="w-full">
                        Registrar
                    </flux:menu.item>
                    @endauth
                </flux:menu>
            </flux:dropdown>
        </div>
    </flux:sidebar>

    <div class="sidebar-mobile-backdrop" id="sidebarMobileBackdrop"></div>

    {{ $slot }}

    <livewire:search-modal />
    <livewire:new-movie-modal />

    @fluxScripts

    <script>
        (function () {
            var KEY = 'sidebarCollapsed';

            function setCollapsed(collapsed, skipTransition) {
                var sidebar = document.querySelector('.app-sidebar');
                if (skipTransition && sidebar) sidebar.style.transition = 'none';
                document.documentElement.classList.remove('sidebar-collapsed-init');
                document.body.classList.toggle('sidebar-collapsed', collapsed);
                if (skipTransition && sidebar) {
                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () {
                            var s = document.querySelector('.app-sidebar');
                            if (s) s.style.transition = '';
                        });
                    });
                }
                localStorage.setItem(KEY, collapsed ? '1' : '0');
            }

            // Apply current state (runs on initial load; also safe on script re-execution)
            setCollapsed(localStorage.getItem(KEY) === '1', true);

            // Guard so re-execution never registers duplicate listeners
            if (!window.__sidebarInit) {
                window.__sidebarInit = true;

                // Event delegation survives Livewire DOM morphing
                document.addEventListener('click', function (e) {
                    if (e.target.closest('#sidebarToggle')) {
                        setCollapsed(!document.body.classList.contains('sidebar-collapsed'), false);
                    }
                });

                // After SPA navigation — re-apply state without transition
                document.addEventListener('livewire:navigated', function () {
                    setCollapsed(localStorage.getItem(KEY) === '1', true);
                });

                document.addEventListener('flux-sidebar-toggle', function () {
                    document.body.classList.toggle('sidebar-mobile-open');
                });

                document.getElementById('sidebarMobileBackdrop')?.addEventListener('click', function () {
                    document.body.classList.remove('sidebar-mobile-open');
                });
            }
        })();
    </script>
</body>
</html>
