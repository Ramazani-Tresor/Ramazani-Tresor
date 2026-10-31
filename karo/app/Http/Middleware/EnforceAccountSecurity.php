<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 1. Mot de passe temporaire → changement obligatoire.
 * 2. Rôle sensible → second facteur obligatoire : configuration, puis vérification à chaque session.
 */
class EnforceAccountSecurity
{
    private const ALLOWED = ['logout', 'account.*', 'two-factor.*', 'documents.verify', 'pwa.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }
        if ($user->must_change_password) {
            return $this->deny($request, route('account.show'), 'Vous devez changer votre mot de passe temporaire.');
        }
        if ($user->requiresTwoFactor() && ! $user->hasTwoFactor()) {
            return $this->deny($request, route('two-factor.setup'), 'Votre rôle exige un second facteur d’authentification : configurez-le.');
        }
        if ($user->hasTwoFactor() && ! $request->session()->get('2fa_passed')) {
            return $this->deny($request, route('two-factor.challenge'), null);
        }

        return $next($request);
    }

    private function deny(Request $request, string $url, ?string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message ?? 'Vérification en deux étapes requise.'], 403);
        }

        return redirect($url)->with('warning', $message);
    }
}
