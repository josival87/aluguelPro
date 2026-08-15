<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! $request->user()->active || ! in_array($request->user()->role, $roles, true)) {
            abort(403, 'Você não possui permissão para acessar esta área.');
        }

        return $next($request);
    }
}
