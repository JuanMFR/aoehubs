@extends('layouts.app')

@section('title', 'Admin — Matches')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Admin · Matches</h1>
    </div>

    <nav class="flex gap-2 text-sm border-b border-zinc-800 pb-3">
        <a href="{{ route('admin.overview') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Overview</a>
        <a href="{{ route('admin.users') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Usuarios</a>
        <a href="{{ route('admin.matches') }}" class="px-3 py-1.5 rounded bg-zinc-800 text-zinc-100">Matches</a>
    </nav>

    {{-- Filtros por status --}}
    <div class="flex flex-wrap gap-2 text-sm">
        <a href="{{ route('admin.matches') }}"
           class="px-3 py-1.5 rounded border {{ ! $status ? 'border-steam bg-steam-dark text-steam' : 'border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900' }}">
            Todos
        </a>
        @foreach ($statuses as $st)
            <a href="{{ route('admin.matches', ['status' => $st]) }}"
               class="px-3 py-1.5 rounded border {{ $status === $st ? 'border-steam bg-steam-dark text-steam' : 'border-zinc-700 text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900' }}">
                {{ $st }}
            </a>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-lg border border-zinc-800">
        <table class="w-full text-sm">
            <thead class="bg-zinc-900/60">
                <tr class="text-left text-xs uppercase tracking-wider text-zinc-500">
                    <th class="px-3 py-3">ID</th>
                    <th class="px-3 py-3">Host</th>
                    <th class="px-3 py-3">Opponent</th>
                    <th class="px-3 py-3">Status</th>
                    <th class="px-3 py-3 hidden md:table-cell">Winner</th>
                    <th class="px-3 py-3 hidden lg:table-cell">Lobby</th>
                    <th class="px-3 py-3 hidden sm:table-cell">Creado</th>
                    <th class="px-3 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($matches as $m)
                    <tr class="hover:bg-zinc-900/40 transition-colors">
                        <td class="px-3 py-3 font-mono text-zinc-400">
                            <a href="{{ route('admin.matches.show', $m->id) }}" class="text-steam hover:underline">#{{ $m->id }}</a>
                        </td>
                        <td class="px-3 py-3 text-sm">
                            @if ($m->host->isBot())
                                <span class="text-amber-400">Bot Dev</span>
                            @else
                                <a href="{{ route('users.show', $m->host->steam_id) }}" class="hover:text-steam transition-colors">{{ $m->host->persona_name ?? Str::limit($m->host->steam_id, 12) }}</a>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-sm">
                            @if (! $m->opponent)
                                <span class="text-zinc-600">—</span>
                            @elseif ($m->opponent->isBot())
                                <span class="text-amber-400">Bot Dev</span>
                            @else
                                <a href="{{ route('users.show', $m->opponent->steam_id) }}" class="hover:text-steam transition-colors">{{ $m->opponent->persona_name ?? Str::limit($m->opponent->steam_id, 12) }}</a>
                            @endif
                        </td>
                        <td class="px-3 py-3"><span class="badge badge-{{ $m->status }}">{{ $m->status }}</span></td>
                        <td class="px-3 py-3 text-sm hidden md:table-cell">
                            @if ($m->winner)
                                <span class="text-emerald-400">{{ $m->winner->persona_name ?? Str::limit($m->winner->steam_id, 10) }}</span>
                            @else
                                <span class="text-zinc-600">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 font-mono text-xs hidden lg:table-cell">{{ $m->lobby_id ?? '—' }}</td>
                        <td class="px-3 py-3 font-mono text-xs text-zinc-500 hidden sm:table-cell whitespace-nowrap">{{ $m->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">
                            @if (in_array($m->status, ['pending', 'in_progress', 'drafting']))
                                <form method="POST" action="{{ route('admin.matches.cancel', $m->id) }}" class="inline" onsubmit="return confirm('¿Forzar cancel del match #{{ $m->id }}?');">
                                    @csrf
                                    <button type="submit" class="rounded border border-red-900 px-2 py-1 text-xs text-red-400 hover:bg-red-950 transition-colors">
                                        Cancelar
                                    </button>
                                </form>
                            @endif
                            @if ($m->status === 'pending_validation')
                                <form method="POST" action="{{ route('admin.matches.reprocess', $m->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="rounded border border-amber-900 px-2 py-1 text-xs text-amber-400 hover:bg-amber-950 transition-colors">
                                        Reprocesar
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-sm text-zinc-500">Sin matches.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $matches->links() }}</div>
</div>
@endsection
