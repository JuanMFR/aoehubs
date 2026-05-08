<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso a rutas /admin/* si el user logueado no tiene role=admin.
 *
 * Si no esta logueado → redirige a login.
 * Si esta logueado pero no es admin → 403.
 */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->isAdmin()) {
            abort(403, 'Solo administradores.');
        }

        return $next($request);
    }
}
