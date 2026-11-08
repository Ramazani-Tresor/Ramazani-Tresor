<?php

use App\Http\Controllers\Api\V1Controller;
use Illuminate\Support\Facades\Route;

// API v1 en lecture seule, authentifiée par jeton porteur ; mêmes permissions et périmètres que l'interface.
Route::prefix('v1')->middleware(['api.token', 'throttle:api'])->group(function () {
    Route::get('/agents', [V1Controller::class, 'employees'])->middleware('perm:employees.view|employees.directory');
    Route::get('/agents/{matricule}', [V1Controller::class, 'employee'])->middleware('perm:employees.view');
    Route::get('/structures', [V1Controller::class, 'units']);
    Route::get('/statistiques', [V1Controller::class, 'stats'])->middleware('perm:dashboard.view');
    Route::get('/patrimoine', [V1Controller::class, 'assets'])->middleware('perm:assets.view');
});
