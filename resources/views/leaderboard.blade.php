@extends('layouts.app')

@section('title', 'Leaderboard — AoE2 Rank')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Leaderboard</h1>
        <p class="mt-1 text-sm text-zinc-500">Top 50 jugadores ordenados por rating Glicko-2.</p>
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-800">
        <table class="w-full text-sm">
            <thead class="bg-zinc-900/60">
                <tr class="text-left text-xs uppercase tracking-wider text-zinc-500">
                    <th class="px-4 py-3 w-10">#</th>
                    <th class="px-4 py-3">Jugador</th>
                    <th class="px-4 py-3 text-right">Rating</th>
                    <th class="px-4 py-3 text-right hidden sm:table-cell">Partidas</th>
                    <th class="px-4 py-3 text-right">W/L</th>
                    <th class="px-4 py-3 text-right hidden sm:table-cell">%</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($users as $i => $u)
                    @php
                        $isMe   = auth()->check() && auth()->id() === $u->id;
                        $rank   = $i + 1;
                        $losses = $u->played - $u->wins;
                        $winRate = $u->played > 0 ? round($u->wins / $u->played * 100) : 0;
                        $rankColor = $rank === 1 ? 'text-amber-400'
                                    : ($rank === 2 ? 'text-zinc-300'
                                    : ($rank === 3 ? 'text-orange-400' : 'text-zinc-500'));
                        $rankWeight = $rank <= 3 ? 'font-bold' : 'font-medium';
                    @endphp
                    <tr class="{{ $isMe ? 'bg-steam-dark/40' : 'hover:bg-zinc-900/40' }} transition-colors">
                        <td class="px-4 py-3 font-mono {{ $rankColor }} {{ $rankWeight }}">{{ $rank }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('users.show', $u->steam_id) }}" class="flex items-center gap-2 hover:text-steam transition-colors">
                                @if ($u->avatar_url)
                                    <img src="{{ $u->avatar_url }}" alt="" class="h-6 w-6 rounded shrink-0">
                                @else
                                    <span class="h-6 w-6 rounded bg-zinc-800 flex items-center justify-center text-xs text-zinc-500 shrink-0">
                                        {{ Str::upper(Str::substr($u->persona_name ?? '?', 0, 1)) }}
                                    </span>
                                @endif
                                <span>{{ $u->persona_name ?? Str::limit($u->steam_id, 12) }}</span>
                                @if ($isMe)
                                    <span class="text-xs text-steam">(vos)</span>
                                @endif
                            </a>
                        </td>
                        <td class="px-4 py-3 text-right font-mono">
                            <span class="font-semibold">{{ round($u->rating) }}</span>
                            <span class="text-zinc-500 text-xs">±{{ round($u->rating_deviation) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono hidden sm:table-cell">{{ $u->played }}</td>
                        <td class="px-4 py-3 text-right font-mono whitespace-nowrap">
                            <span class="text-emerald-400">{{ $u->wins }}W</span>
                            <span class="text-zinc-600">—</span>
                            <span class="text-red-400">{{ $losses }}L</span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono hidden sm:table-cell">{{ $winRate }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-zinc-500">
                            Todavía no hay jugadores en el ranking.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
