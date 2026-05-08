@extends('layouts.app')

@section('title', 'Match #' . $match->id . ' — AoEHubs')

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
            <a href="{{ route('matches.index') }}" class="text-sm text-accent hover:underline">← Mis matches</a>
            <h1 class="mt-2 text-2xl font-bold flex items-center gap-3 flex-wrap">
                Match <span class="font-mono text-zinc-500">#{{ $match->id }}</span>
                <span class="badge badge-{{ $match->status }}">{{ __($match->status) }}</span>
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
                <a href="{{ route('drafts.maps.show', $match->id) }}" class="rounded border border-accent/60 bg-accent-dark px-4 py-2 text-sm text-accent hover:bg-accent hover:text-accent-dark transition-colors">→ Map draft</a>
                @if ($match->civDraft)
                    <a href="{{ route('drafts.civs.show', $match->id) }}" class="rounded border border-accent/60 bg-accent-dark px-4 py-2 text-sm text-accent hover:bg-accent hover:text-accent-dark transition-colors">→ Civ draft</a>
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
                <div class="rounded-xl border-2 border-accent/40 bg-gradient-to-br from-accent-dark/30 to-zinc-900/50 p-4 sm:p-5">
                    <div class="text-xs uppercase tracking-wider text-accent/80 font-semibold mb-3">Mapa</div>
                    <div class="flex items-center gap-3">
                        <x-map-icon :name="$mapName"
                                    class="h-14 w-14 sm:h-16 sm:w-16 shrink-0 rounded-lg" />
                        <div class="font-bold text-xl sm:text-2xl text-accent truncate">{{ $mapName ? __($mapName) : '—' }}</div>
                    </div>
                </div>

                {{-- Tu civ --}}
                <div class="rounded-xl border-2 border-emerald-700/60 bg-emerald-950/30 p-4 sm:p-5">
                    <div class="text-xs uppercase tracking-wider text-emerald-400/80 font-semibold mb-3">Tu civilización</div>
                    <div class="flex items-center gap-3">
                        <x-civ-icon :name="$myCivPick"
                                    class="h-14 w-14 sm:h-16 sm:w-16 shrink-0 rounded-lg" />
                        <div class="font-bold text-xl sm:text-2xl text-emerald-300 truncate">{{ $myCivPick ? __($myCivPick) : '—' }}</div>
                    </div>
                </div>

                {{-- Civ del rival --}}
                <div class="rounded-xl border-2 border-red-800/60 bg-red-950/30 p-4 sm:p-5">
                    <div class="text-xs uppercase tracking-wider text-red-400/80 font-semibold mb-3">Civ del rival</div>
                    <div class="flex items-center gap-3">
                        <x-civ-icon :name="$oppCivPick"
                                    class="h-14 w-14 sm:h-16 sm:w-16 shrink-0 rounded-lg" />
                        <div class="font-bold text-xl sm:text-2xl text-red-300 truncate">{{ $oppCivPick ? __($oppCivPick) : '—' }}</div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Pre-partida: rol del jugador + instrucciones --}}
    @if ($match->status === 'pending')
        <section class="rounded-xl border-2 {{ $isHost ? 'border-accent/40 bg-accent-dark/10' : 'border-sky-700/40 bg-sky-950/10' }} p-5 sm:p-6">
            <div class="flex flex-wrap items-center gap-3 mb-4">
                @if ($isHost)
                    <span class="px-3 py-1 rounded bg-accent text-accent-dark font-bold text-xs uppercase tracking-wider">
                        Sos host
                    </span>
                    <span class="text-zinc-300 text-sm">Vos armás la sala — tu rival entra cuando esté lista.</span>
                @else
                    <span class="px-3 py-1 rounded bg-sky-600 text-sky-50 font-bold text-xs uppercase tracking-wider">
                        Sos joiner
                    </span>
                    <span class="text-zinc-300 text-sm">Tu rival está armando la sala — vos solo esperás.</span>
                @endif
            </div>

            <h3 class="text-xs font-semibold uppercase tracking-wider {{ $isHost ? 'text-accent' : 'text-sky-300' }} mb-3">
                Qué tenés que hacer
            </h3>

            @if ($isHost)
                <ol class="space-y-2.5 text-sm text-zinc-300">
                    <li class="flex items-start gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-dark border border-accent/60 text-accent font-bold text-xs">1</span>
                        <span>Abrí <strong>Age of Empires 2 DE</strong> y verificá que el companion esté corriendo.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-dark border border-accent/60 text-accent font-bold text-xs">2</span>
                        <span>Andá a <strong>Multijugador → Organizar partida</strong>.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-dark border border-accent/60 text-accent font-bold text-xs">3</span>
                        <span>El companion va a completar los datos de creación de la sala (nombre, password, server) y los settings del lobby (población, lock teams, victoria, etc.). <strong class="text-zinc-100">No toques nada</strong> mientras lo hace.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-dark border border-accent/60 text-accent font-bold text-xs">4</span>
                        <span>Una vez en el lobby, <strong class="text-zinc-100">elegí el mapa: <span class="text-accent">{{ $mapName ? __($mapName) : '—' }}</span></strong>. El companion no setea el mapa — eso lo tenés que hacer vos.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-dark border border-accent/60 text-accent font-bold text-xs">5</span>
                        <span>Cuando entre tu rival, <strong class="text-zinc-100">elegí tu civilización: <span class="text-emerald-300">{{ $myCivPick ? __($myCivPick) : '—' }}</span></strong>.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-dark border border-accent/60 text-accent font-bold text-xs">6</span>
                        <span>Cuando estén ambos listos, arranquen la partida.</span>
                    </li>
                </ol>
            @else
                <ol class="space-y-2.5 text-sm text-zinc-300">
                    <li class="flex items-start gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-sky-950 border border-sky-700 text-sky-300 font-bold text-xs">1</span>
                        <span>Abrí <strong>Age of Empires 2 DE</strong> y verificá que el companion esté corriendo.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-sky-950 border border-sky-700 text-sky-300 font-bold text-xs">2</span>
                        <span>Esperá. El companion va a abrir el link a la sala automáticamente cuando el host la termine de armar.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-sky-950 border border-sky-700 text-sky-300 font-bold text-xs">3</span>
                        <span>Cuando AoE2 te pida la contraseña de la sala, el companion la va a poner solo.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-sky-950 border border-sky-700 text-sky-300 font-bold text-xs">4</span>
                        <span>Una vez en el lobby, <strong class="text-zinc-100">elegí tu civilización: <span class="text-emerald-300">{{ $myCivPick ? __($myCivPick) : '—' }}</span></strong>. El mapa lo setea el host.</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-sky-950 border border-sky-700 text-sky-300 font-bold text-xs">5</span>
                        <span>Cuando estén ambos listos, arranquen la partida.</span>
                    </li>
                </ol>
            @endif

            <div class="mt-5 pt-4 border-t {{ $isHost ? 'border-accent/20' : 'border-sky-900/40' }} text-xs text-zinc-500 grid sm:grid-cols-3 gap-2">
                <div>Mapa: <strong class="text-zinc-300">{{ $mapName ? __($mapName) : '—' }}</strong></div>
                <div>Tu civ: <strong class="text-zinc-300">{{ $myCivPick ? __($myCivPick) : '—' }}</strong></div>
                @if ($match->lobby_id)
                    <div>Lobby: <a href="aoe2de://0/{{ $match->lobby_id }}" class="text-accent hover:underline font-mono">{{ $match->lobby_id }}</a></div>
                @endif
            </div>
        </section>

        {{-- Fallback manual: si el companion falla por OCR raro/cambio de UI/etc.,
             el host puede armar la sala a mano con esta info y la partida sigue. --}}
        <section>
            <details class="group rounded-xl border border-amber-900/40 bg-amber-950/10">
                <summary class="cursor-pointer select-none flex items-center gap-2 px-5 py-4 text-sm font-medium text-amber-300 hover:text-amber-200">
                    <span class="inline-block transition-transform group-open:rotate-90">▶</span>
                    ¿El companion no configuró la sala? Crear manualmente
                </summary>
                <div class="px-5 pb-5 pt-2 border-t border-amber-900/40 space-y-4">
                    @php
                        $cfg      = $match->config_json ?? [];
                        $lobbyNm  = $cfg['lobbyName'] ?? '—';
                        $password = $cfg['password']  ?? '—';
                        $server   = $cfg['server']    ?? '—';
                    @endphp

                    <p class="text-sm text-zinc-300">
                        Si el companion no detecta la sala o falla al configurarla, podés armar la sala a mano con estos datos.
                        El sistema valida después la partida igual contra el draft, así que <strong class="text-amber-300">la match cuenta para rating si jugás con la config correcta</strong>.
                    </p>

                    @if ($isHost)
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-2">1. Crear sala (Multijugador → Organizar partida)</h3>
                            <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm font-mono">
                                <div><span class="text-zinc-500">Nombre:</span> <span class="text-accent select-all">{{ $lobbyNm }}</span></div>
                                <div><span class="text-zinc-500">Password:</span> <span class="text-accent select-all">{{ $password }}</span></div>
                                <div><span class="text-zinc-500">Servidor:</span> {{ $server }}</div>
                                <div><span class="text-zinc-500">Visibilidad:</span> Pública</div>
                                <div><span class="text-zinc-500">Cantidad:</span> 2 jugadores</div>
                                <div><span class="text-zinc-500">Retraso:</span> 3 minutos</div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-2">2. Settings de la sala (panel principal)</h3>
                            <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                                <div class="font-mono"><span class="text-zinc-500">Mapa:</span> <span class="text-accent">{{ $mapName ? __($mapName) : '—' }}</span></div>
                                <div class="font-mono"><span class="text-zinc-500">Tamaño:</span> Pequeñito</div>
                                <div class="font-mono"><span class="text-zinc-500">Recursos:</span> Estándar</div>
                                <div class="font-mono"><span class="text-zinc-500">Velocidad:</span> Normal</div>
                                <div class="font-mono"><span class="text-zinc-500">Población:</span> 200</div>
                                <div class="font-mono"><span class="text-zinc-500">Edad inicial:</span> Estándar</div>
                                <div class="font-mono"><span class="text-zinc-500">Edad final:</span> Estándar</div>
                                <div class="font-mono"><span class="text-zinc-500">Victoria:</span> Conquista</div>
                                <div class="font-mono col-span-1 sm:col-span-2"><span class="text-zinc-500">Visibilidad:</span> según mapa (Selva Negra → Todo visible · Arena → Explorado · resto → Normal)</div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-2">3. Equipos (sección "Equipos")</h3>
                            <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-3 text-sm font-mono space-y-1">
                                <div>✓ Bloquear equipos</div>
                                <div>✓ Equipos juntos</div>
                                <div>✓ Exploración compartida</div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-2">4. Avanzado (sección "Avanzado")</h3>
                            <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-3 text-sm font-mono space-y-1">
                                <div>✓ Bloquear velocidad</div>
                                <div>✗ Permitir trampas</div>
                                <div>✗ Modo turbo</div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-2">5. Civilización</h3>
                            <p class="text-sm text-zinc-400">
                                Elegí <strong class="text-emerald-300">{{ $myCivPick ? __($myCivPick) : '—' }}</strong> en tu slot. Tu rival va a elegir su civ del draft cuando entre.
                            </p>
                        </div>

                        <div class="text-xs text-zinc-500 italic">
                            Compartile el nombre de sala + password al rival si tarda en aparecer (Discord, etc.). El joiner también tiene su panel de fallback con esta info.
                        </div>
                    @else
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-2">Datos para entrar a la sala</h3>
                            <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm font-mono">
                                <div><span class="text-zinc-500">Nombre de sala:</span> <span class="text-accent select-all">{{ $lobbyNm }}</span></div>
                                <div><span class="text-zinc-500">Password:</span> <span class="text-accent select-all">{{ $password }}</span></div>
                                <div><span class="text-zinc-500">Servidor:</span> {{ $server }}</div>
                                @if ($match->lobby_id)
                                    <div class="col-span-1 sm:col-span-2">
                                        <span class="text-zinc-500">Link directo:</span>
                                        <a href="aoe2de://0/{{ $match->lobby_id }}" class="text-accent hover:underline">aoe2de://0/{{ $match->lobby_id }}</a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-2">Cómo entrar manualmente</h3>
                            <ol class="text-sm text-zinc-400 list-decimal pl-5 space-y-1">
                                <li>Multijugador → Buscar partida → filtro por servidor <strong class="text-zinc-200">{{ $server }}</strong>.</li>
                                <li>Buscá la sala <strong class="text-accent">{{ $lobbyNm }}</strong> y entrá.</li>
                                <li>Pegá la password: <code class="text-accent">{{ $password }}</code></li>
                                <li>En el lobby, elegí tu civilización: <strong class="text-emerald-300">{{ $myCivPick ? __($myCivPick) : '—' }}</strong>.</li>
                            </ol>
                        </div>

                        <div class="text-xs text-zinc-500 italic">
                            Si la sala no aparece todavía, el host puede estar tardando en armarla. Avisale por Discord si pasa tiempo.
                        </div>
                    @endif
                </div>
            </details>
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

    {{-- Historial del draft — bans de mapa + civ picks/bans --}}
    @php
        $mapBans = $match->mapDraft?->bans_json ?? [];
        $cd = $match->civDraft;
        $hasDraftHistory = !empty($mapBans) || ($cd && (!empty($cd->host_picks_json) || !empty($cd->opponent_picks_json)));

        // Picks/bans desde la perspectiva del user.
        $myPicks   = $cd ? ($isHost ? $cd->host_picks_json     : $cd->opponent_picks_json)     : null;
        $oppPicks  = $cd ? ($isHost ? $cd->opponent_picks_json : $cd->host_picks_json)         : null;
        // host_bans_json son los bans QUE EL HOST hizo contra picks del opponent.
        // Si yo soy host, mis bans afectan a oppPicks; los bans contra mí están en opponent_bans_json.
        $myBansAgainstOpp = $cd ? ($isHost ? $cd->host_bans_json     : $cd->opponent_bans_json) : null;
        $oppBansAgainstMe = $cd ? ($isHost ? $cd->opponent_bans_json : $cd->host_bans_json)     : null;
    @endphp

    @if ($hasDraftHistory)
        <section>
            <details class="group rounded-lg border border-zinc-800 bg-zinc-900/40">
                <summary class="cursor-pointer select-none flex items-center gap-2 px-4 py-3 text-sm font-medium text-zinc-300 hover:text-zinc-100">
                    <span class="inline-block transition-transform group-open:rotate-90">▶</span>
                    Historial del draft
                </summary>
                <div class="px-4 pb-4 pt-2 space-y-5 border-t border-zinc-800">
                    {{-- Map bans en orden cronologico --}}
                    @if (!empty($mapBans))
                        @php
                            $myName    = auth()->user()->displayName();
                            $rivalUser = $opp;
                            $rivalName = $rivalUser ? $rivalUser->displayName() : 'Rival';
                        @endphp
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-2">Mapas baneados</h3>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($mapBans as $b)
                                    @php $byMe = $b['user_id'] === auth()->id(); @endphp
                                    <span class="inline-flex items-center gap-1.5 rounded border border-zinc-700 bg-zinc-900 px-2 py-1 text-xs">
                                        <x-map-icon :name="$b['map']" class="h-4 w-4 rounded shrink-0 text-[8px]" />
                                        <span class="text-[10px] uppercase tracking-wider {{ $byMe ? 'text-emerald-400' : 'text-red-400' }}">
                                            {{ $byMe ? $myName : $rivalName }}
                                        </span>
                                        <span class="line-through text-zinc-400">{{ __($b['map']) }}</span>
                                    </span>
                                @endforeach
                                @if ($mapName)
                                    <span class="inline-flex items-center gap-1.5 rounded border-2 border-accent bg-accent-dark/40 px-2 py-1 text-xs">
                                        <x-map-icon :name="$mapName" class="h-4 w-4 rounded shrink-0" />
                                        <span class="text-[10px] uppercase tracking-wider text-accent">Final</span>
                                        <span class="text-accent font-semibold">{{ __($mapName) }}</span>
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Civ picks/bans --}}
                    @if ($cd && !empty($myPicks))
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-2">Civilizaciones</h3>
                            <div class="grid sm:grid-cols-2 gap-3 text-sm">
                                {{-- Tus picks --}}
                                <div class="rounded-lg border border-emerald-800/40 bg-emerald-950/10 p-3">
                                    <div class="text-xs text-emerald-400 mb-2 font-medium">Tus picks</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($myPicks as $civ)
                                            @php
                                                $isFinal = $civ === $myCivPick;
                                                $isBanned = $oppBansAgainstMe && in_array($civ, $oppBansAgainstMe);
                                            @endphp
                                            <span @class([
                                                'inline-flex items-center gap-1.5 rounded border px-2 py-1 text-xs',
                                                'border-2 border-emerald-400 bg-emerald-950/40 text-emerald-300 font-semibold' => $isFinal,
                                                'border-red-900 bg-zinc-900 text-zinc-500 line-through' => $isBanned,
                                                'border-zinc-700 bg-zinc-900 text-zinc-300' => !$isFinal && !$isBanned,
                                            ])>
                                                <x-civ-icon :name="$civ" class="h-4 w-4 rounded text-[8px]" />
                                                {{ __($civ) }}
                                                @if ($isFinal) <span class="ml-1 text-[10px]">✓</span> @endif
                                            </span>
                                        @endforeach
                                    </div>
                                    @if (!empty($myBansAgainstOpp))
                                        <div class="mt-3 text-xs text-zinc-500">
                                            Banneaste del rival:
                                            <span class="text-red-400">{{ collect($myBansAgainstOpp)->map(fn($c) => __($c))->join(', ') }}</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- Picks del rival --}}
                                <div class="rounded-lg border border-red-900/40 bg-red-950/10 p-3">
                                    <div class="text-xs text-red-400 mb-2 font-medium">Picks del rival</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($oppPicks as $civ)
                                            @php
                                                $isFinal = $civ === $oppCivPick;
                                                $isBanned = $myBansAgainstOpp && in_array($civ, $myBansAgainstOpp);
                                            @endphp
                                            <span @class([
                                                'inline-flex items-center gap-1.5 rounded border px-2 py-1 text-xs',
                                                'border-2 border-red-400 bg-red-950/40 text-red-300 font-semibold' => $isFinal,
                                                'border-red-900 bg-zinc-900 text-zinc-500 line-through' => $isBanned,
                                                'border-zinc-700 bg-zinc-900 text-zinc-300' => !$isFinal && !$isBanned,
                                            ])>
                                                <x-civ-icon :name="$civ" class="h-4 w-4 rounded text-[8px]" />
                                                {{ __($civ) }}
                                                @if ($isFinal) <span class="ml-1 text-[10px]">✓</span> @endif
                                            </span>
                                        @endforeach
                                    </div>
                                    @if (!empty($oppBansAgainstMe))
                                        <div class="mt-3 text-xs text-zinc-500">
                                            Te banneó: <span class="text-red-400">{{ collect($oppBansAgainstMe)->map(fn($c) => __($c))->join(', ') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </details>
        </section>
    @endif

    {{-- Validation errors si el match es invalid --}}
    @if ($match->status === 'invalid' && ! empty($match->validation_errors))
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Esta partida no se contó como ranked</h2>
            <div class="rounded-lg border border-orange-800/50 bg-orange-950/20 p-4">
                <p class="text-sm text-zinc-300 mb-3">
                    El replay no cumple con las reglas de partidas ranked, así que no hubo cambio de rating.
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
            <button type="button"
                    onclick="document.getElementById('cancel-pending-{{ $match->id }}').showModal()"
                    class="rounded border border-red-900 bg-red-950/30 px-3 py-1.5 text-sm text-red-300 hover:bg-red-900/40 transition-colors">
                Cancelar match
            </button>
        </div>
        <x-confirm-modal id="cancel-pending-{{ $match->id }}"
                         title="¿Cancelar el match?"
                         :action="route('matches.cancel', $match->id)"
                         confirmLabel="Sí, abandonar"
                         cancelLabel="No, mantener"
                         :danger="true">
            <p>Vas a abandonar la partida antes de que arranque.</p>
            <p class="text-accent">El tiempo de tu rival también es valioso.</p>
            <p class="text-xs text-zinc-500">Si lo hacés repetidamente vas a quedar bloqueado para buscar partida durante un tiempo.</p>
        </x-confirm-modal>
    @endif

    {{-- Info opponent (con link a su perfil) --}}
    @if ($opp)
        <section class="text-sm text-zinc-500">
            Rival:
            @if ($oppLinkable)
                <a href="{{ route('users.show', $opp->steam_id) }}" class="text-accent hover:underline">{{ $opp->persona_name ?? Str::limit($opp->steam_id, 14) }}</a>
            @else
                <span>Bot Dev</span>
            @endif
        </section>
    @endif
</div>
@endsection
