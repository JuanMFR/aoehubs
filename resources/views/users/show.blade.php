@extends('layouts.app')

@section('title', ($user->persona_name ?? 'Jugador') . ' — Perfil')

@section('content')
@php
    $isMe = auth()->check() && auth()->id() === $user->id;
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
                    {{ Str::upper(Str::substr($user->persona_name ?? '?', 0, 1)) }}
                </div>
            @endif

            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl sm:text-3xl font-bold truncate">{{ $user->persona_name ?? '—' }}</h1>
                    @if ($isMe)
                        <span class="text-xs px-2 py-0.5 rounded bg-steam-dark text-steam font-semibold uppercase tracking-wider">vos</span>
                    @endif
                    @if ($user->isAdmin())
                        <span class="text-xs px-2 py-0.5 rounded bg-amber-950 text-amber-300 font-semibold uppercase tracking-wider">admin</span>
                    @endif
                </div>
                <div class="mt-1 font-mono text-xs text-zinc-500">{{ $user->steam_id }}</div>

                {{-- Rating + W/L row --}}
                <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <div>
                        <span class="text-zinc-500">Rating</span>
                        <span class="ml-1 font-mono font-semibold text-base">{{ round($user->rating) }}</span>
                        <span class="text-zinc-500 font-mono text-xs">±{{ round($user->rating_deviation) }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500">Récord</span>
                        <span class="ml-1 font-mono">
                            <span class="text-emerald-400 font-semibold">{{ $wins }}W</span><span class="text-zinc-600 mx-0.5">—</span><span class="text-red-400 font-semibold">{{ $losses }}L</span>
                        </span>
                    </div>
                    <div>
                        <span class="text-zinc-500">Win rate</span>
                        <span class="ml-1 font-mono">{{ $winRate }}%</span>
                    </div>
                    <div>
                        <span class="text-zinc-500">Miembro desde</span>
                        <span class="ml-1 font-mono">{{ $user->created_at->format('Y-m-d') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Top civs + top mapas --}}
    <div class="grid sm:grid-cols-2 gap-4">
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Civilizaciones más jugadas</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 divide-y divide-zinc-800">
                @forelse ($topCivs as $c)
                    <div class="flex items-center justify-between px-4 py-2.5 text-sm">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="h-8 w-8 shrink-0 rounded bg-zinc-800 flex items-center justify-center font-bold text-zinc-400 text-xs">
                                {{ Str::upper(Str::substr($c['civ'], 0, 2)) }}
                            </div>
                            <span class="truncate">{{ $c['civ'] }}</span>
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
                            <div class="h-8 w-8 shrink-0 rounded bg-zinc-800 flex items-center justify-center font-bold text-zinc-400 text-xs">
                                {{ Str::upper(Str::substr($m['map'], 0, 2)) }}
                            </div>
                            <span class="truncate">{{ $m['map'] }}</span>
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
                                        <a href="{{ route('users.show', $opp->steam_id) }}" class="hover:text-steam transition-colors">
                                            {{ $opp->persona_name ?? Str::limit($opp->steam_id, 12) }}
                                        </a>
                                    @else
                                        <span class="text-zinc-500">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 hidden sm:table-cell">{{ $m->mapDraft->final_map ?? '—' }}</td>
                                <td class="px-3 py-3 hidden md:table-cell">{{ $myCiv ?? '—' }}</td>
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
</div>
@endsection
