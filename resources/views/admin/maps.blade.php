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

    @if (isset($incomplete) && $incomplete->count() > 0)
        <div class="rounded-lg border border-amber-700/50 bg-amber-950/20 p-4">
            <div class="text-sm font-semibold text-amber-300 mb-1">
                {{ $incomplete->count() }} {{ $incomplete->count() === 1 ? 'mapa tiene' : 'mapas tienen' }} fingerprint incompleto
            </div>
            <p class="text-xs text-zinc-400 mb-2">
                Estos mapas validan por nombre (legacy). Para una validacion bulletproof contra el rec,
                completá el fingerprint subiendo un replay del mapa o editando manualmente.
            </p>
            <div class="text-xs text-zinc-500 font-mono">
                @foreach ($incomplete as $m)
                    <span class="inline-block mr-3">{{ $m->name }} ({{ $m->is_custom ? 'falta rms_filename' : 'falta rms_map_id' }})</span>
                @endforeach
            </div>
        </div>
    @endif

    <nav class="flex gap-2 text-sm border-b border-zinc-800 pb-3">
        <a href="{{ route('admin.overview') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Overview</a>
        <a href="{{ route('admin.users') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Usuarios</a>
        <a href="{{ route('admin.matches') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Matches</a>
        <a href="{{ route('admin.seasons') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Seasons</a>
        <a href="{{ route('admin.maps') }}" class="px-3 py-1.5 rounded bg-zinc-800 text-zinc-100">Maps</a>
        <a href="{{ route('admin.map-votes') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Votaciones</a>
        <a href="{{ route('admin.map-categories') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Categorías</a>
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
                        <th class="px-3 py-3">Canonical</th>
                        <th class="px-3 py-3">Tipo</th>
                        <th class="px-3 py-3">Fingerprint</th>
                        <th class="px-3 py-3">Categorías</th>
                        <th class="px-3 py-3">Fijo</th>
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
                            <td class="px-3 py-3">
                                @if ($m->is_custom)
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-purple-950 text-purple-300 border border-purple-800/60 uppercase tracking-wider">custom</span>
                                @else
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-400 border border-zinc-700 uppercase tracking-wider">vanilla</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 font-mono text-xs text-zinc-500">
                                @if ($m->is_custom)
                                    <div title="Para custom validamos por nombre de archivo">{{ $m->rms_filename ?? '—' }}</div>
                                    @if ($m->rms_hash)
                                        <div class="text-[10px] text-zinc-600">sha256: {{ substr($m->rms_hash, 0, 12) }}…</div>
                                    @endif
                                @else
                                    <div title="Para vanilla validamos por rms_map_id">id={{ $m->rms_map_id ?? '—' }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                @if ($m->categories->count() > 0)
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($m->categories as $cat)
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-300 border border-zinc-700">{{ $cat->name }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs text-zinc-600">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                @if ($m->is_fixed_in_pool)
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-accent-dark text-accent border border-accent/40 uppercase tracking-wider"
                                          title="Siempre activo, nunca candidato a votación">fijo</span>
                                @else
                                    <span class="text-xs text-zinc-600">—</span>
                                @endif
                            </td>
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
                                <dialog id="edit-map-{{ $m->id }}" class="rounded-xl bg-zinc-900 border border-zinc-800 backdrop:bg-black/70 max-w-lg w-[90%] p-0 text-zinc-100 m-auto text-left">
                                    <form method="POST" action="{{ route('admin.maps.update', $m->id) }}" class="p-5">
                                        @csrf
                                        @method('PATCH')
                                        <h3 class="text-lg font-bold mb-4">Editar mapa</h3>

                                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Canonical name (parser EN)</label>
                                        <input type="text" name="name" value="{{ $m->name }}" required maxlength="60"
                                               class="w-full mb-3 rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">

                                        <div class="grid grid-cols-2 gap-3 mb-3">
                                            <div>
                                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Display ES</label>
                                                <input type="text" name="name_es" value="{{ $m->name_es }}" maxlength="60" placeholder="Selva Negra"
                                                       class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Display EN</label>
                                                <input type="text" name="name_en" value="{{ $m->name_en }}" maxlength="60" placeholder="Black Forest"
                                                       class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                                            </div>
                                        </div>

                                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Icon path</label>
                                        <input type="text" name="icon_path" value="{{ $m->icon_path }}" placeholder="maps/black_forest.png"
                                               class="w-full mb-3 rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">

                                        <label class="flex items-center gap-2 text-sm mb-2 p-2 rounded bg-zinc-950 border border-zinc-800">
                                            <input type="hidden" name="is_fixed_in_pool" value="0">
                                            <input type="checkbox" name="is_fixed_in_pool" value="1" {{ $m->is_fixed_in_pool ? 'checked' : '' }}>
                                            <span>Mapa fijo del pool (siempre activo, nunca a votación)</span>
                                        </label>

                                        <label class="flex items-center gap-2 text-sm mb-3 p-2 rounded bg-zinc-950 border border-zinc-800">
                                            <input type="hidden" name="is_custom" value="0">
                                            <input type="checkbox" name="is_custom" value="1" {{ $m->is_custom ? 'checked' : '' }}>
                                            <span>Mapa custom (de un pack distribuido por la plataforma)</span>
                                        </label>

                                        <div class="grid grid-cols-2 gap-3 mb-3">
                                            <div>
                                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">
                                                    RMS Map ID <span class="normal-case text-zinc-600">(vanilla)</span>
                                                </label>
                                                <input type="number" name="rms_map_id" value="{{ $m->rms_map_id }}" min="0"
                                                       class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Sort order</label>
                                                <input type="number" name="sort_order" value="{{ $m->sort_order }}"
                                                       class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                                            </div>
                                        </div>

                                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">
                                            RMS filename <span class="normal-case text-zinc-600">(custom — ej. AOEHUBS_PRO_ARABIA.rms)</span>
                                        </label>
                                        <input type="text" name="rms_filename" value="{{ $m->rms_filename }}" maxlength="120" placeholder="AOEHUBS_PRO_ARABIA.rms"
                                               class="w-full mb-3 rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">

                                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">
                                            RMS sha256 hash <span class="normal-case text-zinc-600">(opcional, custom)</span>
                                        </label>
                                        <input type="text" name="rms_hash" value="{{ $m->rms_hash }}" maxlength="64" pattern="[0-9a-fA-F]{64}" placeholder="64 chars hex"
                                               class="w-full mb-3 rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-xs font-mono focus:border-accent focus:outline-none">

                                        @if ($allCategories->count() > 0)
                                            @php $assignedIds = $m->categories->pluck('id')->all(); @endphp
                                            <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">
                                                Categorías <span class="normal-case text-zinc-600">(ladders por tipo de mapa)</span>
                                            </label>
                                            <div class="grid grid-cols-2 gap-1.5 mb-4 p-2 rounded border border-zinc-800 bg-zinc-950">
                                                @foreach ($allCategories as $cat)
                                                    <label class="flex items-center gap-2 text-sm">
                                                        <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}"
                                                               {{ in_array($cat->id, $assignedIds) ? 'checked' : '' }}>
                                                        <span>{{ $cat->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif

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
                            <td colspan="10" class="px-3 py-8 text-center text-sm text-zinc-500">
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
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Canonical name (parser EN)</label>
                    <input type="text" name="name" required maxlength="60"
                           placeholder="Black Forest"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Display ES (opcional)</label>
                    <input type="text" name="name_es" maxlength="60" placeholder="Selva Negra"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Display EN (opcional)</label>
                    <input type="text" name="name_en" maxlength="60" placeholder="Black Forest"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Icon path</label>
                    <input type="text" name="icon_path" placeholder="maps/black_forest.png"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                </div>
                <div class="sm:col-span-2 space-y-2">
                    <label class="flex items-center gap-2 text-sm p-2 rounded bg-zinc-950 border border-zinc-800">
                        <input type="hidden" name="is_fixed_in_pool" value="0">
                        <input type="checkbox" name="is_fixed_in_pool" value="1">
                        <span>Mapa fijo del pool (siempre activo, nunca a votación)</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm p-2 rounded bg-zinc-950 border border-zinc-800">
                        <input type="hidden" name="is_custom" value="0">
                        <input type="checkbox" name="is_custom" value="1">
                        <span>Mapa custom (pack distribuido por la plataforma)</span>
                    </label>
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">RMS Map ID <span class="normal-case text-zinc-600">(vanilla)</span></label>
                    <input type="number" name="rms_map_id" min="0"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Sort order</label>
                    <input type="number" name="sort_order" value="999"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">RMS filename <span class="normal-case text-zinc-600">(custom)</span></label>
                    <input type="text" name="rms_filename" maxlength="120" placeholder="AOEHUBS_PRO_ARABIA.rms"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">RMS sha256 hash <span class="normal-case text-zinc-600">(opcional, custom)</span></label>
                    <input type="text" name="rms_hash" maxlength="64" pattern="[0-9a-fA-F]{64}" placeholder="64 chars hex"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-xs font-mono focus:border-accent focus:outline-none">
                </div>
                @if ($allCategories->count() > 0)
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">
                            Categorías <span class="normal-case text-zinc-600">(opcional — ladders por tipo)</span>
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5 p-2 rounded border border-zinc-800 bg-zinc-950">
                            @foreach ($allCategories as $cat)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}">
                                    <span>{{ $cat->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
                <div class="sm:col-span-2 flex items-end">
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

            // Helper: escape para evitar XSS si rms_filename tuviera caracteres raros.
            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

            const isCustomDefault = data.suggest_is_custom ? 'checked' : '';

            // Caso "partial": el parser conoce el rms_map_id pero no el nombre
            // (mapa nuevo no incluido en DE_MAP_NAMES de mgz-fast, o mapa
            // custom de un pack que mgz no conoce). Admin completa nombre +
            // confirma si es custom o vanilla.
            if (data.partial) {
                resultEl.innerHTML = `
                    <div class="rounded-lg border border-amber-700/50 bg-amber-950/20 p-4">
                        <div class="text-sm text-amber-300 font-medium mb-3">⚠ Parser parcial — mapa no reconocido por mgz</div>
                        <p class="text-xs text-zinc-400 mb-3">${data.partial_message}</p>
                        <div class="grid grid-cols-2 gap-2 text-xs font-mono mb-3">
                            <div><span class="text-zinc-500">rms_map_id:</span> <span class="text-amber-300">${esc(data.rms_map_id)}</span></div>
                            <div><span class="text-zinc-500">rms_filename:</span> <span class="text-zinc-400">${esc(data.rms_filename) || '—'}</span></div>
                        </div>
                        <form method="POST" action="{{ route('admin.maps.store') }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="rms_map_id" value="${esc(data.rms_map_id)}">
                            <input type="hidden" name="rms_filename" value="${esc(data.rms_filename)}">
                            <input type="hidden" name="sort_order" value="999">
                            <input type="hidden" name="is_active" value="1">
                            <div>
                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Canonical name (ingresá a mano)</label>
                                <input type="text" name="name" required maxlength="60" placeholder="ej. Dust Storm"
                                    class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Display ES</label>
                                    <input type="text" name="name_es" maxlength="60" placeholder="Tormenta de Polvo"
                                        class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Display EN</label>
                                    <input type="text" name="name_en" maxlength="60" placeholder="Dust Storm"
                                        class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                                </div>
                            </div>
                            <label class="flex items-center gap-2 text-sm p-2 rounded bg-zinc-950 border border-zinc-800">
                                <input type="hidden" name="is_custom" value="0">
                                <input type="checkbox" name="is_custom" value="1" ${isCustomDefault}>
                                <span>Mapa custom (validar por rms_filename, no por rms_map_id)</span>
                            </label>
                            <div>
                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Icon path</label>
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

            // Caso normal: parser reconoció todo (mapa vanilla conocido por mgz).
            // Si ya existe un mapa con ese name → form de UPDATE al existente
            // (solo actualiza el fingerprint, conserva name_es/en/icon/etc).
            // Sino → form de CREATE normal.
            const datablock = `
                <div class="grid grid-cols-2 gap-2 text-xs font-mono mb-3">
                    <div><span class="text-zinc-500">map_name:</span> <span class="text-emerald-300">${esc(data.map_name)}</span></div>
                    <div><span class="text-zinc-500">rms_map_id:</span> <span class="text-emerald-300">${esc(data.rms_map_id) || '—'}</span></div>
                    <div><span class="text-zinc-500">rms_filename:</span> <span class="text-zinc-400">${esc(data.rms_filename) || '—'}</span></div>
                    <div><span class="text-zinc-500">icon_path sugerido:</span> <span class="text-zinc-400">${esc(data.icon_path)}</span></div>
                </div>`;

            if (data.already_exists) {
                // PATCH al mapa existente — solo cambiamos el fingerprint.
                // No tocamos name_es/name_en/icon_path/sort_order/is_active/
                // is_fixed_in_pool: conservamos lo que el admin ya configuró.
                const updateAction = '{{ url('admin/maps') }}/' + data.existing_map_id;
                resultEl.innerHTML = `
                    <div class="rounded-lg border border-emerald-700/50 bg-emerald-950/20 p-4">
                        <div class="text-sm text-emerald-300 font-medium mb-3">✓ Metadata extraída</div>
                        ${datablock}
                        <div class="rounded-lg border border-amber-700/40 bg-amber-950/20 p-3 text-sm text-amber-300 mb-3">
                            ⚠ Ya existe <strong>${esc(data.existing_map_name)}</strong> en el pool. Vas a actualizar SU fingerprint (rms_map_id + rms_filename) con los datos del replay. No se tocan el nombre, traducciones, ícono ni demás flags.
                        </div>
                        <form method="POST" action="${updateAction}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="_method" value="PATCH">
                            <input type="hidden" name="name" value="${esc(data.existing_map_name)}">
                            <input type="hidden" name="rms_map_id" value="${esc(data.rms_map_id) || ''}">
                            <input type="hidden" name="rms_filename" value="${esc(data.rms_filename)}">
                            <input type="hidden" name="is_custom" value="0">
                            <button type="submit"
                                    class="rounded bg-accent text-accent-dark px-4 py-2 text-sm font-semibold hover:bg-accent-hover transition-colors">
                                Actualizar fingerprint de "${esc(data.existing_map_name)}"
                            </button>
                        </form>
                    </div>
                `;
            } else {
                resultEl.innerHTML = `
                    <div class="rounded-lg border border-emerald-700/50 bg-emerald-950/20 p-4">
                        <div class="text-sm text-emerald-300 font-medium mb-3">✓ Metadata extraída</div>
                        ${datablock}
                        <form method="POST" action="{{ route('admin.maps.store') }}" class="mt-3 space-y-3">
                            @csrf
                            <input type="hidden" name="name" value="${esc(data.map_name)}">
                            <input type="hidden" name="rms_map_id" value="${esc(data.rms_map_id) || ''}">
                            <input type="hidden" name="rms_filename" value="${esc(data.rms_filename)}">
                            <input type="hidden" name="icon_path" value="${esc(data.icon_path)}">
                            <input type="hidden" name="sort_order" value="999">
                            <input type="hidden" name="is_active" value="1">
                            <input type="hidden" name="is_custom" value="0">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Display ES (opcional)</label>
                                    <input type="text" name="name_es" maxlength="60" placeholder="ej. Selva Negra"
                                        class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Display EN (opcional)</label>
                                    <input type="text" name="name_en" maxlength="60" value="${esc(data.map_name)}"
                                        class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                                </div>
                            </div>
                            <button type="submit"
                                    class="rounded bg-accent text-accent-dark px-4 py-2 text-sm font-semibold hover:bg-accent-hover transition-colors">
                                Crear mapa con estos datos
                            </button>
                            <p class="text-xs text-zinc-500">Acordate de subir el icono a <code>public/images/${esc(data.icon_path)}</code>.</p>
                        </form>
                    </div>
                `;
            }
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
