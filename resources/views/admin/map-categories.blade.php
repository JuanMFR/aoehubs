@extends('layouts.app')

@section('title', 'Admin — Categorías de mapas')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold flex items-center gap-3">
            Categorías de mapas
            <span class="text-xs font-medium px-2 py-0.5 rounded bg-amber-950 text-amber-300 uppercase tracking-wider">Solo admin</span>
        </h1>
        <p class="mt-1 text-sm text-zinc-500">
            Cada categoría es una leaderboard adicional (ej. "Cerrados", "Agua"). Cuando un user gana
            un match en un mapa que pertenece a la categoría, se actualiza tanto su rating global como
            el rating de esa categoría — Glicko-2 independiente.
        </p>
    </div>

    <nav class="flex gap-2 text-sm border-b border-zinc-800 pb-3">
        <a href="{{ route('admin.overview') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Overview</a>
        <a href="{{ route('admin.users') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Usuarios</a>
        <a href="{{ route('admin.matches') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Matches</a>
        <a href="{{ route('admin.seasons') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Seasons</a>
        <a href="{{ route('admin.maps') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Maps</a>
        <a href="{{ route('admin.map-votes') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Votaciones</a>
        <a href="{{ route('admin.map-categories') }}" class="px-3 py-1.5 rounded bg-zinc-800 text-zinc-100">Categorías</a>
    </nav>

    {{-- Lista --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Categorías existentes</h2>
        <div class="overflow-x-auto rounded-lg border border-zinc-800">
            <table class="w-full text-sm">
                <thead class="bg-zinc-900/60">
                    <tr class="text-left text-xs uppercase tracking-wider text-zinc-500">
                        <th class="px-3 py-3">Nombre</th>
                        <th class="px-3 py-3">Slug</th>
                        <th class="px-3 py-3">Mapas</th>
                        <th class="px-3 py-3">Sort</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($categories as $c)
                        <tr class="hover:bg-zinc-900/40 {{ $c->is_active ? '' : 'opacity-50' }}">
                            <td class="px-3 py-3">
                                <div class="font-medium">{{ $c->name }}</div>
                                @if ($c->description)
                                    <div class="text-xs text-zinc-500 mt-0.5">{{ $c->description }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-3 font-mono text-xs text-zinc-400">{{ $c->slug }}</td>
                            <td class="px-3 py-3 font-mono text-xs">{{ $c->maps_count }}</td>
                            <td class="px-3 py-3 font-mono text-xs">{{ $c->sort_order }}</td>
                            <td class="px-3 py-3">
                                @if ($c->is_active)
                                    <span class="badge badge-completed">activa</span>
                                @else
                                    <span class="badge badge-abandoned">inactiva</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.map-categories.toggle', $c->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800 transition-colors">
                                        {{ $c->is_active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                                <button type="button"
                                        onclick="document.getElementById('edit-cat-{{ $c->id }}').showModal()"
                                        class="rounded border border-zinc-700 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800 transition-colors">
                                    Editar
                                </button>
                                <button type="button"
                                        onclick="document.getElementById('delete-cat-{{ $c->id }}').showModal()"
                                        class="rounded border border-red-900 px-2 py-1 text-xs text-red-400 hover:bg-red-950 transition-colors">
                                    Eliminar
                                </button>

                                {{-- Edit modal --}}
                                <dialog id="edit-cat-{{ $c->id }}" class="rounded-xl bg-zinc-900 border border-zinc-800 backdrop:bg-black/70 max-w-md w-[90%] p-0 text-zinc-100 m-auto text-left">
                                    <form method="POST" action="{{ route('admin.map-categories.update', $c->id) }}" class="p-5">
                                        @csrf
                                        @method('PATCH')
                                        <h3 class="text-lg font-bold mb-4">Editar categoría</h3>

                                        <div class="grid grid-cols-2 gap-3 mb-3">
                                            <div>
                                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Nombre</label>
                                                <input type="text" name="name" value="{{ $c->name }}" required maxlength="60"
                                                       class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Slug</label>
                                                <input type="text" name="slug" value="{{ $c->slug }}" required maxlength="60" pattern="[a-z0-9_-]+"
                                                       class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                                            </div>
                                        </div>

                                        <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Descripción</label>
                                        <textarea name="description" maxlength="500" rows="2"
                                                  class="w-full mb-3 rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">{{ $c->description }}</textarea>

                                        <div class="grid grid-cols-2 gap-3 mb-4">
                                            <div>
                                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Icon path</label>
                                                <input type="text" name="icon_path" value="{{ $c->icon_path }}" placeholder="categories/closed.png"
                                                       class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Sort order</label>
                                                <input type="number" name="sort_order" value="{{ $c->sort_order }}"
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
                                <x-confirm-modal id="delete-cat-{{ $c->id }}"
                                                 title="¿Eliminar categoría '{{ $c->name }}'?"
                                                 :action="route('admin.map-categories.destroy', $c->id)"
                                                 method="DELETE"
                                                 confirmLabel="Sí, eliminar"
                                                 :danger="true">
                                    <p>Esto va a eliminar la categoría <strong>{{ $c->name }}</strong>, su asociación con {{ $c->maps_count }} mapa(s) y todos los <strong>ratings de users</strong> en esa categoría.</p>
                                    <p class="text-xs text-zinc-500">Las matches históricas NO se ven afectadas — el rating global no se toca.</p>
                                </x-confirm-modal>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-sm text-zinc-500">
                                Todavía no creaste categorías. Mientras no haya, los mapas siguen funcionando con rating global solamente.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Crear --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Crear categoría</h2>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/40 p-4 sm:p-5">
            <form method="POST" action="{{ route('admin.map-categories.store') }}" class="grid sm:grid-cols-2 gap-3">
                @csrf
                <div>
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Nombre</label>
                    <input type="text" name="name" required maxlength="60" placeholder="Cerrados"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">
                        Slug <span class="normal-case text-zinc-600">(URL, /leaderboard?category=X)</span>
                    </label>
                    <input type="text" name="slug" required maxlength="60" pattern="[a-z0-9_-]+" placeholder="closed"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Descripción (opcional)</label>
                    <textarea name="description" maxlength="500" rows="2" placeholder="Mapas con bosques densos o murallas naturales que favorecen estrategias de boom..."
                              class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm focus:border-accent focus:outline-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Icon path (opcional)</label>
                    <input type="text" name="icon_path" placeholder="categories/closed.png"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-zinc-500 uppercase tracking-wider mb-1">Sort order</label>
                    <input type="number" name="sort_order" value="999"
                           class="w-full rounded border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm font-mono focus:border-accent focus:outline-none">
                </div>
                <div class="sm:col-span-2">
                    <button type="submit"
                            class="rounded bg-accent text-accent-dark px-4 py-2 text-sm font-semibold hover:bg-accent-hover transition-colors">
                        Crear categoría
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>
@endsection
