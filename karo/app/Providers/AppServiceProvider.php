<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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
        RateLimiter::for('two-factor', fn (Request $r) => Limit::perMinute(5)->by($r->session()->getId()));
        RateLimiter::for('verify', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
        RateLimiter::for('sync', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));

        if (! $this->app->runningUnitTests()) {
            try {
                if (Schema::hasTable('settings')) {
                    $tz = Settings::get('timezone');
                    if (in_array($tz, timezone_identifiers_list(), true)) {
                        config(['app.timezone' => $tz]);
                        date_default_timezone_set($tz);
                    }
                }
            } catch (\Throwable) {
            }
        }
    }
}
