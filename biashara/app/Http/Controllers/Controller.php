<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

abstract class Controller
{
    /** Contrôle serveur d'une permission (403 sinon). */
    protected function authorizePermission(string $permission): void
    {
        abort_unless(request()->user()?->hasPermission($permission), 403);
    }

    /** L'utilisateur doit posséder au moins une des permissions. */
    protected function authorizeAny(array $permissions): void
    {
        $user = request()->user();
        foreach ($permissions as $p) {
            if ($user?->hasPermission($p)) {
                return;
            }
        }
        abort(403);
    }

    /** Période filtrée (par défaut : le mois en cours), bornes incluses. */
    protected function period(Request $request, string $default = 'month'): array
    {
        $from = $request->date('from') ?? ($default === 'today' ? now() : now()->startOfMonth());
        $to = $request->date('to') ?? now();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
    }
}
