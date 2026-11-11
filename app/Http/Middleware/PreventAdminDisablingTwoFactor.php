<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventAdminDisablingTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()?->isAdmin()
            && $request->isMethod('DELETE')
            && str_contains((string) $request->route()?->getName(), 'two-factor')
        ) {
            abort(403, 'La double authentification est obligatoire pour les comptes administrateurs.');
        }

        return $next($request);
    }
}
