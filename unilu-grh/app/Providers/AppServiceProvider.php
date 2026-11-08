<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Carbon::setLocale(config('app.locale'));
        Gate::before(fn (User $user, string $ability) => $user->hasPermission($ability) ? true : null);

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $r->input('username')).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->bearerToken() ? hash('sha256', $r->bearerToken()) : $r->ip()));

        // Journal des connexions (section 17) dans le journal d'audit chaîné.
        Event::listen(Login::class, function (Login $e) {
            $e->user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();
            AuditService::log('auth.login', $e->user, 'Connexion', null, null, null, $e->user->id);
        });
        Event::listen(Logout::class, fn (Logout $e) => $e->user ? AuditService::log('auth.logout', $e->user, 'Déconnexion', null, null, null, $e->user->id) : null);
        Event::listen(Failed::class, fn (Failed $e) => AuditService::log('auth.failed', null, 'Échec de connexion pour « '.mb_substr((string) ($e->credentials['username'] ?? ''), 0, 60).' »'));
    }
}
