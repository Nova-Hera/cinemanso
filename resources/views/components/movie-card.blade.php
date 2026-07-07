@props([
    'id'           => null,
    'title'        => null,
    'image'        => null,
    'status'       => null,
    'wireNavigate' => false,
])

@php
    $src = $image ? asset('storage/' . $image . '.jpg') : null;

    $badge = match ($status) {
        'watched'   => ['label' => 'Visto',      'style' => 'background:rgb(23,221,98);color:#000'],
        'watching'  => ['label' => 'Assistindo', 'style' => 'background:rgb(217,119,6);color:#fff'],
        'watchlist' => ['label' => 'Watchlist',  'style' => 'background:rgb(82,82,91);color:#fff'],
        default     => null,
    };
@endphp

<a href="{{ route('movies.show', ['movie' => $id]) }}"
   @if($wireNavigate) wire:navigate @endif
   class="group block">
    <div style="position:relative; aspect-ratio:2/3; overflow:hidden; border-radius:0.75rem; border:1px solid rgb(228,228,231); transition:all 200ms ease;"
         class="dark:border-zinc-700 group-hover:shadow-lg group-hover:scale-[1.02]">

        @if ($src)
            <img src="{{ $src }}" alt="{{ $title }}"
                 style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;" />
        @else
            <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgb(39,39,42);">
                <svg xmlns="http://www.w3.org/2000/svg" style="height:3rem; width:3rem; color:rgb(82,82,91);" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z" />
                </svg>
            </div>
        @endif

        @if ($badge)
            <span style="position:absolute; top:0.5rem; right:0.5rem; border-radius:9999px; padding:0.125rem 0.5rem; font-size:0.75rem; font-weight:600; {{ $badge['style'] }}">
                {{ $badge['label'] }}
            </span>
        @endif

        <div style="position:absolute; bottom:0; left:0; width:100%; background:linear-gradient(to top, rgba(0,0,0,0.8), transparent); padding:0.75rem;">
            <h3 style="color:#fff; font-size:0.875rem; font-weight:600; line-height:1.25; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">{{ $title }}</h3>
        </div>
    </div>
</a>
