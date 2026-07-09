<x-layouts.app :title="$movie->title">
    <div class="flex flex-col gap-8">

        <div style="display:flex; gap:2.5rem; align-items:flex-start;">

            <div style="flex-shrink:0; width:8rem;">
                <div style="width:8rem; height:12rem; overflow:hidden; border:1px solid rgb(255 255 255)" class="dark:border-zinc-700">
                    @if ($movie->poster)
                        <img src="{{ asset('storage/' . $movie->poster) }}"
                             alt="{{ $movie->title }}"
                             style="width:100%; height:100%; object-fit:cover" />
                    @else
                        <div class="w-full h-full bg-zinc-800 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                            </svg>
                        </div>
                    @endif
                </div>

                <dl class="mt-4 space-y-2 text-sm">
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 dark:text-zinc-400">DIRETOR</dt>
                        <dd class="text-zinc-800 dark:text-zinc-200">{{ $movie->director ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 dark:text-zinc-400">GÊNERO</dt>
                        <dd class="text-zinc-800 dark:text-zinc-200">{{ !empty($movie->genres) ? implode(', ', $movie->genres) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 dark:text-zinc-400">LANÇAMENTO</dt>
                        <dd class="text-zinc-800 dark:text-zinc-200">{{ $movie->release_date ? $movie->release_date->format('d/m/Y') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 dark:text-zinc-400">ASSISTIDO EM</dt>
                        <dd class="text-zinc-800 dark:text-zinc-200">{{ $movie->watched_at ? $movie->watched_at->format('d/m/Y') : '—' }}</dd>
                    </div>
                    @if ($movie->description)
                        <div class="pt-1">
                            <dt class="text-xs font-semibold tracking-wide text-zinc-500 dark:text-zinc-400">SINOPSE</dt>
                            <dd class="text-zinc-700 dark:text-zinc-300 leading-relaxed mt-1">{{ $movie->description }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="flex-1 min-w-0 flex flex-col gap-6">

                @php
                    $medalColor = fn ($r) => $r == 1 ? 'color:rgb(190,160,0)' : ($r == 2 ? 'color:rgb(192,192,192)' : ($r == 3 ? 'color:rgb(184,115,51)' : null));
                    $statusBadge = match ($movie->status) {
                        'watched'   => ['label' => 'Visto',      'style' => 'background:rgb(23,221,98);color:#000'],
                        'watching'  => ['label' => 'Assistindo', 'style' => 'background:rgb(217,119,6);color:#fff'],
                        default     => ['label' => 'Watchlist',  'style' => 'background:rgb(82,82,91);color:#fff'],
                    };
                @endphp
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-4 flex-wrap">
                        <h1 class="text-3xl font-black leading-tight tracking-tight" style="color:rgb(23,221,98);">{{ $movie->title }}</h1>
                        <span style="{{ $statusBadge['style'] }}; border-radius:9999px; padding:0.2rem 0.6rem; font-size:0.75rem; font-weight:600; flex-shrink:0;">
                            {{ $statusBadge['label'] }}
                        </span>
                        
                        @auth
                            <div class="ml-auto flex-shrink-0">
                                <flux:button size="sm" icon="pencil-square" variant="ghost" inset
                                             onclick="Livewire.dispatch('open-edit-movie')" />
                            </div>
                        @endauth
                    </div>
                    @if ($ranking !== null)
                        <div class="text-base text-zinc-500 dark:text-zinc-400 flex-shrink-0">
                            Ranqueado
                            @if ($medalColor($ranking))
                                <span class="font-bold" style="{{ $medalColor($ranking) }}">#{{ $ranking }}/{{ $totalRated }}</span>
                            @else
                                <span>#{{ $ranking }}/{{ $totalRated }}</span>
                            @endif
                        </div>
                    @else
                        <div class="text-sm text-zinc-400 dark:text-zinc-500 flex-shrink-0">Sem ranking</div>
                    @endif
                    @if (!empty($genreRankings))
                        <div class="text-sm text-zinc-500 dark:text-zinc-400 flex flex-wrap gap-x-2 gap-y-0.5">
                            @foreach ($genreRankings as $g => $rank)
                                <span>Em {{ $g }}:
                                    @if ($medalColor($rank))
                                        <span class="font-bold" style="{{ $medalColor($rank) }}">#{{ $rank }}/{{ $genreTotals[$g] }}</span>
                                    @else
                                        <span>#{{ $rank }}/{{ $genreTotals[$g] }}</span>
                                    @endif
                                </span>
                                @if (!$loop->last)<span class="text-zinc-300 dark:text-zinc-600">·</span>@endif
                            @endforeach
                        </div>
                    @endif
                </div>
                @if ($media !== null)
                    <div style="display:flex; justify-content:space-between; padding:1rem 0; border-bottom:1px solid rgb(228 228 231);" class="dark:border-zinc-700">
                        <div class="text-center">
                            <div class="text-4xl font-bold tabular-nums" style="color:rgb(23,221,98)">
                                {{ rtrim(rtrim(number_format($mediana, 2, '.', ''), '0'), '.') }}
                            </div>
                            <div class="text-xs font-semibold tracking-wide text-zinc-500 dark:text-zinc-400 mt-1">MEDIANA</div>
                        </div>    
                        <div class="text-center">
                            <div class="text-4xl font-bold tabular-nums" style="color:rgb(23,221,98)">
                                {{ rtrim(rtrim(number_format($media, 2, '.', ''), '0'), '.') }}
                            </div>
                            <div class="text-xs font-semibold tracking-wide text-zinc-500 dark:text-zinc-400 mt-1">MÉDIA</div>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold tabular-nums" style="color:rgb(23,221,98)">
                                {{ $moda }}
                            </div>
                            <div class="text-xs font-semibold tracking-wide text-zinc-500 dark:text-zinc-400 mt-1">MODA</div>
                        </div>
                    </div>
                @else
                    <div style="padding:1rem 0; border-bottom:1px solid rgb(228 228 231);" class="dark:border-zinc-700">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Ainda sem avaliações.</p>
                    </div>
                @endif

                <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">

                    @auth
                        <div x-data="{ showCreateForm: false, showEditForm: false }">

                            @if ($userReview === null)
                                <flux:button
                                    @click="showCreateForm = true"
                                    style="background:rgb(0,123,24);color:#fff;border:none"
                                    class="hover:opacity-90 transition-opacity"
                                >
                                    Nova review
                                </flux:button>
                            @else
                                <flux:button @click="showEditForm = true">
                                    Editar review
                                </flux:button>
                            @endif

                            <div class="fixed inset-0 bg-black/50 z-40"
                                 x-cloak
                                 x-show="showCreateForm || showEditForm"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 @click="showCreateForm = false; showEditForm = false">
                            </div>

                            @if ($userReview === null)
                                <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-50
                                            w-full max-w-md rounded-xl border border-zinc-200 dark:border-zinc-700
                                            bg-white dark:bg-zinc-900 p-6 shadow-xl"
                                     x-cloak
                                     x-show="showCreateForm"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 scale-95"
                                     @click.stop>
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-semibold">Escrever review</h3>
                                        <flux:button size="sm" icon="x-mark" variant="ghost" inset @click="showCreateForm = false" />
                                    </div>
                                    <form method="POST" action="{{ route('reviews.store') }}" style="display:flex; flex-direction:column; gap:1.5rem;">
                                        @csrf
                                        <input type="hidden" name="movie_id" value="{{ $movie->id }}">
                                        <flux:input type="number" name="rating" max="10" min="0" step="0.1" label="Nota" placeholder="8.5" required />
                                        <flux:textarea name="content" label="Review" placeholder="O que você achou?" required />
                                        <flux:button type="submit" variant="primary" class="w-full" style="background:rgb(0,123,24);color:#fff;border:none">Publicar</flux:button>
                                    </form>
                                </div>
                            @endif

                            @if ($userReview !== null)
                                <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-50
                                            w-full max-w-md rounded-xl border border-zinc-200 dark:border-zinc-700
                                            bg-white dark:bg-zinc-900 p-6 shadow-xl"
                                     x-cloak
                                     x-show="showEditForm"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 scale-95"
                                     @click.stop>
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-semibold">Editar review</h3>
                                        <flux:button size="sm" icon="x-mark" variant="ghost" inset @click="showEditForm = false" />
                                    </div>
                                    <form method="POST" action="{{ route('reviews.update', $userReview->id) }}" style="display:flex; flex-direction:column; gap:1.5rem;">
                                        @csrf
                                        @method('PUT')
                                        <flux:input type="number" name="rating" max="10" min="0" step="0.1" label="Nota" :value="$userReview->rating" required />
                                        <flux:textarea class="hover:bg-sky-700" name="content" label="Review" required>{{ $userReview->content }}</flux:textarea>
                                        <flux:button type="submit" variant="primary" class="w-full" style="background:rgb(0,123,24);color:#fff;border:none">Salvar</flux:button>
                                    </form>
                                </div>
                            @endif

                        </div>

                        <livewire:edit-movie-modal :movie="$movie" />
                    @endauth

                </div>

                @if ($movie->reviews->isNotEmpty())
                    <div class="flex flex-col gap-3">
                        @foreach ($movie->reviews as $review)
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700
                                        bg-white dark:bg-zinc-900 p-4 border-l-4"
                                 style="border-left-color:rgb(0,123,24)">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <img src="{{ asset('storage/' . ($review->user->profile_picture ?: 'default-profile.png')) }}" alt="{{ $review->user->name }}" class="h-7 w-7 rounded-full object-cover flex-shrink-0" />
                                        <span class="font-semibold text-sm"><a href="{{route('users.show',['user'=>$review->user->username])}}">{{ $review->user->name }}</a></span>
                                    </div>
                                    <span class="text-sm font-bold tabular-nums" style="color:rgb(23,221,98)">
                                        ★ {{ number_format($review->rating, 1) }}
                                    </span>
                                </div>
                                <p class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed whitespace-pre-line">{{ $review->content }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>
        </div>

    </div>
</x-layouts.app>
