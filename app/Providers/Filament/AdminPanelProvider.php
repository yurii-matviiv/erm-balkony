<?php

namespace App\Providers\Filament;

use App\Services\Navigation\NavigationResolver;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
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
use Spatie\Permission\Models\Role;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            // A DEDICATED theme file for the panel — NOT resources/css/app.css.
            // That file has its own independent `@import 'tailwindcss'`
            // (for the public, non-Filament side); loading it here as well
            // gave the panel two separate full Tailwind resets and broke
            // the ENTIRE layout (confirmed by testing). This theme.css
            // instead imports Filament's own Tailwind entrypoint first
            // (which disables auto-scanning and pulls in every Filament
            // package's CSS) and just ADDS @source lines for our own
            // Resources/Pages/Blade files — see that file's docblock.
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            // No stock Filament\Pages\Dashboard here: our own
            // App\Filament\Pages\Dashboard (auto-discovered above)
            // replaces it — it redirects each user to the first page of
            // their role's sidebar instead of showing the generic
            // "Інфопанель". See that class's docblock.
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            // Per-role sidebar (see App\Services\Navigation\NavigationResolver
            // and the "Бокова панель" settings page). Using ->navigation()
            // with a closure replaces Filament's automatic
            // resources/pages/navigationItems() discovery ENTIRELY — that's
            // why the "Додати заявку" shortcut (previously a plain
            // ->navigationItems() call) is reconstructed inside the closure
            // below instead.
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => $builder->groups(
                self::buildNavigationGroups()
            ))
            ->userMenuItems(self::roleSwitcherMenuItems())
            // Show current account name + active role next to the avatar.
            // Useful during development when switching between roles.
            ->renderHook(
                'panels::user-menu.before',
                fn (): string => auth()->check()
                    ? '<div style="display:flex;flex-direction:column;align-items:flex-end;line-height:1.3;margin-right:6px;">'
                        . '<span style="font-size:13px;font-weight:600;color:inherit;">' . e(auth()->user()->name) . '</span>'
                        . '<span style="font-size:11px;opacity:.6;">' . e(auth()->user()->getActiveRoleName() ?? '—') . '</span>'
                        . '</div>'
                    : '',
            )
            // ── Developer floating toolbar (super_admin only) ─────────────
            // Fixed button in the bottom-right corner. Sends a POST to
            // /dev/clear-cache which runs cache:clear, view:clear,
            // config:clear, route:clear — then shows a toast with results.
            // Invisible to all other roles.
            ->renderHook(
                'panels::body.end',
                function (): string {
                    if (! auth()->check() || auth()->user()->getActiveRoleName() !== 'super_admin') {
                        return '';
                    }
                    $url = url('/dev/clear-cache');
                    return <<<HTML
<div
    x-data="{
        status: 'idle',
        messages: [],
        showModal: false,
        async run() {
            if (this.status === 'loading') return;
            this.status = 'loading';
            this.messages = [];
            this.showModal = false;
            try {
                const token = document.querySelector('meta[name=csrf-token]')?.content ?? '';
                const res   = await fetch('{$url}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                this.messages = data.cleared ?? [];
                this.status = 'success';
                this.showModal = true;
                setTimeout(() => location.reload(), 2000);
            } catch(e) {
                this.messages = ['Помилка: ' + e.message];
                this.status = 'error';
                this.showModal = true;
                setTimeout(() => { this.status = 'idle'; this.messages = []; this.showModal = false; }, 4000);
            }
        }
    }"
>
    <!-- Modal overlay -->
    <div
        x-show="showModal"
        x-transition.opacity
        style="position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);"
    >
        <div style="background:#1e293b;color:#e2e8f0;border-radius:14px;padding:28px 32px;min-width:300px;max-width:420px;box-shadow:0 8px 40px rgba(0,0,0,.5);">
            <!-- Title -->
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
                <span x-text="status === 'error' ? '✗' : '✓'"
                      :style="{ color: status === 'error' ? '#f87171' : '#4ade80', fontSize: '22px', fontWeight: 'bold' }"></span>
                <span style="font-size:16px;font-weight:600;"
                      x-text="status === 'error' ? 'Помилка очищення' : 'Кеш успішно очищено'"></span>
            </div>
            <!-- Item list -->
            <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;">
                <template x-for="msg in messages" :key="msg">
                    <li style="font-size:13px;line-height:1.5;" x-text="msg"></li>
                </template>
            </ul>
            <!-- Footer hint -->
            <p x-show="status === 'success'"
               style="margin-top:18px;font-size:12px;opacity:.55;text-align:center;">
                Сторінка перезавантажується…
            </p>
        </div>
    </div>

    <!-- Trigger button -->
    <button
        @click="run()"
        :title="status === 'loading' ? 'Очищаю...' : 'Очистити кеш та шаблони (⚡)'"
        :style="{
            background: status === 'success' ? '#22c55e' : status === 'error' ? '#ef4444' : '#f59e0b',
            transform: status === 'loading' ? 'scale(.95)' : 'scale(1)',
        }"
        style="position:fixed;bottom:22px;right:22px;z-index:9999;color:#1c1917;border:none;border-radius:50%;width:48px;height:48px;font-size:22px;cursor:pointer;box-shadow:0 3px 14px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;transition:background .2s,transform .15s;"
    >
        <span x-text="status === 'loading' ? '⏳' : status === 'success' ? '✓' : status === 'error' ? '✗' : '⚡'"></span>
    </button>
</div>
HTML;
                },
            )
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
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * @return array<NavigationGroup>
     */
    private static function buildNavigationGroups(): array
    {
        $role = auth()->user()?->getActiveRoleName();

        // The "Додати заявку" shortcut used to be hardcoded here directly
        // (invisible to, and unmanageable from, "Бокова панель"). It's now
        // a synthetic entry in NavigationCatalog (key 'quick-create-lead'),
        // so it flows through the exact same group/order/active-toggle
        // pipeline as every real Resource/Page — no special-casing needed.
        return NavigationResolver::resolveForSidebar($role)
            ->map(fn (array $group): NavigationGroup => NavigationGroup::make($group['label'])->items(
                $group['items']->map(
                    fn (array $item): NavigationItem => NavigationItem::make($item['label'])
                        ->icon($item['icon'])
                        ->url($item['url'])
                        ->sort($item['item_sort']),
                )->all(),
            ))
            ->all();
    }

    /**
     * One menu item per role that EXISTS in the system, each visible only
     * if the current user actually has that role — lets someone with
     * several roles (e.g. super_admin testing as Менеджер) switch which
     * sidebar they see. The chosen role is saved on the user record
     * (active_role), not the session, so it follows them to any device.
     *
     * @return array<Action>
     */
    private static function roleSwitcherMenuItems(): array
    {
        try {
            $roleNames = Role::query()->pluck('name')->all();
        } catch (\Throwable $e) {
            // Roles table may not exist yet on a brand-new install before
            // the first migrate — fail quiet, not fatal.
            return [];
        }

        return array_map(
            fn (string $roleName): Action => Action::make('switch_role_'.$roleName)
                ->label(fn (): string => (auth()->user()?->getActiveRoleName() === $roleName ? '✓ ' : '').$roleName)
                ->icon('heroicon-o-user-circle')
                ->visible(fn (): bool => auth()->user()?->hasRole($roleName) ?? false)
                ->action(function () use ($roleName) {
                    auth()->user()?->update(['active_role' => $roleName]);

                    return redirect(request()->header('referer') ?? Filament::getUrl());
                }),
            $roleNames,
        );
    }
}
