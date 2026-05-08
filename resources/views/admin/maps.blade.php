@extends('layouts.app')

@section('title', 'Admin — Maps')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold flex items-center gap-3">
            Maps
            <span class="text-xs font-medium px-2 py-0.5 rounded bg-amber-950 text-amber-300 uppercase tracking-wider">Solo admin</span>
        </h1>
        <p class="mt-1 text-sm text-zinc-500">
            Pool de mapas para el draft. Activos: {{ $maps->where('is_active')->count() }} ·
            Inactivos: {{ $maps->where('is_active', false)->count() }}.
            Conviene mantener cantidad <strong>impar</strong> de activos.
        </p>
    </div>

    <nav class="flex gap-2 text-sm border-b border-zinc-800 pb-3">
        <a href="{{ route('admin.overview') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Overview</a>
        <a href="{{ route('admin.users') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Usuarios</a>
        <a href="{{ route('admin.matches') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Matches</a>
        <a href="{{ route('admin.seasons') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Seasons</a>
        <a href="{{ route('admin.maps') }}" class="px-3 py-1.5 rounded bg-zinc-800 text-zinc-100">Maps</a>
    </nav>

    {{-- Lista de mapas --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Pool actual</h2>
        <div class="overflow-x-auto rounded-lg border border-zinc-800">
            <table class="w-full text-sm">
                <thead class="bg-zinc-900/60">
                    <tr class="text-left text-xs uppercase tracking-wider text-zinc-500">
                        <th class="px-3 py-3 w-12"></th>
                        <th class="px-3 py-3">Mapa</th>
                        <th class="px-3 py-3">Canonical name</th>
                        <th class="px-3 py-3">RMS ID</th>
                        <th class="px-3 py-3">Sort</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($maps as $m)
                        <tr class="hover:bg-zinc-900/40 transition-colors {{ $m->is_active ? '' : 'opacity-50' }}">
                            <td class="px-3 py-3">
                                <x-map-icon :name="$m->name" class="h-10 w-12 rounded" />
                            </td>
                            <td class="px-3 py-3">
                                <div class="font-medium">{{ __($m->name) }}</div>
                                @if (__($m->name) !== $m->name)
                                    <div class="text-xs text-zinc-500">(traducido vía lang/es.json)</div>
                                @endif
                            </td>
                            <td class="px-3 py-3 font-mono text-xs">{{ $m->name }}</td>
                            <td class="px-3 py-3 font-mono text-xs text-zinc-500">{{ $m->rms_map_id ?? '—' }}</td>
                            <td class="px-3 py-3 font-mono text-xs">{{ $m->sort_order }}</td>
                            <td class="px-3 py-3">
                                @if ($m->is_active)
                                    <span class="badge badge-completed">activo</span>
                                @else
                                    <span class="badge badge-abandoned">inactivo</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.maps.toggle', $m->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800 transition-colors">
                                        {{ $m->is_active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                                <button type="button"
                                        onclick="document.getElementById('edit-map-{{ $m->id }}').showModal()"
                                        class="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800 transition-colors">
                                    Editar
                                </button>
                                <button type="button"
                                        onclick="document.getElementById('delete-map-{{ $m->id }}').showModal()"
                                        class="rounded border border-red-900 px-2 py-1 text-xs text-red-400 hover:bg-red-950 transition-colors">
                                    Eliminar
                                </button>

                                {{-- Edit modal --}}
                                <dialog id="edit-map-{{ $m->id }}" class="rounded-xl bg-zinc-900 border border-zinc-800 backdrop:bg-black/70 max-w-md w-[90%] p-0 text-zinc-100 m-auto text-left">
                                    <form method="POST" action="{{ route('admin.maps.update', $m->id) }}" class="p-5">
                                        @csrf
                                        @method('PATCH')
                                        <h3 class="text-lg font-bold mb-4">Editar mapa</h3>

                                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Canonical name</label>
                                        <input type="text" name="name" value="{{ $m->name }}" required maxlength="60"
                                               class="w-full mb-3 rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">

                                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Icon path</label>
                                        <input type="text" name="icon_path" value="{{ $m->icon_path }}" placeholder="maps/black_forest.png"
                                               class="w-full mb-3 rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">

                                        <div class="grid grid-cols-2 gap-3 mb-4">
                                            <div>
                                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">RMS Map ID</label>
                                                <input type="number" name="rms_map_id" value="{{ $m->rms_map_id }}" min="0"
                                                       class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Sort order</label>
                                                <input type="number" name="sort_order" value="{{ $m->sort_order }}"
                                                       class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                                            </div>
                                        </div>

                                        <div class="flex justify-end gap-2">
                                            <button type="button" onclick="this.closest('dialog').close()"
                                                    class="rounded border border-zinc-700 bg-zinc-800 px-4 py-2 text-sm">Cancelar</button>
                                            <button type="submit"
                                                    class="rounded bg-accent text-accent-dark px-4 py-2 text-sm font-semibold">Guardar</button>
                                        </div>
                                    </form>
                                </dialog>

                                {{-- Delete modal --}}
                                <x-confirm-modal id="delete-map-{{ $m->id }}"
                                                 title="¿Eliminar mapa #{{ $m->id }}?"
                                                 :action="route('admin.maps.destroy', $m->id)"
                                                 method="DELETE"
                                                 confirmLabel="Sí, eliminar"
                                                 :danger="true">
                                    <p>Vas a eliminar <strong>{{ $m->name }}</strong> del pool definitivamente.</p>
                                    <p class="text-xs text-zinc-500">Si solo querés sacarlo del draft temporalmente, mejor usá "Desactivar".</p>
                                </x-confirm-modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-sm text-zinc-500">
                                Sin mapas. Corré <code class="text-xs px-1 py-0.5 rounded bg-zinc-800 text-accent">php artisan maps:seed</code>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Agregar mapa desde replay (auto-detect canonical) --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Agregar mapa desde un replay</h2>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/40 p-4 sm:p-5">
            <p class="text-sm text-zinc-400 mb-4">
                Subí un <code class="text-xs px-1 rounded bg-zinc-800">.aoe2record</code> jugado en el mapa nuevo.
                El parser extrae el canonical name + rms_map_id automáticamente y pre-puebla el form.
            </p>
            <div class="flex flex-col sm:flex-row gap-2 items-start">
                <input type="file" id="replay-upload" accept=".aoe2record"
                       class="flex-1 text-sm text-zinc-300 file:mr-3 file:rounded file:border-0 file:bg-accent-dark file:text-accent file:px-3 file:py-1.5 file:font-semibold file:cursor-pointer">
                <button type="button" id="extract-btn"
                        class="rounded bg-accent text-accent-dark px-4 py-1.5 text-sm font-semibold hover:bg-accent-hover transition-colors disabled:opacity-60 disabled:cursor-wait">
                    Extraer metadata
                </button>
            </div>
            <div id="extract-result" class="mt-4 hidden"></div>
        </div>
    </section>

    {{-- Agregar mapa manual --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Agregar mapa manualmente</h2>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/40 p-4 sm:p-5">
            <p class="text-sm text-zinc-400 mb-4">
                Para mapas conocidos donde sabés el canonical name (ej. de
                <a href="https://github.com/SiegeEngineers/aoc-reference-data/blob/master/data/datasets/100.json" target="_blank" rel="noopener" class="text-accent hover:underline">aocref</a>).
                Para mapas nuevos donde no sabés el canonical, usá el upload de replay (Fase 3).
            </p>
            <form method="POST" action="{{ route('admin.maps.store') }}" class="grid sm:grid-cols-2 gap-3">
                @csrf
                <div class="sm:col-span-2">
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Canonical name (igual a lo que devuelve el parser)</label>
                    <input type="text" name="name" required maxlength="60"
                           placeholder="Black Forest"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Icon path (opcional)</label>
                    <input type="text" name="icon_path" placeholder="maps/black_forest.png"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">RMS Map ID (opcional)</label>
                    <input type="number" name="rms_map_id" min="0"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Sort order</label>
                    <input type="number" name="sort_order" value="999"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm text-zinc-300">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span>Activar inmediatamente</span>
                    </label>
                </div>
                <div class="sm:col-span-2">
                    <button type="submit"
                            class="rounded bg-accent text-accent-dark px-4 py-2 text-sm font-semibold hover:bg-accent-hover transition-colors">
                        Agregar al pool
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>

@push('scripts')
<script>
    document.getElementById('extract-btn')?.addEventListener('click', async () => {
        const fileInput = document.getElementById('replay-upload');
        const resultEl  = document.getElementById('extract-result');
        const btn       = document.getElementById('extract-btn');

        if (!fileInput.files.length) {
            resultEl.classList.remove('hidden');
            resultEl.innerHTML = '<div class="rounded-lg border border-amber-900/50 bg-amber-950/20 p-3 text-sm text-amber-300">Subí un .aoe2record primero.</div>';
            return;
        }

        const formData = new FormData();
        formData.append('replay', fileInput.files[0]);
        formData.append('_token', '{{ csrf_token() }}');

        btn.disabled = true;
        btn.textContent = 'Parseando...';
        resultEl.classList.remove('hidden');
        resultEl.innerHTML = '<div class="text-sm text-zinc-400">Procesando replay (puede tardar 5-10s)...</div>';

        try {
            const r = await fetch('{{ route('admin.maps.extract-replay') }}', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            });
            const data = await r.json();

            if (!data.ok) {
                resultEl.innerHTML = `<div class="rounded-lg border border-red-900/50 bg-red-950/20 p-3 text-sm text-red-300">${data.error}</div>`;
                return;
            }

            const exists = data.already_exists
                ? '<div class="text-xs text-amber-400 mt-2">⚠ Ya existe un mapa con este canonical name. Usá la sección de edición arriba.</div>'
                : '';

            // Caso "partial": el parser conoce el rms_map_id pero no el nombre
            // (mapa nuevo no incluido en DE_MAP_NAMES de mgz-fast). El admin
            // ingresa el canonical a mano y queda asociado al rms_map_id.
            if (data.partial) {
                resultEl.innerHTML = `
                    <div class="rounded-lg border border-amber-700/50 bg-amber-950/20 p-4">
                        <div class="text-sm text-amber-300 font-medium mb-3">⚠ Parser parcial — mapa no reconocido</div>
                        <p class="text-xs text-zinc-400 mb-3">${data.partial_message}</p>
                        <div class="grid grid-cols-2 gap-2 text-xs font-mono mb-3">
                            <div><span class="text-zinc-500">rms_map_id:</span> <span class="text-amber-300">${data.rms_map_id}</span></div>
                            <div><span class="text-zinc-500">rms_filename:</span> <span class="text-zinc-400">${data.rms_filename ?? '—'}</span></div>
                        </div>
                        <form method="POST" action="{{ route('admin.maps.store') }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="rms_map_id" value="${data.rms_map_id}">
                            <input type="hidden" name="sort_order" value="999">
                            <input type="hidden" name="is_active" value="1">
                            <div>
                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Canonical name (ingresá a mano)</label>
                                <input type="text" name="name" required maxlength="60"
                                    placeholder="ej. Dust Storm"
                                    class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                                <p class="mt-1 text-xs text-zinc-500">Es lo que devuelve el parser cuando mgz-fast lo soporte. Buscá en aocref/aoe2techtree el nombre EN.</p>
                            </div>
                            <div>
                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Icon path (subí el png a public/images/maps/ después)</label>
                                <input type="text" name="icon_path" placeholder="maps/dust_storm.png"
                                    class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                            </div>
                            <button type="submit"
                                class="rounded bg-accent text-accent-dark px-4 py-2 text-sm font-semibold hover:bg-accent-hover transition-colors">
                                Crear mapa con estos datos
                            </button>
                        </form>
                    </div>
                `;
                return;
            }

            // Caso normal: parser reconoció todo.
            resultEl.innerHTML = `
                <div class="rounded-lg border border-emerald-700/50 bg-emerald-950/20 p-4">
                    <div class="text-sm text-emerald-300 font-medium mb-3">✓ Metadata extraída</div>
                    <div class="grid grid-cols-2 gap-2 text-xs font-mono mb-3">
                        <div><span class="text-zinc-500">map_name:</span> <span class="text-emerald-300">${data.map_name}</span></div>
                        <div><span class="text-zinc-500">rms_map_id:</span> <span class="text-emerald-300">${data.rms_map_id ?? '—'}</span></div>
                        <div><span class="text-zinc-500">rms_filename:</span> <span class="text-zinc-400">${data.rms_filename ?? '—'}</span></div>
                        <div><span class="text-zinc-500">icon_path sugerido:</span> <span class="text-zinc-400">${data.icon_path}</span></div>
                    </div>
                    ${exists}
                    <form method="POST" action="{{ route('admin.maps.store') }}" class="mt-3 flex flex-wrap gap-2">
                        @csrf
                        <input type="hidden" name="name" value="${data.map_name}">
                        <input type="hidden" name="rms_map_id" value="${data.rms_map_id ?? ''}">
                        <input type="hidden" name="icon_path" value="${data.icon_path}">
                        <input type="hidden" name="sort_order" value="999">
                        <input type="hidden" name="is_active" value="1">
                        <button type="submit" ${data.already_exists ? 'disabled' : ''}
                                class="rounded bg-accent text-accent-dark px-4 py-2 text-sm font-semibold hover:bg-accent-hover transition-colors disabled:opacity-60">
                            Crear mapa con estos datos
                        </button>
                        <span class="text-xs text-zinc-500 self-center">Asegurate de subir el icono a public/images/${data.icon_path} después</span>
                    </form>
                </div>
            `;
        } catch (e) {
            resultEl.innerHTML = `<div class="rounded-lg border border-red-900/50 bg-red-950/20 p-3 text-sm text-red-300">Error de red: ${e.message}</div>`;
        } finally {
            btn.disabled = false;
            btn.textContent = 'Extraer metadata';
        }
    });
</script>
@endpush
@endsection
