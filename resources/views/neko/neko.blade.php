<x-layouts.app>
    <div class="h-full w-full p-4 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold text-zinc-800 dark:text-zinc-100">
                Assistir Juntos (Neko)
            </h1>
            @if ($src)
                <div class="flex items-center gap-3">
                    @if (auth()->user()?->is_admin)
                        <form method="POST" action="{{ route('neko.reload') }}"
                            onsubmit="return confirm('Reiniciar a sessão? Todo mundo vai reconectar.')">
                            @csrf
                            <flux:button type="submit" size="sm" icon="arrow-path"
                                style="background:rgb(0,123,24);color:#fff;border:none">
                                Recarregar Sessão
                            </flux:button>
                        </form>
                    @endif
                    <a href="{{ $src }}" target="_blank" rel="noopener"
                        class="text-xs text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 underline">
                        Abrir em nova aba ↗
                    </a>
                </div>
            @endif
        </div>

        @if (session('neko_status'))
            <p @class([
                'mb-4 rounded-xl px-3 py-2 text-sm',
                'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => session('neko_ok'),
                'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' => !session('neko_ok'),
            ])>
                {{ session('neko_status') }}
            </p>
        @endif

        @if ($src)
            {{-- An admin reset kicks everyone, so remount the embed to re-login.
                 about:blank first: cross-origin blocks contentWindow.reload(), and
                 reassigning an identical src is not reliably a navigation. --}}
            <div x-data
                @neko-reload.window="
                    const f = $refs.frame;
                    f.src = 'about:blank';
                    requestAnimationFrame(() => f.src = f.dataset.src);
                "
                class="relative flex-1 w-full overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 bg-black min-h-[500px]">
                <iframe x-ref="frame" data-src="{{ $src }}" src="{{ $src }}"
                    class="absolute inset-0 w-full h-full border-0"
                    allow="autoplay; clipboard-read; clipboard-write; fullscreen; microphone; camera; display-capture"
                    allowfullscreen>
                </iframe>
            </div>
        @else
            <p class="text-sm text-zinc-500">
                Configure <code>NEKO_URL</code> no <code>.env</code> para ativar a Sala.
            </p>
        @endif
    </div>
</x-layouts.app>
