@props(['name'])
@php
    // Normalizamos el nombre para resolver el archivo. CIV_POOL en
    // app/Services/Matchmaking.php tiene los nombres en TitleCase
    // ('Aztecs', 'Britons') y los archivos viven como '{lowercase}.png'.
    $normalizedName = $name ? strtolower(trim($name)) : null;
    $iconPath = $normalizedName ? "images/civs/{$normalizedName}.png" : null;
    $iconExists = $iconPath && file_exists(public_path($iconPath));
@endphp

@if ($iconExists)
    <img src="{{ asset($iconPath) }}" alt="{{ $name }}"
         {{ $attributes->merge(['class' => 'object-contain']) }}
         title="{{ $name }}">
@else
    {{-- Fallback: caja con iniciales para civs sin icono (devs, civs nuevas
         no rippeadas todavia, etc.). Usa el mismo $attributes asi el caller
         define el size + rounding una sola vez. --}}
    <div {{ $attributes->merge(['class' => 'bg-zinc-800 border border-zinc-700 flex items-center justify-center text-zinc-500 font-bold']) }}
         title="{{ $name ?? '' }}">
        {{ Str::upper(Str::substr($name ?? '?', 0, 2)) }}
    </div>
@endif
