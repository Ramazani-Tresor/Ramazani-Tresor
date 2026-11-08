<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Mot de passe temporaire : changement obligatoire avant tout autre écran. */
class EnforceAccountSecurity
{
    private const ALLOWED = ['account.password', 'account.password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->must_change_password && ! in_array($request->route()?->getName(), self::ALLOWED, true) && ! $request->is('livewire/*')) {
            return redirect()->route('account.password')->with('warning', 'Veuillez remplacer votre mot de passe temporaire.');
        }

        return $next($request);
    }
}
