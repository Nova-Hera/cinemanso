
<x-layouts.app :title="__('Home')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <!-- each of these divs will be movies -->

            <!-- <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div> -->
            @php
                $movies = \App\Models\Movie::latest()->take(6)->get();
            @endphp
            @if ($movies->isEmpty())
                <div class="col-span-full flex h-full items-center justify-center">
                    <p class="text-center text-lg text-neutral-500 dark:text-neutral-400">
                        {{ __('Nenhum filme encontrado.') }}
                    </p>
                </div>
            @endif
            
            @foreach ($movies as $movie)
                <x-movie-card
                    class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700"
                    :id="$movie->slug"
                    :title="$movie->title"
                    :image="$movie->poster"
                    :description="$movie->description"
                    :wire-navigate="true"
                />
            @endforeach
        </div>
        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
        </div>
    </div>
</x-layouts.app>
