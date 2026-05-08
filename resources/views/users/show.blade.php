@extends('layouts.app')

@section('title', $user->displayName() . ' — Perfil')

@section('content')
@php
    $isMe = auth()->check() && auth()->id() === $user->id;
    $personaName = $user->displayName();
@endphp

<div class="space-y-8">
    {{-- Header del perfil --}}
    <section class="rounded-xl border border-zinc-800 bg-gradient-to-br from-zinc-900/80 to-zinc-950 p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row gap-5">
            {{-- Avatar (si esta), placeholder si no --}}
            @if ($user->avatar_url)
                <img src="{{ $user->avatar_url }}" alt=""
                     class="h-24 w-24 sm:h-28 sm:w-28 rounded-lg border border-zinc-700 shrink-0">
            @else
                <div class="h-24 w-24 sm:h-28 sm:w-28 rounded-lg bg-zinc-900 border border-zinc-700 flex items-center justify-center text-3xl sm:text-4xl text-zinc-500 shrink-0">
                    {{ Str::upper(Str::substr($personaName, 0, 1)) }}
                </div>
            @endif

            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl sm:text-3xl font-bold truncate">{{ $personaName }}</h1>
                    @if ($isMe)
                        <span class="text-xs px-2 py-0.5 rounded bg-accent-dark text-accent font-semibold uppercase tracking-wider">vos</span>
                    @endif
                    @if ($user->isAdmin())
                        <span class="text-xs px-2 py-0.5 rounded bg-amber-950 text-amber-300 font-semibold uppercase tracking-wider">admin</span>
                    @endif
                </div>
                <div class="mt-1 font-mono text-xs text-zinc-500">{{ $user->steam_id }}</div>

                {{-- Stats de la season actual (prominente). Si no hay season activa,
                     fallback a all-time. --}}
                <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <div>
                        <span class="text-zinc-500">Rating</span>
                        <span class="ml-1 font-mono font-semibold text-base">{{ round($user->rating) }}</span>
                        <span class="text-zinc-500 font-mono text-xs">±{{ round($user->rating_deviation) }}</span>
                    </div>
                    @if ($currentSeason)
                        <div>
                            <span class="text-zinc-500">{{ $currentSeason->name }}</span>
                            <span class="ml-1 font-mono">
                                <span class="text-emerald-400 font-semibold">{{ $seasonWins }}W</span><span class="text-zinc-600 mx-0.5">—</span><span class="text-red-400 font-semibold">{{ $seasonLosses }}L</span>
                            </span>
                            @if ($seasonTotal > 0)
                                <span class="text-zinc-500 ml-1">{{ $seasonWinRate }}%</span>
                            @endif
                        </div>
                    @else
                        <div>
                            <span class="text-zinc-500">Récord</span>
                            <span class="ml-1 font-mono">
                                <span class="text-emerald-400 font-semibold">{{ $wins }}W</span><span class="text-zinc-600 mx-0.5">—</span><span class="text-red-400 font-semibold">{{ $losses }}L</span>
                            </span>
                            <span class="text-zinc-500 ml-1">{{ $winRate }}%</span>
                        </div>
                    @endif
                    <div>
                        <span class="text-zinc-500">Miembro desde</span>
                        <span class="ml-1 font-mono">{{ $user->created_at->format('Y-m-d') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Vitrina de la season activa --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">
            Vitrina @if ($currentSeason) — {{ $currentSeason->name }} @endif
        </h2>
        <x-vitrina :user="$user" season="current" />
    </section>

    {{-- Companion (solo el propio user lo ve) --}}
    @if ($isMe)
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Companion</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4 sm:p-5 space-y-4">
                {{-- Estado de vinculacion --}}
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-start gap-3">
                        @if ($companionToken && $companionToken->last_used_at)
                            <span class="mt-0.5 inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400 shrink-0"></span>
                            <div>
                                <div class="font-medium text-emerald-300">Vinculado</div>
                                <p class="text-xs text-zinc-500 mt-0.5">
                                    Última actividad: <span class="font-mono">{{ $companionToken->last_used_at->diffForHumans() }}</span>
                                </p>
                            </div>
                        @elseif ($companionToken)
                            <span class="mt-0.5 inline-flex h-2.5 w-2.5 rounded-full bg-amber-400 shrink-0"></span>
                            <div>
                                <div class="font-medium text-amber-300">Token generado, sin uso aún</div>
                                <p class="text-xs text-zinc-500 mt-0.5">
                                    El companion todavía no se conectó con este token. Pegalo en el setup del companion.
                                </p>
                            </div>
                        @else
                            <span class="mt-0.5 inline-flex h-2.5 w-2.5 rounded-full bg-zinc-600 shrink-0"></span>
                            <div>
                                <div class="font-medium text-zinc-300">Sin vincular</div>
                                <p class="text-xs text-zinc-500 mt-0.5">
                                    Generá un token y pegalo en el setup del companion para empezar.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Acciones: generar token + (si no esta vinculado) link a descarga --}}
                <div class="border-t border-zinc-800 pt-4 flex flex-col sm:flex-row gap-3">
                    <button type="button"
                            onclick="document.getElementById('token-modal').showModal()"
                            class="rounded border border-accent bg-accent-dark px-4 py-2 text-sm font-semibold text-accent hover:bg-accent hover:text-accent-dark transition-colors">
                        {{ $companionToken ? 'Regenerar token' : 'Generar token' }}
                    </button>
                    @if (! $companionToken || ! $companionToken->last_used_at)
                        <a href="{{ route('companion') }}"
                           class="rounded border border-zinc-700 bg-zinc-900 px-4 py-2 text-sm font-semibold text-zinc-300 hover:bg-zinc-800 transition-colors text-center">
                            Descargar companion →
                        </a>
                    @endif
                </div>

                <x-confirm-modal id="token-modal"
                                 title="{{ $companionToken ? 'Regenerar token' : 'Generar nuevo token' }}"
                                 :action="route('companion.token')"
                                 confirmLabel="{{ $companionToken ? 'Sí, regenerar' : 'Generar' }}"
                                 :danger="(bool) $companionToken">
                    @if ($companionToken)
                        <p class="text-amber-300 font-medium">Esto invalida el token actual.</p>
                        <ul class="text-xs text-zinc-500 list-disc pl-5 space-y-1">
                            <li>Tu companion va a dejar de funcionar hasta que pegues el nuevo token en su setup.</li>
                            <li>Si tu PC/companion sigue corriendo cuando regenerás, va a tirar errores 401 hasta reconfigurarlo.</li>
                            <li>Generá uno nuevo solo si perdiste el anterior o sospechás que se filtró.</li>
                        </ul>
                    @else
                        <p>Vas a recibir un token de un solo uso. <strong>Copialo y pegalo en el companion en ese momento</strong> — por seguridad no se vuelve a mostrar.</p>
                    @endif
                </x-confirm-modal>

                {{-- Token nuevo recien generado (one-shot, viene del flash) --}}
                @if (session('companion_token'))
                    <div class="rounded-lg border border-accent bg-accent-dark/40 p-4">
                        <div class="text-sm font-semibold mb-2">Tu nuevo token:</div>
                        <code class="block break-all rounded bg-black p-3 font-mono text-sm text-accent select-all">{{ session('companion_token') }}</code>
                        <p class="mt-2 text-xs text-amber-400">
                            ⚠ Guardalo ahora — por seguridad no se vuelve a mostrar. Si lo perdés, generá uno nuevo.
                        </p>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Top civs + top mapas --}}
    <div class="grid sm:grid-cols-2 gap-4">
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Civilizaciones más jugadas</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 divide-y divide-zinc-800">
                @forelse ($topCivs as $c)
                    <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                        <div class="flex items-center gap-3 min-w-0">
                            <x-civ-icon :name="$c['civ']" class="h-8 w-8 shrink-0 rounded text-xs" />
                            <span class="truncate">{{ __($c['civ']) }}</span>
                        </div>
                        <div class="flex items-center gap-3 font-mono text-xs shrink-0">
                            <span class="text-zinc-500">{{ $c['played'] }} jug.</span>
                            <span class="{{ $c['win_rate'] >= 50 ? 'text-emerald-400' : 'text-red-400' }}">{{ $c['win_rate'] }}%</span>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-zinc-500">Todavía no hay partidas con civ definida.</div>
                @endforelse
            </div>
        </section>

        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Mapas más jugados</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 divide-y divide-zinc-800">
                @forelse ($topMaps as $m)
                    <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                        <div class="flex items-center gap-3 min-w-0">
                            <x-map-icon :name="$m['map']" class="h-8 w-8 shrink-0 rounded text-xs" />
                            <span class="truncate">{{ __($m['map']) }}</span>
                        </div>
                        <div class="flex items-center gap-3 font-mono text-xs shrink-0">
                            <span class="text-zinc-500">{{ $m['played'] }} jug.</span>
                            <span class="{{ $m['win_rate'] >= 50 ? 'text-emerald-400' : 'text-red-400' }}">{{ $m['win_rate'] }}%</span>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-zinc-500">Todavía no hay partidas con mapa definido.</div>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Ultimas matches --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Últimas partidas</h2>
        @if ($recentMatches->isEmpty())
            <div class="rounded-lg border border-dashed border-zinc-800 bg-zinc-900/30 p-8 text-center text-sm text-zinc-500">
                {{ $isMe ? 'No jugaste partidas todavía.' : 'Este jugador no completó ninguna partida.' }}
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-800">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-900/60">
                        <tr class="text-left text-xs uppercase tracking-wider text-zinc-500">
                            <th class="px-3 py-3">vs</th>
                            <th class="px-3 py-3 hidden sm:table-cell">Mapa</th>
                            <th class="px-3 py-3 hidden md:table-cell">Civ</th>
                            <th class="px-3 py-3">Resultado</th>
                            <th class="px-3 py-3 text-right hidden sm:table-cell">ΔRating</th>
                            <th class="px-3 py-3 hidden md:table-cell">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach ($recentMatches as $m)
                            @php
                                $isHost = $m->host_user_id === $user->id;
                                $opp    = $isHost ? $m->opponent : $m->host;
                                $myCiv  = $m->civDraft ? ($isHost ? $m->civDraft->host_final_civ : $m->civDraft->opponent_final_civ) : null;
                                $won    = $m->winner_user_id === $user->id;
                                $delta  = $isHost ? $m->host_rating_change : $m->opponent_rating_change;
                            @endphp
                            <tr class="hover:bg-zinc-900/40 transition-colors">
                                <td class="px-3 py-3">
                                    @if ($opp)
                                        <a href="{{ route('users.show', $opp->steam_id) }}" class="hover:text-accent transition-colors">
                                            {{ $opp->persona_name ?? Str::limit($opp->steam_id, 12) }}
                                        </a>
                                    @else
                                        <span class="text-zinc-500">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 hidden sm:table-cell">{{ $m->mapDraft?->final_map ? __($m->mapDraft->final_map) : '—' }}</td>
                                <td class="px-3 py-3 hidden md:table-cell">{{ $myCiv ? __($myCiv) : '—' }}</td>
                                <td class="px-3 py-3">
                                    @if ($won)
                                        <span class="font-semibold text-emerald-400">WIN</span>
                                    @elseif ($m->winner_user_id !== null)
                                        <span class="font-semibold text-red-400">LOSS</span>
                                    @else
                                        <span class="text-zinc-600">—</span>
                                    @endif
                                    @if ($m->replay_path === null)
                                        <span class="block text-xs text-zinc-500">walkover</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right font-mono text-xs hidden sm:table-cell whitespace-nowrap">
                                    @if ($delta !== null)
                                        @if ($delta > 0)<span class="text-emerald-400">+{{ round($delta) }}</span>
                                        @elseif ($delta < 0)<span class="text-red-400">{{ round($delta) }}</span>
                                        @else <span class="text-zinc-500">±0</span>
                                        @endif
                                    @else
                                        <span class="text-zinc-600">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 font-mono text-xs text-zinc-500 hidden md:table-cell whitespace-nowrap">{{ $m->created_at->format('Y-m-d') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Histórico — collapsible, all-time + seasons cerradas --}}
    <section>
        <details class="group">
            <summary class="cursor-pointer select-none flex items-center gap-2 mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 hover:text-zinc-300">
                <span class="inline-block transition-transform group-open:rotate-90">▶</span>
                Histórico (all-time)
            </summary>

            <div class="space-y-6 pt-2">
                {{-- Stats all-time --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
                        <div class="text-xs text-zinc-500 uppercase tracking-wider">All-time récord</div>
                        <div class="mt-1 font-mono text-lg font-semibold">
                            <span class="text-emerald-400">{{ $wins }}W</span><span class="text-zinc-600">—</span><span class="text-red-400">{{ $losses }}L</span>
                        </div>
                        <div class="text-xs text-zinc-500">{{ $totalCompleted }} partidas · {{ $winRate }}% WR</div>
                    </div>
                    <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
                        <div class="text-xs text-zinc-500 uppercase tracking-wider">Seasons jugadas</div>
                        <div class="mt-1 font-mono text-lg font-semibold">{{ $pastSeasonStats->count() }}</div>
                        <div class="text-xs text-zinc-500">cerradas</div>
                    </div>
                    @php
                        $bestRank = $pastSeasonStats->whereNotNull('final_rank')->sortBy('final_rank')->first();
                        $peakRating = $pastSeasonStats->max('peak_rating') ?: $user->rating;
                    @endphp
                    <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
                        <div class="text-xs text-zinc-500 uppercase tracking-wider">Mejor rank</div>
                        @if ($bestRank)
                            <div class="mt-1 font-mono text-lg font-semibold text-accent">#{{ $bestRank->final_rank }}</div>
                            <div class="text-xs text-zinc-500 truncate">{{ $bestRank->season->name }}</div>
                        @else
                            <div class="mt-1 text-zinc-600">—</div>
                        @endif
                    </div>
                    <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
                        <div class="text-xs text-zinc-500 uppercase tracking-wider">Peak rating</div>
                        <div class="mt-1 font-mono text-lg font-semibold">{{ round($peakRating) }}</div>
                    </div>
                </div>

                {{-- Vitrina all-time --}}
                <div>
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Vitrina all-time</h3>
                    <x-vitrina :user="$user" season="all_time" />
                </div>

                {{-- Tabla de seasons cerradas --}}
                @if ($pastSeasonStats->count() > 0)
                    <div>
                        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Por season</h3>
                        <div class="overflow-x-auto rounded-lg border border-zinc-800">
                            <table class="w-full text-sm">
                                <thead class="bg-zinc-900/60">
                                    <tr class="text-left text-xs uppercase tracking-wider text-zinc-500">
                                        <th class="px-3 py-2">Season</th>
                                        <th class="px-3 py-2 text-right">Final rating</th>
                                        <th class="px-3 py-2 text-right">Final rank</th>
                                        <th class="px-3 py-2 text-right">Peak</th>
                                        <th class="px-3 py-2 text-right">W/L</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-800">
                                    @foreach ($pastSeasonStats as $s)
                                        <tr class="hover:bg-zinc-900/40 transition-colors">
                                            <td class="px-3 py-2 font-medium">{{ $s->season->name }}</td>
                                            <td class="px-3 py-2 text-right font-mono">{{ round($s->final_rating) }}</td>
                                            <td class="px-3 py-2 text-right font-mono {{ $s->final_rank && $s->final_rank <= 10 ? 'text-accent font-semibold' : '' }}">
                                                {{ $s->final_rank ? '#' . $s->final_rank : '—' }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-mono">{{ round($s->peak_rating) }}</td>
                                            <td class="px-3 py-2 text-right font-mono">
                                                <span class="text-emerald-400">{{ $s->matches_won }}</span><span class="text-zinc-600">—</span><span class="text-red-400">{{ $s->matches_played - $s->matches_won }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </details>
    </section>
</div>
@endsection
