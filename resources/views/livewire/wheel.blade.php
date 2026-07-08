<div class="flex flex-col items-center gap-6 pb-8"
     x-data="{
        rotation: @js($initRotation),
        spinning: false,
        showResult: @js($initShowResult),
        spinTo(angle) {
            this.spinning = true;
            const current = ((this.rotation % 360) + 360) % 360;
            const target  = (((360 - angle) % 360) + 360) % 360;
            const delta   = (((target - current) % 360) + 360) % 360;
            this.rotation += (5 * 360) + delta;
            setTimeout(() => { this.showResult = true; }, 4200);
        }
     }"
     @wheel-spin.window="spinTo($event.detail.targetAngle)"
     wire:poll.3000ms="poll">

    <div class="w-full flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight">Roleta de Filmes</h1>
        <div class="flex items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
            <span>{{ $presentCount }} na página</span>
            <span class="text-zinc-300 dark:text-zinc-600">·</span>
            <span style="color:{{ $readyCount >= 2 ? 'rgb(23,221,98)' : 'inherit' }}">
                {{ $readyCount }}/2 prontos
            </span>
        </div>
    </div>

    @if (empty($segments))
        <div class="flex flex-col items-center justify-center py-24 gap-3 text-zinc-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-lg">Nenhum filme na watchlist ainda.</p>
        </div>
    @else
        <div class="flex flex-col items-center gap-4 w-full max-w-lg">

            <svg id="wheel-svg" viewBox="0 0 400 400"
                 style="width:100%; max-width:500px; display:block; filter:drop-shadow(0 4px 24px rgba(0,0,0,0.4));">

                <g id="wheel-group"
                   :style="`transform-origin:200px 200px; transform:rotate(${rotation}deg); transition:${spinning ? 'transform 4s cubic-bezier(0.17,0.67,0.12,0.99)' : 'none'};`">
                    @foreach ($segments as $seg)
                        <path d="{{ $seg['path'] }}"
                              fill="{{ $seg['color'] }}"
                              stroke="rgba(0,0,0,0.25)"
                              stroke-width="1.5" />
                        @if ($seg['showText'])
                            <text text-anchor="middle"
                                  font-size="{{ $seg['fontSize'] }}"
                                  font-weight="600"
                                  fill="rgba(255,255,255,0.95)"
                                  transform="rotate({{ $seg['centerAngle'] - 90 }}, {{ $seg['textX'] }}, {{ $seg['textY'] }})"
                                  style="pointer-events:none; font-family:system-ui,sans-serif;">
                                @if (count($seg['titleLines']) === 2)
                                    <tspan x="{{ $seg['textX'] }}" y="{{ round($seg['textY'] - $seg['fontSize'] * 0.65, 2) }}">{{ $seg['titleLines'][0] }}</tspan>
                                    <tspan x="{{ $seg['textX'] }}" y="{{ round($seg['textY'] + $seg['fontSize'] * 0.65, 2) }}">{{ $seg['titleLines'][1] }}</tspan>
                                @else
                                    <tspan x="{{ $seg['textX'] }}" y="{{ $seg['textY'] }}">{{ $seg['titleLines'][0] }}</tspan>
                                @endif
                            </text>
                        @endif
                    @endforeach

                    <circle cx="200" cy="200" r="180"
                            fill="none"
                            stroke="rgba(255,255,255,0.08)"
                            stroke-width="2" />
                </g>

                <polygon points="193,3 207,3 200,24"
                         fill="rgb(23,221,98)"
                         filter="drop-shadow(0 2px 4px rgba(0,0,0,0.6))" />

                <circle cx="200" cy="200" r="65"
                        fill="rgb(23,221,98)"
                        stroke="rgba(0,0,0,0.15)"
                        stroke-width="2"
                        class="cursor-pointer"
                        wire:click="clickCenter" />

                <text x="200" y="193"
                      text-anchor="middle"
                      dominant-baseline="middle"
                      font-size="22"
                      font-weight="700"
                      fill="#18181b"
                      style="pointer-events:none; font-family:system-ui,sans-serif;">
                    {{ $readyCount }}/2
                </text>

                <text x="200" y="218"
                      text-anchor="middle"
                      dominant-baseline="middle"
                      font-size="11"
                      fill="rgba(0,0,0,0.6)"
                      style="pointer-events:none; font-family:system-ui,sans-serif;">
                    {{ $iAmReady ? 'pronto' : 'clique' }}
                </text>
            </svg>

            @if ($result)
                <div x-show="showResult"
                     x-transition:enter="transition ease-out duration-500"
                     x-transition:enter-start="opacity-0 translate-y-4"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="w-full rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 shadow-xl">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-3">Filme sorteado</p>
                    <a href="{{ route('movies.show', $result['slug']) }}"
                       wire:navigate
                       class="flex items-center gap-4 group">
                        @if ($result['poster'])
                            <img src="{{ asset('storage/' . $result['poster']) }}"
                                 alt="{{ $result['title'] }}"
                                 class="w-16 rounded-lg object-cover flex-shrink-0"
                                 style="aspect-ratio:2/3;" />
                        @else
                            <div class="w-16 rounded-lg bg-zinc-800 flex-shrink-0" style="aspect-ratio:2/3;"></div>
                        @endif
                        <div>
                            <h2 class="text-lg font-bold group-hover:underline" style="color:rgb(23,221,98)">
                                {{ $result['title'] }}
                            </h2>
                            <p class="text-sm text-zinc-400 mt-0.5">Ver filme →</p>
                        </div>
                    </a>
                </div>
            @endif

            <div class="w-full mt-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-2">Filmes na watchlist</p>
                <div class="flex flex-col gap-1">
                    @foreach ($segments as $seg)
                        <div class="flex items-center gap-2 text-sm">
                            <span style="width:0.75rem; height:0.75rem; border-radius:2px; flex-shrink:0; background:{{ $seg['color'] }};"></span>
                            <span class="truncate text-zinc-700 dark:text-zinc-300">{{ $seg['title'] }}</span>
                            <span class="ml-auto text-zinc-400 text-xs flex-shrink-0">{{ $seg['user_name'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    @endif
</div>
