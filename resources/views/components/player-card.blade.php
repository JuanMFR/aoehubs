@props([
    'user',
    'variant' => 'rival',     // 'self' (vos, gold) o 'rival' (rojo)
    'compact' => false,       // version chica para dashboard preview
    'season' => 'current',    // 'current' (default), 'all_time', o instancia de Season
])
@php
    use App\Models\GameMatch;
    use App\Models\Season;
    use App\Models\SeasonStat;
    use App\Models\UserAward;

    // Resolver el season prop: 'current' → la activa, 'all_time' → null (sin filtro),
    // Season instance → tal cual.
    if ($season === 'current') {
        $resolvedSeason = Season::current();
    } elseif ($season === 'all_time') {
        $resolvedSeason = null;
    } else {
        $resolvedSeason = $season; // asumimos Season instance
    }
    $isAllTime = $resolvedSeason === null;

    // Stats — un solo query (helper centralizado en User::winLossStats).
    $stats = $user->winLossStats($resolvedSeason);
    $totalMatches = $stats['played'];
    $wins         = $stats['wins'];
    $losses       = $stats['losses'];
    $winRate      = $stats['win_rate'];

    // Awards a mostrar:
    //   - Per-season: awards de esa season (incluye awards globales que sirven siempre)
    //   - All-time: todos
    $awardsQuery = $user->awards()
        ->whereIn('tier', [UserAward::TIER_PLATINUM, UserAward::TIER_PRISMATIC]);
    if ($resolvedSeason) {
        $awardsQuery->where(function ($q) use ($resolvedSeason) {
            $q->where('season_id', $resolvedSeason->id)->orWhereNull('season_id');
        });
    }
    $topAwards = $awardsQuery
        ->orderByDesc('tier')
        ->orderByDesc('awarded_at')
        ->get()
        ->groupBy(fn ($a) => $a->award_code . '::' . ($a->season_id ?? 'global'))
        ->map(fn ($g) => $g->sortByDesc('tier')->first())
        ->take($compact ? 4 : 6);

    // Mejor final_rank — si hay season filter, el de esa season; si all_time, el mejor global.
    $bestStatQuery = SeasonStat::where('user_id', $user->id)->whereNotNull('final_rank');
    if ($resolvedSeason) $bestStatQuery->where('season_id', $resolvedSeason->id);
    $bestStat = $bestStatQuery->orderBy('final_rank')->with('season')->first();

    // Rating mostrado: para current/all_time mostramos el rating LIVE del user
    // (que siempre refleja la season activa). Para una season pasada cerrada,
    // mostramos el final_rating snapshotteado en season_stats.
    $isPastSeason = $resolvedSeason && $resolvedSeason->isClosed();
    $displayRating = $isPastSeason && $bestStat ? $bestStat->final_rating : $user->rating;
    $displayRd     = $isPastSeason && $bestStat ? $bestStat->final_rd     : $user->rating_deviation;

    // Variant styling — self=accent (dorado), rival=rojo.
    $isSelf = $variant === 'self';
    $borderClass = $isSelf ? 'border-accent/40'    : 'border-red-900/40';
    $bgClass     = $isSelf ? 'from-accent-dark/20' : 'from-red-950/20';
    $chipClass   = $isSelf ? 'bg-accent-dark text-accent border-accent/40'
                           : 'bg-red-950 text-red-300 border-red-800/60';
    $chipLabel   = $isSelf ? 'Vos' : 'Rival';

    $personaName = $user->displayName();
@endphp

<div class="rounded-xl border {{ $borderClass }} bg-gradient-to-br {{ $bgClass }} to-zinc-900/50 p-4 sm:p-5 h-full flex flex-col">
    <div class="flex {{ $compact ? 'flex-row' : 'flex-col sm:flex-row' }} gap-4 flex-1">
        @if ($user->avatar_url)
            <img src="{{ $user->avatar_url }}" alt=""
                 class="{{ $compact ? 'h-14 w-14' : 'h-16 w-16 sm:h-20 sm:w-20' }} rounded-lg border border-zinc-700 shrink-0">
        @else
            <div class="{{ $compact ? 'h-14 w-14 text-xl' : 'h-16 w-16 sm:h-20 sm:w-20 text-2xl' }} rounded-lg bg-zinc-800 border border-zinc-700 flex items-center justify-center text-zinc-500 shrink-0">
                {{ Str::upper(Str::substr($personaName, 0, 1)) }}
            </div>
        @endif

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('users.show', $user->steam_id) }}"
                   class="text-base sm:text-lg font-bold truncate hover:text-accent transition-colors"
                   @if(!$isSelf) target="_blank" rel="noopener" @endif>
                    {{ $personaName }}
                </a>
                <span class="text-[10px] px-1.5 py-0.5 rounded border uppercase tracking-wider font-semibold {{ $chipClass }}">
                    {{ $chipLabel }}
                </span>
                @if ($user->isBot())
                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-400 uppercase tracking-wider">bot</span>
                @endif
            </div>

            @if ($resolvedSeason)
                <div class="text-[10px] text-zinc-500 uppercase tracking-wider mt-0.5">
                    {{ $resolvedSeason->name }}{{ $isPastSeason ? ' · cerrada' : '' }}
                </div>
            @else
                <div class="text-[10px] text-zinc-500 uppercase tracking-wider mt-0.5">All-time</div>
            @endif

            <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm">
                <span>
                    <span class="text-zinc-500">Rating</span>
                    <span class="font-mono font-semibold ml-1">{{ round($displayRating) }}</span>
                    @if (!$compact)
                        <span class="text-zinc-600 font-mono text-xs">±{{ round($displayRd) }}</span>
                    @endif
                </span>
                @if ($totalMatches > 0)
                    <span>
                        <span class="text-zinc-500">W/L</span>
                        <span class="font-mono ml-1">
                            <span class="text-emerald-400 font-semibold">{{ $wins }}</span><span class="text-zinc-600">—</span><span class="text-red-400 font-semibold">{{ $losses }}</span>
                        </span>
                    </span>
                    <span>
                        <span class="text-zinc-500">WR</span>
                        <span class="font-mono ml-1">{{ $winRate }}%</span>
                    </span>
                @endif
                @if ($bestStat && $bestStat->season && !$compact)
                    <span>
                        <span class="text-zinc-500">Best</span>
                        <span class="font-mono text-accent ml-1">#{{ $bestStat->final_rank }}</span>
                        @if ($isAllTime)
                            <span class="text-zinc-500 text-xs">· {{ $bestStat->season->name }}</span>
                        @endif
                    </span>
                @endif
            </div>

            @if ($topAwards->count() > 0)
                <div class="mt-3 flex items-center flex-wrap gap-1.5">
                    @if (!$compact)
                        <span class="text-xs text-zinc-500 mr-1">Logros:</span>
                    @endif
                    @foreach ($topAwards as $a)
                        <x-award-mini :award="$a" />
                    @endforeach
                </div>
            @elseif ($totalMatches === 0 && !$user->isBot() && !$compact && !$isSelf)
                <div class="mt-3 text-xs text-zinc-600 italic">Sin partidas en {{ $resolvedSeason?->name ?? 'el sistema' }} todavía.</div>
            @endif
        </div>
    </div>
</div>
