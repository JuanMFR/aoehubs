@extends('layouts.app')

@section('title', 'Civ Draft #' . $match->id . ' — AoE2 Rank')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Civ Draft <span class="text-zinc-500 font-mono text-lg">#{{ $match->id }}</span></h1>
        <p id="phase-desc" class="mt-1 text-sm text-zinc-500">Cargando...</p>
    </div>

    {{-- Mapa elegido en el draft anterior. Se muestra arriba para tenerlo
         siempre presente al elegir civs. Cuando agreguemos miniaturas, va
         la imagen del mapa acá tambien. --}}
    @if ($match->mapDraft && $match->mapDraft->final_map)
        <div class="rounded-lg border border-emerald-800/40 bg-gradient-to-r from-emerald-950/30 to-zinc-900/50 px-4 py-3 flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-emerald-950 text-emerald-300 font-bold">
                {{ Str::upper(Str::substr($match->mapDraft->final_map, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xs uppercase tracking-wider text-zinc-500">Mapa de la partida</div>
                <div class="font-semibold text-emerald-300 truncate">{{ $match->mapDraft->final_map }}</div>
            </div>
        </div>
    @endif

    <div id="banner" class="banner waiting">
        <span class="banner-text">Cargando...</span>
        <span class="timer" id="timer">--</span>
    </div>

    <div id="grid" class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));"></div>

    <div class="flex flex-wrap items-center gap-4">
        <button id="confirm-btn" class="btn-confirm" disabled>Confirmar</button>
        <span id="action-hint" class="text-sm text-zinc-500"></span>
    </div>

    <div id="summary" class="hidden rounded-lg border border-zinc-800 bg-zinc-950 p-4 text-sm text-zinc-400 space-y-1"></div>

    {{-- Resultado final del civ draft. Se muestra cuando phase === 'completed'.
         Dos cards side-by-side: la tuya (verde) y la del rival (roja). El
         cuadradito a la izquierda es el placeholder del icono de la civ —
         cuando agreguemos miniaturas, va una <img> ahi. --}}
    <div id="next-step" class="hidden">
        <div class="grid grid-cols-2 gap-3 sm:gap-4 animate-fade-in">
            {{-- Tu civ --}}
            <div class="rounded-xl border-2 border-emerald-700/60 bg-emerald-950/30 p-4 sm:p-5">
                <div class="text-xs uppercase tracking-wider text-emerald-400/80 font-semibold mb-3">Tu civilización</div>
                <div class="flex items-center gap-3 sm:gap-4">
                    <div class="h-14 w-14 sm:h-16 sm:w-16 shrink-0 rounded-lg bg-emerald-950 border border-emerald-800 flex items-center justify-center text-emerald-300 font-bold text-xl sm:text-2xl"
                         id="my-civ-icon">
                        {{-- Placeholder: cuando haya miniatura real, reemplazar por <img> --}}
                        ?
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xl sm:text-2xl font-bold text-emerald-300 truncate" id="my-final-civ">—</div>
                    </div>
                </div>
            </div>

            {{-- Civ del rival --}}
            <div class="rounded-xl border-2 border-red-800/60 bg-red-950/30 p-4 sm:p-5">
                <div class="text-xs uppercase tracking-wider text-red-400/80 font-semibold mb-3">Civilización del rival</div>
                <div class="flex items-center gap-3 sm:gap-4">
                    <div class="h-14 w-14 sm:h-16 sm:w-16 shrink-0 rounded-lg bg-red-950 border border-red-900 flex items-center justify-center text-red-300 font-bold text-xl sm:text-2xl"
                         id="opp-civ-icon">
                        ?
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xl sm:text-2xl font-bold text-red-300 truncate" id="opp-final-civ">—</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-lg border border-zinc-800 bg-zinc-900/50 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm">
            <div class="text-zinc-400">
                Match pasó a <code class="font-mono text-zinc-300">pending</code>. Tu companion la toma automáticamente.
            </div>
            <div class="text-zinc-400">
                Te llevamos al detalle en <span id="redirect-countdown" class="font-mono text-zinc-200 font-semibold">3</span>s
                <a href="{{ route('matches.show', $match->id) }}" class="ml-2 text-zinc-500 hover:text-steam transition-colors text-xs">o ir ahora →</a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const matchId  = {{ $match->id }};
    const csrf     = document.querySelector('meta[name="csrf-token"]').content;
    const stateUrl = `/matches/${matchId}/draft/civs/state`;

    let pool = [];
    let currentState = null;
    let pendingSelection = [];
    let busy = false;
    let civEls = {};

    async function loadState() {
        try {
            const r = await fetch(stateUrl, { headers: { 'Accept': 'application/json' }});
            if (!r.ok) return;
            const data = await r.json();
            currentState = data;
            pool = data.pool;
            render(data);
        } catch (e) { console.error(e); }
    }

    async function submit(endpoint, body, errorMsg) {
        if (busy) return;
        busy = true;
        try {
            const r = await fetch(`/matches/${matchId}/draft/civs/${endpoint}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(body),
            });
            const data = await r.json();
            if (r.ok) { currentState = data; pendingSelection = []; render(data); }
            else      { alert(data.error || errorMsg); }
        } catch (e) { alert('Error de red'); }
        finally     { busy = false; }
    }

    function buildGridOnce() {
        if (Object.keys(civEls).length > 0 && Object.keys(civEls).length === pool.length) return;
        const grid = document.getElementById('grid');
        grid.innerHTML = '';
        civEls = {};
        for (const civ of pool) {
            const el = document.createElement('div');
            el.className = 'civ';
            el.textContent = civ;
            grid.appendChild(el);
            civEls[civ] = el;
        }
    }

    function render(state) {
        const banner    = document.getElementById('banner');
        const bannerTxt = banner.querySelector('.banner-text');
        const phaseDesc = document.getElementById('phase-desc');
        const confirmBtn= document.getElementById('confirm-btn');
        const actionHint= document.getElementById('action-hint');

        const phaseTexts = {
            picking:    'Fase 1/3: Cada uno elige 4 civilizaciones (en privado).',
            banning:    'Fase 2/3: Baneás 2 de los 4 picks del rival.',
            finalizing: 'Fase 3/3: Elegís 1 de tus 2 picks que sobrevivieron.',
            completed:  'Draft completado.',
        };
        phaseDesc.textContent = phaseTexts[state.phase] || '';

        buildGridOnce();
        for (const el of Object.values(civEls)) {
            el.classList.remove('selectable', 'selected', 'banned', 'disabled', 'final');
            el.onclick = null;
            el.style.display = 'none';
        }

        let myDone   = false;
        let oppDone  = false;
        let needCount= 0;

        if (state.phase === 'picking') {
            myDone   = state.my_picked;
            oppDone  = state.opp_picked;
            needCount= 4;

            for (const civ of pool) {
                const el = civEls[civ];
                el.style.display = '';
                if (myDone) {
                    if (state.my_picks.includes(civ)) el.classList.add('selected');
                    else                              el.classList.add('disabled');
                } else {
                    const isSelected = pendingSelection.includes(civ);
                    if (isSelected) el.classList.add('selected');
                    else            el.classList.add('selectable');
                    el.onclick = () => togglePending(civ);
                }
            }
        } else if (state.phase === 'banning') {
            myDone   = state.my_banned;
            oppDone  = state.opp_banned;
            needCount= 2;

            const oppPicks = state.opp_picks || [];
            for (const civ of oppPicks) {
                const el = civEls[civ];
                el.style.display = '';
                if (myDone) {
                    if (state.my_bans.includes(civ)) el.classList.add('banned');
                    else                              el.classList.add('disabled');
                } else {
                    const isSelected = pendingSelection.includes(civ);
                    if (isSelected) el.classList.add('selected');
                    else            el.classList.add('selectable');
                    el.onclick = () => togglePending(civ);
                }
            }
        } else if (state.phase === 'finalizing') {
            myDone   = state.my_finalized;
            oppDone  = state.opp_finalized;
            needCount= 1;

            const remaining = state.my_remaining || [];
            for (const civ of remaining) {
                const el = civEls[civ];
                el.style.display = '';
                if (myDone) {
                    if (state.my_final === civ) el.classList.add('final');
                    else                         el.classList.add('disabled');
                } else {
                    const isSelected = pendingSelection.includes(civ);
                    if (isSelected) el.classList.add('selected');
                    else            el.classList.add('selectable');
                    el.onclick = () => togglePending(civ, true);
                }
            }
        }

        if (state.phase === 'completed') {
            banner.className = 'banner completed';
            bannerTxt.textContent = 'Draft completado.';
            confirmBtn.disabled = true;
            actionHint.textContent = '';
            const t = document.getElementById('timer'); if (t) t.style.display = 'none';

            // Mostrar las cards de resultado (tu civ / civ del rival)
            const ns = document.getElementById('next-step');
            ns.classList.remove('hidden');
            document.getElementById('my-final-civ').textContent  = state.my_final;
            document.getElementById('opp-final-civ').textContent = state.opp_final;
            // Placeholder del icono = primera letra de la civ. Cuando metamos
            // miniaturas, reemplazar el textContent por una <img>.
            document.getElementById('my-civ-icon').textContent  = state.my_final  ? state.my_final[0].toUpperCase() : '?';
            document.getElementById('opp-civ-icon').textContent = state.opp_final ? state.opp_final[0].toUpperCase() : '?';

            startRedirectCountdown();
        } else if (myDone && !oppDone) {
            banner.className = 'banner waiting';
            bannerTxt.textContent = 'Esperando al rival...';
            confirmBtn.disabled = true;
            actionHint.textContent = '';
        } else if (myDone && oppDone) {
            banner.className = 'banner waiting';
            bannerTxt.textContent = 'Avanzando...';
            confirmBtn.disabled = true;
        } else {
            banner.className = 'banner action';
            const phaseAction = {
                picking:    `Elegí ${needCount} civilizaciones.`,
                banning:    `Baneá ${needCount} civilizaciones de las del rival.`,
                finalizing: `Elegí ${needCount} de las civilizaciones que te quedaron.`,
            };
            bannerTxt.textContent = phaseAction[state.phase];
            actionHint.textContent = `Seleccionadas: ${pendingSelection.length}/${needCount}`;
            confirmBtn.disabled = pendingSelection.length !== needCount;
            confirmBtn.onclick = () => onConfirm(state);
        }

        updateTimer();
        renderSummary(state);
    }

    function togglePending(civ, single = false) {
        if (single) {
            pendingSelection = [civ];
        } else {
            const idx = pendingSelection.indexOf(civ);
            if (idx >= 0) {
                pendingSelection.splice(idx, 1);
            } else {
                const max = currentState.phase === 'picking' ? 4
                          : currentState.phase === 'banning' ? 2
                          : 1;
                if (pendingSelection.length >= max) return;
                pendingSelection.push(civ);
            }
        }
        render(currentState);
    }

    function onConfirm(state) {
        if (state.phase === 'picking')         submit('picks', { picks: pendingSelection }, 'Error al enviar picks');
        else if (state.phase === 'banning')    submit('bans', { bans: pendingSelection }, 'Error al enviar bans');
        else if (state.phase === 'finalizing') submit('final', { civ: pendingSelection[0] }, 'Error al enviar civ final');
    }

    function renderSummary(state) {
        const summaryEl = document.getElementById('summary');
        const parts = [];

        const tagFor = (c, banned, isFinal) => {
            const cls = banned ? 'inline-block px-2 py-0.5 rounded font-mono text-xs bg-zinc-800 text-red-400 line-through mr-1 mb-1'
                       : isFinal ? 'inline-block px-2 py-0.5 rounded font-mono text-xs bg-emerald-950 text-emerald-300 font-semibold mr-1 mb-1'
                       : 'inline-block px-2 py-0.5 rounded font-mono text-xs bg-zinc-800 text-zinc-300 mr-1 mb-1';
            return `<span class="${cls}">${c}</span>`;
        };

        if (state.my_picks) {
            const tags = state.my_picks.map(c => tagFor(c, state.opp_bans?.includes(c), state.my_final === c)).join('');
            parts.push(`<div><strong class="text-zinc-200">Mis picks:</strong> ${tags}</div>`);
        }
        if (state.opp_picks) {
            const tags = state.opp_picks.map(c => tagFor(c, state.my_bans?.includes(c), state.opp_final === c)).join('');
            parts.push(`<div><strong class="text-zinc-200">Picks del rival:</strong> ${tags}</div>`);
        }

        if (parts.length > 0) {
            summaryEl.classList.remove('hidden');
            summaryEl.innerHTML = parts.join('');
        } else {
            summaryEl.classList.add('hidden');
        }
    }

    function updateTimer() {
        const timerEl = document.getElementById('timer');
        if (!timerEl || !currentState?.turn_deadline) {
            if (timerEl) timerEl.style.display = 'none';
            return;
        }
        timerEl.style.display = '';
        const remaining = Math.max(0, Math.ceil((new Date(currentState.turn_deadline) - new Date()) / 1000));
        timerEl.textContent = `${remaining}s`;
        timerEl.classList.toggle('warning', remaining <= 15 && remaining > 5);
        timerEl.classList.toggle('danger',  remaining <= 5);
    }

    // Auto-redirect a /matches al terminar el civ draft. Mismo patron que el
    // map draft: ambos jugadores quedan en la misma pagina sin depender de
    // que uno clickee.
    let redirectStarted = false;
    function startRedirectCountdown() {
        if (redirectStarted) return;
        redirectStarted = true;

        const countdownEl = document.getElementById('redirect-countdown');
        let secondsLeft = 3;

        const intervalId = setInterval(() => {
            secondsLeft--;
            if (countdownEl) countdownEl.textContent = String(Math.max(0, secondsLeft));
            if (secondsLeft <= 0) {
                clearInterval(intervalId);
                window.location.href = '{{ route("matches.show", $match->id) }}';
            }
        }, 1000);
    }

    loadState();
    setInterval(loadState,  1000);
    setInterval(updateTimer, 200);
</script>
@endpush
