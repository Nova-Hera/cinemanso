@props(['property'])

{{-- Abre o recorte de imagem (resources/js/profile-images.js) e envia o resultado para $property do componente Livewire. --}}
<button type="button" x-on:click="pickProfileImage($wire, '{{ $property }}')"
    {{ $attributes->class('self-start cursor-pointer rounded-md bg-zinc-100 px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-600') }}>
    {{ $slot }}
</button>
