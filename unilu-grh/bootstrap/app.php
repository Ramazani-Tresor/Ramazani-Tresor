<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\EnforceAccountSecurity;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequirePermission;
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
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [SecurityHeaders::class, EnsureUserIsActive::class, EnforceAccountSecurity::class]);
        $middleware->alias(['perm' => RequirePermission::class, 'api.token' => ApiTokenAuth::class]);
        $middleware->redirectGuestsTo('/connexion');
        $middleware->redirectUsersTo('/');
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', ''));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([BusinessException::class]);
        $exceptions->render(function (BusinessException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $e->getMessage(), 'details' => $e->details()], 422);
            }

            return back()->withInput($request->except(['password', 'password_confirmation', 'file', 'document', 'photo', 'bank_account']))->with('error', $e->getMessage());
        });
        $exceptions->render(function (AccessDeniedHttpException|AuthorizationException $e, Request $request) {
            $message = 'Accès refusé : cette opération ou ces données sont hors de vos habilitations.';
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $message], 403);
            }

            return response()->view('errors.403', ['message' => $message], 403);
        });
    })->create();
