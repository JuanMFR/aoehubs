@extends('layouts.app')

@section('title', "Admin — Votación '{$vote->name}'")

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold flex items-center gap-3">
            {{ $vote->name }}
            @if ($vote->status === \App\Models\MapPoolVote::STATUS_OPEN)
                <span class="text-xs px-2 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-800/60 uppercase tracking-wider">abierta</span>
            @elseif ($vote->status === \App\Models\MapPoolVote::STATUS_CLOSED)
                <span class="text-xs px-2 py-0.5 rounded bg-zinc-800 text-zinc-300 border border-zinc-700 uppercase tracking-wider">cerrada</span>
            @else
                <span class="text-xs px-2 py-0.5 rounded bg-red-950 text-red-300 border border-red-800/60 uppercase tracking-wider">cancelada</span>
            @endif
        </h1>
        <p class="mt-1 text-sm text-zinc-500">
            <a href="{{ route('admin.map-votes') }}" class="hover:text-zinc-300">← Volver a votaciones</a>
        </p>
    </div>

    {{-- Metadata --}}
    <section class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/40 p-4">
            <div class="text-xs text-zinc-500 uppercase tracking-wider">Inicio</div>
            <div class="font-mono text-sm">{{ $vote->starts_at->format('Y-m-d H:i') }}</div>
        </div>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/40 p-4">
            <div class="text-xs text-zinc-500 uppercase tracking-wider">Fin</div>
            <div class="font-mono text-sm">{{ $vote->ends_at->format('Y-m-d H:i') }}</div>
            @if ($vote->status === \App\Models\MapPoolVote::STATUS_OPEN)
                <div class="text-xs text-zinc-600 mt-1">{{ $vote->ends_at->diffForHumans() }}</div>
            @endif
        </div>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/40 p-4">
            <div class="text-xs text-zinc-500 uppercase tracking-wider">Pool size voted</div>
            <div class="font-mono text-sm">{{ $vote->pool_size_voted }} de {{ $vote->candidates->count() }}</div>
        </div>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/40 p-4">
            <div class="text-xs text-zinc-500 uppercase tracking-wider">Ballots</div>
            <div class="font-mono text-2xl text-accent">{{ $totalBallots }}</div>
        </div>
    </section>

    {{-- Tally --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">
            @if ($vote->status === \App\Models\MapPoolVote::STATUS_CLOSED)
                Resultados finales
            @else
                Tally en vivo
            @endif
        </h2>
        <div class="overflow-x-auto rounded-lg border border-zinc-800">
            <table class="w-full text-sm">
                <thead class="bg-zinc-900/60">
                    <tr class="text-left text-xs uppercase tracking-wider text-zinc-500">
                        <th class="px-3 py-3 w-12">#</th>
                        <th class="px-3 py-3">Mapa</th>
                        <th class="px-3 py-3">Votos</th>
                        <th class="px-3 py-3">% de ballots</th>
                        <th class="px-3 py-3" title="cuantas veces gano una votacion previa — tiebreaker">Wins previos</th>
                        <th class="px-3 py-3">¿Entra al pool?</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @foreach ($tally as $i => $row)
                        @php
                            $isWinner = $i < $vote->pool_size_voted;
                            $pct = $totalBallots > 0 ? round($row['votes'] / $totalBallots * 100) : 0;
                        @endphp
                        <tr class="hover:bg-zinc-900/40 {{ $isWinner ? 'bg-emerald-950/10' : '' }}">
                            <td class="px-3 py-3 font-mono text-xs text-zinc-500">{{ $i + 1 }}</td>
                            <td class="px-3 py-3 font-medium">{{ $row['map']->name }}</td>
                            <td class="px-3 py-3 font-mono">{{ $row['votes'] }}</td>
                            <td class="px-3 py-3 font-mono text-xs text-zinc-500">{{ $pct }}%</td>
                            <td class="px-3 py-3 font-mono text-xs text-zinc-500">{{ $row['pool_winner_count'] }}</td>
                            <td class="px-3 py-3">
                                @if ($isWinner)
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-emerald-950 text-emerald-300 uppercase tracking-wider">sí</span>
                                @else
                                    <span class="text-xs text-zinc-500">no</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($vote->status === \App\Models\MapPoolVote::STATUS_OPEN)
            <p class="text-xs text-zinc-500 mt-2 italic">
                El ranking actual se recomputa cada vez que abrís la página. Al cerrarse la votación queda congelado y se aplica al pool.
            </p>
        @endif
    </section>

    {{-- Acciones --}}
    @if ($vote->status === \App\Models\MapPoolVote::STATUS_OPEN)
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Acciones</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/40 p-4 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('admin.map-votes.apply', $vote->id) }}"
                      onsubmit="return confirm('Aplicar resultados al pool AHORA? Los ganadores actuales se activan, el resto se desactiva.');">
                    @csrf
                    <button type="submit"
                            class="rounded bg-emerald-700 hover:bg-emerald-600 text-white px-4 py-2 text-sm font-semibold transition-colors">
                        Cerrar y aplicar ahora
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.map-votes.cancel', $vote->id) }}"
                      onsubmit="return confirm('Cancelar la votación? El pool actual queda intacto.');">
                    @csrf
                    <button type="submit"
                            class="rounded border border-red-900 text-red-400 hover:bg-red-950 px-4 py-2 text-sm font-semibold transition-colors">
                        Cancelar votación
                    </button>
                </form>
                <p class="text-xs text-zinc-500 self-center">
                    El cron <code class="text-xs px-1 py-0.5 rounded bg-zinc-800 text-accent">map-vote:close-expired</code> aplica automáticamente al pasar <code>ends_at</code>.
                </p>
            </div>
        </section>
    @endif
</div>
@endsection
