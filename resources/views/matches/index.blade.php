@extends('layouts.app')

@section('title', 'Mis matches — AoEHubs')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Mis matches</h1>
        <p class="mt-1 text-sm text-zinc-500">Historial de tus partidas. Click en cualquier fila para ver el detalle.</p>
    </div>

    {{-- Filtros — collapsible asi no satura la vista por default --}}
    <details class="group rounded-lg border border-zinc-800 bg-zinc-900/40">
        <summary class="cursor-pointer select-none flex items-center justify-between gap-2 px-4 py-3 text-sm">
            <span class="font-medium text-zinc-300">
                <span class="inline-block transition-transform group-open:rotate-90">▶</span>
                Filtros
                @if ($statusFilter || $resultFilter || $opponentQ)
                    <span class="ml-2 text-xs px-2 py-0.5 rounded bg-accent-dark text-accent">activos</span>
                @endif
            </span>
            <span class="text-xs text-zinc-500">{{ $matches->total() }} {{ Str::plural('partida', $matches->total()) }}</span>
        </summary>
        <form method="GET" class="grid sm:grid-cols-4 gap-3 p-4 border-t border-zinc-800">
            <div>
                <label class="block text-xs font-medium text-zinc-500 mb-1">Estado</label>
                <select name="status" class="w-full rounded border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm focus:border-accent focus:outline-none">
                    <option value="">Todos</option>
                    @foreach ($statuses as $st)
                        <option value="{{ $st }}" {{ $statusFilter === $st ? 'selected' : '' }}>{{ __($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-zinc-500 mb-1">Resultado</label>
                <select name="result" class="w-full rounded border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm focus:border-accent focus:outline-none">
                    <option value="">Todos</option>
                    <option value="win"      {{ $resultFilter === 'win'      ? 'selected' : '' }}>Victorias</option>
                    <option value="loss"     {{ $resultFilter === 'loss'     ? 'selected' : '' }}>Derrotas</option>
                    <option value="walkover" {{ $resultFilter === 'walkover' ? 'selected' : '' }}>Walkover</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-zinc-500 mb-1">Rival</label>
                <input type="text" name="opponent" value="{{ $opponentQ }}" placeholder="Buscar por nombre..."
                       class="w-full rounded border border-zinc-700 bg-zinc-950 px-2 py-1.5 text-sm focus:border-accent focus:outline-none">
            </div>
            <div class="sm:col-span-4 flex gap-2">
                <button type="submit" class="rounded border border-accent/60 bg-accent-dark px-3 py-1.5 text-sm text-accent hover:bg-accent hover:text-accent-dark transition-colors">
                    Aplicar
                </button>
                @if ($statusFilter || $resultFilter || $opponentQ)
                    <a href="{{ route('matches.index') }}" class="rounded px-3 py-1.5 text-sm text-zinc-400 hover:text-zinc-100">Limpiar</a>
                @endif
            </div>
        </form>
    </details>

    {{-- Lista --}}
    @if ($matches->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-800 bg-zinc-900/30 p-12 text-center">
            @if ($statusFilter || $resultFilter || $opponentQ)
                <p class="text-zinc-500">Sin resultados para los filtros aplicados.</p>
                <p class="mt-1 text-xs text-zinc-600">Probá <a href="{{ route('matches.index') }}" class="text-accent hover:underline">limpiar filtros</a>.</p>
            @else
                <p class="text-zinc-500">Todavía no jugaste ninguna partida.</p>
                <p class="mt-1 text-xs text-zinc-600">Andá al <a href="{{ route('dashboard') }}" class="text-accent hover:underline">dashboard</a> y apretá "Buscar partida".</p>
            @endif
        </div>
    @else
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/30 divide-y divide-zinc-800 overflow-hidden">
            @foreach ($matches as $m)
                @php
                    $myId  = auth()->id();
                    $isHost = $m->host_user_id === $myId;
                    $opp   = $isHost ? $m->opponent : $m->host;
                    $oppName = $opp ? $opp->displayName() : '—';
                    $myCiv = $m->civDraft ? ($isHost ? $m->civDraft->host_final_civ : $m->civDraft->opponent_final_civ) : null;
                    $oppCiv = $m->civDraft ? ($isHost ? $m->civDraft->opponent_final_civ : $m->civDraft->host_final_civ) : null;
                    $map = $m->mapDraft?->final_map;
                    $change = $isHost ? $m->host_rating_change : $m->opponent_rating_change;
                    $won = $m->winner_user_id === $myId;
                    $lost = $m->winner_user_id !== null && $m->winner_user_id !== $myId;
                    $walkover = $m->status === 'completed' && $m->replay_path === null;
                @endphp

                <div class="relative grid grid-cols-12 gap-3 items-center px-4 py-3 hover:bg-zinc-900/60 transition-colors">
                    {{-- Stretched link: cubre toda la fila pero deja activos elementos con z-index superior. --}}
                    <a href="{{ route('matches.show', $m->id) }}"
                       class="absolute inset-0 z-0"
                       aria-label="Ver detalle del match contra {{ $oppName }}"></a>

                    {{-- Rival --}}
                    <div class="col-span-12 sm:col-span-4 flex items-center gap-3 min-w-0 relative z-10 pointer-events-none">
                        @if ($opp && $opp->avatar_url)
                            <img src="{{ $opp->avatar_url }}" alt="" class="h-10 w-10 rounded-lg border border-zinc-700 shrink-0">
                        @else
                            <div class="h-10 w-10 rounded-lg bg-zinc-800 border border-zinc-700 flex items-center justify-center text-sm text-zinc-400 shrink-0">
                                {{ Str::upper(Str::substr($oppName, 0, 1)) }}
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="font-medium truncate flex items-center gap-2">
                                {{ $oppName }}
                                @if ($opp?->isBot())
                                    <span class="text-[10px] px-1 rounded bg-zinc-800 text-zinc-500 uppercase">bot</span>
                                @endif
                            </div>
                            @if ($m->status !== 'completed')
                                <div class="mt-0.5">
                                    <span class="badge badge-{{ $m->status }}">{{ __($m->status) }}</span>
                                </div>
                            @else
                                <div class="text-xs text-zinc-500 font-mono">{{ $m->created_at->diffForHumans() }}</div>
                            @endif
                        </div>
                    </div>

                    {{-- Map + Civ --}}
                    <div class="col-span-6 sm:col-span-3 text-sm relative z-10 pointer-events-none">
                        <div class="flex items-center gap-2 text-zinc-200 truncate">
                            @if ($map)
                                <x-map-icon :name="$map" class="h-5 w-5 rounded shrink-0 text-[8px]" />
                            @endif
                            <span class="truncate">{{ $map ? __($map) : '—' }}</span>
                        </div>
                        @if ($myCiv)
                            <div class="text-xs text-zinc-500 truncate flex items-center gap-1 mt-0.5">
                                <x-civ-icon :name="$myCiv" class="h-4 w-4 rounded shrink-0 text-[8px]" />
                                <span class="text-zinc-400">{{ __($myCiv) }}</span>
                                @if ($oppCiv)
                                    <span class="text-zinc-600">vs</span>
                                    <x-civ-icon :name="$oppCiv" class="h-4 w-4 rounded shrink-0 text-[8px]" />
                                    <span>{{ __($oppCiv) }}</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Resultado + ΔRating --}}
                    <div class="col-span-6 sm:col-span-3 text-right sm:text-left relative z-10 pointer-events-none">
                        @if ($won)
                            <div class="text-lg font-bold text-emerald-400 leading-tight">WIN</div>
                        @elseif ($lost)
                            <div class="text-lg font-bold text-red-400 leading-tight">LOSS</div>
                        @elseif ($m->status === 'completed')
                            <div class="text-zinc-600">—</div>
                        @endif

                        @if ($change !== null && $change != 0)
                            <div class="text-xs font-mono mt-0.5">
                                @if ($change > 0)
                                    <span class="text-emerald-400">+{{ round($change) }}</span>
                                @elseif ($change < 0)
                                    <span class="text-red-400">{{ round($change) }}</span>
                                @endif
                                <span class="text-zinc-600">rating</span>
                            </div>
                        @endif

                        @if ($walkover)
                            <div class="text-[10px] text-zinc-500 italic">{{ $won ? 'walkover' : 'forfeit' }}</div>
                        @endif
                    </div>

                    {{-- Acciones (cancel solo en pending) --}}
                    <div class="col-span-12 sm:col-span-2 flex sm:justify-end gap-2 relative z-20">
                        @if ($m->status === 'pending')
                            <button type="button"
                                    onclick="event.stopPropagation(); document.getElementById('cancel-row-{{ $m->id }}').showModal()"
                                    class="rounded border border-red-900 px-2 py-1 text-xs text-red-400 hover:bg-red-950 transition-colors">
                                Cancelar
                            </button>
                        @endif
                    </div>

                    {{-- Modales — fuera del flujo visual pero dentro de la fila por context --}}
                    @if ($m->status === 'pending')
                        <x-confirm-modal id="cancel-row-{{ $m->id }}"
                                         title="¿Cancelar match?"
                                         :action="route('matches.cancel', $m->id)"
                                         confirmLabel="Sí, abandonar"
                                         cancelLabel="No"
                                         :danger="true">
                            <p>Vas a abandonar la partida.</p>
                            <p class="text-accent">El tiempo de tu rival también es valioso.</p>
                            <p class="text-xs text-zinc-500">Si lo hacés repetidamente vas a quedar bloqueado para buscar partida durante un tiempo.</p>
                        </x-confirm-modal>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $matches->links() }}</div>
    @endif
</div>
@endsection
