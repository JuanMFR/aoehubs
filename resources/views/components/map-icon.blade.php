@props(['name'])
@php
    // MAP_POOL en app/Services/Matchmaking.php tiene nombres con espacios
    // ('Black Forest', 'Hill Fort'). Los archivos viven como
    // {lowercase_con_underscores}.png.
    $normalizedName = $name ? strtolower(str_replace(' ', '_', trim($name))) : null;
    $iconPath = $normalizedName ? "images/maps/{$normalizedName}.png" : null;
    $iconExists = $iconPath && file_exists(public_path($iconPath));
@endphp

@if ($iconExists)
    {{-- object-contain: las miniaturas de mapas son rombos isometricos
         (mas anchas que altas y rotadas 45deg). object-cover las cortaria
         por las puntas — preferimos ver el logo completo aunque queden
         margenes transparentes arriba/abajo en contenedores cuadrados. --}}
    <img src="{{ asset($iconPath) }}" alt="{{ $name }}"
         {{ $attributes->merge(['class' => 'object-contain']) }}
         title="{{ $name }}">
@else
    <div {{ $attributes->merge(['class' => 'bg-zinc-800 border border-zinc-700 flex items-center justify-center text-zinc-500 font-bold']) }}
         title="{{ $name ?? '' }}">
        {{ Str::upper(Str::substr($name ?? '?', 0, 2)) }}
    </div>
@endif
