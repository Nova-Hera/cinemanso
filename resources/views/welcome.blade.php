<x-layouts.app :title="__('Home')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">

        @if (empty($items))
            <div class="flex h-full items-center justify-center">
                <p class="text-center text-lg text-neutral-500 dark:text-neutral-400">
                    Nenhum filme ou usuário encontrado.
                </p>
            </div>
        @else
            <div class="grid gap-4 grid-cols-4">
                @foreach ($items as $item)
                    @if ($item['type'] === 'movie')
                        @php $movie = $item['model']; @endphp
                        <x-movie-card
                            :id="$movie->slug"
                            :title="$movie->title"
                            :image="$movie->poster"
                            :wire-navigate="true"
                        />
                    @else
                        @php $user = $item['model']; @endphp
                        <x-user-card
                            :username="$user->username"
                            :title="$user->name"
                            :image="$user->profile_picture"
                            :wire-navigate="true"
                        />
                    @endif
                @endforeach
            </div>
        @endif

        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
        </div>
    </div>
</x-layouts.app>
