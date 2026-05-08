<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AoE2 Rank')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">

    @auth
    <header class="sticky top-0 z-40 border-b border-zinc-800/80 bg-zinc-950/95 backdrop-blur">
        <nav class="max-w-6xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between gap-4">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 group">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded bg-steam-dark text-steam font-bold text-sm group-hover:bg-steam group-hover:text-steam-dark transition-colors">A2</span>
                <span class="font-semibold tracking-tight">AoE2 Rank</span>
            </a>

            <div class="hidden sm:flex items-center gap-1 text-sm">
                @php $route = request()->route()?->getName(); @endphp
                <a href="{{ route('dashboard') }}"
                   class="px-3 py-1.5 rounded transition-colors {{ $route === 'dashboard' ? 'bg-zinc-800 text-zinc-100' : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900' }}">
                    Dashboard
                </a>
                <a href="{{ route('matches.index') }}"
                   class="px-3 py-1.5 rounded transition-colors {{ str_starts_with($route ?? '', 'matches.') ? 'bg-zinc-800 text-zinc-100' : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900' }}">
                    Mis matches
                </a>
                <a href="{{ route('leaderboard') }}"
                   class="px-3 py-1.5 rounded transition-colors {{ $route === 'leaderboard' ? 'bg-zinc-800 text-zinc-100' : 'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-900' }}">
                    Leaderboard
                </a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.overview') }}"
                       class="px-3 py-1.5 rounded transition-colors {{ str_starts_with($route ?? '', 'admin.') ? 'bg-amber-950 text-amber-300' : 'text-amber-400 hover:text-amber-300 hover:bg-amber-950/40' }}">
                        Admin
                    </a>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <div class="hidden sm:flex flex-col items-end leading-tight">
                    <span class="text-sm">{{ auth()->user()->persona_name ?? '—' }}</span>
                    <span class="text-xs text-zinc-500 font-mono">{{ round(auth()->user()->rating) }} rating</span>
                </div>
                @if (auth()->user()->avatar_url)
                    <img src="{{ auth()->user()->avatar_url }}" alt="" class="h-8 w-8 rounded-full border border-zinc-700">
                @else
                    <div class="h-8 w-8 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center text-xs text-zinc-500">
                        {{ Str::upper(Str::substr(auth()->user()->persona_name ?? 'P', 0, 1)) }}
                    </div>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="text-sm text-zinc-400 hover:text-zinc-100 px-2 py-1 rounded hover:bg-zinc-900 transition-colors">
                        Salir
                    </button>
                </form>
            </div>
        </nav>

        {{-- Nav mobile (visible <sm) --}}
        <div class="sm:hidden flex justify-around border-t border-zinc-900 text-sm">
            <a href="{{ route('dashboard') }}" class="flex-1 text-center py-2 {{ $route === 'dashboard' ? 'text-steam border-b-2 border-steam' : 'text-zinc-400' }}">Dashboard</a>
            <a href="{{ route('matches.index') }}" class="flex-1 text-center py-2 {{ str_starts_with($route ?? '', 'matches.') ? 'text-steam border-b-2 border-steam' : 'text-zinc-400' }}">Matches</a>
            <a href="{{ route('leaderboard') }}" class="flex-1 text-center py-2 {{ $route === 'leaderboard' ? 'text-steam border-b-2 border-steam' : 'text-zinc-400' }}">Ranking</a>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.overview') }}" class="flex-1 text-center py-2 {{ str_starts_with($route ?? '', 'admin.') ? 'text-amber-300 border-b-2 border-amber-400' : 'text-amber-400' }}">Admin</a>
            @endif
        </div>
    </header>
    @else
    <header class="border-b border-zinc-800/80 bg-zinc-950/95">
        <nav class="max-w-6xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded bg-steam-dark text-steam font-bold text-sm">A2</span>
                <span class="font-semibold tracking-tight">AoE2 Rank</span>
            </a>
            <div class="flex items-center gap-3">
                <a href="{{ route('leaderboard') }}" class="text-sm text-zinc-400 hover:text-zinc-100">Leaderboard</a>
                <a href="{{ route('login') }}" class="text-sm bg-steam-dark border border-steam text-steam hover:bg-steam hover:text-steam-dark px-3 py-1.5 rounded transition-colors font-semibold">Iniciar sesión con Steam</a>
            </div>
        </nav>
    </header>
    @endauth

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-8 animate-fade-in">
        @if (session('flash'))
            <div class="mb-6 rounded-lg border border-emerald-800 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-300">
                {{ session('flash') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-lg border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
                {{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mt-12 border-t border-zinc-900 text-xs text-zinc-600">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex flex-col sm:flex-row justify-between gap-1">
            <span>AoE2 Rank — beta</span>
            <span>Plataforma ranked competitiva 1v1 para Age of Empires 2 DE</span>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
