<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Who may edit "Документація сторінки" blocks (see PageDoc /
        // HasPageDocs). Deliberately a separate, narrow permission rather
        // than reusing Filament Shield's resource policies — this isn't a
        // CRUD resource, it's an in-app help system every page can embed.
        Gate::define('manage-page-docs', fn ($user) => $user->hasRole(['super_admin', 'founder']));
    }
}
