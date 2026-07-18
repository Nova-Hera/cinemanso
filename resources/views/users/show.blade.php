<x-layouts.app :title="$user->name">
    <div class="flex flex-col gap-8">

        {{-- Profile header --}}
        <div style="display:flex; align-items:center; gap:1.5rem;">
            <div style="flex-shrink:0; width:4rem; height:4rem; border-radius:9999px; overflow:hidden;">
                <img src="{{ asset('storage/' . ($user->profile_picture ?: 'default-profile.png')) }}" alt="{{ $user->name }}" style="width:100%; height:100%; object-fit:cover;" />
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ $user->name }}</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $user->username }}</p>
            </div>
        </div>

        @if($media !== null)
            <div style="display:flex; justify-content:space-between; padding:1rem 0; border-bottom:1px solid rgb(228 228 231);" class="dark:border-zinc-700">
                <div class="text-center">
                    <div class="text-4xl font-bold tabular-nums" style="color:rgb(23,221,98)">
                        {{ rtrim(rtrim(number_format($mediana, 2, '.', ''), '0'), '.') }}
                    </div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mt-1">Mediana</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold tabular-nums" style="color:rgb(23,221,98)">
                        {{ rtrim(rtrim(number_format($media, 2, '.', ''), '0'), '.') }}
                    </div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mt-1">Média</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold tabular-nums" style="color:rgb(23,221,98)">{{ $moda }}</div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mt-1">Moda</div>
                </div>
            </div>
        @else
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Ainda sem reviews.</p>
        @endif

        @if($user->reviews->isNotEmpty())
            <div class="flex flex-col gap-4">
                <h2 class="text-lg font-semibold">Reviews</h2>

                @foreach ($user->reviews->sortByDesc('created_at') as $review)
                    <a href="{{ route('movies.show', $review->movie->slug) }}" wire:navigate class="block">
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 transition-colors"
                             style="border-left:4px solid rgb(0,123,24)">
                            <div style="display:flex; align-items:flex-start; gap:1rem;">

                                @if ($review->movie->poster)
                                    <div style="flex-shrink:0; width:3rem; height:4.5rem; overflow:hidden; border-radius:0.375rem;">
                                        <img src="{{ asset('storage/' . $review->movie->poster) }}"
                                             alt="{{ $review->movie->title }}"
                                             style="width:100%; height:100%; object-fit:cover;" />
                                    </div>
                                @endif

                                <div style="flex:1; min-width:0;">
                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.375rem;">
                                        <span class="font-semibold text-sm truncate">{{ $review->movie->title }}</span>
                                        <span class="text-sm font-bold tabular-nums flex-shrink-0 ml-4" style="color:rgb(23,221,98)">
                                            ★ {{ number_format($review->rating, 1) }}
                                        </span>
                                    </div>
                                    <x-rich-text :text="$review->content" />
                                    <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-2">{{ $review->created_at->format('d/m/Y') }}</p>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

    </div>
</x-layouts.app>
