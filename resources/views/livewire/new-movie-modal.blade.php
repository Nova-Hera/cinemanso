<?php

use App\Models\Movie;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public bool $open = false;
    public string $title = '';
    public string $director = '';
    public string $genre = '';
    public string $releaseDate = '';
    public string $watchedAt = '';
    public string $status = 'watchlist';
    public string $description = '';
    public $poster = null;

    #[On('open-new-movie')]
    public function openModal(): void
    {
        if(!auth()->check()) {
            return;
        }
        $this->open = true;
        $this->reset(['title', 'director', 'genre', 'releaseDate', 'watchedAt', 'description', 'poster']);
        $this->status = 'watchlist';
    }

    public function save(): void
    {
        if(!auth()->check()) {
            return;
        }
        $this->validate([
            'title'       => 'required|string|max:255',
            'director'    => 'nullable|string|max:255',
            'genre'       => 'nullable|string|max:255',
            'releaseDate' => 'nullable|date',
            'watchedAt'   => 'nullable|date',
            'status'      => 'required|in:watchlist,watching,watched',
            'description' => 'nullable|string',
            'poster'      => 'nullable|image|mimes:jpeg,jpg|max:4096',
        ]);

        $base = Str::slug($this->title);
        $slug = $base;
        $i = 1;
        while (Movie::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $posterPath = '';
        if ($this->poster) {
            $this->poster->storeAs('posters', $slug, 'public');
            $posterPath = 'posters/' . $slug;
        }

        $movie = Movie::create([
            'title'        => $this->title,
            'slug'         => $slug,
            'director'     => $this->director ?: null,
            'genre'        => $this->genre ?: null,
            'release_date' => $this->releaseDate ?: null,
            'watched_at'   => $this->watchedAt ?: null,
            'status'       => $this->status,
            'description'  => $this->description ?: null,
            'poster'       => $posterPath,
        ]);

        $this->open = false;
        $this->redirect(route('movies.show', $movie->slug), navigate: true);
    }

    public function close(): void
    {
        $this->open = false;
    }
}; ?>

<div>
    @if ($open)
        <div class="fixed inset-0 z-50 flex items-start justify-center pt-16 px-4"
             @keydown.escape.window="$wire.close()">
            <div class="absolute inset-0 bg-black/60" wire:click="close"></div>

            <div class="relative w-full max-w-lg rounded-xl bg-white dark:bg-zinc-900 shadow-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">

                <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-200 dark:border-zinc-700">
                    <h2 class="text-base font-semibold">Novo Filme</h2>
                    <flux:button size="sm" icon="x-mark" variant="ghost" inset wire:click="close" />
                </div>

                <form wire:submit="save" class="px-5 py-4 flex flex-col gap-4 max-h-[calc(100vh-10rem)] overflow-y-auto">

                    <flux:input wire:model="title" label="Título" placeholder="Nome do filme" required autofocus />

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="director" label="Diretor" placeholder="Nome do diretor" />
                        <flux:input wire:model="genre" label="Gênero" placeholder="Ação, Drama..." />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input type="date" wire:model="releaseDate" label="Lançamento" />
                        <flux:input type="date" wire:model="watchedAt" label="Assistido em" />
                    </div>

                    <div>
                        <flux:label>Status</flux:label>
                        <select wire:model="status"
                                class="mt-1 w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-700 dark:text-zinc-200 focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="watchlist">Watchlist</option>
                            <option value="watching">Assistindo</option>
                            <option value="watched">Visto</option>
                        </select>
                    </div>

                    <flux:textarea wire:model="description" label="Sinopse" placeholder="Breve descrição do filme..." rows="3" />

                    <div>
                        <flux:label>Poster (JPG)</flux:label>
                        <input type="file" wire:model="poster" accept=".jpg,.jpeg"
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

                    <div class="flex gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                        <flux:button type="submit" class="flex-1" style="background:rgb(0,123,24);color:#fff;border:none">
                            Adicionar Filme
                        </flux:button>
                    </div>

                </form>
            </div>
        </div>
    @endif
</div>
