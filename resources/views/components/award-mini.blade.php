@props(['award'])
@php
    $def = config("awards.{$award->award_code}");
    $title = $def['title'] ?? $award->award_code;
    $iconRel = $def['icon'] ?? null;
    $iconExists = $iconRel && file_exists(public_path("images/{$iconRel}"));

    // Una sola letra para version mini — primer caracter del code (puede repetirse,
    // se distinguen por el title del tooltip).
    $initial = Str::upper(Str::substr($award->award_code, 0, 1));

    $tierClass = match ($award->tier) {
        \App\Models\UserAward::TIER_BRONZE    => 'tier-bronze',
        \App\Models\UserAward::TIER_SILVER    => 'tier-silver',
        \App\Models\UserAward::TIER_GOLD      => 'tier-gold',
        \App\Models\UserAward::TIER_PLATINUM  => 'tier-platinum',
        \App\Models\UserAward::TIER_PRISMATIC => 'tier-prismatic',
        default => 'tier-bronze',
    };
@endphp

<span class="inline-flex h-5 w-5 shrink-0 rounded border items-center justify-center text-[10px] font-bold {{ $tierClass }}"
      title="{{ $title }} · {{ $award->tierName() }}{{ $award->season ? ' · ' . $award->season->name : '' }}">
    @if ($iconExists)
        <img src="{{ asset('images/' . $iconRel) }}" alt="" class="h-4 w-4">
    @else
        {{ $initial }}
    @endif
</span>
