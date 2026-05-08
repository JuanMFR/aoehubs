@props(['award'])
@php
    $def = config("awards.{$award->award_code}");
    $title = $def['title'] ?? $award->award_code;
    $description = $def['description'] ?? '';
    $iconRel = $def['icon'] ?? null;
    $iconExists = $iconRel && file_exists(public_path("images/{$iconRel}"));

    // Iniciales unicas a partir del code: para multi-word toma primera letra
    // de cada palabra, para single word toma las dos primeras letras.
    $parts = explode('_', $award->award_code);
    $initials = count($parts) >= 2
        ? Str::upper(Str::substr($parts[0], 0, 1) . Str::substr($parts[1], 0, 1))
        : Str::upper(Str::substr($parts[0], 0, 2));

    $tierClass = match ($award->tier) {
        \App\Models\UserAward::TIER_BRONZE    => 'tier-bronze',
        \App\Models\UserAward::TIER_SILVER    => 'tier-silver',
        \App\Models\UserAward::TIER_GOLD      => 'tier-gold',
        \App\Models\UserAward::TIER_PLATINUM  => 'tier-platinum',
        \App\Models\UserAward::TIER_PRISMATIC => 'tier-prismatic',
        default => 'tier-bronze',
    };
@endphp

<div class="rounded-lg border bg-zinc-900/40 p-3 transition-all hover:bg-zinc-900/70 {{ $tierClass }}"
     title="{{ $description }}{{ $award->season ? ' · ' . $award->season->name : '' }}">
    <div class="flex items-start gap-3">
        @if ($iconExists)
            <img src="{{ asset('images/' . $iconRel) }}" alt=""
                 class="h-10 w-10 shrink-0">
        @else
            {{-- Placeholder hasta que exista el SVG real en public/images/awards/ --}}
            <div class="h-10 w-10 shrink-0 rounded border-2 border-current/40 flex items-center justify-center font-bold text-base bg-current/10">
                {{ $initials }}
            </div>
        @endif
        <div class="min-w-0 flex-1">
            <div class="text-sm font-semibold text-zinc-100 truncate">{{ $title }}</div>
            <div class="text-xs uppercase tracking-wider mt-0.5">{{ $award->tierName() }}</div>
        </div>
    </div>
    @if ($award->season)
        <div class="mt-2 text-xs text-zinc-500 truncate">{{ $award->season->name }}</div>
    @endif
</div>
