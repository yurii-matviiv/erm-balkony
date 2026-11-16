<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentIcon;
use Filament\View\PanelsIconAlias;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
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
        // The four-squares mark (cut from the site logo) doubles as the sidebar
        // toggle (collapse/expand), replacing Filament's chevron arrows. Inlined
        // so it sits inside the icon button.
        $logoMark = new HtmlString((string) file_get_contents(public_path('images/logo-mark.svg')));
        FilamentIcon::register([
            PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON => $logoMark,
            PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON_RTL => $logoMark,
            PanelsIconAlias::SIDEBAR_EXPAND_BUTTON => $logoMark,
            PanelsIconAlias::SIDEBAR_EXPAND_BUTTON_RTL => $logoMark,
        ]);

        Gate::define('manage-page-docs', fn ($user) => $user->hasRole('super_admin'));
    }
}
