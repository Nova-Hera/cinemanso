<x-layouts.app>
    <div class="h-full w-full p-4 flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold text-zinc-800 dark:text-zinc-100">
                Assistir Juntos (Neko)
            </h1>
            @if ($src)
                <a href="{{ $src }}" target="_blank" rel="noopener"
                    class="text-xs text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 underline">
                    Abrir em nova aba ↗
                </a>
            @endif
        </div>

        @if ($src)
            <div
                class="relative flex-1 w-full overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700 bg-black min-h-[500px]">
                <iframe src="{{ $src }}" class="absolute inset-0 w-full h-full border-0"
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
