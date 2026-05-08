@extends('layouts.app')

@section('title', 'Dashboard — AoE2 Rank')

@section('content')
@php
    $u = auth()->user();
    $wins   = \App\Models\GameMatch::where('winner_user_id', $u->id)->where('status', 'completed')->count();
    $losses = \App\Models\GameMatch::where(function ($q) use ($u) {
            $q->where('host_user_id', $u->id)->orWhere('opponent_user_id', $u->id);
        })
        ->where('status', 'completed')
        ->where('winner_user_id', '!=', $u->id)
        ->whereNotNull('winner_user_id')
        ->count();
    $totalMatches = $wins + $losses;
    $winRate = $totalMatches > 0 ? round(($wins / $totalMatches) * 100) : 0;
    $queueEntry = \App\Models\QueueEntry::where('user_id', $u->id)->where('is_bot', false)->first();
    $inCooldown = $u->isInCooldown();
    $cooldownLeft = $inCooldown ? \App\Services\CooldownService::formatSeconds(\App\Services\CooldownService::remainingSeconds($u)) : null;
@endphp

<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <p class="mt-1 text-sm text-zinc-500">Bienvenido, {{ $u->persona_name ?? 'jugador' }}.</p>
    </div>

    {{-- Matchmaking CTA / queue state --}}
    <section>
        @if ($inCooldown)
            <div class="rounded-xl border border-red-900/60 bg-red-950/20 p-6 sm:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-red-300">⏱ En cooldown anti-griefing</h2>
                        <p class="mt-1 text-sm text-zinc-400">
                            Volvés a poder buscar partida en <span class="font-mono text-red-300 font-semibold">{{ $cooldownLeft }}</span>.
                            Causa: abandonaste lobbies o partidas mid-game recientemente.
                        </p>
                    </div>
                </div>
            </div>
        @elseif ($queueEntry)
            <div class="rounded-xl border border-steam/30 bg-gradient-to-r from-steam-dark/40 to-zinc-900/50 p-6 sm:p-8 transition-all">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold flex items-center gap-2">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span>
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                            </span>
                            Buscando rival...
                        </h2>
                        <p class="mt-1 text-sm text-zinc-400">
                            En cola desde hace <span id="queue-timer" class="font-mono text-zinc-200">--</span>
                        </p>
                    </div>
                    <form method="POST" action="{{ route('queue.leave') }}" class="shrink-0" data-loading-form>
                        @csrf
                        <button type="submit"
                                class="w-full sm:w-auto rounded-lg border border-red-900 bg-red-950/30 px-6 py-3 font-semibold text-red-300 hover:bg-red-900/40 transition-colors disabled:opacity-60 disabled:cursor-wait"
                                data-loading-text="Cancelando...">
                            Cancelar búsqueda
                        </button>
                    </form>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/50 p-6 sm:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Buscar partida ranked</h2>
                        <p class="mt-1 text-sm text-zinc-400">
                            Hay un Bot Dev permanentemente en cola — vas a quedar emparejado al instante para testing.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('queue.join') }}" class="shrink-0" data-loading-form>
                        @csrf
                        <button type="submit"
                                class="w-full sm:w-auto rounded-lg bg-steam px-6 py-3 font-semibold text-steam-dark hover:bg-steam-hover transition-colors disabled:opacity-60 disabled:cursor-wait"
                                data-loading-text="Buscando...">
                            Buscar partida
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </section>

    {{-- Stats grid --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Tu cuenta</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
                <div class="text-xs text-zinc-500 uppercase tracking-wider">Rating</div>
                <div class="mt-1 font-mono text-2xl font-semibold">{{ round($u->rating) }}</div>
                <div class="text-xs text-zinc-500 font-mono">± {{ round($u->rating_deviation) }} RD</div>
            </div>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
                <div class="text-xs text-zinc-500 uppercase tracking-wider">Record</div>
                <div class="mt-1 font-mono text-2xl font-semibold">
                    <span class="text-emerald-400">{{ $wins }}</span><span class="text-zinc-600">—</span><span class="text-red-400">{{ $losses }}</span>
                </div>
                <div class="text-xs text-zinc-500">{{ $totalMatches }} partidas</div>
            </div>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
                <div class="text-xs text-zinc-500 uppercase tracking-wider">Win rate</div>
                <div class="mt-1 font-mono text-2xl font-semibold">{{ $winRate }}%</div>
                <div class="text-xs text-zinc-500">vs partidas completas</div>
            </div>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
                <div class="text-xs text-zinc-500 uppercase tracking-wider">Miembro desde</div>
                <div class="mt-1 font-mono text-base font-semibold">{{ $u->created_at->format('Y-m-d') }}</div>
                <div class="text-xs text-zinc-500 font-mono">{{ $u->steam_id }}</div>
            </div>
        </div>
    </section>

    {{-- Companion token --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Companion</h2>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4 sm:p-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="font-medium">Token de acceso</div>
                    <p class="mt-0.5 text-sm text-zinc-400">
                        Pegalo en el companion la primera vez. Generar uno nuevo invalida el anterior.
                    </p>
                </div>
                <form method="POST" action="{{ route('companion.token') }}" class="shrink-0" data-loading-form>
                    @csrf
                    <button type="submit"
                            class="w-full sm:w-auto rounded border border-steam bg-steam-dark px-4 py-2 text-sm font-semibold text-steam hover:bg-steam hover:text-steam-dark transition-colors disabled:opacity-60 disabled:cursor-wait"
                            data-loading-text="Generando...">
                        Generar nuevo token
                    </button>
                </form>
            </div>

            @if (session('companion_token'))
                <div class="mt-4 rounded-lg border border-steam bg-steam-dark/40 p-4">
                    <div class="text-sm font-semibold mb-2">Token generado:</div>
                    <code class="block break-all rounded bg-black p-3 font-mono text-sm text-steam select-all">{{ session('companion_token') }}</code>
                    <p class="mt-2 text-xs text-amber-400">
                        ⚠ Guardalo ahora — por seguridad no se vuelve a mostrar. Si lo perdés, generá uno nuevo.
                    </p>
                </div>
            @endif
        </div>
    </section>
</div>
@endsection

@if ($queueEntry)
@push('scripts')
<script>
    // Timer del estado "en cola" + polling para detectar cuando matcheamos.
    const joinedAt   = new Date('{{ $queueEntry->joined_at->toIso8601String() }}');
    const statusUrl  = '{{ route('queue.status') }}';
    const timerEl    = document.getElementById('queue-timer');

    function fmt(secs) {
        const m = Math.floor(secs / 60);
        const s = String(secs % 60).padStart(2, '0');
        return m > 0 ? `${m}:${s}` : `${s}s`;
    }

    function updateTimer() {
        const elapsed = Math.max(0, Math.floor((Date.now() - joinedAt.getTime()) / 1000));
        if (timerEl) timerEl.textContent = fmt(elapsed);
    }

    async function pollStatus() {
        try {
            const r = await fetch(statusUrl, { headers: { 'Accept': 'application/json' }});
            if (!r.ok) return;
            const data = await r.json();

            // Match encontrada → redirect al draft (o a /matches si ya está en pending/in_progress)
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
                return;
            }
            // Salimos de la queue por otro lado (otra pestaña, cancel manual, etc.) → refresh
            if (!data.in_queue) {
                window.location.reload();
            }
        } catch (e) { /* network blip, próximo intento */ }
    }

    updateTimer();
    setInterval(updateTimer, 1000);
    setInterval(pollStatus, 2000);
</script>
@endpush
@endif
