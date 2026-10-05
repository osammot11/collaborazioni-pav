<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::tokensCan([
            'offline_access' => 'Rinnovare il collegamento fino a revoca o scadenza del token di rinnovo',
            'pipeline:read' => 'Leggere opportunità, contatti, note e storico della pipeline',
            'pipeline:write' => 'Creare e modificare opportunità e note (nessuna eliminazione)',
        ]);
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::authorizationView(fn ($parameters) => view('integrations.authorize', $parameters));
    }
}
