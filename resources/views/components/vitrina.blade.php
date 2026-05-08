@props([
    'user',
    'limit' => null,
    'showEmpty' => true,
    'season' => 'current',  // 'current', 'all_time', o instancia de Season
])
@php
    use App\Models\Season;

    if ($season === 'current') {
        $resolvedSeason = Season::current();
    } elseif ($season === 'all_time') {
        $resolvedSeason = null;
    } else {
        $resolvedSeason = $season;
    }

    // Si hay season filter: mostrar awards de esa season + globales (no atados).
    // Si all_time: mostrar todos.
    $awardsQuery = $user->awards();
    if ($resolvedSeason) {
        $awardsQuery->where(function ($q) use ($resolvedSeason) {
            $q->where('season_id', $resolvedSeason->id)->orWhereNull('season_id');
        });
    }

    $awards = $awardsQuery
        ->orderByDesc('awarded_at')
        ->get()
        ->groupBy(fn ($a) => $a->award_code . '::' . ($a->season_id ?? 'global'))
        ->map(fn ($group) => $group->sortByDesc('tier')->first())
        ->sortByDesc(fn ($a) => $a->tier * 1000 + $a->awarded_at->timestamp / 1e9)
        ->values();

    if ($limit !== null) {
        $awards = $awards->take($limit);
    }
@endphp

@if ($awards->isEmpty())
    @if ($showEmpty)
        <div class="rounded-xl border border-dashed border-zinc-800 bg-zinc-900/30 p-6 text-center">
            <p class="text-sm text-zinc-500">
                @if ($resolvedSeason)
                    Sin logros desbloqueados en {{ $resolvedSeason->name }} todavía.
                @else
                    Sin logros desbloqueados todavía.
                @endif
            </p>
        </div>
    @endif
@else
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        @foreach ($awards as $award)
            <x-award-badge :award="$award" />
        @endforeach
    </div>
@endif
