@extends('layouts.app')

@section('title', 'Dashboard — AoEHubs')

@section('content')
@php
    $secondsLeft = $season?->secondsUntilEnd();
    $daysLeft = $secondsLeft !== null ? (int) ceil($secondsLeft / 86400) : null;
@endphp

<div class="space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
        <div>
            <h1 class="text-2xl font-bold">Dashboard</h1>
            <p class="mt-1 text-sm text-zinc-500">Bienvenido, {{ $user->displayName() }}.</p>
        </div>
        @if ($season)
            <div class="text-xs sm:text-right">
                <div class="text-zinc-500 uppercase tracking-wider">{{ $season->name }}</div>
                @if ($daysLeft !== null && $daysLeft > 0)
                    <div class="font-mono text-accent mt-0.5">termina en {{ $daysLeft }} {{ $daysLeft === 1 ? 'día' : 'días' }}</div>
                @elseif ($daysLeft !== null && $daysLeft <= 0)
                    <div class="font-mono text-amber-400 mt-0.5">cierre pendiente</div>
                @else
                    <div class="text-zinc-600 mt-0.5">sin fecha de cierre</div>
                @endif
            </div>
        @endif
    </div>

    {{-- "Estás en partida" CTA — aparece si hay drafting/pending/in_progress --}}
    @if ($activeMatch)
        <a href="{{ $activeMatchUrl }}"
           class="block rounded-xl border border-emerald-700/60 bg-gradient-to-r from-emerald-950/40 to-zinc-900/60 p-4 sm:p-5 hover:from-emerald-950/60 transition-all">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="relative flex h-3 w-3 shrink-0">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span>
                        <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-400"></span>
                    </span>
                    <div class="min-w-0">
                        <div class="font-semibold text-emerald-300">¡Estás en partida!</div>
                        <p class="text-sm text-zinc-400 truncate">
                            Match #{{ $activeMatch->id }} en
                            <span class="font-mono">{{ $activeMatch->status }}</span>
                            @if ($activeMatch->status === 'drafting')
                                — volvé al draft para terminar de banear/elegir
                            @elseif ($activeMatch->status === 'pending')
                                — el host arma el lobby, mirá el detalle para los datos
                            @elseif ($activeMatch->status === 'in_progress')
                                — el match está corriendo en AoE2
                            @endif
                        </p>
                    </div>
                </div>
                <span class="hidden sm:block text-emerald-300 font-semibold shrink-0">Volver →</span>
            </div>
        </a>
    @endif

    {{-- Matchmaking CTA / queue state --}}
    @if (!$activeMatch)
        <section>
            @if ($inCooldown)
                <div class="rounded-xl border border-red-900/60 bg-red-950/20 p-6 sm:p-8">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex-1">
                            <h2 class="text-lg font-semibold text-red-300">Has abandonado demasiadas partidas</h2>
                            <p class="mt-1 text-sm text-zinc-400">Vas a poder volver a buscar cuando termine el contador.</p>
                        </div>
                        <div id="cooldown-timer"
                             data-seconds="{{ $cooldownSeconds }}"
                             class="font-mono text-3xl sm:text-4xl font-bold text-red-300 tabular-nums shrink-0 text-center">
                            {{ $cooldownLeft }}
                        </div>
                    </div>
                </div>
            @elseif ($queueEntry)
                <div class="rounded-xl border border-accent/30 bg-gradient-to-r from-accent-dark/40 to-zinc-900/50 p-6 sm:p-8 transition-all">
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
                            @if (!$companionAlive)
                                <p class="mt-1 text-sm text-amber-300">
                                    Tu companion no parece estar corriendo. Abrilo para poder buscar partida.
                                </p>
                                <p class="mt-1 text-xs text-zinc-500">
                                    ¿No lo tenés instalado? <a href="{{ route('companion') }}" class="text-accent hover:underline">Descargalo acá</a>.
                                </p>
                            @elseif ($botInQueue)
                                <p class="mt-1 text-sm text-zinc-400">
                                    Hay un Bot Dev permanentemente en cola — vas a quedar emparejado al instante para testing.
                                </p>
                            @else
                                <p class="mt-1 text-sm text-zinc-400">
                                    Vas a quedar en cola hasta que aparezca otro jugador buscando partida.
                                </p>
                            @endif
                        </div>
                        @if ($companionAlive)
                            <form method="POST" action="{{ route('queue.join') }}" class="shrink-0" data-loading-form>
                                @csrf
                                <button type="submit"
                                        class="w-full sm:w-auto rounded-lg bg-accent px-6 py-3 font-semibold text-accent-dark hover:bg-accent-hover transition-colors disabled:opacity-60 disabled:cursor-wait"
                                        data-loading-text="Buscando...">
                                    Buscar partida
                                </button>
                            </form>
                        @else
                            <button type="button" disabled
                                    class="w-full sm:w-auto rounded-lg bg-zinc-800 px-6 py-3 font-semibold text-zinc-500 cursor-not-allowed">
                                Buscar partida
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </section>
    @endif

    {{-- Tu card (mismo componente que aparece en draft) + atajo a vitrina --}}
    <section>
        <div class="flex items-baseline justify-between mb-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Tu cuenta</h2>
            <a href="{{ route('users.show', $user->steam_id) }}" class="text-xs text-accent hover:underline">ver perfil completo →</a>
        </div>
        <x-player-card :user="$user" variant="self" :compact="true" />
    </section>

    {{-- Stats de la season — carrusel auto-advance, pausable con hover. --}}
    @if ($season && $seasonStats)
        @php
            // Lista declarativa de stats. Cada entrada: title + value + subtitle + opcional href.
            $statCards = [];

            $statCards[] = [
                'title' => 'Partidas jugadas',
                'value' => $seasonStats['total_matches'],
                'subtitle' => 'completadas en la season',
            ];

            if ($seasonStats['top_map'] ?? null) {
                $statCards[] = [
                    'title' => 'Mapa más jugado',
                    'value' => __($seasonStats['top_map']->map),
                    'subtitle' => $seasonStats['top_map']->count . ' partidas',
                    'map'   => $seasonStats['top_map']->map,
                ];
            }

            if ($seasonStats['top_banned_map'] ?? null) {
                $statCards[] = [
                    'title' => 'Mapa más baneado',
                    'value' => __($seasonStats['top_banned_map']->map),
                    'subtitle' => $seasonStats['top_banned_map']->count . ' bans',
                    'map'   => $seasonStats['top_banned_map']->map,
                ];
            }

            if ($seasonStats['top_civ'] ?? null) {
                $statCards[] = [
                    'title' => 'Civ más ganadora',
                    'value' => __($seasonStats['top_civ']->civ),
                    'subtitle' => $seasonStats['top_civ']->count . ' wins',
                    'civ'    => $seasonStats['top_civ']->civ,
                ];
            }

            if ($seasonStats['top_banned_civ'] ?? null) {
                $statCards[] = [
                    'title' => 'Civ más baneada',
                    'value' => __($seasonStats['top_banned_civ']->civ),
                    'subtitle' => $seasonStats['top_banned_civ']->count . ' bans',
                    'civ'    => $seasonStats['top_banned_civ']->civ,
                ];
            }

            if ($seasonStats['top_player'] ?? null) {
                $statCards[] = [
                    'title' => 'Top 1 ranking',
                    'value' => $seasonStats['top_player']->displayName(),
                    'subtitle' => round($seasonStats['top_player']->rating) . ' rating',
                    'href' => route('users.show', $seasonStats['top_player']->steam_id),
                ];
            }

            if (isset($seasonStats['most_active_user']) && $seasonStats['most_active_user']) {
                $statCards[] = [
                    'title' => 'Más activo',
                    'value' => $seasonStats['most_active_user']->displayName(),
                    'subtitle' => $seasonStats['most_active']->plays . ' partidas',
                    'href' => route('users.show', $seasonStats['most_active_user']->steam_id),
                ];
            }
        @endphp

        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Estadísticas — {{ $season->name }}</h2>
            <div id="stats-carousel"
                 class="flex overflow-x-auto snap-x snap-mandatory gap-3 pb-1 scrollbar-hide scroll-smooth">
                @foreach ($statCards as $card)
                    @if (isset($card['href']))
                        <a href="{{ $card['href'] }}"
                           class="snap-start shrink-0 w-[80%] sm:w-[45%] md:w-[30%] lg:w-[19%] rounded-lg border border-zinc-800 bg-zinc-900/50 p-4 hover:bg-zinc-900/70 transition-colors block">
                            <div class="text-xs text-zinc-500 uppercase tracking-wider">{{ $card['title'] }}</div>
                            <div class="mt-1 font-semibold text-base truncate flex items-center gap-2" title="{{ $card['value'] }}">
                                @if (isset($card['civ']))
                                    <x-civ-icon :name="$card['civ']" class="h-6 w-6 rounded shrink-0 text-[10px]" />
                                @elseif (isset($card['map']))
                                    <x-map-icon :name="$card['map']" class="h-6 w-6 rounded shrink-0 text-[10px]" />
                                @endif
                                <span class="truncate">{{ $card['value'] }}</span>
                            </div>
                            <div class="text-xs text-zinc-500 font-mono mt-0.5">{{ $card['subtitle'] }}</div>
                        </a>
                    @else
                        <div class="snap-start shrink-0 w-[80%] sm:w-[45%] md:w-[30%] lg:w-[19%] rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
                            <div class="text-xs text-zinc-500 uppercase tracking-wider">{{ $card['title'] }}</div>
                            <div class="mt-1 font-semibold text-base truncate flex items-center gap-2" title="{{ $card['value'] }}">
                                @if (isset($card['civ']))
                                    <x-civ-icon :name="$card['civ']" class="h-6 w-6 rounded shrink-0 text-[10px]" />
                                @elseif (isset($card['map']))
                                    <x-map-icon :name="$card['map']" class="h-6 w-6 rounded shrink-0 text-[10px]" />
                                @endif
                                <span class="truncate">{{ $card['value'] }}</span>
                            </div>
                            <div class="text-xs text-zinc-500 font-mono mt-0.5">{{ $card['subtitle'] }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    {{-- Torneos activos (placeholder — pieza de contenido + monetizacion futura.
         Cuando exista el modelo Tournament: listar activos + boton "promocionar". --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Torneos activos</h2>
        <div class="rounded-xl border border-dashed border-zinc-800 bg-zinc-900/30 p-6 text-center">
            <p class="text-sm text-zinc-400">Próximamente — torneos de la comunidad.</p>
            <p class="mt-1 text-xs text-zinc-600">¿Organizás un torneo? El espacio para promocionarlo va a estar acá.</p>
        </div>
    </section>

    {{-- TODO MONETIZACION: este es un buen lugar para slot de Google AdSense
         o promociones sponsoreadas. Estructura de container ya esta definida —
         solo agregar un <section> con el ad slot cuando se active publisher. --}}
</div>

{{-- Modal "Partida encontrada" — abre solo desde JS cuando el polling
     detecta una match nueva. Auto-redirige tras countdown. --}}
<dialog id="match-found-modal"
        class="rounded-xl bg-zinc-900 border-2 border-emerald-500/40 backdrop:bg-black/70 backdrop:backdrop-blur-sm max-w-md w-[90%] p-0 text-zinc-100 m-auto">
    <div class="p-6 sm:p-7 text-center">
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-950 border-2 border-emerald-400">
            <span class="relative flex h-3 w-3">
                <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span>
                <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-400"></span>
            </span>
        </div>
        <h3 class="text-2xl font-bold text-emerald-300">¡Partida encontrada!</h3>

        <div id="match-found-rival" class="mt-4 hidden">
            <div class="flex items-center justify-center gap-3 mb-2">
                <img id="match-found-rival-avatar" src="" alt="" class="h-12 w-12 rounded-lg border border-zinc-700 hidden">
                <div id="match-found-rival-avatar-fallback" class="h-12 w-12 rounded-lg bg-zinc-800 border border-zinc-700 flex items-center justify-center text-xl text-zinc-500"></div>
                <div class="text-left">
                    <div class="text-xs text-zinc-500 uppercase tracking-wider">Rival</div>
                    <div id="match-found-rival-name" class="font-bold"></div>
                    <div id="match-found-rival-rating" class="text-xs text-zinc-500 font-mono"></div>
                </div>
            </div>
        </div>

        <p class="mt-3 text-sm text-zinc-400">
            Empezando draft de mapas en
            <span id="match-found-countdown" class="font-mono text-emerald-300 font-semibold">3</span>s...
        </p>

        <div class="mt-5">
            <button type="button"
                    id="match-found-go"
                    class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-emerald-50 transition-colors">
                Ir al draft ahora →
            </button>
        </div>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
    // Carousel auto-advance de stats. Pausa con hover/touch.
    (() => {
        const track = document.getElementById('stats-carousel');
        if (!track || track.children.length <= 1) return;

        let currentIdx = 0;
        let paused = false;

        function advance() {
            if (paused) return;
            currentIdx = (currentIdx + 1) % track.children.length;
            const card = track.children[currentIdx];
            track.scrollTo({ left: card.offsetLeft - track.offsetLeft, behavior: 'smooth' });
        }

        setInterval(advance, 5000);
        track.addEventListener('mouseenter', () => paused = true);
        track.addEventListener('mouseleave', () => paused = false);
        track.addEventListener('touchstart', () => paused = true, { passive: true });
        track.addEventListener('touchend',   () => setTimeout(() => paused = false, 3000), { passive: true });

        let scrollTimeout;
        track.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                const cardWidth = track.children[0].offsetWidth + 12;
                currentIdx = Math.round(track.scrollLeft / cardWidth);
            }, 150);
        });
    })();

    // Cooldown countdown — tickea hasta 0 y refresca la pagina para
    // re-habilitar el CTA "Buscar partida".
    (() => {
        const el = document.getElementById('cooldown-timer');
        if (!el) return;
        let secs = parseInt(el.dataset.seconds, 10) || 0;

        function fmt(s) {
            if (s < 60)        return s + 's';
            const m = Math.floor(s / 60);
            const r = s % 60;
            if (s < 3600)      return m + ':' + String(r).padStart(2, '0');
            const h = Math.floor(s / 3600);
            const mm = Math.floor((s % 3600) / 60);
            return h + 'h ' + mm + 'min';
        }

        function tick() {
            if (secs <= 0) {
                window.location.reload();
                return;
            }
            el.textContent = fmt(secs);
            secs--;
        }
        tick();
        setInterval(tick, 1000);
    })();

    // Sonido de notificacion via Web Audio API — sin assets externos.
    let audioCtx = null;
    function playMatchFoundSound() {
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            const playTone = (freq, startOffset, dur) => {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.connect(gain).connect(audioCtx.destination);
                osc.frequency.value = freq;
                osc.type = 'sine';
                const t0 = audioCtx.currentTime + startOffset;
                gain.gain.setValueAtTime(0, t0);
                gain.gain.linearRampToValueAtTime(0.3, t0 + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.001, t0 + dur);
                osc.start(t0);
                osc.stop(t0 + dur);
            };
            playTone(880, 0, 0.18);
            playTone(1320, 0.18, 0.25);
        } catch (e) { /* sin sonido si el browser no lo permite */ }
    }

    let matchFoundHandled = false;
    function showMatchFound(rival, redirectUrl) {
        if (matchFoundHandled) return;
        matchFoundHandled = true;

        const modal = document.getElementById('match-found-modal');
        if (!modal) { window.location.href = redirectUrl; return; }

        if (rival) {
            const wrap = document.getElementById('match-found-rival');
            wrap.classList.remove('hidden');
            document.getElementById('match-found-rival-name').textContent = rival.name;
            document.getElementById('match-found-rival-rating').textContent = rival.rating + ' rating' + (rival.is_bot ? ' · BOT' : '');
            const img = document.getElementById('match-found-rival-avatar');
            const fallback = document.getElementById('match-found-rival-avatar-fallback');
            if (rival.avatar_url) {
                img.src = rival.avatar_url;
                img.classList.remove('hidden');
                fallback.classList.add('hidden');
            } else {
                fallback.textContent = (rival.name || '?').charAt(0).toUpperCase();
            }
        }

        playMatchFoundSound();
        modal.showModal();

        let secs = 3;
        const counter = document.getElementById('match-found-countdown');
        const tick = () => {
            secs--;
            if (counter) counter.textContent = String(Math.max(0, secs));
            if (secs <= 0) {
                clearInterval(intervalId);
                window.location.href = redirectUrl;
            }
        };
        const intervalId = setInterval(tick, 1000);

        document.getElementById('match-found-go').addEventListener('click', () => {
            clearInterval(intervalId);
            window.location.href = redirectUrl;
        });
    }

    // Page-load fire: emparejamos instantaneamente (ej. con Bot Dev) y el
    // server redirigio al dashboard con session('match_just_made'). El
    // dashboard tiene activeMatch + rival info via PHP — disparamos el
    // modal sin esperar polling.
    @if (session('match_just_made') && $activeMatch && $activeMatchRival)
        showMatchFound(@json($activeMatchRival), @json($activeMatchUrl));
    @endif

    @if ($queueEntry)
        // Polling cuando estamos en cola esperando rival real.
        const joinedAt   = new Date('{{ $queueEntry->joined_at->toIso8601String() }}');
        const statusUrl  = '{{ route('queue.status') }}';
        const timerEl    = document.getElementById('queue-timer');

        function fmtQ(secs) {
            const m = Math.floor(secs / 60);
            const s = String(secs % 60).padStart(2, '0');
            return m > 0 ? `${m}:${s}` : `${s}s`;
        }

        function updateQueueTimer() {
            const elapsed = Math.max(0, Math.floor((Date.now() - joinedAt.getTime()) / 1000));
            if (timerEl) timerEl.textContent = fmtQ(elapsed);
        }

        async function pollStatus() {
            try {
                const r = await fetch(statusUrl, { headers: { 'Accept': 'application/json' }});
                if (!r.ok) return;
                const data = await r.json();

                if (data.redirect_url && !matchFoundHandled) {
                    showMatchFound(data.rival, data.redirect_url);
                    return;
                }
                if (!data.in_queue && !matchFoundHandled) {
                    window.location.reload();
                }
            } catch (e) { /* */ }
        }

        updateQueueTimer();
        setInterval(updateQueueTimer, 1000);
        setInterval(pollStatus, 2000);
    @endif
</script>
@endpush
