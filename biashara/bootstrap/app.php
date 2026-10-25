<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            EnsureUserIsActive::class,
            SecurityHeaders::class,
        ]);
        $middleware->redirectGuestsTo('/connexion');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Règle de gestion violée : message clair en français, sans page d'erreur.
        $exceptions->render(function (BusinessException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'details' => $e->details()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
        $exceptions->render(function (AccessDeniedHttpException|AuthorizationException $e, Request $request) {
            $message = "Accès refusé : vous n'avez pas la permission d'effectuer cette opération.";
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return response()->view('errors.403', ['message' => $message], 403);
        });
    })->create();
