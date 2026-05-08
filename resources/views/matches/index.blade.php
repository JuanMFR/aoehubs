@extends('layouts.app')

@section('title', 'Mis matches — AoE2 Rank')

@section('content')
<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold">Mis matches</h1>
        <p class="mt-1 text-sm text-zinc-500">Historial completo de tus partidas.</p>
    </div>

    {{-- Historial --}}
    <section>
        <div class="flex items-baseline justify-between gap-2 mb-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Historial</h2>
            <span class="text-xs text-zinc-500">{{ $matches->total() }} {{ Str::plural('match', $matches->total()) }}</span>
        </div>

        {{-- Filtros --}}
        <form method="GET" class="rounded-lg border border-zinc-800 bg-zinc-900/40 p-3 sm:p-4 mb-4 grid sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-zinc-500 mb-1">Estado</label>
                <select name="status" class="w-full rounded border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm focus:border-steam focus:outline-none">
                    <option value="">Todos</option>
                    @foreach ($statuses as $st)
                        <option value="{{ $st }}" {{ $statusFilter === $st ? 'selected' : '' }}>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-zinc-500 mb-1">Resultado</label>
                <select name="result" class="w-full rounded border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm focus:border-steam focus:outline-none">
                    <option value="">Todos</option>
                    <option value="win"      {{ $resultFilter === 'win'      ? 'selected' : '' }}>WIN</option>
                    <option value="loss"     {{ $resultFilter === 'loss'     ? 'selected' : '' }}>LOSS</option>
                    <option value="walkover" {{ $resultFilter === 'walkover' ? 'selected' : '' }}>Walkover</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-zinc-500 mb-1">Rival (nombre o Steam ID)</label>
                <input type="text" name="opponent" value="{{ $opponentQ }}" placeholder="Buscar rival..."
                       class="w-full rounded border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm focus:border-steam focus:outline-none">
            </div>
            <div class="sm:col-span-4 flex gap-2">
                <button type="submit" class="rounded border border-steam/60 bg-steam-dark px-3 py-1.5 text-sm text-steam hover:bg-steam hover:text-steam-dark transition-colors">
                    Filtrar
                </button>
                @if ($statusFilter || $resultFilter || $opponentQ)
                    <a href="{{ route('matches.index') }}" class="rounded px-3 py-1.5 text-sm text-zinc-400 hover:text-zinc-100">Limpiar filtros</a>
                @endif
            </div>
        </form>

        @if ($matches->isEmpty())
            <div class="rounded-lg border border-dashed border-zinc-800 bg-zinc-900/30 p-12 text-center">
                @if ($statusFilter || $resultFilter || $opponentQ)
                    <p class="text-zinc-500">Sin resultados para los filtros aplicados.</p>
                    <p class="mt-1 text-xs text-zinc-600">Probá <a href="{{ route('matches.index') }}" class="text-steam hover:underline">limpiar filtros</a>.</p>
                @else
                    <p class="text-zinc-500">No tenés matches todavía.</p>
                    <p class="mt-1 text-xs text-zinc-600">Hacé click en "Buscar partida" desde el dashboard para arrancar.</p>
                @endif
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-800">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-900/60">
                        <tr class="text-left text-xs uppercase tracking-wider text-zinc-500">
                            <th class="px-3 py-3">ID</th>
                            <th class="px-3 py-3">vs</th>
                            <th class="px-3 py-3 hidden lg:table-cell">Lobby</th>
                            <th class="px-3 py-3 hidden md:table-cell">Servidor</th>
                            <th class="px-3 py-3">Estado</th>
                            <th class="px-3 py-3">Resultado</th>
                            <th class="px-3 py-3 text-right hidden sm:table-cell">ΔRating</th>
                            <th class="px-3 py-3 hidden lg:table-cell">Replay</th>
                            <th class="px-3 py-3 hidden md:table-cell">Fecha</th>
                            <th class="px-3 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach ($matches as $m)
                            @php
                                $myId  = auth()->id();
                                $opp   = $m->host_user_id === $myId ? $m->opponent : $m->host;
                                $oppName = $opp?->persona_name ?? Str::limit($opp?->steam_id ?? '—', 12);
                                $oppLinkable = $opp && ! $opp->isBot();
                            @endphp
                            <tr class="hover:bg-zinc-900/40 transition-colors">
                                <td class="px-3 py-3 font-mono">
                                    <a href="{{ route('matches.show', $m->id) }}" class="text-steam hover:underline">#{{ $m->id }}</a>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($oppLinkable)
                                        <a href="{{ route('users.show', $opp->steam_id) }}" class="flex items-center gap-2 hover:text-steam transition-colors">
                                            @if ($opp->avatar_url)
                                                <img src="{{ $opp->avatar_url }}" alt="" class="h-6 w-6 rounded shrink-0">
                                            @else
                                                <span class="h-6 w-6 rounded bg-zinc-800 flex items-center justify-center text-xs text-zinc-500 shrink-0">{{ Str::upper(Str::substr($oppName, 0, 1)) }}</span>
                                            @endif
                                            <span class="truncate">{{ $oppName }}</span>
                                        </a>
                                    @else
                                        <span class="flex items-center gap-2 text-zinc-400">
                                            <span class="h-6 w-6 rounded bg-amber-950/40 border border-amber-900 flex items-center justify-center text-xs text-amber-400 shrink-0">B</span>
                                            <span class="truncate">Bot Dev</span>
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 font-mono text-xs hidden lg:table-cell">
                                    @if ($m->lobby_id)
                                        <a href="aoe2de://0/{{ $m->lobby_id }}" title="Abrir en AoE2 DE" class="text-steam hover:underline">{{ $m->lobby_id }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-3 font-mono text-xs hidden md:table-cell">{{ $m->config_json['server'] ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    <span class="badge badge-{{ $m->status }}">{{ $m->status }}</span>
                                    @if ($m->status === 'invalid' && ! empty($m->validation_errors))
                                        <ul class="mt-1.5 space-y-0.5 list-disc list-inside text-xs text-orange-300">
                                            @foreach ($m->validation_errors as $err)
                                                <li>{{ $err }}</li>
                                            @endforeach
                                        </ul>
                                    @elseif ($m->status === 'pending_validation')
                                        <div class="mt-1 text-xs text-zinc-500">esperando soporte de mgz</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($m->status === 'completed')
                                        @php $walkover = $m->replay_path === null; @endphp
                                        @if ($m->winner_user_id === auth()->id())
                                            <span class="font-semibold text-emerald-400">WIN</span>
                                            @if ($walkover)<span class="block text-xs text-zinc-500">por walkover</span>@endif
                                        @elseif ($m->winner_user_id !== null)
                                            <span class="font-semibold text-red-400">LOSS</span>
                                            @if ($walkover)<span class="block text-xs text-zinc-500">forfeit</span>@endif
                                        @else
                                            <span class="text-zinc-600">—</span>
                                        @endif
                                    @else
                                        <span class="text-zinc-600">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right font-mono text-xs hidden sm:table-cell whitespace-nowrap">
                                    @php
                                        $isHost = $m->host_user_id === auth()->id();
                                        $before = $isHost ? $m->host_rating_before : $m->opponent_rating_before;
                                        $change = $isHost ? $m->host_rating_change : $m->opponent_rating_change;
                                    @endphp
                                    @if ($change !== null)
                                        <span class="text-zinc-500">{{ round($before) }}</span>
                                        @if ($change > 0)
                                            <span class="text-emerald-400">+{{ round($change) }}</span>
                                        @elseif ($change < 0)
                                            <span class="text-red-400">{{ round($change) }}</span>
                                        @else
                                            <span class="text-zinc-500">±0</span>
                                        @endif
                                    @else
                                        <span class="text-zinc-600">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 font-mono text-xs hidden lg:table-cell">
                                    @if ($m->replay_filename)
                                        <span title="{{ $m->replay_filename }}">{{ Str::limit($m->replay_filename, 24) }}</span>
                                        <span class="text-zinc-500">({{ round($m->replay_size / 1024) }} KB)</span>
                                    @else
                                        <span class="text-zinc-600">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 font-mono text-xs text-zinc-500 hidden md:table-cell whitespace-nowrap">{{ $m->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-3 py-3">
                                    @if ($m->status === 'pending')
                                        <form method="POST" action="{{ route('matches.cancel', $m->id) }}" onsubmit="return confirm('¿Cancelar match #{{ $m->id }}?');">
                                            @csrf
                                            <button class="rounded border border-red-900 px-2 py-1 text-xs text-red-400 hover:bg-red-950 transition-colors" type="submit">Cancelar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $matches->links() }}</div>
        @endif
    </section>
</div>
@endsection
