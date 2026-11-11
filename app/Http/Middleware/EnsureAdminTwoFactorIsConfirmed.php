<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactorIsConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user?->isAdmin()
            && (empty($user->two_factor_secret) || ! $user->two_factor_confirmed_at)
        ) {
            return redirect()->route('security.edit')->with(
                'status',
                'Configure et confirme la double authentification avant d’accéder à l’administration.',
            );
        }

        return $next($request);
    }
}
