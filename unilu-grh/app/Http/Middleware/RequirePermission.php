<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** perm:a|b — l'utilisateur doit détenir au moins une des permissions (contrôle serveur). */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();
        foreach (explode('|', $permissions) as $p) {
            if ($user?->hasPermission($p)) {
                return $next($request);
            }
        }
        abort(403);
    }
}
