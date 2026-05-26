<?php

namespace App\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PanelPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('panel')
            ->path('panel')
            ->login()
            ->favicon(asset('favicon.ico'))
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Amber,
            ])

            /**
             * ---------------------------------------------------------
             * AUTO DISCOVER FILAMENT RESOURCES
             * ---------------------------------------------------------
             */
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources'
            )

            /**
             * ---------------------------------------------------------
             * AUTO DISCOVER FILAMENT PAGES
             * ---------------------------------------------------------
             */
            ->discoverPages(
                in: app_path('Filament/Pages'),
                for: 'App\\Filament\\Pages'
            )

            /**
             * ---------------------------------------------------------
             * DEFAULT PAGES
             * ---------------------------------------------------------
             */
           ->pages([
    \App\Filament\Pages\Dashboard\AdminDashboard::class,
    \App\Filament\Pages\Dashboard\FounderDashboard::class,
    \App\Filament\Pages\Dashboard\AccountantDashboard::class,
    \App\Filament\Pages\Dashboard\InstallerDashboard::class,
    \App\Filament\Pages\Dashboard\ManagerDashboard::class,
    \App\Filament\Pages\Dashboard\MarketingAgencyDashboard::class,
    \App\Filament\Pages\Dashboard\MeasurerDashboard::class,
    \App\Filament\Pages\Dashboard\SalesManagerDashboard::class,
])

            /**
             * ---------------------------------------------------------
             * AUTO DISCOVER WIDGETS
             * ---------------------------------------------------------
             */
            ->discoverWidgets(
                in: app_path('Filament/Widgets'),
                for: 'App\\Filament\\Widgets'
            )

            /**
             * ---------------------------------------------------------
             * DEFAULT WIDGETS
             * ---------------------------------------------------------
             */
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])

            /**
             * ---------------------------------------------------------
             * FILAMENT MIDDLEWARE
             * ---------------------------------------------------------
             */
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])

            /**
             * ---------------------------------------------------------
             * FILAMENT PLUGINS
             * ---------------------------------------------------------
             */
            ->plugins([
                FilamentShieldPlugin::make(),
            ])

            /**
             * ---------------------------------------------------------
             * AUTH MIDDLEWARE
             * ---------------------------------------------------------
             */
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}