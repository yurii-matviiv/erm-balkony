<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RedirectToRolePanel;
use App\Services\Navigation\NavigationResolver;
use App\Support\RolePanels;
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
        // Admin-only part: identity, auto-discovered resources/pages/widgets and
        // the Shield (roles/permissions) plugin. Everything shared with the
        // manager panel lives in configureCommon() below.
        return self::configureCommon($panel)
            ->default()
            ->id('admin')
            ->path('admin')
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
            ->plugins([
                FilamentShieldPlugin::make(),
            ]);
    }

    /**
     * Configuration shared by BOTH panels (admin and manager): login, theme,
     * per-role sidebar, user card, render hooks, middleware. Panel identity
     * (id/path), which resources/pages it exposes and the Shield plugin are
     * set by each provider itself.
     */
    public static function configureCommon(Panel $panel): Panel
    {
        return $panel
            ->login()
            ->brandName('ERM')
            ->brandLogo(fn () => new \Illuminate\Support\HtmlString(
                '<span style="display:inline-flex;align-items:center;padding-left:15px;">'
                .'<span style="font-weight:300;font-size:1.25rem;letter-spacing:.04em;color:#fff;">ERM ВІКНА</span>'
                .'</span>'
            ))
            ->brandLogoHeight('2rem')
            // No top bar (user request): the logo and collapse button live in
            // the sidebar header, and the user card (avatar, name, menu with
            // role switcher + logout) sits at the BOTTOM of the sidebar —
            // Filament does that itself when the topbar is off. This frees
            // the whole top strip for page content.
            ->topbar(false)
            // Full-width content (like the old CRM): wide tables such as the
            // orders list no longer need horizontal scrolling.
            ->maxContentWidth(\Filament\Support\Enums\Width::Full)
            // Brand blue from the "Балкони та вікна" logo (the 4-squares mark):
            // calmer for managers than amber, and Filament puts white text on
            // filled primary buttons. Applies to every panel and page, so
            // individual buttons never need their own colour fix.
            ->colors([
                'primary' => [
                    50 => '#eef6fb', 100 => '#d6e9f5', 200 => '#b0d3ea', 300 => '#7db6dc',
                    400 => '#4a96c9', 500 => '#2e86c1', 600 => '#2874a6', 700 => '#226089',
                    800 => '#1f4f70', 900 => '#1d425d', 950 => '#112a3c',
                ],
            ])
            // Breadcrumbs ("Заявки > List") just repeated the page heading on
            // every list page — one title is enough (global rule).
            ->breadcrumbs(false)
            // Roboto, SELF-HOSTED (public/fonts/roboto): crisp on Windows like the
            // old CRM's system font, but identical on every device, and no
            // third-party request (faster, no Google/Bunny round-trip). Only
            // Cyrillic + Latin subsets are shipped (~65 KB total).
            ->font('Roboto Variable', url: fn (): string => asset('fonts/roboto/roboto.css'), provider: \Filament\FontProviders\LocalFontProvider::class)
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
            // Collapsed = narrow icons-only rail (user request v2: NOT
            // fully hidden — icons stay for quick access). Toggled by
            // clicking the "ERM" brand (render hook below); state is
            // persisted by Filament itself (Alpine $persist → browser
            // localStorage), so the browser remembers the choice.
            ->sidebarCollapsibleOnDesktop()
            // Narrower than Filament's default 20rem; the name in the user card is
            // cut to "Прізвище Ім'я" and never stretches the sidebar anyway.
            ->sidebarWidth('16rem')
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
            // ── Dark sidebar (user request): the menu is black with white text so it
            // is clearly separated from the page, like the old CRM. Inline CSS on
            // purpose — visible immediately, no `npm run build`. Colors are set on
            // the sidebar's own elements only (not on the whole <aside>), so the
            // user-menu dropdown keeps its normal light styling and stays readable.
            ->renderHook(
                'panels::head.end',
                fn (): string => <<<'HTML'
<style>
html { font-size:106.25%; }
/* Єдиний статус-бар (components/status-bar.blade.php): прямокутні етапи + лінія під кожним; пройдені — синя, поточний — яскрава помаранчева, майбутні — сіра. */
.sb{display:flex;gap:0;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;padding-bottom:9px;}
.sb-step{flex:1 1 0;min-width:max-content;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;padding:.6rem .75rem;border:0;border-radius:0;font-size:.8rem;font-weight:500;line-height:1.2;white-space:nowrap;position:relative;background:#eef2f5;color:#5b6b78;}
button.sb-step{cursor:pointer;} button.sb-step:hover{filter:brightness(.96);}
.sb-step:first-child{border-radius:3px 0 0 3px;} .sb-step:last-child{border-radius:0 3px 3px 0;}
.sb-step::after{content:"";position:absolute;left:0;right:0;bottom:-7px;height:2px;background:#d5dde3;}
.sb-date{font-size:.68rem;opacity:.8;}
.sb-done{background:#d6e6f2;color:#1b4f72;} .sb-done::after{background:#2874A6;}
.sb-current{background:#2874A6;color:#fff;font-weight:600;} .sb-current::after{background:#ff7a1a;}
.sb-danger{background:#fde8e8;color:#b42318;font-weight:600;} .sb-danger::after{background:#d92d20;}
.sb-dimmed .sb-current{background:#8aa4b5;} .sb-dimmed .sb-current::after{background:#d5dde3;}
.dark .sb-step{background:#1f2933;color:#9aa8b4;} .dark .sb-done{background:#1d3347;color:#bcd6ea;}
/* Filled coloured buttons: always a solid darker shade with WHITE text (Filament defaults to light shade + dark text for success/warning/danger/info). Gray/outlined buttons untouched. */
.fi-btn.fi-color:not(.fi-color-gray):not(.fi-outlined), .fi-btn.fi-color:not(.fi-color-gray):not(.fi-outlined) svg { color:#fff !important; }
.fi-btn.fi-color:not(.fi-color-gray):not(.fi-outlined) { background-color:var(--color-600) !important; }
.fi-btn.fi-color:not(.fi-color-gray):not(.fi-outlined):hover { background-color:var(--color-700) !important; }
/* Form fields: almost rectangular, like the old CRM (Filament default is rounded-lg). */
.fi-input-wrp, .fi-input-wrp > *, .fi-input, .fi-select-input, .fi-fo-textarea, .fi-fo-select-native, .fi-input-wrp .fi-badge { border-radius:3px; }
.fi-input-wrp { border-radius:3px !important; }
.fi-dropdown-panel { border-radius:4px; }
/* Cards/containers (sections, tables, modals): minimal rounding (3px) everywhere — Filament default is rounded-xl. */
.fi-section, .fi-section-content-ctn, .fi-section-header, .fi-ta-ctn, .fi-ta-content, .fi-ta-content-ctn, .fi-ta-header-ctn, .fi-modal-window, .fi-in-section, .fi-wi-stats-overview-stat, .fi-ta-ctn > * { border-radius:3px !important; }
/* "Input-group" як у старій CRM (Bootstrap): підпис і поле — один суцільний блок, підпис завжди однакової ширини (11rem), решта — поле. Opt in: ->extraAttributes(['class' => 'ig-card'])->inlineLabel() на Section. */
.ig-card .fi-fo-field, .ig-card .fi-in-entry { display:grid !important; grid-template-columns:11rem minmax(0,1fr) !important; gap:0 !important; align-items:stretch; }
.ig-card .fi-fo-field-label-col, .ig-card .fi-in-entry-label-col { display:flex; align-items:center; background:#eef2f5; border:1px solid #cfd8de; border-right:0; border-radius:3px 0 0 3px; padding:.45rem .75rem; font-size:.85rem; font-weight:500; color:#41515c; }
.ig-card .fi-fo-field-label-col > *, .ig-card .fi-in-entry-label-col > * { margin:0; }
.ig-card .fi-fo-field-content-col .fi-input-wrp { border-radius:0 3px 3px 0 !important; }
.ig-card .fi-in-entry-content-col { display:flex; align-items:center; border:1px solid #cfd8de; border-radius:0 3px 3px 0; padding:.45rem .75rem; }
.ig-card .fi-fo-field-content-col, .ig-card .fi-in-entry-content-col { grid-column:auto !important; }
.ig-card .fi-fo-field-content-col:has(.fi-fo-toggle), .ig-card .fi-fo-field-content-col:has(input[type=checkbox]) { display:flex; align-items:center; border:1px solid #cfd8de; border-radius:0 3px 3px 0; padding:.35rem .75rem; background:#fff; }
.ig-card .fi-in-entry:has(.ig-plain) { display:block !important; } .ig-card .fi-in-entry:has(.ig-plain) .fi-in-entry-label-col { display:none; } .ig-card .fi-in-entry:has(.ig-plain) .fi-in-entry-content-col { border:0; padding:0; }
.ig-card .fi-fo-toggle { margin-block:auto; }
/* Label-left "record card" (old-CRM style): each row gets a full-width bottom line so the left label visually ties to its right-hand value. Opt in with ->extraAttributes(['class' => 'record-card']) on a Section. */
.record-card .fi-fo-field, .record-card .fi-in-entry, .record-card .record-row { border-bottom:1px solid rgba(0,0,0,.09); padding-block:.6rem; }
.record-card .fi-section-content > .fi-grid-col:last-child > .fi-sc-component > * { border-bottom:0; }
.fi-sidebar, .fi-sidebar-header-ctn, .fi-sidebar-header, .fi-sidebar-nav, .fi-sidebar-footer { background-color:#0e1012 !important; }
.fi-sidebar { border-inline-end:0 !important; box-shadow:2px 0 12px rgba(0,0,0,.18); }
.fi-sidebar-header-ctn { box-shadow:none !important; border-bottom:1px solid rgba(255,255,255,.08) !important; }
.fi-sidebar-footer { border-top:1px solid rgba(255,255,255,.08) !important; }
.fi-sidebar .fi-logo, .fi-sidebar .fi-sidebar-header a, .fi-sidebar .fi-sidebar-header span { color:#fff !important; }
.fi-sidebar-item-label { color:#fff !important; }
.fi-sidebar-item-icon, .fi-sidebar-item-btn svg { color:#4aa3df !important; }
.fi-sidebar-group-label, .fi-sidebar-group-btn svg, .fi-sidebar-group-collapse-btn svg { color:rgba(255,255,255,.65) !important; }
.fi-sidebar-item-btn:hover, .fi-sidebar-group-btn:hover { background-color:rgba(255,255,255,.09) !important; }
.fi-sidebar-item.fi-active > .fi-sidebar-item-btn { background-color:rgba(255,255,255,.15) !important; }
.fi-sidebar-header .fi-icon-btn { color:#fff !important; padding:.25rem !important; }
.fi-sidebar-header .fi-icon-btn:hover { background-color:rgba(255,255,255,.12) !important; }
.fi-sidebar .fi-user-menu-trigger { color:#fff !important; }
.fi-sidebar .fi-user-menu-trigger:hover { background-color:rgba(255,255,255,.09) !important; }
.fi-sidebar .fi-user-menu-trigger-text, .fi-sidebar .fi-user-menu-trigger svg { color:#fff !important; }
.fi-sidebar-footer .fi-icon-btn, .fi-sidebar-footer .fi-icon-btn svg { color:#fff !important; }
.fi-sidebar-footer { margin:0 !important; padding:18px 16px !important; display:flex !important; align-items:center; gap:12px; min-height:92px; }
.fi-sidebar-footer .fi-user-menu { width:40px; flex:none; }
.fi-sidebar-footer .fi-user-menu-trigger { padding:0 !important; width:40px; height:40px; justify-content:center; }
.fi-sidebar-footer .fi-user-menu-trigger-text, .fi-sidebar-footer .fi-user-menu-trigger > svg:not(.fi-avatar) { display:none !important; }
.fi-sidebar-footer .fi-avatar { width:40px !important; height:40px !important; }
.fi-sidebar-header { justify-content:space-between !important; }
.fi-sidebar-header .fi-icon-btn svg { width:1.5rem !important; height:1.5rem !important; }
.fi-sidebar-open-collapse-sidebar-btn { margin-inline:auto !important; }
</style>
HTML,
            )
            // Bottom user card (layout copied from the reference ERM): the avatar
            // (Filament's own dropdown trigger — keeps the role switcher) sits
            // on the left; to its right, stacked: full name (whole row), email,
            // then the active role on the left and a "Вийти" button with icon on
            // the right. Rendered by the user-menu.after hook, i.e. INSIDE
            // .fi-sidebar-footer, which the CSS above turns into a flex row with
            // real padding (an earlier absolutely-positioned version drifted up
            // when the viewport height differed). Hidden when the sidebar is collapsed to the rail.
            ->renderHook(
                'panels::user-menu.after',
                fn (): string => auth()->check()
                    ? '<div x-data="{}" x-show="$store.sidebar.isOpen" x-cloak style="flex:1;display:flex;flex-direction:column;justify-content:center;gap:2px;color:#fff;min-width:0;">'
                        . '<div style="font-size:14px;font-weight:600;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' . e(implode(' ', array_slice(preg_split('/\s+/u', trim(auth()->user()->name)), 0, 2))) . '</div>'
                        . '<div style="font-size:12px;line-height:1.25;opacity:.65;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' . e(auth()->user()->email) . '</div>'
                        . '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:2px;">'
                        . '<span style="font-size:11px;opacity:.7;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' . e(auth()->user()->getActiveRoleName() ?? '—') . '</span>'
                        . '<form method="POST" action="' . e(filament()->getLogoutUrl()) . '" style="margin:0;flex:none;">'
                        . csrf_field()
                        . '<button type="submit" title="Вийти" aria-label="Вийти" style="display:flex;align-items:center;gap:4px;font-size:12px;opacity:.85;cursor:pointer;background:none;border:0;padding:2px 0 2px 4px;color:#fff;">'
                        . 'Вийти<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>'
                        . '</button></form></div></div>'
                    : '',
            )
            // ── Global data-loading indicator (every user, every page) ────
            // Explicit user request: with big filter periods it was
            // impossible to tell whether filtering was running or hung.
            // Local wire:loading proved unreliable with Filament 5's
            // partial table rendering, so this hooks the Livewire request
            // lifecycle itself (Livewire.hook('commit')) — it therefore
            // works UNIVERSALLY: table filters on Платежі, the date bar
            // on Аналітика, sidebar toggles, any future page. Pill at the
            // bottom: spinner while any request is in flight, then a
            // short green "done" confirmation. Inline styles on purpose —
            // no dependency on the Vite/Tailwind build.
            ->renderHook(
                'panels::body.end',
                // Guests excluded on purpose: the login form is a Livewire
                // page too, so the pill showed "✓ Дані оновлено" after a
                // FAILED sign-in attempt — nonsense outside the panel.
                //
                // Bottom-right (per user request — the centered pill was in
                // the way), right:82px keeps it clear of the 48px dev
                // clear-cache button in the same corner. Near-transparent
                // backgrounds + colored text: enough to see "something is
                // talking to the DB" without drawing attention.
                fn (): string => auth()->check() ? <<<'HTML'
<div id="global-loading-pill" style="position:fixed;bottom:28px;right:82px;z-index:9999;display:none;align-items:center;gap:6px;padding:4px 10px;border-radius:8px;font-size:12px;font-weight:500;pointer-events:none;">
    <span id="global-loading-spinner" style="display:inline-block;width:11px;height:11px;border:2px solid rgba(180,83,9,.3);border-top-color:#b45309;border-radius:50%;animation:glp-spin .7s linear infinite;"></span>
    <span id="global-loading-text">Оновлення даних…</span>
</div>
<style>@keyframes glp-spin{to{transform:rotate(360deg)}}</style>
<script>
document.addEventListener('livewire:init', () => {
    const pill = document.getElementById('global-loading-pill');
    const spinner = document.getElementById('global-loading-spinner');
    const text = document.getElementById('global-loading-text');
    let active = 0;
    let hideTimer = null;

    const showLoading = () => {
        clearTimeout(hideTimer);
        pill.style.display = 'flex';
        pill.style.background = 'rgba(245,158,11,.10)';
        pill.style.color = '#b45309';
        spinner.style.display = 'inline-block';
        text.textContent = 'Оновлення даних…';
    };

    const showDone = () => {
        pill.style.display = 'flex';
        pill.style.background = 'rgba(22,163,74,.10)';
        pill.style.color = '#16a34a';
        spinner.style.display = 'none';
        text.textContent = '✓ Дані оновлено';
        hideTimer = setTimeout(() => { pill.style.display = 'none'; }, 1400);
    };

    Livewire.hook('commit', ({ succeed, fail }) => {
        active++;
        showLoading();

        const finish = () => {
            active = Math.max(0, active - 1);
            if (active === 0) showDone();
        };

        succeed(finish);
        fail(finish);
    });
});
</script>
HTML : '',
            )
            // ── Sidebar: click the "ERM" brand to collapse/expand ─────────
            // Works with ->sidebarFullyCollapsibleOnDesktop() above.
            // Default state is COLLAPSED: on the very first visit (no
            // persisted value in localStorage yet) we close the store
            // once; afterwards the user's last choice always wins.
            // CAREFUL with the storage key: Alpine's $persist adds its
            // '_x_' prefix ONLY when .as() is not used — Filament calls
            // .as('isOpenDesktop'), so the real key is plain
            // 'isOpenDesktop'. Checking '_x_isOpenDesktop' here was a
            // real bug: "first visit" never ended and the menu collapsed
            // on every page load, ignoring the saved choice. Click is
            // delegated from `document` so it survives livewire:navigate
            // DOM swaps; guests excluded — the login page has a logo too,
            // but no sidebar.
            ->renderHook(
                'panels::body.end',
                fn (): string => auth()->check() ? <<<'HTML'
<style>.fi-logo{cursor:pointer;user-select:none;}</style>
<script>
(() => {
    const sidebarStore = () => window.Alpine?.store('sidebar');

    // Default = collapsed, only when nothing is persisted yet.
    const applyDefaultCollapsed = () => {
        const store = sidebarStore();

        if (! store) {
            setTimeout(applyDefaultCollapsed, 50);

            return;
        }

        if (window.innerWidth >= 1024 && localStorage.getItem('isOpenDesktop') === null) {
            store.close();
        }
    };

    document.addEventListener('DOMContentLoaded', applyDefaultCollapsed);

    if (document.readyState !== 'loading') {
        applyDefaultCollapsed();
    }

    // Brand click = toggle (the logo normally links to the home page —
    // we swallow that; the sidebar is the only thing it should drive).
    document.addEventListener('click', (event) => {
        const logo = event.target.closest('.fi-logo');

        if (! logo) {
            return;
        }

        const store = sidebarStore();

        if (! store) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        store.isOpen ? store.close() : store.open();
    });
})();
</script>
HTML : '',
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
                // Sends a user who opened the "wrong" panel to the one that
                // matches their active role (see config/panels.php).
                RedirectToRolePanel::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
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
            ->map(fn (array $group): NavigationGroup => NavigationGroup::make($group['label'] !== '' ? $group['label'] : null)->items(
                $group['items']->map(
                    fn (array $item): NavigationItem => NavigationItem::make($item['label'])
                        ->icon($item['icon'])
                        ->url($item['url'])
                        ->sort($item['item_sort'])
                        // The custom navigation bypasses Filament's own badge
                        // wiring, so resources keep working badges (e.g. the
                        // count of unaccepted leads) via getNavigationBadge().
                        ->badge(
                            fn (): ?string => isset($item['key']) && is_string($item['key']) && class_exists($item['key']) && method_exists($item['key'], 'getNavigationBadge')
                                ? $item['key']::getNavigationBadge()
                                : null,
                            isset($item['key']) && is_string($item['key']) && class_exists($item['key']) && method_exists($item['key'], 'getNavigationBadgeColor')
                                ? $item['key']::getNavigationBadgeColor()
                                : null,
                        ),
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

                    // The chosen role may live in the OTHER panel (e.g. admin -> Менеджер):
                    // go to that panel's home instead of back to the old page.
                    return redirect(RolePanels::homeUrl(RolePanels::idForRole($roleName)));
                }),
            $roleNames,
        );
    }
}
