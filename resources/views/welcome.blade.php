@extends('layouts.app')

@section('title', 'AoE2 Rank — Plataforma ranked')

@section('content')
<div class="flex items-center justify-center min-h-[60vh]">
    <div class="w-full max-w-md text-center">
        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-xl bg-steam-dark text-steam font-bold text-2xl">
            A2
        </div>
        <h1 class="text-3xl font-bold tracking-tight">AoE2 Rank</h1>
        <p class="mt-2 text-zinc-400">Plataforma competitiva ranked 1v1 para Age of Empires 2 DE.</p>

        <div class="mt-8">
            @auth
                <p class="mb-4 text-sm text-zinc-500">Sesión activa.</p>
                <a href="{{ route('dashboard') }}"
                   class="inline-block rounded border border-steam bg-steam-dark px-6 py-2.5 font-semibold text-steam hover:bg-steam hover:text-steam-dark transition-colors">
                    Ir al dashboard →
                </a>
            @else
                <a href="{{ route('login') }}"
                   class="inline-block rounded border border-steam bg-steam-dark px-6 py-2.5 font-semibold text-steam hover:bg-steam hover:text-steam-dark transition-colors">
                    Iniciar sesión con Steam
                </a>
                <p class="mt-3 text-xs text-zinc-600">Necesitás una cuenta de Steam con AoE2 DE.</p>
            @endauth
        </div>

        <div class="mt-12 flex justify-center gap-4 text-sm">
            <a href="{{ route('leaderboard') }}" class="text-zinc-500 hover:text-zinc-300">Ver leaderboard →</a>
        </div>
    </div>
</div>
@endsection
