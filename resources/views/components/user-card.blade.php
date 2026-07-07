@props([
    'username'     => null,
    'title'        => null,
    'image'        => null,
    'wireNavigate' => false,
])

<a href="{{ route('users.show', ['user' => $username]) }}"
   @if($wireNavigate) wire:navigate @endif
   class="group block">
    <div style="position:relative; aspect-ratio:2/3; overflow:hidden; border-radius:0.75rem; border:1px solid rgb(228,228,231); transition:all 200ms ease;"
         class="dark:border-zinc-700 group-hover:shadow-lg group-hover:scale-[1.02]">

        <img
            src="{{ asset('storage/' . ($image ?: 'default-profile.png')) }}"
            alt="{{ $title }}"
            style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;"
        />

        <div style="position:absolute; bottom:0; left:0; width:100%; background:linear-gradient(to top, rgba(0,0,0,0.8), transparent); padding:0.75rem;">
            <h3 style="color:#fff; font-size:0.875rem; font-weight:600; line-height:1.25; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">{{ $title }}</h3>
            <p style="color:rgba(255,255,255,0.7); font-size:0.75rem; margin-top:0.125rem;">{{ $username }}</p>
        </div>
    </div>
</a>
