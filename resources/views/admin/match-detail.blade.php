@extends('layouts.app')

@section('title', 'Admin — Match #' . $match->id)

@section('content')
<div class="space-y-6">
    <div>
        <a href="{{ route('admin.matches') }}" class="text-sm text-accent hover:underline">← Volver a matches</a>
        <h1 class="mt-2 text-2xl font-bold flex items-center gap-3">
            Match #{{ $match->id }}
            <span class="badge badge-{{ $match->status }}">{{ __($match->status) }}</span>
        </h1>
    </div>

    {{-- Acciones rapidas --}}
    @if (in_array($match->status, ['pending', 'in_progress', 'drafting']))
        <div class="rounded-lg border border-red-900/50 bg-red-950/20 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-sm">
                <strong>Match activa.</strong>
                <span class="text-zinc-400">Forzar cancel la marca como abandoned sin afectar rating.</span>
            </div>
            <button type="button"
                    onclick="document.getElementById('admin-cancel-{{ $match->id }}').showModal()"
                    class="rounded border border-red-700 bg-red-950 px-3 py-1.5 text-sm text-red-300 hover:bg-red-900 transition-colors">
                Forzar cancel
            </button>
        </div>
        <x-confirm-modal id="admin-cancel-{{ $match->id }}"
                         title="Forzar cancel del match #{{ $match->id }}"
                         :action="route('admin.matches.cancel', $match->id)"
                         confirmLabel="Sí, marcar como abandoned"
                         :danger="true">
            <p>El match queda como <code class="font-mono text-zinc-300">abandoned</code>, sin afectar rating de los jugadores.</p>
            <p class="text-xs text-zinc-500">Esta acción no aplica anti-griefing — es admin override puro.</p>
        </x-confirm-modal>
    @endif

    @if ($match->status === 'pending_validation')
        <div class="rounded-lg border border-amber-900/50 bg-amber-950/20 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-sm">
                <strong>Replay no parseable.</strong>
                <span class="text-zinc-400">Forzar reprocesamiento (corre el parser de nuevo). Sirve si actualizaste mgz.</span>
            </div>
            <form method="POST" action="{{ route('admin.matches.reprocess', $match->id) }}">
                @csrf
                <button type="submit" class="rounded border border-amber-700 bg-amber-950 px-3 py-1.5 text-sm text-amber-300 hover:bg-amber-900 transition-colors">
                    Reprocesar replay
                </button>
            </form>
        </div>
    @endif

    {{-- Datos generales --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Participantes</h2>
        <div class="grid sm:grid-cols-2 gap-3">
            @php
                $isHostWinner = $match->winner_user_id === $match->host_user_id;
                $isOppWinner  = $match->winner_user_id === $match->opponent_user_id;
            @endphp
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4 {{ $isHostWinner ? 'border-emerald-900' : '' }}">
                <div class="text-xs text-zinc-500 uppercase tracking-wider">Host</div>
                <div class="mt-1 flex items-center gap-2">
                    @if ($match->host->avatar_url)
                        <img src="{{ $match->host->avatar_url }}" alt="" class="h-8 w-8 rounded shrink-0">
                    @endif
                    <div class="min-w-0">
                        <div class="font-medium truncate">
                            @if ($match->host->isBot())
                                <span class="text-amber-400">Bot Dev</span>
                            @else
                                <a href="{{ route('users.show', $match->host->steam_id) }}" class="hover:text-accent transition-colors">{{ $match->host->persona_name ?? Str::limit($match->host->steam_id, 14) }}</a>
                            @endif
                            {{ $isHostWinner ? '🏆' : '' }}
                        </div>
                        <div class="font-mono text-xs text-zinc-500 truncate">{{ $match->host->steam_id }}</div>
                    </div>
                </div>
                @if ($match->host_rating_before !== null)
                    <div class="mt-2 text-sm font-mono">
                        <span class="text-zinc-500">{{ round($match->host_rating_before) }}</span>
                        @php $delta = $match->host_rating_change; @endphp
                        @if ($delta > 0)<span class="text-emerald-400">+{{ round($delta) }}</span>
                        @elseif ($delta < 0)<span class="text-red-400">{{ round($delta) }}</span>
                        @else <span class="text-zinc-500">±0</span>
                        @endif
                    </div>
                @endif
            </div>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4 {{ $isOppWinner ? 'border-emerald-900' : '' }}">
                <div class="text-xs text-zinc-500 uppercase tracking-wider">Opponent</div>
                <div class="mt-1 flex items-center gap-2">
                    @if ($match->opponent?->avatar_url)
                        <img src="{{ $match->opponent->avatar_url }}" alt="" class="h-8 w-8 rounded shrink-0">
                    @endif
                    <div class="min-w-0">
                        <div class="font-medium truncate">
                            @if (! $match->opponent)
                                <span class="text-zinc-500">—</span>
                            @elseif ($match->opponent->isBot())
                                <span class="text-amber-400">Bot Dev</span>
                            @else
                                <a href="{{ route('users.show', $match->opponent->steam_id) }}" class="hover:text-accent transition-colors">{{ $match->opponent->persona_name ?? Str::limit($match->opponent->steam_id, 14) }}</a>
                            @endif
                            {{ $isOppWinner ? '🏆' : '' }}
                        </div>
                        <div class="font-mono text-xs text-zinc-500 truncate">{{ $match->opponent?->steam_id }}</div>
                    </div>
                </div>
                @if ($match->opponent_rating_before !== null)
                    <div class="mt-2 text-sm font-mono">
                        <span class="text-zinc-500">{{ round($match->opponent_rating_before) }}</span>
                        @php $delta = $match->opponent_rating_change; @endphp
                        @if ($delta > 0)<span class="text-emerald-400">+{{ round($delta) }}</span>
                        @elseif ($delta < 0)<span class="text-red-400">{{ round($delta) }}</span>
                        @else <span class="text-zinc-500">±0</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Lifecycle timestamps --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Ciclo de vida</h2>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 divide-y divide-zinc-800">
            @php
                $rows = [
                    'created_at'            => 'Creada',
                    'started_at'            => 'Empezó (replay creado)',
                    'host_heartbeat_at'     => 'Último heartbeat host',
                    'opponent_heartbeat_at' => 'Último heartbeat opp',
                    'parsed_at'             => 'Parseada',
                    'updated_at'            => 'Última actualización',
                ];
            @endphp
            @foreach ($rows as $field => $label)
                <div class="flex justify-between px-4 py-2 text-sm">
                    <span class="text-zinc-500">{{ $label }}</span>
                    <span class="font-mono text-xs text-zinc-300">{{ $match->$field?->format('Y-m-d H:i:s') ?? '—' }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Lobby config --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Configuración del lobby</h2>
        <pre class="rounded-lg border border-zinc-800 bg-zinc-950 p-4 text-xs font-mono text-zinc-300 overflow-x-auto">{{ json_encode($match->config_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </section>

    {{-- Drafts --}}
    @if ($match->mapDraft)
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Map draft</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4 text-sm">
                <div><strong>Final:</strong> <span class="text-emerald-400">{{ $match->mapDraft->final_map ? __($match->mapDraft->final_map) : '(en curso)' }}</span></div>
                <div class="mt-1 text-zinc-400"><strong>Bans:</strong> {{ count($match->mapDraft->bans_json ?? []) }}</div>
            </div>
        </section>
    @endif

    @if ($match->civDraft)
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Civ draft</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4 text-sm space-y-1">
                <div><strong>Phase:</strong> {{ $match->civDraft->phase }}</div>
                <div><strong>Host final:</strong> {{ $match->civDraft->host_final_civ ? __($match->civDraft->host_final_civ) : '—' }}</div>
                <div><strong>Opp final:</strong> {{ $match->civDraft->opponent_final_civ ? __($match->civDraft->opponent_final_civ) : '—' }}</div>
            </div>
        </section>
    @endif

    {{-- Replay --}}
    @if ($match->replay_path || $match->replay_filename)
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Replay</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4 text-sm space-y-1">
                <div><strong>Filename:</strong> <span class="font-mono text-xs">{{ $match->replay_filename }}</span></div>
                <div><strong>Size:</strong> {{ round($match->replay_size / 1024) }} KB</div>
                <div><strong>Path:</strong> <span class="font-mono text-xs text-zinc-400">{{ $match->replay_path }}</span></div>
            </div>
        </section>
    @endif

    {{-- Validation errors --}}
    @if (! empty($match->validation_errors))
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Validation errors</h2>
            <div class="rounded-lg border border-orange-900/50 bg-orange-950/20 p-4">
                <ul class="list-disc list-inside text-sm text-orange-300 space-y-1">
                    @foreach ($match->validation_errors as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Parsed metadata --}}
    @if (! empty($match->parsed_metadata))
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Metadata parseada (mgz)</h2>
            <pre class="rounded-lg border border-zinc-800 bg-zinc-950 p-4 text-xs font-mono text-zinc-300 overflow-x-auto max-h-96">{{ json_encode($match->parsed_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </section>
    @endif
</div>
@endsection
