<x-layouts.app :title="__('Filmes')">
    <div class="flex flex-col gap-6">

        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold tracking-tight">Filmes</h1>
            <form method="GET" action="{{ route('movies.index') }}" class="flex items-center gap-2">
                <select name="status" onchange="this.form.submit()"
                        style="background:transparent; border:1px solid rgb(161,161,170); border-radius:0.5rem; padding:0.4rem 0.75rem; font-size:0.875rem; color:inherit; cursor:pointer;">
                    <option style="color:black" value="" @selected(!$currentStatus)>Todos</option>
                    <option style="color:black" value="watchlist" @selected($currentStatus === 'watchlist')>Watchlist</option>
                    <option style="color:black" value="watching"  @selected($currentStatus === 'watching')>Assistindo</option>
                    <option style="color:black" value="watched"   @selected($currentStatus === 'watched')>Visto</option>
                </select>
                <select name="sort" onchange="this.form.submit()"
                        style="background:transparent; border:1px solid rgb(161,161,170); border-radius:0.5rem; padding:0.4rem 0.75rem; font-size:0.875rem; color:inherit; cursor:pointer;">
                    <option style="color:black" value="title"   @selected($currentSort === 'title')>Alfabético</option>
                    <option style="color:black" value="media"   @selected($currentSort === 'media')>Média</option>
                    <option style="color:black" value="mediana" @selected($currentSort === 'mediana')>Mediana</option>
                    <option style="color:black" value="moda"    @selected($currentSort === 'moda')>Moda</option>
                </select>
            </form>
        </div>

        @if ($movies->isEmpty())
            <div class="flex flex-col items-center justify-center py-24 gap-3 text-zinc-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                </svg>
                <p class="text-lg">Nenhum filme por aqui ainda.</p>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                @foreach ($movies as $movie)
                    @php
                        $ratingValues = $movie->reviews->pluck('rating');
                        $displayRating = null;
                        if ($ratingValues->isNotEmpty()) {
                            $displayRating = match($currentSort) {
                                'mediana' => (function() use ($ratingValues) {
                                    $s = $ratingValues->sort()->values();
                                    $c = $s->count(); $mid = (int)($c / 2);
                                    return $c % 2 === 0 ? ($s[$mid-1] + $s[$mid]) / 2 : $s[$mid];
                                })(),
                                'moda'    => $ratingValues->countBy()->sortDesc()->keys()->first(),
                                default   => round($ratingValues->avg(), 2),
                            };
                        }
                    @endphp
                    <x-movie-card
                        :id="$movie->slug"
                        :title="$movie->title"
                        :image="$movie->poster"
                        :status="$movie->status"
                        :rating="$displayRating"
                        :wire-navigate="true"
                    />
                @endforeach
            </div>
        @endif

    </div>
</x-layouts.app>
