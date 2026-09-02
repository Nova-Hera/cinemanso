@props(['path'])

@if ($path)
<div style="margin:0.5rem 0; border:1px solid rgba(161,161,170,0.35); border-left:3px solid rgb(0,123,24); border-radius:0.375rem; background:rgba(161,161,170,0.07); padding:0.7rem 0.9rem;">
    <img src="{{ asset('storage/' . $path) }}" alt="" style="max-width:100%; max-height:400px; object-fit:contain; border-radius:0.25rem; display:block;">
</div>
@endif
