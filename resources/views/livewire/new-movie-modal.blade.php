<?php

use App\Models\Movie;
use App\Services\Tmdb;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public bool   $open          = false;
    public string $title         = '';
    public string $director      = '';
    public string $genre         = '';
    public string $releaseDate   = '';
    public string $watchedAt     = '';
    public string $status        = 'watchlist';
    public string $description   = '';
    public array  $genres        = [];
    public array  $searchResults = [];
    public string $posterUrl     = '';
    public        $poster        = null;
    public ?string $imdb_id      = null;

    // ADICIONADO: Propriedade para armazenar os provedores de streaming temporariamente
    public array  $streamings    = ['BR' => [], 'US' => []];

    #[On('open-new-movie')]
    public function openModal(): void
    {
        if (!auth()->check()) return;
        $this->open = true;
        $this->reset(['title', 'director', 'releaseDate', 'watchedAt', 'description', 'poster',
                      'genres', 'searchResults', 'posterUrl', 'streamings', 'imdb_id']);
        $this->status = 'watchlist';
    }

    public function searchTmdb(): void
    {
        if (!auth()->check()) return;

        $tmdb = app(Tmdb::class);
        if (!$tmdb->configured()) {
            $this->addError('title', 'Chave da API TMDB não configurada.');
            return;
        }
        if (trim($this->title) === '') {
            $this->addError('title', 'Digite um título para buscar.');
            return;
        }

        $this->searchResults = $tmdb->search($this->title);
    }

    public function selectResult(int $id, string $type = 'movie'): void
    {
        if (!auth()->check()) return;

        $d = app(Tmdb::class)->details($id, $type);
        if (empty($d)) return;

        $this->title         = $d['title'];
        $this->director      = $d['director'];
        $this->releaseDate   = $d['releaseDate'];
        $this->description   = $d['description'];
        $this->genres        = $d['genres'];
        $this->posterUrl     = $d['posterUrl'];
        $this->streamings    = $d['streamings'] ?? ['BR' => [], 'US' => []];
        $this->imdb_id       = $d['imdb_id'] ?? null;
        $this->searchResults = [];
    }

    public function dismissSearch(): void
    {
        $this->searchResults = [];
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
            'imdb_id'     => 'nullable|string|max:255', // CORRIGIDO AQUI!
        ]);

        $base = Str::slug($this->title);
        $slug = $base;
        $i = 1;
        while (Movie::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $posterPath = '';
        if ($this->poster) {
            $ext = $this->poster->extension();
            $this->poster->storeAs('posters', $slug . '.' . $ext, 'public');
            $posterPath = 'posters/' . $slug . '.' . $ext;
        } elseif ($this->posterUrl !== '') {
            try {
                $body = Http::timeout(10)->get($this->posterUrl)->body();
                Storage::disk('public')->put("posters/{$slug}.jpg", $body);
                $posterPath = "posters/{$slug}.jpg";
            } catch (\Throwable) {
                $posterPath = '';
            }
        }

        $movie = Movie::create([
            'title'        => $this->title,
            'slug'         => $slug,
            'director'     => $this->director ?: null,
            'genres'       => $this->genres,
            'release_date' => $this->releaseDate ?: null,
            'watched_at'   => $this->watchedAt ?: null,
            'status'       => $this->status,
            'description'  => $this->description ?: null,
            'poster'       => $posterPath,
            'added_by'     => auth()->id(),
            'streamings'   => $this->streamings,
            'imdb_id'      => $this->imdb_id, // CORRIGIDO AQUI: Faltava salvar no banco!
        ]);

        $this->open = false;
        $this->redirect(route('movies.show', $movie->slug), navigate: true);
    }

    public function close(): void
    {
        $this->open          = false;
        $this->searchResults = [];
    }
}; ?>

<div>
    @if ($open)
    <div class="fixed inset-0 z-50 flex items-start justify-center pt-6 px-4" @keydown.escape.window="$wire.close()">
        <div class="absolute inset-0 bg-black/60" wire:click="close"></div>

        <div
            class="relative w-full max-w-lg rounded-xl bg-white dark:bg-zinc-900 shadow-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden flex flex-col max-h-[90vh]">

            <div
                class="flex items-center justify-between px-5 py-3 border-b border-zinc-200 dark:border-zinc-700 flex-shrink-0">
                <div class="flex items-center gap-2.5">
                    <h2 class="text-base font-semibold">Novo Filme</h2>
                    <a href="https://www.themoviedb.org/" target="_blank" rel="noopener"
                        title="API do TMDB é usada pra pesquisa"
                        class="opacity-60 hover:opacity-100 transition-opacity">
                        <img src="{{ asset('images/tmdb.svg') }}" alt="TMDB" style="height:14px; width:auto;" />
                    </a>
                </div>
                <flux:button size="sm" icon="x-mark" variant="ghost" inset wire:click="close" />
            </div>

            <form wire:submit="save" class="px-5 py-3 flex flex-col gap-3 overflow-y-auto [scrollbar-width:thin]">

                <div class="relative">
                    <flux:label>Título</flux:label>
                    <div class="flex items-center gap-1 mt-1">
                        <flux:input wire:model="title" placeholder="Nome do filme" required autofocus class="flex-1" />
                        <flux:button type="button" wire:click="searchTmdb" size="sm" icon="magnifying-glass"
                            variant="ghost" inset wire:loading.attr="disabled" wire:target="searchTmdb" />
                    </div>

                    @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror

                    @if (!empty($searchResults))
                    <div
                        class="absolute top-full left-0 right-0 z-50 mt-1 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-xl overflow-hidden">
                        <div
                            class="flex items-center justify-between px-3 py-1.5 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                            <span class="text-xs text-zinc-500">Selecione o filme correto</span>
                            <button type="button" wire:click="dismissSearch"
                                class="text-xs text-zinc-400 hover:text-zinc-600">✕</button>
                        </div>
                        @foreach ($searchResults as $r)
                        <button type="button" wire:click="selectResult({{ $r['id'] }}, '{{ $r['type'] }}')"
                            class="w-full flex items-center gap-3 px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-left transition-colors border-b border-zinc-100 dark:border-zinc-800 last:border-0">
                            @if ($r['thumb'])
                            <img src="{{ $r['thumb'] }}" alt="{{ $r['title'] }}" class="w-8 rounded flex-shrink-0"
                                style="aspect-ratio:2/3;object-fit:cover;" />
                            @else
                            <div class="w-8 rounded bg-zinc-200 dark:bg-zinc-700 flex-shrink-0"
                                style="aspect-ratio:2/3;"></div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium truncate">{{ $r['title'] }}</p>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="text-xs text-zinc-400">{{ $r['year'] }}</span>
                                    @if ($r['type'] === 'tv')
                                    <span class="text-xs px-1 rounded"
                                        style="background:rgb(99,102,241);color:#fff;">Série</span>
                                    @endif
                                </div>
                            </div>
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>

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

                <flux:textarea wire:model="description" label="Sinopse" placeholder="Breve descrição do filme..."
                    rows="2" />

                {{-- Preview Visual dos Streamings capturados antes de Salvar --}}
                @if(!empty($streamings['BR']) || !empty($streamings['US']))
                <div
                    class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 bg-zinc-50/50 dark:bg-zinc-800/30">
                    <flux:label class="block mb-2 font-medium">Disponibilidade Detectada</flux:label>
                    <div class="flex flex-col gap-2 text-xs">
                        @if(!empty($streamings['BR']))
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="font-semibold text-zinc-500">BR:</span>
                            @foreach($streamings['BR'] as $st)
                            <img src="{{ $st['logo'] }}" title="{{ $st['name'] }}"
                                class="w-5 h-5 rounded-md shadow-sm" />
                            @endforeach
                        </div>
                        @endif
                        @if(!empty($streamings['US']))
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="font-semibold text-zinc-500">US:</span>
                            @foreach($streamings['US'] as $st)
                            <img src="{{ $st['logo'] }}" title="{{ $st['name'] }}"
                                class="w-5 h-5 rounded-md shadow-sm" />
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Poster --}}
                <div>
                    <flux:label>Poster</flux:label>
                    @if ($posterUrl && !$poster)
                    <div class="mt-1 mb-2 flex items-center gap-3">
                        <img src="{{ $posterUrl }}" alt="Preview" class="w-12 rounded object-cover flex-shrink-0"
                            style="aspect-ratio:2/3;" />
                        <span class="text-xs text-zinc-400">Imagem do TMDB</span>
                    </div>
                    @endif
                    <input type="file" wire:model="poster" accept="image/*" class="mt-1 w-full text-sm text-zinc-600 dark:text-zinc-400
                                      file:mr-3 file:rounded-md file:border-0
                                      file:bg-zinc-100 dark:file:bg-zinc-700
                                      file:px-3 file:py-1.5 file:text-sm file:font-medium
                                      file:text-zinc-700 dark:file:text-zinc-200
                                      hover:file:bg-zinc-200 dark:hover:file:bg-zinc-600
                                      file:cursor-pointer" />
                    <div wire:loading wire:target="poster" class="text-xs text-zinc-400 mt-1">Carregando...</div>
                    @error('poster') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex mt-1">
                    <flux:button type="submit" class="flex-1" style="background:rgb(0,123,24);color:#fff;border:none">
                        Adicionar Filme
                    </flux:button>
                </div>

            </form>
        </div>
    </div>
    @endif
</div>
