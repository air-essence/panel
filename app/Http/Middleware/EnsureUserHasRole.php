<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Użycie w trasach: ->middleware('role:admin') lub 'role:kierownik,admin'.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = array_map(fn (string $r) => Role::from($r), $roles);

        abort_unless($user && $user->hasRole(...$allowed), 403, 'Brak uprawnień do tej części panelu.');

        return $next($request);
    }
}
