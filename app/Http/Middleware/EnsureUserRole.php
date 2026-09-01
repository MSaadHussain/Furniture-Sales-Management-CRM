<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Account inactive.');
        }

        $allowed = array_map(fn ($r) => UserRole::from($r), $roles);

        if (! $user->hasRole(...$allowed)) {
            abort(403, 'Insufficient permissions.');
        }

        return $next($request);
    }
}
