@extends('layouts.app')

@section('title', 'Admin — Votaciones de pool')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold flex items-center gap-3">
            Votaciones de pool
            <span class="text-xs font-medium px-2 py-0.5 rounded bg-amber-950 text-amber-300 uppercase tracking-wider">Solo admin</span>
        </h1>
        <p class="mt-1 text-sm text-zinc-500">
            Pool final = {{ $fixedMaps->count() }} fijos + top-N votados. El cron <code class="text-xs px-1 py-0.5 rounded bg-zinc-800 text-accent">map-vote:close-expired</code> aplica resultados al pasar <code>ends_at</code>.
        </p>
    </div>

    <nav class="flex gap-2 text-sm border-b border-zinc-800 pb-3">
        <a href="{{ route('admin.overview') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Overview</a>
        <a href="{{ route('admin.users') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Usuarios</a>
        <a href="{{ route('admin.matches') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Matches</a>
        <a href="{{ route('admin.seasons') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Seasons</a>
        <a href="{{ route('admin.maps') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Maps</a>
        <a href="{{ route('admin.map-votes') }}" class="px-3 py-1.5 rounded bg-zinc-800 text-zinc-100">Votaciones</a>
        <a href="{{ route('admin.map-categories') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Categorías</a>
    </nav>

    {{-- Lista de votaciones --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Historial</h2>
        <div class="overflow-x-auto rounded-lg border border-zinc-800">
            <table class="w-full text-sm">
                <thead class="bg-zinc-900/60">
                    <tr class="text-left text-xs uppercase tracking-wider text-zinc-500">
                        <th class="px-3 py-3">#</th>
                        <th class="px-3 py-3">Nombre</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="px-3 py-3">Ventana</th>
                        <th class="px-3 py-3">Top-N</th>
                        <th class="px-3 py-3">Aplicada</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($votes as $v)
                        <tr class="hover:bg-zinc-900/40 transition-colors">
                            <td class="px-3 py-3 font-mono text-xs text-zinc-500">#{{ $v->id }}</td>
                            <td class="px-3 py-3 font-medium">{{ $v->name }}</td>
                            <td class="px-3 py-3">
                                @if ($v->status === \App\Models\MapPoolVote::STATUS_OPEN)
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-800/60 uppercase tracking-wider">abierta</span>
                                @elseif ($v->status === \App\Models\MapPoolVote::STATUS_CLOSED)
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-300 border border-zinc-700 uppercase tracking-wider">cerrada</span>
                                @else
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-red-950 text-red-300 border border-red-800/60 uppercase tracking-wider">cancelada</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-xs font-mono text-zinc-400">
                                {{ $v->starts_at->format('Y-m-d H:i') }}<br>
                                <span class="text-zinc-600">→ {{ $v->ends_at->format('Y-m-d H:i') }}</span>
                            </td>
                            <td class="px-3 py-3 font-mono text-xs">{{ $v->pool_size_voted }}</td>
                            <td class="px-3 py-3 text-xs text-zinc-500">
                                {{ $v->applied_at?->format('Y-m-d H:i') ?? '—' }}
                            </td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ route('admin.map-votes.show', $v->id) }}"
                                   class="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800 transition-colors">
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-sm text-zinc-500">
                                Todavía no creaste ninguna votación.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Mapas fijos: snapshot informativo --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">
            Mapas fijos del pool ({{ $fixedMaps->count() }})
        </h2>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/40 p-4">
            <p class="text-sm text-zinc-400 mb-3">
                Estos mapas <strong>siempre están en el pool</strong> y nunca son candidatos a votación.
                Para cambiar este flag, editá el mapa desde <a href="{{ route('admin.maps') }}" class="text-accent hover:underline">Maps</a>.
            </p>
            @if ($fixedMaps->count() > 0)
                <div class="flex flex-wrap gap-2">
                    @foreach ($fixedMaps as $m)
                        <span class="text-xs px-2 py-1 rounded bg-accent-dark text-accent border border-accent/40">
                            {{ $m->name }}
                        </span>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-amber-400 italic">No marcaste ningún mapa como fijo. El pool entero se va a sortear cada votación — riesgoso si querés mantener clásicos como Arabia.</p>
            @endif
        </div>
    </section>

    {{-- Crear nueva votación --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Crear nueva votación</h2>

        @if ($openVote)
            <div class="rounded-lg border border-amber-700/50 bg-amber-950/20 p-4">
                <p class="text-sm text-amber-300 font-medium">⚠ Hay una votación abierta: <strong>{{ $openVote->name }}</strong></p>
                <p class="text-xs text-zinc-400 mt-1">
                    Solo puede haber una a la vez. Esperá su cierre el {{ $openVote->ends_at->format('Y-m-d H:i') }} o
                    <a href="{{ route('admin.map-votes.show', $openVote->id) }}" class="text-accent hover:underline">cancelala</a>
                    antes de crear otra.
                </p>
            </div>
        @else
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/40 p-4 sm:p-5">
                <form method="POST" action="{{ route('admin.map-votes.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Nombre de la votación</label>
                        <input type="text" name="name" required maxlength="80"
                               placeholder="Pool {{ now()->locale('es')->translatedFormat('F Y') }}"
                               class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                    </div>

                    <div class="grid sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Inicio</label>
                            <input type="datetime-local" name="starts_at" required
                                   value="{{ now()->format('Y-m-d\TH:i') }}"
                                   class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Fin</label>
                            <input type="datetime-local" name="ends_at" required
                                   value="{{ now()->addDays(7)->format('Y-m-d\TH:i') }}"
                                   class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Top N (al pool)</label>
                            <input type="number" name="pool_size_voted" required min="1" max="30" value="5"
                                   class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-2">
                            Candidatos
                            <span class="normal-case text-zinc-600">— elegí los mapas que entran a votación. Solo no fijos.</span>
                        </label>

                        @if (count($excludeIds) > 0)
                            <p class="text-xs text-amber-400 mb-2">
                                💡 Los marcados con borde naranja ganaron la votación anterior — idealmente no los repitas.
                                Toggle "permitir repetir" abajo si querés forzarlos.
                            </p>
                        @endif

                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                            @foreach ($availableMaps as $m)
                                @php $wasWinner = in_array($m->id, $excludeIds); @endphp
                                <label class="flex items-center gap-2 p-2 rounded border cursor-pointer transition-colors
                                              {{ $wasWinner ? 'border-amber-700/40 bg-amber-950/10' : 'border-zinc-800 bg-zinc-950 hover:bg-zinc-900' }}">
                                    <input type="checkbox" name="candidate_ids[]" value="{{ $m->id }}"
                                           class="candidate-check"
                                           data-was-winner="{{ $wasWinner ? '1' : '0' }}"
                                           {{ $wasWinner ? 'disabled' : '' }}>
                                    <span class="text-sm">{{ $m->name }}</span>
                                    @if ($wasWinner)
                                        <span class="text-[10px] text-amber-500 ml-auto">ganó</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>

                        @if (count($excludeIds) > 0)
                            <label class="flex items-center gap-2 mt-3 text-sm text-zinc-300 p-2 rounded bg-zinc-950 border border-zinc-800">
                                <input type="checkbox" id="allow-repeats">
                                <span>Permitir mapas repetidos de la votación anterior (caso edge)</span>
                            </label>
                        @endif
                    </div>

                    <div>
                        <button type="submit"
                                class="rounded bg-accent text-accent-dark px-4 py-2 text-sm font-semibold hover:bg-accent-hover transition-colors">
                            Crear votación
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </section>
</div>

@push('scripts')
<script>
    // Toggle "permitir repetidos": habilita los checkboxes de mapas que ganaron
    // la votacion anterior. Por defecto van disabled.
    document.getElementById('allow-repeats')?.addEventListener('change', (e) => {
        const allow = e.target.checked;
        document.querySelectorAll('.candidate-check[data-was-winner="1"]').forEach(c => {
            c.disabled = !allow;
        });
    });
</script>
@endpush
@endsection
