<?php

use App\Models\Movie;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public bool   $open          = false;
    public int    $movieId       = 0;
    public string $title         = '';
    public string $director      = '';
    public array  $genres        = [];
    public string $releaseDate   = '';
    public string $watchedAt     = '';
    public string $status        = 'watchlist';
    public string $description   = '';
    public string $currentPoster = '';
    public        $poster        = null;

    public function mount(Movie $movie): void
    {
        $this->movieId       = $movie->id;
        $this->title         = $movie->title;
        $this->director      = $movie->director ?? '';
        $this->genres        = $movie->genres ?? [];
        $this->releaseDate   = $movie->release_date?->format('Y-m-d') ?? '';
        $this->watchedAt     = $movie->watched_at?->format('Y-m-d') ?? '';
        $this->status        = $movie->status;
        $this->description   = $movie->description ?? '';
        $this->currentPoster = $movie->poster ?? '';
    }

    #[On('open-edit-movie')]
    public function openModal(): void
    {
        if (!auth()->check()) return;
        $this->open = true;
    }

    public function save(): void
    {
        if (!auth()->check()) return;

        $this->validate([
            'title'       => 'required|string|max:255',
            'director'    => 'nullable|string|max:255',
            'releaseDate' => 'nullable|date',
            'watchedAt'   => 'nullable|date',
            'status'      => 'required|in:watchlist,watching,watched',
            'description' => 'nullable|string',
            'genres'      => 'array',
            'genres.*'    => 'in:' . implode(',', Movie::GENRES),
            'poster'      => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:4096',
        ]);

        $movie = Movie::findOrFail($this->movieId);

        $posterPath = $movie->poster;
        if ($this->poster) {
            $ext = $this->poster->extension();
            $this->poster->storeAs('posters', $movie->slug . '.' . $ext, 'public');
            $posterPath = 'posters/' . $movie->slug . '.' . $ext;
        }

        $movie->update([
            'title'        => $this->title,
            'director'     => $this->director ?: null,
            'genres'       => $this->genres,
            'release_date' => $this->releaseDate ?: null,
            'watched_at'   => $this->watchedAt ?: null,
            'status'       => $this->status,
            'description'  => $this->description ?: null,
            'poster'       => $posterPath,
        ]);

        $this->open = false;
        $this->redirect(route('movies.show', $movie->slug), navigate: true);
    }

    public function delete(): void
    {
        if (!auth()->check()) return;

        Movie::findOrFail($this->movieId)->delete();
        $this->redirect(route('movies.index'), navigate: true);
    }

    public function close(): void
    {
        $this->open = false;
    }
}; ?>

<div>
    @if ($open)
        <div class="fixed inset-0 z-50 flex items-start justify-center pt-6 px-4"
             @keydown.escape.window="$wire.close()">
            <div class="absolute inset-0 bg-black/60" wire:click="close"></div>

            <div class="relative w-full max-w-lg rounded-xl bg-white dark:bg-zinc-900 shadow-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">

                <div class="flex items-center justify-between px-5 py-3 border-b border-zinc-200 dark:border-zinc-700">
                    <h2 class="text-base font-semibold">Editar Filme</h2>
                    <flux:button size="sm" icon="x-mark" variant="ghost" inset wire:click="close" />
                </div>

                <form wire:submit="save" class="px-5 py-3 flex flex-col gap-3">

                    <flux:input wire:model="title" label="Título" placeholder="Nome do filme" required autofocus />

                    <div class="grid grid-cols-2 gap-3">
                        <flux:input wire:model="director" label="Diretor" placeholder="Nome do diretor" />
                        <flux:input type="date" wire:model="releaseDate" label="Lançamento" />
                    </div>

                    <div class="grid grid-cols-2 gap-3 items-end">
                        <flux:input type="date" wire:model="watchedAt" label="Assistido em" />
                        <div>
                            <flux:label>Status</flux:label>
                            <select wire:model="status"
                                    class="mt-1 h-10 w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 text-sm text-zinc-700 dark:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-green-500">
                                <option value="watchlist">Watchlist</option>
                                <option value="watching">Assistindo</option>
                                <option value="watched">Visto</option>
                            </select>
                        </div>
                    </div>

                    {{-- Genre chips --}}
                    <div>
                        <flux:label>Gênero</flux:label>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            @foreach (\App\Models\Movie::GENRES as $g)
                                <label class="cursor-pointer select-none">
                                    <input type="checkbox" wire:model="genres" value="{{ $g }}" class="sr-only peer">
                                    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium border
                                                 border-zinc-300 dark:border-zinc-600
                                                 text-zinc-600 dark:text-zinc-300
                                                 peer-checked:border-green-500 peer-checked:bg-green-500/10
                                                 peer-checked:text-green-600 dark:peer-checked:text-green-400
                                                 transition-colors">{{ $g }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <flux:textarea wire:model="description" label="Sinopse" placeholder="Breve descrição do filme..." rows="2" />

                    <div>
                        <flux:label>Trocar Poster</flux:label>
                        @if ($currentPoster)
                            <div class="mt-1 mb-2">
                                <img src="{{ asset('storage/' . $currentPoster) }}" alt="Poster atual"
                                     class="w-12 rounded object-cover" style="aspect-ratio:2/3;" />
                            </div>
                        @endif
                        <input type="file" wire:model="poster" accept="image/*"
                               class="mt-1 w-full text-sm text-zinc-600 dark:text-zinc-400
                                      file:mr-3 file:rounded-md file:border-0
                                      file:bg-zinc-100 dark:file:bg-zinc-700
                                      file:px-3 file:py-1.5 file:text-sm file:font-medium
                                      file:text-zinc-700 dark:file:text-zinc-200
                                      hover:file:bg-zinc-200 dark:hover:file:bg-zinc-600
                                      file:cursor-pointer" />
                        <div wire:loading wire:target="poster" class="text-xs text-zinc-400 mt-1">Carregando...</div>
                        @error('poster') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    @error('title') <p class="text-xs text-red-500 -mt-2">{{ $message }}</p> @enderror

                    <div class="flex gap-2 mt-1">
                        <flux:button type="button"
                                     wire:click="delete"
                                     wire:confirm="Tem certeza que deseja deletar este filme? Esta ação não pode ser desfeita."
                                     class="flex-1"
                                     style="background:rgb(220,38,38);color:#fff;border:none">
                            Deletar Filme
                        </flux:button>
                        <flux:button type="submit" class="flex-1" style="background:rgb(0,123,24);color:#fff;border:none">
                            Editar Filme
                        </flux:button>
                    </div>

                </form>
            </div>
        </div>
    @endif
</div>
