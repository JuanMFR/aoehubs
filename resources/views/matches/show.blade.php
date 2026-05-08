@extends('layouts.app')

@section('title', 'Match #' . $match->id . ' — AoE2 Rank')

@section('content')
@php
    $isHost     = $match->host_user_id === auth()->id();
    $myCivPick  = $match->civDraft ? ($isHost ? $match->civDraft->host_final_civ : $match->civDraft->opponent_final_civ) : null;
    $oppCivPick = $match->civDraft ? ($isHost ? $match->civDraft->opponent_final_civ : $match->civDraft->host_final_civ) : null;
    $opp        = $isHost ? $match->opponent : $match->host;
    $oppLinkable = $opp && ! $opp->isBot();
    $mapName    = $match->mapDraft?->final_map;
    $myDelta    = $isHost ? $match->host_rating_change : $match->opponent_rating_change;
    $myBefore   = $isHost ? $match->host_rating_before : $match->opponent_rating_before;
    $isWinner   = $match->winner_user_id === auth()->id();
    $walkover   = $match->status === 'completed' && $match->replay_path === null;
@endphp

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <a href="{{ route('matches.index') }}" class="text-sm text-steam hover:underline">← Mis matches</a>
            <h1 class="mt-2 text-2xl font-bold flex items-center gap-3 flex-wrap">
                Match <span class="font-mono text-zinc-500">#{{ $match->id }}</span>
                <span class="badge badge-{{ $match->status }}">{{ $match->status }}</span>
                @if ($walkover)
                    <span class="badge badge-completed">walkover</span>
                @endif
            </h1>
            <p class="mt-1 text-sm text-zinc-500">
                Creada {{ $match->created_at->format('Y-m-d H:i') }}
                @if ($match->started_at)· Empezó {{ $match->started_at->format('H:i') }}@endif
            </p>
        </div>

        {{-- Resultado prominente para completed --}}
        @if ($match->status === 'completed' && $match->winner_user_id !== null)
            <div class="rounded-xl border-2 px-5 py-3 {{ $isWinner ? 'border-emerald-700 bg-emerald-950/30' : 'border-red-800 bg-red-950/30' }}">
                <div class="text-xs uppercase tracking-wider text-zinc-500">Resultado</div>
                <div class="text-2xl font-bold {{ $isWinner ? 'text-emerald-300' : 'text-red-300' }}">
                    {{ $isWinner ? 'WIN' : 'LOSS' }}
                </div>
                @if ($myDelta !== null)
                    <div class="font-mono text-sm">
                        <span class="text-zinc-500">{{ round($myBefore) }}</span>
                        @if ($myDelta > 0)<span class="text-emerald-400">+{{ round($myDelta) }}</span>
                        @elseif ($myDelta < 0)<span class="text-red-400">{{ round($myDelta) }}</span>
                        @else <span class="text-zinc-500">±0</span>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Estado por status --}}

    @if ($match->status === 'drafting')
        <div class="rounded-lg border border-purple-800/50 bg-purple-950/20 p-5">
            <h2 class="font-semibold text-purple-300 mb-2">Drafts en curso</h2>
            <p class="text-sm text-zinc-300 mb-4">Volvé al draft para completar la selección.</p>
            <div class="flex gap-2">
                <a href="{{ route('drafts.maps.show', $match->id) }}" class="rounded border border-steam/60 bg-steam-dark px-4 py-2 text-sm text-steam hover:bg-steam hover:text-steam-dark transition-colors">→ Map draft</a>
                @if ($match->civDraft)
                    <a href="{{ route('drafts.civs.show', $match->id) }}" class="rounded border border-steam/60 bg-steam-dark px-4 py-2 text-sm text-steam hover:bg-steam hover:text-steam-dark transition-colors">→ Civ draft</a>
                @endif
            </div>
        </div>
    @endif

    {{-- Cards: mapa + tu civ + civ rival --}}
    @if ($mapName || $myCivPick)
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Selecciones del draft</h2>
            <div class="grid sm:grid-cols-3 gap-3">
                {{-- Mapa --}}
                <div class="rounded-xl border-2 border-steam/40 bg-gradient-to-br from-steam-dark/30 to-zinc-900/50 p-4 sm:p-5">
                    <div class="text-xs uppercase tracking-wider text-steam/80 font-semibold mb-3">Mapa</div>
                    <div class="flex items-center gap-3">
                        <div class="h-14 w-14 sm:h-16 sm:w-16 shrink-0 rounded-lg bg-steam-dark border border-steam/40 flex items-center justify-center text-steam font-bold text-xl sm:text-2xl">
                            {{ $mapName ? Str::upper(Str::substr($mapName, 0, 1)) : '?' }}
                        </div>
                        <div class="font-bold text-xl sm:text-2xl text-steam truncate">{{ $mapName ?? '—' }}</div>
                    </div>
                </div>

                {{-- Tu civ --}}
                <div class="rounded-xl border-2 border-emerald-700/60 bg-emerald-950/30 p-4 sm:p-5">
                    <div class="text-xs uppercase tracking-wider text-emerald-400/80 font-semibold mb-3">Tu civilización</div>
                    <div class="flex items-center gap-3">
                        <div class="h-14 w-14 sm:h-16 sm:w-16 shrink-0 rounded-lg bg-emerald-950 border border-emerald-800 flex items-center justify-center text-emerald-300 font-bold text-xl sm:text-2xl">
                            {{ $myCivPick ? Str::upper(Str::substr($myCivPick, 0, 1)) : '?' }}
                        </div>
                        <div class="font-bold text-xl sm:text-2xl text-emerald-300 truncate">{{ $myCivPick ?? '—' }}</div>
                    </div>
                </div>

                {{-- Civ del rival --}}
                <div class="rounded-xl border-2 border-red-800/60 bg-red-950/30 p-4 sm:p-5">
                    <div class="text-xs uppercase tracking-wider text-red-400/80 font-semibold mb-3">Civ del rival</div>
                    <div class="flex items-center gap-3">
                        <div class="h-14 w-14 sm:h-16 sm:w-16 shrink-0 rounded-lg bg-red-950 border border-red-900 flex items-center justify-center text-red-300 font-bold text-xl sm:text-2xl">
                            {{ $oppCivPick ? Str::upper(Str::substr($oppCivPick, 0, 1)) : '?' }}
                        </div>
                        <div class="font-bold text-xl sm:text-2xl text-red-300 truncate">{{ $oppCivPick ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Pre-partida: lobby info + checklist --}}
    @if ($match->status === 'pending')
        <section class="rounded-lg border border-amber-700/40 bg-amber-950/10 p-4 sm:p-5">
            <h2 class="font-semibold text-amber-300 flex items-center gap-2 mb-3">
                <span class="text-lg">⚠</span> Antes de iniciar la partida en AoE2
            </h2>
            <ul class="space-y-2 text-sm text-zinc-300">
                <li class="flex items-start gap-2">
                    <span class="text-amber-400">1.</span>
                    <span>
                        @if ($isHost)
                            El companion ya armó la sala. Esperá al rival en el lobby.
                        @else
                            El link al lobby debería abrirse automáticamente cuando el host esté listo.
                            @if ($match->lobby_id)
                                Si no se abrió, <a href="aoe2de://0/{{ $match->lobby_id }}" class="text-steam hover:underline">click acá para entrar</a>.
                            @endif
                        @endif
                    </span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-amber-400">2.</span>
                    <span>Verificá que el mapa esté en <strong class="text-steam">{{ $mapName ?? '—' }}</strong></span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-amber-400">3.</span>
                    <span>Elegí tu civilización: <strong class="text-emerald-300">{{ $myCivPick ?? '—' }}</strong></span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-amber-400">4.</span>
                    <span>Una vez listos los dos, inicien partida</span>
                </li>
            </ul>
            @if ($match->lobby_id)
                <div class="mt-4 pt-3 border-t border-amber-900/40 text-xs text-zinc-500 font-mono">
                    Lobby ID: <a href="aoe2de://0/{{ $match->lobby_id }}" class="text-steam hover:underline">{{ $match->lobby_id }}</a>
                    · Servidor: {{ $match->config_json['server'] ?? '—' }}
                </div>
            @endif
        </section>
    @endif

    {{-- En partida --}}
    @if ($match->status === 'in_progress')
        <section class="rounded-lg border border-sky-800/50 bg-sky-950/20 p-5">
            <h2 class="font-semibold text-sky-300 mb-2 flex items-center gap-2">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75 animate-ping"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-sky-400"></span>
                </span>
                Partida en curso
            </h2>
            <p class="text-sm text-zinc-300">El companion está siguiendo la partida. Cuando termine y suba el replay, el resultado va a aparecer acá.</p>
        </section>
    @endif

    {{-- Replay parseado (cuando completed o invalid) --}}
    @if (! empty($match->parsed_metadata))
        @php $md = $match->parsed_metadata; @endphp
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Datos del replay</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 divide-y divide-zinc-800 text-sm">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4">
                    <div>
                        <div class="text-xs text-zinc-500 uppercase">Game version</div>
                        <div class="font-mono">{{ $md['game_version'] ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 uppercase">Mapa real</div>
                        <div>{{ Str::replaceLast('.rms', '', $md['rms_filename'] ?? '—') }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 uppercase">Operaciones</div>
                        <div class="font-mono">{{ $md['ops_count'] ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500 uppercase">Mensajes chat</div>
                        <div class="font-mono">{{ $md['chat_count'] ?? 0 }}</div>
                    </div>
                </div>

                {{-- Civs jugadas vs drafted --}}
                @if (! empty($md['humans']))
                    <div class="p-4">
                        <div class="text-xs text-zinc-500 uppercase mb-2">Jugadores en el replay</div>
                        <div class="space-y-1.5">
                            @foreach ($md['humans'] as $h)
                                <div class="font-mono text-sm flex items-center gap-2 flex-wrap">
                                    <span class="text-zinc-400">slot {{ $h['number'] }}</span>
                                    <span>{{ $h['name'] }}</span>
                                    <span class="text-zinc-600">→</span>
                                    <span class="text-emerald-300">{{ $h['civilization'] ?? 'civ_id=' . $h['civilization_id'] }}</span>
                                </div>
                            @endforeach
                            @foreach ($md['ais'] ?? [] as $ai)
                                <div class="font-mono text-sm flex items-center gap-2 flex-wrap text-zinc-500">
                                    <span>slot {{ $ai['number'] }}</span>
                                    <span>{{ $ai['ai_name'] }} (AI)</span>
                                    <span>→</span>
                                    <span>{{ $ai['civilization'] ?? '?' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Eventos: resigns + completion --}}
                <div class="p-4 grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div>
                        <div class="text-xs text-zinc-500 uppercase">Terminó natural</div>
                        <div>{{ ($md['saw_postgame'] ?? false) ? '✓ Sí' : '✗ No (truncado)' }}</div>
                    </div>
                    @if (! empty($md['resigned_players']))
                        <div>
                            <div class="text-xs text-zinc-500 uppercase">Resigns</div>
                            <div class="font-mono">slot {{ implode(', ', $md['resigned_players']) }}</div>
                        </div>
                    @endif
                    <div>
                        <div class="text-xs text-zinc-500 uppercase">Mod cargado</div>
                        <div>{{ ! empty($md['mod']) ? $md['mod'] : '✓ vanilla' }}</div>
                    </div>
                </div>

                {{-- Settings clave (si están raros, ya van a estar en validation_errors igual) --}}
                @if (! empty($md['settings']))
                    @php $s = $md['settings']; @endphp
                    <div class="p-4">
                        <div class="text-xs text-zinc-500 uppercase mb-2">Settings</div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs font-mono">
                            <div>pop: <span class="text-zinc-300">{{ $s['population_limit'] ?? '?' }}</span></div>
                            <div>lock_teams: <span class="text-zinc-300">{{ var_export($s['lock_teams'] ?? null, true) }}</span></div>
                            <div>lock_speed: <span class="text-zinc-300">{{ var_export($s['lock_speed'] ?? null, true) }}</span></div>
                            <div>cheats: <span class="text-zinc-300">{{ var_export($s['cheats'] ?? null, true) }}</span></div>
                            <div>treaty: <span class="text-zinc-300">{{ $s['treaty_length'] ?? '?' }}</span></div>
                            <div>multiplayer: <span class="text-zinc-300">{{ var_export($s['multiplayer'] ?? null, true) }}</span></div>
                            <div>shared_explor: <span class="text-zinc-300">{{ var_export($s['shared_exploration'] ?? null, true) }}</span></div>
                            <div>turbo: <span class="text-zinc-300">{{ var_export($s['turbo_enabled'] ?? null, true) }}</span></div>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Validation errors si el match es invalid --}}
    @if ($match->status === 'invalid' && ! empty($match->validation_errors))
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Por qué quedó inválida</h2>
            <div class="rounded-lg border border-orange-800/50 bg-orange-950/20 p-4">
                <p class="text-sm text-zinc-300 mb-3">
                    El replay no pasó la validación de ranked. Sin rating change.
                </p>
                <ul class="list-disc list-inside text-sm text-orange-300 space-y-1">
                    @foreach ($match->validation_errors as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Acciones --}}
    @if ($match->status === 'pending')
        <div class="flex gap-2">
            <form method="POST" action="{{ route('matches.cancel', $match->id) }}" onsubmit="return confirm('¿Cancelar match #{{ $match->id }}?');">
                @csrf
                <button type="submit" class="rounded border border-red-900 bg-red-950/30 px-3 py-1.5 text-sm text-red-300 hover:bg-red-900/40 transition-colors">
                    Cancelar match
                </button>
            </form>
        </div>
    @endif

    {{-- Info opponent (con link a su perfil) --}}
    @if ($opp)
        <section class="text-sm text-zinc-500">
            Rival:
            @if ($oppLinkable)
                <a href="{{ route('users.show', $opp->steam_id) }}" class="text-steam hover:underline">{{ $opp->persona_name ?? Str::limit($opp->steam_id, 14) }}</a>
            @else
                <span>Bot Dev</span>
            @endif
        </section>
    @endif
</div>
@endsection
