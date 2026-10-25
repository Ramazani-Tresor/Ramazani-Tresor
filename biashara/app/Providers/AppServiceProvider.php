<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Carbon::setLocale(config('app.locale'));
        Route::resourceVerbs(['create' => 'nouveau', 'edit' => 'modifier']);

        // Toutes les capacités `can:xxx` sont résolues par les permissions du rôle (contrôle serveur).
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ? true : null;
        });

        // Fuseau horaire paramétrable (Africa/Lubumbashi par défaut).
        if (! $this->app->runningUnitTests()) {
            try {
                if (Schema::hasTable('settings')) {
                    $tz = Settings::get('timezone', config('app.timezone'));
                    if (in_array($tz, timezone_identifiers_list(), true)) {
                        config(['app.timezone' => $tz]);
                        date_default_timezone_set($tz);
                    }
                }
            } catch (\Throwable) {
                // Base non encore installée : on garde la configuration du fichier .env.
            }
        }
    }
}
