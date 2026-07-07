<?php

use App\Models\Movie;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public bool $open = false;
    public string $query = '';
    public array $movies = [];
    public array $users = [];

    #[On('open-search')]
    public function openModal(): void
    {
        $this->open = true;
        $this->query = '';
        $this->movies = [];
        $this->users = [];
    }

    public function updatedQuery(): void
    {
        if (strlen(trim($this->query)) < 2) {
            $this->movies = [];
            $this->users = [];
            return;
        }

        $q = '%' . $this->query . '%';
        $this->movies = Movie::where('title', 'like', $q)->limit(5)->get(['id', 'title', 'slug', 'poster'])->toArray();
        $this->users = User::where('name', 'like', $q)->orWhere('username', 'like', $q)->limit(5)->get(['id', 'name', 'username', 'profile_picture'])->toArray();
    }

    public function close(): void
    {
        $this->open = false;
    }
}; ?>

<div>
    @if ($open)
        <div
            class="fixed inset-0 z-50 flex items-start justify-center pt-20 px-4"
            @keydown.escape.window="$wire.close()"
        >
            <div class="absolute inset-0 bg-black/60" wire:click="close"></div>

            <div class="relative w-full max-w-lg rounded-xl bg-white dark:bg-zinc-900 shadow-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
                    <input
                        type="text"
                        wire:model.live.debounce.200ms="query"
                        placeholder="Buscar filmes ou usuários..."
                        class="w-full rounded-lg bg-zinc-100 dark:bg-zinc-800 border-0 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-green-500 placeholder:text-zinc-400"
                        x-init="$el.focus()"
                    />
                </div>

                @if(strlen(trim($query)) >= 2)
                    @if (empty($movies) && empty($users))
                        <div class="p-6 text-center text-sm text-zinc-400">Nenhum resultado encontrado.</div>
                    @else
                        <div class="max-h-96 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800">
                            @if(!empty($movies))
                                <div class="px-4 py-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-1">Filmes</p>
                                    @foreach ($movies as $movie)
                                        <a href="{{ route('movies.show', $movie['slug']) }}" wire:navigate wire:click="close"
                                           class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                            @if ($movie['poster'])
                                                <img src="{{ asset('storage/' . $movie['poster'] . '.jpg') }}" alt="{{ $movie['title'] }}" class="h-10 w-7 rounded object-cover flex-shrink-0" />
                                            @else
                                                <div class="h-10 w-7 rounded bg-zinc-200 dark:bg-zinc-700 flex-shrink-0"></div>
                                            @endif
                                            <span class="text-sm font-medium truncate">{{ $movie['title'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($users))
                                <div class="px-4 py-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-1">Usuários</p>
                                    @foreach ($users as $user)
                                        <a href="{{ route('users.show', $user['username']) }}" wire:navigate wire:click="close"
                                           class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                            <img src="{{ asset('storage/' . ($user['profile_picture'] ?: 'default-profile.png')) }}" alt="{{ $user['name'] }}" class="h-8 w-8 rounded-full object-cover flex-shrink-0" />
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium truncate">{{ $user['name'] }}</p>
                                                <p class="text-xs text-zinc-400 truncate">{{ $user['username'] }}</p>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                @else
                    <div class="p-4 text-center text-sm text-zinc-400">O Xbox series X é como um geladeira moderna: é onde tem tudo q vc gosta e deicha sua comida mais preservada e saborosa.</div>
                @endif
            </div>
        </div>
    @endif
</div>
