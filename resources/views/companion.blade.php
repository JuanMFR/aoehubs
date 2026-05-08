@extends('layouts.app')

@section('title', 'AoEHubs Companion — Descargar')

@section('content')
@php
    $downloadUrl = config('companion.download_url');
    $version     = config('companion.version');
    $sizeMb      = config('companion.size_mb');
    $sourceUrl   = config('companion.source_url');
@endphp

<div class="space-y-10 max-w-3xl mx-auto">
    {{-- Hero --}}
    <section class="text-center pt-4">
        <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-2xl bg-accent-dark border-2 border-accent text-accent font-bold text-3xl">
            A2
        </div>
        <h1 class="text-3xl sm:text-4xl font-bold">AoEHubs Companion</h1>
        <p class="mt-2 text-zinc-400 max-w-xl mx-auto">
            Una app de Windows que detecta tus partidas ranked, sube el replay automáticamente
            y aplica el rating al terminar.
        </p>

        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="{{ $downloadUrl }}"
               class="inline-flex items-center gap-2 rounded-lg bg-accent px-6 py-3 font-bold text-accent-dark hover:bg-accent-hover transition-colors text-lg">
                Descargar para Windows
            </a>
            <div class="text-xs text-zinc-500">
                <div>v{{ $version }} · ~{{ $sizeMb }}MB</div>
                <div>Solo Windows 10/11 (64-bit)</div>
            </div>
        </div>
    </section>

    {{-- ¿Para qué sirve? --}}
    <section>
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-3">¿Para qué sirve?</h2>
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5 sm:p-6 space-y-3 text-sm text-zinc-300">
            <p>El companion corre en segundo plano mientras jugás Age of Empires 2 DE. Su trabajo es:</p>
            <ul class="list-disc pl-5 space-y-1.5 text-zinc-400">
                <li>Detectar cuándo arrancás una partida ranked emparejada por AoEHubs</li>
                <li>Verificar que el lobby tenga el mapa, civilización y configuración correcta del draft</li>
                <li>Subir el replay (<code class="text-xs px-1 rounded bg-zinc-800">.aoe2record</code>) cuando la partida termina</li>
                <li>Activar el cálculo de rating Glicko-2 una vez que el server valida el replay</li>
            </ul>
            <p class="text-zinc-500 text-xs pt-2 border-t border-zinc-800">
                Sin el companion no podés jugar ranked — el rating depende del replay subido y validado contra el draft.
            </p>
        </div>
    </section>

    {{-- Requisitos + privacidad --}}
    <section class="grid sm:grid-cols-2 gap-4">
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5">
            <h3 class="font-semibold mb-2">Requisitos</h3>
            <ul class="text-sm text-zinc-400 space-y-1">
                <li>Windows 10 u 11 (64-bit)</li>
                <li>Age of Empires 2: Definitive Edition</li>
                <li>Cuenta de Steam (login en AoEHubs)</li>
                <li>~200MB libres en disco</li>
            </ul>
        </div>
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5">
            <h3 class="font-semibold mb-2">Privacidad</h3>
            <ul class="text-sm text-zinc-400 space-y-1">
                <li>Solo lee la carpeta de replays de AoE2 DE</li>
                <li>Sube replays únicamente de partidas iniciadas desde AoEHubs</li>
                <li>El token de acceso vive solo en tu PC</li>
                <li>Configuración local en <code class="text-xs px-1 rounded bg-zinc-800">%APPDATA%\AoE2Companion</code></li>
            </ul>
        </div>
    </section>

    {{-- Cómo configurarlo --}}
    <section>
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-3">Cómo configurarlo</h2>
        <ol class="space-y-3 text-sm text-zinc-300">
            <li class="flex gap-3 rounded-lg border border-zinc-800 bg-zinc-900/40 p-4">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-dark border border-accent text-accent font-bold text-sm">1</span>
                <div>
                    <div class="font-medium text-zinc-100">Descargá y ejecutá el instalador</div>
                    <p class="mt-1 text-xs text-zinc-500">Se instala en <code class="text-[11px] px-1 rounded bg-zinc-800">%LOCALAPPDATA%\Programs\AoE2Companion\</code> sin pedir permisos de administrador. Crea un acceso directo en el menú inicio.</p>
                </div>
            </li>
            <li class="flex gap-3 rounded-lg border border-zinc-800 bg-zinc-900/40 p-4">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-dark border border-accent text-accent font-bold text-sm">2</span>
                <div>
                    <div class="font-medium text-zinc-100">Generá tu token de acceso</div>
                    <p class="mt-1 text-xs text-zinc-500">
                        Logueate en AoEHubs con Steam, andá a tu perfil y apretá "Generar token".
                        El token es de un solo uso — copialo y pegalo en el companion.
                    </p>
                    @auth
                        <a href="{{ route('users.show', auth()->user()->steam_id) }}" class="mt-2 inline-block text-xs text-accent hover:underline">
                            Ir a mi perfil →
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="mt-2 inline-block text-xs text-accent hover:underline">
                            Iniciar sesión con Steam →
                        </a>
                    @endauth
                </div>
            </li>
            <li class="flex gap-3 rounded-lg border border-zinc-800 bg-zinc-900/40 p-4">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-dark border border-accent text-accent font-bold text-sm">3</span>
                <div>
                    <div class="font-medium text-zinc-100">Pegá el token en el companion</div>
                    <p class="mt-1 text-xs text-zinc-500">El companion queda corriendo en la bandeja del sistema. A partir de ahí cada vez que busques partida ranked en la web, va a detectar tu sesión de AoE2 automáticamente.</p>
                </div>
            </li>
        </ol>
    </section>

    {{-- Cómo se usa --}}
    <section>
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-3">Cómo se usa</h2>
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5 sm:p-6 space-y-4 text-sm text-zinc-300">
            <p>Una vez instalado y configurado el token, dejá el companion abierto en segundo plano (se minimiza a la bandeja del sistema). El flujo de una partida ranked es:</p>

            <ol class="space-y-3 mt-2">
                <li class="flex gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-dark border border-accent text-accent font-bold text-sm">1</span>
                    <div>
                        <div class="font-medium text-zinc-100">Buscás partida en la web</div>
                        <p class="mt-1 text-xs text-zinc-500">Desde el dashboard apretás "Buscar partida". Cuando aparece un rival, vas al draft de mapas y civilizaciones.</p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-dark border border-accent text-accent font-bold text-sm">2</span>
                    <div>
                        <div class="font-medium text-zinc-100">Termina el draft → te asignan un rol</div>
                        <p class="mt-1 text-xs text-zinc-500">
                            Uno de los dos jugadores es <strong class="text-accent">host</strong> (arma la sala) y el otro es
                            <strong class="text-sky-300">joiner</strong> (entra a la sala). En el detalle de la partida ves cuál te tocó y qué tenés que hacer.
                        </p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-dark border border-accent text-accent font-bold text-sm">3a</span>
                    <div>
                        <div class="font-medium text-accent">Si sos host</div>
                        <p class="mt-1 text-xs text-zinc-400">
                            Abrí AoE2 DE y andá a <strong class="text-zinc-200">Multijugador → Organizar partida</strong>. El companion completa
                            los datos de creación de sala (nombre, password, server) y los settings del lobby (población, lock teams, victoria, etc.).
                            <strong class="text-zinc-200">No toques nada</strong> mientras lo hace — el cursor se va a mover por su cuenta.
                        </p>
                        <p class="mt-1.5 text-xs text-amber-400">
                            ⚠ El companion <strong>no setea el mapa</strong> ni la civilización. Una vez en el lobby tenés que elegir vos:
                            el mapa que ganó el draft + tu civilización.
                        </p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-sky-950 border border-sky-600 text-sky-300 font-bold text-sm">3b</span>
                    <div>
                        <div class="font-medium text-sky-300">Si sos joiner</div>
                        <p class="mt-1 text-xs text-zinc-400">
                            Solo tenés que tener AoE2 DE abierto. Cuando el host arme la sala, el companion abre el link automáticamente
                            (<code class="text-[11px] px-1 rounded bg-zinc-800">aoe2de://0/{lobbyId}</code>) y completa la contraseña por vos.
                            Aceptás el invite que aparece en AoE2 y entrás al lobby.
                        </p>
                        <p class="mt-1.5 text-xs text-amber-400">
                            ⚠ Una vez en el lobby tenés que <strong>elegir tu civilización</strong> manualmente. El mapa lo setea el host.
                        </p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-950 border border-emerald-600 text-emerald-300 font-bold text-sm">4</span>
                    <div>
                        <div class="font-medium text-zinc-100">Juegan la partida</div>
                        <p class="mt-1 text-xs text-zinc-500">
                            En el lobby, el host puso el mapa, ambos eligieron su civilización del draft, y los settings ya los configuró el companion.
                            Ponen "Listo" los dos y arrancan partida. El companion sigue corriendo en segundo plano.
                        </p>
                    </div>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-950 border border-emerald-600 text-emerald-300 font-bold text-sm">5</span>
                    <div>
                        <div class="font-medium text-zinc-100">Termina la partida → rating actualizado</div>
                        <p class="mt-1 text-xs text-zinc-500">
                            Cuando termina la partida, el companion sube el replay automáticamente. El servidor lo valida (mapa, civ, settings) y
                            aplica el cambio de rating Glicko-2. En segundos ves el resultado en aoehubs.com.
                        </p>
                    </div>
                </li>
            </ol>

            <div class="mt-4 pt-4 border-t border-zinc-800 text-xs text-zinc-500">
                <strong class="text-zinc-300">Tip:</strong> el companion muestra el estado actual en su ventana — sabés en todo momento qué está haciendo
                ("Esperando que abras AoE2", "Ajustando la sala", "Partida en progreso", etc.). Si algo se desconfigura podés revisar ahí.
            </div>
        </div>
    </section>

    {{-- Aviso para testers existentes (Inno Setup → Velopack) --}}
    <section>
        <details class="group rounded-xl border border-sky-900/40 bg-sky-950/10 p-5">
            <summary class="cursor-pointer select-none text-sm font-semibold text-sky-300 flex items-center gap-2">
                <span class="inline-block transition-transform group-open:rotate-90">▶</span>
                ¿Ya tenías el companion antes de v0.3.0? Leer esto
            </summary>
            <div class="mt-3 text-sm text-zinc-300 space-y-2">
                <p>Desde la versión <strong>0.3.0</strong> el companion usa <strong>Velopack</strong> para auto-actualizarse — todas las versiones futuras se aplican solas sin que tengas que reinstalar nada.</p>
                <p>Pero por única vez tenés que hacer una migración manual:</p>
                <ol class="list-decimal pl-5 space-y-1 text-zinc-400 text-xs">
                    <li>Abrí <strong class="text-zinc-200">Configuración de Windows → Aplicaciones</strong></li>
                    <li>Buscá <strong class="text-zinc-200">"AoE2 Companion"</strong> en la lista y desinstalalo</li>
                    <li>Bajá el nuevo setup desde el botón "Descargar para Windows" arriba</li>
                    <li>Ejecutalo — instala el companion nuevo y se abre solo</li>
                </ol>
                <p class="text-xs text-zinc-500 pt-2 border-t border-sky-900/30">
                    Tu token guardado se preserva — no necesitás regenerarlo. A partir de esta versión, todas las nuevas se actualizan en segundo plano automáticamente.
                </p>
            </div>
        </details>
    </section>

    {{-- Aviso SmartScreen --}}
    <section>
        <details class="group rounded-xl border border-amber-900/40 bg-amber-950/10 p-5">
            <summary class="cursor-pointer select-none text-sm font-semibold text-amber-300 flex items-center gap-2">
                <span class="inline-block transition-transform group-open:rotate-90">▶</span>
                Windows va a mostrar un aviso al instalar — es esperado
            </summary>
            <div class="mt-3 text-sm text-zinc-300 space-y-2">
                <p>
                    La primera vez que ejecutes el instalador, Windows SmartScreen muestra una pantalla naranja
                    que dice <em>"Microsoft Defender SmartScreen impidió el inicio de una aplicación no reconocida"</em>.
                </p>
                <p>Para continuar:</p>
                <ol class="list-decimal pl-5 space-y-1 text-zinc-400 text-xs">
                    <li>Click en <strong>"Más información"</strong> (texto pequeño)</li>
                    <li>Click en <strong>"Ejecutar de todos modos"</strong></li>
                </ol>
                <p class="text-xs text-zinc-500 pt-2 border-t border-amber-900/30">
                    El instalador es seguro — la alerta aparece porque la firma de código profesional cuesta
                    ~$300/año y AoEHubs todavía está en beta. Si querés podés verificar el binario contra el
                    @if ($sourceUrl)
                        <a href="{{ $sourceUrl }}" target="_blank" rel="noopener" class="text-accent hover:underline">código fuente</a>
                    @else
                        código fuente
                    @endif
                    y compilarlo vos mismo.
                </p>
            </div>
        </details>
    </section>

    {{-- CTA repetido al final --}}
    <section class="text-center pb-6">
        <a href="{{ $downloadUrl }}"
           class="inline-flex items-center gap-2 rounded-lg bg-accent px-6 py-3 font-bold text-accent-dark hover:bg-accent-hover transition-colors">
            Descargar AoEHubs Companion
        </a>
        <div class="mt-2 text-xs text-zinc-600">v{{ $version }} · ~{{ $sizeMb }}MB · Windows 10/11 64-bit</div>
    </section>
</div>
@endsection
