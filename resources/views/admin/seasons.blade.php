@extends('layouts.app')

@section('title', 'Admin — Seasons')

@section('content')
<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold flex items-center gap-3">
            Seasons
            <span class="text-xs font-medium px-2 py-0.5 rounded bg-amber-950 text-amber-300 uppercase tracking-wider">Solo admin</span>
        </h1>
        <p class="mt-1 text-sm text-zinc-500">Gestionar temporadas: planificar fecha de cierre, cerrar y abrir siguiente.</p>
    </div>

    <nav class="flex gap-2 text-sm border-b border-zinc-800 pb-3">
        <a href="{{ route('admin.overview') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Overview</a>
        <a href="{{ route('admin.users') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Usuarios</a>
        <a href="{{ route('admin.matches') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Matches</a>
        <a href="{{ route('admin.seasons') }}" class="px-3 py-1.5 rounded bg-zinc-800 text-zinc-100">Seasons</a>
        <a href="{{ route('admin.maps') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Maps</a>
    </nav>

    {{-- Season activa --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Season activa</h2>
        @if ($current)
            <div class="rounded-xl border border-accent/30 bg-gradient-to-br from-accent-dark/30 to-zinc-900/50 p-5 sm:p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <div>
                        <div class="text-lg font-bold">{{ $current->name }}</div>
                        <div class="text-xs text-zinc-500 font-mono">slug: {{ $current->slug }} · started {{ $current->starts_at?->format('Y-m-d') ?? '—' }}</div>
                    </div>
                    <div class="text-right text-xs">
                        @if ($current->ends_at)
                            <div class="font-mono text-accent">termina {{ $current->ends_at->format('Y-m-d') }}</div>
                            <div class="text-zinc-500">{{ now()->diffInDays($current->ends_at, false) > 0 ? 'en ' . (int) ceil(now()->diffInSeconds($current->ends_at, false) / 86400) . ' días' : 'cierre pendiente' }}</div>
                        @else
                            <div class="text-zinc-500">sin fecha de cierre</div>
                        @endif
                    </div>
                </div>

                <div class="mt-4 grid sm:grid-cols-3 gap-3 text-sm">
                    <div class="rounded-lg border border-zinc-800 bg-zinc-950/50 px-3 py-2">
                        <div class="text-xs text-zinc-500 uppercase">Matches completed</div>
                        <div class="font-mono text-lg">{{ $matchCount }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-800 bg-zinc-950/50 px-3 py-2">
                        <div class="text-xs text-zinc-500 uppercase">Users reales</div>
                        <div class="font-mono text-lg">{{ $userCount }}</div>
                    </div>
                    <div class="rounded-lg border border-zinc-800 bg-zinc-950/50 px-3 py-2">
                        <div class="text-xs text-zinc-500 uppercase">ID</div>
                        <div class="font-mono text-lg">#{{ $current->id }}</div>
                    </div>
                </div>

                {{-- Form de fecha de cierre --}}
                <form method="POST" action="{{ route('admin.seasons.ends-at', $current->id) }}"
                      class="mt-5 flex flex-col sm:flex-row sm:items-end gap-2">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Fecha de cierre planificada</label>
                        <input type="date" name="ends_at"
                               value="{{ $current->ends_at?->format('Y-m-d') }}"
                               class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                    </div>
                    <button type="submit"
                            class="rounded border border-zinc-700 bg-zinc-900 px-4 py-1.5 text-sm hover:bg-zinc-800 transition-colors">
                        Guardar
                    </button>
                </form>

                {{-- Toggle del form de cierre --}}
                <div class="mt-6 pt-5 border-t border-zinc-800/60">
                    <details class="group">
                        <summary class="cursor-pointer text-sm font-semibold text-red-400 hover:text-red-300 select-none flex items-center gap-2">
                            <span class="inline-block transition-transform group-open:rotate-90">▶</span>
                            Cerrar season y abrir la siguiente
                        </summary>
                        <div class="mt-4 rounded-lg border border-red-900/50 bg-red-950/20 p-4 sm:p-5">
                            <p class="text-sm text-red-300 font-medium">⚠ Acción destructiva</p>
                            <ul class="mt-2 text-xs text-zinc-400 list-disc pl-5 space-y-1">
                                <li>Snapshotea las {{ $matchCount }} matches completed a season_stats con final_rank</li>
                                <li>Aplica <strong>soft reset</strong> al rating de los {{ $userCount }} users (regresión a la media)</li>
                                <li>Resetea RD a 350 y volatility a 0.06 (Glicko defaults)</li>
                                <li>Crea la nueva season en estado active</li>
                            </ul>

                            <form method="POST" action="{{ route('admin.seasons.close', $current->id) }}"
                                  class="mt-4 space-y-3">
                                @csrf
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Nombre nueva season</label>
                                        <input type="text" name="next_name" required maxlength="60"
                                               placeholder="ej. Pre-season B, Season 1"
                                               class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Slug</label>
                                        <input type="text" name="next_slug" required maxlength="40"
                                               pattern="[a-z0-9-]+" placeholder="ej. pre-b, s1"
                                               class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Fecha de cierre nueva (opcional)</label>
                                        <input type="date" name="next_ends_at"
                                               class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Factor reset</label>
                                            <input type="number" name="factor" value="0.4" step="0.05" min="0" max="1" required
                                                   class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                                            <p class="mt-1 text-xs text-zinc-600">0=reset total, 1=sin reset</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Base</label>
                                            <input type="number" name="base" value="1500" step="50" min="500" max="3000" required
                                                   class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                                        </div>
                                    </div>
                                </div>

                                <label class="flex items-start gap-2 text-sm text-zinc-300 cursor-pointer">
                                    <input type="checkbox" name="confirm" required class="mt-0.5">
                                    <span>Entiendo que esta acción es <strong>irreversible</strong> y que va a modificar el rating de todos los users.</span>
                                </label>

                                <button type="submit"
                                        class="w-full sm:w-auto rounded bg-red-900/40 border border-red-800 px-4 py-2 text-sm font-semibold text-red-300 hover:bg-red-900/60 transition-colors">
                                    Cerrar y abrir siguiente
                                </button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-dashed border-zinc-800 bg-zinc-900/30 p-8 text-center">
                <p class="text-sm text-zinc-400">No hay season activa. Para crear la primera correr <code class="text-xs px-1 py-0.5 rounded bg-zinc-800 text-accent">php artisan seasons:init</code></p>
            </div>
        @endif
    </section>

    {{-- Seasons cerradas --}}
    @if ($closed->count() > 0)
        <section>
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Seasons cerradas</h2>
            <div class="overflow-x-auto rounded-lg border border-zinc-800">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-900/60">
                        <tr class="text-left text-xs uppercase tracking-wider text-zinc-500">
                            <th class="px-3 py-2">ID</th>
                            <th class="px-3 py-2">Nombre</th>
                            <th class="px-3 py-2">Slug</th>
                            <th class="px-3 py-2">Started</th>
                            <th class="px-3 py-2">Closed</th>
                            <th class="px-3 py-2">Reset config</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach ($closed as $s)
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">#{{ $s->id }}</td>
                                <td class="px-3 py-2">{{ $s->name }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-zinc-400">{{ $s->slug }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-zinc-500">{{ $s->starts_at?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-zinc-500">{{ $s->closed_at?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-3 py-2 font-mono text-xs text-zinc-500">
                                    @if ($s->reset_config_json)
                                        factor={{ $s->reset_config_json['factor'] ?? '?' }}, base={{ $s->reset_config_json['base'] ?? '?' }}
                                    @else — @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
