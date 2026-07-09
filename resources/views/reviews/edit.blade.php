@include('components.layouts.app.header')

<x-layouts.app :title="'Editar review — ' . $review->movie->title">
    <div class="flex flex-col gap-6" style="max-width:36rem;">

        <div>
            <a href="{{ route('movies.show', $review->movie->slug) }}"
               class="text-sm text-zinc-500 dark:text-zinc-400 hover:underline"
               wire:navigate>
                ← {{ $review->movie->title }}
            </a>
            <h1 class="text-2xl font-bold tracking-tight mt-2">Editar review</h1>
        </div>

        <form method="POST" action="{{ route('reviews.update', $review->id) }}" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <flux:input
                type="number"
                name="rating"
                max="10" min="0" step="0.1"
                label="Nota"
                :value="old('rating', $review->rating)"
                required
            />

            <flux:textarea
                name="content"
                label="Review"
                rows="6"
                required
            >{{ old('content', $review->content) }}</flux:textarea>

            @if ($errors->any())
                <div class="text-sm text-red-500">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="flex gap-3">
                <flux:button
                    type="submit"
                    variant="primary"
                    style="background:rgb(0,123,24);color:#fff;border:none"
                >
                    Salvar
                </flux:button>
                <a href="{{ route('movies.show', $review->movie->slug) }}" wire:navigate>
                    <flux:button type="button" variant="ghost">Cancelar</flux:button>
                </a>
            </div>
        </form>

    </div>
</x-layouts.app>
