<?php

namespace App\Providers;

use Closure;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
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
        // SINGLE PLACE for the "filled status cell" look used in tables instead
        // of Filament badges: ->filledBadge(fn ($state, $record) => 'success').
        // Look (inset, height, opacity) is edited only in
        // resources/views/filament/components/filled-badge-styles.blade.php.
        TextColumn::macro('filledBadge', function (Closure|string $color = 'gray'): TextColumn {
            /** @var TextColumn $this */
            return $this
                ->extraCellAttributes(function (?string $state, $record = null) use ($color): array {
                    $name = $this->evaluate($color, ['state' => $state, 'record' => $record]);

                    return ['class' => 'filled-badge-cell', 'style' => "--badge-color: var(--{$name}-500);"];
                });
        });

        // Бар індикаторів менеджера — у шапці сторінки «Заявки», в одному рядку із заголовком (перед кнопками дій).
        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
            fn (): string => view('filament.components.lead-indicators-bar')->render(),
            scopes: \App\Filament\Resources\LeadResource\Pages\ListLeads::class,
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): string => view('filament.components.filled-badge-styles')->render()
                .view('filament.components.table-header-styles')->render()
                .view('filament.components.sticky-table-header')->render()
                .view('filament.components.tabs-styles')->render().view('filament.components.page-width-styles')->render(),
        );

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

        // Банер «Ви в акаунті X» — поки super_admin увійшов як інший користувач.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            function (): string {
                if (! auth()->check() || ! \App\Services\Impersonation\Impersonator::active()) {
                    return '';
                }

                return '<form method="POST" action="' . e(route('impersonate.leave', [], false)) . '" style="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:70;margin:0;">'
                    . csrf_field()
                    . '<div style="display:flex;align-items:center;gap:12px;background:#b45309;color:#fff;padding:8px 14px;border-radius:999px;box-shadow:0 4px 14px rgba(0,0,0,.35);font-size:13px;">'
                    . '<span>Ви в акаунті: <b>' . e(auth()->user()->name) . '</b></span>'
                    . '<button type="submit" style="background:#fff;color:#92400e;border:0;border-radius:999px;padding:4px 12px;font-weight:600;cursor:pointer;">Повернутись у свій акаунт</button>'
                    . '</div></form>';
            },
        );

        Gate::define('manage-page-docs', fn ($user) => $user->hasRole('super_admin'));
    }
}
