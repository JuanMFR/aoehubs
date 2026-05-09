@extends('layouts.app')

@section('title', 'Votar pool de mapas')

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">
    <div>
        <h1 class="text-2xl font-bold">Votación de pool de mapas</h1>
        <p class="mt-1 text-sm text-zinc-500">
            Tu opinión decide qué mapas entran a la rotación. Pool final = mapas fijos + los más votados.
        </p>
    </div>

    @if (! $vote)
        {{-- Empty state: no hay votacion abierta --}}
        <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-8 text-center">
            <div class="text-5xl mb-3 opacity-30">🗳</div>
            <h2 class="text-lg font-semibold text-zinc-300">No hay votación abierta ahora mismo</h2>
            <p class="mt-2 text-sm text-zinc-500">
                Cuando el admin programe una nueva votación, vas a ver acá los candidatos para opinar.
                Mientras tanto, jugá en el pool actual.
            </p>
            <a href="{{ route('dashboard') }}" class="inline-block mt-4 rounded bg-accent text-accent-dark px-4 py-2 text-sm font-semibold hover:bg-accent-hover transition-colors">
                Volver al dashboard
            </a>
        </div>
    @else
        @php
            $selected = $ballot ? ($ballot->votes_json ?? []) : [];
            $maxVotes = $vote->pool_size_voted;
            $remainingSeconds = max(0, $vote->ends_at->diffInSeconds(now(), absolute: false));
        @endphp

        {{-- Header de la votacion --}}
        <div class="rounded-xl border border-accent/40 bg-gradient-to-br from-accent-dark/20 to-zinc-900/50 p-5">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="text-xl font-bold">{{ $vote->name }}</h2>
                    <p class="mt-1 text-sm text-zinc-400">
                        Cierra {{ $vote->ends_at->diffForHumans() }}
                        <span class="text-zinc-600">· {{ $vote->ends_at->format('Y-m-d H:i') }}</span>
                    </p>
                </div>
                @if ($ballot)
                    <span class="text-xs px-2 py-1 rounded bg-emerald-950 text-emerald-300 border border-emerald-800/60 uppercase tracking-wider">
                        Ya votaste — podés cambiar
                    </span>
                @endif
            </div>
        </div>

        {{-- Form de voto --}}
        <form method="POST" action="{{ route('maps.vote.submit') }}" class="space-y-4">
            @csrf
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5">
                <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                    <h3 class="text-sm font-semibold text-zinc-300">
                        Elegí hasta {{ $maxVotes }} {{ $maxVotes === 1 ? 'mapa' : 'mapas' }}
                    </h3>
                    <div class="text-xs text-zinc-500">
                        Seleccionados: <span id="selected-count" class="font-mono text-accent">{{ count($selected) }}</span> / {{ $maxVotes }}
                    </div>
                </div>

                @error('votes')
                    <div class="mb-3 rounded-lg border border-red-900/50 bg-red-950/20 p-3 text-sm text-red-300">{{ $message }}</div>
                @enderror

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                    @foreach ($vote->candidates->sortBy('name') as $map)
                        @php $isSelected = in_array($map->id, $selected); @endphp
                        <label class="map-vote-item relative flex flex-col items-center gap-2 p-3 rounded-lg border cursor-pointer transition-all
                                      {{ $isSelected ? 'border-accent bg-accent-dark/30' : 'border-zinc-800 bg-zinc-950 hover:bg-zinc-900' }}">
                            <input type="checkbox" name="votes[]" value="{{ $map->id }}"
                                   class="vote-check absolute top-2 right-2"
                                   data-max="{{ $maxVotes }}"
                                   {{ $isSelected ? 'checked' : '' }}>
                            <x-map-icon :name="$map->name" class="h-16 w-20 rounded" />
                            <span class="text-sm text-center">{{ $map->name_es ?? $map->name }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-4 flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-xs text-zinc-500 italic">
                        Tu voto se sobrescribe cada vez que guardás. Mientras la votación esté abierta, podés cambiarlo cuantas veces quieras.
                    </p>
                    <button type="submit" id="submit-vote"
                            class="rounded bg-accent text-accent-dark px-5 py-2 text-sm font-semibold hover:bg-accent-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        @if ($ballot)
                            Actualizar voto
                        @else
                            Guardar voto
                        @endif
                    </button>
                </div>
            </div>
        </form>

        {{-- Mapas fijos (informativo) --}}
        @php
            $fixedMaps = \App\Models\Map::where('is_fixed_in_pool', true)->orderBy('name')->get();
        @endphp
        @if ($fixedMaps->count() > 0)
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/30 p-4">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 mb-3">
                    Mapas fijos del pool ({{ $fixedMaps->count() }})
                </h3>
                <p class="text-xs text-zinc-500 mb-3">
                    Estos mapas siempre están en el pool. No se votan.
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($fixedMaps as $m)
                        <span class="flex items-center gap-1.5 text-xs px-2 py-1 rounded bg-accent-dark text-accent border border-accent/40">
                            <x-map-icon :name="$m->name" class="h-5 w-6 rounded" />
                            {{ $m->name_es ?? $m->name }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>

@push('scripts')
@if ($vote)
<script>
    // Limita la cantidad de checkboxes marcados al pool_size_voted del vote.
    // Si el user marca uno de mas, se le previene y se le explica con un
    // titulo. Tambien actualizamos el contador y la appearance de la card.
    const maxVotes = {{ $maxVotes }};
    const checks   = document.querySelectorAll('.vote-check');
    const counter  = document.getElementById('selected-count');
    const submitBtn = document.getElementById('submit-vote');

    function update() {
        const selected = Array.from(checks).filter(c => c.checked);
        counter.textContent = selected.length;
        submitBtn.disabled = selected.length === 0;

        // Update card visual state
        checks.forEach(c => {
            const card = c.closest('.map-vote-item');
            if (c.checked) {
                card.classList.add('border-accent', 'bg-accent-dark/30');
                card.classList.remove('border-zinc-800', 'bg-zinc-950', 'hover:bg-zinc-900');
            } else {
                card.classList.remove('border-accent', 'bg-accent-dark/30');
                card.classList.add('border-zinc-800', 'bg-zinc-950', 'hover:bg-zinc-900');
            }
        });
    }

    checks.forEach(c => {
        c.addEventListener('change', (e) => {
            const checkedNow = Array.from(checks).filter(x => x.checked).length;
            if (checkedNow > maxVotes) {
                // Prevenir el ultimo. Volvemos al estado previo.
                e.target.checked = false;
                alert('Solo podés elegir hasta ' + maxVotes + ' mapas. Desmarcá uno antes de marcar otro.');
            }
            update();
        });
    });

    update();
</script>
@endif
@endpush
@endsection
