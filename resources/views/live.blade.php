@extends('layouts.app')

@section('title', 'Partidas en vivo — AoEHubs')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold flex items-center gap-3">
                Partidas en vivo
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                </span>
            </h1>
            <p class="mt-1 text-sm text-zinc-500">
                @if ($source === 'platform')
                    {{ $matches->count() }} {{ $matches->count() === 1 ? 'partida' : 'partidas' }} de la plataforma en curso.
                    Auto-refresh cada 15s.
                @else
                    Lobbies <strong>custom</strong> de AoE2 DE (vía Relic API). No incluye ranked auto-matchmade.
                    Auto-refresh cada 15s.
                @endif
            </p>
        </div>

        {{-- Toggle Plataforma / Global --}}
        <div class="flex rounded-lg border border-zinc-700 bg-zinc-950 p-0.5 self-start sm:self-end">
            <a href="{{ route('live') }}"
               class="px-4 py-1.5 rounded text-sm font-semibold transition-colors {{ $source === 'platform' ? 'bg-accent text-accent-dark' : 'text-zinc-400 hover:text-zinc-100' }}">
                Plataforma
            </a>
            <a href="{{ route('live', ['source' => 'global']) }}"
               class="px-4 py-1.5 rounded text-sm font-semibold transition-colors {{ $source === 'global' ? 'bg-accent text-accent-dark' : 'text-zinc-400 hover:text-zinc-100' }}">
                Global
            </a>
        </div>
    </div>

    @if ($source === 'platform')
        {{-- Filtros plataforma: search por player, dropdown map, dropdown categoria --}}
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

                        {{-- Spectate hint --}}
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

    @else
        {{-- Source = global --}}

        {{-- Filtros global: search + ELO mínimo + toggle "se puede ver" --}}
        <form method="GET" action="{{ route('live') }}" class="flex flex-col sm:flex-row gap-2 flex-wrap">
            <input type="hidden" name="source" value="global">
            <input type="text" name="q" value="{{ $q }}" placeholder="Buscar mapa o lobby..." maxlength="60"
                   class="flex-1 min-w-[200px] rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">

            {{-- Filtro de ELO del host. Default 2000+ para mostrar solo top-tier. --}}
            <select name="elo_min" onchange="this.form.submit()"
                    class="rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none"
                    title="Filtra lobbies por el rating 1v1 RM del host">
                @foreach ($eloPresets as $preset)
                    <option value="{{ $preset }}" {{ $eloMin === $preset ? 'selected' : '' }}>
                        {{ $preset === 0 ? 'Cualquier ELO' : $preset . '+ ELO' }}
                    </option>
                @endforeach
            </select>

            <button type="submit"
                    class="rounded bg-accent text-accent-dark px-4 py-1.5 text-sm font-semibold hover:bg-accent-hover transition-colors">
                Filtrar
            </button>

            @if ($q || $eloMin !== 2000)
                <a href="{{ route('live', ['source' => 'global']) }}"
                   class="rounded border border-zinc-700 px-4 py-1.5 text-sm text-zinc-400 hover:bg-zinc-800 self-center">
                    Reset
                </a>
            @endif
        </form>

        @if (! $apiOk && empty($ads))
            <div class="rounded-xl border border-amber-700/50 bg-amber-950/20 p-6 text-sm text-amber-300">
                <strong>API global no disponible.</strong>
                No pudimos contactar la API de Relic ahora mismo. Probá refrescar en unos segundos
                o cambiá a la pestaña <a href="{{ route('live') }}" class="text-accent hover:underline">Plataforma</a>.
            </div>
        @elseif (empty($ads))
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-8 text-center">
                <div class="text-5xl mb-3 opacity-30">🎮</div>
                <h2 class="text-lg font-semibold text-zinc-300">
                    No hay lobbies que matcheen los filtros
                </h2>
                <p class="mt-2 text-sm text-zinc-500">
                    @if ($eloMin >= 2000)
                        El filtro <strong>{{ $eloMin }}+ ELO</strong> es exigente — probá bajarlo.
                    @else
                        Probá ajustar la búsqueda.
                    @endif
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                @foreach ($ads as $ad)
                    @php
                        $mapname    = $ad['mapname']    ?? '?';
                        $lobbyName  = $ad['description'] ?? '?';
                        $region     = $ad['relayserver_region'] ?? null;
                        $isObs      = ($ad['isobservable'] ?? 0) === 1;
                        $obsCount   = (int) ($ad['observernum'] ?? 0);
                        $hasPwd     = ($ad['passwordprotected'] ?? 0) === 1;
                        $maxPlayers = (int) ($ad['maxplayers'] ?? 0);
                        $hostId     = $ad['host_profile_id'] ?? null;
                        $hostStat   = $hostId ? ($stats[$hostId] ?? null) : null;
                        $members    = $ad['matchmembers'] ?? [];
                    @endphp

                    <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-4 hover:border-zinc-700 transition-colors">
                        {{-- Header --}}
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold truncate" title="{{ $lobbyName }}">{{ $lobbyName }}</div>
                                <div class="text-xs text-zinc-500 truncate flex items-center gap-2 mt-0.5">
                                    <span>📍 {{ $mapname }}</span>
                                    @if ($region)
                                        <span class="text-zinc-600">· {{ $region }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                @if ($hasPwd)
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-400 border border-zinc-700" title="Lobby protegido por password">🔒</span>
                                @endif
                                @if ($isObs)
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-800/60 uppercase tracking-wider" title="Spectable">spec</span>
                                @endif
                            </div>
                        </div>

                        {{-- Host + jugadores --}}
                        <div class="space-y-1 text-sm">
                            @if ($hostStat)
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-zinc-500 w-12 shrink-0">Host:</span>
                                    <span class="font-medium truncate">{{ $hostStat['alias'] ?? "Player #{$hostId}" }}</span>
                                    @if (! empty($hostStat['country']))
                                        <span class="text-xs text-zinc-500 uppercase">{{ $hostStat['country'] }}</span>
                                    @endif
                                    @if (! empty($hostStat['rating']))
                                        <span class="text-xs font-mono text-accent ml-auto">{{ $hostStat['rating'] }}</span>
                                    @endif
                                </div>
                            @elseif ($hostId)
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-zinc-500 w-12 shrink-0">Host:</span>
                                    <span class="text-zinc-400 truncate">Player #{{ $hostId }}</span>
                                </div>
                            @endif

                            @if (count($members) > 0)
                                <div class="flex items-start gap-2">
                                    <span class="text-xs text-zinc-500 w-12 shrink-0 mt-0.5">Slots:</span>
                                    <div class="flex flex-wrap gap-1.5 text-xs">
                                        @foreach ($members as $m)
                                            @php
                                                $pid = (int) ($m['profile_id'] ?? 0);
                                                $stat = $pid > 0 ? ($stats[$pid] ?? null) : null;
                                                $alias = $stat['alias'] ?? ($pid > 0 ? "#{$pid}" : '?');
                                                $rating = $stat['rating'] ?? null;
                                            @endphp
                                            <span class="px-1.5 py-0.5 rounded bg-zinc-950 border border-zinc-800">
                                                {{ $alias }}@if ($rating)
                                                    <span class="text-zinc-500 font-mono">·{{ $rating }}</span>
                                                @endif
                                            </span>
                                        @endforeach
                                        <span class="text-zinc-600">{{ count($members) }}/{{ $maxPlayers }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Footer: observers + spectate hint --}}
                        <div class="mt-3 pt-2 border-t border-zinc-800/60 flex items-center justify-between text-xs text-zinc-500">
                            <span>👁 {{ $obsCount }} {{ $obsCount === 1 ? 'spectator' : 'spectators' }}</span>
                            @if ($isObs && $lobbyName !== '?')
                                <details class="text-right">
                                    <summary class="cursor-pointer hover:text-zinc-300">Cómo spectear</summary>
                                    <div class="mt-1 p-2 rounded bg-zinc-950 border border-zinc-800 text-left text-zinc-400 normal-case absolute right-0 max-w-xs">
                                        Buscá <span class="font-mono text-accent">{{ Str::limit($lobbyName, 40) }}</span> en AoE2 → Multijugador → Buscar partida.
                                    </div>
                                </details>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>

@push('scripts')
<script>
    // Auto-refresh cada 15s para mantener actualizado sin polling agresivo.
    setInterval(() => window.location.reload(), 15000);
</script>
@endpush
@endsection
