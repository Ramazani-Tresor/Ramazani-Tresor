<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** API v1 : jeton porteur (seule son empreinte SHA-256 est stockée), droits = ceux du compte propriétaire. */
class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = (string) $request->bearerToken();
        $token = $plain !== '' ? ApiToken::query()->with('user')->where('token_hash', hash('sha256', $plain))->first() : null;
        if (! $token || $token->revoked_at || ($token->expires_at && $token->expires_at->isPast()) || ! $token->user?->is_active || $token->user->archived_at) {
            return response()->json(['message' => 'Jeton d’accès invalide, expiré ou révoqué.'], 401);
        }
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->save();
        }
        Auth::setUser($token->user);

        return $next($request);
    }
}
