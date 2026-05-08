@extends('layouts.app')

@section('title', 'Map Draft #' . $match->id . ' — AoEHubs')

@section('content')
@php
    $rival = auth()->id() === $match->host_user_id ? $match->opponent : $match->host;
@endphp
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Map Draft <span class="text-zinc-500 font-mono text-lg">#{{ $match->id }}</span></h1>
        <p class="mt-1 text-sm text-zinc-500">Cada jugador banea un mapa por turno. El que queda al final es el de la partida.</p>
    </div>

    @if ($rival)
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto_1fr] gap-3 sm:gap-4 items-stretch">
            <x-player-card :user="auth()->user()" variant="self" />
            <div class="flex sm:flex-col items-center justify-center text-2xl sm:text-3xl font-black text-zinc-700 tracking-widest py-2 sm:py-0">VS</div>
            <x-player-card :user="$rival" variant="rival" />
        </div>
    @endif

    <div id="turn-banner" class="turn-banner">Cargando...</div>

    <div id="grid" class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));"></div>

    <div>
        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-500">Historial de bans</h2>
        <div id="bans-log" class="rounded-lg border border-zinc-800 bg-zinc-950 px-4 py-2 max-h-60 overflow-y-auto text-sm text-zinc-400">
            <div class="py-1 text-zinc-600">Sin bans todavía.</div>
        </div>
    </div>

    <div id="next-step" class="hidden rounded-lg border border-emerald-800 bg-emerald-950/40 p-5 animate-fade-in">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="text-xs uppercase tracking-wider text-emerald-400/70">Mapa elegido</div>
                <div class="mt-1 text-2xl font-bold text-emerald-300" id="final-map"></div>
            </div>
            <div class="text-sm text-zinc-300">
                Pasando al draft de civilizaciones en <span id="redirect-countdown" class="font-mono text-emerald-300 font-semibold">3</span>s...
            </div>
        </div>
        <div class="mt-3 text-xs">
            <a href="{{ route('drafts.civs.show', $match->id) }}" class="text-zinc-500 hover:text-accent transition-colors">o ir ahora →</a>
        </div>
    </div>

    {{-- Cancel match — anti-griefing penalty aplica --}}
    <div class="flex justify-end pt-2">
        <button type="button"
                onclick="document.getElementById('cancel-match-modal').showModal()"
                class="text-sm text-zinc-500 hover:text-red-400 transition-colors">
            Cancelar partida
        </button>
    </div>

    <x-confirm-modal id="cancel-match-modal"
                     title="¿Cancelar la partida?"
                     :action="route('matches.cancel', $match->id)"
                     confirmLabel="Sí, abandonar"
                     cancelLabel="Volver al draft"
                     :danger="true">
        <p>Vas a abandonar la partida.</p>
        <p class="text-accent">El tiempo de tu rival también es valioso.</p>
        <p class="text-xs text-zinc-500">Si lo hacés repetidamente vas a quedar bloqueado para buscar partida durante un tiempo.</p>
    </x-confirm-modal>
</div>
@endsection

@push('scripts')
@include('partials.translations-js')
<script>
    const matchId  = {{ $match->id }};
    const csrf     = document.querySelector('meta[name="csrf-token"]').content;
    const stateUrl = `/matches/${matchId}/draft/maps/state`;
    const banUrl   = `/matches/${matchId}/draft/maps/ban`;

    let pool = [];
    let currentState = null;
    let banning = false;

    async function loadState() {
        try {
            const r = await fetch(stateUrl, { headers: { 'Accept': 'application/json' }});
            if (!r.ok) return;
            const data = await r.json();
            // Si el match se abandono (rival canceló), redirigir al detalle.
            if (data.match_status === 'abandoned') {
                window.location.href = `/matches/${matchId}`;
                return;
            }
            currentState = data;
            pool = data.pool;
            render(data);
        } catch (e) { console.error(e); }
    }

    async function ban(mapName) {
        if (banning || !currentState?.your_turn) return;
        banning = true;
        try {
            const r = await fetch(banUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ map: mapName }),
            });
            const data = await r.json();
            if (r.ok) { currentState = data; render(data); }
            else      { alert(data.error || 'Error al banear'); }
        } catch (e) { alert('Error de red'); }
        finally     { banning = false; }
    }

    // Para evitar que el polling rompa el estado de hover/focus, construimos
    // los elementos UNA sola vez y solo mutamos clases/handlers en cada update.
    const MY_USER_ID = {{ auth()->id() }};
    const MY_NAME    = @json(auth()->user()->displayName());
    const RIVAL_NAME = @json($rival ? $rival->displayName() : 'Rival');
    let mapEls = {};
    let lastBanCount = 0;

    function buildGridOnce() {
        if (Object.keys(mapEls).length > 0) return;
        const grid = document.getElementById('grid');
        grid.innerHTML = '';
        for (const map of pool) {
            const el = document.createElement('div');
            el.className = 'map flex flex-col items-center gap-2';

            const img = document.createElement('img');
            img.src = `/images/maps/${map.toLowerCase().replace(/ /g, '_')}.png`;
            img.alt = '';
            // object-contain porque las miniaturas son rombos isometricos
            // — object-cover cortaria las puntas. Mas alto que ancho para
            // dar lugar al rombo extendido.
            img.className = 'h-24 w-32 object-contain';
            img.loading = 'lazy';
            img.onerror = () => img.remove();
            el.appendChild(img);

            const txt = document.createElement('span');
            txt.textContent = window.t(map);
            el.appendChild(txt);

            grid.appendChild(el);
            mapEls[map] = el;
        }
    }

    function render(state) {
        const banner = document.getElementById('turn-banner');
        const desiredClass = state.is_completed ? 'turn-banner completed'
                          : state.your_turn    ? 'turn-banner your-turn'
                          :                      'turn-banner their-turn';
        if (banner.className !== desiredClass) banner.className = desiredClass;

        const text = state.is_completed
            ? `Draft completado. Mapa elegido: ${window.t(state.final_map)}`
            : state.your_turn
                ? 'Tu turno: clickeá un mapa para banearlo.'
                : 'Turno del rival, esperá...';

        let textSpan  = banner.querySelector('.turn-text');
        let timerSpan = banner.querySelector('.timer');
        if (!textSpan) {
            banner.innerHTML = '';
            textSpan  = document.createElement('span'); textSpan.className  = 'turn-text';
            timerSpan = document.createElement('span'); timerSpan.className = 'timer'; timerSpan.id = 'timer'; timerSpan.textContent = '--';
            banner.appendChild(textSpan);
            if (!state.is_completed) banner.appendChild(timerSpan);
        }
        textSpan.textContent = text;
        if (state.is_completed && timerSpan) timerSpan.remove();

        if (state.is_completed) {
            const ns = document.getElementById('next-step');
            ns.classList.remove('hidden');
            document.getElementById('final-map').textContent = window.t(state.final_map);
            startRedirectCountdown();
        }
        updateTimer();

        buildGridOnce();
        const banned = new Set(state.bans.map(b => b.map));
        for (const [name, el] of Object.entries(mapEls)) {
            const isFinal      = state.final_map === name;
            const isBanned     = banned.has(name);
            const isSelectable = state.your_turn && !isBanned && !isFinal;

            el.classList.toggle('final',      isFinal);
            el.classList.toggle('banned',     isBanned && !isFinal);
            el.classList.toggle('selectable', isSelectable);
            el.classList.toggle('disabled',   !state.your_turn && !isBanned && !isFinal);

            el.onclick = isSelectable ? () => ban(name) : null;
        }

        const log = document.getElementById('bans-log');
        if (state.bans.length === 0) {
            if (log.childElementCount === 0 || lastBanCount > 0) {
                log.innerHTML = '<div class="py-1 text-zinc-600">Sin bans todavía.</div>';
            }
        } else {
            if (lastBanCount === 0) log.innerHTML = '';
            for (let i = lastBanCount; i < state.bans.length; i++) {
                const b = state.bans[i];
                const who = b.user_id === MY_USER_ID ? MY_NAME : RIVAL_NAME;
                const row = document.createElement('div');
                row.className = 'py-1.5 border-b border-zinc-900 last:border-0 animate-fade-in';
                row.innerHTML = `${i + 1}. ${who} baneó <span class="text-red-400 line-through font-medium">${window.t(b.map)}</span>`;
                log.appendChild(row);
            }
        }
        lastBanCount = state.bans.length;
    }

    function updateTimer() {
        const timerEl = document.getElementById('timer');
        if (!timerEl || !currentState?.turn_deadline) return;
        const remaining = Math.max(0, Math.ceil((new Date(currentState.turn_deadline) - new Date()) / 1000));
        timerEl.textContent = `${remaining}s`;
        timerEl.classList.toggle('warning', remaining <= 10 && remaining > 5);
        timerEl.classList.toggle('danger',  remaining <= 5);
    }

    // Auto-redirect al civ draft cuando termina el map draft. Pensado para
    // que ambos jugadores lleguen al mismo lugar sin depender de que uno
    // clickee. Idempotente — si la pagina ya esta corriendo el countdown, no
    // se reinicia.
    let redirectStarted = false;
    function startRedirectCountdown() {
        if (redirectStarted) return;
        redirectStarted = true;

        const civsUrl = `/matches/${matchId}/draft/civs`;
        const countdownEl = document.getElementById('redirect-countdown');
        let secondsLeft = 3;

        const tick = () => {
            secondsLeft--;
            if (countdownEl) countdownEl.textContent = String(Math.max(0, secondsLeft));
            if (secondsLeft <= 0) {
                clearInterval(intervalId);
                window.location.href = civsUrl;
            }
        };
        const intervalId = setInterval(tick, 1000);
    }

    loadState();
    setInterval(loadState,  1000);
    setInterval(updateTimer, 200);
</script>
@endpush
