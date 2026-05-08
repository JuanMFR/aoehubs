@extends('layouts.app')

@section('title', 'Admin — Usuarios')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Admin · Usuarios</h1>
    </div>

    <nav class="flex gap-2 text-sm border-b border-zinc-800 pb-3">
        <a href="{{ route('admin.overview') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Overview</a>
        <a href="{{ route('admin.users') }}" class="px-3 py-1.5 rounded bg-zinc-800 text-zinc-100">Usuarios</a>
        <a href="{{ route('admin.matches') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Matches</a>
        <a href="{{ route('admin.seasons') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Seasons</a>
    </nav>

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.users') }}" class="flex gap-2">
        <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por nombre o Steam ID..."
               class="flex-1 rounded border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm focus:border-accent focus:outline-none">
        <button type="submit" class="rounded border border-zinc-700 bg-zinc-900 px-4 py-2 text-sm text-zinc-200 hover:bg-zinc-800 transition-colors">
            Buscar
        </button>
        @if ($q)
            <a href="{{ route('admin.users') }}" class="rounded px-4 py-2 text-sm text-zinc-400 hover:text-zinc-100">Limpiar</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-lg border border-zinc-800">
        <table class="w-full text-sm">
            <thead class="bg-zinc-900/60">
                <tr class="text-left text-xs uppercase tracking-wider text-zinc-500">
                    <th class="px-3 py-3">ID</th>
                    <th class="px-3 py-3">Nombre</th>
                    <th class="px-3 py-3 hidden md:table-cell">Steam ID</th>
                    <th class="px-3 py-3 text-right">Rating</th>
                    <th class="px-3 py-3 hidden sm:table-cell">Role</th>
                    <th class="px-3 py-3 hidden lg:table-cell">Creado</th>
                    <th class="px-3 py-3 text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($users as $u)
                    <tr class="hover:bg-zinc-900/40 transition-colors">
                        <td class="px-3 py-3 font-mono text-zinc-500">{{ $u->id }}</td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-2">
                                @if ($u->avatar_url)
                                    <img src="{{ $u->avatar_url }}" alt="" class="h-7 w-7 rounded shrink-0">
                                @else
                                    <span class="h-7 w-7 rounded bg-zinc-800 flex items-center justify-center text-xs text-zinc-500 shrink-0">
                                        {{ Str::upper(Str::substr($u->persona_name ?? '?', 0, 1)) }}
                                    </span>
                                @endif
                                @if ($u->isBot())
                                    <span>{{ $u->persona_name ?? 'Bot Dev' }}</span>
                                @else
                                    <a href="{{ route('users.show', $u->steam_id) }}" class="hover:text-accent transition-colors">{{ $u->persona_name ?? '—' }}</a>
                                @endif
                                @if ($u->id === auth()->id())
                                    <span class="text-xs text-accent">(vos)</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-3 font-mono text-xs text-zinc-400 hidden md:table-cell">{{ $u->steam_id }}</td>
                        <td class="px-3 py-3 text-right font-mono">{{ round($u->rating) }}</td>
                        <td class="px-3 py-3 hidden sm:table-cell">
                            @if ($u->isAdmin())
                                <span class="text-xs px-1.5 py-0.5 rounded bg-amber-950 text-amber-300 uppercase tracking-wider font-medium">admin</span>
                            @else
                                <span class="text-xs px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-400 uppercase tracking-wider font-medium">player</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 font-mono text-xs text-zinc-500 hidden lg:table-cell whitespace-nowrap">{{ $u->created_at->format('Y-m-d') }}</td>
                        <td class="px-3 py-3 text-right">
                            @if ($u->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.promote', $u) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="role" value="{{ $u->isAdmin() ? 'player' : 'admin' }}">
                                    <button type="submit"
                                            class="rounded border border-zinc-700 bg-zinc-900 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-800 transition-colors">
                                        {{ $u->isAdmin() ? 'Quitar admin' : 'Hacer admin' }}
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-zinc-600">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-zinc-500">Sin resultados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $users->links() }}</div>
</div>
@endsection
