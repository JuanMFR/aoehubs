@extends('layouts.app')

@section('title', 'Admin — Overview')

@section('content')
<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold flex items-center gap-3">
            Admin
            <span class="text-xs font-medium px-2 py-0.5 rounded bg-amber-950 text-amber-300 uppercase tracking-wider">Solo admin</span>
        </h1>
        <p class="mt-1 text-sm text-zinc-500">Estado del sistema y atajos a las herramientas.</p>
    </div>

    {{-- Sub-nav admin --}}
    <nav class="flex gap-2 text-sm border-b border-zinc-800 pb-3">
        <a href="{{ route('admin.overview') }}" class="px-3 py-1.5 rounded bg-zinc-800 text-zinc-100">Overview</a>
        <a href="{{ route('admin.users') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Usuarios</a>
        <a href="{{ route('admin.matches') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Matches</a>
        <a href="{{ route('admin.seasons') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Seasons</a>
        <a href="{{ route('admin.maps') }}" class="px-3 py-1.5 rounded text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900">Maps</a>
    </nav>

    {{-- Top metrics --}}
    <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
            <div class="text-xs text-zinc-500 uppercase tracking-wider">Usuarios</div>
            <div class="mt-1 font-mono text-2xl font-semibold">{{ $userStats['total'] }}</div>
            <div class="text-xs text-zinc-500">{{ $userStats['admins'] }} admins · {{ $userStats['players'] }} players</div>
        </div>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
            <div class="text-xs text-zinc-500 uppercase tracking-wider">En cola</div>
            <div class="mt-1 font-mono text-2xl font-semibold">{{ $queueSize }}</div>
            <div class="text-xs text-zinc-500">jugadores reales (sin bot)</div>
        </div>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
            <div class="text-xs text-zinc-500 uppercase tracking-wider">Matches activas</div>
            <div class="mt-1 font-mono text-2xl font-semibold">{{ ($statusCounts['drafting'] ?? 0) + ($statusCounts['pending'] ?? 0) + ($statusCounts['in_progress'] ?? 0) }}</div>
            <div class="text-xs text-zinc-500">drafting + pending + in_progress</div>
        </div>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
            <div class="text-xs text-zinc-500 uppercase tracking-wider">Pendientes parser</div>
            <div class="mt-1 font-mono text-2xl font-semibold {{ ($statusCounts['pending_validation'] ?? 0) > 5 ? 'text-amber-400' : '' }}">
                {{ $statusCounts['pending_validation'] ?? 0 }}
            </div>
            <div class="text-xs text-zinc-500">esperan mgz upstream</div>
        </div>
    </section>

    {{-- Status breakdown completo --}}
    <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-zinc-500">Matches por status</h2>
        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                @foreach (['drafting', 'pending', 'in_progress', 'completed', 'pending_validation', 'invalid', 'abandoned'] as $st)
                    <a href="{{ route('admin.matches', ['status' => $st]) }}"
                       class="flex items-center justify-between rounded px-3 py-2 hover:bg-zinc-800 transition-colors">
                        <span class="badge badge-{{ $st }}">{{ __($st) }}</span>
                        <span class="font-mono">{{ $statusCounts[$st] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <div class="grid sm:grid-cols-2 gap-6">
        {{-- Recent matches --}}
        <section>
            <div class="flex items-baseline justify-between mb-3">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Últimos matches</h2>
                <a href="{{ route('admin.matches') }}" class="text-xs text-accent hover:underline">ver todos →</a>
            </div>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 divide-y divide-zinc-800">
                @forelse ($recentMatches as $m)
                    <a href="{{ route('admin.matches.show', $m->id) }}" class="flex items-center justify-between px-4 py-3 hover:bg-zinc-800/60 transition-colors">
                        <div>
                            <span class="font-mono text-sm text-zinc-400">#{{ $m->id }}</span>
                            <span class="ml-2 text-sm">{{ $m->host->persona_name ?? Str::limit($m->host->steam_id, 10) }}</span>
                            <span class="text-zinc-600 mx-1">vs</span>
                            <span class="text-sm">{{ $m->opponent->persona_name ?? Str::limit($m->opponent->steam_id ?? '—', 10) }}</span>
                        </div>
                        <span class="badge badge-{{ $m->status }}">{{ __($m->status) }}</span>
                    </a>
                @empty
                    <div class="px-4 py-6 text-center text-sm text-zinc-500">Sin matches.</div>
                @endforelse
            </div>
        </section>

        {{-- Recent users --}}
        <section>
            <div class="flex items-baseline justify-between mb-3">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Últimos usuarios</h2>
                <a href="{{ route('admin.users') }}" class="text-xs text-accent hover:underline">ver todos →</a>
            </div>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 divide-y divide-zinc-800">
                @forelse ($recentUsers as $u)
                    <div class="flex items-center justify-between px-4 py-3">
                        <div>
                            <span class="text-sm">{{ $u->persona_name ?? Str::limit($u->steam_id, 14) }}</span>
                            @if ($u->isAdmin())
                                <span class="ml-2 text-xs px-1.5 py-0.5 rounded bg-amber-950 text-amber-300 uppercase tracking-wider font-medium">admin</span>
                            @endif
                        </div>
                        <span class="font-mono text-xs text-zinc-500">{{ round($u->rating) }}</span>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-sm text-zinc-500">Sin usuarios.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
