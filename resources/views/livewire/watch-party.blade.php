<div>
    @if ($embedUrl)
        <div class="flex flex-col gap-3"
             x-data="hbRoom({ embedUrl: @js($embedUrl), myId: @js((int) auth()->id()) })"
             wire:poll.3000ms.keep-alive="poll">

            {{-- header --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight">Sala</h1>
                    <span class="text-xs text-zinc-500" x-text="status"></span>
                </div>

                {{-- Nova sessão (opens a modal; the reset itself needs >=80% agreement) --}}
                <div x-data="{ open: false }">
                    <flux:button size="sm" variant="subtle" icon="arrow-path" @click="open = true">
                        Nova sessão
                    </flux:button>

                    <div x-show="open" x-cloak @keydown.escape.window="open = false"
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div @click.outside="open = false"
                             class="w-full max-w-sm rounded-xl border border-zinc-200 bg-white p-4 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                            <h2 class="text-lg font-semibold">Iniciar nova sessão</h2>
                            <p class="mt-1 text-sm text-zinc-500">
                                Isso recria o navegador para todos. Precisa da concordância de pelo menos 80% de quem está na sala.
                            </p>

                            <div class="mt-3 flex flex-col gap-2 text-sm">
                                <label class="flex items-center justify-between gap-3">
                                    FPS
                                    <select x-model.number="fps"
                                            class="rounded border border-zinc-300 bg-transparent px-2 py-1 dark:border-zinc-600">
                                        <option value="24">24</option>
                                        <option value="30">30</option>
                                        <option value="60">60</option>
                                    </select>
                                </label>
                                <label class="flex items-center justify-between gap-3">
                                    Qualidade base
                                    <select x-model="baseResolution"
                                            class="rounded border border-zinc-300 bg-transparent px-2 py-1 dark:border-zinc-600">
                                        <option value="960x540">540p</option>
                                        <option value="1280x720">720p</option>
                                        <option value="1920x1080">1080p</option>
                                    </select>
                                </label>
                            </div>

                            <div class="mt-4 flex justify-end gap-2">
                                <flux:button size="sm" variant="ghost" @click="open = false">Cancelar</flux:button>
                                <flux:button size="sm" variant="primary" @click="requestReset(); open = false">
                                    Propor
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- pending reset vote banner --}}
            @if ($resetRequestedBy)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-400/60 bg-amber-50 p-3 text-sm dark:bg-amber-950/40">
                    <span>
                        <strong>{{ $resetRequesterName }}</strong> quer uma nova sessão
                        ({{ $resetFps }}fps · {{ $resetResolution }}) —
                        <strong>{{ $resetYes }}/{{ $resetNeeded }}</strong> concordaram.
                    </span>
                    <div class="flex items-center gap-2">
                        @if ($resetRequestedBy === (int) auth()->id())
                            <flux:button size="sm" variant="ghost" wire:click="cancelReset">Cancelar</flux:button>
                        @elseif (is_null($myVote))
                            <flux:button size="sm" variant="subtle" wire:click="voteReset(false)">Não</flux:button>
                            <flux:button size="sm" variant="primary" wire:click="voteReset(true)">Sim</flux:button>
                        @else
                            <span class="text-zinc-500">Você votou: {{ $myVote ? 'Sim' : 'Não' }}</span>
                        @endif
                    </div>
                </div>
            @endif

            {{-- stage: SDK mounts into wire:ignore #screen; overlay blocks the mouse + hides
                 the cursor ONLY over the stream (non-controllers, or when you "travar") --}}
            <div x-ref="stage" class="relative w-full overflow-hidden rounded-xl bg-black aspect-video">
                <div wire:ignore x-ref="screen" class="absolute inset-0"></div>
                <div x-show="showOverlay" x-cloak @contextmenu.prevent
                     class="absolute inset-0 z-10" style="cursor:none"></div>
            </div>

            {{-- control bar --}}
            <div class="flex flex-wrap items-center gap-2 rounded-xl border border-zinc-200 p-2 dark:border-zinc-700">
                <label class="flex items-center gap-2 px-1 text-sm text-zinc-600 dark:text-zinc-300">
                    Volume
                    <input type="range" min="0" max="1" step="0.05" class="w-28 accent-[#17dd62]"
                           x-model.number="volume" @input="applyVolume()" aria-label="Volume">
                </label>

                <flux:button size="sm" variant="subtle" icon="arrows-pointing-out" @click="toggleFullscreen()">
                    Tela cheia
                </flux:button>

                <flux:button size="sm" variant="subtle" @click="travar = ! travar" ::class="travar && 'bg-zinc-200 dark:bg-zinc-700'">
                    <span x-text="travar ? 'Mostrar cursor' : 'Travar mouse'"></span>
                </flux:button>

                @if ($isHost)
                    <flux:button size="sm" variant="subtle" icon="lock-closed" wire:click="toggleLock">
                        {{ $controlLocked ? 'Liberar controle' : 'Só eu controlo' }}
                    </flux:button>
                @endif

                {{-- settings popover --}}
                <div class="relative" x-data="{ open: false }">
                    <flux:button size="sm" variant="subtle" icon="cog-6-tooth" @click="open = ! open">
                        Configurações
                    </flux:button>
                    <div x-show="open" @click.outside="open = false" x-transition x-cloak
                         class="absolute right-0 z-20 mt-1 flex w-64 flex-col gap-2 rounded-xl border border-zinc-200 bg-white p-3 text-sm shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                        <label class="flex items-center justify-between gap-3">
                            Suavizar (mais atraso)
                            <input type="checkbox" x-model="playoutDelay" @change="applyPlayoutDelay()">
                        </label>
                        <label class="flex items-center justify-between gap-3">
                            Pausar vídeo
                            <input type="checkbox" x-model="videoPaused" @change="applyPause()">
                        </label>
                        <label class="flex items-center justify-between gap-3">
                            Qualidade <span class="text-xs text-zinc-500">(todos)</span>
                            <select x-model="resolution" @change="applyResolution()"
                                    class="rounded border border-zinc-300 bg-transparent px-1 py-0.5 dark:border-zinc-600">
                                <option style="color:black" value="960x540">540p</option>
                                <option style="color:black" value="1280x720">720p</option>
                                <option style="color:black" value="1920x1080">1080p</option>
                            </select>
                        </label>
                        <label class="flex items-center justify-between gap-3">
                            FPS <span class="text-xs text-zinc-500">(nova sessão)</span>
                            <select x-model.number="fps"
                                    class="rounded border border-zinc-300 bg-transparent px-1 py-0.5 dark:border-zinc-600">
                                <option style="color:black" value="24">24</option>
                                <option style="color:black" value="30">30</option>
                                <option style="color:black" value="60">60</option>
                            </select>
                        </label>
                        <button type="button" @click="reconnect()"
                                class="text-left text-zinc-600 hover:underline dark:text-zinc-300">
                            Reconectar
                        </button>
                    </div>
                </div>
            </div>

            {{-- presence: who's watching right now --}}
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1.5">
                    @foreach ($members as $m)
                        <button type="button" wire:key="member-{{ $m['id'] }}"
                                wire:click="pfpClick({{ $m['id'] }})"
                                title="{{ $m['name'] }}{{ $m['isController'] ? ' (no controle)' : '' }}"
                                class="relative h-9 w-9 flex-shrink-0 rounded-full ring-2 transition
                                       {{ $m['isController'] ? 'ring-emerald-500' : 'ring-zinc-600' }}
                                       {{ $m['visible'] ? '' : 'opacity-40 grayscale' }}">
                            <img src="{{ asset('storage/' . ($m['picture'] ?: 'default-profile.png')) }}"
                                 alt="{{ $m['name'] }}"
                                 class="h-full w-full rounded-full object-cover" />
                            @if ($m['isController'])
                                <span class="absolute -bottom-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-emerald-600 text-white ring-2 ring-white dark:ring-zinc-900">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                                         stroke-linecap="round" stroke-linejoin="round" class="h-2.5 w-2.5">
                                        <rect x="6" y="3" width="12" height="18" rx="6" />
                                        <line x1="12" y1="7" x2="12" y2="11" />
                                    </svg>
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
            <span class="text-xs text-zinc-500">
                @if ($controlLocked)
                    Controle travado pelo host
                @else
                    Clique na sua foto para assumir o controle
                @endif
            </span>
        </div>
    @elseif (! $configured)
        <p class="text-sm text-zinc-500">
            Configure <code>HYPERBEAM_API_KEY</code> no <code>.env</code> para ativar a Sala.
        </p>
    @else
        <p class="text-sm text-zinc-500">
            Não foi possível iniciar a Sala agora. Tente novamente com “Nova sessão”.
        </p>
    @endif
</div>
