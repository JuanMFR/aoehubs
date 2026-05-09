{{--
    Modal de votacion de pool de mapas. Se incluye desde dashboard.blade.php
    cuando hay $openVote.

    Vars esperadas:
      $openVote         — MapPoolVote (con candidates)
      $userBallot       — MapPoolVoteBallot|null (el voto previo del user)
      $voteTally        — Collection [{map, votes, pool_winner_count}]
      $voteTotalBallots — int (cantidad total de users que ya votaron)
--}}
@php
    $selected   = $userBallot ? ($userBallot->votes_json ?? []) : [];
    $maxVotes   = $openVote->pool_size_voted;
    // Para lookup rapido del count de un map dentro del loop:
    $tallyByMap = collect($voteTally)->keyBy(fn ($r) => $r['map']->id);
@endphp

<dialog id="vote-modal" class="rounded-xl border border-zinc-800 bg-zinc-900 backdrop:bg-black/70 max-w-3xl w-[95%] p-0 text-zinc-100 m-auto text-left">
    <form method="POST" action="{{ route('maps.vote.submit') }}" class="flex flex-col max-h-[90vh]">
        @csrf

        {{-- Header sticky --}}
        <div class="flex items-start justify-between gap-3 p-5 border-b border-zinc-800">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-lg sm:text-xl font-bold truncate">{{ $openVote->name }}</h2>
                    @if ($userBallot)
                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-800/60 uppercase tracking-wider">
                            Ya votaste
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-xs text-zinc-500">
                    Cierra {{ $openVote->ends_at->diffForHumans() }} ·
                    {{ $voteTotalBallots }} {{ $voteTotalBallots === 1 ? 'voto' : 'votos' }} hasta ahora
                </p>
            </div>
            <button type="button" onclick="this.closest('dialog').close()"
                    class="text-zinc-400 hover:text-zinc-100 text-2xl leading-none shrink-0"
                    aria-label="Cerrar">×</button>
        </div>

        {{-- Body scroll-able --}}
        <div class="flex-1 overflow-y-auto p-5">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <p class="text-sm text-zinc-300">
                    Elegí hasta <strong>{{ $maxVotes }}</strong> {{ $maxVotes === 1 ? 'mapa' : 'mapas' }}
                </p>
                <div class="text-xs text-zinc-500">
                    Seleccionados: <span id="vote-modal-count" class="font-mono text-accent">{{ count($selected) }}</span> / {{ $maxVotes }}
                </div>
            </div>

            @error('votes')
                <div class="mb-3 rounded-lg border border-red-900/50 bg-red-950/20 p-3 text-sm text-red-300">{{ $message }}</div>
            @enderror

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                @foreach ($openVote->candidates->sortBy('name') as $map)
                    @php
                        $isSelected = in_array($map->id, $selected);
                        $voteCount  = (int) ($tallyByMap[$map->id]['votes'] ?? 0);
                        $pct        = $voteTotalBallots > 0 ? round($voteCount / $voteTotalBallots * 100) : 0;
                        // Tint alpha = pct/100 * 0.30 cap. Asi 0% queda transparente
                        // y 100% queda con un dorado tenue, sin opacar el contenido.
                        $tintAlpha = round($pct / 100 * 0.30, 3);
                    @endphp
                    {{-- has-[:checked]: cambia border + intensidad de fondo cuando el
                         checkbox interno esta marcado. CSS-only para el highlight. --}}
                    <label class="vote-card relative flex flex-col items-center gap-1.5 p-3 rounded-lg border cursor-pointer overflow-hidden
                                  bg-zinc-950 border-zinc-800 transition-colors
                                  hover:bg-zinc-900/80
                                  has-[:checked]:border-accent has-[:checked]:bg-accent-dark/30">

                        {{-- Tint de fondo proporcional al % de votos. Capa absoluta
                             debajo del contenido, color accent (#D4AF37) con alpha
                             escalada al porcentaje. Mas votos = fondo mas dorado. --}}
                        @if ($pct > 0)
                            <span class="absolute inset-0 pointer-events-none"
                                  style="background-color: rgba(212, 175, 55, {{ $tintAlpha }})"
                                  aria-hidden="true"></span>
                        @endif

                        {{-- Checkbox visualmente oculto pero accesible por teclado +
                             screen readers. Inline style para garantizar que NO se
                             vea, independiente del build de Tailwind. El click se
                             propaga via el <label> que lo wrappea. --}}
                        <input type="checkbox" name="votes[]" value="{{ $map->id }}"
                               class="vote-check"
                               style="position:absolute;opacity:0;width:0;height:0;margin:0;padding:0;"
                               data-max="{{ $maxVotes }}"
                               {{ $isSelected ? 'checked' : '' }}>

                        <x-map-icon :name="$map->name" class="relative h-14 w-16 sm:h-16 sm:w-20 rounded mt-1" />
                        <div class="relative text-xs sm:text-sm text-center font-medium leading-tight">
                            <span>{{ $map->name_es ?? $map->name }}</span>
                            <span class="text-zinc-600 font-mono ml-1"
                                  title="{{ $voteCount }} {{ $voteCount === 1 ? 'voto' : 'votos' }} ({{ $pct }}%)">{{ $voteCount }}</span>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Footer sticky --}}
        <div class="flex items-center justify-between gap-3 p-5 border-t border-zinc-800 flex-wrap">
            <p class="text-xs text-zinc-500 italic flex-1 min-w-0">
                Tu voto se sobrescribe cada vez que guardás. Podés cambiarlo cuantas veces quieras hasta el cierre.
            </p>
            <button type="submit" id="vote-modal-submit"
                    class="rounded bg-accent text-accent-dark px-5 py-2 text-sm font-semibold hover:bg-accent-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed shrink-0">
                {{ $userBallot ? 'Actualizar voto' : 'Guardar voto' }}
            </button>
        </div>
    </form>
</dialog>
