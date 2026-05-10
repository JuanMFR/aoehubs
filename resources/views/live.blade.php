@extends('layouts.app')

@section('title', 'Partidas en vivo — AoEHubs')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold flex items-center gap-3">
            Partidas en vivo
            <span class="relative flex h-2.5 w-2.5">
                <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
            </span>
        </h1>
        <p class="mt-1 text-sm text-zinc-500">
            {{ $matches->count() }} {{ $matches->count() === 1 ? 'partida' : 'partidas' }} de la plataforma en curso.
            La página se actualiza automáticamente cada 15s.
        </p>
    </div>

    {{-- Filtros: search por player, dropdown map, dropdown categoria --}}
    <form method="GET" action="{{ route('live') }}" class="flex flex-col sm:flex-row gap-2">
        <input type="text" name="q" value="{{ $q }}" placeholder="Buscar jugador..." maxlength="60"
               class="flex-1 rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">

        @if ($playedMapNames->count() > 0)
            <select name="map" onchange="this.form.submit()"
                    class="rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                <option value="">Todos los mapas</option>
                @foreach ($playedMapNames as $name)
                    <option value="{{ $name }}" {{ $mapFilter === $name ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
        @endif

        @if ($allCategories->count() > 0)
            <select name="category" onchange="this.form.submit()"
                    class="rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                <option value="">Todas las categorías</option>
                @foreach ($allCategories as $cat)
                    <option value="{{ $cat->slug }}" {{ $catSlug === $cat->slug ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        @endif

        <button type="submit"
                class="rounded bg-accent text-accent-dark px-4 py-1.5 text-sm font-semibold hover:bg-accent-hover transition-colors">
            Filtrar
        </button>

        @if ($q || $mapFilter || $catSlug)
            <a href="{{ route('live') }}"
               class="rounded border border-zinc-700 px-4 py-1.5 text-sm text-zinc-400 hover:bg-zinc-800 self-center">
                Limpiar
            </a>
        @endif
    </form>

    @if ($matches->isEmpty())
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-8 text-center">
            <div class="text-5xl mb-3 opacity-30">🎮</div>
            <h2 class="text-lg font-semibold text-zinc-300">
                @if ($q || $mapFilter || $catSlug)
                    No hay partidas que matcheen los filtros
                @else
                    No hay partidas en curso ahora mismo
                @endif
            </h2>
            <p class="mt-2 text-sm text-zinc-500">
                @if ($q || $mapFilter || $catSlug)
                    Probá con otros criterios o <a href="{{ route('live') }}" class="text-accent hover:underline">limpiá los filtros</a>.
                @else
                    Las partidas aparecen acá apenas el companion confirma que arrancaron en AoE2.
                @endif
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
            @foreach ($matches as $match)
                @php
                    $mapName  = $match->mapDraft?->final_map ?? '?';
                    $hostCiv  = $match->civDraft?->host_final_civ;
                    $oppCiv   = $match->civDraft?->opponent_final_civ;
                    $elapsed  = $match->started_at ? $match->started_at->diffForHumans(null, true) : '—';
                    $lobby    = $match->config_json['lobbyName'] ?? null;
                    $server   = $match->config_json['server']    ?? null;
                @endphp

                <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-4 hover:border-zinc-700 transition-colors">
                    {{-- Header: map + tiempo --}}
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <x-map-icon :name="$mapName" class="h-8 w-10 rounded shrink-0" />
                            <div class="min-w-0">
                                <div class="text-sm font-semibold truncate">{{ $mapName }}</div>
                                @if ($server)
                                    <div class="text-[10px] text-zinc-500 uppercase tracking-wider">{{ $server }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="text-xs font-mono text-emerald-400 shrink-0" title="Tiempo desde que arrancó">
                            ⏱ {{ $elapsed }}
                        </div>
                    </div>

                    {{-- Players --}}
                    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                        <div class="min-w-0">
                            <a href="{{ route('users.show', $match->host->steam_id) }}"
                               class="flex items-center gap-2 hover:text-accent transition-colors">
                                @if ($match->host->avatar_url)
                                    <img src="{{ $match->host->avatar_url }}" alt="" class="h-7 w-7 rounded shrink-0">
                                @else
                                    <span class="h-7 w-7 rounded bg-zinc-800 flex items-center justify-center text-xs text-zinc-500 shrink-0">
                                        {{ Str::upper(Str::substr($match->host->displayName(), 0, 1)) }}
                                    </span>
                                @endif
                                <div class="min-w-0">
                                    <div class="text-sm font-medium truncate">{{ $match->host->displayName() }}</div>
                                    <div class="text-xs text-zinc-500 font-mono">{{ round($match->host->rating) }}</div>
                                </div>
                            </a>
                            @if ($hostCiv)
                                <div class="flex items-center gap-1 mt-1.5 text-xs text-zinc-400">
                                    <x-civ-icon :name="$hostCiv" class="h-4 w-4 rounded shrink-0" />
                                    <span class="truncate">{{ $hostCiv }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="text-zinc-600 font-mono text-xs px-1">vs</div>

                        <div class="min-w-0 text-right">
                            <a href="{{ route('users.show', $match->opponent->steam_id) }}"
                               class="flex items-center gap-2 hover:text-accent transition-colors justify-end">
                                <div class="min-w-0 text-right">
                                    <div class="text-sm font-medium truncate">{{ $match->opponent->displayName() }}</div>
                                    <div class="text-xs text-zinc-500 font-mono">{{ round($match->opponent->rating) }}</div>
                                </div>
                                @if ($match->opponent->avatar_url)
                                    <img src="{{ $match->opponent->avatar_url }}" alt="" class="h-7 w-7 rounded shrink-0">
                                @else
                                    <span class="h-7 w-7 rounded bg-zinc-800 flex items-center justify-center text-xs text-zinc-500 shrink-0">
                                        {{ Str::upper(Str::substr($match->opponent->displayName(), 0, 1)) }}
                                    </span>
                                @endif
                            </a>
                            @if ($oppCiv)
                                <div class="flex items-center gap-1 mt-1.5 text-xs text-zinc-400 justify-end">
                                    <span class="truncate">{{ $oppCiv }}</span>
                                    <x-civ-icon :name="$oppCiv" class="h-4 w-4 rounded shrink-0" />
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Spectate hint: el companion configura los lobbies como
                         públicos + permite espectadores con 3min de delay
                         (ver LobbyConfigurator.cs). El user puede abrir AoE2
                         → Multijugador → buscar el lobby por nombre. --}}
                    @if ($lobby)
                        <details class="mt-3 text-xs">
                            <summary class="text-zinc-500 cursor-pointer hover:text-zinc-300">
                                Cómo spectear esta partida
                            </summary>
                            <div class="mt-2 p-2 rounded bg-zinc-950 border border-zinc-800 space-y-1">
                                <p class="text-zinc-400">
                                    1. Abrí <strong>AoE2 DE</strong> → <strong>Multijugador</strong> → <strong>Buscar partida</strong>.
                                </p>
                                <p class="text-zinc-400">
                                    2. Buscá el lobby por nombre:
                                </p>
                                <p class="font-mono text-accent bg-zinc-900 px-2 py-1 rounded text-[11px] break-all">{{ $lobby }}</p>
                                <p class="text-zinc-500 text-[10px] mt-1">
                                    Las partidas tienen 3 min de delay para spectators (anti stream-snipe).
                                </p>
                            </div>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

@push('scripts')
<script>
    // Auto-refresh cada 15s para mantener actualizado sin polling agresivo.
    // Ajustado al ratio típico de cambio de matches (10-30s entre eventos).
    setInterval(() => window.location.reload(), 15000);
</script>
@endpush
@endsection
