@extends('layouts.app')

@section('title', $activeCategory ? "Leaderboard — {$activeCategory->name}" : 'Leaderboard — AoEHubs')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold flex items-center gap-2">
                Leaderboard
                @if ($activeCategory)
                    <span class="text-base px-2 py-0.5 rounded bg-accent-dark text-accent border border-accent/40 font-semibold">{{ $activeCategory->name }}</span>
                @endif
            </h1>
            <p class="mt-1 text-sm text-zinc-500">
                @if ($activeCategory)
                    Top 50 jugadores en mapas de la categoría {{ $activeCategory->name }}, por rating Glicko-2 específico.
                @else
                    Top 50 jugadores ordenados por rating Glicko-2 global.
                @endif
            </p>
        </div>

        @if ($categories->count() > 0)
            {{-- Filtro de categoria. Cambia ?category=slug. "Global" = sin filtro. --}}
            <form method="GET" action="{{ route('leaderboard') }}" class="shrink-0">
                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Categoría</label>
                <select name="category" onchange="this.form.submit()"
                        class="rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                    <option value="">Global</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->slug }}" {{ $activeCategory && $activeCategory->id === $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    <div class="overflow-x-auto rounded-lg border border-zinc-800">
        <table class="w-full text-sm">
            <thead class="bg-zinc-900/60">
                <tr class="text-left text-xs uppercase tracking-wider text-zinc-500">
                    <th class="px-4 py-3 w-10">#</th>
                    <th class="px-4 py-3">Jugador</th>
                    <th class="px-4 py-3 text-right">Rating</th>
                    @if ($activeCategory)
                        <th class="px-4 py-3 text-right hidden sm:table-cell" title="Matches jugados en esta categoría">Matches</th>
                    @else
                        <th class="px-4 py-3 text-right hidden sm:table-cell">Partidas</th>
                        <th class="px-4 py-3 text-right">W/L</th>
                        <th class="px-4 py-3 text-right hidden sm:table-cell">%</th>
                    @endif
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
                        $shownRating = $activeCategory ? $u->cat_rating : $u->rating;
                        $shownRd     = $activeCategory ? $u->cat_rd     : $u->rating_deviation;
                    @endphp
                    <tr class="{{ $isMe ? 'bg-accent-dark/40' : 'hover:bg-zinc-900/40' }} transition-colors">
                        <td class="px-4 py-3 font-mono {{ $rankColor }} {{ $rankWeight }}">{{ $rank }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('users.show', $u->steam_id) }}" class="flex items-center gap-2 hover:text-accent transition-colors">
                                @if ($u->avatar_url)
                                    <img src="{{ $u->avatar_url }}" alt="" class="h-6 w-6 rounded shrink-0">
                                @else
                                    <span class="h-6 w-6 rounded bg-zinc-800 flex items-center justify-center text-xs text-zinc-500 shrink-0">
                                        {{ Str::upper(Str::substr($u->persona_name ?? '?', 0, 1)) }}
                                    </span>
                                @endif
                                <span>{{ $u->persona_name ?? Str::limit($u->steam_id, 12) }}</span>
                                @if ($isMe)
                                    <span class="text-xs text-accent">(vos)</span>
                                @endif
                                @if ($u->awards->isNotEmpty())
                                    <span class="flex items-center gap-1 ml-1">
                                        @foreach ($u->awards->take(3) as $a)
                                            <x-award-mini :award="$a" />
                                        @endforeach
                                    </span>
                                @endif
                            </a>
                        </td>
                        <td class="px-4 py-3 text-right font-mono">
                            <span class="font-semibold">{{ round($shownRating) }}</span>
                            <span class="text-zinc-500 text-xs">±{{ round($shownRd) }}</span>
                        </td>
                        @if ($activeCategory)
                            <td class="px-4 py-3 text-right font-mono hidden sm:table-cell">{{ $u->cat_matches }}</td>
                        @else
                            <td class="px-4 py-3 text-right font-mono hidden sm:table-cell">{{ $u->played }}</td>
                            <td class="px-4 py-3 text-right font-mono whitespace-nowrap">
                                <span class="text-emerald-400">{{ $u->wins }}W</span>
                                <span class="text-zinc-600">—</span>
                                <span class="text-red-400">{{ $losses }}L</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono hidden sm:table-cell">{{ $winRate }}%</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $activeCategory ? 4 : 6 }}" class="px-4 py-12 text-center text-zinc-500">
                            @if ($activeCategory)
                                Todavía no hay jugadores con rating en {{ $activeCategory->name }}.
                                <p class="mt-2 text-xs">Las filas se crean cuando alguien gana un match en un mapa de esta categoría.</p>
                            @else
                                Todavía no hay jugadores en el ranking.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
